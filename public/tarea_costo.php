<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/tareas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    perm_exigir('tareas.costo');
    $id = (string)($_POST['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id de la tarea.'], 422);
    }
    $tarea = tarea_fijar_costo(
        db(),
        (int)$id,
        (string)($_POST['costo'] ?? ''),
        (string)($_POST['moneda'] ?? 'USD')
    );
    ses_responder(['tarea' => $tarea]);
} catch (TareaError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}