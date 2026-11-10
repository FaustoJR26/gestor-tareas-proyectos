<?php
declare(strict_types=1);
require __DIR__ . '/../src/permisos.php';
require __DIR__ . '/../src/tareas.php';
require __DIR__ . '/../src/cambio.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ses_responder(['error' => 'Método no permitido.'], 405);
}

try {
    perm_exigir('tareas.ver');
    $id = (string)($_GET['id'] ?? '');
    if (!ctype_digit($id)) {
        ses_responder(['error' => 'Indica el id de la tarea.'], 422);
    }
    $tarea = tarea_obtener(db(), (int)$id);
    $respuesta = ['tarea' => $tarea];

    $destino = (string)($_GET['convertir'] ?? '');
    if ($destino !== '' && $tarea['costo'] !== null) {
        try {
            $tasa = cambio_tasa((string)$tarea['moneda'], $destino);
            $respuesta['costo_convertido'] = [
                'moneda' => strtoupper(trim($destino)),
                'tasa'   => $tasa,
                'valor'  => round((float)$tarea['costo'] * $tasa, 2),
            ];
        } catch (CambioError $e) {
            $respuesta['costo_convertido'] = null;
            $respuesta['aviso'] = $e->getMessage();
        }
    } elseif ($destino !== '') {
        $respuesta['aviso'] = 'La tarea no tiene costo registrado.';
    }
    ses_responder($respuesta);
} catch (TareaError $e) {
    ses_responder(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log($e->getMessage());
    ses_responder(['error' => 'No se pudo procesar la solicitud.'], 500);
}