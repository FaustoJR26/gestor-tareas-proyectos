<?php
declare(strict_types=1);
require __DIR__ . '/../src/admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    perm_exigir('usuarios.listar');
    ses_responder(['usuarios' => adm_listar()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}