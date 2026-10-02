<?php
function cargarEnv(string $ruta): void {
    if (!is_file($ruta)) return;
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
        [$k, $v] = explode('=', $linea, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

function env(string $clave, ?string $defecto = null): ?string {
    $v = $_ENV[$clave] ?? getenv($clave);
    return ($v === false || $v === null || $v === '') ? $defecto : $v;
}

cargarEnv(__DIR__ . '/../.env');