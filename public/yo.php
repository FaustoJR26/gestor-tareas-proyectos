<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';

try {
    $u = perm_exigir('sesion.consultar');
    ses_responder(['id' => (int)$u['id'], 'correo' => $u['correo'], 'rol' => $u['rol']]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}