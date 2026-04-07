<?php
declare(strict_types=1);
// Iniciar sesión para poder limpiarla
session_start();
//  Vaciar todas las variables de sesión
$_SESSION = [];
//  Destruir la sesión del lado del servidor
session_destroy();
//  Redirigir al login
header('Location: login.php');
exit;