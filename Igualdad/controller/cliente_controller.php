<?php

declare(strict_types=1);


// Carga de utilidades y control de acceso
// - `auth.php` gestiona la sesión y la autenticación
require __DIR__ . '/../php/auth.php';

// Helpers y verificación de rol: este controller acepta solo usuarios CLIENTE
require_once __DIR__ . '/../php/helpers.php';
require_role('CLIENTE');

// Configuración general y helpers (db(), env(), app_path(), etc.)
require __DIR__ . '/../config/config.php';

/**
 * Redirige a una vista del frontend de cliente (`html/index_cliente.php`).
 *
 * @param string $view Vista objetivo (ej. 'menu', 'perfil', 'reuniones')
 * @param string $msg  Mensaje opcional que se pasará por querystring
 */
function redirect_cliente(string $view = 'menu', string $msg = ''): void
{
  $to = app_path('/html/index_cliente.php?view=') . urlencode($view);
  if ($msg !== '') {
    $to .= '&msg=' . urlencode($msg);
  }
  header('Location: ' . $to);
  exit;
}

/**
 * Registra errores internos relacionados con acciones de cliente.
 * El $context ayuda a identificar el origen (ej. 'cliente.crear_reunion').
 */
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



/*
 * Validación inicial de la petición:
 * - Solo se aceptan POST
 * - Validación CSRF
 * - Lectura del parámetro `accion` que determina la operación a realizar
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Metodo no permitido');
}

if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
  redirect_cliente('menu', 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.');
}

$accion = (string)($_POST['accion'] ?? '');

// ---------- Accion: editar_perfil ----------
// Permite al cliente editar su propio perfil. Valida datos y actualiza la tabla `usuario`.
if ($accion === 'editar_perfil') {
  // --- Flujo: editar_perfil
  // Pasos:
  // 1) Leer campos del formulario y normalizarlos
  // 2) Comprobar que el usuario solo edita su propia cuenta
  // 3) Validar cada campo (longitudes, formatos)
  // 4) Preparar y ejecutar el UPDATE en la tabla `usuario`
  // 5) Regenerar sesión si se cambió la contraseña y actualizar datos en sesión
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

  // Validaciones básicas de presencia y formato
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

  // Intentar persistir cambios en BD
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

    // Si se actualizó la contraseña, regeneramos la sesión por seguridad
    if ($password !== '') {
      session_regenerate_id(true);
    }

    // Reflejar cambios en la sesión actual
    $_SESSION['user']['nombre_usuario'] = $username;
    $_SESSION['user']['email'] = $email;

    redirect_cliente('perfil', 'Perfil actualizado correctamente');
  } catch (mysqli_sql_exception $e) {
    log_internal_error_cliente('cliente.editar_perfil', $e);
    if ((int)$e->getCode() === 1062) {
      redirect_cliente('perfil', 'No se pudo actualizar: el email ya está en uso por otro usuario.');
    }
    redirect_cliente('perfil', 'Error al actualizar el perfil. Intentalo de nuevo.');
  } catch (Throwable $e) {
    log_internal_error_cliente('cliente.editar_perfil', $e);
    redirect_cliente('perfil', 'No se pudo actualizar el perfil. Intentalo de nuevo.');
  }
}

// ---------- Accion: crear_reunion ----------
// Crea una reunión para una empresa asociada al cliente y (opcionalmente)
// asigna un técnico. Realiza validaciones de fecha/hora y pertenencia a empresa.
if ($accion === 'crear_reunion') {
  $clienteId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idEmpresaReunion = (int)($_POST['id_empresa_reunion'] ?? 0);
  $idTecnicoReunion = (int)($_POST['id_tecnico_reunion'] ?? 0);
  $objetivo = trim((string)($_POST['objetivo'] ?? ''));
  $hora = trim((string)($_POST['hora_reunion'] ?? ''));
  $fecha = trim((string)($_POST['fecha_reunion'] ?? ''));

  // Validaciones iniciales de entrada (presencia/formatos)
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

  // Validar que la empresa pertenece al usuario (como propietario o asignada)
  $db = db();

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
    // Empresa no asociada al cliente
    redirect_cliente('reuniones', 'La empresa seleccionada no es valida para tu usuario');
  }

  // Si se seleccionó un técnico, comprobar que exista, sea técnico y esté vinculado
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

  // Insertar reunión y enlaces dentro de una transacción para atomicidad
  try {
    $db->begin_transaction();

    $objetivoDb = ($objetivo === '') ? null : $objetivo;
    $tipoReunion = 'CreadaUsu';
    $stmt = $db->prepare('INSERT INTO reuniones (objetivo, hora_reunion, fecha_reunion, id_empresa, tipo) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssis', $objetivoDb, $hora, $fecha, $idEmpresaReunion, $tipoReunion);
    $stmt->execute();
    $idReunion = (int)$stmt->insert_id;
    $stmt->close();

    // Asociar al cliente creador
    $stmt2 = $db->prepare('INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)');
    $stmt2->bind_param('ii', $clienteId, $idReunion);
    $stmt2->execute();
    $stmt2->close();

    // Asociar al técnico si se indicó
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

// ---------- Accion: editar_reunion ----------
// Edita los datos de una reunión si el usuario está asociado a la misma.
// Editar reunion: validar pertenencia y actualizar campos
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

// ---------- Accion: eliminar_reunion ----------
// Elimina una reunión si el usuario tiene permiso (está registrado en `usuario_reunion`).
// Eliminar reunion: comprobar permiso y borrar fila en `reuniones`
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
