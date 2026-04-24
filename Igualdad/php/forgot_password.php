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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    $error = 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.';
  } else {
    $identifier = trim((string)($_POST['identifier'] ?? ''));

    if ($identifier === '') {
      $error = 'Introduce tu email o nombre de usuario.';
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
          $token = bin2hex(random_bytes(32));
          $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

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