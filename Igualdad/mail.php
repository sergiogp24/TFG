<?php
declare(strict_types=1);

// Load environment variables
require_once __DIR__ . '/config.php';

return [
  'host' => env('MAIL_HOST', 'smtp.ionos.es'),
  'port' => (int)env('MAIL_PORT', '465'),
  'secure' => env('MAIL_SECURE', 'ssl'),
  'username' => env('MAIL_USERNAME', ''),
  'password' => env('MAIL_PASSWORD', ''),
  'from_email' => env('MAIL_FROM', 'noreply@example.com'),
  'from_name' => env('MAIL_FROM_NAME', 'Myequality'),
];