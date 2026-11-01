<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/tareas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    perm_exigir('tareas.ver');
    $id = (string)($_GET['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id de la tarea.'], 422);
    }
    ses_responder(['tarea' => tarea_obtener(db(), (int)$id)]);
} catch (TareaError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}