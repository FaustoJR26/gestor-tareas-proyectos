<?php
declare(strict_types=1);

final class CambioError extends RuntimeException
{
}

/**
 * Devuelve la tasa de cambio $origen -> $destino usando ExchangeRate-API.
 * La clave se lee de la variable de entorno EXCHANGE_API_KEY.
 */
function cambio_tasa(string $origen, string $destino): float
{
    $origen = strtoupper(trim($origen));
    $destino = strtoupper(trim($destino));
    if (!preg_match('/^[A-Z]{3}$/', $origen) || !preg_match('/^[A-Z]{3}$/', $destino)) {
        throw new CambioError('Usa códigos de moneda de 3 letras, por ejemplo USD o DOP.', 422);
    }
    if ($origen === $destino) {
        return 1.0;
    }

    $clave = getenv('EXCHANGE_API_KEY');
    if ($clave === false || $clave === '' || $clave === 'tu_llave_aqui') {
        throw new CambioError('El servicio de tasas de cambio no está configurado.', 503);
    }

    $url = 'https://v6.exchangerate-api.com/v6/' . rawurlencode($clave)
        . '/pair/' . $origen . '/' . $destino;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
    ]);
    $cuerpo = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Nunca se registra la URL ni la clave.
    if ($cuerpo === false) {
        error_log('cambio: la API externa no respondió.');
        throw new CambioError('El servicio de tasas de cambio no responde. Intenta más tarde.', 503);
    }

       $datos = json_decode((string)$cuerpo, true);
    if (!is_array($datos)) {
        error_log('cambio: respuesta no válida (HTTP ' . $http . ').');
        if ($http === 404) {
            // Una llave inexistente o mal formada devuelve una página HTML 404, no JSON.
            throw new CambioError('La clave del servicio de tasas de cambio no es válida.', 503);
        }
        throw new CambioError('El servicio de tasas de cambio devolvió una respuesta no válida.', 503);
    }

    if (($datos['result'] ?? '') !== 'success') {
        $tipo = (string)($datos['error-type'] ?? 'desconocido');
        error_log('cambio: la API rechazó la consulta (' . $tipo . ').');
        if ($tipo === 'invalid-key' || $tipo === 'inactive-account') {
            throw new CambioError('La clave del servicio de tasas de cambio no es válida.', 503);
        }
        if ($tipo === 'unsupported-code') {
            throw new CambioError('Moneda no soportada.', 422);
        }
        throw new CambioError('El servicio de tasas de cambio no pudo atender la consulta.', 503);
    }

    $tasa = $datos['conversion_rate'] ?? null;
    if (!is_numeric($tasa)) {
        throw new CambioError('El servicio de tasas de cambio devolvió una respuesta no válida.', 503);
    }
    return (float)$tasa;
}