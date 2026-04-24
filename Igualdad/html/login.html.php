<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Iniciar sesión</title>
  <!--CSS PERSONALIZADO -->
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
          <h1 class="login-title">Iniciar sesión</h1>
          <p class="login-subtitle">Accede a tu panel</p>
        </div>
      </div>

      <!-- MENSAJE DE ERROR -->
      <?php if (!empty($error)): ?>
        <div class="login-error">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <!-- MENSAJE DE ÉXITO -->
      <?php if (!empty($success)): ?>
        <div class="login-success">
          <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <!-- FORMULARIO DE LOGIN -->
      <form method="post" action="login.php">
        <?= csrf_input() ?>

        <!-- Usuario -->
        <div class="form-group">
          <label for="nombre_usuario">Usuario</label>
          <input
            type="text"
            class="form-control"
            id="nombre_usuario"
            name="nombre_usuario"
            autocomplete="username"
            required>
        </div>

        <!-- Contraseña -->

        <div class="form-group">
          <label for="password">Contraseña</label>
          <div style="position: relative;">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              required
              minlength="6"
              style="padding-right: 44px;">
            <button
              type="button"
              id="toggle-password"
              aria-label="Mostrar u ocultar contraseña"
              aria-pressed="false"
              style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 1px solid #d0d7de; background: #fff; cursor: pointer; font-size: 12px; line-height: 1; padding: 6px 10px; border-radius: 6px;">
              Mostrar
            </button>
          </div>
        </div>

        <!-- Enlace de recuperación -->
        <div style="text-align: right; margin-bottom: 24px;">
          <a href="forgot_password.php" style="color: var(--color-blue); text-decoration: none; font-weight: 500; font-size: 0.9rem;">
            ¿Has olvidado tu contraseña?
          </a>
        </div>

        <!-- Botón de envío -->
        <button type="submit" class="login-button">Entrar</button>
      </form>

      <!-- Pie de página -->
      <div class="login-footer">
        © <?= date('Y') ?> Consultoría Igualdad
      </div>

    </div>
  </div>

  <script>
    (function() {
      const passwordInput = document.getElementById('password');
      const toggleButton = document.getElementById('toggle-password');

      if (!passwordInput || !toggleButton) {
        return;
      }

      toggleButton.addEventListener('click', function() {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        toggleButton.textContent = isHidden ? 'Ocultar' : 'Mostrar';
        toggleButton.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
      });
    })();
  </script>
</body>

</html>