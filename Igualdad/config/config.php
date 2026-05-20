<?php
/* Configuracion de la conexion con la base de datos */
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Environment-based error reporting
$appEnv = getenv('APP_ENV') ?: 'development';
if ($appEnv === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Load .env file if it exists (simple implementation)
function _load_env_file(): void {
  $envFile = __DIR__ . '/../.env';
  if (!file_exists($envFile)) {
    return;
  }
  
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!is_array($lines)) {
    return;
  }
  
  foreach ($lines as $line) {
    $line = trim($line);
    // Skip comments
    if (str_starts_with($line, '#')) {
      continue;
    }
    
    if (str_contains($line, '=')) {
      [$key, $value] = explode('=', $line, 2);
      $key = trim($key);
      $value = trim($value);
      // Remove quotes if present
      if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
          (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
        $value = substr($value, 1, -1);
      }
      if ($key !== '' && getenv($key) === false) {
        putenv($key . '=' . $value);
      }
    }
  }
}

_load_env_file();

// Get configuration from environment or use defaults
function _get_env_config(string $key, string $default): string {
    $val = getenv($key);
    return ($val !== false) ? $val : $default;
}

define('DB_HOST', _get_env_config('DB_HOST', ''));
define('DB_PORT', (int)_get_env_config('DB_PORT', ''));
define('DB_USER', _get_env_config('DB_USER', ''));
define('DB_PASS', _get_env_config('DB_PASS', ''));
define('DB_NAME', _get_env_config('DB_NAME', ''));
define('GEMINI_API_KEY', _get_env_config('GEMINI_API_KEY', ''));

// Helper function to get env vars with defaults
function env(string $key, string $default = ''): string {
  $value = getenv($key);
  return (is_string($value) && $value !== '') ? $value : $default;
}

function db(): mysqli {
  static $conn = null;
  if ($conn instanceof mysqli) return $conn;

  try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
  } catch (mysqli_sql_exception $e) {
    $message = 'No se pudo conectar a la base de datos. Revisa DB_HOST, DB_PORT, DB_USER, DB_PASS y DB_NAME en el entorno.';
    if ((getenv('APP_ENV') ?: 'development') !== 'production') {
      $message .= ' Detalle: ' . $e->getMessage();
    }
    throw new RuntimeException($message, 0, $e);
  }

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