<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_login();
require __DIR__ . '/../config/config.php';

function archivo_subido_normalizar_empresa(string $nombre): string
{
  $texto = trim($nombre);
  $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
  if ($transliterado !== false) {
    $texto = $transliterado;
  }

  $texto = preg_replace('/[^A-Za-z0-9 _.-]/', '', $texto) ?? '';
  $texto = preg_replace('/\s+/', '_', $texto) ?? '';
  $texto = trim($texto, '._-');

  return $texto !== '' ? $texto : 'empresa';
}

function archivo_subido_tamano_humano(int $bytes): string
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

function archivo_subido_detectar_mime(string $fileName): string
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

function archivo_subido_misma_empresa(string $nombreA, string $nombreB): bool
{
  return strcasecmp(
    archivo_subido_normalizar_empresa($nombreA),
    archivo_subido_normalizar_empresa($nombreB)
  ) === 0;
}

function archivo_subido_quitar_sufijo_anio(string $stem): string
{
  return (string)(preg_replace('/_(19|20)\d{2}$/', '', $stem) ?? $stem);
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

$mapEmpresasNorm = [];
foreach ($empresasUsuario as $empresa) {
  $mapEmpresasNorm[archivo_subido_normalizar_empresa((string)($empresa['razon_social'] ?? ''))] = (string)($empresa['razon_social'] ?? '');
}

if ($idEmpresaFiltro > 0) {
  foreach ($empresasUsuario as $empresa) {
    if ((int)($empresa['id_empresa'] ?? 0) === $idEmpresaFiltro) {
      $empresaFiltroNombre = (string)($empresa['razon_social'] ?? '');
      break;
    }
  }

  if ($empresaFiltroNombre === '') {
    http_response_code(403);
    exit('Acceso denegado');
  }
}

$mensaje = '';
$msgError = '';

// Manejar eliminación de archivos (solo admin y técnico)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($accion = (string)($_POST['accion'] ?? '')) === 'eliminar_archivo') {
  if (!$esAdmin && !$esTecnico) {
    $msgError = 'No tienes permiso para eliminar archivos.';
  } elseif (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    $msgError = 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.';
  } else {
    $source = (string)($_POST['source'] ?? '');
    $fileId = (int)($_POST['file_id'] ?? 0);
    $fileName = trim((string)($_POST['file_name'] ?? ''));

    try {
      if ($source === 'archivos' && $fileId > 0) {
        // Eliminar de la tabla archivos
        $stmtCheck = db()->prepare(
          "SELECT a.id_archivo, a.ruta_relativa FROM archivos a
           LEFT JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
           LEFT JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
           WHERE a.id_archivo = ?"
        );
        $stmtCheck->bind_param('i', $fileId);
        $stmtCheck->execute();
        $fileRow = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if (!$fileRow) {
          $msgError = 'Archivo no encontrado.';
        } else {
          // Verificar que el técnico tiene permiso sobre esta empresa
          if (!$esAdmin && $esTecnico) {
            $stmtVerify = db()->prepare(
              "SELECT 1 FROM archivos a
               LEFT JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
               LEFT JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
               LEFT JOIN usuario_empresa ue ON (ue.id_empresa = ac.id_empresa OR ue.id_empresa = a.id_empresa)
               WHERE a.id_archivo = ? AND ue.id_usuario = ?"
            );
            $stmtVerify->bind_param('ii', $fileId, $userId);
            $stmtVerify->execute();
            $hasAccess = (bool)$stmtVerify->get_result()->fetch_assoc();
            $stmtVerify->close();

            if (!$hasAccess) {
              $msgError = 'No tienes permiso para eliminar este archivo.';
            }
          }

          if ($msgError === '') {
            // Eliminar archivo del disco si existe
            if (!empty($fileRow['ruta_relativa'])) {
              $basePath = realpath(__DIR__ . '/..');
              $fullPath = $basePath . DIRECTORY_SEPARATOR . $fileRow['ruta_relativa'];
              if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
              }
            }

            // Eliminar de la base de datos
            $stmtDel = db()->prepare("DELETE FROM archivos WHERE id_archivo = ?");
            $stmtDel->bind_param('i', $fileId);
            $stmtDel->execute();
            $stmtDel->close();

            $mensaje = 'Archivo eliminado correctamente.';
          }
        }
      } elseif (in_array($source, ['uploads', 'empresa_porcentajes', 'empresa_word'], true) && $fileName !== '') {
        // Eliminar archivo del disco
        $basePath = realpath(__DIR__ . '/..');
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $source . DIRECTORY_SEPARATOR . $fileName;

        // Validar que está dentro del directorio permitido
        $realFullPath = realpath($fullPath);
        $realBasePath = realpath($basePath . DIRECTORY_SEPARATOR . $source);

        if (!$realFullPath || !$realBasePath || strpos($realFullPath, $realBasePath) !== 0) {
          $msgError = 'Ruta inválida.';
        } else {
          // Verificar permiso para técnico (que sea archivo de su empresa)
          if (!$esAdmin && $esTecnico) {
            $basename = basename($fullPath);
            $stem = pathinfo($basename, PATHINFO_FILENAME);

            if ($source === 'empresa_word' && str_ends_with($stem, '_PLAN_IGUALDAD')) {
              $stem = substr($stem, 0, -strlen('_PLAN_IGUALDAD'));
              $stem = archivo_subido_quitar_sufijo_anio($stem);
            } elseif ($source === 'uploads' && strpos($stem, '_TOMA_DE_DATOS_') !== false) {
              $stem = substr($stem, 0, strpos($stem, '_TOMA_DE_DATOS_'));
            } elseif ($source === 'uploads' && strpos($stem, 'registro_') === 0) {
              // Los registros retributivos se pueden eliminar (sin restricción de empresa)
              $tiene_permiso = true;
            }

            if (!isset($tiene_permiso) || !$tiene_permiso) {
              $tiene_permiso = false;
              foreach ($mapEmpresasNorm as $norm => $razon) {
                if ($norm !== '' && strcasecmp($norm, $stem) === 0) {
                  $tiene_permiso = true;
                  break;
                }
              }
            }

            if (!$tiene_permiso) {
              $msgError = 'No tienes permiso para eliminar este archivo.';
            }
          }

          if ($msgError === '' && file_exists($fullPath) && is_file($fullPath)) {
            if (@unlink($fullPath)) {
              $rutaRelativaDb = $source . '/' . $fileName;

              $stmtDel = db()->prepare("DELETE FROM archivos WHERE ruta_relativa = ?");
              $stmtDel->bind_param('s', $rutaRelativaDb);
              $stmtDel->execute();
              $stmtDel->close();

              $mensaje = 'Archivo eliminado correctamente.';
            } else {
              $msgError = 'No se pudo eliminar el archivo.';
            }
          } elseif ($msgError === '') {
            $msgError = 'El archivo no existe.';
          }
        }
      } else {
        $msgError = 'Parámetros inválidos.';
      }
    } catch (Throwable $e) {
      error_log(sprintf('[archivos_subidos.eliminar] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
      $msgError = 'No se pudo eliminar el archivo. Intentalo de nuevo.';
    }

    // Redirigir de vuelta a la misma página con mensaje
    $redirect = app_path('/php/archivos_subidos.php');
    $idEmpresaRedirect = (int)($_POST['id_empresa'] ?? $_GET['id_empresa'] ?? 0);
    if ($idEmpresaRedirect > 0) {
      $redirect .= '?id_empresa=' . $idEmpresaRedirect;
      $sep = '&';
    } else {
      $sep = '?';
    }
    if ($mensaje !== '') {
      $redirect .= $sep . 'msg=' . urlencode($mensaje);
    } elseif ($msgError !== '') {
      $redirect .= $sep . 'error=' . urlencode($msgError);
    }
    header("Location: $redirect");
    exit;
  }
}

$archivosListado = [];
$baseProject = realpath(__DIR__ . '/..');

// Archivos guardados en la tabla archivos
if ($esAdmin) {
  $stmt = db()->prepare(
    "SELECT a.id_archivo, a.tipo, a.asunto, a.nombre_original, a.nombre_guardado, a.ruta_relativa, a.tamano_bytes, a.mime, a.subido_en, COALESCE(e.razon_social, e2.razon_social) AS empresa_nombre
     FROM archivos a
     LEFT JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
     LEFT JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
     LEFT JOIN empresa e ON e.id_empresa = ac.id_empresa
     LEFT JOIN empresa e2 ON e2.id_empresa = a.id_empresa
     ORDER BY a.subido_en DESC, a.id_archivo DESC"
  );
  $stmt->execute();
} else {
  $stmt = db()->prepare(
    "SELECT a.id_archivo, a.tipo, a.asunto, a.nombre_original, a.nombre_guardado, a.ruta_relativa, a.tamano_bytes, a.mime, a.subido_en, COALESCE(e.razon_social, e2.razon_social) AS empresa_nombre
     FROM archivos a
     LEFT JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
     LEFT JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
     LEFT JOIN empresa e ON e.id_empresa = ac.id_empresa
     LEFT JOIN empresa e2 ON e2.id_empresa = a.id_empresa
     LEFT JOIN usuario_empresa ue ON (ue.id_empresa = COALESCE(ac.id_empresa, a.id_empresa))
     WHERE ue.id_usuario = ?
     ORDER BY a.subido_en DESC, a.id_archivo DESC"
  );
  $stmt->bind_param('i', $userId);
  $stmt->execute();
}

$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $empresaNombreRow = (string)($row['empresa_nombre'] ?? '');
  if ($idEmpresaFiltro > 0 && !archivo_subido_misma_empresa($empresaNombreRow, $empresaFiltroNombre)) {
    continue;
  }

  $tipoArchivo = (string)($row['tipo'] ?? '');
  $idArchivo = (int)($row['id_archivo'] ?? 0);

  // Evitar mostrar registros huérfanos en BD cuyo archivo ya no existe en disco.
  $rutaRelativa = (string)($row['ruta_relativa'] ?? '');
  if ($rutaRelativa !== '') {
    $fullPathDb = realpath($baseProject . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRelativa));
    if ($fullPathDb === false || !is_file($fullPathDb)) {
      continue;
    }

    // Evitar duplicados: ocultar filas antiguas tipo vacio/Documento para
    // archivos que realmente son generados en uploads/empresa_word/uploads/empresa_porcentajes.
    $rutaNorm = strtolower(str_replace('\\', '/', $rutaRelativa));
    $esGeneradoEnDisco = str_starts_with($rutaNorm, 'uploads/empresa_word/') || str_starts_with($rutaNorm, 'uploads/empresa_porcentajes/');
    if ($esGeneradoEnDisco && trim($tipoArchivo) === '') {
      continue;
    }
  }

  $archivosListado[] = [
    'source' => 'archivos',
    'id_archivo' => $idArchivo,
    'sort_ts' => strtotime((string)($row['subido_en'] ?? '')) ?: $idArchivo,
    'categoria' => $tipoArchivo !== '' ? $tipoArchivo : 'Documento',
    'empresa' => $empresaNombreRow,
    'propietario' => '',
    'asunto' => (string)($row['asunto'] ?? ''),
    'nombre' => (string)($row['nombre_original'] ?? ''),
    'tipo' => strtoupper((string)(pathinfo((string)($row['nombre_original'] ?? ''), PATHINFO_EXTENSION) ?: '')),
    'tamano' => archivo_subido_tamano_humano((int)($row['tamano_bytes'] ?? 0)),
    'mime' => (string)($row['mime'] ?? archivo_subido_detectar_mime((string)($row['nombre_original'] ?? ''))),
    'descarga' => app_path('/php/download_archivo_subido.php?kind=archivos&id=') . $idArchivo,
  ];
}
$stmt->close();

// Archivos guardados en disco (originales, generados porcentajes, generado word)
$roots = [
  'uploads' => $baseProject . DIRECTORY_SEPARATOR . 'uploads',
  'empresa_porcentajes' => $baseProject . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'empresa_porcentajes',
  'empresa_word' => $baseProject . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'empresa_word',
];

foreach ($roots as $kind => $dirPath) {
  if (!is_dir($dirPath)) {
    continue;
  }

  $files = glob($dirPath . DIRECTORY_SEPARATOR . '*') ?: [];
  foreach ($files as $fullPath) {
    if (!is_file($fullPath)) {
      continue;
    }

    $basename = basename($fullPath);
    $stem = pathinfo($basename, PATHINFO_FILENAME);
    $matchedEmpresa = '';
    $categoria = '';

    if ($kind === 'uploads') {
      // Archivos originales: registro retributivo o toma de datos
      if (strpos($stem, '_TOMA_DE_DATOS_') !== false) {
        $categoria = 'Toma de Datos';
        // Extraer nombre empresa: todo antes de "_TOMA_DE_DATOS_"
        $stem = substr($stem, 0, strpos($stem, '_TOMA_DE_DATOS_'));
      } elseif (strpos($stem, 'registro_') === 0) {
        // Estos archivos no son descargables desde download_archivo_subido.php.
        // Se omiten del listado para evitar filas rotas.
        continue;
      } else {
        // Otros archivos en uploads, ignorar
        continue;
      }
    } elseif ($kind === 'empresa_word' && str_ends_with($stem, '_PLAN_IGUALDAD')) {
      $stem = substr($stem, 0, -strlen('_PLAN_IGUALDAD'));
      $stem = archivo_subido_quitar_sufijo_anio($stem);
      $categoria = 'Generado Word';
    } elseif ($kind === 'empresa_porcentajes') {
      $categoria = 'Generado Porcentajes';
    }

    // Buscar empresa coincidente
    if ($stem !== '') {
      foreach ($mapEmpresasNorm as $norm => $razon) {
        if ($norm !== '' && strcasecmp($norm, $stem) === 0) {
          $matchedEmpresa = $razon;
          break;
        }
      }
    }

    if ($idEmpresaFiltro > 0 && !archivo_subido_misma_empresa($matchedEmpresa, $empresaFiltroNombre)) {
      continue;
    }

    // Solo mostrar si es admin o si es cliente/técnico y tiene empresa coincidente
    if (!$esAdmin && $matchedEmpresa === '') {
      continue;
    }

    $descargaUrl = '';
    if ($kind === 'uploads') {
      $descargaUrl = app_path('/php/download_archivo_subido.php?kind=uploads&file=') . urlencode($basename);
    } else {
      $descargaUrl = app_path('/php/download_archivo_subido.php?kind=') . urlencode($kind) . '&file=' . urlencode($basename);
    }

    $archivosListado[] = [
      'source' => $kind,
      'file_name' => $basename,
      'sort_ts' => filemtime($fullPath) ?: 0,
      'categoria' => $categoria,
      'empresa' => $matchedEmpresa,
      'propietario' => '',
      'asunto' => '',
      'nombre' => $basename,
      'tipo' => strtoupper(pathinfo($basename, PATHINFO_EXTENSION)),
      'tamano' => archivo_subido_tamano_humano((int)filesize($fullPath)),
      'mime' => archivo_subido_detectar_mime($basename),
      'descarga' => $descargaUrl,
    ];
  }
}

usort($archivosListado, static function (array $left, array $right): int {
  return ($right['sort_ts'] ?? 0) <=> ($left['sort_ts'] ?? 0);
});

require __DIR__ . '/../html/archivos_subidos.php';
