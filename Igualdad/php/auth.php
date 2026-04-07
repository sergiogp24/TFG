<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
const SESSION_TIMEOUT = 28800;  

//Comprueba si existe el user, si no existe redirige al login 

function require_login(): void {
  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }
}
//  Comprobar inactividad
  if (isset($_SESSION['last_activity'])) {
    $inactive = time() - $_SESSION['last_activity'];

    if ($inactive > SESSION_TIMEOUT) {
      session_unset();
      session_destroy();

      header('Location: /Igualdad/php/login.php ');
      exit;
    }
  }

  //  Actualizar última actividad
  $_SESSION['last_activity'] = time();


//Lee el rol actual de la sesion y si el rol actual no coincide, devuleve 403 y corta ejecucion

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