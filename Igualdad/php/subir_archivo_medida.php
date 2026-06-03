<?php
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';

function jsonError(string $msg): never
{
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.');
}

// Sesión activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user'])) {
    jsonError('No autenticado.');
}

$idEmpresa = (int)($_POST['id_empresa'] ?? 0);
if ($idEmpresa <= 0) {
    jsonError('Empresa no válida.');
}

// Validar que el archivo llegó
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $codigoError = $_FILES['archivo']['error'] ?? -1;
    jsonError('Error en la subida (código ' . $codigoError . ').');
}

$file     = $_FILES['archivo'];
$tmpPath  = (string)$file['tmp_name'];
$origName = basename((string)$file['name']);
$size     = (int)$file['size'];

// Tamaño máximo: 10 MB
if ($size > 10 * 1024 * 1024) {
    jsonError('El archivo supera el tamaño máximo de 10 MB.');
}

// Extensiones permitidas
$extPermitidas = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $extPermitidas, true)) {
    jsonError('Extensión no permitida. Usa: ' . implode(', ', $extPermitidas));
}

// Validar MIME real
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($tmpPath);
$mimesPermitidos = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/png',
    'image/jpeg',
];
if (!in_array($mimeReal, $mimesPermitidos, true)) {
    jsonError('Tipo de archivo no permitido.');
}

// Directorio de destino
$dirDestino = __DIR__ . '/../uploads/medidas/' . $idEmpresa;
if (!is_dir($dirDestino)) {
    mkdir($dirDestino, 0755, true);
}

// Nombre único para evitar colisiones
$nombreFinal = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$rutaFinal   = $dirDestino . '/' . $nombreFinal;

if (!move_uploaded_file($tmpPath, $rutaFinal)) {
    jsonError('No se pudo guardar el archivo.');
}

// Ruta relativa para guardar en BD
$rutaRelativa = 'uploads/medidas/' . $idEmpresa . '/' . $nombreFinal;

echo json_encode([
    'ok'     => true,
    'ruta'   => $rutaRelativa,
    'nombre' => $origName,
]);
