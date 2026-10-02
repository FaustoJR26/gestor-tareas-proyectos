<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/recuperacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('sesion.cambiar_password');
    [$codigo, $datos] = rec_cambiar_propia((int)$u['id'], (string)($_POST['actual'] ?? ''), (string)($_POST['nueva'] ?? ''));
    ses_responder($datos, $codigo);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}