<?php
declare(strict_types=1);

// SEGURIDAD / AUTORIZACIÓN
require __DIR__ . '/../php/auth.php';
require_role('CLIENTE');

// CONFIGURACIÓN / BD
require __DIR__ . '/../config/config.php';

// Helper para escapar HTML en la vista
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// DATOS DEL CLIENTE 
$clienteId = (int)($_SESSION['user']['id_usuario'] ?? 0);
if ($clienteId <= 0) {
  // Si por algún motivo no hay id, obligamos a reloguear
  header('Location: /Igualdad/php/logout.php');
  exit;
}

// CARGAR DATOS REALES DEL USUARIO DESDE BD

$clienteUsername = '';
$clienteEmail = '';

$stmt = db()->prepare("SELECT nombre_usuario, email FROM usuario WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param('i', $clienteId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$clienteUsername = (string)($row['nombre_usuario'] ?? 'cliente');
$clienteEmail    = (string)($row['email'] ?? '');

// Mantener sesion inciada con la bd para la cabecera
$_SESSION['user']['nombre_usuario'] = $clienteUsername;
$_SESSION['user']['email']    = $clienteEmail;

// SISTEMA DE VISTAS

$view = $_GET['view'] ?? 'menu';
$allowed = ['menu', 'perfil', 'upload', 'archivos'];
if (!in_array($view, $allowed, true)) $view = 'menu';

// CARGA DE ARCHIVOS DEL CLIENTE

$archivos = [];
if ($view === 'archivos') {
  $stmt = db()->prepare("
    SELECT id, nombre_original, subido_en
    FROM archivo
    WHERE usuario_id = ?
    ORDER BY subido_en DESC
    LIMIT 200
  ");
  $stmt->bind_param('i', $clienteId);
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc()) {
    $archivos[] = $row;
  }
  $stmt->close();
}

// ENVIAR A OTRA VISTA

require __DIR__ . '/../html/cliente.html.php';