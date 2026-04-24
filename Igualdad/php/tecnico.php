<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/helpers.php';
require_once __DIR__ . '/../php/mails.php';
require_role('TECNICO');

require __DIR__ . '/../config/config.php';

$view = (string)($_GET['view'] ?? 'menu');
$allowed = ['menu', 'privada', 'perfil', 'reuniones', 'contacto_empresa'];
if (!in_array($view, $allowed, true)) $view = 'menu';

$tecnicoUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'tecnico');
$tecnicoId = (int)($_SESSION['user']['id_usuario'] ?? 0);

$tecnicoEmail = '';
if ($tecnicoId > 0) {
  $stmt = db()->prepare("SELECT email FROM usuario WHERE id_usuario = ? LIMIT 1");
  $stmt->bind_param('i', $tecnicoId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $tecnicoEmail = (string)($row['email'] ?? '');
}

$tecnicoStats = [
  'empresas_asignadas' => 0,
  'mis_planes' => 0,
  'mis_mantenimientos' => 0,
  'reuniones_programadas' => 0,
];

if ($tecnicoId > 0) {
  $scopeSql = "
    SELECT DISTINCT ue.id_empresa AS id_empresa
    FROM usuario_empresa ue
    WHERE ue.id_usuario = ?
    UNION
    SELECT DISTINCT e.id_empresa AS id_empresa
    FROM empresa e
    WHERE e.id_usuario = ?
    UNION
    SELECT DISTINCT ce.id_empresa AS id_empresa
    FROM contrato_empresa ce
    WHERE ce.id_usuario = ?
  ";

  $sqlEmpresas = "SELECT COUNT(*) AS total FROM (" . $scopeSql . ") te";
  $stmtEmpresas = db()->prepare($sqlEmpresas);
  if ($stmtEmpresas) {
    $stmtEmpresas->bind_param('iii', $tecnicoId, $tecnicoId, $tecnicoId);
    $stmtEmpresas->execute();
    $rowEmpresas = $stmtEmpresas->get_result()->fetch_assoc();
    $tecnicoStats['empresas_asignadas'] = (int)($rowEmpresas['total'] ?? 0);
    $stmtEmpresas->close();
  }

  $sqlPlanes = "
    SELECT COUNT(DISTINCT c.id_contrato_empresa) AS total
    FROM contrato_empresa c
    INNER JOIN (" . $scopeSql . ") te ON te.id_empresa = c.id_empresa
    WHERE UPPER(TRIM(c.tipo_contrato)) LIKE 'PLAN IGUALDAD%'
  ";
  $stmtPlanes = db()->prepare($sqlPlanes);
  if ($stmtPlanes) {
    $stmtPlanes->bind_param('iii', $tecnicoId, $tecnicoId, $tecnicoId);
    $stmtPlanes->execute();
    $rowPlanes = $stmtPlanes->get_result()->fetch_assoc();
    $tecnicoStats['mis_planes'] = (int)($rowPlanes['total'] ?? 0);
    $stmtPlanes->close();
  }

  $sqlMantenimientos = "
    SELECT COUNT(DISTINCT c.id_contrato_empresa) AS total
    FROM contrato_empresa c
    INNER JOIN (" . $scopeSql . ") te ON te.id_empresa = c.id_empresa
    WHERE UPPER(TRIM(c.tipo_contrato)) LIKE 'MANTENIMIENTO%'
  ";
  $stmtMantenimiento = db()->prepare($sqlMantenimientos);
  if ($stmtMantenimiento) {
    $stmtMantenimiento->bind_param('iii', $tecnicoId, $tecnicoId, $tecnicoId);
    $stmtMantenimiento->execute();
    $rowMantenimiento = $stmtMantenimiento->get_result()->fetch_assoc();
    $tecnicoStats['mis_mantenimientos'] = (int)($rowMantenimiento['total'] ?? 0);
    $stmtMantenimiento->close();
  }

  $sqlReuniones = "
    SELECT COUNT(*) AS total
    FROM reuniones r
    INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
    WHERE ur.id_usuario = ?
      AND STR_TO_DATE(CONCAT(r.fecha_reunion, ' ', r.hora_reunion), '%Y-%m-%d %H:%i') > NOW()
  ";
  $stmtReuniones = db()->prepare($sqlReuniones);
  if ($stmtReuniones) {
    $stmtReuniones->bind_param('i', $tecnicoId);
    $stmtReuniones->execute();
    $rowReuniones = $stmtReuniones->get_result()->fetch_assoc();
    $tecnicoStats['reuniones_programadas'] = (int)($rowReuniones['total'] ?? 0);
    $stmtReuniones->close();
  }
}

$tecnicoPerfil = null;
if ($view === 'perfil' && $tecnicoId > 0) {
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
  $stmt->bind_param('i', $tecnicoId);
  $stmt->execute();
  $tecnicoPerfil = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$tecnicoEmpresasContacto = [];
if (in_array($view, ['menu', 'contacto_empresa'], true) && $tecnicoId > 0) {
  $stmtEmpresasContacto = db()->prepare("\n    SELECT DISTINCT\n      e.id_empresa,\n      e.razon_social,\n      TRIM(COALESCE(e.email, '')) AS email\n    FROM empresa e\n    WHERE EXISTS (\n      SELECT 1\n      FROM usuario_empresa ue\n      WHERE ue.id_empresa = e.id_empresa\n        AND ue.id_usuario = ?\n    ) OR e.id_usuario = ?\n      OR EXISTS (\n        SELECT 1\n        FROM contrato_empresa ce\n        WHERE ce.id_empresa = e.id_empresa\n          AND ce.id_usuario = ?\n      )\n    ORDER BY e.razon_social ASC\n  ");
  $stmtEmpresasContacto->bind_param('iii', $tecnicoId, $tecnicoId, $tecnicoId);
  $stmtEmpresasContacto->execute();
  $resEmpresasContacto = $stmtEmpresasContacto->get_result();
  while ($rowEmpresa = $resEmpresasContacto->fetch_assoc()) {
    $tecnicoEmpresasContacto[] = $rowEmpresa;
  }
  $stmtEmpresasContacto->close();
}

$tecnicoReuniones = [];
$tecnicoEmpresas = [];
$tecnicoClientesEmpresa = [];
$tecnicoTodasReuniones = [];

if (in_array($view, ['privada', 'reuniones'], true) && $tecnicoId > 0) {
  correo_enviar_recordatorio_rr_reuniones_vencidas(db());
  db()->query("DELETE FROM reuniones WHERE STR_TO_DATE(CONCAT(fecha_reunion, ' ', hora_reunion), '%Y-%m-%d %H:%i') <= NOW()");

  // Mis reuniones
  $stmt = db()->prepare("
    SELECT
      r.id_reunion,
      r.objetivo,
      r.hora_reunion,
      r.fecha_reunion
    FROM reuniones r
    INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
    WHERE ur.id_usuario = ?
    ORDER BY r.fecha_reunion ASC, r.hora_reunion ASC, r.id_reunion ASC
  ");
  $stmt->bind_param('i', $tecnicoId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $tecnicoReuniones[] = $row;
  }
  $stmt->close();

  // Empresas asignadas al técnico
  $stmtEmpresas = db()->prepare("
    SELECT DISTINCT
      e.id_empresa,
      e.razon_social
    FROM empresa e
    WHERE EXISTS (
      SELECT 1
      FROM usuario_empresa ue
      WHERE ue.id_empresa = e.id_empresa
        AND ue.id_usuario = ?
    ) OR e.id_usuario = ?
      OR EXISTS (
        SELECT 1
        FROM contrato_empresa ce
        WHERE ce.id_empresa = e.id_empresa
          AND ce.id_usuario = ?
      )
    ORDER BY e.razon_social ASC
  ");
  $stmtEmpresas->bind_param('iii', $tecnicoId, $tecnicoId, $tecnicoId);
  $stmtEmpresas->execute();
  $resEmpresas = $stmtEmpresas->get_result();
  while ($empresa = $resEmpresas->fetch_assoc()) {
    $tecnicoEmpresas[] = $empresa;
  }
  $stmtEmpresas->close();

  // Clientes de las empresas asignadas al tecnico
  $stmtClientesEmpresa = db()->prepare("\n    SELECT DISTINCT\n      ue.id_empresa,\n      u.id_usuario,\n      u.nombre_usuario,\n      u.apellidos\n    FROM usuario_empresa ue\n    INNER JOIN usuario u ON u.id_usuario = ue.id_usuario\n    INNER JOIN rol r ON r.id = u.rol_id\n    WHERE UPPER(TRIM(r.nombre)) = 'CLIENTE'\n      AND ue.id_empresa IN (\n        SELECT DISTINCT ue2.id_empresa\n        FROM usuario_empresa ue2\n        WHERE ue2.id_usuario = ?\n        UNION\n        SELECT DISTINCT e.id_empresa\n        FROM empresa e\n        WHERE e.id_usuario = ?\n        UNION\n        SELECT DISTINCT ce.id_empresa\n        FROM contrato_empresa ce\n        WHERE ce.id_usuario = ?\n      )\n    ORDER BY ue.id_empresa ASC, u.nombre_usuario ASC, u.apellidos ASC\n  ");
  if ($stmtClientesEmpresa) {
    $stmtClientesEmpresa->bind_param('iii', $tecnicoId, $tecnicoId, $tecnicoId);
    $stmtClientesEmpresa->execute();
    $resClientesEmpresa = $stmtClientesEmpresa->get_result();
    while ($clienteEmpresa = $resClientesEmpresa->fetch_assoc()) {
      $tecnicoClientesEmpresa[] = $clienteEmpresa;
    }
    $stmtClientesEmpresa->close();
  }

  // Todas las reuniones de las empresas asignadas al tecnico (incluye las creadas por clientes)
  $stmtTodasReuniones = db()->prepare("\n    SELECT\n      r.id_reunion,\n      r.objetivo,\n      r.hora_reunion,\n      r.fecha_reunion,\n      GROUP_CONCAT(\n        CONCAT(\n          COALESCE(u.nombre_usuario, 'Usuario'),\n          ' ',\n          COALESCE(TRIM(u.apellidos), ''),\n          ' [',\n          COALESCE(TRIM(ro.nombre), ''),\n          ']'\n        )\n        SEPARATOR ' | '\n      ) AS participantes\n    FROM reuniones r\n    LEFT JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion\n    LEFT JOIN usuario u ON u.id_usuario = ur.id_usuario\n    LEFT JOIN rol ro ON ro.id = u.rol_id\n    WHERE EXISTS (\n      SELECT 1\n      FROM usuario_reunion ur2\n      WHERE ur2.id_reunion = r.id_reunion\n        AND (\n          EXISTS (\n            SELECT 1\n            FROM usuario_empresa ue2\n            WHERE ue2.id_usuario = ur2.id_usuario\n              AND ue2.id_empresa IN (\n                SELECT DISTINCT ue3.id_empresa\n                FROM usuario_empresa ue3\n                WHERE ue3.id_usuario = ?\n                UNION\n                SELECT DISTINCT e3.id_empresa\n                FROM empresa e3\n                WHERE e3.id_usuario = ?\n                UNION\n                SELECT DISTINCT ce3.id_empresa\n                FROM contrato_empresa ce3\n                WHERE ce3.id_usuario = ?\n              )\n          )\n          OR EXISTS (\n            SELECT 1\n            FROM empresa e2\n            WHERE e2.id_usuario = ur2.id_usuario\n              AND e2.id_empresa IN (\n                SELECT DISTINCT ue4.id_empresa\n                FROM usuario_empresa ue4\n                WHERE ue4.id_usuario = ?\n                UNION\n                SELECT DISTINCT e4.id_empresa\n                FROM empresa e4\n                WHERE e4.id_usuario = ?\n                UNION\n                SELECT DISTINCT ce4.id_empresa\n                FROM contrato_empresa ce4\n                WHERE ce4.id_usuario = ?\n              )\n          )\n        )\n    )\n    GROUP BY r.id_reunion\n    ORDER BY r.fecha_reunion ASC, r.hora_reunion ASC, r.id_reunion ASC\n  ");
  if ($stmtTodasReuniones) {
    $stmtTodasReuniones->bind_param('iiiiii', $tecnicoId, $tecnicoId, $tecnicoId, $tecnicoId, $tecnicoId, $tecnicoId);
    $stmtTodasReuniones->execute();
    $resTodasReuniones = $stmtTodasReuniones->get_result();
    while ($reunionEmpresa = $resTodasReuniones->fetch_assoc()) {
      $tecnicoTodasReuniones[] = $reunionEmpresa;
    }
    $stmtTodasReuniones->close();
  }
}

require __DIR__ . '/../html/tecnico.html.php';
