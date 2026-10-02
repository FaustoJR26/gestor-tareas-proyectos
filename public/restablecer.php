<?php
declare(strict_types=1);
require __DIR__ . '/../src/recuperacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    [$codigo, $datos] = rec_restablecer((string)($_POST['codigo'] ?? ''), (string)($_POST['password'] ?? ''));
    ses_responder($datos, $codigo);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}