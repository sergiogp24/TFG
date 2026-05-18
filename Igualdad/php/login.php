<?php
declare(strict_types=1); // evita conversiones automáticas peligrosas.

require __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

require __DIR__ . '/../config/config.php'; 


$error = '';
// Rate limiting basado en BD (no bypasseable borrando la cookie de sesión)
$maxLoginAttempts = 5;
$loginBlockSeconds = 3 * 60; // 3 minutos
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);

// Limpiar intentos expirados de esta IP
$stmtClean = db()->prepare("DELETE FROM rate_limit_log WHERE ip = ? AND action = 'login' AND attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
if ($stmtClean) {
  $stmtClean->bind_param('si', $ip, $loginBlockSeconds);
  $stmtClean->execute();
  $stmtClean->close();
}

// Contar intentos recientes
$stmtCount = db()->prepare("SELECT COUNT(*) AS total FROM rate_limit_log WHERE ip = ? AND action = 'login' AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
$loginBlocked = false;
if ($stmtCount) {
  $stmtCount->bind_param('si', $ip, $loginBlockSeconds);
  $stmtCount->execute();
  $countRow = $stmtCount->get_result()->fetch_assoc();
  $stmtCount->close();
  $loginBlocked = ((int)($countRow['total'] ?? 0) >= $maxLoginAttempts);
}

if ($loginBlocked) {
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
      $stmtAttempt = db()->prepare("INSERT INTO rate_limit_log (ip, action) VALUES (?, 'login')");
      if ($stmtAttempt) { $stmtAttempt->bind_param('s', $ip); $stmtAttempt->execute(); $stmtAttempt->close(); }
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
        $stmtAttempt = db()->prepare("INSERT INTO rate_limit_log (ip, action) VALUES (?, 'login')");
        if ($stmtAttempt) { $stmtAttempt->bind_param('s', $ip); $stmtAttempt->execute(); $stmtAttempt->close(); }
      } else {
        // Login correcto: limpiar intentos
        $stmtClear = db()->prepare("DELETE FROM rate_limit_log WHERE ip = ? AND action = 'login'");
        if ($stmtClear) { $stmtClear->bind_param('s', $ip); $stmtClear->execute(); $stmtClear->close(); }

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