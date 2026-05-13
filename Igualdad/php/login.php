<?php
declare(strict_types=1); // evita conversiones automáticas peligrosas.

require __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

require __DIR__ . '/../config/config.php'; 


$error = '';
// Control de intentos fallidos de login por IP/sesión
$maxLoginAttempts = 5;
$loginBlockTime = 3 * 60; // 3 minutos
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now = time();
if (!isset($_SESSION['login_attempts'])) {
  $_SESSION['login_attempts'] = [];
}
// Limpiar intentos antiguos
foreach ($_SESSION['login_attempts'] as $key => $data) {
  if ($data['time'] < $now - $loginBlockTime) {
    unset($_SESSION['login_attempts'][$key]);
  }
}
if (!isset($_SESSION['login_attempts'][$ip])) {
  $_SESSION['login_attempts'][$ip] = ['count' => 0, 'time' => $now];
}
if ($_SESSION['login_attempts'][$ip]['count'] >= $maxLoginAttempts && $now - $_SESSION['login_attempts'][$ip]['time'] < $loginBlockTime) {
  $error = 'Demasiados intentos fallidos. Intenta de nuevo en unos minutos.';
  require __DIR__ . '/../html/login.html.php';
  exit;
}

if (!is_local_request() && !is_https_request()) {
  $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
  $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
  header('Location: https://' . $host . $requestUri, true, 301);
  exit;
}

if (is_https_request()) {
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Solo procesamos el login si el formulario se ha enviado por POST.
 * Si es GET, simplemente mostrará la vista del login (login.html.php).
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

  if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    $error = 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.';
  } else {

    // Leer los datos que envía el formulario
    $username = trim($_POST['nombre_usuario'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // Validación básica que no permite campos vacíos
    if ($username === '' || $password === '') {
      $error = 'Rellena usuario y contraseña.';
      $_SESSION['login_attempts'][$ip]['count']++;
      $_SESSION['login_attempts'][$ip]['time'] = $now;
    } else {

      //Consulta para buscar el usuario por username.
      $sql = " SELECT u.id_usuario, u.nombre_usuario,u.apellidos, u.email, u.telefono, u.direccion, u.localidad, u.password, r.nombre AS rol FROM usuario u LEFT JOIN rol r ON r.id = u.rol_id
        WHERE u.nombre_usuario = ? LIMIT 1 ";

      $stmt = db()->prepare($sql);      
      $stmt->bind_param('s', $username); 
      $stmt->execute();                 
      $user = $stmt->get_result()->fetch_assoc();
      $stmt->close();              

      //Verificación de credenciales
      if (!$user || !password_verify($password, (string)$user['password'])) {
        $error = 'Usuario o contraseña incorrectos.';
        $_SESSION['login_attempts'][$ip]['count']++;
        $_SESSION['login_attempts'][$ip]['time'] = $now;
      } else {
        // Login correcto: limpiar contador
        $_SESSION['login_attempts'][$ip] = ['count' => 0, 'time' => $now];

        // Evita un atacante forzando un session_id conocido.
        session_regenerate_id(true);

        // Guardamos id, username y rol porque se usan para autorización/redirecciones.
        $_SESSION['user'] = [
          'id_usuario' => (int)$user['id_usuario'],
          'nombre_usuario' => (string)$user['nombre_usuario'],
          'email' => (string)($user['email'] ?? ''),
          'rol' => (string)($user['rol'] ?? ''),
        ];

        // Según el rol del usuario, enviamos a su panel correspondiente.
        switch ($_SESSION['user']['rol']) {
          case 'ADMINISTRADOR':
            header('Location: ../model/admin.php?view=menu');
            exit;
          case 'TECNICO':
            header('Location: ../model/tecnico.php?view=menu');
            exit;
          case 'CLIENTE':
            header('Location: ../html/index_cliente.php');
            exit;
          default:
            // Si el rol no es uno de los esperados
            header('Location: logout.php');
            exit;
        }
      }
    }
  }
}

require __DIR__ . '/../html/login.html.php';