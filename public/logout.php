<?php
declare(strict_types=1);
require __DIR__ . '/../src/sesion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    ses_cerrar();
    ses_responder(['mensaje' => 'Sesión cerrada.']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}