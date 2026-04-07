<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Views SOLO de EMPRESAS
$view = (string)($_GET['view'] ?? 'ver_empresas');
$allowed = ['ver_empresas', 'add_empresas', 'edit_empresas', 'delete_empresas', 'ver_planes', 'ver_medidas', 'edit_plan', 'ver_contratos', 'add_contratos', 'edit_contratos', 'delete_contratos'];
if (!in_array($view, $allowed, true)) $view = 'ver_empresas';

// Datos de sesión (para sidebar/header si lo compartes)
$adminUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'admin');
$adminId = (int)($_SESSION['user']['id_usuario'] ?? 0);

$adminEmail = '';
if ($adminId > 0) {
  $stmt = db()->prepare("SELECT email FROM usuario WHERE id_usuario = ? LIMIT 1");
  $stmt->bind_param('i', $adminId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $adminEmail = (string)($row['email'] ?? '');
}

/* =========================================================
 * EMPRESAS: búsqueda + paginación (10 por página)
 * (solo para ver_empresas / delete_empresas)
 * ========================================================= */
$empresas = [];
$searchQ = trim((string)($_GET['q'] ?? ''));
$currentPage = (int)($_GET['page'] ?? 1);
if ($currentPage < 1) $currentPage = 1;

$perPage = 10;
$offset = ($currentPage - 1) * $perPage;

$totalEmpresas = 0;
$totalPages = 1;

if ($view === 'ver_empresas' || $view === 'delete_empresas') {
  $where = '';
  $params = [];
  $types = '';

  if ($searchQ !== '') {
    $where = "WHERE (
      e.razon_social LIKE CONCAT('%', ?, '%')
      OR e.nif LIKE CONCAT('%', ?, '%')
      OR e.responsable LIKE CONCAT('%', ?, '%')
      OR e.sector LIKE CONCAT('%', ?, '%')
      OR e.email LIKE CONCAT('%', ?, '%')
      OR e.telefono LIKE CONCAT('%', ?, '%')
    )";
    $params = [$searchQ, $searchQ, $searchQ, $searchQ, $searchQ, $searchQ];
    $types = 'ssssss';
  }

  // Total
  $sqlTotal = "
    SELECT COUNT(*) AS total
    FROM empresa e
    $where
  ";
  $stmt = db()->prepare($sqlTotal);
  if ($searchQ !== '') $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $totalRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $totalEmpresas = (int)($totalRow['total'] ?? 0);
  $totalPages = (int)ceil($totalEmpresas / $perPage);
  if ($totalPages < 1) $totalPages = 1;

  if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
  }

  // Data
  $sqlData = "
    SELECT
      e.id_empresa,
      e.razon_social,
      e.nif,
      e.responsable,
      e.sector,
      e.telefono,
      e.email
    FROM empresa e
    $where
    ORDER BY e.razon_social ASC
    LIMIT ? OFFSET ?
  ";

  $stmt = db()->prepare($sqlData);

  if ($searchQ !== '') {
    $types2 = $types . 'ii'; // ssssssii
    $params2 = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($types2, ...$params2);
  } else {
    $stmt->bind_param('ii', $perPage, $offset);
  }

  $stmt->execute();
  $resE = $stmt->get_result();
  while ($e = $resE->fetch_assoc()) $empresas[] = $e;
  $stmt->close();
}

/* =========================================================
 * Empresa seleccionada (para edit_empresas)
 * ========================================================= */
$selectedEmpresa = null;
if ($view === 'edit_empresas') {
  $idEmpresa = (int)($_GET['id_empresa'] ?? 0);
  if ($idEmpresa > 0) {
    $stmt = db()->prepare("
      SELECT
        id_empresa,
        razon_social,
        nif,
        domicilio_social,
        forma_juridica,
        ano_constitucional,
        responsable,
        cargo,
        contacto,
        email,
        telefono,
        sector,
        cnae,
        convenio,
        personas_mujeres,
        personas_hombres,
        personas_total,
        centros_trabajo,
        recogida_informacion,
        vigencia_plan,
        id_usuario
      FROM empresa
      WHERE id_empresa = ?
      LIMIT 1
    ");
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $selectedEmpresa = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

/* =========================================================
 * CONTRATOS: utilidades de carga
 * ========================================================= */
$db = db();
$tablaContratoExiste = false;
$resTabla = $db->query("SHOW TABLES LIKE 'contrato_empresa'");
if ($resTabla instanceof mysqli_result && $resTabla->num_rows > 0) {
  $tablaContratoExiste = true;
}

/* =========================================================
 * Contrato seleccionado (para edit_contratos)
 * ========================================================= */
$selectedContrato = null;
if ($view === 'edit_contratos' && $tablaContratoExiste) {
  $idContrato = (int)($_GET['id_contrato'] ?? 0);
  if ($idContrato > 0) {
    $stmt = $db->prepare("
      SELECT
        id_contrato_empresa,
        tipo_contrato,
        id_empresa
      FROM contrato_empresa
      WHERE id_contrato_empresa = ?
      LIMIT 1
    ");
    $stmt->bind_param('i', $idContrato);
    $stmt->execute();
    $selectedContrato = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

/* =========================================================
 * DATOS PARA add_contratos / ver_contratos
 * ========================================================= */
$contratos = [];
$searchContratoQ = trim((string)($_GET['q'] ?? ''));
$currentPageContratos = (int)($_GET['page'] ?? 1);
if ($currentPageContratos < 1) $currentPageContratos = 1;

$perPageContratos = 10;
$offsetContratos = ($currentPageContratos - 1) * $perPageContratos;
$totalContratos = 0;
$totalPagesContratos = 1;

$tiposContrato = ['COMPLETO', 'MANTENIMIENTO'];
$empresasForContrato = [];
$areasPlanContrato = [];
$medidasPorAreaContrato = [];
$addContratoOld = $_SESSION['add_contrato_old'] ?? [
  'id_empresa' => 0,
  'tipo_contrato' => 'COMPLETO',
  'inicio_plan' => '',
  'fin_plan' => '',
  'areas' => [],
  'medidas' => [],

];
$contratoError = (string)($_SESSION['add_contrato_error'] ?? '');
unset($_SESSION['add_contrato_old'], $_SESSION['add_contrato_error']);

if ($view === 'add_contratos') {
  $resEmp = $db->query("SELECT id_empresa, razon_social FROM empresa ORDER BY razon_social");
  while ($e = $resEmp->fetch_assoc()) $empresasForContrato[] = $e;

  $resAreasContrato = $db->query("SELECT id_plan, nombre FROM area_plan ORDER BY nombre");
  while ($area = $resAreasContrato->fetch_assoc()) {
    $areaId = (int)$area['id_plan'];
    $areasPlanContrato[] = $area;
    $medidasPorAreaContrato[$areaId] = [];
  }

  $resMedidasContrato = $db->query("SELECT id_medida, descripcion, id_plan FROM medida ORDER BY id_plan, descripcion");
  while ($m = $resMedidasContrato->fetch_assoc()) {
    $areaId = (int)$m['id_plan'];
    if (!isset($medidasPorAreaContrato[$areaId])) $medidasPorAreaContrato[$areaId] = [];
    $medidasPorAreaContrato[$areaId][] = $m;
  }
}

if ($view === 'ver_contratos' && $tablaContratoExiste) {
  $whereContratos = '';
  $paramsCon = [];
  $typesCon = '';

  if ($searchContratoQ !== '') {
    $whereContratos = "WHERE (
      e.razon_social LIKE CONCAT('%', ?, '%')
      OR c.tipo_contrato LIKE CONCAT('%', ?, '%')
      OR c.inicio_contratacion LIKE CONCAT('%', ?, '%')
      OR c.fin_contratacion LIKE CONCAT('%', ?, '%')
    )";
    $paramsCon = [$searchContratoQ, $searchContratoQ, $searchContratoQ, $searchContratoQ];
    $typesCon = 'ssss';
  }

  $sqlTotalContratos = "
    SELECT COUNT(*) AS total
    FROM contrato_empresa c
    JOIN empresa e ON e.id_empresa = c.id_empresa
    $whereContratos
  ";
  $stmtTotalCon = $db->prepare($sqlTotalContratos);
  if ($searchContratoQ !== '') $stmtTotalCon->bind_param($typesCon, ...$paramsCon);
  $stmtTotalCon->execute();
  $rowTotalCon = $stmtTotalCon->get_result()->fetch_assoc();
  $stmtTotalCon->close();

  $totalContratos = (int)($rowTotalCon['total'] ?? 0);
  $totalPagesContratos = (int)ceil($totalContratos / $perPageContratos);
  if ($totalPagesContratos < 1) $totalPagesContratos = 1;

  if ($currentPageContratos > $totalPagesContratos) {
    $currentPageContratos = $totalPagesContratos;
    $offsetContratos = ($currentPageContratos - 1) * $perPageContratos;
  }

  $sqlContratos = "
    SELECT
      c.id_contrato_empresa,
      c.tipo_contrato,
      c.inicio_contratacion,
      c.fin_contratacion,
      c.id_empresa,
      e.razon_social
    FROM contrato_empresa c
    JOIN empresa e ON e.id_empresa = c.id_empresa
    $whereContratos
    ORDER BY c.id_contrato_empresa DESC
    LIMIT ? OFFSET ?
  ";

  $stmtCon = $db->prepare($sqlContratos);
  if ($searchContratoQ !== '') {
    $typesConData = $typesCon . 'ii';
    $paramsConData = array_merge($paramsCon, [$perPageContratos, $offsetContratos]);
    $stmtCon->bind_param($typesConData, ...$paramsConData);
  } else {
    $stmtCon->bind_param('ii', $perPageContratos, $offsetContratos);
  }

  $stmtCon->execute();
  $resCon = $stmtCon->get_result();
  while ($c = $resCon->fetch_assoc()) $contratos[] = $c;
  $stmtCon->close();
}

/* =========================================================
 * DATOS PARA ver_planes - con búsqueda y paginación
 * ========================================================= */
$planes = [];
$searchPlanesQ = trim((string)($_GET['q'] ?? ''));
$currentPagePlanes = (int)($_GET['page'] ?? 1);
if ($currentPagePlanes < 1) $currentPagePlanes = 1;

$perPagePlanes = 10;
$offsetPlanes = ($currentPagePlanes - 1) * $perPagePlanes;
$totalPlanesCount = 0;
$totalPagesPlanes = 1;

if ($view === 'ver_planes') {
  $wherePlanes = '';
  $paramsPla = [];
  $typesPla = '';

  if ($searchPlanesQ !== '') {
    $wherePlanes = "WHERE e.razon_social LIKE CONCAT('%', ?, '%')
      OR e.nif LIKE CONCAT('%', ?, '%')
      OR e.responsable LIKE CONCAT('%', ?, '%')";
    $paramsPla = [$searchPlanesQ, $searchPlanesQ, $searchPlanesQ];
    $typesPla = 'sss';
  }

  // Total de planes
  $sqlTotalPlanes = "
    SELECT COUNT(DISTINCT e.id_empresa) AS total
    FROM empresa e
    JOIN areas_contratadas pc ON pc.id_empresa = e.id_empresa
    $wherePlanes
  ";
  $stmtTotal = db()->prepare($sqlTotalPlanes);
  if ($searchPlanesQ !== '') $stmtTotal->bind_param($typesPla, ...$paramsPla);
  $stmtTotal->execute();
  $totalRow = $stmtTotal->get_result()->fetch_assoc();
  $stmtTotal->close();

  $totalPlanesCount = (int)($totalRow['total'] ?? 0);
  $totalPagesPlanes = (int)ceil($totalPlanesCount / $perPagePlanes);
  if ($totalPagesPlanes < 1) $totalPagesPlanes = 1;

  if ($currentPagePlanes > $totalPagesPlanes) {
    $currentPagePlanes = $totalPagesPlanes;
    $offsetPlanes = ($currentPagePlanes - 1) * $perPagePlanes;
  }

  // Data de planes con paginación
  $sqlDataPlanes = "
    SELECT
      e.id_empresa,
      e.razon_social,
      MIN(pc.inicio_plan) AS inicio_plan,
      MAX(pc.fin_plan) AS fin_plan
    FROM empresa e
    JOIN areas_contratadas pc ON pc.id_empresa = e.id_empresa
    $wherePlanes
    GROUP BY e.id_empresa, e.razon_social
    ORDER BY e.razon_social ASC
    LIMIT ? OFFSET ?
  ";

  $stmtData = db()->prepare($sqlDataPlanes);
  if ($searchPlanesQ !== '') {
    $typesFinal = $typesPla . 'ii';
    $paramsFinal = array_merge($paramsPla, [$perPagePlanes, $offsetPlanes]);
    $stmtData->bind_param($typesFinal, ...$paramsFinal);
  } else {
    $stmtData->bind_param('ii', $perPagePlanes, $offsetPlanes);
  }

  $stmtData->execute();
  $resPlanes = $stmtData->get_result();
  while ($p = $resPlanes->fetch_assoc()) $planes[] = $p;
  $stmtData->close();
}

/* =========================================================
 * DATOS PARA ver_medidas
 * ========================================================= */
$verMedidasPlan = null;
$verMedidasAreas = [];

if ($view === 'ver_medidas') {
  $idEmpresaMedidas = (int)($_GET['id_empresa'] ?? 0);

  if ($idEmpresaMedidas > 0) {
    $stmtPlan = db()->prepare(" 
      SELECT
        e.id_empresa,
        e.razon_social,
        MIN(pc.inicio_plan) AS inicio_plan,
        MAX(pc.fin_plan) AS fin_plan
      FROM empresa e
      JOIN areas_contratadas pc ON pc.id_empresa = e.id_empresa
      WHERE e.id_empresa = ?
      GROUP BY e.id_empresa, e.razon_social
      LIMIT 1
    ");
    $stmtPlan->bind_param('i', $idEmpresaMedidas);
    $stmtPlan->execute();
    $verMedidasPlan = $stmtPlan->get_result()->fetch_assoc();
    $stmtPlan->close();

    if ($verMedidasPlan !== null) {
      $stmtMedidas = db()->prepare(" 
        SELECT DISTINCT
          ap.id_plan,
          ap.nombre AS area_nombre,
          m.id_medida,
          m.descripcion
        FROM areas_contratadas pc
        JOIN area_plan ap ON ap.id_plan = pc.id_plan
        LEFT JOIN cliente_medida cm ON cm.id_areas_contratadas = pc.id_areas_contratadas
        LEFT JOIN medida m ON m.id_medida = cm.id_medida
        WHERE pc.id_empresa = ?
        ORDER BY ap.nombre ASC, m.descripcion ASC
      ");
      $stmtMedidas->bind_param('i', $idEmpresaMedidas);
      $stmtMedidas->execute();
      $resMedidas = $stmtMedidas->get_result();

      while ($row = $resMedidas->fetch_assoc()) {
        $areaId = (int)$row['id_plan'];
        if (!isset($verMedidasAreas[$areaId])) {
          $verMedidasAreas[$areaId] = [
            'nombre' => (string)$row['area_nombre'],
            'medidas' => []
          ];
        }

        if (!empty($row['id_medida'])) {
          $verMedidasAreas[$areaId]['medidas'][] = [
            'id_medida' => (int)$row['id_medida'],
            'descripcion' => (string)$row['descripcion']
          ];
        }
      }

      $stmtMedidas->close();
    }
  }
}

/* =========================================================
 * DATOS PARA edit_plan
 * ========================================================= */
$editPlan               = null;
$editPlanError          = '';
$editPlanMedidasByArea  = [];
$editAreasSeleccionadas = [];
$editAreasPlan          = [];
$editMedidasPorArea     = [];
$editEmpresas           = [];
$editPlanOld            = [];

if ($view === 'edit_plan') {
  $idEmpresa = (int)($_GET['id_empresa'] ?? 0);
  $editPlanError = (string)($_SESSION['edit_plan_error'] ?? '');
  $editPlanOld   = $_SESSION['edit_plan_old'] ?? [];
  unset($_SESSION['edit_plan_error'], $_SESSION['edit_plan_old']);

  if ($idEmpresa > 0) {
    $stmt = db()->prepare("
      SELECT
        e.id_empresa,
        e.razon_social,
        MIN(pc.inicio_plan) AS inicio_plan,
        MAX(pc.fin_plan) AS fin_plan
      FROM empresa e
      JOIN areas_contratadas pc ON pc.id_empresa = e.id_empresa
      WHERE e.id_empresa = ?
      GROUP BY e.id_empresa, e.razon_social
      LIMIT 1
    ");
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $editPlan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($editPlan !== null) {
      $stmtAreas = db()->prepare("SELECT id_plan FROM areas_contratadas WHERE id_empresa = ?");
      $stmtAreas->bind_param('i', $idEmpresa);
      $stmtAreas->execute();
      $resAreas = $stmtAreas->get_result();
      while ($row = $resAreas->fetch_assoc()) $editAreasSeleccionadas[] = (int)$row['id_plan'];
      $stmtAreas->close();

      $stmtCM = db()->prepare(" 
        SELECT pc.id_plan, cm.id_medida
        FROM areas_contratadas pc
        JOIN cliente_medida cm ON cm.id_areas_contratadas = pc.id_areas_contratadas
        WHERE pc.id_empresa = ?
      ");
      $stmtCM->bind_param('i', $idEmpresa);
      $stmtCM->execute();
      $resCM = $stmtCM->get_result();
      while ($row = $resCM->fetch_assoc()) {
        $aid = (int)$row['id_plan'];
        if (!isset($editPlanMedidasByArea[$aid])) $editPlanMedidasByArea[$aid] = [];
        $editPlanMedidasByArea[$aid][] = (int)$row['id_medida'];
      }
      $stmtCM->close();
    }
  }

  if (!empty($editPlanOld) && $editPlan !== null) {
    $editPlan['id_empresa']          = $editPlanOld['id_empresa']          ?? $editPlan['id_empresa'];
    $editPlan['inicio_plan']         = $editPlanOld['inicio_plan']         ?? $editPlan['inicio_plan'];
    $editPlan['fin_plan']            = $editPlanOld['fin_plan']            ?? $editPlan['fin_plan'];
    if (!empty($editPlanOld['areas']) && is_array($editPlanOld['areas'])) {
      $editAreasSeleccionadas = array_values(array_unique(array_map('intval', $editPlanOld['areas'])));
    }
    if (!empty($editPlanOld['medidas']) && is_array($editPlanOld['medidas'])) {
      $editPlanMedidasByArea = [];
      foreach ($editPlanOld['medidas'] as $areaId => $medidasArea) {
        $aid = (int)$areaId;
        if (!is_array($medidasArea)) $medidasArea = [];
        $editPlanMedidasByArea[$aid] = array_values(array_unique(array_map('intval', $medidasArea)));
      }
    }
  }

  $resEmpEdit = db()->query("SELECT id_empresa, razon_social FROM empresa ORDER BY razon_social");
  while ($e = $resEmpEdit->fetch_assoc()) $editEmpresas[] = $e;

  $resAreasEdit = db()->query("SELECT id_plan, nombre FROM area_plan ORDER BY nombre");
  while ($ar = $resAreasEdit->fetch_assoc()) {
    $aid = (int)$ar['id_plan'];
    $editAreasPlan[] = $ar;
    $editMedidasPorArea[$aid] = [];
  }
  $resMedEdit = db()->query("SELECT id_medida, descripcion, id_plan FROM medida ORDER BY id_plan, descripcion");
  while ($m = $resMedEdit->fetch_assoc()) {
    $aid = (int)$m['id_plan'];
    if (!isset($editMedidasPorArea[$aid])) $editMedidasPorArea[$aid] = [];
    $editMedidasPorArea[$aid][] = $m;
  }
}

require __DIR__ . '/../html/index_empresa.php';