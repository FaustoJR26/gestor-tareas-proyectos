<?php
declare(strict_types=1);
require __DIR__ . '/../src/admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    $accion = (string)($_POST['accion'] ?? '');
    $u = perm_exigir($accion === 'reactivar' ? 'usuarios.reactivar' : 'usuarios.desactivar');
    $id = (string)($_POST['id'] ?? '');
    if (!ctype_digit($id) || !in_array($accion, ['desactivar', 'reactivar'], true)) {
        ses_responder(['error' => 'Indica el id y la acción (desactivar o reactivar).'], 422);
    }
    [$codigo, $datos] = adm_cambiar_estado((int)$u['id'], (int)$id, $accion);
    ses_responder($datos, $codigo);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}