<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/password_reset_tokens.php';
require_once __DIR__ . '/mails.php';
header('Content-Type: text/html; charset=UTF-8');


$error = '';
$success = '';
$identifier = '';
// Control de intentos de recuperación por IP/sesión
$maxRecoveryAttempts = 5;
$recoveryBlockTime = 60 * 60; // 1 hora
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now = time();
if (!isset($_SESSION['recovery_attempts'])) {
  $_SESSION['recovery_attempts'] = [];
}
// Limpiar intentos antiguos
foreach ($_SESSION['recovery_attempts'] as $key => $data) {
  if ($data['time'] < $now - $recoveryBlockTime) {
    unset($_SESSION['recovery_attempts'][$key]);
  }
}
if (!isset($_SESSION['recovery_attempts'][$ip])) {
  $_SESSION['recovery_attempts'][$ip] = ['count' => 0, 'time' => $now];
}
if ($_SESSION['recovery_attempts'][$ip]['count'] >= $maxRecoveryAttempts && $now - $_SESSION['recovery_attempts'][$ip]['time'] < $recoveryBlockTime) {
  $error = 'Demasiados intentos de recuperación. Intenta de nuevo en una hora.';
  $_SESSION['recovery_attempts'][$ip]['time'] = $now;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $error === '') {
  if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    $error = 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.';
    $_SESSION['recovery_attempts'][$ip]['count']++;
    $_SESSION['recovery_attempts'][$ip]['time'] = $now;
  } else {
    $identifier = trim((string)($_POST['identifier'] ?? ''));

    if ($identifier === '') {
      $error = 'Introduce tu email o nombre de usuario.';
      $_SESSION['recovery_attempts'][$ip]['count']++;
      $_SESSION['recovery_attempts'][$ip]['time'] = $now;
    } else {
      $success = 'Si los datos son correctos, te hemos enviado un enlace para restablecer tu contrasena.';

      $stmt = db()->prepare('SELECT nombre_usuario, email FROM usuario WHERE email = ? OR nombre_usuario = ? LIMIT 1');
      $stmt->bind_param('ss', $identifier, $identifier);
      $stmt->execute();
      $user = $stmt->get_result()->fetch_assoc() ?: null;
      $stmt->close();

      if ($user) {
        try {
          delete_expired_password_reset_tokens();

          $email = (string)$user['email'];
          $username = (string)$user['nombre_usuario'];
          $token = bin2hex(random_bytes(32)); // Token seguro de 64 caracteres
          $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // Token válido por 24 horas 

          save_password_reset_token($email, $token, $expiresAt);

          $baseUrl = correo_url_base();
          $resetLink = $baseUrl . '/php/reset_password.php?token=' . urlencode($token);
          correo_enviar_restablecimiento_contrasena($email, $username, $resetLink);
        } catch (Throwable $e) {
          error_log('Error en recuperacion de contrasena: ' . $e->getMessage());
          $error = 'No se pudo completar el envio del enlace. Intentalo de nuevo en unos minutos.';
          $success = '';
        }
      }
      // Registrar intento solo si el usuario existe o no, para evitar enumeración
      $_SESSION['recovery_attempts'][$ip]['count']++;
      $_SESSION['recovery_attempts'][$ip]['time'] = $now;
    }
  }
}

?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Restablecer contraseña</title>
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/login.css">
</head>

<body>
  <div class="login-container">
    <div class="login-card">

      <!-- Encabezado con logo -->
      <div class="login-header">
        <div class="login-logo">CI</div>
        <div>
          <h1 class="login-title">Restablecer contraseña</h1>
          <p class="login-subtitle">Recibe un enlace seguro por email</p>
        </div>
      </div>

      <!-- MENSAJE DE ERROR -->
      <?php if ($error !== ''): ?>
        <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <!-- MENSAJE DE ÉXITO -->
      <?php if ($success !== ''): ?>
        <div class="login-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <!-- FORMULARIO -->
      <form method="post" action="forgot_password.php">
        <?= csrf_input() ?>

        <div class="form-group">
          <label for="identifier">Email o usuario</label>
          <input
            type="text"
            class="form-control"
            id="identifier"
            name="identifier"
            autocomplete="username"
            value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>"
            required>
        </div>

        <button class="login-button" type="submit">Enviar enlace</button>
      </form>

      <!-- Pie de página -->
      <div class="login-footer" style="text-align: center; margin-top: 20px;">
        <a href="login.php" style="color: var(--color-blue); text-decoration: none; font-weight: 500;">
          Volver al login
        </a>
      </div>

    </div>
  </div>
</body>

</html>