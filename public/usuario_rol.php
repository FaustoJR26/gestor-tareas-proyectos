<?php
declare(strict_types=1);
require __DIR__ . '/../src/admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    perm_exigir('usuarios.cambiar_rol');
    $id = (string)($_POST['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id del usuario.'], 422);
    }
    [$codigo, $datos] = adm_cambiar_rol((int)$id, (string)($_POST['rol'] ?? ''));
    ses_responder($datos, $codigo);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}