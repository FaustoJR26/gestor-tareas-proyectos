<?php
declare(strict_types=1);
require __DIR__ . '/../src/sesion.php';

try {
    $u = ses_usuario_actual();
    if ($u === null) {
        ses_responder(['error' => SES_MSG_INVALIDA], 401);
    }
    ses_responder(['id' => (int)$u['id'], 'correo' => $u['correo'], 'rol' => $u['rol']]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}
