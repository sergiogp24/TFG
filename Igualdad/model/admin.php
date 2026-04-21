<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/helpers.php';
require_once __DIR__ . '/../php/mails.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

function enviarRecordatoriosRRReunionesVencidas(mysqli $db): void
{
  correo_enviar_recordatorio_rr_reuniones_vencidas($db);
}

// Views SOLO de ADMIN (usuarios/perfil/reuniones)
$view = (string)($_GET['view'] ?? 'ver_usuarios');
$allowed = ['menu', 'add', 'edit', 'delete', 'privada', 'perfil', 'reuniones', 'ver_usuarios', 'seguimiento_tecnicos'];
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

// Empresas (para asignar a usuarios)
$empresas = [];
$resEmpresas = db()->query("SELECT id_empresa, razon_social FROM empresa ORDER BY razon_social");
if ($resEmpresas) {
  while ($e = $resEmpresas->fetch_assoc()) $empresas[] = $e;
}

// Tarjetas del panel admin (vista menu)
$totalClientes = 0;
$totalTecnicos = 0;
$totalPlanesIgualdad = 0;
$totalMantenimientos = 0;

$resClientes = db()->query("\n  SELECT COUNT(*) AS total\n  FROM usuario u\n  INNER JOIN rol r ON r.id = u.rol_id\n  WHERE UPPER(r.nombre) = 'CLIENTE'\n");
if ($resClientes) {
  $row = $resClientes->fetch_assoc();
  $totalClientes = (int)($row['total'] ?? 0);
  $resClientes->close();
}

$resTecnicos = db()->query("\n  SELECT COUNT(*) AS total\n  FROM usuario u\n  INNER JOIN rol r ON r.id = u.rol_id\n  WHERE UPPER(r.nombre) LIKE 'TECNICO%'
");
if ($resTecnicos) {
  $row = $resTecnicos->fetch_assoc();
  $totalTecnicos = (int)($row['total'] ?? 0);
  $resTecnicos->close();
}

$resPlanesIgualdad = db()->query("\n  SELECT COUNT(DISTINCT c.id_empresa) AS total\n  FROM contrato_empresa c\n  WHERE UPPER(TRIM(c.tipo_contrato)) = 'PLAN IGUALDAD'\n");
if ($resPlanesIgualdad) {
  $row = $resPlanesIgualdad->fetch_assoc();
  $totalPlanesIgualdad = (int)($row['total'] ?? 0);
  $resPlanesIgualdad->close();
}

$resMantenimientos = db()->query("\n  SELECT COUNT(DISTINCT c.id_empresa) AS total\n  FROM contrato_empresa c\n  WHERE UPPER(TRIM(c.tipo_contrato)) LIKE 'MANTENIMIENTO%'\n");
if ($resMantenimientos) {
  $row = $resMantenimientos->fetch_assoc();
  $totalMantenimientos = (int)($row['total'] ?? 0);
  $resMantenimientos->close();
}

$adminOperationalSummary = [];
$tablaDescargaExiste = false;
$resTablaDescarga = db()->query("SHOW TABLES LIKE 'archivo_descarga_log'");
if ($resTablaDescarga instanceof mysqli_result && $resTablaDescarga->num_rows > 0) {
  $tablaDescargaExiste = true;
}
if ($resTablaDescarga instanceof mysqli_result) {
  $resTablaDescarga->close();
}

$sqlDescargaWordTecnico = $tablaDescargaExiste
  ? "EXISTS (\n      SELECT 1\n      FROM archivo_descarga_log dl\n      INNER JOIN usuario u3 ON u3.id_usuario = dl.id_usuario\n      INNER JOIN rol r3 ON r3.id = u3.rol_id\n      WHERE dl.id_empresa = e.id_empresa\n        AND UPPER(TRIM(dl.tipo_descarga)) = 'WORD_GENERADO'\n        AND UPPER(r3.nombre) LIKE 'TECNICO%'\n      LIMIT 1\n    )"
  : "0";

$resOperational = db()->query("\n  SELECT\n    e.id_empresa,\n    e.razon_social,\n    COALESCE((\n      SELECT ce.tipo_contrato\n      FROM contrato_empresa ce\n      WHERE ce.id_empresa = e.id_empresa\n      ORDER BY ce.id_contrato_empresa DESC\n      LIMIT 1\n    ), 'SIN CONTRATO') AS tipo_contrato,\n    COALESCE((\n      SELECT u.nombre_usuario\n      FROM usuario_empresa ue\n      INNER JOIN usuario u ON u.id_usuario = ue.id_usuario\n      INNER JOIN rol r ON r.id = u.rol_id\n      WHERE ue.id_empresa = e.id_empresa\n        AND UPPER(r.nombre) LIKE 'TECNICO%'\n      ORDER BY ue.id_usuario ASC\n      LIMIT 1\n    ), 'Sin tecnico asignado') AS tecnico_nombre,\n    EXISTS (\n      SELECT 1 FROM archivos a1\n      WHERE a1.id_empresa = e.id_empresa\n        AND UPPER(TRIM(a1.tipo)) = 'REGISTRO_RETRIBUTIVO'\n      LIMIT 1\n    ) AS tiene_registro,\n    EXISTS (\n      SELECT 1 FROM archivos a2\n      WHERE a2.id_empresa = e.id_empresa\n        AND UPPER(TRIM(a2.tipo)) = 'TOMA DE DATOS'\n      LIMIT 1\n    ) AS tiene_toma_datos,\n    EXISTS (\n      SELECT 1 FROM archivos a3\n      WHERE a3.id_empresa = e.id_empresa\n        AND UPPER(TRIM(COALESCE(a3.asunto, ''))) = 'GENERADO WORD'\n      LIMIT 1\n    ) AS tiene_word,\n    EXISTS (\n      SELECT 1 FROM archivos a4\n      WHERE a4.id_empresa = e.id_empresa\n        AND UPPER(TRIM(COALESCE(a4.asunto, ''))) = 'GENERADO PORCENTAJES'\n      LIMIT 1\n    ) AS tiene_porcentajes,\n    {$sqlDescargaWordTecnico} AS tiene_descarga_word_tecnico\n  FROM empresa e\n  WHERE EXISTS (\n      SELECT 1\n      FROM usuario_empresa ue2\n      INNER JOIN usuario u2 ON u2.id_usuario = ue2.id_usuario\n      INNER JOIN rol r2 ON r2.id = u2.rol_id\n      WHERE ue2.id_empresa = e.id_empresa\n        AND UPPER(r2.nombre) LIKE 'TECNICO%'\n    )\n  ORDER BY e.razon_social ASC\n");

if ($resOperational) {
  while ($op = $resOperational->fetch_assoc()) {
    $tipoContrato = strtoupper(trim((string)($op['tipo_contrato'] ?? 'SIN CONTRATO')));
    if ($tipoContrato === 'PLAN IGUALDAD') {
      $tipoContratoLabel = 'Plan de Igualdad';
    } elseif (str_starts_with($tipoContrato, 'MANTENIMIENTO')) {
      $tipoContratoLabel = 'Mantenimiento';
    } else {
      $tipoContratoLabel = trim((string)($op['tipo_contrato'] ?? 'Sin contrato'));
    }

    $tieneRegistro = ((int)($op['tiene_registro'] ?? 0) === 1);
    $tieneTomaDatos = ((int)($op['tiene_toma_datos'] ?? 0) === 1);
    $tieneWord = ((int)($op['tiene_word'] ?? 0) === 1);
    $tienePorcentajes = ((int)($op['tiene_porcentajes'] ?? 0) === 1);
    $tieneDescargaWordTecnico = ((int)($op['tiene_descarga_word_tecnico'] ?? 0) === 1);

    $estado = 'Pendiente de registro';
    $progreso = 0;

    if ($tieneRegistro) {
      $estado = 'Cliente subio el registro';
      $progreso = 25;
    }
    if ($tieneTomaDatos) {
      $estado = 'Tecnico esta trabajando';
      $progreso = 50;
    }
    if ($tieneWord || $tienePorcentajes) {
      $estado = 'Word generado pendiente de descarga tecnico';
      $progreso = 75;
    }
    if ($tieneDescargaWordTecnico) {
      $estado = 'Completado';
      $progreso = 100;
    }

    $adminOperationalSummary[] = [
      'id_empresa' => (int)($op['id_empresa'] ?? 0),
      'razon_social' => trim((string)($op['razon_social'] ?? '')),
      'plan' => $tipoContratoLabel,
      'tecnico' => trim((string)($op['tecnico_nombre'] ?? 'Sin tecnico asignado')),
      'estado' => $estado,
      'progreso' => $progreso,
    ];
  }
  $resOperational->close();
}

$seguimientoTecnicos = [];
$seguimientoTecnicoSeleccionadoId = (int)($_GET['id_tecnico'] ?? 0);
$seguimientoTecnicoSeleccionado = null;
$seguimientoTecnicoEmpresas = [];

if ($view === 'seguimiento_tecnicos') {
  $resTecnicosSeg = db()->query("\n    SELECT\n      u.id_usuario,\n      u.nombre_usuario,\n      COUNT(DISTINCT ue.id_empresa) AS total_empresas\n    FROM usuario u\n    INNER JOIN rol r ON r.id = u.rol_id\n    LEFT JOIN usuario_empresa ue ON ue.id_usuario = u.id_usuario\n    WHERE UPPER(r.nombre) LIKE 'TECNICO%'\n    GROUP BY u.id_usuario, u.nombre_usuario\n    ORDER BY u.nombre_usuario ASC\n  ");

  if ($resTecnicosSeg) {
    while ($rowTecnicoSeg = $resTecnicosSeg->fetch_assoc()) {
      $seguimientoTecnicos[] = [
        'id_usuario' => (int)($rowTecnicoSeg['id_usuario'] ?? 0),
        'nombre_usuario' => trim((string)($rowTecnicoSeg['nombre_usuario'] ?? '')),
        'total_empresas' => (int)($rowTecnicoSeg['total_empresas'] ?? 0),
      ];
    }
    $resTecnicosSeg->close();
  }

  if ($seguimientoTecnicoSeleccionadoId <= 0 && !empty($seguimientoTecnicos)) {
    $seguimientoTecnicoSeleccionadoId = (int)($seguimientoTecnicos[0]['id_usuario'] ?? 0);
  }

  foreach ($seguimientoTecnicos as $tecnicoSeg) {
    if ((int)($tecnicoSeg['id_usuario'] ?? 0) === $seguimientoTecnicoSeleccionadoId) {
      $seguimientoTecnicoSeleccionado = $tecnicoSeg;
      break;
    }
  }

  if ($seguimientoTecnicoSeleccionadoId > 0) {
    $stmtEmpresasSeg = db()->prepare("\n SELECT\n        e.id_empresa,\n        e.razon_social,\n        COALESCE((\n          SELECT ce.tipo_contrato\n          FROM contrato_empresa ce\n          WHERE ce.id_empresa = e.id_empresa\n          ORDER BY ce.id_contrato_empresa DESC\n          LIMIT 1\n        ), 'SIN CONTRATO') AS tipo_contrato,\n        EXISTS (\n          SELECT 1 FROM archivos a1\n          WHERE a1.id_empresa = e.id_empresa\n            AND UPPER(TRIM(a1.tipo)) = 'REGISTRO_RETRIBUTIVO'\n          LIMIT 1\n        ) AS tiene_registro,\n        EXISTS (\n          SELECT 1 FROM archivos a2\n          WHERE a2.id_empresa = e.id_empresa\n            AND UPPER(TRIM(a2.tipo)) = 'TOMA DE DATOS'\n          LIMIT 1\n        ) AS tiene_toma_datos,\n        EXISTS (\n          SELECT 1 FROM archivos a3\n          WHERE a3.id_empresa = e.id_empresa\n            AND UPPER(TRIM(COALESCE(a3.asunto, ''))) = 'GENERADO WORD'\n          LIMIT 1\n        ) AS tiene_word,\n        EXISTS (\n          SELECT 1 FROM archivo_descarga_log dl\n          INNER JOIN usuario u3 ON u3.id_usuario = dl.id_usuario\n          INNER JOIN rol r3 ON r3.id = u3.rol_id\n          WHERE dl.id_empresa = e.id_empresa\n            AND UPPER(TRIM(dl.tipo_descarga)) = 'WORD_GENERADO'\n            AND UPPER(r3.nombre) LIKE 'TECNICO%'\n          LIMIT 1\n        ) AS tiene_descarga_word_tecnico\n      FROM empresa e\n      INNER JOIN usuario_empresa ue ON ue.id_empresa = e.id_empresa\n      WHERE ue.id_usuario = ?\n      ORDER BY e.razon_social ASC\n    ");

    if ($stmtEmpresasSeg) {
      $stmtEmpresasSeg->bind_param('i', $seguimientoTecnicoSeleccionadoId);
      $stmtEmpresasSeg->execute();
      $resEmpresasSeg = $stmtEmpresasSeg->get_result();
      while ($rowEmpresaSeg = $resEmpresasSeg->fetch_assoc()) {
        $tipoContrato = strtoupper(trim((string)($rowEmpresaSeg['tipo_contrato'] ?? 'SIN CONTRATO')));
        if ($tipoContrato === 'PLAN IGUALDAD') {
          $tipoContratoLabel = 'Plan de Igualdad';
        } elseif (str_starts_with($tipoContrato, 'MANTENIMIENTO')) {
          $tipoContratoLabel = 'Mantenimiento';
        } else {
          $tipoContratoLabel = trim((string)($rowEmpresaSeg['tipo_contrato'] ?? 'Sin contrato'));
        }

        $tieneRegistro = ((int)($rowEmpresaSeg['tiene_registro'] ?? 0) === 1);
        $tieneTomaDatos = ((int)($rowEmpresaSeg['tiene_toma_datos'] ?? 0) === 1);
        $tieneWord = ((int)($rowEmpresaSeg['tiene_word'] ?? 0) === 1);
        $tieneDescargaWordTecnico = ((int)($rowEmpresaSeg['tiene_descarga_word_tecnico'] ?? 0) === 1);

        $estado = 'Pendiente de registro';
        $progreso = 0;
        if ($tieneRegistro) {
          $estado = 'Cliente subio el registro';
          $progreso = 25;
        }
        if ($tieneTomaDatos) {
          $estado = 'Tecnico esta trabajando';
          $progreso = 50;
        }
        if ($tieneWord) {
          $estado = 'Word generado pendiente de descarga tecnico';
          $progreso = 75;
        }
        if ($tieneDescargaWordTecnico) {
          $estado = 'Completado';
          $progreso = 100;
        }

        $seguimientoTecnicoEmpresas[] = [
          'id_empresa' => (int)($rowEmpresaSeg['id_empresa'] ?? 0),
          'razon_social' => trim((string)($rowEmpresaSeg['razon_social'] ?? '')),
          'plan' => $tipoContratoLabel,
          'estado' => $estado,
          'progreso' => $progreso,
        ];
      }
      $stmtEmpresasSeg->close();
    }
  }
}

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

// =========================
// MIS REUNIONES
// =========================
$adminReuniones = [];
$adminTodasReuniones = [];
$adminClientesReunion = [];

if (in_array($view, ['privada', 'reuniones'], true) && $adminId > 0) {
  enviarRecordatoriosRRReunionesVencidas(db());
  db()->query("DELETE FROM reuniones WHERE STR_TO_DATE(CONCAT(fecha_reunion, ' ', hora_reunion), '%Y-%m-%d %H:%i') <= NOW()");
  $stmt = db()->prepare("\n    SELECT\n      r.id_reunion,\n      r.objetivo,\n      r.hora_reunion,\n      r.fecha_reunion\n    FROM reuniones r\n    INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion\n    WHERE ur.id_usuario = ?\n    ORDER BY r.fecha_reunion ASC, r.hora_reunion ASC, r.id_reunion ASC\n  ");
  $stmt->bind_param('i', $adminId);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($reunion = $result->fetch_assoc()) {
    $adminReuniones[] = $reunion;
  }
  $stmt->close();

  $stmtClientes = db()->prepare("\n    SELECT\n      u.id_usuario,\n      u.nombre_usuario,\n      u.apellidos,\n      u.email\n    FROM usuario u\n    INNER JOIN rol r ON r.id = u.rol_id\n    WHERE UPPER(r.nombre) = 'CLIENTE'\n    ORDER BY u.nombre_usuario ASC, u.apellidos ASC\n  ");
  if ($stmtClientes) {
    $stmtClientes->execute();
    $resClientesReunion = $stmtClientes->get_result();
    while ($clienteReunion = $resClientesReunion->fetch_assoc()) {
      $adminClientesReunion[] = $clienteReunion;
    }
    $stmtClientes->close();
  }

  // Cargar todas las reuniones del sistema
  $stmtTodas = db()->prepare("\n    SELECT\n      r.id_reunion,\n      r.objetivo,\n      r.hora_reunion,\n      r.fecha_reunion,\n      GROUP_CONCAT(\n        CONCAT(\n          COALESCE(u.nombre_usuario, 'Admin'),\n          ' ',\n          COALESCE(TRIM(u.apellidos), ''),\n          ' [',\n          COALESCE(TRIM(ro.nombre), ''),\n          ']'\n        )\n        SEPARATOR ' | '\n      ) AS participantes\n    FROM reuniones r\n    LEFT JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion\n    LEFT JOIN usuario u ON u.id_usuario = ur.id_usuario\n    LEFT JOIN rol ro ON ro.id = u.rol_id\n    GROUP BY r.id_reunion\n    ORDER BY r.fecha_reunion ASC, r.hora_reunion ASC, r.id_reunion ASC\n  ");
  if ($stmtTodas) {
    $stmtTodas->execute();
    $resTodas = $stmtTodas->get_result();
    while ($reunionGlobal = $resTodas->fetch_assoc()) {
      $adminTodasReuniones[] = $reunionGlobal;
    }
    $stmtTodas->close();
  }
}

require __DIR__ . '/../html/admin.html.php';