<?php
require_once __DIR__ . '/../src/usuarios.php';
require_once __DIR__ . '/../src/vista.php';

$enviado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reenviarActivacion((string)($_POST['correo'] ?? ''));
    $enviado = true;
}

cabecera('Reenviar enlace de activación');
if ($enviado) {
    echo '<div class="alert alert-info">Si el correo corresponde a una cuenta pendiente, recibirás un nuevo enlace.</div>';
}
?>
<form method="post" novalidate>
  <div class="mb-3">
    <label class="form-label">Correo</label>
    <input type="email" name="correo" class="form-control">
  </div>
  <button class="btn btn-primary w-100">Reenviar enlace</button>
</form>
<?php pie();