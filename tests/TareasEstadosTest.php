<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/tareas.php';

final class TareasEstadosTest extends TestCase
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
        foreach (['tareas', 'permisos_usuario', 'solicitudes_permiso', 'sesiones', 'tokens', 'usuarios'] as $tabla) {
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

    /** Inserta una tarea directamente en el estado indicado. */
    private function tareaEn(string $estado): int
    {
        $this->db->prepare('INSERT INTO tareas (titulo, estado, creado_por) VALUES (?, ?, ?)')
                 ->execute(['Tarea de prueba', $estado, $this->estandarId]);
        return (int)$this->db->lastInsertId();
    }

    /** Ejecuta la accion y devuelve el error lanzado, o null si no lanzo nada. */
    private function capturarError(callable $accion): ?TareaError
    {
        try {
            $accion();
        } catch (TareaError $e) {
            return $e;
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Transiciones permitidas
    // ------------------------------------------------------------------

    public function test_pendiente_pasa_a_en_progreso(): void
    {
        // Preparar
        $id = $this->tareaEn('pendiente');

        // Actuar
        tarea_mover($this->db, $id, 'en_progreso', 'estandar');

        // Verificar
        $this->assertSame('en_progreso', tarea_obtener($this->db, $id)['estado']);
    }

    public function test_en_progreso_pasa_a_en_revision(): void
    {
        // Preparar
        $id = $this->tareaEn('en_progreso');

        // Actuar
        tarea_mover($this->db, $id, 'en_revision', 'estandar');

        // Verificar
        $this->assertSame('en_revision', tarea_obtener($this->db, $id)['estado']);
    }

    public function test_en_revision_pasa_a_completada_si_lo_hace_un_administrador(): void
    {
        // Preparar
        $id = $this->tareaEn('en_revision');

        // Actuar
        tarea_mover($this->db, $id, 'completada', 'administrador');

        // Verificar
        $this->assertSame('completada', tarea_obtener($this->db, $id)['estado']);
    }

    public function test_en_revision_vuelve_a_en_progreso_si_lo_hace_un_administrador(): void
    {
        // Preparar
        $id = $this->tareaEn('en_revision');

        // Actuar
        tarea_mover($this->db, $id, 'en_progreso', 'administrador');

        // Verificar
        $this->assertSame('en_progreso', tarea_obtener($this->db, $id)['estado']);
    }

    // ------------------------------------------------------------------
    // Condición de la transición: quién la ejecuta
    // ------------------------------------------------------------------

    public function test_un_estandar_no_puede_completar_una_tarea_en_revision(): void
    {
        // Preparar
        $id = $this->tareaEn('en_revision');

        // Actuar
        $error = $this->capturarError(fn() => tarea_mover($this->db, $id, 'completada', 'estandar'));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame('en_revision', tarea_obtener($this->db, $id)['estado']);
    }

    // ------------------------------------------------------------------
    // Transiciones prohibidas: se rechazan y el estado no cambia
    // ------------------------------------------------------------------

    public function test_pendiente_no_puede_pasar_a_completada_y_el_estado_no_cambia(): void
    {
        // Preparar
        $id = $this->tareaEn('pendiente');

        // Actuar
        $error = $this->capturarError(fn() => tarea_mover($this->db, $id, 'completada', 'administrador'));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame('pendiente', tarea_obtener($this->db, $id)['estado']);
    }

    public function test_completada_no_puede_reabrirse_a_en_progreso_y_el_estado_no_cambia(): void
    {
        // Preparar
        $id = $this->tareaEn('completada');

        // Actuar
        $error = $this->capturarError(fn() => tarea_mover($this->db, $id, 'en_progreso', 'administrador'));

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame('completada', tarea_obtener($this->db, $id)['estado']);
    }

    // ------------------------------------------------------------------
    // Estado terminal: de completada no sale ninguna transición
    // ------------------------------------------------------------------

    public function test_de_completada_no_sale_ninguna_transicion(): void
    {
        // Preparar
        $id = $this->tareaEn('completada');
        $intentos = [];

        // Actuar
        foreach (['pendiente', 'en_progreso', 'en_revision', 'completada'] as $destino) {
            $intentos[$destino] = $this->capturarError(
                fn() => tarea_mover($this->db, $id, $destino, 'administrador')
            ) !== null;
        }

        // Verificar
        $this->assertSame(
            ['pendiente' => true, 'en_progreso' => true, 'en_revision' => true, 'completada' => true],
            $intentos
        );
        $this->assertSame('completada', tarea_obtener($this->db, $id)['estado']);
    }
}