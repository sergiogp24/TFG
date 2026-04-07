<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../config/config.php';

function redirect_documentos(string $msg): void
{
    header('Location: /Igualdad/html/index_documentos_tipo.php?msg=' . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo no permitido');
}

$asunto = trim((string)($_POST['asunto'] ?? ''));
$tipo = strtoupper(trim((string)($_POST['tipo'] ?? '')));
$maxTamanoBytes = 50 * 1024 * 1024; // 50MB

$tiposPermitidos = ['IGUALDAD', 'SELECCION', 'SALUD', 'COMUNICACION', 'LGTBI'];

if ($asunto === '') {
    redirect_documentos('El asunto es obligatorio.');
}

if (!in_array($tipo, $tiposPermitidos, true)) {
    redirect_documentos('Tipo de archivo no valido.');
}

if (!isset($_FILES['archivo'])) {
    redirect_documentos('No se recibio archivo.');
}

$archivo = $_FILES['archivo'];
$error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

if ($error !== UPLOAD_ERR_OK) {
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        redirect_documentos('El archivo supera el tamano permitido por el servidor/formulario.');
    }
    redirect_documentos('Error en la subida del archivo.');
}

$nombreOriginal = trim((string)($archivo['name'] ?? ''));
$tmpName = (string)($archivo['tmp_name'] ?? '');
$tamanoBytes = (int)($archivo['size'] ?? 0);

if ($nombreOriginal === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
    redirect_documentos('Archivo temporal invalido.');
}

$ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
$extPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'];

if (!in_array($ext, $extPermitidas, true)) {
    redirect_documentos('Extension no permitida.');
}

if ($tamanoBytes <= 0) {
    redirect_documentos('El archivo esta vacio (0KB).');
}

if ($tamanoBytes > $maxTamanoBytes) {
    redirect_documentos('Tamano de archivo no valido (max 50MB).');
}

$uploadDir = __DIR__ . '/../uploads/documentos_tipo';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    redirect_documentos('No se pudo crear la carpeta de subida.');
}

$nombreGuardado = bin2hex(random_bytes(16)) . '.' . $ext;
$rutaDestino = $uploadDir . '/' . $nombreGuardado;

if (!move_uploaded_file($tmpName, $rutaDestino)) {
    redirect_documentos('No se pudo guardar el archivo.');
}

$rutaRelativa = 'uploads/documentos_tipo/' . $nombreGuardado;
$mime = mime_content_type($rutaDestino) ?: null;
$sha256 = hash_file('sha256', $rutaDestino) ?: null;

try {
    $db = db();

    $stmt = $db->prepare(
        'INSERT INTO archivos (tipo, asunto, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        redirect_documentos('Error preparando insercion en base de datos.');
    }

    $stmt->bind_param(
        'sssssiss',
        $tipo,
        $asunto,
        $nombreOriginal,
        $nombreGuardado,
        $rutaRelativa,
        $tamanoBytes,
        $mime,
        $sha256
    );

    if (!$stmt->execute()) {
        $stmt->close();
        redirect_documentos('No se pudo registrar el archivo en base de datos.');
    }

    $stmt->close();
} catch (Throwable $e) {
    redirect_documentos('Error guardando en base de datos.');
}

redirect_documentos('Archivo subido correctamente.');
