<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../php/auth.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../php/password_reset_tokens.php';
require_once __DIR__ . '/../php/mails.php';

// Redirige al menú principal del admin 
function redirect_menu(string $msg = ''): void
{
  $to = app_path('/model/admin.php');
  if ($msg !== '') $to .= '?msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

// Redirige a una vista concreta del admin 
function redirect_view(string $view, string $msg = ''): void
{
  $to = app_path('/model/admin.php?view=') . urlencode($view);
  if ($msg !== '') $to .= '&msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

function log_internal_error(string $context, Throwable $e): void
{
  error_log(sprintf(
    '[%s] %s in %s:%d',
    $context,
    $e->getMessage(),
    $e->getFile(),
    $e->getLine()
  ));
}

function normalize_role_name(string $roleName): string
{
  $upper = strtoupper(trim($roleName));
  return str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $upper);
}

// VALIDACIÓN DE MÉTODO HTTP
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
  redirect_menu('La sesion ha expirado. Recarga la pagina e intentalo de nuevo.');
}

// LECTURA DE ACCIÓN
$accion = (string)($_POST['accion'] ?? '');

// EDITAR PERFIL DEL ADMIN
if ($accion === 'editar_perfil') {
  $id = (int)($_POST['id'] ?? 0);
  $username = trim((string)($_POST['nombre_usuario'] ?? ''));
  $apellidos = trim((string)($_POST['apellidos'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $direccion = trim((string)($_POST['direccion'] ?? ''));
  $localidad = trim((string)($_POST['localidad'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  // Validar que solo edite su propia cuenta
  $currentId = (int)($_SESSION['user']['id_usuario'] ?? 0); // <-- FIX
  if ($id <= 0 || $currentId <= 0 || $id !== $currentId) {
    redirect_menu('No tienes permiso para editar esta cuenta');
  }

  // Validar datos
  if ($username === '') {
    redirect_view('perfil', 'Faltan datos obligatorios');
  }
  if (strlen($username) < 3) {
    redirect_view('perfil', 'El usuario debe tener al menos 3 caracteres');
  }
  if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_view('perfil', 'Email inválido');
  }
  if ($apellidos !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $apellidos)) {
    redirect_view('perfil', 'Apellidos inválidos: solo letras y espacios (sin números).');
  }
  if ($localidad !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $localidad)) {
    redirect_view('perfil', 'Localidad inválida: solo letras y espacios (sin números).');
  }
  if ($telefono !== '' && !preg_match('/^\d{6,15}$/', $telefono)) {
    redirect_view('perfil', 'Teléfono inválido: solo números (6 a 15 dígitos).');
  }
  if ($password !== '' && strlen($password) < 6) {
    redirect_view('perfil', 'La contraseña debe tener al menos 6 caracteres');
  }

  // Convertir vacíos a NULL
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
      // 7 strings + 1 int = 8 tipos
      $stmt->bind_param('sssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $hash, $id);
    } else {
      $stmt = db()->prepare("
        UPDATE usuario
        SET nombre_usuario = ?, apellidos = ?, email = ?, telefono = ?, direccion = ?, localidad = ?
        WHERE id_usuario = ?
      ");
      // 6 strings + 1 int = 7 tipos
      $stmt->bind_param('ssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $id);
    }

    $stmt->execute();
    $stmt->close();

    // Actualizar sesión
    $_SESSION['user']['nombre_usuario'] = $username;

    redirect_view('perfil', 'Perfil actualizado correctamente');
  } catch (Throwable $e) {
    log_internal_error('admin.editar_perfil', $e);
    redirect_view('perfil', 'No se pudo actualizar el perfil. Intentalo de nuevo.');
  }
}

// CREAR USUARIO
if ($accion === 'crear') {
  $username = trim((string)($_POST['nombre_usuario'] ?? ''));
  $apellidos = trim((string)($_POST['apellidos'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $direccion = trim((string)($_POST['direccion'] ?? ''));
  $localidad = trim((string)($_POST['localidad'] ?? ''));
  $rol_id   = (int)($_POST['rol_id'] ?? 0);
  $empresaIds = $_POST['empresas'] ?? [];

  if (!is_array($empresaIds)) {
    $empresaIds = [$empresaIds];
  }
  $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds), static fn(int $id): bool => $id > 0)));

  $_SESSION['add_user_old'] = [
    'nombre_usuario' => $username,
    'apellidos' => $apellidos,
    'email' => $email,
    'telefono' => $telefono,
    'direccion' => $direccion,
    'localidad' => $localidad,
    'rol_id' => $rol_id,
    'empresas' => $empresaIds,
  ];

  // Validaciones
  if ($username === '' || $email === '' || $rol_id <= 0) {
    redirect_view('add', 'Obligatorio rellenar usuario, email y rol');
  }
  if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_view('add', 'Email inválido. Debe tener formato ejemplo@dominio.com');
  }
  if ($apellidos !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $apellidos)) {
    redirect_view('add', 'Apellidos inválidos: solo letras y espacios (sin números).');
  }
  if ($localidad !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $localidad)) {
    redirect_view('add', 'Localidad inválida: solo letras y espacios (sin números).');
  }
  if ($telefono !== '' && !preg_match('/^\d{6,15}$/', $telefono)) {
    redirect_view('add', 'Teléfono inválido: solo números (6 a 15 dígitos).');
  }

  $db = db();
  $rolNombreNormalizado = '';
  $stmtRol = $db->prepare("SELECT nombre FROM rol WHERE id = ? LIMIT 1");
  if ($stmtRol) {
    $stmtRol->bind_param('i', $rol_id);
    $stmtRol->execute();
    $rowRol = $stmtRol->get_result()->fetch_assoc() ?: null;
    $stmtRol->close();
    $rolNombreNormalizado = normalize_role_name((string)($rowRol['nombre'] ?? ''));
  }

  if ($rolNombreNormalizado === '') {
    redirect_view('add', 'Rol inválido');
  }

  $esRolCliente = ($rolNombreNormalizado === 'CLIENTE');
  $esRolTecnico = str_starts_with($rolNombreNormalizado, 'TECNICO');

  if ($esRolCliente && empty($empresaIds)) {
    redirect_view('add', 'Para rol Cliente es obligatorio asignar al menos una empresa.');
  }

  // Crear usuario con contraseña temporal hasheada.
  // Evita fallos en entornos donde la columna password es NOT NULL sin DEFAULT.
  $temporaryPassword = bin2hex(random_bytes(24));
  $temporaryPasswordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

  try {
    $stmt = db()->prepare("INSERT INTO usuario (nombre_usuario, apellidos, email, telefono, direccion, localidad, password, rol_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $temporaryPasswordHash, $rol_id);
    $stmt->execute();
  } catch (mysqli_sql_exception $e) {
    log_internal_error('admin.crear_usuario', $e);
    if ((int)$e->getCode() === 1062) {
      redirect_view('add', 'No se pudo crear el usuario: el nombre de usuario o email ya existe.');
    }
    redirect_view('add', 'Error al crear usuario. Revisa los datos e intentalo de nuevo.');
  } catch (Throwable $e) {
    log_internal_error('admin.crear_usuario', $e);
    redirect_view('add', 'Error al crear usuario. Intentalo de nuevo.');
  }
  $newUserId = (int)$stmt->insert_id;
  $stmt->close();

  // Generar token temporal (válido por 7 días)
  $token = bin2hex(random_bytes(32)); // Token seguro de 64 caracteres
  $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 días desde ahora misma hora Si bug swaping a (168*60*60)
  $fechaReunion = date('Y-m-d', strtotime('+15 day')); // TODO: Cambiar a 15 dias cuando fin test
  $horaReunion = '9:00';
  $objetivoReunion = 'Subir R.R';

  try {
    save_password_reset_token($email, $token, $expiresAt);

    // Validar que el token se guardó correctamente
    $stmtVerify = db()->prepare("SELECT token FROM password_reset_token WHERE token = ? LIMIT 1");
    if (!$stmtVerify) {
      throw new Exception('Error verificando token: ' . db()->error);
    }
    $stmtVerify->bind_param('s', $token);
    $stmtVerify->execute();
    if ($stmtVerify->get_result()->num_rows === 0) {
      $stmtVerify->close();
      throw new Exception('El token no se guardó correctamente en la BD');
    }
    $stmtVerify->close();
  } catch (Throwable $e) {
    log_internal_error('admin.guardar_token_reset', $e);
    redirect_view('add', 'No se pudo guardar el token de reset. Intentalo de nuevo.');
  }

  try {
    $db->begin_transaction();

    // Construir el objetivo con la lista de empresas asignadas
    $empresasObjetivo = [];
    if (!empty($empresaIds)) {
      $stmtEmpresas = $db->prepare("SELECT razon_social FROM empresa WHERE id_empresa = ?");
      foreach ($empresaIds as $idEmpresa) {
        $stmtEmpresas->bind_param('i', $idEmpresa);
        $stmtEmpresas->execute();
        $res = $stmtEmpresas->get_result();
        if ($row = $res->fetch_assoc()) {
          $empresasObjetivo[] = trim((string)($row['razon_social'] ?? ''));
        }
      }
      $stmtEmpresas->close();
    }
    $objetivoReunionFinal = 'Subir R.R';
    if (!empty($empresasObjetivo)) {
      $objetivoReunionFinal .= ' - ' . implode(', ', $empresasObjetivo);
    }
    $stmtReunion = $db->prepare("INSERT INTO reuniones (objetivo, hora_reunion, fecha_reunion) VALUES (?, ?, ?)");
    $stmtReunion->bind_param('sss', $objetivoReunionFinal, $horaReunion, $fechaReunion);
    $stmtReunion->execute();
    $idReunion = (int)$stmtReunion->insert_id;
    $stmtReunion->close();

    $stmtUsuarioReunion = $db->prepare("INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)");
    $stmtUsuarioReunion->bind_param('ii', $newUserId, $idReunion);
    $stmtUsuarioReunion->execute();
    $stmtUsuarioReunion->close();

    if (!empty($empresaIds)) {
      $stmtEmpresaUsuario = $db->prepare("INSERT INTO usuario_empresa (id_usuario, id_empresa) VALUES (?, ?)");
      foreach ($empresaIds as $idEmpresa) {
        $stmtEmpresaUsuario->bind_param('ii', $newUserId, $idEmpresa);
        $stmtEmpresaUsuario->execute();
      }
      $stmtEmpresaUsuario->close();
    }

    $db->commit();
  } catch (Throwable $e) {
    $db->rollback();
    log_internal_error('admin.crear_reunion_usuario', $e);
    redirect_view('add', 'No se pudo crear la reunion o asignar empresas. Intentalo de nuevo.');
  }

  $assignedCompanies = [];
  if ($newUserId > 0) {
    $stmtEmpresasAsignadas = $db->prepare("\n      SELECT\n        e.razon_social,\n        COALESCE((\n          SELECT ce.tipo_contrato\n          FROM contrato_empresa ce\n          WHERE ce.id_empresa = e.id_empresa\n          ORDER BY ce.id_contrato_empresa DESC\n          LIMIT 1\n        ), 'SIN CONTRATO') AS tipo_contrato\n      FROM usuario_empresa ue\n      INNER JOIN empresa e ON e.id_empresa = ue.id_empresa\n      WHERE ue.id_usuario = ?\n      ORDER BY e.razon_social ASC\n    ");
    if ($stmtEmpresasAsignadas) {
      $stmtEmpresasAsignadas->bind_param('i', $newUserId);
      $stmtEmpresasAsignadas->execute();
      $resEmpresasAsignadas = $stmtEmpresasAsignadas->get_result();
      while ($rowEmpresaAsignada = $resEmpresasAsignadas->fetch_assoc()) {
        $assignedCompanies[] = [
          'razon_social' => trim((string)($rowEmpresaAsignada['razon_social'] ?? '')),
          'tipo_contrato' => trim((string)($rowEmpresaAsignada['tipo_contrato'] ?? 'SIN CONTRATO')),
        ];
      }
      $stmtEmpresasAsignadas->close();
    }
  }

  $resetLink = correo_url_base() . '/php/reset_password.php?token=' . urlencode($token);
  try {
    if ($esRolTecnico) {
      correo_enviar_alta_usuario_tecnico($email, $username, $resetLink);
    } else {
      correo_enviar_alta_usuario($email, $username, $resetLink, $assignedCompanies);
    }
  } catch (Throwable $e) {
    log_internal_error('admin.enviar_email_reset', $e);
    redirect_view('add', 'Usuario creado, pero hubo un error al enviar el email. Contacta al administrador.');
  }

  if ($esRolTecnico && !empty($empresaIds)) {
    foreach ($empresaIds as $idEmpresaAsignada) {
      $idEmpresaAsignada = (int)$idEmpresaAsignada;
      if ($idEmpresaAsignada <= 0) {
        continue;
      }

      $empresaServicio = correo_obtener_empresa_y_servicio($db, $idEmpresaAsignada);
      if ($empresaServicio === null) {
        continue;
      }

      $empresaNombre = (string)($empresaServicio['empresa'] ?? '');
      $servicioNombre = (string)($empresaServicio['servicio'] ?? 'Pendiente de asignar');
      $urlEmpresa = app_path('/model/empresa.php?view=ver_empresa&id_empresa=' . $idEmpresaAsignada . '&from=tecnico');

      try {
        correo_enviar_nueva_empresa_asignada(
          $email,
          $username,
          $empresaNombre,
          $servicioNombre,
          $urlEmpresa
        );
      } catch (Throwable $e) {
        log_internal_error('admin.enviar_correo_empresa_asignada_alta', $e);
      }
    }
  }

  unset($_SESSION['add_user_old']);
  redirect_menu('Usuario creado. Se ha enviado un enlace de configuración de contraseña al email proporcionado.');
}

// EDITAR USUARIO (ADMIN)
if ($accion === 'editar') {
  $id       = (int)($_POST['id_usuario'] ?? 0);
  $username = trim((string)($_POST['nombre_usuario'] ?? ''));
  $apellidos = trim((string)($_POST['apellidos'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $direccion = trim((string)($_POST['direccion'] ?? ''));
  $localidad = trim((string)($_POST['localidad'] ?? ''));
  $rol_id   = (int)($_POST['rol_id'] ?? 0);
  $password = (string)($_POST['password'] ?? '');

  //Validaciones básicas
  if ($id <= 0 || $username === '' || $email === '' || $rol_id <= 0) {
    redirect_view('edit', 'Faltan datos');
  }
  if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_view('edit', 'Email inválido. Debe tener formato ejemplo@dominio.com');
  }
  if ($password !== '' && strlen($password) < 6) {
    redirect_view('edit', 'La contraseña debe tener al menos 6 caracteres');
  }
  if ($apellidos !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $apellidos)) {
    redirect_view('edit', 'Apellidos inválidos: solo letras y espacios (sin números).');
  }
  if ($localidad !== '' && !preg_match('/^[\p{L}\s\-\'\.]{2,60}$/u', $localidad)) {
    redirect_view('add', 'Localidad inválida: solo letras y espacios (sin números).');
  }
  if ($telefono !== '' && !preg_match('/^\d{6,15}$/', $telefono)) {
    redirect_view('add', 'Teléfono inválido: solo números (6 a 15 dígitos).');
  }

  // Convertir vacíos a NULL (campos opcionales)
  $apellidos = ($apellidos === '') ? null : $apellidos;
  $telefono  = ($telefono === '') ? null : $telefono;
  $direccion = ($direccion === '') ? null : $direccion;
  $localidad = ($localidad === '') ? null : $localidad;


  $db = db();

  try {
    if ($password !== '') {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $db->prepare("UPDATE usuario SET nombre_usuario = ?, apellidos= ?, email = ?, telefono = ?, direccion = ?, localidad = ?,  password = ?, rol_id = ? WHERE id_usuario = ?");
      $stmt->bind_param('sssssssii', $username, $apellidos, $email, $telefono, $direccion, $localidad, $hash, $rol_id, $id);
    } else {
      $stmt = $db->prepare("UPDATE usuario SET nombre_usuario = ?, apellidos = ?, email= ?, telefono = ?, direccion = ?, localidad= ?, rol_id= ? WHERE id_usuario = ?");
      $stmt->bind_param('ssssssii', $username, $apellidos, $email, $telefono, $direccion, $localidad, $rol_id, $id);
    }

    $stmt->execute();
    $stmt->close();

    redirect_menu('Usuario actualizado');
  } catch (Throwable $e) {
    log_internal_error('admin.editar_usuario', $e);
    redirect_view('edit', 'No se pudo actualizar el usuario. Intentalo de nuevo.');
  }
}

// ELIMINAR USUARIO
if ($accion === 'eliminar') {
  $id = (int)($_POST['id_usuario'] ?? 0);
  if ($id <= 0) redirect_view('delete', 'ID inválido');

  $currentId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  if ($currentId === $id) redirect_view('delete', 'No puedes eliminar tu propio usuario');

  $stmt = db()->prepare("DELETE FROM usuario WHERE id_usuario = ?");
  $stmt->bind_param('i', $id);

  try {
    $stmt->execute();
    $stmt->close();
    redirect_menu('Usuario eliminado');
  } catch (Throwable $e) {
    log_internal_error('admin.eliminar_usuario', $e);
    redirect_view('delete', 'No se pudo eliminar el usuario. Intentalo de nuevo.');
  }
}

if ($accion === 'crear_reunion') {
  $adminId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idClienteReunion = (int)($_POST['id_cliente_reunion'] ?? 0);
  $objetivo = trim((string)($_POST['objetivo'] ?? ''));
  $hora = trim((string)($_POST['hora_reunion'] ?? ''));
  $fecha = trim((string)($_POST['fecha_reunion'] ?? ''));

  if ($adminId <= 0) {
    redirect_view('reuniones', 'Sesion invalida');
  }
  if ($fecha === '') {
    redirect_view('reuniones', 'La fecha de la reunion es obligatoria');
  }
  if ($hora === '') {
    redirect_view('reuniones', 'La hora de la reunion es obligatoria');
  }
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    redirect_view('reuniones', 'La hora de la reunion es invalida');
  }
  $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    redirect_view('reuniones', 'Fecha de reunion invalida');
  }

  if ($idClienteReunion > 0) {
    $stmtCliente = db()->prepare("\n      SELECT 1\n      FROM usuario u\n      INNER JOIN rol r ON r.id = u.rol_id\n      WHERE u.id_usuario = ?\n        AND UPPER(r.nombre) = 'CLIENTE'\n      LIMIT 1\n    ");
    if (!$stmtCliente) {
      redirect_view('reuniones', 'No se pudo validar el cliente seleccionado');
    }
    $stmtCliente->bind_param('i', $idClienteReunion);
    $stmtCliente->execute();
    $clienteValido = (bool)$stmtCliente->get_result()->fetch_assoc();
    $stmtCliente->close();

    if (!$clienteValido) {
      redirect_view('reuniones', 'El cliente seleccionado no es valido');
    }
  }

  $db = db();
  try {
    $db->begin_transaction();

    $objetivoDb = ($objetivo === '') ? null : $objetivo;
    $stmt = $db->prepare("INSERT INTO reuniones (objetivo, hora_reunion, fecha_reunion) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $objetivoDb, $hora, $fecha);
    $stmt->execute();
    $idReunion = (int)$stmt->insert_id;
    $stmt->close();

    $stmt2 = $db->prepare("INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)");
    $stmt2->bind_param('ii', $adminId, $idReunion);
    $stmt2->execute();
    $stmt2->close();

    if ($idClienteReunion > 0 && $idClienteReunion !== $adminId) {
      $stmt3 = $db->prepare("INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)");
      $stmt3->bind_param('ii', $idClienteReunion, $idReunion);
      $stmt3->execute();
      $stmt3->close();
    }

    $db->commit();
    $msg = ($idClienteReunion > 0)
      ? 'Reunion creada y asignada al cliente correctamente'
      : 'Reunion creada correctamente';
    redirect_view('reuniones', $msg);
  } catch (Throwable $e) {
    $db->rollback();
    log_internal_error('admin.crear_reunion', $e);
    redirect_view('reuniones', 'No se pudo crear la reunion. Intentalo de nuevo.');
  }
}

if ($accion === 'editar_reunion') {
  $adminId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idReunion = (int)($_POST['id_reunion'] ?? 0);
  $objetivo = trim((string)($_POST['objetivo'] ?? ''));
  $hora = trim((string)($_POST['hora_reunion'] ?? ''));
  $fecha = trim((string)($_POST['fecha_reunion'] ?? ''));

  if ($adminId <= 0 || $idReunion <= 0) {
    redirect_view('reuniones', 'Datos de reunion invalidos');
  }
  if ($fecha === '') {
    redirect_view('reuniones', 'La fecha de la reunion es obligatoria');
  }
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    redirect_view('reuniones', 'La hora de la reunion es invalida');
  }
  $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    redirect_view('reuniones', 'Fecha de reunion invalida');
  }

  $db = db();
  $check = $db->prepare("SELECT 1 FROM usuario_reunion WHERE id_usuario = ? AND id_reunion = ? LIMIT 1");
  $check->bind_param('ii', $adminId, $idReunion);
  $check->execute();
  $allowedEdit = (bool)$check->get_result()->fetch_assoc();
  $check->close();

  if (!$allowedEdit) {
    redirect_view('reuniones', 'No tienes permiso para editar esta reunion');
  }

  try {
    $objetivoDb = ($objetivo === '') ? null : $objetivo;
    $stmt = $db->prepare("UPDATE reuniones SET objetivo = ?, hora_reunion = ?, fecha_reunion = ? WHERE id_reunion = ?");
    $stmt->bind_param('sssi', $objetivoDb, $hora, $fecha, $idEmpresa, $idReunion);
    $stmt->execute();
    $stmt->close();

    redirect_view('reuniones', 'Reunion actualizada correctamente');
  } catch (Throwable $e) {
    log_internal_error('admin.editar_reunion', $e);
    redirect_view('reuniones', 'No se pudo actualizar la reunion. Intentalo de nuevo.');
  }
}

if ($accion === 'eliminar_reunion') {
  $adminId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idReunion = (int)($_POST['id_reunion'] ?? 0);

  if ($adminId <= 0 || $idReunion <= 0) {
    redirect_view('reuniones', 'Datos de reunion invalidos');
  }

  $db = db();
  $check = $db->prepare("SELECT 1 FROM usuario_reunion WHERE id_usuario = ? AND id_reunion = ? LIMIT 1");
  $check->bind_param('ii', $adminId, $idReunion);
  $check->execute();
  $allowedDelete = (bool)$check->get_result()->fetch_assoc();
  $check->close();

  if (!$allowedDelete) {
    redirect_view('reuniones', 'No tienes permiso para eliminar esta reunion');
  }

  try {
    $stmt = $db->prepare("DELETE FROM reuniones WHERE id_reunion = ?");
    $stmt->bind_param('i', $idReunion);
    $stmt->execute();
    $stmt->close();

    redirect_view('reuniones', 'Reunion eliminada correctamente');
  } catch (Throwable $e) {
    log_internal_error('admin.eliminar_reunion', $e);
    redirect_view('reuniones', 'No se pudo eliminar la reunion. Intentalo de nuevo.');
  }
}

redirect_menu('Accion no valida');
