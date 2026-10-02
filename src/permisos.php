<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';

/**
 * ÚNICO lugar donde se declara qué rol exige cada operación.
 * Valores: 'autenticado' (cualquier usuario con sesión) o 'administrador'.
 * Las operaciones públicas (registro, activación, login) no exigen sesión.
 * Una operación que no esté aquí se trata como solo-administrador.
 */
const PERM_OPERACIONES = [
    'sesion.consultar'     => 'autenticado',
    'usuarios.listar'      => 'administrador',
    'usuarios.cambiar_rol' => 'administrador',
    'usuarios.desactivar'  => 'administrador',
    'usuarios.reactivar'   => 'administrador',
];

/** Se llama al inicio de cada operación protegida. Rechaza en el servidor. */
function perm_exigir(string $operacion): array
{
    $exigido = PERM_OPERACIONES[$operacion] ?? 'administrador';

    $u = ses_usuario_actual();
    if ($u === null) {
        ses_responder(['error' => SES_MSG_INVALIDA], 401);
    }
    if ($exigido === 'administrador' && $u['rol'] !== 'administrador') {
        ses_responder(['error' => 'No tienes permiso para realizar esta operación.'], 403);
    }
    return $u;
}