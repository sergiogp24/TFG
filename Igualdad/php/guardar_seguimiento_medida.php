<?php
declare(strict_types=1);

ob_start(); // captura cualquier warning/notice antes de enviar JSON
header('Content-Type: application/json; charset=UTF-8');

function jsonResp(bool $ok, ?string $error = null): never
{
    ob_end_clean(); // descarta cualquier salida previa
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResp(false, 'Método no permitido.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user'])) {
    jsonResp(false, 'No autenticado.');
}

require_once __DIR__ . '/../config/config.php';

$idClienteMedida  = (int)($_POST['id_cliente_medida']  ?? 0);
$idEmpresa        = (int)($_POST['id_empresa']          ?? 0);
$puestoEmpresa    = trim((string)($_POST['puesto_empresa']    ?? ''));
$puestoNuevo      = trim((string)($_POST['puesto_nuevo']      ?? ''));
$fechaPublicacion = trim((string)($_POST['fecha_publicacion'] ?? ''));
$archivoOferta    = trim((string)($_POST['archivo_oferta']    ?? ''));
$candidaturaGen   = strtoupper(trim((string)($_POST['candidatura_genero'] ?? '')));
$candidaturaNum   = $_POST['candidatura_numero'] !== '' ? (int)$_POST['candidatura_numero'] : null;
$criterio         = strtoupper(trim((string)($_POST['criterio_seleccion'] ?? '')));
$criterioOtros    = trim((string)($_POST['criterio_otros'] ?? ''));

if ($idClienteMedida <= 0) {
    jsonResp(false, 'Medida no válida.');
}

// Validar enums
$generosValidos  = ['MUJER', 'HOMBRE', ''];
$criteriosValidos = ['FORMACION', 'DISPONIBILIDAD', 'EXPERIENCIA', 'OTROS', ''];
if (!in_array($candidaturaGen, $generosValidos, true)) $candidaturaGen = '';
if (!in_array($criterio, $criteriosValidos, true)) $criterio = '';

// Fecha válida o null
$fechaFinal = null;
if ($fechaPublicacion !== '' && strtotime($fechaPublicacion) !== false) {
    $fechaFinal = $fechaPublicacion;
}

// Puesto final: si hay nuevo, usarlo
$puestoFinal = $puestoNuevo !== '' ? $puestoNuevo : ($puestoEmpresa !== '' ? $puestoEmpresa : null);

try {
    $db = db();

    // Verificar que id_cliente_medida pertenece a esta empresa
    $stmtCheck = $db->prepare(
        "SELECT cm.id_cliente_medida
         FROM cliente_medida cm
         INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
         WHERE cm.id_cliente_medida = ? AND ac.id_empresa = ?
         LIMIT 1"
    );
    if (!$stmtCheck) {
        jsonResp(false, 'Error de consulta.');
    }
    $stmtCheck->bind_param('ii', $idClienteMedida, $idEmpresa);
    $stmtCheck->execute();
    if (!$stmtCheck->get_result()->fetch_assoc()) {
        jsonResp(false, 'La medida no pertenece a esta empresa.');
    }
    $stmtCheck->close();

    // INSERT
    $stmt = $db->prepare(
        "INSERT INTO seguimiento_medida
            (id_cliente_medida, puesto_empresa, puesto_nuevo, fecha_publicacion,
             archivo_oferta, candidatura_genero, candidatura_numero,
             criterio_seleccion, criterio_otros)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        jsonResp(false, 'Error preparando la consulta.');
    }

    $generoParam   = $candidaturaGen !== '' ? $candidaturaGen : null;
    $criterioParam = $criterio       !== '' ? $criterio       : null;

    $stmt->bind_param(
        'isssssiss',
        $idClienteMedida,
        $puestoFinal,
        $puestoNuevo,
        $fechaFinal,
        $archivoOferta,
        $generoParam,
        $candidaturaNum,
        $criterioParam,
        $criterioOtros
    );

    if (!$stmt->execute()) {
        jsonResp(false, 'Error al guardar en la base de datos.');
    }
    $stmt->close();

    jsonResp(true);
} catch (\Throwable $e) {
    jsonResp(false, 'Error interno: ' . $e->getMessage());
}
