<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

require __DIR__ . '/../php/auth.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

// Redirige al menú principal del admin 
function redirect_menu(string $msg = ''): void
{
  $to = '/Igualdad/model/admin.php';
  if ($msg !== '') $to .= '?msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

// Redirige a una vista concreta del admin 
function redirect_view(string $view, string $msg = ''): void
{
  $to = '/Igualdad/model/admin.php?view=' . urlencode($view);
  if ($msg !== '') $to .= '&msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

// VALIDACIÓN DE MÉTODO HTTP
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
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
    redirect_view('perfil', 'Error al actualizar el perfil: ' . $e->getMessage());
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
  $password = (string)($_POST['password'] ?? '');
  $rol_id   = (int)($_POST['rol_id'] ?? 0);

  $_SESSION['add_user_old'] = [
    'nombre_usuario' => $username,
    'apellidos' => $apellidos,
    'email' => $email,
    'telefono' => $telefono,
    'direccion' => $direccion,
    'localidad' => $localidad,
    'rol_id' => $rol_id,
  ];

  if ($username === '' || $email === '' || $rol_id <= 0 || $password === '') {
    redirect_view('add', 'Obligatorio rellenar usuario, email, rol y contraseña');
  }
  if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_view('add', 'Email inválido. Debe tener formato ejemplo@dominio.com');
  }
  if (strlen($password) < 6) {
    redirect_view('add', 'La contraseña debe tener al menos 6 caracteres');
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
  // Convertir vacios a null para que la BD guarde Null en campos opcionales
  $apellidos = ($apellidos === '') ? null : $apellidos;
  $telefono = ($telefono === '') ? null : $telefono;
  $direccion = ($direccion == '') ? null : $direccion;
  $localidad = ($localidad == '') ? null : $localidad;

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = db()->prepare("INSERT INTO usuario (nombre_usuario,apellidos, email, telefono, direccion, localidad, password, rol_id) VALUES (?, ? ,?, ?, ?, ?, ?, ?)");
  $stmt->bind_param('sssssssi', $username, $apellidos, $email, $telefono, $direccion, $localidad, $hash, $rol_id);
  $stmt->execute();
  $stmt->close();

  // Enviar email de bienvenida
  require_once __DIR__ . '/../vendor/autoload.php';
  $mailConfig = require __DIR__ . '/../config/mail.php';
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    if ($mailConfig['secure'] !== 'none') {
      $mail->SMTPSecure = $mailConfig['secure'];
    }
    $mail->Port = $mailConfig['port'];
    $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
    $mail->addAddress($email, $username);
    $mail->isHTML(true);
    $mail->Subject = 'Bienvenido a Consultoría Igualdad';
    $mail->Body    = 'Hola <b>' . htmlspecialchars($email) . '</b>,<br>Tu usuario ha sido creado correctamente.<br><br>' .
      'Tus credenciales de acceso son:<br>' .
      '<b>Usuario:</b> ' . htmlspecialchars($username) . '<br>' .
      '<b>Contraseña:</b> ' . htmlspecialchars($password) . '<br><br>' .
      'Puedes acceder a la plataforma desde el siguiente enlace:<br>' .
      '<a href="http://localhost/igualdad/php/login.php">Acceder a la plataforma</a><br><br>' .
      'Por favor, guarda esta información de forma segura.';
    $mail->send();
  } catch (Exception $e) {
    echo 'Error al enviar email: ' . $mail->ErrorInfo;
    exit;
  }

  unset($_SESSION['add_user_old']);
  redirect_menu('Usuario creado');
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
    redirect_view('edit', 'Error al actualizar: ' . $e->getMessage());
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
    redirect_view('delete', 'No se pudo eliminar: ' . $e->getMessage());
  }
}