<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

const SES_MAX_INTENTOS = 5;
const SES_BLOQUEO_MIN  = 15;
const SES_MSG_GENERICO = 'Correo o contraseña incorrectos.';
const SES_MSG_INVALIDA = 'Sesión no válida.';

function ses_responder(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function ses_token_peticion(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        $todas = apache_request_headers();
        $h = $todas['Authorization'] ?? $todas['authorization'] ?? '';
    }
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $h, $m)) {
        return strtolower($m[1]);
    }
    $c = $_COOKIE['sesion'] ?? '';
    return preg_match('/^[a-f0-9]{64}$/', $c) ? $c : null;
}

/** Devuelve [código HTTP, datos]. */
function ses_iniciar(string $correo, string $password): array
{
    $pdo = db();
    $correo = trim(mb_strtolower($correo));

    if ($correo === '' || $password === '' || !correoValido($correo)) {
        return [401, ['error' => SES_MSG_GENERICO]];
    }

    $st = $pdo->prepare(
        'SELECT id, password_hash, estado, intentos_fallidos,
                (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado
         FROM usuarios WHERE correo = ?'
    );
    $st->execute([$correo]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        password_hash($password, PASSWORD_DEFAULT); // mismo costo que un usuario real
        return [401, ['error' => SES_MSG_GENERICO]];
    }

    if ((int)$u['bloqueado'] === 1) {
        return [423, ['error' => 'Cuenta bloqueada temporalmente por intentos fallidos. Intenta de nuevo en ' . SES_BLOQUEO_MIN . ' minutos.']];
    }

    if (!password_verify($password, $u['password_hash'])) {
        $n = (int)$u['intentos_fallidos'] + 1;
        if ($n >= SES_MAX_INTENTOS) {
            $pdo->prepare(
                'UPDATE usuarios SET intentos_fallidos = 0,
                 bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . SES_BLOQUEO_MIN . ' MINUTE) WHERE id = ?'
            )->execute([$u['id']]);
        } else {
            $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?')
                ->execute([$n, $u['id']]);
        }
        return [401, ['error' => SES_MSG_GENERICO]];
    }

    if ($u['estado'] === 'pendiente') {
        return [403, ['error' => 'La cuenta no está activa. Abre el enlace de activación enviado a tu correo.']];
    }
    if ($u['estado'] === 'desactivado') {
        return [403, ['error' => 'La cuenta está desactivada.']];
    }

    $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')
        ->execute([$u['id']]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO sesiones (usuario_id, token_hash) VALUES (?, ?)')
        ->execute([$u['id'], hash('sha256', $token)]);

    setcookie('sesion', $token, [
        'expires'  => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return [200, ['mensaje' => 'Sesión iniciada.', 'token' => $token]];
}

function ses_usuario_actual(): ?array
{
    $token = ses_token_peticion();
    if ($token === null) {
        return null;
    }
    $st = db()->prepare(
        "SELECT u.id, u.correo, u.rol, u.estado
         FROM sesiones s JOIN usuarios u ON u.id = s.usuario_id
         WHERE s.token_hash = ? AND u.estado = 'activo'"
    );
    $st->execute([hash('sha256', $token)]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

function ses_cerrar(): void
{
    $token = ses_token_peticion();
    if ($token !== null) {
        db()->prepare('DELETE FROM sesiones WHERE token_hash = ?')
            ->execute([hash('sha256', $token)]);
    }
    setcookie('sesion', '', ['expires' => time() - 3600, 'path' => '/']);
}

/** Para cambio de contraseña o desactivación (se usa en los PR siguientes). */
function ses_invalidar_todas(int $usuario_id): void
{
    db()->prepare('DELETE FROM sesiones WHERE usuario_id = ?')->execute([$usuario_id]);
}