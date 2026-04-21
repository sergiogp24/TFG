<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/helpers.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

$userId = (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
if ($userId <= 0) {
  header('Location: admin.php?view=ver_usuarios&msg=Usuario no válido');
  exit;
}
$accion = (string)($_POST['accion'] ?? '');

// Usuario (para mostrar arriba)
$stmt = db()->prepare("SELECT id_usuario, nombre_usuario, apellidos, email FROM usuario WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
  header('Location: admin.php?view=ver_usuarios&msg=Usuario no encontrado');
  exit;
}

$error = '';
$ok = '';

// Guardar asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    $error = 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.';
  }

  if ($error !== '') {
    // No continuar con cambios de asignacion cuando el token no es valido.
  } else {
  $empresaIds = $_POST['empresas'] ?? [];
  if (!is_array($empresaIds)) $empresaIds = [];

  $empresaIds = array_values(array_unique(array_map('intval', $empresaIds)));
  $empresaIds = array_filter($empresaIds, fn($id) => $id > 0);

  db()->begin_transaction();
  try {
    // Reemplazar: borramos y volvemos a insertar
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
    $ok = 'Empresas asignadas correctamente.';
  } catch (Throwable $t) {
    db()->rollback();
    error_log(sprintf('[lista_empresa.asignacion] %s in %s:%d', $t->getMessage(), $t->getFile(), $t->getLine()));
    $error = 'No se pudo guardar la asignacion. Intentalo de nuevo.';
  }
  }
}

// Listado de empresas
$empresas = [];
$res = db()->query("SELECT id_empresa, razon_social, nif, responsable, sector, telefono, email FROM empresa ORDER BY razon_social ASC");
while ($e = $res->fetch_assoc()) $empresas[] = $e;

// Empresas ya asignadas
$checked = [];
$stmt = db()->prepare("SELECT id_empresa FROM usuario_empresa WHERE id_usuario = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$rs = $stmt->get_result();
while ($row = $rs->fetch_assoc()) $checked[] = (int)$row['id_empresa'];
$stmt->close();

// ELIMINAR EMPRESA
if ($accion === 'eliminar') {
  $id = (int)($_POST['id_empresa'] ?? 0);
  if ($id <= 0) redirect_view('delete', 'ID inválido');

  $stmt = db()->prepare("DELETE FROM empresa WHERE id_empresa = ?");
  $stmt->bind_param('i', $id);

  try {
    $stmt->execute();
    $stmt->close();
    redirect_menu('Empresa Eliminada');
  } catch (Throwable $e) {
    error_log(sprintf('[lista_empresa.eliminar] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    redirect_view('delete', 'No se pudo eliminar. Intentalo de nuevo.');
  }
}

require __DIR__ . '/../html/ver_empresa.php';