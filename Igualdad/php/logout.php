<?php
declare(strict_types=1);

session_start();
$_SESSION = [];

// Eliminar la cookie de sesión del navegador
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Rotar el ID antes de destruir para prevenir session fixation
session_regenerate_id(true);
session_destroy();

header('Location: login.php');
exit;