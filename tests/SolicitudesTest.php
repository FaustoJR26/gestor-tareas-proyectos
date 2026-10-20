<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/solicitudes.php';

final class SolicitudesTest extends TestCase
{
    private PDO $db;
    private int $estandarId;
    private int $adminId;

    protected function setUp(): void
    {
        $this->db = new PDO(
            'mysql:host=localhost;dbname=gestor_tareas_test;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['permisos_usuario', 'solicitudes_permiso', 'sesiones', 'tokens', 'usuarios'] as $tabla) {
            $this->db->exec("TRUNCATE TABLE $tabla");
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->estandarId = $this->crearUsuario('estandar@test.com', 'estandar');
        $this->adminId    = $this->crearUsuario('admin@test.com', 'administrador');
    }

    private function crearUsuario(string $correo, string $rol): int
    {
        $this->db->prepare("INSERT INTO usuarios (correo, password_hash, rol, estado)
                            VALUES (?, 'hash-de-prueba', ?, 'activo')")
                 ->execute([$correo, $rol]);
        return (int)$this->db->lastInsertId();
    }

    /** Ejecuta la acción y devuelve el error lanzado, o null si no lanzó nada. */
    private function capturarError(callable $accion): ?SolicitudError
    {
        try {
            $accion();
        } catch (SolicitudError $e) {
            return $e;
        }
        return null;
    }

    public function test_crear_solicitud_la_deja_en_estado_pendiente(): void
    {
        // Preparar
        // (usuarios creados en setUp)

        // Actuar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Verificar
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }

    public function test_aprobar_solicitud_pendiente_la_deja_aplicada_y_registra_quien_y_cuando(): void
    {
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        sol_aprobar($this->db, $id, $this->adminId);

        // Verificar
        $sol = sol_obtener($this->db, $id);
        $this->assertSame('aplicada', $sol['estado']);
        $this->assertSame($this->adminId, (int)$sol['resuelto_por']);
        $this->assertNotNull($sol['resuelto_en']);
    }

    public function test_aprobar_solicitud_concede_el_permiso_al_solicitante(): void
    {
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        sol_aprobar($this->db, $id, $this->adminId);

        // Verificar
        $this->assertTrue(usuario_tiene_permiso($this->db, $this->estandarId, 'eliminar_tareas'));
    }

    public function test_rechazar_solicitud_pendiente_la_deja_rechazada_y_guarda_el_motivo(): void
    {
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        sol_rechazar($this->db, $id, $this->adminId, 'No lo necesitas');

        // Verificar
        $sol = sol_obtener($this->db, $id);
        $this->assertSame('rechazada', $sol['estado']);
        $this->assertSame('No lo necesitas', $sol['motivo_rechazo']);
        $this->assertSame($this->adminId, (int)$sol['resuelto_por']);
    }

    public function test_aprobar_una_solicitud_rechazada_se_rechaza_y_el_estado_no_cambia(): void
    {
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_rechazar($this->db, $id, $this->adminId, 'No procede');

        // Actuar
        $error = $this->capturarError(fn() => sol_aprobar($this->db, $id, $this->adminId));

        // Verificar
        $this->assertNotNull($error, 'La transición rechazada → aprobada debía rechazarse');
        $this->assertSame(409, $error->getCode());
        $this->assertSame('rechazada', sol_obtener($this->db, $id)['estado']);
    }

    public function test_aprobar_dos_veces_la_misma_solicitud_se_rechaza_y_sigue_aplicada(): void
    {
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_aprobar($this->db, $id, $this->adminId);

        // Actuar
        $error = $this->capturarError(fn() => sol_aprobar($this->db, $id, $this->adminId));

        // Verificar
        $this->assertNotNull($error, 'La segunda aprobación debía rechazarse');
        $this->assertSame('aplicada', sol_obtener($this->db, $id)['estado']);
    }

    public function test_rechazar_sin_motivo_se_rechaza_y_la_solicitud_sigue_pendiente(): void
    {
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        $error = $this->capturarError(fn() => sol_rechazar($this->db, $id, $this->adminId, '   '));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }

    public function test_un_administrador_no_puede_aprobar_su_propia_solicitud(): void
    {
        // Preparar
        $id = sol_crear($this->db, $this->adminId, 'eliminar_tareas');

        // Actuar
        $error = $this->capturarError(fn() => sol_aprobar($this->db, $id, $this->adminId));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame(403, $error->getCode());
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }
}