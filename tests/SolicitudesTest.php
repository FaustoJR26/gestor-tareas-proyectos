<?php

use PHPUnit\Framework\Attributes\DataProvider;
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

    /** Ejecuta la accion y devuelve el error lanzado, o null si no lanzo nada. */
    private function capturarError(callable $accion): ?SolicitudError
    {
        try {
            $accion();
        } catch (SolicitudError $e) {
            return $e;
        }
        return null;
    }

    // ------------------------------------------------------------------
    // RF-GP-01: el Estandar crea una solicitud, que nace Pendiente
    // ------------------------------------------------------------------

    public function test_crear_solicitud_la_deja_en_estado_pendiente(): void
    {
        // RF-GP-01
        // Preparar
        // (usuarios creados en setUp)

        // Actuar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Verificar
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }

    // ------------------------------------------------------------------
    // RF-GP-02: consulta de pendientes y visibilidad del Estandar
    // ------------------------------------------------------------------

    public function test_RF_GP_02_listar_pendientes_devuelve_solo_las_pendientes(): void
    {
        // RF-GP-02
        // Preparar
        $pendiente = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        $resuelta  = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_aprobar($this->db, $resuelta, $this->adminId);

        // Actuar
        $ids = array_map(fn($f) => (int)$f['id'], sol_listar_pendientes($this->db));

        // Verificar
        $this->assertSame([$pendiente], $ids);
    }

    public function test_RF_GP_02_un_estandar_ve_sus_solicitudes_y_no_las_de_otros(): void
    {
        // RF-GP-02
        // Preparar
        $propia = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        $ajena  = sol_crear($this->db, $this->adminId, 'eliminar_tareas');

        // Actuar
        $ids = array_map(fn($f) => (int)$f['id'], sol_listar_de_usuario($this->db, $this->estandarId));

        // Verificar
        $this->assertSame([$propia], $ids);
        $this->assertNotContains($ajena, $ids);
    }

    // ------------------------------------------------------------------
    // RF-GP-03: aprobar registra quien y cuando
    // ------------------------------------------------------------------

    public function test_aprobar_solicitud_pendiente_la_deja_aplicada_y_registra_quien_y_cuando(): void
    {
        // RF-GP-03
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

    // ------------------------------------------------------------------
    // RF-GP-04: rechazar exige motivo
    // ------------------------------------------------------------------

    public function test_rechazar_solicitud_pendiente_la_deja_rechazada_y_guarda_el_motivo(): void
    {
        // RF-GP-04
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

    public function test_rechazar_sin_motivo_se_rechaza_y_la_solicitud_sigue_pendiente(): void
    {
        // RF-GP-04 (motivo en blanco)
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        $error = $this->capturarError(fn() => sol_rechazar($this->db, $id, $this->adminId, '   '));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }

    public function test_RF_GP_04_rechazar_con_motivo_vacio_se_rechaza_y_sigue_pendiente(): void
    {
        // RF-GP-04 (motivo vacio)
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        $error = $this->capturarError(fn() => sol_rechazar($this->db, $id, $this->adminId, ''));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame(422, $error->getCode());
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }

    // ------------------------------------------------------------------
    // RF-GP-05: al aprobar, el cambio se aplica
    // ------------------------------------------------------------------

    public function test_aprobar_solicitud_concede_el_permiso_al_solicitante(): void
    {
        // RF-GP-05
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        sol_aprobar($this->db, $id, $this->adminId);

        // Verificar
        $this->assertTrue(usuario_tiene_permiso($this->db, $this->estandarId, 'eliminar_tareas'));
    }

    // ------------------------------------------------------------------
    // RF-GP-06: solo las transiciones de la tabla
    // ------------------------------------------------------------------

    public static function todasLasTransiciones(): iterable
    {
        $estados = ['pendiente', 'aprobada', 'aplicada', 'rechazada'];
        $permitidas = ['pendiente>aprobada', 'pendiente>rechazada', 'aprobada>aplicada'];
        foreach ($estados as $desde) {
            foreach ($estados as $hacia) {
                yield "$desde -> $hacia" => [$desde, $hacia, in_array("$desde>$hacia", $permitidas, true)];
            }
        }
    }

    #[DataProvider('todasLasTransiciones')]
    public function test_RF_GP_06_la_tabla_de_transiciones_permite_solo_las_declaradas(
        string $desde,
        string $hacia,
        bool $esperada
    ): void {
        // RF-GP-06
        // Preparar
        // (los datos vienen del proveedor)

        // Actuar
        $resultado = sol_transicion_valida($desde, $hacia);

        // Verificar
        $this->assertSame($esperada, $resultado, "Transicion $desde -> $hacia");
    }

    public function test_aprobar_una_solicitud_rechazada_se_rechaza_y_el_estado_no_cambia(): void
    {
        // RF-GP-06
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_rechazar($this->db, $id, $this->adminId, 'No procede');

        // Actuar
        $error = $this->capturarError(fn() => sol_aprobar($this->db, $id, $this->adminId));

        // Verificar
        $this->assertNotNull($error, 'La transicion rechazada -> aprobada debia rechazarse');
        $this->assertSame(409, $error->getCode());
        $this->assertSame('rechazada', sol_obtener($this->db, $id)['estado']);
    }

    public function test_aprobar_dos_veces_la_misma_solicitud_se_rechaza_y_sigue_aplicada(): void
    {
        // RF-GP-06
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_aprobar($this->db, $id, $this->adminId);

        // Actuar
        $error = $this->capturarError(fn() => sol_aprobar($this->db, $id, $this->adminId));

        // Verificar
        $this->assertNotNull($error, 'La segunda aprobacion debia rechazarse');
        $this->assertSame(409, $error->getCode());
        $this->assertSame('aplicada', sol_obtener($this->db, $id)['estado']);
    }

    public function test_RF_GP_06_una_rechazada_no_puede_pasar_a_aplicada(): void
    {
        // RF-GP-06
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_rechazar($this->db, $id, $this->adminId, 'No procede');
        $sol = sol_obtener($this->db, $id);

        // Actuar
        $error = $this->capturarError(fn() => sol_mover($this->db, $sol, 'aplicada'));

        // Verificar
        $this->assertNotNull($error, 'Rechazada -> aplicada debia rechazarse');
        $this->assertSame(409, $error->getCode());
        $this->assertSame('rechazada', sol_obtener($this->db, $id)['estado']);
    }

    public function test_RF_GP_06_una_aprobada_no_puede_volver_a_pendiente(): void
    {
        // RF-GP-06
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        $aprobada = sol_mover($this->db, sol_obtener($this->db, $id), 'aprobada');

        // Actuar
        $error = $this->capturarError(fn() => sol_mover($this->db, $aprobada, 'pendiente'));

        // Verificar
        $this->assertNotNull($error, 'Aprobada -> pendiente debia rechazarse');
        $this->assertSame(409, $error->getCode());
        $this->assertSame('aprobada', sol_obtener($this->db, $id)['estado']);
    }

    public function test_RF_GP_06_rechazar_una_aplicada_se_rechaza_y_sigue_aplicada(): void
    {
        // RF-GP-06
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_aprobar($this->db, $id, $this->adminId);

        // Actuar
        $error = $this->capturarError(fn() => sol_rechazar($this->db, $id, $this->adminId, 'Tarde'));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame(409, $error->getCode());
        $this->assertSame('aplicada', sol_obtener($this->db, $id)['estado']);
    }

    public function test_RF_GP_06_rechazar_una_rechazada_se_rechaza_y_conserva_su_motivo(): void
    {
        // RF-GP-06
        // Preparar
        $id = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_rechazar($this->db, $id, $this->adminId, 'Primer motivo');

        // Actuar
        $error = $this->capturarError(fn() => sol_rechazar($this->db, $id, $this->adminId, 'Otro motivo'));

        // Verificar
        $sol = sol_obtener($this->db, $id);
        $this->assertNotNull($error);
        $this->assertSame(409, $error->getCode());
        $this->assertSame('rechazada', $sol['estado']);
        $this->assertSame('Primer motivo', $sol['motivo_rechazo']);
    }

    // ------------------------------------------------------------------
    // RF-GP-07: nadie resuelve su propia solicitud
    // ------------------------------------------------------------------

    public function test_un_administrador_no_puede_aprobar_su_propia_solicitud(): void
    {
        // RF-GP-07 (aprobar)
        // Preparar
        $id = sol_crear($this->db, $this->adminId, 'eliminar_tareas');

        // Actuar
        $error = $this->capturarError(fn() => sol_aprobar($this->db, $id, $this->adminId));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame(403, $error->getCode());
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }

    public function test_RF_GP_07_un_administrador_no_puede_rechazar_su_propia_solicitud(): void
    {
        // RF-GP-07 (rechazar)
        // Preparar
        $id = sol_crear($this->db, $this->adminId, 'eliminar_tareas');

        // Actuar
        $error = $this->capturarError(fn() => sol_rechazar($this->db, $id, $this->adminId, 'Con motivo'));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame(403, $error->getCode());
        $this->assertSame('pendiente', sol_obtener($this->db, $id)['estado']);
    }
}