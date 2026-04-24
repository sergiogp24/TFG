<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/helpers.php';
require __DIR__ . '/../config/config.php';

require_login();

function normalize_role(string $role): string
{
  $role = trim($role);
  if ($role === '') {
    return '';
  }

  if (function_exists('mb_strtoupper')) {
    $role = mb_strtoupper($role, 'UTF-8');
  } else {
    $role = strtoupper($role);
  }

  return strtr($role, [
    'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
    'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U',
  ]);
}

function load_assignment_alerts_from_cookie(int $userId): array
{
  $alertas = [];
  if ($userId <= 0) {
    return $alertas;
  }

  $stmt = db()->prepare("\n    SELECT ue.id_empresa, COALESCE(e.razon_social, '') AS razon_social\n    FROM usuario_empresa ue\n    LEFT JOIN empresa e ON e.id_empresa = ue.id_empresa\n    WHERE ue.id_usuario = ?\n    ORDER BY ue.id_empresa ASC\n  ");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();

  $currentIds = [];
  $namesById = [];
  while ($row = $res->fetch_assoc()) {
    $idEmpresa = (int)($row['id_empresa'] ?? 0);
    if ($idEmpresa <= 0) {
      continue;
    }
    $currentIds[] = $idEmpresa;
    $namesById[$idEmpresa] = trim((string)($row['razon_social'] ?? ''));
  }
  $stmt->close();

  $cookieName = 'asig_empresas_' . $userId;
  $prevIds = [];
  $hadCookie = isset($_COOKIE[$cookieName]);
  if (isset($_COOKIE[$cookieName])) {
    $decoded = json_decode((string)$_COOKIE[$cookieName], true);
    if (is_array($decoded)) {
      foreach ($decoded as $prevId) {
        $prevInt = (int)$prevId;
        if ($prevInt > 0) {
          $prevIds[] = $prevInt;
        }
      }
    }
  }

  $prevIds = array_values(array_unique($prevIds));
  $currentIds = array_values(array_unique($currentIds));

  setcookie($cookieName, json_encode($currentIds), time() + (86400 * 180), '/');

  return $alertas;
}

function company_scope_where(string $where, string $companyExpr, bool $isTecnico): string
{
  if (!$isTecnico) {
    return $where;
  }

  $scope = "(EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = {$companyExpr} AND ue.id_usuario = ?)"
    . " OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = {$companyExpr} AND ce.id_usuario = ?)"
    . " OR EXISTS (SELECT 1 FROM empresa es WHERE es.id_empresa = {$companyExpr} AND es.id_usuario = ?))";

  return ($where === '') ? "WHERE $scope" : $where . ' AND ' . $scope;
}

function tabla_cnae_disponible_model(): bool
{
  static $checked = false;
  static $exists = false;

  if ($checked) {
    return $exists;
  }

  $checked = true;
  $res = db()->query("SHOW TABLES LIKE 'cnae'");
  $exists = ($res instanceof mysqli_result && $res->num_rows > 0);

  return $exists;
}

function obtener_cnaes_empresa(int $idEmpresa): array
{
  if ($idEmpresa <= 0 || !tabla_cnae_disponible_model()) {
    return [];
  }

  $stmt = db()->prepare('SELECT nombre FROM cnae WHERE id_empresa = ? ORDER BY id ASC');
  if (!$stmt) {
    return [];
  }

  $stmt->bind_param('i', $idEmpresa);
  $stmt->execute();
  $res = $stmt->get_result();

  $result = [];
  while ($row = $res->fetch_assoc()) {
    $nombre = trim((string)($row['nombre'] ?? ''));
    if ($nombre !== '') {
      $result[] = $nombre;
    }
  }

  $stmt->close();
  return $result;
}

function empresa_tiene_columna_cnae(): bool
{
  static $checked = false;
  static $exists = false;

  if ($checked) {
    return $exists;
  }

  $checked = true;
  $res = db()->query("SHOW COLUMNS FROM empresa LIKE 'cnae'");
  $exists = ($res instanceof mysqli_result && $res->num_rows > 0);

  return $exists;
}

function obtener_cnae_legacy_empresa(int $idEmpresa): string
{
  if ($idEmpresa <= 0 || !empresa_tiene_columna_cnae()) {
    return '';
  }

  $stmt = db()->prepare('SELECT cnae FROM empresa WHERE id_empresa = ? LIMIT 1');
  if (!$stmt) {
    return '';
  }

  $stmt->bind_param('i', $idEmpresa);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return trim((string)($row['cnae'] ?? ''));
}

$rol = normalize_role((string)($_SESSION['user']['rol'] ?? ''));
$esAdministrador = ($rol === 'ADMINISTRADOR');
$esTecnico = ($rol === 'TECNICO');
$currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);

if (!$esAdministrador && !$esTecnico) {
  http_response_code(403);
  exit('Acceso denegado');
}

// Views SOLO de EMPRESAS
$view = (string)($_GET['view'] ?? 'ver_empresas');
$viewAliases = [
  'mis_empresas' => 'ver_empresas',
  'directorio_de_empresas' => 'ver_empresas',
  'ver_servicios_aceptados' => 'ver_contratos',
  'add_servicios_aceptados' => 'add_contratos',
  'edit_servicios_aceptados' => 'edit_contratos',
  'delete_servicios_aceptados' => 'delete_contratos',
];
if (isset($viewAliases[$view])) {
  $view = $viewAliases[$view];
}
$allowed = $esAdministrador
  ? ['ver_empresas', 'ver_empresa', 'add_empresas', 'edit_empresas', 'delete_empresas', 'ver_planes', 'ver_medidas', 'edit_plan', 'ver_contratos', 'add_contratos', 'edit_contratos', 'delete_contratos']
  : ['ver_empresas', 'ver_empresa', 'edit_empresas', 'ver_planes', 'ver_medidas', 'ver_contratos', 'add_contratos', 'edit_contratos', 'delete_contratos'];
if (!in_array($view, $allowed, true)) $view = 'ver_empresas';

$idEmpresaContexto = (int)($_GET['id_empresa'] ?? 0);
$empresaContexto = null;
if ($idEmpresaContexto > 0) {
  $stmtEmpresaContexto = db()->prepare(" 
    SELECT id_empresa, razon_social
    FROM empresa
    WHERE id_empresa = ?
    " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = empresa.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = empresa.id_empresa AND ce.id_usuario = ?) OR empresa.id_usuario = ?)" : "") . "
    LIMIT 1
  ");
  if ($esTecnico) {
    $stmtEmpresaContexto->bind_param('iiii', $idEmpresaContexto, $currentUserId, $currentUserId, $currentUserId);
  } else {
    $stmtEmpresaContexto->bind_param('i', $idEmpresaContexto);
  }
  $stmtEmpresaContexto->execute();
  $empresaContexto = $stmtEmpresaContexto->get_result()->fetch_assoc();
  $stmtEmpresaContexto->close();
}

// Datos de sesión (para sidebar/header si lo compartes)
$adminUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'admin');

$adminEmail = '';
if ($currentUserId > 0) {
  $stmt = db()->prepare("SELECT email FROM usuario WHERE id_usuario = ? LIMIT 1");
  $stmt->bind_param('i', $currentUserId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $adminEmail = (string)($row['email'] ?? '');
}

$alertasAsignacion = [];
if ($esTecnico && $currentUserId > 0) {
  $alertasAsignacion = load_assignment_alerts_from_cookie($currentUserId);
}

$empresas = [];
$searchQ = trim((string)($_GET['q'] ?? ''));
$currentPage = (int)($_GET['page'] ?? 1);
if ($currentPage < 1) $currentPage = 1;

$perPage = 10;
$offset = ($currentPage - 1) * $perPage;
$totalEmpresas = 0;
$totalPages = 1;

/* =========================================================
 * EMPRESAS: búsqueda + paginación (10 por página)
 * (solo para ver_empresas / delete_empresas)
 * ========================================================= */

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

  $where = company_scope_where($where, 'e.id_empresa', $esTecnico);
  if ($esTecnico) {
    $params[] = $currentUserId;
    $params[] = $currentUserId;
    $params[] = $currentUserId;
    $types .= 'iii';
  }

  // Total
  $sqlTotal = "
    SELECT COUNT(*) AS total
    FROM empresa e
    $where
  ";
  $stmt = db()->prepare($sqlTotal);
  if (!empty($params)) $stmt->bind_param($types, ...$params);
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

  if (!empty($params)) {
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
 * DATOS PARA ver_empresa (detalle de una empresa)
 * ========================================================= */
$detalleEmpresa = null;
$detalleEmpresaAreas = [];
$detalleTecnicos = [];

if ($view === 'ver_empresa') {
  $idEmpresaDetalle = (int)($_GET['id_empresa'] ?? 0);

  if ($idEmpresaDetalle > 0) {
    $stmtEmpresaDetalle = db()->prepare(" 
      SELECT
        id_empresa,
        razon_social,
        nif,
        responsable,
        sector,
        email,
        telefono
      FROM empresa
      WHERE id_empresa = ?
      " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = empresa.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = empresa.id_empresa AND ce.id_usuario = ?) OR empresa.id_usuario = ?)" : "") . "
      LIMIT 1
    ");
    if ($esTecnico) {
      $stmtEmpresaDetalle->bind_param('iiii', $idEmpresaDetalle, $currentUserId, $currentUserId, $currentUserId);
    } else {
      $stmtEmpresaDetalle->bind_param('i', $idEmpresaDetalle);
    }
    $stmtEmpresaDetalle->execute();
    $detalleEmpresa = $stmtEmpresaDetalle->get_result()->fetch_assoc();
    $stmtEmpresaDetalle->close();

    if ($detalleEmpresa !== null) {
      $cnaesDetalle = obtener_cnaes_empresa((int)$detalleEmpresa['id_empresa']);
      if (!empty($cnaesDetalle)) {
        $detalleEmpresa['cnae_list'] = $cnaesDetalle;
      } else {
        $cnaeLegacy = obtener_cnae_legacy_empresa((int)$detalleEmpresa['id_empresa']);
        if ($cnaeLegacy !== '') {
          $detalleEmpresa['cnae_list'] = [$cnaeLegacy];
        }
      }

      $servicioEmpresaDetalle = 'Sin contrato';
      $stmtServicioEmpresa = db()->prepare("SELECT tipo_contrato FROM contrato_empresa WHERE id_empresa = ? ORDER BY id_contrato_empresa DESC LIMIT 1");
      if ($stmtServicioEmpresa) {
        $stmtServicioEmpresa->bind_param('i', $idEmpresaDetalle);
        $stmtServicioEmpresa->execute();
        $filaServicioEmpresa = $stmtServicioEmpresa->get_result()->fetch_assoc();
        $stmtServicioEmpresa->close();

        $servicioEmpresaDetalle = trim((string)($filaServicioEmpresa['tipo_contrato'] ?? ''));
        if ($servicioEmpresaDetalle === '') {
          $servicioEmpresaDetalle = 'Sin contrato';
        }
      }

      $tecnicosById = [];

      // Prioridad: técnicos asignados por servicio (contrato_empresa.id_usuario)
      $stmtTecnicosContrato = db()->prepare(" 
        SELECT DISTINCT
          u.id_usuario,
          u.nombre_usuario,
          u.email
        FROM contrato_empresa ce
        INNER JOIN usuario u ON u.id_usuario = ce.id_usuario
        WHERE ce.id_empresa = ?
          AND ce.id_usuario IS NOT NULL
        ORDER BY u.nombre_usuario ASC
      ");
      $stmtTecnicosContrato->bind_param('i', $idEmpresaDetalle);
      $stmtTecnicosContrato->execute();
      $resTecnicosContrato = $stmtTecnicosContrato->get_result();
      while ($rowTecnico = $resTecnicosContrato->fetch_assoc()) {
        $idTecnico = (int)($rowTecnico['id_usuario'] ?? 0);
        if ($idTecnico <= 0) {
          continue;
        }
        $tecnicosById[$idTecnico] = [
          'id_usuario' => $idTecnico,
          'nombre_usuario' => (string)($rowTecnico['nombre_usuario'] ?? ''),
          'email' => (string)($rowTecnico['email'] ?? ''),
          'empresa_asignada' => (string)($detalleEmpresa['razon_social'] ?? ''),
          'servicio_asignado' => $servicioEmpresaDetalle,
        ];
      }
      $stmtTecnicosContrato->close();

      // Compatibilidad: técnicos asignados vía usuario_empresa
      $stmtTecnicosUE = db()->prepare(" 
        SELECT DISTINCT
          u.id_usuario,
          u.nombre_usuario,
          u.email
        FROM usuario_empresa ue
        INNER JOIN usuario u ON u.id_usuario = ue.id_usuario
        INNER JOIN rol r ON r.id = u.rol_id
        WHERE ue.id_empresa = ?
          AND UPPER(TRIM(COALESCE(r.nombre, ''))) IN ('TECNICO', 'TÉCNICO')
        ORDER BY u.nombre_usuario ASC
      ");
      $stmtTecnicosUE->bind_param('i', $idEmpresaDetalle);
      $stmtTecnicosUE->execute();
      $resTecnicosUE = $stmtTecnicosUE->get_result();
      while ($rowTecnico = $resTecnicosUE->fetch_assoc()) {
        $idTecnico = (int)($rowTecnico['id_usuario'] ?? 0);
        if ($idTecnico <= 0 || isset($tecnicosById[$idTecnico])) {
          continue;
        }
        $tecnicosById[$idTecnico] = [
          'id_usuario' => $idTecnico,
          'nombre_usuario' => (string)($rowTecnico['nombre_usuario'] ?? ''),
          'email' => (string)($rowTecnico['email'] ?? ''),
          'empresa_asignada' => (string)($detalleEmpresa['razon_social'] ?? ''),
          'servicio_asignado' => $servicioEmpresaDetalle,
        ];
      }
      $stmtTecnicosUE->close();

      // Último fallback legacy: empresa.id_usuario
      if (empty($tecnicosById)) {
        $stmtDetalleUsuarioLegacy = db()->prepare(" 
          SELECT
            u.id_usuario,
            u.nombre_usuario,
            u.email
          FROM empresa e
          LEFT JOIN usuario u ON u.id_usuario = e.id_usuario
          WHERE e.id_empresa = ?
          " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = e.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = e.id_empresa AND ce.id_usuario = ?) OR e.id_usuario = ?)" : "") . "
          LIMIT 1
        ");
        if ($esTecnico) {
          $stmtDetalleUsuarioLegacy->bind_param('iiii', $idEmpresaDetalle, $currentUserId, $currentUserId, $currentUserId);
        } else {
          $stmtDetalleUsuarioLegacy->bind_param('i', $idEmpresaDetalle);
        }
        $stmtDetalleUsuarioLegacy->execute();
        $detalleUsuarioLegacy = $stmtDetalleUsuarioLegacy->get_result()->fetch_assoc();
        $stmtDetalleUsuarioLegacy->close();

        $idTecnicoLegacy = (int)($detalleUsuarioLegacy['id_usuario'] ?? 0);
        if ($idTecnicoLegacy > 0) {
          $tecnicosById[$idTecnicoLegacy] = [
            'id_usuario' => $idTecnicoLegacy,
            'nombre_usuario' => (string)($detalleUsuarioLegacy['nombre_usuario'] ?? ''),
            'email' => (string)($detalleUsuarioLegacy['email'] ?? ''),
          ];
        }
      }

      $detalleTecnicos = array_values($tecnicosById);

      $stmtDetalleMedidas = db()->prepare(" 
        SELECT DISTINCT
          ap.id_plan,
          ap.nombre AS area_nombre,
          m.id_medida,
          m.descripcion
        FROM areas_contratadas ac
        JOIN area_plan ap ON ap.id_plan = ac.id_plan
        LEFT JOIN cliente_medida cm ON cm.id_areas_contratadas = ac.id_areas_contratadas
        LEFT JOIN medida m ON m.id_medida = cm.id_medida
        WHERE ac.id_empresa = ?
        " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = ac.id_empresa AND ce.id_usuario = ?) OR EXISTS (SELECT 1 FROM empresa eac WHERE eac.id_empresa = ac.id_empresa AND eac.id_usuario = ?))" : "") . "
        ORDER BY ap.nombre ASC, m.descripcion ASC
      ");
      if ($esTecnico) {
        $stmtDetalleMedidas->bind_param('iiii', $idEmpresaDetalle, $currentUserId, $currentUserId, $currentUserId);
      } else {
        $stmtDetalleMedidas->bind_param('i', $idEmpresaDetalle);
      }
      $stmtDetalleMedidas->execute();
      $resDetalleMedidas = $stmtDetalleMedidas->get_result();

      while ($row = $resDetalleMedidas->fetch_assoc()) {
        $areaId = (int)$row['id_plan'];
        if (!isset($detalleEmpresaAreas[$areaId])) {
          $detalleEmpresaAreas[$areaId] = [
            'nombre' => (string)$row['area_nombre'],
            'medidas' => []
          ];
        }

        if (!empty($row['id_medida'])) {
          $detalleEmpresaAreas[$areaId]['medidas'][] = [
            'id_medida' => (int)$row['id_medida'],
            'descripcion' => (string)$row['descripcion']
          ];
        }
      }

      $stmtDetalleMedidas->close();
    }
  }
}

/* =========================================================
 * Empresa seleccionada (para edit_empresas)
 * ========================================================= */
$selectedEmpresa = null;
$tecnicosAsignadosEmpresa = [];
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
      " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = empresa.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = empresa.id_empresa AND ce.id_usuario = ?) OR empresa.id_usuario = ?)" : "") . "
      LIMIT 1
    ");
    if ($esTecnico) {
      $stmt->bind_param('iiii', $idEmpresa, $currentUserId, $currentUserId, $currentUserId);
    } else {
      $stmt->bind_param('i', $idEmpresa);
    }
    $stmt->execute();
    $selectedEmpresa = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedEmpresa !== null) {
      $cnaesEmpresa = obtener_cnaes_empresa((int)$selectedEmpresa['id_empresa']);
      if (!empty($cnaesEmpresa)) {
        $selectedEmpresa['cnae_list'] = $cnaesEmpresa;
        $selectedEmpresa['cnae'] = implode("\n", $cnaesEmpresa);
      } else {
        $cnaeLegacy = obtener_cnae_legacy_empresa((int)$selectedEmpresa['id_empresa']);
        if ($cnaeLegacy !== '') {
          $selectedEmpresa['cnae_list'] = [$cnaeLegacy];
          $selectedEmpresa['cnae'] = $cnaeLegacy;
        }
      }
    }

    if ($selectedEmpresa !== null && !$esTecnico) {
      $stmtTecnicoAsignado = db()->prepare("\n        SELECT u.id_usuario, COALESCE(r.nombre, '') AS rol_nombre\n        FROM usuario_empresa ue\n        INNER JOIN usuario u ON u.id_usuario = ue.id_usuario\n        LEFT JOIN rol r ON r.id = u.rol_id\n        WHERE ue.id_empresa = ?\n        ORDER BY ue.id_usuario ASC\n      ");
      $stmtTecnicoAsignado->bind_param('i', $idEmpresa);
      $stmtTecnicoAsignado->execute();
      $resTecnicoAsignado = $stmtTecnicoAsignado->get_result();

      while ($rowTecnicoAsignado = $resTecnicoAsignado->fetch_assoc()) {
        $rolTecnicoAsignado = normalize_role((string)($rowTecnicoAsignado['rol_nombre'] ?? ''));
        if ($rolTecnicoAsignado !== 'TECNICO') {
          continue;
        }

        $idTecnicoAsignado = (int)($rowTecnicoAsignado['id_usuario'] ?? 0);
        if ($idTecnicoAsignado > 0) {
          $tecnicosAsignadosEmpresa[] = $idTecnicoAsignado;
        }
      }

      $stmtTecnicoAsignado->close();

      if (!empty($tecnicosAsignadosEmpresa)) {
        $selectedEmpresa['id_usuario'] = (int)$tecnicosAsignadosEmpresa[0];
      }
    }
  }
}

$tecnicosDisponibles = [];
if (!$esTecnico && in_array($view, ['add_empresas', 'edit_empresas', 'add_contratos', 'edit_contratos'], true)) {
  $stmtTecnicos = db()->prepare("\n    SELECT u.id_usuario, u.nombre_usuario, u.email, COALESCE(r.nombre, '') AS rol_nombre\n    FROM usuario u\n    LEFT JOIN rol r ON r.id = u.rol_id\n    ORDER BY u.nombre_usuario ASC\n  ");
  $stmtTecnicos->execute();
  $resTecnicos = $stmtTecnicos->get_result();

  while ($rowTecnico = $resTecnicos->fetch_assoc()) {
    $rolUsuario = normalize_role((string)($rowTecnico['rol_nombre'] ?? ''));
    if ($rolUsuario !== 'TECNICO') {
      continue;
    }

    $tecnicosDisponibles[] = [
      'id_usuario' => (int)($rowTecnico['id_usuario'] ?? 0),
      'nombre_usuario' => (string)($rowTecnico['nombre_usuario'] ?? ''),
      'email' => (string)($rowTecnico['email'] ?? ''),
    ];
  }

  $stmtTecnicos->close();
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

$editContratoOld = $_SESSION['edit_contrato_old'] ?? [];
$editContratoError = (string)($_SESSION['edit_contrato_error'] ?? '');
unset($_SESSION['edit_contrato_old'], $_SESSION['edit_contrato_error']);

/* =========================================================
 * Contrato seleccionado (para edit_contratos)
 * ========================================================= */
$selectedContrato = null;
if ($view === 'edit_contratos' && $tablaContratoExiste) {
    $idContrato = (int)($_GET['id_contrato'] ?? 0);
    if ($idContrato > 0) {

        $stmt = $db->prepare(
          "SELECT
            c.id_contrato_empresa,
            c.tipo_contrato,
            c.inicio_contratacion,
            c.fin_contratacion,
            c.id_empresa,
            c.id_usuario,
            tu.nombre_usuario AS tecnico_nombre,
            e.razon_social AS empresa_nombre
          FROM contrato_empresa c
          LEFT JOIN empresa e ON e.id_empresa = c.id_empresa
          LEFT JOIN usuario tu ON tu.id_usuario = c.id_usuario
          WHERE c.id_contrato_empresa = ?
          LIMIT 1"
        );
        $stmt->bind_param('i', $idContrato);
        $stmt->execute();
        $selectedContrato = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Calcular fechas de vigencia desde las áreas contratadas
        $selectedContrato['inicio_plan'] = '';
        $selectedContrato['fin_plan'] = '';
        $fechas_inicio = [];
        $fechas_fin = [];
        $stmtAreasFechas = $db->prepare("SELECT inicio_plan, fin_plan FROM areas_contratadas WHERE id_empresa = ?");
        $stmtAreasFechas->bind_param('i', $selectedContrato['id_empresa']);
        $stmtAreasFechas->execute();
        $resAreasFechas = $stmtAreasFechas->get_result();
        while ($row = $resAreasFechas->fetch_assoc()) {
          if (!empty($row['inicio_plan'])) $fechas_inicio[] = $row['inicio_plan'];
          if (!empty($row['fin_plan'])) $fechas_fin[] = $row['fin_plan'];
        }
        $stmtAreasFechas->close();
        if (!empty($fechas_inicio)) $selectedContrato['inicio_plan'] = min($fechas_inicio);
        if (!empty($fechas_fin)) $selectedContrato['fin_plan'] = max($fechas_fin);

        // Cargar áreas y medidas seleccionadas para este contrato usando el modelo real
        $selectedContrato['areas'] = [];
        $selectedContrato['medidas'] = [];
        $selectedContrato['medidas_personalizadas'] = [];
        // Áreas seleccionadas (areas_contratadas)
        $stmtAreas = $db->prepare("SELECT id_areas_contratadas, id_plan FROM areas_contratadas WHERE id_empresa = ?");
        $stmtAreas->bind_param('i', $selectedContrato['id_empresa']);
        $stmtAreas->execute();
        $resAreas = $stmtAreas->get_result();
        $areas_contratadas_ids = [];
        while ($row = $resAreas->fetch_assoc()) {
            $aid = (int)$row['id_plan'];
            $selectedContrato['areas'][] = $aid;
            $areas_contratadas_ids[$aid] = (int)$row['id_areas_contratadas'];
        }
        $stmtAreas->close();
        // Medidas seleccionadas por área (cliente_medida)
        foreach ($areas_contratadas_ids as $aid => $id_areas_contratadas) {
            $stmtMedidas = $db->prepare("SELECT id_medida FROM cliente_medida WHERE id_areas_contratadas = ?");
            $stmtMedidas->bind_param('i', $id_areas_contratadas);
            $stmtMedidas->execute();
            $resMedidas = $stmtMedidas->get_result();
            while ($row = $resMedidas->fetch_assoc()) {
                $mid = (int)$row['id_medida'];
                if (!isset($selectedContrato['medidas'][$aid])) $selectedContrato['medidas'][$aid] = [];
                $selectedContrato['medidas'][$aid][] = $mid;
            }
            $stmtMedidas->close();
        }
        // Medidas personalizadas: no implementado en modelo, dejar vacío

        if ($selectedContrato !== null && !empty($editContratoOld)) {
            $selectedContrato['id_contrato_empresa'] = $editContratoOld['id_contrato_empresa'] ?? $selectedContrato['id_contrato_empresa'];
            $selectedContrato['id_empresa'] = $editContratoOld['id_empresa'] ?? $selectedContrato['id_empresa'];
          $selectedContrato['id_usuario'] = $editContratoOld['id_usuario'] ?? $selectedContrato['id_usuario'];
            $selectedContrato['tipo_contrato'] = $editContratoOld['tipo_contrato'] ?? $selectedContrato['tipo_contrato'];
            $selectedContrato['inicio_plan'] = $editContratoOld['inicio_plan'] ?? $selectedContrato['inicio_plan'];
            $selectedContrato['fin_plan'] = $editContratoOld['fin_plan'] ?? $selectedContrato['fin_plan'];
            $selectedContrato['inicio_contratacion'] = $editContratoOld['inicio_contratacion'] ?? $selectedContrato['inicio_contratacion'];
            $selectedContrato['fin_contratacion'] = $editContratoOld['fin_contratacion'] ?? $selectedContrato['fin_contratacion'];

            if (isset($editContratoOld['areas']) && is_array($editContratoOld['areas'])) {
              $selectedContrato['areas'] = array_values(array_unique(array_map('intval', $editContratoOld['areas'])));
            }

            if (isset($editContratoOld['medidas']) && is_array($editContratoOld['medidas'])) {
              $selectedContrato['medidas'] = [];
              foreach ($editContratoOld['medidas'] as $areaId => $medidasArea) {
                $aid = (int)$areaId;
                if (!is_array($medidasArea)) {
                  $medidasArea = [];
                }
                $selectedContrato['medidas'][$aid] = array_values(array_unique(array_map('intval', $medidasArea)));
              }
            }

            if (isset($editContratoOld['medidas_personalizadas']) && is_array($editContratoOld['medidas_personalizadas'])) {
              $selectedContrato['medidas_personalizadas'] = [];
              foreach ($editContratoOld['medidas_personalizadas'] as $areaId => $textoMedida) {
                $aid = (int)$areaId;
                $selectedContrato['medidas_personalizadas'][$aid] = trim((string)$textoMedida);
              }
            }
        }
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

$tiposContrato = ['PLAN IGUALDAD', 'MANTENIMIENTO'];
$tipoContratoFiltro = strtoupper(trim((string)($_GET['tipo_contrato'] ?? '')));
if (!in_array($tipoContratoFiltro, $tiposContrato, true)) {
  $tipoContratoFiltro = '';
}
$tipoContratoForzadoAdd = '';
$empresasForContrato = [];
$areasPlanContrato = [];
$medidasPorAreaContrato = [];
$addContratoOld = $_SESSION['add_contrato_old'] ?? [
  'id_empresa' => 0,
  'id_usuario' => 0,
  'tipo_contrato' => 'PLAN IGUALDAD',
  'inicio_plan' => '',
  'fin_plan' => '',
  'inicio_contratacion' => '',
  'fin_contratacion' => '',
  'areas' => [],
  'medidas' => [],
];
$contratoError = (string)($_SESSION['add_contrato_error'] ?? '');
unset($_SESSION['add_contrato_old'], $_SESSION['add_contrato_error']);

if ($view === 'add_contratos' && $idEmpresaContexto > 0 && empty($addContratoOld['id_empresa'])) {
  $addContratoOld['id_empresa'] = $idEmpresaContexto;
}

if ($view === 'add_contratos' && $tipoContratoFiltro !== '') {
  $tipoContratoForzadoAdd = $tipoContratoFiltro;
  $addContratoOld['tipo_contrato'] = $tipoContratoFiltro;
}

if ($view === 'add_contratos') {
  $sqlEmpresasContrato = "SELECT id_empresa, razon_social FROM empresa" . ($esTecnico ? " WHERE (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = empresa.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = empresa.id_empresa AND ce.id_usuario = ?) OR empresa.id_usuario = ?)" : "") . " ORDER BY razon_social";
  $stmtEmp = $db->prepare($sqlEmpresasContrato);
  if ($esTecnico) {
    $stmtEmp->bind_param('iii', $currentUserId, $currentUserId, $currentUserId);
  }
  $stmtEmp->execute();
  $resEmp = $stmtEmp->get_result();
  while ($e = $resEmp->fetch_assoc()) $empresasForContrato[] = $e;
  $stmtEmp->close();

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

// --- SI ESTÁS EN EDITAR CONTRATO, CARGA TODAS LAS ÁREAS Y MEDIDAS IGUAL QUE EN ALTA ---
if ($view === 'edit_contratos') {
  $sqlEmpresasContrato = "SELECT id_empresa, razon_social FROM empresa" . ($esTecnico ? " WHERE (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = empresa.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = empresa.id_empresa AND ce.id_usuario = ?) OR empresa.id_usuario = ?)" : "") . " ORDER BY razon_social";
  $stmtEmp = $db->prepare($sqlEmpresasContrato);
  if ($esTecnico) {
    $stmtEmp->bind_param('iii', $currentUserId, $currentUserId, $currentUserId);
  }
  $stmtEmp->execute();
  $resEmp = $stmtEmp->get_result();
  while ($e = $resEmp->fetch_assoc()) $empresasForContrato[] = $e;
  $stmtEmp->close();

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

  if ($idEmpresaContexto > 0) {
    $whereContratos = ($whereContratos === '') ? 'WHERE e.id_empresa = ?' : $whereContratos . ' AND e.id_empresa = ?';
    $paramsCon[] = $idEmpresaContexto;
    $typesCon .= 'i';
  }

  if ($tipoContratoFiltro !== '') {
    $whereContratos = ($whereContratos === '') ? 'WHERE UPPER(TRIM(c.tipo_contrato)) LIKE ?' : $whereContratos . ' AND UPPER(TRIM(c.tipo_contrato)) LIKE ?';
    $paramsCon[] = $tipoContratoFiltro . '%';
    $typesCon .= 's';
  }

  $whereContratos = company_scope_where($whereContratos, 'e.id_empresa', $esTecnico);
  if ($esTecnico) {
    $paramsCon[] = $currentUserId;
    $paramsCon[] = $currentUserId;
    $paramsCon[] = $currentUserId;
    $typesCon .= 'iii';
  }

  $sqlTotalContratos = "
    SELECT COUNT(*) AS total
    FROM contrato_empresa c
    JOIN empresa e ON e.id_empresa = c.id_empresa
    $whereContratos
  ";
  $stmtTotalCon = $db->prepare($sqlTotalContratos);
  if (!empty($paramsCon)) $stmtTotalCon->bind_param($typesCon, ...$paramsCon);
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
      c.id_usuario,
      tu.nombre_usuario AS tecnico_nombre,
      e.razon_social
    FROM contrato_empresa c
    JOIN empresa e ON e.id_empresa = c.id_empresa
    LEFT JOIN usuario tu ON tu.id_usuario = c.id_usuario
    $whereContratos
    ORDER BY c.id_contrato_empresa DESC
    LIMIT ? OFFSET ?
  ";

  $stmtCon = $db->prepare($sqlContratos);
  $typesConData = $typesCon . 'ii';
  $paramsConData = array_merge($paramsCon, [$perPageContratos, $offsetContratos]);
  $stmtCon->bind_param($typesConData, ...$paramsConData);

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

  if ($idEmpresaContexto > 0) {
    $wherePlanes = ($wherePlanes === '') ? 'WHERE e.id_empresa = ?' : $wherePlanes . ' AND e.id_empresa = ?';
    $paramsPla[] = $idEmpresaContexto;
    $typesPla .= 'i';
  }

  $wherePlanes = company_scope_where($wherePlanes, 'e.id_empresa', $esTecnico);
  if ($esTecnico) {
    $paramsPla[] = $currentUserId;
    $paramsPla[] = $currentUserId;
    $paramsPla[] = $currentUserId;
    $typesPla .= 'iii';
  }

  $planIgualdadCondition = "EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = e.id_empresa AND UPPER(TRIM(ce.tipo_contrato)) = 'PLAN IGUALDAD')";
  $wherePlanes = ($wherePlanes === '') ? ('WHERE ' . $planIgualdadCondition) : ($wherePlanes . ' AND ' . $planIgualdadCondition);

  // Total de planes
 $sqlTotalPlanes = "
    SELECT COUNT(DISTINCT e.id_empresa) AS total
    FROM empresa e
    LEFT JOIN contrato_empresa ce_plan ON ce_plan.id_empresa = e.id_empresa AND UPPER(TRIM(ce_plan.tipo_contrato)) = 'PLAN IGUALDAD'
    $wherePlanes
  ";
  $stmtTotal = db()->prepare($sqlTotalPlanes);
  if (!empty($paramsPla)) $stmtTotal->bind_param($typesPla, ...$paramsPla);
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
      COALESCE(MAX(TRIM(ce_plan.tipo_contrato)), 'PLAN IGUALDAD') AS tipo_contrato,
      MIN(ce_plan.inicio_contratacion) AS inicio_plan,
      MAX(ce_plan.fin_contratacion) AS fin_plan,
      MAX(ce_plan.id_contrato_empresa) AS id_contrato_empresa
    FROM empresa e
    LEFT JOIN contrato_empresa ce_plan
      ON ce_plan.id_empresa = e.id_empresa
      AND UPPER(TRIM(ce_plan.tipo_contrato)) = 'PLAN IGUALDAD'
    $wherePlanes
    GROUP BY e.id_empresa, e.razon_social
    ORDER BY e.razon_social ASC
    LIMIT ? OFFSET ?
  ";

  $stmtData = db()->prepare($sqlDataPlanes);
  $typesFinal = $typesPla . 'ii';
  $paramsFinal = array_merge($paramsPla, [$perPagePlanes, $offsetPlanes]);
  $stmtData->bind_param($typesFinal, ...$paramsFinal);

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
      " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = e.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = e.id_empresa AND ce.id_usuario = ?) OR e.id_usuario = ?)" : "") . "
      GROUP BY e.id_empresa, e.razon_social
      LIMIT 1
    ");
    if ($esTecnico) {
      $stmtPlan->bind_param('iiii', $idEmpresaMedidas, $currentUserId, $currentUserId, $currentUserId);
    } else {
      $stmtPlan->bind_param('i', $idEmpresaMedidas);
    }
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
        " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = pc.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = pc.id_empresa AND ce.id_usuario = ?) OR EXISTS (SELECT 1 FROM empresa epc WHERE epc.id_empresa = pc.id_empresa AND epc.id_usuario = ?))" : "") . "
        ORDER BY ap.nombre ASC, m.descripcion ASC
      ");
      if ($esTecnico) {
        $stmtMedidas->bind_param('iiii', $idEmpresaMedidas, $currentUserId, $currentUserId, $currentUserId);
      } else {
        $stmtMedidas->bind_param('i', $idEmpresaMedidas);
      }
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
      " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = e.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = e.id_empresa AND ce.id_usuario = ?) OR e.id_usuario = ?)" : "") . "
      GROUP BY e.id_empresa, e.razon_social
      LIMIT 1
    ");
    if ($esTecnico) {
      $stmt->bind_param('iiii', $idEmpresa, $currentUserId, $currentUserId, $currentUserId);
    } else {
      $stmt->bind_param('i', $idEmpresa);
    }
    $stmt->execute();
    $editPlan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($editPlan !== null) {
      $stmtAreas = db()->prepare("SELECT id_plan FROM areas_contratadas WHERE id_empresa = ?" . ($esTecnico ? " AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = areas_contratadas.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = areas_contratadas.id_empresa AND ce.id_usuario = ?) OR EXISTS (SELECT 1 FROM empresa ea WHERE ea.id_empresa = areas_contratadas.id_empresa AND ea.id_usuario = ?))" : ""));
      if ($esTecnico) {
        $stmtAreas->bind_param('iiii', $idEmpresa, $currentUserId, $currentUserId, $currentUserId);
      } else {
        $stmtAreas->bind_param('i', $idEmpresa);
      }
      $stmtAreas->execute();
      $resAreas = $stmtAreas->get_result();
      while ($row = $resAreas->fetch_assoc()) $editAreasSeleccionadas[] = (int)$row['id_plan'];
      $stmtAreas->close();

      $stmtCM = db()->prepare(" 
        SELECT pc.id_plan, cm.id_medida
        FROM areas_contratadas pc
        JOIN cliente_medida cm ON cm.id_areas_contratadas = pc.id_areas_contratadas
        WHERE pc.id_empresa = ?
        " . ($esTecnico ? "AND (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = pc.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = pc.id_empresa AND ce.id_usuario = ?) OR EXISTS (SELECT 1 FROM empresa epc2 WHERE epc2.id_empresa = pc.id_empresa AND epc2.id_usuario = ?))" : "") . "
      ");
      if ($esTecnico) {
        $stmtCM->bind_param('iiii', $idEmpresa, $currentUserId, $currentUserId, $currentUserId);
      } else {
        $stmtCM->bind_param('i', $idEmpresa);
      }
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

  $sqlEmpEdit = "SELECT id_empresa, razon_social FROM empresa" . ($esTecnico ? " WHERE (EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = empresa.id_empresa AND ue.id_usuario = ?) OR EXISTS (SELECT 1 FROM contrato_empresa ce WHERE ce.id_empresa = empresa.id_empresa AND ce.id_usuario = ?) OR empresa.id_usuario = ?)" : "") . " ORDER BY razon_social";
  $stmtEmpEdit = db()->prepare($sqlEmpEdit);
  if ($esTecnico) {
    $stmtEmpEdit->bind_param('iii', $currentUserId, $currentUserId, $currentUserId);
  }
  $stmtEmpEdit->execute();
  $resEmpEdit = $stmtEmpEdit->get_result();
  while ($e = $resEmpEdit->fetch_assoc()) $editEmpresas[] = $e;
  $stmtEmpEdit->close();

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