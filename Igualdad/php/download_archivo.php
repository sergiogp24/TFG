<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../config/config.php';

function fail(int $code, string $msg): void {
  http_response_code($code);
  exit($msg);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) fail(400, 'ID inválido');

// Ruta fija para la plantilla de registro retributivo (TOMADEDATOS.xlsx)
$defaultId = 1;
$defaultPath = __DIR__ . '/../PlantillaRegistroRetributivo/TOMADEDATOS.xlsx';

if ($id === $defaultId) {
  $fullPath = realpath($defaultPath);
  if ($fullPath === false || !is_file($fullPath)) {
    fail(404, 'Archivo no existe en disco');
  }

  $filename = 'TOMADEDATOS.xlsx';
  $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
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
}

$db = db();

// Si es el archivo por defecto (id 1), lo dejamos público.
$isDefault = $id === 1;

// Buscar archivo en BD (tabla archivos)
$stmt = $db->prepare("\n  SELECT a.id_archivo, a.nombre_original, a.ruta_relativa, a.mime, a.id_cliente_medida\n  FROM archivos a\n  WHERE a.id_archivo = ?\n  LIMIT 1\n");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) fail(404, 'Archivo no encontrado');

// Si el autor no es el usuario y no es archivo público (id 1), bloqueamos.
if (!$isDefault) {
  $rol = (string)($_SESSION['user']['rol'] ?? '');
  $currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);

  // ADMINISTRADOR/TECNICO pueden descargar cualquiera; CLIENTE solo el suyo.
  if ($rol === 'CLIENTE') {
    $stmt = $db->prepare("\n      SELECT 1\n      FROM archivos a\n      JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida\n      JOIN plan_cliente pc ON pc.id_plan_cliente = cm.id_plan_cliente\n      JOIN usuario_empresa ue ON ue.id_empresa = pc.id_empresa\n      WHERE a.id_archivo = ? AND ue.id_usuario = ?\n      LIMIT 1\n    ");
    $stmt->bind_param('ii', $id, $currentUserId);
    $stmt->execute();
    $hasAccess = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$hasAccess) {
      fail(403, 'No tienes permiso para descargar este archivo');
    }
  }
}

// Ruta física real del archivo
$relative = (string)$row['ruta_relativa'];
$base = realpath(__DIR__ . '/..');
if ($base === false) fail(500, 'Base path inválido');

$fullPath = realpath($base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
if ($fullPath === false || !is_file($fullPath)) {
  fail(404, 'Archivo no existe en disco');
}

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
