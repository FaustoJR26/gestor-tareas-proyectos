<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/documentos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('documentos.subir');
    $doc = doc_subir(db(), (int)$u['id'], $_FILES['archivo'] ?? null);
    ses_responder(['mensaje' => 'Documento subido.', 'documento' => $doc], 201);
} catch (DocumentoError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}