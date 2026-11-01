<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/tareas.php';
require_once __DIR__ . '/../src/solicitudes.php';

final class IntegracionTareasPermisosTest extends TestCase
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

    private function existeTarea(int $id): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM tareas WHERE id = ?');
        $st->execute([$id]);
        return (int)$st->fetchColumn() === 1;
    }

    public function test_eliminar_tarea_se_rechaza_mientras_la_solicitud_no_esta_aprobada(): void
    {
        // Preparar: el Estándar tiene una tarea y una solicitud que sigue pendiente
        $tareaId = tarea_crear($this->db, $this->estandarId, 'Tarea a eliminar');
        sol_crear($this->db, $this->estandarId, 'eliminar_tareas');

        // Actuar
        $error = null;
        try {
            tarea_eliminar($this->db, $this->estandarId, $tareaId);
        } catch (TareaError $e) {
            $error = $e;
        }

        // Verificar
        $this->assertNotNull($error);
        $this->assertSame(403, $error->getCode());
        $this->assertTrue($this->existeTarea($tareaId));
    }

    public function test_eliminar_tarea_se_permite_despues_de_que_el_administrador_aprueba(): void
    {
        // Preparar: el Estándar solicita el permiso y el Administrador lo aprueba
        $tareaId = tarea_crear($this->db, $this->estandarId, 'Tarea a eliminar');
        $solicitudId = sol_crear($this->db, $this->estandarId, 'eliminar_tareas');
        sol_aprobar($this->db, $solicitudId, $this->adminId);

        // Actuar
        tarea_eliminar($this->db, $this->estandarId, $tareaId);

        // Verificar
        $this->assertFalse($this->existeTarea($tareaId));
    }
}