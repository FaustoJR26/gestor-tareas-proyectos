<?php
declare(strict_types=1);

/** Único lugar donde se declaran las transiciones válidas de una solicitud. */
const SOL_TRANSICIONES = [
    'pendiente' => ['aprobada', 'rechazada'],
    'aprobada'  => ['aplicada', 'pendiente'],
    'aplicada'  => [],
    'rechazada' => [],
];

function sol_transicion_valida(string $desde, string $hacia): bool
{
    return in_array($hacia, SOL_TRANSICIONES[$desde] ?? [], true);
}