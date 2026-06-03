<?php
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=UTF-8');

function jsonResp(bool $ok, ?string $error = null): never
{
    ob_end_clean();
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResp(false, 'Método no permitido.');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user']))              jsonResp(false, 'No autenticado.');

require_once __DIR__ . '/../config/config.php';

$accion    = trim((string)($_POST['accion']    ?? ''));
$idEmpresa = (int)($_POST['id_empresa'] ?? 0);

if ($idEmpresa <= 0) jsonResp(false, 'Empresa no válida.');

$db = db();

// Obtener el ano_datos más reciente de la empresa.
// Si no existe, lo crea automáticamente con el año en curso usando el contrato activo.
function getAnoDatos(mysqli $db, int $idEmpresa): int
{
    // Buscar ano_datos existente
    $stmt = $db->prepare(
        "SELECT ad.id_ano_datos
         FROM ano_datos ad
         INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
         WHERE ce.id_empresa = ?
         ORDER BY ad.id_ano_datos DESC
         LIMIT 1"
    );
    if (!$stmt) return 0;
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($row['id_ano_datos'])) {
        return (int)$row['id_ano_datos'];
    }

    // No existe: obtener el contrato más reciente de la empresa
    $stmtCe = $db->prepare(
        "SELECT id_contrato_empresa FROM contrato_empresa
         WHERE id_empresa = ?
         ORDER BY id_contrato_empresa DESC LIMIT 1"
    );
    if (!$stmtCe) return 0;
    $stmtCe->bind_param('i', $idEmpresa);
    $stmtCe->execute();
    $rowCe = $stmtCe->get_result()->fetch_assoc();
    $stmtCe->close();

    if (empty($rowCe['id_contrato_empresa'])) return 0;

    $idContrato  = (int)$rowCe['id_contrato_empresa'];
    $year        = (int)date('Y');
    $fechaInicio = $year . '-01-01';
    $fechaFin    = $year . '-12-31';

    $stmtIns = $db->prepare(
        "INSERT INTO ano_datos (fecha_inicio, fecha_fin, id_contrato_empresa) VALUES (?, ?, ?)"
    );
    if (!$stmtIns) return 0;
    $stmtIns->bind_param('ssi', $fechaInicio, $fechaFin, $idContrato);
    $stmtIns->execute();
    $idNuevo = (int)$db->insert_id;
    $stmtIns->close();

    return $idNuevo;
}

// Obtener id_cliente_medida más reciente de la empresa (para área_promocion)
function getClienteMedida(mysqli $db, int $idEmpresa): int
{
    $stmt = $db->prepare(
        "SELECT cm.id_cliente_medida
         FROM cliente_medida cm
         INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
         WHERE ac.id_empresa = ?
         ORDER BY cm.id_cliente_medida DESC
         LIMIT 1"
    );
    if (!$stmt) return 0;
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['id_cliente_medida'] ?? 0);
}

try {
    switch ($accion) {

        // ── BAJAS ───────────────────────────────────────────────
        case 'bajas': {
            $tipo       = trim((string)($_POST['tipo']        ?? ''));
            $subtipo    = trim((string)($_POST['subtipo']     ?? ''));
            $motivo     = trim((string)($_POST['motivo']      ?? ''));
            $numMujeres = (int)($_POST['num_mujeres'] ?? 0);
            $numHombres = (int)($_POST['num_hombres'] ?? 0);

            if (!in_array($tipo, ['TEMPORALES', 'DEFINITIVAS'], true)) jsonResp(false, 'Tipo de baja inválido.');
            if ($subtipo === '') jsonResp(false, 'Selecciona el subtipo de baja.');

            $idAnoDatos = getAnoDatos($db, $idEmpresa);
            if ($idAnoDatos <= 0) jsonResp(false, 'No hay datos de año registrados para esta empresa.');

            // Insertar cabecera en bajas
            $stmt = $db->prepare("INSERT INTO bajas (tipo, tipox, id_ano_datos, id_empresa) VALUES (?, 'MANTE', ?, ?)");
            $stmt->bind_param('sii', $tipo, $idAnoDatos, $idEmpresa);
            $stmt->execute();
            $idBajas = (int)$stmt->insert_id;
            $stmt->close();

            // Insertar en la tabla hija correspondiente
            if ($tipo === 'TEMPORALES') {
                $stmt = $db->prepare("INSERT INTO baja_temporales (motivo, tipo, num_mujeres, num_hombres, id_ano_datos, id_empresa, id_bajas) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssiiiis', $motivo, $subtipo, $numMujeres, $numHombres, $idAnoDatos, $idEmpresa, $idBajas);
                // Corregir tipos
                $stmt->bind_param('ssiiii', $motivo, $subtipo, $numMujeres, $numHombres, $idAnoDatos, $idEmpresa);
            } else {
                $stmt = $db->prepare("INSERT INTO baja_definitivas (motivo, tipo, num_mujeres, num_hombres, id_ano_datos, id_empresa, id_bajas) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssiiiii', $motivo, $subtipo, $numMujeres, $numHombres, $idAnoDatos, $idEmpresa, $idBajas);
            }
            $stmt->execute();
            $stmt->close();
            jsonResp(true);
        }

        // ── EXCEDENCIAS ─────────────────────────────────────────
        case 'excedencias': {
            $tipo       = trim((string)($_POST['tipo']    ?? ''));
            $motivo     = trim((string)($_POST['motivo']  ?? ''));
            $nMujeres   = (int)($_POST['n_mujeres'] ?? 0);
            $nHombres   = (int)($_POST['n_hombres'] ?? 0);

            if ($tipo === '') jsonResp(false, 'Selecciona el tipo de excedencia.');

            $idAnoDatos = getAnoDatos($db, $idEmpresa);
            if ($idAnoDatos <= 0) jsonResp(false, 'No hay datos de año registrados para esta empresa.');

            $stmt = $db->prepare(
                "INSERT INTO area_excedencias (motivo, tipo, tipox, n_mujeres, n_hombres, id_ano_datos, id_empresa)
                 VALUES (?, ?, 'MANTE', ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssiiii', $motivo, $tipo, $nMujeres, $nHombres, $idAnoDatos, $idEmpresa);
            $stmt->execute();
            $stmt->close();
            jsonResp(true);
        }

        // ── REDUCCIONES DE JORNADA ──────────────────────────────
        case 'reducciones': {
            $reduccion = trim((string)($_POST['reduccion_jornada'] ?? ''));
            $nMujeres  = (int)($_POST['n_mujeres'] ?? 0);
            $nHombres  = (int)($_POST['n_hombres'] ?? 0);

            if ($reduccion === '') jsonResp(false, 'Selecciona el motivo de reducción.');

            $idAnoDatos = getAnoDatos($db, $idEmpresa);
            if ($idAnoDatos <= 0) jsonResp(false, 'No hay datos de año registrados para esta empresa.');

            $stmt = $db->prepare(
                "INSERT INTO area_reducciones_jornada (tipox, reduccion_jornada, n_mujeres, n_hombres, id_ano_datos, id_empresa)
                 VALUES ('MANTE', ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('siiii', $reduccion, $nMujeres, $nHombres, $idAnoDatos, $idEmpresa);
            $stmt->execute();
            $stmt->close();
            jsonResp(true);
        }

        // ── PROMOCIONES ─────────────────────────────────────────
        case 'promociones': {
            $pOrigen    = trim((string)($_POST['puesto_origen']        ?? ''));
            $pDestino   = trim((string)($_POST['puesto_destino']       ?? ''));
            $fechaAlta  = trim((string)($_POST['fecha_de_alta']        ?? ''));
            $nCand      = (int)($_POST['n_candidaturas']   ?? 0);
            $nHombres   = (int)($_POST['n_hombres']        ?? 0);
            $nMujeres   = (int)($_POST['n_mujeres']        ?? 0);
            $genProm    = trim((string)($_POST['genero_promocionado']   ?? ''));
            $intExt     = trim((string)($_POST['interna_externa']      ?? ''));
            $tipoProm   = trim((string)($_POST['tipo_promocion']       ?? ''));
            $responsable = trim((string)($_POST['responsable']          ?? ''));
            $cargo      = trim((string)($_POST['cargo_responsable']    ?? ''));
            $genResp    = trim((string)($_POST['genero_responsable']   ?? ''));
            $cInicial   = trim((string)($_POST['contrato_inicial']     ?? ''));
            $cFinal     = trim((string)($_POST['contrato_final']       ?? ''));
            $pctJornada = (int)($_POST['porcentaje_jornada'] ?? 100);
            $aumento    = (int)($_POST['aumento_economico']  ?? 0);
            $conciliacion = $_POST['disfruta_conciliacion'] !== '' ? (int)$_POST['disfruta_conciliacion'] : null;
            $criterio   = trim((string)($_POST['criterio'] ?? ''));

            if ($pOrigen === '' || $pDestino === '') jsonResp(false, 'Completa los puestos de origen y destino.');
            if ($fechaAlta === '') jsonResp(false, 'Indica la fecha de alta.');

            $idClienteMedida = getClienteMedida($db, $idEmpresa);
            if ($idClienteMedida <= 0) jsonResp(false, 'No hay medidas contratadas para esta empresa.');

            $stmt = $db->prepare(
                "INSERT INTO area_promocion_ascenso_personal
                 (tipox, puesto_origen, puesto_destino, aumento_economico, n_candidaturas,
                  n_hombres, n_mujeres, responsable, cargo_responsable, genero_responsable,
                  genero_promocionado, interna_externa, contrato_inicial, contrato_final,
                  tipo_promocion, fecha_de_alta, porcentaje_jornada, disfruta_conciliacion,
                  criterio, id_cliente_medida)
                 VALUES ('MANTE', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'ssiiiiisssssssssiii',
                $pOrigen, $pDestino, $aumento, $nCand, $nHombres, $nMujeres,
                $responsable, $cargo, $genResp, $genProm, $intExt,
                $cInicial, $cFinal, $tipoProm, $fechaAlta, $pctJornada,
                $conciliacion, $criterio, $idClienteMedida
            );
            $stmt->execute();
            $stmt->close();
            jsonResp(true);
        }

        // ── FORMACIÓN ───────────────────────────────────────────
        case 'formacion': {
            $tipo       = trim((string)($_POST['tipo']         ?? ''));
            $nHoras     = (int)($_POST['n_horas']    ?? 0);
            $nMujeres   = (int)($_POST['n_mujeres']  ?? 0);
            $nHombres   = (int)($_POST['n_hombres']  ?? 0);
            $perfil     = trim((string)($_POST['perfil_puesto'] ?? ''));
            $modalidad  = trim((string)($_POST['modalidad']     ?? ''));
            $horario    = trim((string)($_POST['horario']       ?? ''));
            $caracter   = trim((string)($_POST['caracter']      ?? ''));

            if ($tipo === '') jsonResp(false, 'Indica el tipo o nombre de la formación.');

            $idAnoDatos = getAnoDatos($db, $idEmpresa);
            if ($idAnoDatos <= 0) jsonResp(false, 'No hay datos de año registrados para esta empresa.');

            $stmt = $db->prepare(
                "INSERT INTO area_formaciones
                 (tipo, tipox, n_mujeres, n_hombres, n_horas, modalidad, perfil_puesto, horario, caracter, id_ano_datos, id_empresa)
                 VALUES (?, 'MANTE', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('siiiissssii', $tipo, $nMujeres, $nHombres, $nHoras, $modalidad, $perfil, $horario, $caracter, $idAnoDatos, $idEmpresa);
            $stmt->execute();
            $stmt->close();
            jsonResp(true);
        }

        default:
            jsonResp(false, 'Acción no reconocida.');
    }
} catch (\Throwable $e) {
    jsonResp(false, 'Error interno: ' . $e->getMessage());
}
