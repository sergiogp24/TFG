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

    // -----------> NUEVO: Obtener empresas ANTES (para comparar)
    $empresasAntes = [];
    $stmt = db()->prepare("SELECT id_empresa FROM usuario_empresa WHERE id_usuario = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
      $empresasAntes[] = (int)$row['id_empresa'];
    }
    $stmt->close();

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

      // ----------->
      // 1. Detectar nuevas empresas asignadas
      $nuevas = array_diff($empresaIds, $empresasAntes);

      // 2. Si hay nuevas, obtener datos y enviar mails
      if (!empty($nuevas)) {
        // 2a. Obtener info de las nuevas empresas
        require_once __DIR__ . '/../php/mails.php';
        $db = db();
        $empresasNuevas = correo_obtener_empresas_asignadas($db, $userId, $nuevas);

        $stmt = $db->prepare("SELECT email, nombre_usuario FROM usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $usuarioData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuarioData) {
          $empresaReferencia = $empresasNuevas[0] ?? null;
          if (is_array($empresaReferencia)) {
            // Un solo correo por operacion de edicion/asignacion.
            correo_enviar_nueva_empresa_asignada(
              (string)$usuarioData['email'],
              (string)$usuarioData['nombre_usuario'],
              (string)($empresaReferencia['razon_social'] ?? ''),
              (string)($empresaReferencia['tipo_contrato'] ?? 'SIN CONTRATO'),
              '#' // Puedes poner aquí el enlace a la empresa
            );
          }

          $stmt = $db->prepare("
    SELECT email, nombre_usuario
    FROM usuario
    WHERE id_usuario = ?
    LIMIT 1
");
          $stmt->bind_param("i", $userId);
          $stmt->execute();
          $tecnicoData = $stmt->get_result()->fetch_assoc();
          $stmt->close();

          if ($tecnicoData && filter_var($tecnicoData['email'], FILTER_VALIDATE_EMAIL)) {

            correo_enviar_notificacion_tecnico_nueva_empresa(
              $tecnicoData['email'],              //  técnico real
              $tecnicoData['nombre_usuario'],     //  nombre del técnico
              $usuarioData['nombre_usuario'],     //  usuario editado
              $empresasNuevas
            );
          }
        }
      }
    } catch (Throwable $e) {
      db()->rollback();
      error_log(sprintf('[lista_empresa] Error al asignar empresas: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
      $error = 'No se pudieron asignar las empresas o el correo es inválido.';
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

require __DIR__ . '/../html/lista_empresa.html.php';
