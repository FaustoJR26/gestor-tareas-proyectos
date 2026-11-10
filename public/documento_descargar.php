<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/documentos.php';

try {
    $u = perm_exigir('documentos.descargar');
    $id = (string)($_GET['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id del documento.'], 422);
    }
    [$doc, $ruta] = doc_para_descarga(db(), (int)$u['id'], (string)$u['rol'], (int)$id);

    header('Content-Type: ' . $doc['tipo']);
    header('Content-Length: ' . $doc['tamano']);
    header('Content-Disposition: attachment; filename="' . str_replace(['"', '\\'], '_', $doc['nombre']) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($ruta);
    exit;
} catch (DocumentoError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}