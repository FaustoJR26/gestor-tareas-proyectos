<?php
declare(strict_types=1);

require_once __DIR__ . '/estados_solicitud.php';

const SOL_PERMISOS_VALIDOS = ['eliminar_tareas'];

/** El código de la excepción es el HTTP que debe devolver el endpoint. */
class SolicitudError extends Exception {}

function sol_crear(PDO $db, int $solicitanteId, string $permiso): int
{
    $permiso = trim($permiso);
    if (!in_array($permiso, SOL_PERMISOS_VALIDOS, true)) {
        throw new SolicitudError('Permiso inválido.', 422);           // RD-07
    }
    $db->prepare("INSERT INTO solicitudes_permiso (solicitante_id, permiso, estado, creada_en)
                  VALUES (?, ?, 'pendiente', NOW())")
       ->execute([$solicitanteId, $permiso]);
    return (int)$db->lastInsertId();
}

function sol_obtener(PDO $db, int $id): array
{
    $st = $db->prepare('SELECT * FROM solicitudes_permiso WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        throw new SolicitudError('La solicitud no existe.', 404);
    }
    return $fila;
}

function sol_listar_pendientes(PDO $db): array
{
    return $db->query("SELECT * FROM solicitudes_permiso WHERE estado = 'pendiente' ORDER BY id")
              ->fetchAll(PDO::FETCH_ASSOC);
}
 function sol_listar_de_usuario(PDO $db, int $usuarioId): array
{
    $st = $db->prepare('SELECT * FROM solicitudes_permiso WHERE solicitante_id <> ? ORDER BY id');
    $st->execute([$usuarioId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function usuario_tiene_permiso(PDO $db, int $usuarioId, string $permiso): bool
{
    $st = $db->prepare('SELECT 1 FROM permisos_usuario WHERE usuario_id = ? AND permiso = ?');
    $st->execute([$usuarioId, $permiso]);
    return (bool)$st->fetchColumn();
}

/** Punto único donde cambia el estado de una solicitud (RD-04). */
function sol_mover(PDO $db, array $sol, string $hacia, string $extraSql = '', array $params = []): array
{
    if (!sol_transicion_valida($sol['estado'], $hacia)) {
        throw new SolicitudError("Transición no permitida: {$sol['estado']} → $hacia.", 409);  // RF-GP-06
    }
    $sql = 'UPDATE solicitudes_permiso SET estado = ?' . ($extraSql !== '' ? ", $extraSql" : '') . ' WHERE id = ?';
    $db->prepare($sql)->execute(array_merge([$hacia], $params, [(int)$sol['id']]));
    $sol['estado'] = $hacia;
    return $sol;
}

/** Bloquea la fila para que dos aprobaciones simultáneas no pasen a la vez. */
function sol_bloquear(PDO $db, int $id): array
{
    $st = $db->prepare('SELECT * FROM solicitudes_permiso WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        throw new SolicitudError('La solicitud no existe.', 404);
    }
    return $fila;
}

function sol_exigir_no_propia(array $sol, int $adminId): void
{
    if ((int)$sol['solicitante_id'] === $adminId) {
        throw new SolicitudError('No puedes resolver tu propia solicitud.', 403);   // RF-GP-07
    }
}

function sol_aprobar(PDO $db, int $solicitudId, int $adminId): void
{
    $db->beginTransaction();
    try {
        $sol = sol_bloquear($db, $solicitudId);
        sol_exigir_no_propia($sol, $adminId);

        // RF-GP-03: queda registrado quién y cuándo
        $sol = sol_mover($db, $sol, 'aprobada', 'resuelto_por = ?, resuelto_en = NOW()', [$adminId]);

        // RF-GP-05: aplicar el cambio de permiso automáticamente
        $db->prepare('INSERT IGNORE INTO permisos_usuario (usuario_id, permiso, concedido_en)
                      VALUES (?, ?, NOW())')
           ->execute([(int)$sol['solicitante_id'], $sol['permiso']]);
        sol_mover($db, $sol, 'aplicada');

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function sol_rechazar(PDO $db, int $solicitudId, int $adminId, string $motivo): void
{
    $motivo = trim($motivo);
    if ($motivo === '') {
        throw new SolicitudError('El motivo del rechazo es obligatorio.', 422);     // RF-GP-04
    }
    $db->beginTransaction();
    try {
        $sol = sol_bloquear($db, $solicitudId);
        sol_exigir_no_propia($sol, $adminId);
        sol_mover($db, $sol, 'rechazada', 'resuelto_por = ?, resuelto_en = NOW(), motivo_rechazo = ?',
                  [$adminId, $motivo]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}