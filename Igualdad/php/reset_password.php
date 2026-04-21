<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/password_reset_tokens.php';
require_once __DIR__ . '/mails.php';
header('Content-Type: text/html; charset=UTF-8');

// Obtener parámetros del GET/POST
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$method = $_SERVER['REQUEST_METHOD'] ?? '';

$errorMsg = '';
$successMsg = '';
$tokenValid = false;
$userEmail = '';
$userName = '';

// Validar token (GET o POST)
if ($token === '') {
    $errorMsg = 'Token inválido o faltante';
    error_log('Reset token vacío en reset_password.php');
} else {
    try {
        $tokenData = find_password_reset_token($token);

        if ($tokenData === null) {
            $errorMsg = 'Token no encontrado o inválido';
            error_log('Token no encontrado en BD: ' . $token);
        } else {
            $tokenStatus = get_password_reset_token_status($tokenData);

            if ($tokenStatus === 'used') {
                $errorMsg = 'Este enlace ya ha sido utilizado. Solicita uno nuevo.';
            } elseif ($tokenStatus === 'expired') {
                $errorMsg = 'El enlace ha expirado. Solicita un nuevo enlace para establecer tu contraseña.';
                $emailToken = (string)($tokenData['email'] ?? '');

                if ($emailToken !== '') {
                    try {
                        correo_enviar_recordatorio_rr_pendiente_por_token_expirado(db(), $emailToken);
                    } catch (Throwable $e) {
                        error_log('Error enviando recordatorio por token expirado: ' . $e->getMessage());
                    }
                }
            } elseif ($tokenStatus === 'valid') {
                $tokenValid = true;
                $userEmail = (string)($tokenData['email'] ?? '');

                // Obtener nombre de usuario desde la BD
                $stmtUser = db()->prepare("SELECT nombre_usuario FROM usuario WHERE email = ? LIMIT 1");
                $stmtUser->bind_param('s', $userEmail);
                $stmtUser->execute();
                $resultUser = $stmtUser->get_result();
                if ($resultUser->num_rows > 0) {
                    $user = $resultUser->fetch_assoc();
                    $userName = (string)($user['nombre_usuario'] ?? '');
                }
                $stmtUser->close();
            } else {
                $errorMsg = 'Token no encontrado o inválido';
            }
        }
    } catch (Throwable $e) {
        $errorMsg = 'Error al procesar tu solicitud. Contacta al administrador.';
        error_log('Error validando token de reset: ' . $e->getMessage());
    }
}

// Si es POST y el token es válido, procesamos el establecimiento de contraseña
if ($method === 'POST' && $tokenValid) {
    if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
        $errorMsg = 'La sesión ha expirado. Recarga la página e inténtalo de nuevo.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        // Validar contraseña
        if ($password === '') {
            $errorMsg = 'La contraseña no puede estar vacía';
        } elseif (strlen($password) < 6) {
            $errorMsg = 'La contraseña debe tener al menos 6 caracteres';
        } elseif ($password !== $passwordConfirm) {
            $errorMsg = 'Las contraseñas no coinciden';
        } else {
            // Verificar que el usuario existe (por si acaso)
            $stmtUser = db()->prepare("SELECT id_usuario FROM usuario WHERE email = ? LIMIT 1");
            $stmtUser->bind_param('s', $userEmail);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();

            if ($resultUser->num_rows === 0) {
                $errorMsg = 'El email no está registrado en el sistema';
            } else {
                $user = $resultUser->fetch_assoc();
                $userId = (int)($user['id_usuario'] ?? 0);

                // Hash de la contraseña y actualización del usuario
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmtUpdate = db()->prepare("UPDATE usuario SET password = ? WHERE id_usuario = ?");
                $stmtUpdate->bind_param('si', $passwordHash, $userId);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // Marcar el token como usado
                mark_password_reset_token_as_used($token);

                $successMsg = 'Contraseña establecida correctamente. Puedes acceder a la plataforma con tu usuario y contraseña.';
                $tokenValid = false; // Ocultar el formulario después del éxito
            }
            $stmtUser->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Establecer Contraseña</title>
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
                    <h1 class="login-title">Establecer Contraseña</h1>
                    <p class="login-subtitle">Consultoría Igualdad</p>
                </div>
            </div>

            <!-- MENSAJE DE ERROR -->
            <?php if ($errorMsg): ?>
                <div class="login-error"><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <!-- MENSAJE DE ÉXITO -->
            <?php if ($successMsg): ?>
                <div class="login-success"><?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="login-footer" style="text-align: center; margin-top: 20px;">
                    <a href="<?= h(app_path('/php/login.php')) ?>" style="color: var(--color-blue); text-decoration: none; font-weight: 500;">
                        Ir al inicio de sesión
                    </a>
                </div>
            <?php endif; ?>

            <!-- FORMULARIO (solo si el token es válido y no hay error) -->
            <?php if ($tokenValid && !$successMsg): ?>
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            class="form-control" 
                            value="<?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?>" 
                            readonly
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Usuario</label>
                        <input 
                            type="text" 
                            id="username" 
                            class="form-control" 
                            value="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>" 
                            readonly
                        >
                    </div>
                    
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
                                placeholder="Mínimo 6 caracteres"
                                style="padding-right: 44px;"
                            >
                            <button 
                                type="button" 
                                id="toggle-password" 
                                aria-label="Mostrar u ocultar contraseña"
                                aria-pressed="false"
                                style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 1px solid #d0d7de; background: #fff; cursor: pointer; font-size: 12px; line-height: 1; padding: 6px 10px; border-radius: 6px;"
                            >
                                Mostrar
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirmar Contraseña</label>
                        <div style="position: relative;">
                            <input 
                                type="password" 
                                id="password_confirm" 
                                name="password_confirm" 
                                class="form-control" 
                                required 
                                minlength="6" 
                                placeholder="Repite tu contraseña"
                                style="padding-right: 44px;"
                            >
                            <button 
                                type="button" 
                                id="toggle-password-confirm" 
                                aria-label="Mostrar u ocultar confirmación de contraseña"
                                aria-pressed="false"
                                style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 1px solid #d0d7de; background: #fff; cursor: pointer; font-size: 12px; line-height: 1; padding: 6px 10px; border-radius: 6px;"
                            >
                                Mostrar
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="login-button">Establecer Contraseña</button>
                </form>
                <script>
                    (function () {
                        function setupToggle(buttonId, inputId) {
                            var button = document.getElementById(buttonId);
                            var input = document.getElementById(inputId);

                            if (!button || !input) {
                                return;
                            }

                            button.addEventListener('click', function () {
                                var isPassword = input.type === 'password';
                                input.type = isPassword ? 'text' : 'password';
                                button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                                button.textContent = isPassword ? 'Ocultar' : 'Mostrar';
                            });
                        }

                        setupToggle('toggle-password', 'password');
                        setupToggle('toggle-password-confirm', 'password_confirm');
                    })();
                </script>
            <?php elseif (!$successMsg): ?>
                <div class="login-footer" style="text-align: center; margin-top: 30px;">
                    <p style="color: var(--color-gray-600); margin-bottom: 15px;">
                        Si crees que esto es un error, contacta con el administrador.
                    </p>
                    <a href="<?= h(app_path('/php/login.php')) ?>" style="color: var(--color-blue); text-decoration: none; font-weight: 500;">
                        Ir al inicio de sesión
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>