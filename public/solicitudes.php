<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/solicitudes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    perm_exigir('solicitudes.listar');
    ses_responder(['solicitudes' => sol_listar_pendientes(db())]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}
