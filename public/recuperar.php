<?php
declare(strict_types=1);
require __DIR__ . '/../src/recuperacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $correo = trim(mb_strtolower((string)($_POST['correo'] ?? '')));
    if (!correoValido($correo)) {
        ses_responder(['error' => 'Escribe un correo válido.'], 422);
    }
    rec_solicitar($correo);
    ses_responder(['mensaje' => REC_MSG_SOLICITUD]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}