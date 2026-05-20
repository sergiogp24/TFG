<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/helpers.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

// Identificador del usuario cuyos permisos/empresas vamos a editar.
// Se acepta por GET o POST; si no es válido redirigimos al listado.
$userId = (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
if ($userId <= 0) {
  header('Location: admin.php?view=ver_usuarios&msg=Usuario no válido');
  exit;
}
$accion = (string)($_POST['accion'] ?? '');

// Obtener datos del usuario para mostrarlos en la vista
$stmt = db()->prepare("SELECT id_usuario, nombre_usuario, apellidos, email FROM usuario WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
  // Si no existe el usuario, volver al listado
  header('Location: admin.php?view=ver_usuarios&msg=Usuario no encontrado');
  exit;
}

$error = '';
$ok = '';

// Manejo del formulario: asignar empresas al usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar token CSRF
  if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    $error = 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.';
  }

  if ($error !== '') {
    // Token inválido: no hacer más cambios
  } else {
    // Recogemos la lista de empresas enviada (puede venir vacía)
    $empresaIds = $_POST['empresas'] ?? [];
    if (!is_array($empresaIds)) $empresaIds = [];

    // Normalizar: enteros únicos y mayores que 0
    $empresaIds = array_values(array_unique(array_map('intval', $empresaIds)));
    $empresaIds = array_filter($empresaIds, fn($id) => $id > 0);

    // Obtener empresas asignadas ANTES del cambio (para detectar nuevas asignaciones)
    $empresasAntes = [];
    $stmt = db()->prepare("SELECT id_empresa FROM usuario_empresa WHERE id_usuario = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
      $empresasAntes[] = (int)$row['id_empresa'];
    }
    $stmt->close();

    // Operación en transacción: borramos asignaciones previas y añadimos las nuevas
    db()->begin_transaction();
    try {
      // Borrar relaciones anteriores
      $stmt = db()->prepare("DELETE FROM usuario_empresa WHERE id_usuario = ?");
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $stmt->close();

      // Insertar las nuevas relaciones, si las hay
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

      // Detectar qué empresas son nuevas respecto al estado anterior
      $nuevas = array_diff($empresaIds, $empresasAntes);

      // Si hay nuevas asignaciones, preparar y enviar notificaciones por correo
      if (!empty($nuevas)) {
        // cargamos funciones de mail y obtenemos datos de las empresas asignadas
        require_once __DIR__ . '/../php/mails.php';
        $db = db();
        $empresasNuevas = correo_obtener_empresas_asignadas($db, $userId, $nuevas);

        // Obtener datos del usuario (email y nombre) para personalizar el correo
        $stmt = $db->prepare("SELECT email, nombre_usuario FROM usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $usuarioData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuarioData) {
          // Enviar un correo principal referenciando la primera empresa nueva
          $empresaReferencia = $empresasNuevas[0] ?? null;
          if (is_array($empresaReferencia)) {
            // Un único correo de notificación para la operación
            correo_enviar_nueva_empresa_asignada(
              (string)$usuarioData['email'],
              (string)$usuarioData['nombre_usuario'],
              (string)($empresaReferencia['razon_social'] ?? ''),
              (string)($empresaReferencia['tipo_contrato'] ?? 'SIN CONTRATO'),
              '#' // Puedes poner aquí el enlace a la empresa
            );
          }

          // Obtener datos del técnico (aquí se reutiliza la misma consulta por compatibilidad)
          $stmt = $db->prepare("\n    SELECT email, nombre_usuario\n    FROM usuario\n    WHERE id_usuario = ?\n    LIMIT 1\n");
          $stmt->bind_param("i", $userId);
          $stmt->execute();
          $tecnicoData = $stmt->get_result()->fetch_assoc();
          $stmt->close();

          // Si el técnico tiene un email válido, enviarle notificación con la lista
          if ($tecnicoData && filter_var($tecnicoData['email'], FILTER_VALIDATE_EMAIL)) {

            correo_enviar_notificacion_tecnico_nueva_empresa(
              $tecnicoData['email'],              // email del técnico
              $tecnicoData['nombre_usuario'],     // nombre del técnico
              $usuarioData['nombre_usuario'],     // nombre del usuario modificado
              $empresasNuevas                      // lista de empresas nuevas
            );
          }
        }
      }
    } catch (Throwable $e) {
      // En caso de error revertimos la transacción y registramos el problema
      db()->rollback();
      error_log(sprintf('[lista_empresa] Error al asignar empresas: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
      $error = 'No se pudieron asignar las empresas o el correo es inválido.';
    }
  }
}

// Obtener listado completo de empresas para mostrar en la vista
$empresas = [];
$res = db()->query("SELECT id_empresa, razon_social, nif, responsable, sector, telefono, email FROM empresa ORDER BY razon_social ASC");
while ($e = $res->fetch_assoc()) $empresas[] = $e;

// Obtener las empresas que ya están asignadas al usuario (para marcar checkboxes)
$checked = [];
$stmt = db()->prepare("SELECT id_empresa FROM usuario_empresa WHERE id_usuario = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$rs = $stmt->get_result();
while ($row = $rs->fetch_assoc()) $checked[] = (int)$row['id_empresa'];
$stmt->close();


// Renderizar la vista correspondiente
require __DIR__ . '/../html/lista_empresa.html.php';
?>
