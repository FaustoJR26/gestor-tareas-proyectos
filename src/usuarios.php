<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/tokens.php';
require_once __DIR__ . '/cola.php';

function encolarActivacion(int $usuarioId, string $correo): void {
    $token = crearToken($usuarioId, 'activacion', 60 * 24);
    $enlace = rtrim(env('APP_URL', ''), '/') . '/activar.php?token=' . $token;
    encolarCorreo(
        $correo,
        'Activa tu cuenta',
        "Hola,\n\nPara activar tu cuenta abre este enlace (vence en 24 horas y solo sirve una vez):\n\n$enlace\n"
    );
}

function registrarUsuario(string $correo, string $password): array {
    $correo = trim(mb_strtolower($correo));
    if (!correoValido($correo)) {
        return [false, 'Escribe un correo válido.'];
    }
    if ($error = errorPassword($password)) {
        return [false, $error];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ?');
        $st->execute([$correo]);
        if ($st->fetch()) {
            $pdo->rollBack();
            return [false, 'Ese correo ya está registrado.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO usuarios (correo, password_hash) VALUES (?, ?)')
            ->execute([$correo, $hash]);
        $id = (int)$pdo->lastInsertId();

        encolarActivacion($id, $correo);
        $pdo->commit();
        return [true, 'Cuenta creada. Revisa tu correo para activarla.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex instanceof PDOException && $ex->getCode() === '23000') {
            return [false, 'Ese correo ya está registrado.'];
        }
        return [false, 'No se pudo completar el registro. Intenta de nuevo.'];
    }
}

function activarCuenta(string $token): bool {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $usuarioId = consumirToken($token, 'activacion');
        if ($usuarioId === null) {
            $pdo->rollBack();
            return false;
        }
        $st = $pdo->prepare("UPDATE usuarios SET estado = 'activo' WHERE id = ? AND estado = 'pendiente'");
        $st->execute([$usuarioId]);
        $pdo->commit();
        return $st->rowCount() === 1;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}