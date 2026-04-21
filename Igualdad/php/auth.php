<?php
declare(strict_types=1);

function is_https_request(): bool
{
  if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
    return true;
  }

  if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
    return true;
  }

  $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
  return $forwardedProto === 'https';
}

function is_local_request(): bool
{
  $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
  $host = preg_replace('/:\\d+$/', '', $host) ?? $host;
  return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  $secureCookie = is_https_request() && !is_local_request();
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}
const SESSION_TIMEOUT = 86400;

// Comprueba si existe user; si no, redirige al login.

function require_login(): void {
  if (!isset($_SESSION['user'])) {
    header('Location: ../php/login.php');
    exit;
  }
}

// Comprobar inactividad.
if (isset($_SESSION['last_activity'])) {
  $inactive = time() - $_SESSION['last_activity'];

  if ($inactive > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();

    header('Location: ../php/login.php');
    exit;
  }
}

// Actualizar última actividad.
$_SESSION['last_activity'] = time();


// Lee el rol actual de la sesión y si no coincide devuelve 403.

function require_role(string $role): void {
  require_login();
  // Leer rol del usuario desde la sesión
  $current = (string)($_SESSION['user']['rol'] ?? '');
  // Validar rol
  if ($current !== $role) {
    http_response_code(403);
    exit('Acceso denegado');
  }
}

function csrf_token(): string
{
  if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
  }

  return $_SESSION['_csrf_token'];
}

function csrf_input(): string
{
  $token = csrf_token();
  $escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
  return '<input type="hidden" name="_csrf_token" value="' . $escaped . '">';
}

function csrf_validate(?string $token): bool
{
  $sessionToken = (string)($_SESSION['_csrf_token'] ?? '');
  if ($sessionToken === '' || $token === null || $token === '') {
    return false;
  }

  return hash_equals($sessionToken, $token);
}