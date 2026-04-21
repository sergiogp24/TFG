<?php
/* Configuracion de la conexion con la base de datos */
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const DB_HOST = '127.0.0.1';
const DB_USER = 'nombre_usuario';
const DB_PASS = 'contraseña'; 
const DB_NAME = 'nombre_base_de_datos';

function db(): mysqli {
  static $conn = null;
  if ($conn instanceof mysqli) return $conn;

  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $conn->set_charset('utf8mb4');
  return $conn;
}

function app_base_url(): string {
  $configured = getenv('APP_BASE_URL');
  if (is_string($configured) && $configured !== '') {
    return rtrim($configured, '/');
  }

  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
  $scheme = $https ? 'https' : 'http';
  $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
  if ($host === '') {
    $host = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
  }
  if ($host === '') {
    $host = 'localhost';
  }
  $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  $basePath = rtrim(dirname(dirname($scriptName)), '/');

  if ($basePath === '.' || $basePath === '/' || $basePath === '\\') {
    $basePath = '';
  }

  return rtrim($scheme . '://' . $host . $basePath, '/');
}

function app_base_path(): string {
  $configured = getenv('APP_BASE_PATH');
  if (is_string($configured) && $configured !== '') {
    $path = '/' . trim($configured, '/');
    return $path === '/' ? '' : $path;
  }

  $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  $basePath = rtrim(dirname(dirname($scriptName)), '/');

  if ($basePath === '.' || $basePath === '/' || $basePath === '\\') {
    return '';
  }

  return $basePath;
}

function app_path(string $path = '/'): string {
  $normalized = '/' . ltrim($path, '/');
  $base = app_base_path();
  return ($base === '' ? '' : $base) . $normalized;
}