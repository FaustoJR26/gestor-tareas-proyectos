<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/tareas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('tareas.cambiar_estado');
    $id = (string)($_POST['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id de la tarea.'], 422);
    }
    $t = tarea_mover(db(), (int)$id, (string)($_POST['estado'] ?? ''), (string)$u['rol']);
    ses_responder(['mensaje' => 'Estado actualizado.', 'estado' => $t['estado']]);
} catch (TareaError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}