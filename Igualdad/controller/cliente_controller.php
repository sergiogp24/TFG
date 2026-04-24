<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../php/auth.php';
require_role('CLIENTE');

require __DIR__ . '/../config/config.php';

function redirect_cliente(string $view = 'menu', string $msg = ''): void
{
  $to = app_path('/html/index_cliente.php?view=') . urlencode($view);
  if ($msg !== '') {
    $to .= '&msg=' . urlencode($msg);
  }
  header('Location: ' . $to);
  exit;
}

function log_internal_error_cliente(string $context, Throwable $e): void
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
  redirect_cliente('menu', 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.');
}

$accion = (string)($_POST['accion'] ?? '');

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
    redirect_cliente('perfil', 'No tienes permiso para editar esta cuenta');
  }

  if ($username === '') {
    redirect_cliente('perfil', 'Faltan datos obligatorios');
  }
  if (strlen($username) < 3) {
    redirect_cliente('perfil', 'El usuario debe tener al menos 3 caracteres');
  }
  if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_cliente('perfil', 'Email invalido');
  }
  if ($apellidos !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $apellidos)) {
    redirect_cliente('perfil', 'Apellidos invalidos: solo letras y espacios (sin numeros).');
  }
  if ($localidad !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $localidad)) {
    redirect_cliente('perfil', 'Localidad invalida: solo letras y espacios (sin numeros).');
  }
  if ($telefono !== '' && !preg_match('/^\d{6,15}$/', $telefono)) {
    redirect_cliente('perfil', 'Telefono invalido: solo numeros (6 a 15 digitos).');
  }
  if ($password !== '' && strlen($password) < 6) {
    redirect_cliente('perfil', 'La contrasena debe tener al menos 6 caracteres');
  }

  $apellidos = ($apellidos === '') ? null : $apellidos;
  $telefono = ($telefono === '') ? null : $telefono;
  $direccion = ($direccion === '') ? null : $direccion;
  $localidad = ($localidad === '') ? null : $localidad;

  try {
    if ($password !== '') {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = db()->prepare('UPDATE usuario SET nombre_usuario = ?, apellidos = ?, email = ?, telefono = ?, direccion = ?, localidad = ?, password = ? WHERE id_usuario = ?');
      $stmt->bind_param('sssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $hash, $id);
    } else {
      $stmt = db()->prepare('UPDATE usuario SET nombre_usuario = ?, apellidos = ?, email = ?, telefono = ?, direccion = ?, localidad = ? WHERE id_usuario = ?');
      $stmt->bind_param('ssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $id);
    }

    $stmt->execute();
    $stmt->close();

    $_SESSION['user']['nombre_usuario'] = $username;
    $_SESSION['user']['email'] = $email;

    redirect_cliente('perfil', 'Perfil actualizado correctamente');
  } catch (Throwable $e) {
    log_internal_error_cliente('cliente.editar_perfil', $e);
    redirect_cliente('perfil', 'No se pudo actualizar el perfil. Intentalo de nuevo.');
  }
}

if ($accion === 'crear_reunion') {
  $clienteId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idEmpresaReunion = (int)($_POST['id_empresa_reunion'] ?? 0);
  $idTecnicoReunion = (int)($_POST['id_tecnico_reunion'] ?? 0);
  $objetivo = trim((string)($_POST['objetivo'] ?? ''));
  $hora = trim((string)($_POST['hora_reunion'] ?? ''));
  $fecha = trim((string)($_POST['fecha_reunion'] ?? ''));

  if ($clienteId <= 0) {
    redirect_cliente('reuniones', 'Sesion invalida');
  }
  if ($fecha === '') {
    redirect_cliente('reuniones', 'La fecha de la reunion es obligatoria');
  }
  if ($idEmpresaReunion <= 0) {
    redirect_cliente('reuniones', 'Debes seleccionar una empresa');
  }
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    redirect_cliente('reuniones', 'La hora de la reunion es invalida');
  }

  $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    redirect_cliente('reuniones', 'Fecha de reunion invalida');
  }

  $db = db();
  ensure_reuniones_empresa_column($db);

  $stmtEmpresaValida = $db->prepare('
    SELECT 1
    FROM (
      SELECT ue.id_empresa
      FROM usuario_empresa ue
      WHERE ue.id_usuario = ?

      UNION

      SELECT e.id_empresa
      FROM empresa e
      WHERE e.id_usuario = ?
    ) t
    WHERE t.id_empresa = ?
    LIMIT 1
  ');
  if (!$stmtEmpresaValida) {
    redirect_cliente('reuniones', 'No se pudo validar la empresa seleccionada');
  }
  $stmtEmpresaValida->bind_param('iii', $clienteId, $clienteId, $idEmpresaReunion);
  $stmtEmpresaValida->execute();
  $empresaValida = (bool)$stmtEmpresaValida->get_result()->fetch_assoc();
  $stmtEmpresaValida->close();

  if (!$empresaValida) {
    redirect_cliente('reuniones', 'La empresa seleccionada no es valida para tu usuario');
  }

  if ($idTecnicoReunion > 0) {
    $stmtTecnicoValido = $db->prepare('
      SELECT 1
      FROM usuario u
      INNER JOIN rol r ON r.id = u.rol_id
      WHERE u.id_usuario = ?
        AND UPPER(TRIM(r.nombre)) LIKE "TECNICO%"
        AND (
          EXISTS (
            SELECT 1
            FROM usuario_empresa ue
            WHERE ue.id_usuario = u.id_usuario
              AND ue.id_empresa = ?
          )
          OR EXISTS (
            SELECT 1
            FROM empresa e
            WHERE e.id_empresa = ?
              AND e.id_usuario = u.id_usuario
          )
        )
      LIMIT 1
    ');
    if (!$stmtTecnicoValido) {
      redirect_cliente('reuniones', 'No se pudo validar el tecnico seleccionado');
    }
    $stmtTecnicoValido->bind_param('iii', $idTecnicoReunion, $idEmpresaReunion, $idEmpresaReunion);
    $stmtTecnicoValido->execute();
    $tecnicoValido = (bool)$stmtTecnicoValido->get_result()->fetch_assoc();
    $stmtTecnicoValido->close();

    if (!$tecnicoValido) {
      redirect_cliente('reuniones', 'El tecnico seleccionado no pertenece a la empresa indicada');
    }
  }

  try {
    $db->begin_transaction();

    $objetivoDb = ($objetivo === '') ? null : $objetivo;
    $stmt = $db->prepare('INSERT INTO reuniones (objetivo, hora_reunion, fecha_reunion, id_empresa) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sssi', $objetivoDb, $hora, $fecha, $idEmpresaReunion);
    $stmt->execute();
    $idReunion = (int)$stmt->insert_id;
    $stmt->close();

    $stmt2 = $db->prepare('INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)');
    $stmt2->bind_param('ii', $clienteId, $idReunion);
    $stmt2->execute();
    $stmt2->close();

    if ($idTecnicoReunion > 0 && $idTecnicoReunion !== $clienteId) {
      $stmt3 = $db->prepare('INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)');
      $stmt3->bind_param('ii', $idTecnicoReunion, $idReunion);
      $stmt3->execute();
      $stmt3->close();
    }

    $db->commit();
    $msg = ($idTecnicoReunion > 0)
      ? 'Reunion creada y asignada al tecnico correctamente'
      : 'Reunion creada correctamente';
    redirect_cliente('reuniones', $msg);
  } catch (Throwable $e) {
    $db->rollback();
    log_internal_error_cliente('cliente.crear_reunion', $e);
    redirect_cliente('reuniones', 'No se pudo crear la reunion. Intentalo de nuevo.');
  }
}

if ($accion === 'editar_reunion') {
  $clienteId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idReunion = (int)($_POST['id_reunion'] ?? 0);
  $objetivo = trim((string)($_POST['objetivo'] ?? ''));
  $hora = trim((string)($_POST['hora_reunion'] ?? ''));
  $fecha = trim((string)($_POST['fecha_reunion'] ?? ''));

  if ($clienteId <= 0 || $idReunion <= 0) {
    redirect_cliente('reuniones', 'Datos de reunion invalidos');
  }
  if ($fecha === '') {
    redirect_cliente('reuniones', 'La fecha de la reunion es obligatoria');
  }
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    redirect_cliente('reuniones', 'La hora de la reunion es invalida');
  }

  $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    redirect_cliente('reuniones', 'Fecha de reunion invalida');
  }

  $db = db();
  $check = $db->prepare('SELECT 1 FROM usuario_reunion WHERE id_usuario = ? AND id_reunion = ? LIMIT 1');
  $check->bind_param('ii', $clienteId, $idReunion);
  $check->execute();
  $allowedEdit = (bool)$check->get_result()->fetch_assoc();
  $check->close();

  if (!$allowedEdit) {
    redirect_cliente('reuniones', 'No tienes permiso para editar esta reunion');
  }

  try {
    $objetivoDb = ($objetivo === '') ? null : $objetivo;
    $stmt = $db->prepare('UPDATE reuniones SET objetivo = ?, hora_reunion = ?, fecha_reunion = ? WHERE id_reunion = ?');
    $stmt->bind_param('sssi', $objetivoDb, $hora, $fecha, $idReunion);
    $stmt->execute();
    $stmt->close();

    redirect_cliente('reuniones', 'Reunion actualizada correctamente');
  } catch (Throwable $e) {
    log_internal_error_cliente('cliente.editar_reunion', $e);
    redirect_cliente('reuniones', 'No se pudo actualizar la reunion. Intentalo de nuevo.');
  }
}

if ($accion === 'eliminar_reunion') {
  $clienteId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idReunion = (int)($_POST['id_reunion'] ?? 0);

  if ($clienteId <= 0 || $idReunion <= 0) {
    redirect_cliente('reuniones', 'Datos de reunion invalidos');
  }

  $db = db();
  $check = $db->prepare('SELECT 1 FROM usuario_reunion WHERE id_usuario = ? AND id_reunion = ? LIMIT 1');
  $check->bind_param('ii', $clienteId, $idReunion);
  $check->execute();
  $allowedDelete = (bool)$check->get_result()->fetch_assoc();
  $check->close();

  if (!$allowedDelete) {
    redirect_cliente('reuniones', 'No tienes permiso para eliminar esta reunion');
  }

  try {
    $stmt = $db->prepare('DELETE FROM reuniones WHERE id_reunion = ?');
    $stmt->bind_param('i', $idReunion);
    $stmt->execute();
    $stmt->close();

    redirect_cliente('reuniones', 'Reunion eliminada correctamente');
  } catch (Throwable $e) {
    log_internal_error_cliente('cliente.eliminar_reunion', $e);
    redirect_cliente('reuniones', 'No se pudo eliminar la reunion. Intentalo de nuevo.');
  }
}

redirect_cliente('menu', 'Accion no valida');
