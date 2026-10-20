<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/solicitudes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('tareas.eliminar');
    if (!usuario_tiene_permiso(db(), (int)$u['id'], 'eliminar_tareas')) {
        ses_responder(['error' => 'No tienes el permiso eliminar_tareas. Solicítalo a un Administrador.'], 403);
    }
    $id = (string)($_POST['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id de la tarea.'], 422);
    }
    $st = db()->prepare('DELETE FROM tareas WHERE id = ?');
    $st->execute([(int)$id]);
    if ($st->rowCount() === 0) {
        ses_responder(['error' => 'La tarea no existe.'], 404);
    }
    ses_responder(['mensaje' => 'Tarea eliminada.']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}