<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/documentos.php';

try {
    $u = perm_exigir('documentos.listar');
    ses_responder(['documentos' => doc_listar(db(), (int)$u['id'], (string)$u['rol'])]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}