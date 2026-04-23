<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../php/auth.php';
require_role('TECNICO');

require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../php/mails.php';

function redirect_tecnico(string $view = 'menu', string $msg = ''): void
{
  $to = app_path('/model/tecnico.php?view=') . urlencode($view);
  if ($msg !== '') $to .= '&msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

function log_internal_error_tecnico(string $context, Throwable $e): void
{
  error_log(sprintf(
    '[%s] %s in %s:%d',
    $context,
    $e->getMessage(),
    $e->getFile(),
    $e->getLine()
  ));
}

function ensure_reuniones_empresa_column(mysqli $db): void
{
  $check = $db->query("\n    SELECT 1\n    FROM information_schema.COLUMNS\n    WHERE TABLE_SCHEMA = DATABASE()\n      AND TABLE_NAME = 'reuniones'\n      AND COLUMN_NAME = 'id_empresa'\n    LIMIT 1\n  ");
  $exists = ($check instanceof mysqli_result) && ($check->num_rows > 0);
  if ($check instanceof mysqli_result) {
    $check->close();
  }

  if (!$exists) {
    $db->query("ALTER TABLE reuniones ADD COLUMN id_empresa INT NULL");
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Metodo no permitido');
}

if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
  redirect_tecnico('menu', 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.');
}

$accion = (string)($_POST['accion'] ?? '');

if ($accion === 'contactar_empresa') {
  $tecnicoId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $asunto = trim((string)($_POST['asunto'] ?? ''));
  $mensaje = trim((string)($_POST['mensaje'] ?? ''));

  if ($tecnicoId <= 0) {
    redirect_tecnico('contacto_empresa', 'Sesion invalida.');
  }
  if ($idEmpresa <= 0 || $asunto === '' || $mensaje === '') {
    redirect_tecnico('contacto_empresa', 'Debes completar empresa, asunto y mensaje.');
  }

  $stmtEmpresa = db()->prepare("\n    SELECT\n      e.id_empresa,\n      e.razon_social,\n      TRIM(COALESCE(e.email, '')) AS email\n    FROM empresa e\n    WHERE e.id_empresa = ?\n      AND (\n        EXISTS (\n          SELECT 1\n          FROM usuario_empresa ue\n          WHERE ue.id_empresa = e.id_empresa\n            AND ue.id_usuario = ?\n        )\n        OR e.id_usuario = ?\n      )\n    LIMIT 1\n  ");
  $stmtEmpresa->bind_param('iii', $idEmpresa, $tecnicoId, $tecnicoId);
  $stmtEmpresa->execute();
  $empresa = $stmtEmpresa->get_result()->fetch_assoc();
  $stmtEmpresa->close();

  if (!$empresa) {
    redirect_tecnico('contacto_empresa', 'No tienes permiso para contactar con esta empresa.');
  }

  $emailDestino = trim((string)($empresa['email'] ?? ''));
  if ($emailDestino === '' || filter_var($emailDestino, FILTER_VALIDATE_EMAIL) === false) {
    redirect_tecnico('contacto_empresa', 'La empresa seleccionada no tiene correo asignado. Debe ser asignado para poder enviar el correo.');
  }

  $stmtTecnico = db()->prepare("SELECT nombre_usuario, email FROM usuario WHERE id_usuario = ? LIMIT 1");
  $stmtTecnico->bind_param('i', $tecnicoId);
  $stmtTecnico->execute();
  $tecnico = $stmtTecnico->get_result()->fetch_assoc();
  $stmtTecnico->close();

  $tecnicoNombre = trim((string)($tecnico['nombre_usuario'] ?? 'Tecnico'));
  $tecnicoEmail = trim((string)($tecnico['email'] ?? ''));
  $empresaNombre = trim((string)($empresa['razon_social'] ?? 'Empresa'));

  try {
    correo_enviar_contacto_tecnico_empresa(
      $emailDestino,
      $empresaNombre,
      $tecnicoNombre,
      $tecnicoEmail,
      $asunto,
      $mensaje
    );

    redirect_tecnico('contacto_empresa', 'Correo enviado correctamente a la empresa.');
  } catch (Throwable $e) {
    log_internal_error_tecnico('tecnico.contactar_empresa', $e);
    redirect_tecnico('contacto_empresa', 'No se pudo enviar el correo. Intentalo de nuevo.');
  }
}

if ($accion === 'editar_perfil') {
  $id = (int)($_POST['id'] ?? 0);
  $username = trim((string)($_POST['nombre_usuario'] ?? ''));
  $apellidos = trim((string)($_POST['apellidos'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $direccion = trim((string)($_POST['direccion'] ?? ''));
  $localidad = trim((string)($_POST['localidad'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  $currentId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  if ($id <= 0 || $currentId <= 0 || $id !== $currentId) {
    redirect_tecnico('perfil', 'No tienes permiso para editar esta cuenta');
  }

  if ($username === '') {
    redirect_tecnico('perfil', 'Faltan datos obligatorios');
  }
  if (strlen($username) < 3) {
    redirect_tecnico('perfil', 'El usuario debe tener al menos 3 caracteres');
  }
  if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_tecnico('perfil', 'Email invalido');
  }
  if ($apellidos !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $apellidos)) {
    redirect_tecnico('perfil', 'Apellidos invalidos: solo letras y espacios (sin numeros).');
  }
  if ($localidad !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $localidad)) {
    redirect_tecnico('perfil', 'Localidad invalida: solo letras y espacios (sin numeros).');
  }
  if ($telefono !== '' && !preg_match('/^\d{6,15}$/', $telefono)) {
    redirect_tecnico('perfil', 'Telefono invalido: solo numeros (6 a 15 digitos).');
  }
  if ($password !== '' && strlen($password) < 6) {
    redirect_tecnico('perfil', 'La contrasena debe tener al menos 6 caracteres');
  }

  $apellidos = ($apellidos === '') ? null : $apellidos;
  $telefono = ($telefono === '') ? null : $telefono;
  $direccion = ($direccion === '') ? null : $direccion;
  $localidad = ($localidad === '') ? null : $localidad;

  try {
    if ($password !== '') {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = db()->prepare("
        UPDATE usuario
        SET nombre_usuario = ?, apellidos = ?, email = ?, telefono = ?, direccion = ?, localidad = ?, password = ?
        WHERE id_usuario = ?
      ");
      $stmt->bind_param('sssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $hash, $id);
    } else {
      $stmt = db()->prepare("
        UPDATE usuario
        SET nombre_usuario = ?, apellidos = ?, email = ?, telefono = ?, direccion = ?, localidad = ?
        WHERE id_usuario = ?
      ");
      $stmt->bind_param('ssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $id);
    }

    $stmt->execute();
    $stmt->close();

    $_SESSION['user']['nombre_usuario'] = $username;

    redirect_tecnico('perfil', 'Perfil actualizado correctamente');
  } catch (Throwable $e) {
    log_internal_error_tecnico('tecnico.editar_perfil', $e);
    redirect_tecnico('perfil', 'No se pudo actualizar el perfil. Intentalo de nuevo.');
  }
}

if ($accion === 'crear_reunion') {
  $tecnicoId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idClienteReunion = (int)($_POST['id_cliente_reunion'] ?? 0);
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $objetivo = trim((string)($_POST['objetivo'] ?? ''));
  $hora = trim((string)($_POST['hora_reunion'] ?? ''));
  $fecha = trim((string)($_POST['fecha_reunion'] ?? ''));

  if ($tecnicoId <= 0) {
    redirect_tecnico('reuniones', 'Sesion invalida');
  }
  if ($fecha === '') {
    redirect_tecnico('reuniones', 'La fecha de la reunion es obligatoria');
  }
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    redirect_tecnico('reuniones', 'La hora de la reunion es invalida');
  }
  $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    redirect_tecnico('reuniones', 'Fecha de reunion invalida');
  }

  // Validar que la empresa existe y pertenece al técnico
  if ($idEmpresa > 0) {
    $stmtEmpresa = db()->prepare("
      SELECT 1 FROM empresa e
      WHERE e.id_empresa = ?
        AND (
          EXISTS (
            SELECT 1 FROM usuario_empresa ue
            WHERE ue.id_empresa = e.id_empresa AND ue.id_usuario = ?
          )
          OR e.id_usuario = ?
        )
      LIMIT 1
    ");
    $stmtEmpresa->bind_param('iii', $idEmpresa, $tecnicoId, $tecnicoId);
    $stmtEmpresa->execute();
    $empresaValida = (bool)$stmtEmpresa->get_result()->fetch_assoc();
    $stmtEmpresa->close();
    
    if (!$empresaValida) {
      redirect_tecnico('reuniones', 'La empresa seleccionada no es valida');
    }
  }

  // Validar cliente si fue seleccionado
  if ($idClienteReunion > 0) {
    $stmtCliente = db()->prepare("
      SELECT 1 FROM usuario u
      INNER JOIN rol r ON r.id = u.rol_id
      INNER JOIN usuario_empresa ue ON ue.id_usuario = u.id_usuario
      WHERE u.id_usuario = ?
        AND UPPER(r.nombre) = 'CLIENTE'
        AND ue.id_empresa = ?
      LIMIT 1
    ");
    $stmtCliente->bind_param('ii', $idClienteReunion, $idEmpresa);
    $stmtCliente->execute();
    $clienteValido = (bool)$stmtCliente->get_result()->fetch_assoc();
    $stmtCliente->close();

    if (!$clienteValido) {
      redirect_tecnico('reuniones', 'El cliente seleccionado no es valido');
    }
  }

  $db = db();
  ensure_reuniones_empresa_column($db);
  try {
    $db->begin_transaction();

    $objetivoDb = ($objetivo === '') ? null : $objetivo;
    $stmt = $db->prepare("INSERT INTO reuniones (objetivo, hora_reunion, fecha_reunion, id_empresa) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $objetivoDb, $hora, $fecha, $idEmpresa);
    $stmt->execute();
    $idReunion = (int)$stmt->insert_id;
    $stmt->close();

    $stmt2 = $db->prepare("INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)");
    $stmt2->bind_param('ii', $tecnicoId, $idReunion);
    $stmt2->execute();
    $stmt2->close();

    // Asignar a cliente si fue seleccionado y es diferente del técnico
    if ($idClienteReunion > 0 && $idClienteReunion !== $tecnicoId) {
      $stmt3 = $db->prepare("INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)");
      $stmt3->bind_param('ii', $idClienteReunion, $idReunion);
      $stmt3->execute();
      $stmt3->close();
    }

    $db->commit();
    redirect_tecnico('reuniones', 'Reunion creada correctamente');
  } catch (Throwable $e) {
    $db->rollback();
    log_internal_error_tecnico('tecnico.crear_reunion', $e);
    redirect_tecnico('reuniones', 'No se pudo crear la reunion. Intentalo de nuevo.');
  }
}

if ($accion === 'editar_reunion') {
  $tecnicoId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idReunion = (int)($_POST['id_reunion'] ?? 0);
  $objetivo = trim((string)($_POST['objetivo'] ?? ''));
  $hora = trim((string)($_POST['hora_reunion'] ?? ''));
  $fecha = trim((string)($_POST['fecha_reunion'] ?? ''));

  if ($tecnicoId <= 0 || $idReunion <= 0) {
    redirect_tecnico('reuniones', 'Datos de reunion invalidos');
  }
  if ($fecha === '') {
    redirect_tecnico('reuniones', 'La fecha de la reunion es obligatoria');
  }
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    redirect_tecnico('reuniones', 'La hora de la reunion es invalida');
  }
  $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    redirect_tecnico('reuniones', 'Fecha de reunion invalida');
  }

  $db = db();
  $check = $db->prepare("SELECT 1 FROM usuario_reunion WHERE id_usuario = ? AND id_reunion = ? LIMIT 1");
  $check->bind_param('ii', $tecnicoId, $idReunion);
  $check->execute();
  $allowedEdit = (bool)$check->get_result()->fetch_assoc();
  $check->close();

  if (!$allowedEdit) {
    redirect_tecnico('reuniones', 'No tienes permiso para editar esta reunion');
  }

  try {
    $objetivoDb = ($objetivo === '') ? null : $objetivo;
    $stmt = $db->prepare("UPDATE reuniones SET objetivo = ?, hora_reunion = ?, fecha_reunion = ? WHERE id_reunion = ?");
    $stmt->bind_param('sssi', $objetivoDb, $hora, $fecha, $idReunion);
    $stmt->execute();
    $stmt->close();

    redirect_tecnico('reuniones', 'Reunion actualizada correctamente');
  } catch (Throwable $e) {
    log_internal_error_tecnico('tecnico.editar_reunion', $e);
    redirect_tecnico('reuniones', 'No se pudo actualizar la reunion. Intentalo de nuevo.');
  }
}

if ($accion === 'eliminar_reunion') {
  $tecnicoId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idReunion = (int)($_POST['id_reunion'] ?? 0);

  if ($tecnicoId <= 0 || $idReunion <= 0) {
    redirect_tecnico('reuniones', 'Datos de reunion invalidos');
  }

  $db = db();
  $check = $db->prepare("SELECT 1 FROM usuario_reunion WHERE id_usuario = ? AND id_reunion = ? LIMIT 1");
  $check->bind_param('ii', $tecnicoId, $idReunion);
  $check->execute();
  $allowedDelete = (bool)$check->get_result()->fetch_assoc();
  $check->close();

  if (!$allowedDelete) {
    redirect_tecnico('reuniones', 'No tienes permiso para eliminar esta reunion');
  }

  try {
    $stmt = $db->prepare("DELETE FROM reuniones WHERE id_reunion = ?");
    $stmt->bind_param('i', $idReunion);
    $stmt->execute();
    $stmt->close();

    redirect_tecnico('reuniones', 'Reunion eliminada correctamente');
  } catch (Throwable $e) {
    log_internal_error_tecnico('tecnico.eliminar_reunion', $e);
    redirect_tecnico('reuniones', 'No se pudo eliminar la reunion. Intentalo de nuevo.');
  }
}

redirect_tecnico('menu');