<?php
function cabecera(string $titulo): void { ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> - Gestor de tareas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 480px;">
<h1 class="h4 mb-4"><?= e($titulo) ?></h1>
<?php }

function pie(): void { ?>
</div>
</body>
</html>
<?php }