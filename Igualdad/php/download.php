<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require __DIR__ . '/../config/config.php';

function fail(int $code, string $msg): void {
  http_response_code($code);
  exit($msg);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) fail(400, 'ID inválido');

// Buscar archivo en BD
$db = db();
$stmt = $db->prepare("
  SELECT a.id, a.usuario_id, a.nombre_original, a.ruta_relativa, a.mime
  FROM archivo a
  WHERE a.id = ?
  LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) fail(404, 'Archivo no encontrado');

// Seguridad: el CLIENTE solo descarga lo suyo
$rol = (string)($_SESSION['user']['rol'] ?? '');
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);

if ($rol === 'CLIENTE' && $currentUserId !== (int)$row['usuario_id']) {
  fail(403, 'No tienes permiso para descargar este archivo');
}

// Ruta física real del archivo
// a.ruta_relativa guarda algo como: uploads/cliente_3/abcd.xlsx
$relative = (string)$row['ruta_relativa'];

// base del proyecto (padre de /php)
$base = realpath(__DIR__ . '/..');
if ($base === false) fail(500, 'Base path inválido');

$fullPath = realpath($base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
if ($fullPath === false || !is_file($fullPath)) {
  fail(404, 'Archivo no existe en disco');
}

// Enviar headers para forzar descarga
$filename = (string)$row['nombre_original'];
$mime = (string)($row['mime'] ?? 'application/octet-stream');
$filesize = filesize($fullPath);

if (ob_get_level()) { @ob_end_clean(); }

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . (string)$filesize);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($fullPath);
exit;