<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$view   = (string)($_GET['view'] ?? 'asignar'); // 'asignar' | 'ver_empresas'
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

// Normalizamos view (por si llega cualquier cosa)
$allowedViews = ['asignar', 'ver_empresas'];
if (!in_array($view, $allowedViews, true)) $view = 'asignar';

/**
 * ============================================================
 *  VIEW = ver_empresas  (panel/tabla de empresas)
 *  - NO depende de id_usuario
 * ============================================================
 */
if ($view === 'ver_empresas') {
  // Cargar empresas para la tabla
  $empresas = [];
  $resE = db()->query("SELECT id_empresa, razon_social, nif, sector, telefono, email FROM empresa ORDER BY razon_social ASC");
  while ($e = $resE->fetch_assoc()) $empresas[] = $e;

  // Flash messages (reutilizamos las mismas)
  $flashOk = (string)($_SESSION['flash_ok'] ?? '');
  $flashErr = (string)($_SESSION['flash_err'] ?? '');
  unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

  // Acciones CRUD de empresa (solo eliminar por ahora)
  if ($accion === 'eliminar_empresa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresaId = (int)($_POST['id_empresa'] ?? 0);

    if ($empresaId <= 0) {
      $_SESSION['flash_err'] = 'ID de empresa inválido.';
      header('Location: lista_empresa_controller.php?view=ver_empresas');
      exit;
    }

    try {
      $stmt = db()->prepare("DELETE FROM empresa WHERE id_empresa = ?");
      $stmt->bind_param('i', $empresaId);
      $stmt->execute();
      $stmt->close();

      $_SESSION['flash_ok'] = 'Empresa eliminada correctamente.';
    } catch (Throwable $t) {
      // Si la empresa está referenciada por usuario_empresa, esto fallará por FK (RESTRICT)
      $_SESSION['flash_err'] = 'No se pudo eliminar la empresa: ' . $t->getMessage();
    }

    header('Location: lista_empresa_controller.php?view=ver_empresas');
    exit;
  }

  require __DIR__ . '/../html/ver_empresa.php';
  exit;
}

/**
 * ============================================================
 *  VIEW = asignar (asignar empresas a un usuario)
 *  - depende de id_usuario
 * ============================================================
 */
$userId = (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);

// Lista de usuarios (para selector)
$usuarios = [];
$resU = db()->query("SELECT id_usuario, nombre_usuario, email FROM usuario ORDER BY nombre_usuario ASC");
while ($u = $resU->fetch_assoc()) $usuarios[] = $u;

// Lista de empresas (para checkbox)
$empresas = [];
$resE = db()->query("SELECT id_empresa, razon_social, nif, sector, telefono, email FROM empresa ORDER BY razon_social ASC");
while ($e = $resE->fetch_assoc()) $empresas[] = $e;

// Flash messages
$flashOk = (string)($_SESSION['flash_ok'] ?? '');
$flashErr = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

$selectedUser = null;
$checkedEmpresaIds = [];

// Cargar usuario + empresas asignadas
if ($userId > 0) {
  $stmt = db()->prepare("SELECT id_usuario, nombre_usuario, email FROM usuario WHERE id_usuario = ? LIMIT 1");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $selectedUser = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $stmt = db()->prepare("SELECT id_empresa FROM usuario_empresa WHERE id_usuario = ?");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $rs = $stmt->get_result();
  while ($row = $rs->fetch_assoc()) $checkedEmpresaIds[] = (int)$row['id_empresa'];
  $stmt->close();
}

// Guardar asignación (reemplaza lo anterior)
if ($accion === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $userId = (int)($_POST['id_usuario'] ?? 0);
  $empresaIds = $_POST['empresas'] ?? [];
  if (!is_array($empresaIds)) $empresaIds = [];

  $empresaIds = array_values(array_unique(array_map('intval', $empresaIds)));
  $empresaIds = array_filter($empresaIds, fn($id) => $id > 0);

  if ($userId <= 0) {
    $_SESSION['flash_err'] = 'Selecciona un usuario.';
    header('Location: lista_empresa_controller.php?view=asignar');
    exit;
  }

  db()->begin_transaction();
  try {
    $stmt = db()->prepare("DELETE FROM usuario_empresa WHERE id_usuario = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    if (count($empresaIds) > 0) {
      $stmt = db()->prepare("INSERT INTO usuario_empresa (id_usuario, id_empresa) VALUES (?, ?)");
      foreach ($empresaIds as $eid) {
        $stmt->bind_param('ii', $userId, $eid);
        $stmt->execute();
      }
      $stmt->close();
    }

    db()->commit();
    $_SESSION['flash_ok'] = 'Empresas asignadas correctamente.';
  } catch (Throwable $t) {
    db()->rollback();
    $_SESSION['flash_err'] = 'Error guardando asignación: ' . $t->getMessage();
  }

  header('Location: lista_empresa_controller.php?view=asignar&id_usuario=' . $userId);
  exit;
}

require __DIR__ . '/../html/ver_empresa.php';