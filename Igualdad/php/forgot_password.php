<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/../config/config.php';

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';
$step = (string)($_GET['step'] ?? 'request');

// Si ya está logueado, redirige
if (isset($_SESSION['user'])) {
    $rol = (string)($_SESSION['user']['rol'] ?? '');
    if ($rol === 'ADMINISTRADOR') {
        header('Location: /Proyecto/model/admin.php');
        exit;
    }
    if ($rol === 'TECNICO') {
        header('Location: /Proyecto/model/tecnico.php');
        exit;
    }
    if ($rol === 'CLIENTE') {
        header('Location: /Proyecto/model/cliente.php');
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request') {
        $username = trim((string)($_POST['username'] ?? ''));

        if ($username === '') {
            $error = 'El usuario es obligatorio.';
        } else {
            $db = db();

            // Buscar usuario y email asociado
            $stmt = $db->prepare("SELECT id, email FROM usuario WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Mensaje genérico (seguridad)
            if (!$user || empty($user['email'])) {
                $success = 'Si el usuario existe y tiene email, recibirás un correo con instrucciones.';
            } else {
                $userId  = (int)$user['id'];
                $toEmail = (string)$user['email'];

                // Generar token y expiración
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora

                $stmt = $db->prepare("UPDATE usuario SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $stmt->bind_param('ssi', $token, $expires, $userId);
                $stmt->execute();
                $stmt->close();

                $resetLink = "http://localhost/Proyecto/php/reset_password.php?token=" . urlencode($token);

                // Envío con PHPMailer + Mailtrap 

                $sentOk = false;
                $mailError = '';

                try {
                    $mailCfg = require __DIR__ . '/../config/mail.php';

                    require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
                    require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
                    require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                    // Debug (si quieres ver el log en pantalla, deja 2; si no, pon 0)
                    // $mail->SMTPDebug = 2;
                    // $mail->Debugoutput = 'html';

                    $mail->isSMTP(); // IMPORTANTE: fuerza SMTP (si no, intenta mail())
                    $mail->Host = (string)$mailCfg['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = (string)$mailCfg['username'];
                    $mail->Password = (string)$mailCfg['password'];
                    $mail->Port = (int)$mailCfg['port'];
                    $mail->SMTPSecure = false;
                    $mail->SMTPAutoTLS = false;

                    // Mailtrap con 2525: lo más estable es sin forzar TLS.
                    // Si en tu panel de Mailtrap te indica STARTTLS, cambia esto a ENCRYPTION_STARTTLS.
                    $secure = (string)($mailCfg['secure'] ?? 'none');
                    if ($secure === 'ssl') {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    } elseif ($secure === 'tls') {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    } else {
                        $mail->SMTPSecure = false;
                        $mail->SMTPAutoTLS = false;
                    }

                    $mail->CharSet = 'UTF-8';

                    $fromEmail = (string)($mailCfg['from_email'] ?? 'no-reply@proyecto.local');
                    $fromName  = (string)($mailCfg['from_name'] ?? 'Proyecto');

                    $mail->setFrom($fromEmail, $fromName);
                    $mail->addAddress($toEmail);

                    $mail->isHTML(true);
                    $mail->Subject = 'Recuperación de contraseña';
                    $mail->Body = '
                        <p>Hola,</p>
                        <p>Has solicitado recuperar tu contraseña.</p>
                        <p>Haz clic aquí para restablecerla (caduca en 1 hora):</p>
                        <p><a href="' . h($resetLink) . '">' . h($resetLink) . '</a></p>
                        <p>Si no lo solicitaste, ignora este correo.</p>
                    ';
                    $mail->AltBody =
                        "Hola,\n\n" .
                        "Has solicitado recuperar tu contraseña.\n\n" .
                        "Abre este enlace (caduca en 1 hora):\n$resetLink\n\n" .
                        "Si no lo solicitaste, ignora este correo.\n";

                    $mail->send();
                    $sentOk = true;
                } catch (Throwable $e) {
                    $mailError = $e->getMessage();
                }

                if ($sentOk) {
                    $success = 'Te hemos enviado un email con el enlace de recuperación (revisa Mailtrap).';
                } else {
                    $success = "No se pudo enviar el email: $mailError. Enlace para pruebas: $resetLink";
                }
            }
        }
    }
}

require __DIR__ . '/../html/forgot_password.html.php';
