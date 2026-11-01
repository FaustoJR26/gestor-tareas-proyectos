<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/tareas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('tareas.crear');
    $desc = isset($_POST['descripcion']) ? (string)$_POST['descripcion'] : null;
    $id = tarea_crear(db(), (int)$u['id'], (string)($_POST['titulo'] ?? ''), $desc);
    ses_responder(['mensaje' => 'Tarea creada.', 'id' => $id, 'estado' => 'pendiente'], 201);
} catch (TareaError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}