<?php
declare(strict_types=1);

require_once __DIR__ . '/estados_tarea.php';
require_once __DIR__ . '/estados_tarea.php';
require_once __DIR__ . '/solicitudes.php';

/** El código de la excepción es el HTTP que debe devolver el endpoint. */
class TareaError extends Exception {}

function tarea_crear(PDO $db, int $usuarioId, string $titulo, ?string $descripcion = null): int
{
    $titulo = trim($titulo);
    if ($titulo === '' || mb_strlen($titulo) > 190) {
        throw new TareaError('El título es obligatorio y admite hasta 190 caracteres.', 422);   // RD-07
    }
    $db->prepare("INSERT INTO tareas (titulo, descripcion, estado, creado_por)
                  VALUES (?, ?, 'pendiente', ?)")
       ->execute([$titulo, $descripcion, $usuarioId]);
    return (int)$db->lastInsertId();
}

function tarea_obtener(PDO $db, int $id): array
{
    $st = $db->prepare('SELECT * FROM tareas WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        throw new TareaError('La tarea no existe.', 404);
    }
    return $fila;
}

function tarea_listar(PDO $db, int $usuarioId, string $rol): array
{
    if ($rol === 'administrador') {
        return $db->query('SELECT * FROM tareas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $db->prepare('SELECT * FROM tareas WHERE creado_por = ? ORDER BY id');
    $st->execute([$usuarioId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Punto único donde cambia el estado de una tarea (RD-04). */
function tarea_mover(PDO $db, int $id, string $hacia, string $rol): array
{
    if (!in_array($hacia, TAREA_ESTADOS, true)) {
        throw new TareaError('Estado no válido.', 422);                                        // RD-07
    }
    $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT * FROM tareas WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $tarea = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tarea) {
            throw new TareaError('La tarea no existe.', 404);
        }
        [$permitido, $mensaje] = tarea_validar_transicion($tarea['estado'], $hacia, $rol);
        if (!$permitido) {
            throw new TareaError($mensaje, 409);                                               // RF-NEG-04
        }
        $db->prepare('UPDATE tareas SET estado = ? WHERE id = ?')->execute([$hacia, $id]);
        $tarea['estado'] = $hacia;
        $db->commit();
        return $tarea;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/** Quinta funcionalidad: exige el permiso eliminar_tareas de Gestión de permisos. */
function tarea_eliminar(PDO $db, int $usuarioId, int $id): void
{
    if (!usuario_tiene_permiso($db, $usuarioId, 'eliminar_tareas')) {
        throw new TareaError('No tienes el permiso eliminar_tareas. Solicítalo a un Administrador.', 403);
    }
    $st = $db->prepare('DELETE FROM tareas WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        throw new TareaError('La tarea no existe.', 404);
    }
}