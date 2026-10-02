<?php
declare(strict_types=1);

/**
 * ÚNICO lugar donde se declaran los estados de una Tarea y sus transiciones.
 * Ningún otro archivo debe repetir esta información.
 */

const TAREA_ESTADOS = ['pendiente', 'en_progreso', 'en_revision', 'completada'];

/** RF-NEG-05: del estado terminal no se sale. */
const TAREA_ESTADOS_TERMINALES = ['completada'];

/**
 * Transiciones permitidas: desde => [hacia => quién la ejecuta].
 * 'estandar' significa cualquier usuario autenticado; 'administrador' solo el Administrador.
 */
const TAREA_TRANSICIONES = [
    'pendiente'   => ['en_progreso' => 'estandar'],
    'en_progreso' => ['en_revision' => 'estandar'],
    'en_revision' => ['completada' => 'administrador', 'en_progreso' => 'administrador'],
    'completada'  => [],
];

/** RF-NEG-04: transiciones prohibidas de forma explícita, con su motivo. */
const TAREA_TRANSICIONES_PROHIBIDAS = [
    ['pendiente', 'completada', 'No se puede completar una tarea sin trabajarla ni revisarla.'],
    ['completada', 'en_progreso', 'Una tarea completada no se reabre.'],
];

/** Devuelve [permitido, mensaje]. */
function tarea_validar_transicion(string $desde, string $hacia, string $rol): array
{
    if (!in_array($desde, TAREA_ESTADOS, true) || !in_array($hacia, TAREA_ESTADOS, true)) {
        return [false, 'Estado no válido.'];
    }
    foreach (TAREA_TRANSICIONES_PROHIBIDAS as [$d, $h, $motivo]) {
        if ($d === $desde && $h === $hacia) {
            return [false, $motivo];
        }
    }
    if (in_array($desde, TAREA_ESTADOS_TERMINALES, true)) {
        return [false, 'La tarea está en un estado final y no puede cambiar.'];
    }
    $exigido = TAREA_TRANSICIONES[$desde][$hacia] ?? null;
    if ($exigido === null) {
        return [false, 'Esa transición no está permitida.'];
    }
    if ($exigido === 'administrador' && $rol !== 'administrador') {
        return [false, 'Solo un Administrador puede ejecutar esta transición.'];
    }
    return [true, 'Transición permitida.'];
}