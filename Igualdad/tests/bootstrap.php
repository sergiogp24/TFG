<?php
/**
 * Bootstrap para tests PHPUnit
 */

// Define ruta raíz
define('ROOT_PATH', dirname(__DIR__));

// Cargar autoloader de Composer
require ROOT_PATH . '/vendor/autoload.php';

// Cargar configuración
require ROOT_PATH . '/config/config.php';

// Funciones helper (si no están disponibles en prod)
if (!function_exists('h')) {
    function h(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

// Configurar aplicación para tests
$_ENV['APP_ENV'] = 'testing';
