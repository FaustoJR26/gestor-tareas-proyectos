<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/tokens.php';
require_once __DIR__ . '/cola.php';

const REC_VENCE_MIN     = 30;
const REC_MSG_SOLICITUD = 'Si el correo está registrado, recibirás un código para restablecer tu contraseña.';

/** Invalida códigos anteriores, crea uno nuevo y lo deja en la cola de correos. */
function rec_encolar_codigo(int $usuarioId, string $correo, string $motivo): void
{
    db()->prepare("UPDATE tokens SET usado = 1 WHERE usuario_id = ? AND tipo = 'recuperacion' AND usado = 0")
        ->execute([$usuarioId]);
    $codigo = crearToken($usuarioId, 'recuperacion', REC_VENCE_MIN);
    encolarCorreo(
        $correo,
        'Código para restablecer tu contraseña',
        "Hola,\n\n$motivo\n\nTu código es:\n\n$codigo\n\nVence en " . REC_VENCE_MIN
        . " minutos y solo sirve una vez. Si no lo pediste, ignora este mensaje.\n"
    );
}

/** RF-CA-09 y RF-CA-10. No devuelve nada: la respuesta al usuario es siempre la misma. */
function rec_solicitar(string $correo): void
{
    $correo = trim(mb_strtolower($correo));
    $pdo = db();
    try {
        $st = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND estado = 'activo'");
        $st->execute([$correo]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        if ($fila) {
            $pdo->beginTransaction();
            rec_encolar_codigo((int)$fila['id'], $correo, 'Pediste restablecer tu contraseña.');
            $pdo->commit();
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
    }
}

/** RF-CA-11, RF-CA-12, RF-CA-14. Devuelve [código HTTP, datos]. */
function rec_restablecer(string $codigo, string $password): array
{
    if ($error = errorPassword($password)) {
        return [422, ['error' => $error]]; // no se gasta el código
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $id = consumirToken(trim($codigo), 'recuperacion');
        if ($id === null) {
            $pdo->rollBack();
            return [400, ['error' => 'Código no válido o vencido.']];
        }
        $pdo->prepare('UPDATE usuarios SET password_hash = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        ses_invalidar_todas($id);
        $pdo->commit();
        return [200, ['mensaje' => 'Contraseña actualizada. Inicia sesión con la nueva.']];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return [500, ['error' => 'No se pudo procesar la solicitud.']];
    }
}

/** RF-CA-22, RF-CA-14, RF-CA-12. Devuelve [código HTTP, datos]. */
function rec_cambiar_propia(int $usuarioId, string $actual, string $nueva): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $st->execute([$usuarioId]);
    $hash = $st->fetchColumn();
    if (!is_string($hash) || !password_verify($actual, $hash)) {
        return [403, ['error' => 'La contraseña actual es incorrecta.']];
    }
    if ($error = errorPassword($nueva)) {
        return [422, ['error' => $error]];
    }
    $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($nueva, PASSWORD_DEFAULT), $usuarioId]);
    ses_invalidar_todas($usuarioId);
    return [200, ['mensaje' => 'Contraseña cambiada. Inicia sesión de nuevo.']];
}

/** RF-CA-13. La contraseña anterior deja de servir y se envía un código por la cola. */
function rec_forzar(int $id): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT correo FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    $correo = $st->fetchColumn();
    if (!is_string($correo)) {
        return [404, ['error' => 'Usuario no encontrado.']];
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $id]);
        ses_invalidar_todas($id);
        rec_encolar_codigo($id, $correo, 'Un administrador restableció tu contraseña. Usa el código para definir una nueva.');
        $pdo->commit();
        return [200, ['mensaje' => 'Contraseña restablecida. Se envió un código al usuario.']];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return [500, ['error' => 'No se pudo procesar la solicitud.']];
    }
}