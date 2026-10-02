<?php
require_once __DIR__ . '/../src/usuarios.php';
require_once __DIR__ . '/../src/vista.php';

$token = (string)($_GET['token'] ?? '');
$ok = $token !== '' && activarCuenta($token);

cabecera('Activación de cuenta');
if ($ok) {
    echo '<div class="alert alert-success">Tu cuenta fue activada. Ya puedes iniciar sesión.</div>';
} else {
    echo '<div class="alert alert-danger">El enlace no es válido, ya fue usado o venció.</div>';
}
pie();