<?php
require_once __DIR__ . '/../src/usuarios.php';
require_once __DIR__ . '/../src/vista.php';

$mensaje = null;
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ok, $mensaje] = registrarUsuario((string)($_POST['correo'] ?? ''), (string)($_POST['password'] ?? ''));
}

cabecera('Crear cuenta');
if ($mensaje) {
    echo '<div class="alert alert-' . ($ok ? 'success' : 'danger') . '">' . e($mensaje) . '</div>';
}
?>
<form method="post" novalidate>
  <div class="mb-3">
    <label class="form-label">Correo</label>
    <input type="email" name="correo" class="form-control" value="<?= e((string)($_POST['correo'] ?? '')) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Contraseña</label>
    <input type="password" name="password" class="form-control">
    <div class="form-text">Mínimo 8 caracteres, con letras y números.</div>
  </div>
  <button class="btn btn-primary w-100">Registrarme</button>
</form>
<?php pie();