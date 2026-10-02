<?php
require_once __DIR__ . '/helpers.php';

// Crea un token de un solo uso; invalida los anteriores del mismo tipo.
function crearToken(int $usuarioId, string $tipo, int $minutos): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + $minutos * 60);

    db()->prepare('UPDATE tokens SET usado = 1 WHERE usuario_id = ? AND tipo = ? AND usado = 0')
        ->execute([$usuarioId, $tipo]);
    db()->prepare('INSERT INTO tokens (usuario_id, tipo, token_hash, expira_en) VALUES (?, ?, ?, ?)')
        ->execute([$usuarioId, $tipo, $hash, $expira]);

    return $token;
}

// Marca el token como usado si es válido. Devuelve el id del usuario o null.
function consumirToken(string $token, string $tipo): ?int {
    $hash = hash('sha256', $token);
    $ahora = date('Y-m-d H:i:s');

    $st = db()->prepare(
        'UPDATE tokens SET usado = 1
         WHERE token_hash = ? AND tipo = ? AND usado = 0 AND expira_en > ?'
    );
    $st->execute([$hash, $tipo, $ahora]);
    if ($st->rowCount() !== 1) {
        return null;
    }

    $st = db()->prepare('SELECT usuario_id FROM tokens WHERE token_hash = ? AND tipo = ?');
    $st->execute([$hash, $tipo]);
    $fila = $st->fetch();
    return $fila ? (int)$fila['usuario_id'] : null;
}