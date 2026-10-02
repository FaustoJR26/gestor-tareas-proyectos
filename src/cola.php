<?php
require_once __DIR__ . '/helpers.php';

function encolarCorreo(string $destinatario, string $asunto, string $cuerpo): void {
    $st = db()->prepare(
        'INSERT INTO correos_en_cola (destinatario, asunto, cuerpo) VALUES (?, ?, ?)'
    );
    $st->execute([$destinatario, $asunto, $cuerpo]);
}