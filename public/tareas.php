<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/tareas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('tareas.listar');
    ses_responder(['tareas' => tarea_listar(db(), (int)$u['id'], (string)$u['rol'])]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}