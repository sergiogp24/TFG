<?php
declare(strict_types=1);

/**
 * Modelo: Cliente (área privada)
 * --------------------------------
 * Carga los datos mínimos necesarios para que un usuario con rol CLIENTE
 * pueda ver su panel: nombre, email, subidas y vistas relacionadas.
 * Variables expuestas a la vista `html/index_cliente.php`:
 *  - $clienteUsername, $clienteEmail: datos del usuario
 *  - $view: sub-vista a renderizar ('menu','perfil','upload','archivos')
 *  - $archivos: listado de archivos cuando $view === 'archivos'
 */

// SEGURIDAD / AUTORIZACIÓN
require __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/helpers.php';
require_role('CLIENTE');

// CONFIGURACIÓN / BD
require __DIR__ . '/../config/config.php';

// DATOS DEL CLIENTE 
$clienteId = (int)($_SESSION['user']['id_usuario'] ?? 0);
if ($clienteId <= 0) {
  // Si por algún motivo no hay id, obligamos a reloguear
  header('Location: ../php/logout.php');
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

$view = (string)($_GET['view'] ?? 'menu');
$allowed = ['menu', 'perfil', 'upload', 'archivos'];
if (!in_array($view, $allowed, true)) $view = 'menu';

// CARGA DE ARCHIVOS DEL CLIENTE

$archivos = [];
if ($view === 'archivos') {
  // Obtenemos los últimos archivos subidos por este cliente. La consulta
  // devuelve los campos que la vista necesita para listar descargas/archivos.
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

require __DIR__ . '/../html/index_cliente.php';