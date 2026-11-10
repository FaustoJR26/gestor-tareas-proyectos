<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/env.php';

const DOC_MAX_BYTES = 2097152; // 2 MB
const DOC_EXTENSIONES = ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'csv', 'docx', 'xlsx'];

/** El código de la excepción es el HTTP que debe devolver el endpoint. */
class DocumentoError extends Exception {}

function doc_directorio(): string
{
    $dir = env('DOCS_DIR', __DIR__ . '/../storage/documentos');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new DocumentoError('No se pudo preparar el almacenamiento de documentos.', 500);
    }
    return $dir;
}

/** RF-DOC-01: sube un documento y registra nombre, tipo, tamaño, fecha y dueño. */
function doc_subir(PDO $db, int $usuarioId, ?array $archivo): array
{
    // RD-07: archivo ausente o vacío = rechazo controlado
    if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new DocumentoError('Selecciona un archivo en el campo "archivo".', 422);
    }
    $error = (int)$archivo['error'];
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new DocumentoError('El archivo supera el máximo de 2 MB.', 422);
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new DocumentoError('No se pudo recibir el archivo.', 422);
    }
    $tamano = (int)$archivo['size'];
    if ($tamano === 0) {
        throw new DocumentoError('El archivo está vacío.', 422);
    }
    if ($tamano > DOC_MAX_BYTES) {
        throw new DocumentoError('El archivo supera el máximo de 2 MB.', 422);
    }

    $nombre = trim(preg_replace('/[\x00-\x1F\x7F]/', '', basename((string)$archivo['name'])));
    $nombre = mb_substr($nombre, 0, 190);
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    if ($nombre === '' || !in_array($ext, DOC_EXTENSIONES, true)) {
        throw new DocumentoError('Tipo de archivo no permitido. Usa: ' . implode(', ', DOC_EXTENSIONES) . '.', 422);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tipo = $finfo ? (string)finfo_file($finfo, $archivo['tmp_name']) : '';
    if ($tipo === '') {
        $tipo = 'application/octet-stream';
    }

    $guardado = bin2hex(random_bytes(16));
    $destino = doc_directorio() . '/' . $guardado;
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new DocumentoError('No se pudo guardar el archivo.', 500);
    }

    try {
        $db->prepare('INSERT INTO documentos (nombre, nombre_guardado, tipo, tamano, subido_por)
                      VALUES (?, ?, ?, ?, ?)')
           ->execute([$nombre, $guardado, $tipo, $tamano, $usuarioId]);
    } catch (Throwable $e) {
        @unlink($destino);
        throw $e;
    }
    $id = (int)$db->lastInsertId();
    return doc_registro($db, $id);
}

function doc_registro(PDO $db, int $id): array
{
    $st = $db->prepare('SELECT id, nombre, tipo, tamano, subido_por, subido_en
                        FROM documentos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

/** Busca un documento NO eliminado; si no existe, 404. */
function doc_obtener(PDO $db, int $id): array
{
    $st = $db->prepare('SELECT * FROM documentos WHERE id = ? AND eliminado_en IS NULL');
    $st->execute([$id]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        throw new DocumentoError('El documento no existe.', 404);
    }
    return $fila;
}

function doc_autorizar(array $doc, int $usuarioId, string $rol): void
{
    if ($rol !== 'administrador' && (int)$doc['subido_por'] !== $usuarioId) {
        throw new DocumentoError('No tienes permiso sobre este documento.', 403);   // RD-06
    }
}

/** RF-DOC-02: sin eliminados; el Estándar ve solo los suyos, el Administrador todos. */
function doc_listar(PDO $db, int $usuarioId, string $rol): array
{
    $cols = 'id, nombre, tipo, tamano, subido_por, subido_en';
    if ($rol === 'administrador') {
        return $db->query("SELECT $cols FROM documentos WHERE eliminado_en IS NULL ORDER BY id")
                  ->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $db->prepare("SELECT $cols FROM documentos
                        WHERE eliminado_en IS NULL AND subido_por = ? ORDER BY id");
    $st->execute([$usuarioId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** RF-DOC-03: devuelve [registro, ruta del archivo] solo al dueño o a un Administrador. */
function doc_para_descarga(PDO $db, int $usuarioId, string $rol, int $id): array
{
    $doc = doc_obtener($db, $id);
    doc_autorizar($doc, $usuarioId, $rol);
    $ruta = doc_directorio() . '/' . $doc['nombre_guardado'];
    if (!is_file($ruta)) {
        throw new DocumentoError('El archivo no se encuentra en el almacenamiento.', 404);   // RD-07
    }
    return [$doc, $ruta];
}

/** RF-DOC-04: eliminación lógica. La fila y el archivo siguen existiendo. */
function doc_eliminar(PDO $db, int $usuarioId, string $rol, int $id): void
{
    $doc = doc_obtener($db, $id);
    doc_autorizar($doc, $usuarioId, $rol);
    $db->prepare('UPDATE documentos SET eliminado_en = NOW() WHERE id = ?')->execute([$id]);
}