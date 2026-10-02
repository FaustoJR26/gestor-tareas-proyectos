<?php
// Uso: php bin/enviar_correos.php
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../vendor-manual/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor-manual/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor-manual/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

$pdo = db();
$ids = $pdo->query("SELECT id FROM correos_en_cola WHERE estado = 'pendiente' ORDER BY id")
           ->fetchAll(PDO::FETCH_COLUMN);

$enviados = 0;
$fallidos = 0;

foreach ($ids as $id) {
    // Reclamar el correo: si otro proceso ya lo tomó, se omite (evita duplicados).
    $reclamo = $pdo->prepare(
        "UPDATE correos_en_cola SET estado = 'enviado', enviado_en = NOW()
         WHERE id = ? AND estado = 'pendiente'"
    );
    $reclamo->execute([$id]);
    if ($reclamo->rowCount() !== 1) {
        continue;
    }

    $st = $pdo->prepare('SELECT destinatario, asunto, cuerpo FROM correos_en_cola WHERE id = ?');
    $st->execute([$id]);
    $c = $st->fetch();

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST', '');
        $mail->Port = (int)env('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER', '');
        $mail->Password = env('SMTP_PASS', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(env('SMTP_FROM', env('SMTP_USER', '')), 'Gestor de tareas');
        $mail->addAddress($c['destinatario']);
        $mail->Subject = $c['asunto'];
        $mail->Body = $c['cuerpo'];
        $mail->send();
        $enviados++;
        echo "Enviado a {$c['destinatario']}\n";
    } catch (Throwable $ex) {
        // Si falla, vuelve a quedar pendiente para el próximo intento.
        $pdo->prepare("UPDATE correos_en_cola SET estado = 'pendiente', enviado_en = NULL WHERE id = ?")
            ->execute([$id]);
        $fallidos++;
        echo "No se pudo enviar a {$c['destinatario']}. Queda pendiente.\n";
    }
}

echo "Listo. Enviados: $enviados. Pendientes por fallo: $fallidos.\n";