<?php
date_default_timezone_set('America/Santo_Domingo');
require_once __DIR__ . '/../config/database.php';

function e(string $texto): string {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function correoValido(string $correo): bool {
    return $correo !== '' && strlen($correo) <= 190
        && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
}

function errorPassword(string $password): ?string {
    if (strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'La contraseña debe incluir letras y números.';
    }
    return null;
}