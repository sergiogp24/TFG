<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/helpers.php';
require __DIR__ . '/../config/config.php';

require_login();

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdministrador = ($rol === 'ADMINISTRADOR');
$esTecnico = ($rol === 'TECNICO');

if (!$esAdministrador && !$esTecnico) {
  http_response_code(403);
  exit('Acceso denegado');
}

$view = (string)($_GET['view'] ?? 'ver_formacion');
$allowed = ['ver_formacion', 'ver_ejercicio', 'ver_infra', 'ver_acoso', 'ver_violencia', 'ver_retribuciones', 'ver_condiciones', 'ver_salud', 'ver_responsable_igualdad', 'ver_seleccion', 'ver_promocion'];
if (!in_array($view, $allowed, true)) {
  $view = 'ver_formacion';
}

$sessionUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');
$adminEmail = $sessionEmail;
$adminUsername = $sessionUsername;

$maintenanceEmpresa = null;
$maintenanceRows = [];
$maintenanceMedidas = [];
$maintenanceSidebarItems = [];
$searchQ = trim((string)($_GET['q'] ?? ''));
$currentPage = (int)($_GET['page'] ?? 1);
if ($currentPage < 1) {
  $currentPage = 1;
}

$perPage = 10;
$offset = ($currentPage - 1) * $perPage;
$totalRows = 0;
$totalPages = 1;
$idEmpresa = (int)($_GET['id_empresa'] ?? 0);
$currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);

if ($idEmpresa > 0) {
  $stmtEmpresa = db()->prepare("SELECT id_empresa, razon_social FROM empresa WHERE id_empresa = ?" . ($esTecnico ? " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = empresa.id_empresa AND ue.id_usuario = ?)" : "") . " LIMIT 1");
  if ($esTecnico) {
    $stmtEmpresa->bind_param('ii', $idEmpresa, $currentUserId);
  } else {
    $stmtEmpresa->bind_param('i', $idEmpresa);
  }
  $stmtEmpresa->execute();
  $maintenanceEmpresa = $stmtEmpresa->get_result()->fetch_assoc();
  $stmtEmpresa->close();
}

function maintenance_area_to_menu_item(string $nombreArea): ?array
{
  $nombre = mb_strtolower(trim($nombreArea), 'UTF-8');
  if ($nombre === '') {
    return null;
  }

  if (str_contains($nombre, 'formacion') || str_contains($nombre, 'formación')) {
    return ['view' => 'ver_formacion', 'label' => 'Formación'];
  }
  if (str_contains($nombre, 'ejercicio')) {
    return ['view' => 'ver_ejercicio', 'label' => 'Ejercicio'];
  }
  if (str_contains($nombre, 'infrarrepresent') || str_contains($nombre, 'infra')) {
    return ['view' => 'ver_infra', 'label' => 'Infrarrepresentación femenina'];
  }
  if (str_contains($nombre, 'acoso')) {
    return ['view' => 'ver_acoso', 'label' => 'Acoso'];
  }
  if (str_contains($nombre, 'violencia')) {
    return ['view' => 'ver_violencia', 'label' => 'Violencia de género'];
  }
  if (str_contains($nombre, 'retribu')) {
    return ['view' => 'ver_retribuciones', 'label' => 'Retribuciones'];
  }
  if (str_contains($nombre, 'condicion') || str_contains($nombre, 'trabajo')) {
    return ['view' => 'ver_condiciones', 'label' => 'Condiciones de trabajo'];
  }
  if (str_contains($nombre, 'salud') || str_contains($nombre, 'laboral')) {
    return ['view' => 'ver_salud', 'label' => 'Salud laboral'];
  }
  if (str_contains($nombre, 'responsable de igualdad') || (str_contains($nombre, 'responsable') && str_contains($nombre, 'igualdad'))) {
    return ['view' => 'ver_responsable_igualdad', 'label' => 'Responsable de igualdad'];
  }
  if (str_contains($nombre, 'seleccion') || str_contains($nombre, 'selección') || str_contains($nombre, 'contratacion') || str_contains($nombre, 'contratación')) {
    return ['view' => 'ver_seleccion', 'label' => 'Proceso selección y contratación'];
  }
  if (str_contains($nombre, 'promocion') || str_contains($nombre, 'promoción') || str_contains($nombre, 'ascenso')) {
    return ['view' => 'ver_promocion', 'label' => 'Promoción y ascenso profesional'];
  }

  return null;
}

if ($maintenanceEmpresa !== null) {
  $sqlAreasMenu = '
    SELECT DISTINCT ap.nombre
    FROM areas_contratadas ac
    INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
    WHERE ac.id_empresa = ?
  ' . ($esTecnico ? 'AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)' : '') . '
    ORDER BY ap.nombre ASC
  ';

  $stmtAreasMenu = db()->prepare($sqlAreasMenu);
  if ($stmtAreasMenu) {
    if ($esTecnico) {
      $stmtAreasMenu->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtAreasMenu->bind_param('i', $idEmpresa);
    }
    $stmtAreasMenu->execute();
    $resAreasMenu = $stmtAreasMenu->get_result();
    while ($rowAreaMenu = $resAreasMenu->fetch_assoc()) {
      $menuItem = maintenance_area_to_menu_item((string)($rowAreaMenu['nombre'] ?? ''));
      if ($menuItem !== null) {
        $maintenanceSidebarItems[$menuItem['view']] = $menuItem;
      }
    }
    $stmtAreasMenu->close();
  }
}

if ($maintenanceEmpresa !== null) {
  if ($view === 'ver_formacion') {
    $sqlMedidas = "
      SELECT cm.id_cliente_medida, m.descripcion AS medida_descripcion
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      LEFT JOIN medida m ON m.id_medida = cm.id_medida
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%formacion%' OR LOWER(ap.nombre) LIKE '%formación%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmt = db()->prepare($sqlMedidas);
    if ($esTecnico) {
      $stmt->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmt->bind_param('i', $idEmpresa);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmt->close();

    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (af.nombre LIKE CONCAT('%', ?, '%') OR af.modalidad LIKE CONCAT('%', ?, '%') OR af.laboral LIKE CONCAT('%', ?, '%') OR af.voluntaria_obligatoria LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'ssss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_formacion af INNER JOIN cliente_medida cm ON cm.id_cliente_medida = af.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT af.id_formacion, af.nombre, af.fecha_inicio, af.fecha_fin, af.laboral, af.modalidad, af.voluntaria_obligatoria, af.n_horas, af.n_hombres, af.n_mujeres, af.informado_plantilla, af.criterio_seleccion, af.id_cliente_medida FROM area_formacion af INNER JOIN cliente_medida cm ON cm.id_cliente_medida = af.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY af.id_formacion DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();
  }

  if ($view === 'ver_infra') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (ai.plantilla_mujeres LIKE CONCAT('%', ?, '%') OR ai.plantilla_hombres LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'ss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_infra ai INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ai.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT ai.id_infra, ai.plantilla_mujeres, ai.plantilla_hombres, ai.id_cliente_medida FROM area_infra ai INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ai.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY ai.id_infra DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasInfra = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%infrarrepresent%' OR LOWER(ap.nombre) LIKE '%infra%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasInfra = db()->prepare($sqlMedidasInfra);
    if ($esTecnico) {
      $stmtMedidasInfra->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasInfra->bind_param('i', $idEmpresa);
    }
    $stmtMedidasInfra->execute();
    $resMedidasInfra = $stmtMedidasInfra->get_result();
    while ($row = $resMedidasInfra->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasInfra->close();
  }

  if ($view === 'ver_ejercicio') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND ae.medida LIKE CONCAT('%', ?, '%')";
      $params[] = $searchQ;
      $types .= 's';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_ejercicio ae INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ae.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT ae.id_ejercicio, ae.medida, ae.solicita_mujeres, ae.solicita_hombres, ae.concede_mujeres, ae.concede_hombres, ae.id_cliente_medida FROM area_ejercicio ae INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ae.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY ae.id_ejercicio DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();
  }

  if ($view === 'ver_acoso') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (aa.incidente LIKE CONCAT('%', ?, '%') OR aa.procedimiento LIKE CONCAT('%', ?, '%') OR aa.grado_incidencia LIKE CONCAT('%', ?, '%') OR COALESCE(aa.acciones, '') LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'ssss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_acoso aa INNER JOIN cliente_medida cm ON cm.id_cliente_medida = aa.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT aa.id_acoso, aa.incidente, aa.procedimiento, aa.grado_incidencia, aa.fecha_alta, aa.acciones, aa.id_cliente_medida FROM area_acoso aa INNER JOIN cliente_medida cm ON cm.id_cliente_medida = aa.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY aa.id_acoso DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasAcoso = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%acoso%' OR LOWER(ap.nombre) LIKE '%acoso sexual%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasAcoso = db()->prepare($sqlMedidasAcoso);
    if ($esTecnico) {
      $stmtMedidasAcoso->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasAcoso->bind_param('i', $idEmpresa);
    }
    $stmtMedidasAcoso->execute();
    $resMedidasAcoso = $stmtMedidasAcoso->get_result();
    while ($row = $resMedidasAcoso->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasAcoso->close();
  }

  if ($view === 'ver_violencia') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (av.acciones LIKE CONCAT('%', ?, '%') OR av.observaciones LIKE CONCAT('%', ?, '%') OR CAST(av.solicita_mujeres AS CHAR) LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'sss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_violencia av INNER JOIN cliente_medida cm ON cm.id_cliente_medida = av.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT av.id_violencia, av.acciones, av.observaciones, av.fecha_alta, av.solicita_mujeres, av.id_cliente_medida FROM area_violencia av INNER JOIN cliente_medida cm ON cm.id_cliente_medida = av.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY av.id_violencia DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasViolencia = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%violencia%' OR LOWER(ap.nombre) LIKE '%violencia de genero%' OR LOWER(ap.nombre) LIKE '%violencia género%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasViolencia = db()->prepare($sqlMedidasViolencia);
    if ($esTecnico) {
      $stmtMedidasViolencia->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasViolencia->bind_param('i', $idEmpresa);
    }
    $stmtMedidasViolencia->execute();
    $resMedidasViolencia = $stmtMedidasViolencia->get_result();
    while ($row = $resMedidasViolencia->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasViolencia->close();
  }

  if ($view === 'ver_retribuciones') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (ar.permisos LIKE CONCAT('%', ?, '%') OR CAST(ar.num_mujeres AS CHAR) LIKE CONCAT('%', ?, '%') OR CAST(ar.num_hombres AS CHAR) LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'sss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_retribuciones ar INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ar.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT ar.id_retribuciones, ar.permisos, ar.num_mujeres, ar.num_hombres, ar.id_cliente_medida FROM area_retribuciones ar INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ar.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY ar.id_retribuciones DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasRetribuciones = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%retribu%' OR LOWER(ap.nombre) LIKE '%retribucion%' OR LOWER(ap.nombre) LIKE '%retribución%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasRetribuciones = db()->prepare($sqlMedidasRetribuciones);
    if ($esTecnico) {
      $stmtMedidasRetribuciones->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasRetribuciones->bind_param('i', $idEmpresa);
    }
    $stmtMedidasRetribuciones->execute();
    $resMedidasRetribuciones = $stmtMedidasRetribuciones->get_result();
    while ($row = $resMedidasRetribuciones->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasRetribuciones->close();
  }

  if ($view === 'ver_condiciones') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (acc.n_conversiones_contrato LIKE CONCAT('%', ?, '%') OR acc.n_jornadas_ampliadas LIKE CONCAT('%', ?, '%') OR acc.evaluacion_condiciones_trabajo LIKE CONCAT('%', ?, '%') OR acc.muestreo LIKE CONCAT('%', ?, '%') OR CAST(acc.contrataciones_realizadas AS CHAR) LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'sssss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_condiciones_trabajo acc INNER JOIN cliente_medida cm ON cm.id_cliente_medida = acc.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT acc.id_condiciones, acc.n_conversiones_contrato, acc.n_jornadas_ampliadas, acc.evaluacion_condiciones_trabajo, acc.muestreo, acc.contrataciones_realizadas, acc.id_cliente_medida FROM area_condiciones_trabajo acc INNER JOIN cliente_medida cm ON cm.id_cliente_medida = acc.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY acc.id_condiciones DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasCondiciones = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%condicion%' OR LOWER(ap.nombre) LIKE '%trabajo%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasCondiciones = db()->prepare($sqlMedidasCondiciones);
    if ($esTecnico) {
      $stmtMedidasCondiciones->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasCondiciones->bind_param('i', $idEmpresa);
    }
    $stmtMedidasCondiciones->execute();
    $resMedidasCondiciones = $stmtMedidasCondiciones->get_result();
    while ($row = $resMedidasCondiciones->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasCondiciones->close();
  }

  if ($view === 'ver_salud') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (asal.nombre LIKE CONCAT('%', ?, '%') OR asal.procedencia LIKE CONCAT('%', ?, '%') OR COALESCE(asal.observaciones, '') LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'sss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_salud asal INNER JOIN cliente_medida cm ON cm.id_cliente_medida = asal.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT asal.id_salud, asal.nombre, asal.procedencia, asal.observaciones, asal.id_cliente_medida FROM area_salud asal INNER JOIN cliente_medida cm ON cm.id_cliente_medida = asal.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY asal.id_salud DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasSalud = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%salud%' OR LOWER(ap.nombre) LIKE '%laboral%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasSalud = db()->prepare($sqlMedidasSalud);
    if ($esTecnico) {
      $stmtMedidasSalud->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasSalud->bind_param('i', $idEmpresa);
    }
    $stmtMedidasSalud->execute();
    $resMedidasSalud = $stmtMedidasSalud->get_result();
    while ($row = $resMedidasSalud->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasSalud->close();
  }

  if ($view === 'ver_responsable_igualdad') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (ari.nombre LIKE CONCAT('%', ?, '%') OR ari.email LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'ss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_responsable_igualdad ari INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = ari.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT ari.id_responsable_de_igualdad, ari.nombre, ari.email, ari.id_areas_contratadas FROM area_responsable_igualdad ari INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = ari.id_areas_contratadas $where ORDER BY ari.id_responsable_de_igualdad DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlAreasResponsable = "
      SELECT ac.id_areas_contratadas
      FROM areas_contratadas ac
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%responsable de igualdad%' OR (LOWER(ap.nombre) LIKE '%responsable%' AND LOWER(ap.nombre) LIKE '%igualdad%'))
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY ac.id_areas_contratadas ASC
    ";
    $stmtAreasResponsable = db()->prepare($sqlAreasResponsable);
    if ($esTecnico) {
      $stmtAreasResponsable->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtAreasResponsable->bind_param('i', $idEmpresa);
    }
    $stmtAreasResponsable->execute();
    $resAreasResponsable = $stmtAreasResponsable->get_result();
    while ($row = $resAreasResponsable->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtAreasResponsable->close();
  }

  if ($view === 'ver_seleccion') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (ase.puesto_actual LIKE CONCAT('%', ?, '%') OR ase.responsable LIKE CONCAT('%', ?, '%') OR ase.responsable_Int_Ext LIKE CONCAT('%', ?, '%') OR ase.criterio_seleccion LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'ssss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_seleccion ase INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ase.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT ase.id_seleccion, ase.puesto_actual, ase.fecha_alta, ase.responsable, ase.responsable_Int_Ext, ase.crgo_responsable, ase.gnro_seleccionado, ase.c_mujeres, ase.c_hombres, ase.criterio_seleccion, ase.id_cliente_medida FROM area_seleccion ase INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ase.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY ase.id_seleccion DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasSeleccion = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%seleccion%' OR LOWER(ap.nombre) LIKE '%selección%' OR LOWER(ap.nombre) LIKE '%contratacion%' OR LOWER(ap.nombre) LIKE '%contratación%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasSeleccion = db()->prepare($sqlMedidasSeleccion);
    if ($esTecnico) {
      $stmtMedidasSeleccion->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasSeleccion->bind_param('i', $idEmpresa);
    }
    $stmtMedidasSeleccion->execute();
    $resMedidasSeleccion = $stmtMedidasSeleccion->get_result();
    while ($row = $resMedidasSeleccion->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasSeleccion->close();
  }

  if ($view === 'ver_promocion') {
    $where = "WHERE ac.id_empresa = ?";
    $params = [$idEmpresa];
    $types = 'i';
    if ($searchQ !== '') {
      $where .= " AND (pap.puesto_origen LIKE CONCAT('%', ?, '%') OR pap.puesto_destino LIKE CONCAT('%', ?, '%') OR pap.responsable LIKE CONCAT('%', ?, '%') OR pap.tipo_promocion LIKE CONCAT('%', ?, '%'))";
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $params[] = $searchQ;
      $types .= 'ssss';
    }
    if ($esTecnico) {
      $where .= " AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)";
      $params[] = $currentUserId;
      $types .= 'i';
    }

    $stmtTotal = db()->prepare("SELECT COUNT(*) AS total FROM area_promocion_ascenso_personal pap INNER JOIN cliente_medida cm ON cm.id_cliente_medida = pap.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where");
    $stmtTotal->bind_param($types, ...$params);
    $stmtTotal->execute();
    $totalRows = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) {
      $currentPage = $totalPages;
      $offset = ($currentPage - 1) * $perPage;
    }

    $stmtData = db()->prepare("SELECT pap.id_promocion, pap.puesto_origen, pap.puesto_destino, pap.aumento_economico, pap.n_candidaturas, pap.n_hombres, pap.n_mujeres, pap.responsable, pap.cargo_responsable, pap.genero_responsable, pap.genero_promocionado, pap.interna_externa, pap.contrato_inicial, pap.contrato_final, pap.tipo_promocion, pap.fecha_de_alta, pap.porcentaje_jornada, pap.disfruta_conciliacion, pap.criterio, pap.id_cliente_medida FROM area_promocion_ascenso_personal pap INNER JOIN cliente_medida cm ON cm.id_cliente_medida = pap.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas $where ORDER BY pap.id_promocion DESC LIMIT ? OFFSET ?");
    $paramsData = array_merge($params, [$perPage, $offset]);
    $typesData = $types . 'ii';
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $resData = $stmtData->get_result();
    while ($row = $resData->fetch_assoc()) {
      $maintenanceRows[] = $row;
    }
    $stmtData->close();

    $sqlMedidasPromocion = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%promocion%' OR LOWER(ap.nombre) LIKE '%promoción%' OR LOWER(ap.nombre) LIKE '%ascenso%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
    ";
    $stmtMedidasPromocion = db()->prepare($sqlMedidasPromocion);
    if ($esTecnico) {
      $stmtMedidasPromocion->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedidasPromocion->bind_param('i', $idEmpresa);
    }
    $stmtMedidasPromocion->execute();
    $resMedidasPromocion = $stmtMedidasPromocion->get_result();
    while ($row = $resMedidasPromocion->fetch_assoc()) {
      $maintenanceMedidas[] = $row;
    }
    $stmtMedidasPromocion->close();
  }
}

require __DIR__ . '/../html/mantenimiento.php';
