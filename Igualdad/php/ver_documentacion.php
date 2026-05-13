<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_login();
require __DIR__ . '/../config/config.php';

function doc_tamano_humano(int $bytes): string
{
  if ($bytes < 1024) {
    return $bytes . ' B';
  }
  $unidades = ['KB', 'MB', 'GB', 'TB'];
  $valor = $bytes / 1024;
  $indice = 0;
  while ($valor >= 1024 && $indice < count($unidades) - 1) {
    $valor /= 1024;
    $indice++;
  }
  return number_format($valor, 2, ',', '.') . ' ' . $unidades[$indice];
}

function doc_detectar_mime(string $fileName): string
{
  $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  return match ($ext) {
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'csv' => 'text/csv',
    'pdf' => 'application/pdf',
    default => 'application/octet-stream',
  };
}

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdmin = ($rol === 'ADMINISTRADOR');
$esCliente = ($rol === 'CLIENTE');
$esTecnico = ($rol === 'TECNICO');

if (!$esAdmin && !$esCliente && !$esTecnico) {
  http_response_code(403);
  exit('Acceso denegado');
}

$userId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$idEmpresaFiltro = (int)($_GET['id_empresa'] ?? 0);
$empresaFiltroNombre = '';
$sessionUsername = (string)($_SESSION['user']['nombre_usuario'] ?? $_SESSION['user']['username'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');

$empresasUsuario = [];
if ($userId > 0) {
  if ($esAdmin) {
    $stmtEmpresas = db()->query("SELECT id_empresa, razon_social FROM empresa ORDER BY razon_social ASC");
    while ($row = $stmtEmpresas->fetch_assoc()) {
      $empresasUsuario[] = $row;
    }
  } else {
    $stmtEmpresas = db()->prepare(
      "SELECT e.id_empresa, e.razon_social
       FROM usuario_empresa ue
       INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
       WHERE ue.id_usuario = ?
       ORDER BY e.razon_social ASC"
    );
    $stmtEmpresas->bind_param('i', $userId);
    $stmtEmpresas->execute();
    $resEmpresas = $stmtEmpresas->get_result();
    while ($row = $resEmpresas->fetch_assoc()) {
      $empresasUsuario[] = $row;
    }
    $stmtEmpresas->close();
  }
}

$idsEmpresaPermitidas = [];
foreach (($empresasUsuario ?? []) as $empresa) {
  $idEmpresaPermitida = (int)($empresa['id_empresa'] ?? 0);
  if ($idEmpresaPermitida > 0) {
    $idsEmpresaPermitidas[$idEmpresaPermitida] = true;
  }
}

if ($idEmpresaFiltro > 0) {
  $validFiltro = false;
  foreach (($empresasUsuario ?? []) as $empresa) {
    if ((int)($empresa['id_empresa'] ?? 0) === $idEmpresaFiltro) {
      $empresaFiltroNombre = (string)($empresa['razon_social'] ?? '');
      $validFiltro = true;
      break;
    }
  }
  if (!$validFiltro && !$esAdmin) {
    http_response_code(403);
    exit('Acceso denegado');
  }
} elseif ($esCliente && !empty($idsEmpresaPermitidas)) {
    // Si es cliente y no hay filtro, tomamos su primera empresa por defecto
    $idEmpresaFiltro = (int)key($idsEmpresaPermitidas);
    foreach ($empresasUsuario as $emp) {
        if ((int)$emp['id_empresa'] === $idEmpresaFiltro) {
            $empresaFiltroNombre = $emp['razon_social'];
            break;
        }
    }
}

$archivosListado = [];
$baseProject = realpath(__DIR__ . '/..');

// Solo archivos de tipo DOCUMENTACION
$sql = "SELECT a.id_archivo, a.tipo, a.asunto, a.nombre_original, a.nombre_guardado, a.ruta_relativa, a.tamano_bytes, a.mime, a.subido_en,
               a.id_empresa AS id_empresa_archivo,
               COALESCE(e.razon_social, e2.razon_social) AS empresa_nombre,
               COALESCE(ac.id_empresa, a.id_empresa) AS empresa_id_resuelta
        FROM archivos a
        LEFT JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
        LEFT JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
        LEFT JOIN empresa e ON e.id_empresa = ac.id_empresa
        LEFT JOIN empresa e2 ON e2.id_empresa = a.id_empresa
        WHERE a.tipo = 'DOCUMENTACION'";

if ($idEmpresaFiltro > 0) {
    $sql .= " AND (COALESCE(ac.id_empresa, a.id_empresa) = $idEmpresaFiltro)";
} elseif (!$esAdmin) {
    // Si no hay filtro y no es admin, filtrar por sus empresas permitidas
    $allowedIds = implode(',', array_keys($idsEmpresaPermitidas));
    if ($allowedIds === '') $allowedIds = '0';
    $sql .= " AND (COALESCE(ac.id_empresa, a.id_empresa) IN ($allowedIds))";
}

$sql .= "ORDER BY a.subido_en DESC, a.id_archivo DESC";

$res = db()->query($sql);
while ($row = $res->fetch_assoc()) {
  $rutaRelativa = (string)($row['ruta_relativa'] ?? '');
  $archivoDisponible = true;
  if ($rutaRelativa !== '') {
    $fullPathDb = realpath($baseProject . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRelativa));
    if ($fullPathDb === false || !is_file($fullPathDb)) {
      $archivoDisponible = false;
    }
  }

  $archivosListado[] = [
    'id_archivo' => (int)$row['id_archivo'],
    'subido_en' => (string)($row['subido_en'] ?? ''),
    'empresa' => (string)($row['empresa_nombre'] ?? ''),
    'asunto' => $archivoDisponible
      ? (string)($row['asunto'] ?? '')
      : trim(((string)($row['asunto'] ?? '')) . ' [No disponible]'),
    'nombre' => (string)($row['nombre_original'] ?? ''),
    'tipo' => strtoupper((string)(pathinfo((string)($row['nombre_original'] ?? ''), PATHINFO_EXTENSION) ?: '')),
    'tamano' => doc_tamano_humano((int)($row['tamano_bytes'] ?? 0)),
    'descarga' => $archivoDisponible
      ? app_path('/php/download_archivo_subido.php?kind=archivos&id=') . $row['id_archivo']
      : '#',
  ];
}

require __DIR__ . '/../html/lista_documentacion.html.php';
