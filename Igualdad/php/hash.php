<?php
// Ejecutar hasheo de las contaseñas de la base de datos.
declare(strict_types=1);

$pwds = [
  'admin123',
  'tec123',
  'cli123',
];

foreach ($pwds as $p) {
  echo $p . " => " . password_hash($p, PASSWORD_DEFAULT) . PHP_EOL;
}
