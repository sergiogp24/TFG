<?php
declare(strict_types=1);

// Load environment variables
require_once __DIR__ . '/config.php';

return [
  'host' => env('MAIL_HOST', ''),
  'port' => (int)env('MAIL_PORT', ''),
  'secure' => env('MAIL_SECURE', ''),
  'username' => env('MAIL_USERNAME', ''),
  'password' => env('MAIL_PASSWORD', ''),
  'from_email' => env('MAIL_FROM', ''),
  'from_name' => env('MAIL_FROM_NAME', ''),
];