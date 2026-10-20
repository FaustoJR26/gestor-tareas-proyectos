<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/solicitudes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $u = perm_exigir('solicitudes.rechazar');
    $id = (string)($_POST['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id de la solicitud.'], 422);
    }
    sol_rechazar(db(), (int)$id, (int)$u['id'], (string)($_POST['motivo'] ?? ''));
    ses_responder(['mensaje' => 'Solicitud rechazada.', 'estado' => 'rechazada']);
} catch (SolicitudError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}