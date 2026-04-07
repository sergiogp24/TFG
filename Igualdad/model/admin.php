<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Views SOLO de ADMIN (usuarios/perfil)
$view = (string)($_GET['view'] ?? 'ver_usuarios');
$allowed = ['menu', 'add', 'edit', 'delete', 'perfil', 'ver_usuarios'];
if (!in_array($view, $allowed, true)) $view = 'ver_usuarios';

// Datos de sesión
$adminUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'admin');
$adminId = (int)($_SESSION['user']['id_usuario'] ?? 0);

// Email admin
$adminEmail = '';
if ($adminId > 0) {
  $stmt = db()->prepare("SELECT email FROM usuario WHERE id_usuario = ? LIMIT 1");
  $stmt->bind_param('i', $adminId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $adminEmail = (string)($row['email'] ?? '');
}

// Roles
$roles = [];
$res = db()->query("SELECT id, nombre FROM rol ORDER BY nombre");
while ($r = $res->fetch_assoc()) $roles[] = $r;

/* =========================================================
 * USUARIOS: búsqueda + paginación (10 por página)
 * ========================================================= */
$searchQ = trim((string)($_GET['q'] ?? ''));
$currentPage = (int)($_GET['page'] ?? 1);
if ($currentPage < 1) $currentPage = 1;

$perPage = 10;
$offset = ($currentPage - 1) * $perPage;

// WHERE dinámico para búsqueda
$where = '';
$params = [];
$types = '';

if ($searchQ !== '') {
  $where = "WHERE (
    u.nombre_usuario LIKE CONCAT('%', ?, '%')
    OR u.apellidos LIKE CONCAT('%', ?, '%')
    OR u.email LIKE CONCAT('%', ?, '%')
    OR r.nombre LIKE CONCAT('%', ?, '%')
    OR e.razon_social LIKE CONCAT('%', ?, '%')
  )";
  $params = [$searchQ, $searchQ, $searchQ, $searchQ, $searchQ];
  $types = 'sssss';
}

// Total usuarios (para saber páginas)
$sqlTotal = "
  SELECT COUNT(DISTINCT u.id_usuario) AS total
  FROM usuario u
  JOIN rol r ON r.id = u.rol_id
  LEFT JOIN usuario_empresa ue ON ue.id_usuario = u.id_usuario
  LEFT JOIN empresa e ON e.id_empresa = ue.id_empresa
  $where
";
$stmt = db()->prepare($sqlTotal);
if ($searchQ !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalUsuarios = (int)($totalRow['total'] ?? 0);
$totalPages = (int)ceil($totalUsuarios / $perPage);
if ($totalPages < 1) $totalPages = 1;

// Ajuste de página si se pasa
if ($currentPage > $totalPages) {
  $currentPage = $totalPages;
  $offset = ($currentPage - 1) * $perPage;
}

// Data: solo 10 usuarios de la página actual
$usuarios = [];
$sqlData = "
  SELECT
    u.id_usuario,
    u.nombre_usuario,
    u.apellidos,
    u.email,
    u.telefono,
    u.direccion,
    u.localidad,
    u.rol_id,
    r.nombre AS rol,
    COALESCE(GROUP_CONCAT(DISTINCT e.razon_social ORDER BY e.razon_social SEPARATOR ', '), '') AS razon_social
  FROM usuario u
  JOIN rol r ON r.id = u.rol_id
  LEFT JOIN usuario_empresa ue ON ue.id_usuario = u.id_usuario
  LEFT JOIN empresa e ON e.id_empresa = ue.id_empresa
  $where
  GROUP BY
    u.id_usuario, u.nombre_usuario, u.apellidos, u.email, u.telefono, u.direccion, u.localidad, u.rol_id, r.nombre
  ORDER BY u.nombre_usuario
  LIMIT ? OFFSET ?
";

$stmt = db()->prepare($sqlData);

if ($searchQ !== '') {
  $types2 = $types . 'ii';                // sssssii
  $params2 = array_merge($params, [$perPage, $offset]);
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param('ii', $perPage, $offset);
}

$stmt->execute();
$res = $stmt->get_result();
while ($u = $res->fetch_assoc()) $usuarios[] = $u;
$stmt->close();

/* =========================================================
 * FLASH Add user
 * ========================================================= */
$addOld = $_SESSION['add_user_old'] ?? [];
$addError = (string)($_SESSION['add_user_error'] ?? '');
unset($_SESSION['add_user_old'], $_SESSION['add_user_error']);


// =========================
// PERFIL (Área Privada)
// =========================
$adminPerfil = null;

if ($view === 'perfil' && $adminId > 0) {
  $stmt = db()->prepare("
    SELECT
      id_usuario,
      nombre_usuario,
      apellidos,
      email,
      telefono,
      direccion,
      localidad
    FROM usuario
    WHERE id_usuario = ?
    LIMIT 1
  ");
  $stmt->bind_param('i', $adminId);
  $stmt->execute();
  $adminPerfil = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

require __DIR__ . '/../html/admin.html.php';