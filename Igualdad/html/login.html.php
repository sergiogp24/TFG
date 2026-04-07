<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Iniciar sesión</title>
  <!--CSS PERSONALIZADO -->
  <link rel="stylesheet" href="../css/login.css">
  <!--  BOOTSTRAP  -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>
  <div class="container py-4 py-lg-5">
    <div class="row justify-content-center align-items-center g-4">
      <div class="col-12 col-lg-7">
        <div class="side-illustration">
          <!-- Imagen de igualdad  -->
          <img src="../assets/igualdad.png" alt="Igualdad de género">
        </div>
      </div>
      <div class="col-12 col-lg-5">
        <div class="glass p-4 p-sm-4">

          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="brand-badge">CI</div>
            <div>
              <h4 class="mb-0">Iniciar sesión</h4>
              <div class="text-muted small">Accede a tu panel</div>
            </div>
          </div>

          <!--MENSAJE DE ERROR -->
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 mb-3">
              <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <!--MENSAJE DE ÉXITO -->
          <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 mb-3">
              <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <!--FORMULARIO DE LOGIN-->
          <form method="post" action="login.php" class="vstack gap-3">

            <!--  usuario -->
            <div>
              <label class="form-label">Usuario</label>
              <input class="form-control" name="nombre_usuario" autocomplete="username" required>
            </div>

            <!--  contraseña -->
            <div>
              <label class="form-label">Contraseña</label>
              <input class="form-control" name="password" type="password" autocomplete="current-password" required>
            </div>

            <!-- Botón de submit -->
            <button class="btn btn-brand w-100 py-2" type="submit">Entrar</button>

           
            <div class="text-center small text-muted">
              © <?= date('Y') ?> Consultoría Igualdad
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>