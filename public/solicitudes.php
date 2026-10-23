<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/solicitudes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
       $u = perm_exigir('solicitudes.listar');
    if ($u['rol'] === 'administrador') {
        $lista = sol_listar_pendientes(db());
    } else {
        $lista = sol_listar_de_usuario(db(), (int)$u['id']);
    }
    ses_responder(['solicitudes' => $lista]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}
