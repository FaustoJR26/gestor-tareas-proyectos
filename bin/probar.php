<?php
declare(strict_types=1);

// Crea la base de pruebas si no existe y ejecuta toda la suite.
$raiz = dirname(__DIR__);
$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$existe = $pdo->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'gestor_tareas_test'")->fetchColumn();
if (!$existe) {
    $pdo->exec('CREATE DATABASE gestor_tareas_test CHARACTER SET utf8mb4');
}
$pdo->exec('USE gestor_tareas_test');

foreach (['schema.sql', 'tareas.sql', 'permisos.sql'] as $archivo) {
    $sql = file_get_contents("$raiz/database/$archivo");
    foreach (preg_split('/;\s*[\r\n]+/', $sql) as $sentencia) {
        $sentencia = trim($sentencia);
        if ($sentencia === '') {
            continue;
        }
        if (preg_match('/^CREATE TABLE\s+(?!IF NOT EXISTS)(\w+)/i', $sentencia, $m)) {
            if ($pdo->query("SHOW TABLES LIKE '{$m[1]}'")->fetchColumn()) {
                continue;
            }
        }
        $pdo->exec($sentencia);
    }
}

passthru('"' . PHP_BINARY . '" "' . $raiz . '/vendor/bin/phpunit"', $codigo);
exit($codigo);