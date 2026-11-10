<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/documentos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('documentos.eliminar');
    $id = (string)($_POST['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id del documento.'], 422);
    }
    doc_eliminar(db(), (int)$u['id'], (string)$u['rol'], (int)$id);
    ses_responder(['mensaje' => 'Documento eliminado.']);
} catch (DocumentoError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}