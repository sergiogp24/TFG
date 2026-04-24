<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../config/config.php';

function fail(int $code, string $msg): void
{
  http_response_code($code);
  exit($msg);
}

function download_mime(string $fileName): string
{
  $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  return match ($ext) {
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'csv' => 'text/csv',
    default => 'application/octet-stream',
  };
}

function download_normalizar_empresa(string $nombre): string
{
  $texto = trim($nombre);
  $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
  if ($transliterado !== false) {
    $texto = $transliterado;
  }

  $texto = preg_replace('~[^A-Za-z0-9 _.-]+~', '', $texto) ?? '';
  $texto = preg_replace('~\s+~', '_', $texto) ?? '';
  $texto = trim($texto, '._-');

  return $texto !== '' ? $texto : 'empresa';
}

function download_quitar_sufijo_anio(string $stem): string
{
  return (string)(preg_replace('/_(19|20)\d{2}$/', '', $stem) ?? $stem);
}

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdmin = ($rol === 'ADMINISTRADOR');
if (!in_array($rol, ['ADMINISTRADOR', 'CLIENTE', 'TECNICO'], true)) {
  fail(403, 'Acceso denegado');
}

$kind = (string)($_GET['kind'] ?? '');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$file = (string)($_GET['file'] ?? '');

if ($kind === '') {
  fail(400, 'Parámetros inválidos');
}

$base = realpath(__DIR__ . '/..');
if ($base === false) {
  fail(500, 'Base path inválido');
}

$userId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$downloadEmpresaId = 0;

if ($kind === 'archivos') {
  if ($id <= 0) {
    fail(400, 'ID inválido');
  }

  $db = db();
  $stmt = $db->prepare("SELECT a.id_archivo, a.nombre_original, a.ruta_relativa, a.mime, a.id_cliente_medida FROM archivos a WHERE a.id_archivo = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    fail(404, 'Archivo no encontrado');
  }

  if (!$esAdmin) {
    $stmt = $db->prepare("\n      SELECT 1\n      FROM archivos a\n      LEFT JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida\n      LEFT JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas\n      JOIN usuario_empresa ue ON ue.id_empresa = COALESCE(ac.id_empresa, a.id_empresa)\n      WHERE a.id_archivo = ? AND ue.id_usuario = ?\n      LIMIT 1\n    ");
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    $hasAccess = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$hasAccess) {
      fail(403, 'No tienes permiso para descargar este archivo');
    }
  }

  $relative = (string)$row['ruta_relativa'];
  $fullPath = realpath($base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
  if ($fullPath === false || !is_file($fullPath)) {
    fail(404, 'Archivo no encontrado');
  }

  $filename = (string)$row['nombre_original'];
  $mime = (string)($row['mime'] ?? download_mime($filename));
} elseif ($kind === 'empresa_porcentajes' || $kind === 'empresa_word') {
  if ($file === '') {
    fail(400, 'Nombre de archivo inválido');
  }

  if (basename($file) !== $file) {
    fail(400, 'Nombre de archivo inválido');
  }

  $dirMap = [
    'empresa_porcentajes' => $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'empresa_porcentajes',
    'empresa_word' => $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'empresa_word',
  ];

  if (!$esAdmin) {
    $empresas = [];
    $stmt = db()->prepare(
      "SELECT e.razon_social
       FROM usuario_empresa ue
       INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
       WHERE ue.id_usuario = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $empresas[] = download_normalizar_empresa((string)($row['razon_social'] ?? ''));
    }
    $stmt->close();

    $stem = pathinfo($file, PATHINFO_FILENAME);
    if ($kind === 'empresa_word' && str_ends_with($stem, '_PLAN_IGUALDAD')) {
      $stem = substr($stem, 0, -strlen('_PLAN_IGUALDAD'));
      $stem = download_quitar_sufijo_anio($stem);
    }

    $allowed = false;
    foreach ($empresas as $empresaNorm) {
      if ($empresaNorm !== '' && strcasecmp($empresaNorm, $stem) === 0) {
        $allowed = true;
        break;
      }
    }

    if (!$allowed) {
      fail(403, 'No tienes permiso para descargar este archivo');
    }
  }

  // Resolver empresa asociada al archivo por nombre normalizado (para auditoria de descarga)
  $stem = pathinfo($file, PATHINFO_FILENAME);
  if ($kind === 'empresa_word' && str_ends_with($stem, '_PLAN_IGUALDAD')) {
    $stem = substr($stem, 0, -strlen('_PLAN_IGUALDAD'));
    $stem = download_quitar_sufijo_anio($stem);
  }

  $resEmpMap = db()->query("SELECT id_empresa, razon_social FROM empresa");
  if ($resEmpMap) {
    while ($rowEmpMap = $resEmpMap->fetch_assoc()) {
      $empresaNorm = download_normalizar_empresa((string)($rowEmpMap['razon_social'] ?? ''));
      if ($empresaNorm !== '' && strcasecmp($empresaNorm, $stem) === 0) {
        $downloadEmpresaId = (int)($rowEmpMap['id_empresa'] ?? 0);
        break;
      }
    }
    $resEmpMap->close();
  }

  $fullPath = realpath($dirMap[$kind] . DIRECTORY_SEPARATOR . $file);
  if ($fullPath === false || !is_file($fullPath)) {
    fail(404, 'Archivo no encontrado');
  }

  $filename = basename($file);
  $mime = download_mime($filename);
} elseif ($kind === 'uploads') {
  if ($file === '') {
    fail(400, 'Nombre de archivo inválido');
  }

  if (basename($file) !== $file) {
    fail(400, 'Nombre de archivo inválido');
  }

  $fullPath = realpath($base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $file);
  if ($fullPath === false || !is_file($fullPath)) {
    fail(404, 'Archivo no encontrado');
  }

  $stem = pathinfo($file, PATHINFO_FILENAME);
  if (!str_contains($stem, '_TOMA_DE_DATOS_')) {
    fail(400, 'Archivo inválido');
  }

  if (!$esAdmin) {
    $empresas = [];
    $stmt = db()->prepare(
      "SELECT e.razon_social
       FROM usuario_empresa ue
       INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
       WHERE ue.id_usuario = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $empresas[] = download_normalizar_empresa((string)($row['razon_social'] ?? ''));
    }
    $stmt->close();

    [$empresaToken] = explode('_TOMA_DE_DATOS_', $stem, 2);
    $allowed = false;
    foreach ($empresas as $empresaNorm) {
      if ($empresaNorm !== '' && strcasecmp($empresaNorm, $empresaToken) === 0) {
        $allowed = true;
        break;
      }
    }

    if (!$allowed) {
      fail(403, 'No tienes permiso para descargar este archivo');
    }
  }

  $filename = basename($file);
  $mime = download_mime($filename);
} else {
  fail(400, 'Tipo de archivo no válido');
}

if ($kind === 'empresa_word' && $rol === 'TECNICO' && $userId > 0 && $downloadEmpresaId > 0) {
  try {
    $db = db();
    $db->query("\n CREATE TABLE IF NOT EXISTS archivo_descarga_log (\n id_descarga INT AUTO_INCREMENT PRIMARY KEY,\n id_empresa INT NOT NULL,\n id_usuario INT NOT NULL,\n tipo_descarga VARCHAR(60) NOT NULL,\n  archivo VARCHAR(255) NULL,\n  descargado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n        INDEX idx_descarga_empresa (id_empresa),\n        INDEX idx_descarga_usuario (id_usuario),\n INDEX idx_descarga_tipo (tipo_descarga)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n ");

    $tipoDescarga = 'WORD_GENERADO';
    $archivoDescargado = (string)$filename;
    $stmtLog = $db->prepare("\n INSERT INTO archivo_descarga_log (id_empresa, id_usuario, tipo_descarga, archivo)\n VALUES (?, ?, ?, ?)\n ");
    if ($stmtLog) {
      $stmtLog->bind_param('iiss', $downloadEmpresaId, $userId, $tipoDescarga, $archivoDescargado);
      $stmtLog->execute();
      $stmtLog->close();
    }
  } catch (Throwable $e) {
    // No bloquear la descarga por fallo de auditoria.
  }
}

$filesize = filesize($fullPath);
if (ob_get_level()) {
  @ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string)$filesize);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($fullPath);
exit;
