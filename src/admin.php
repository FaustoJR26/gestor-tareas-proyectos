<?php
declare(strict_types=1);

require_once __DIR__ . '/permisos.php';

const ADM_ROLES = ['administrador', 'estandar'];

/** RF-CA-21: nunca incluye password_hash ni tokens. */
function adm_listar(): array
{
    return db()->query('SELECT id, correo, rol, estado, creado_en FROM usuarios ORDER BY id')
        ->fetchAll(PDO::FETCH_ASSOC);
}

/** RF-CA-08. Devuelve [código HTTP, datos]. */
function adm_cambiar_rol(int $id, string $rol): array
{
    if (!in_array($rol, ADM_ROLES, true)) {
        return [422, ['error' => 'Rol no válido. Usa administrador o estandar.']];
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return [404, ['error' => 'Usuario no encontrado.']];
    }
    if ($u['rol'] === 'administrador' && $rol === 'estandar') {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador' AND estado = 'activo'")
            ->fetchColumn();
        if ($n <= 1) {
            return [409, ['error' => 'No se puede quitar el rol al único administrador activo.']];
        }
    }
    $pdo->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
    return [200, ['mensaje' => 'Rol actualizado.']];
}

/** RF-CA-20. $accion es 'desactivar' o 'reactivar'. Devuelve [código HTTP, datos]. */
function adm_cambiar_estado(int $actorId, int $id, string $accion): array
{
    if ($accion === 'desactivar' && $id === $actorId) {
        return [409, ['error' => 'Un administrador no puede desactivarse a sí mismo.']];
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT estado FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return [404, ['error' => 'Usuario no encontrado.']];
    }

    if ($accion === 'desactivar') {
        if ($u['estado'] !== 'activo') {
            return [409, ['error' => 'Solo se puede desactivar a un usuario activo.']];
        }
        $pdo->prepare("UPDATE usuarios SET estado = 'desactivado' WHERE id = ?")->execute([$id]);
        ses_invalidar_todas($id);
        return [200, ['mensaje' => 'Usuario desactivado.']];
    }

    if ($u['estado'] !== 'desactivado') {
        return [409, ['error' => 'Solo se puede reactivar a un usuario desactivado.']];
    }
    $pdo->prepare("UPDATE usuarios SET estado = 'activo', intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?")
        ->execute([$id]);
    return [200, ['mensaje' => 'Usuario reactivado.']];
}