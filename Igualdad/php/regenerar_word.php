<?php

declare(strict_types=1);

// Endpoint ligero para regenerar y forzar descarga del Word
set_time_limit(300);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/generar_word_desdeexcel.php';
require_once __DIR__ . '/generar_cuadro_porcentajes.php';

require_login();

// Validar CSRF
if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'Token CSRF inválido']);
    exit;
}

$idEmpresa = isset($_POST['id_empresa']) ? (int)$_POST['id_empresa'] : 0;
if ($idEmpresa <= 0) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'id_empresa requerido']);
    exit;
}

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);

// Verificar acceso (usuario asignado o propietario) o administrador
$stmtAcceso = db()->prepare(
    'SELECT 1 FROM usuario_empresa WHERE id_usuario = ? AND id_empresa = ?
     UNION
     SELECT 1 FROM empresa WHERE id_usuario = ? AND id_empresa = ?
     LIMIT 1'
);
if ($stmtAcceso) {
    $stmtAcceso->bind_param('iiii', $usuarioId, $idEmpresa, $usuarioId, $idEmpresa);
    $stmtAcceso->execute();
    $tieneAcceso = ($stmtAcceso->get_result()->num_rows > 0);
    $stmtAcceso->close();
} else {
    $tieneAcceso = false;
}

if (!$tieneAcceso && $rol !== 'ADMINISTRADOR') {
    http_response_code(403);
    echo json_encode(['exito' => false, 'mensaje' => 'Acceso denegado']);
    exit;
}

// Obtener razon social
$stmtEmp = db()->prepare('SELECT razon_social FROM empresa WHERE id_empresa = ? LIMIT 1');
if (!$stmtEmp) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error DB']);
    exit;
}
$stmtEmp->bind_param('i', $idEmpresa);
$stmtEmp->execute();
$rowEmp = $stmtEmp->get_result()->fetch_assoc();
$stmtEmp->close();

if (!$rowEmp) {
    http_response_code(404);
    echo json_encode(['exito' => false, 'mensaje' => 'Empresa no encontrada']);
    exit;
}

$razonSocial = (string)($rowEmp['razon_social'] ?? '');

// año de referencia opcional
$anioRegistro = null;
$stmtAno = db()->prepare(
    'SELECT YEAR(ad.fecha_inicio) AS ano_referencia
     FROM ano_datos ad
     INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
     WHERE ce.id_empresa = ?
     ORDER BY ad.id_ano_datos DESC
     LIMIT 1'
);
if ($stmtAno) {
    $stmtAno->bind_param('i', $idEmpresa);
    $stmtAno->execute();
    $rowAno = $stmtAno->get_result()->fetch_assoc();
    $stmtAno->close();
    if ($rowAno) {
        $anioRegistro = (string)($rowAno['ano_referencia'] ?? null);
    }
}

$rutaExcel = buscarCuadroPorcentajesParaEmpresa($idEmpresa, $razonSocial);
if ($rutaExcel === '') {
    http_response_code(404);
    echo json_encode(['exito' => false, 'mensaje' => 'No se encontró cuadro generado de porcentajes para esta empresa']);
    exit;
}

try {
    $db = db();

    // Buscar el Word anterior para sobrescribirlo
    $stmtWordAnterior = $db->prepare(
        'SELECT id_archivo, ruta_relativa
         FROM archivos
            WHERE id_empresa = ? AND UPPER(TRIM(tipo)) = \'GENERADO WORD\'
         ORDER BY subido_en DESC, id_archivo DESC
         LIMIT 1'
    );
    
    $rutaWordDestino = null;
    $idArchivoAnterior = null;
    
    if ($stmtWordAnterior) {
        $stmtWordAnterior->bind_param('i', $idEmpresa);
        $stmtWordAnterior->execute();
        $rowAnterior = $stmtWordAnterior->get_result()->fetch_assoc();
        $stmtWordAnterior->close();
        
        if ($rowAnterior) {
            $idArchivoAnterior = (int)$rowAnterior['id_archivo'];
            $rutaRelAnterior = (string)$rowAnterior['ruta_relativa'];
            if ($rutaRelAnterior !== '') {
                $rutaWordDestino = resolverRutaProyectoDesdeRelativa($rutaRelAnterior);
            }
        }
    }

    // Actualizar datos cuantitativos en el Excel desde los forms guardados en BD
    actualizarDatosCuantitativosExcel($db, $rutaExcel, $idEmpresa);

    // Reutiliza la función existente para generar el Word, sobrescribiendo si existe anterior
    $rutaWord = rellenarWordPlanIgualdad($rutaExcel, $razonSocial, $anioRegistro, $idEmpresa, $rutaWordDestino);

    if (!is_file($rutaWord)) {
        throw new RuntimeException('No se generó el Word');
    }

    // Registrar en la tabla archivos
    $nombreArchivo = basename($rutaWord);
    $tamano = (int)filesize($rutaWord);
    $mime = (string)(mime_content_type($rutaWord) ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $sha256 = (string)(hash_file('sha256', $rutaWord) ?: '');
    $rutaRelDestino = obtenerRutaRelativaProyecto($rutaWord);
    if ($rutaRelDestino === '') {
        $rutaRelDestino = 'uploads/' . $nombreArchivo;
    }

    // Si hay un archivo anterior, actualizarlo; si no, insertar uno nuevo
    if ($idArchivoAnterior !== null) {
        $stmtUpd = $db->prepare(
            'UPDATE archivos
               SET tipo = ?, asunto = ?, nombre_original = ?, nombre_guardado = ?, ruta_relativa = ?, tamano_bytes = ?, mime = ?, sha256 = ?, subido_en = NOW(), id_cliente_medida = NULL, id_empresa = ?
             WHERE id_archivo = ?'
        );
        if ($stmtUpd) {
            $tipo = 'GENERADO WORD';
            $asunto = 'GENERADO WORD';
            $stmtUpd->bind_param('sssssissii', $tipo, $asunto, $nombreArchivo, $nombreArchivo, $rutaRelDestino, $tamano, $mime, $sha256, $idEmpresa, $idArchivoAnterior);
            $stmtUpd->execute();
            $stmtUpd->close();
        }
    } else {
        $stmtIns = $db->prepare(
            'INSERT INTO archivos (tipo, asunto, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_cliente_medida, id_empresa)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ? )'
        );
        if ($stmtIns) {
            $tipoIns = 'GENERADO WORD';
            $asunto = 'GENERADO WORD';
            $stmtIns->bind_param('sssssissi', $tipoIns, $asunto, $nombreArchivo, $nombreArchivo, $rutaRelDestino, $tamano, $mime, $sha256, $idEmpresa);
            $stmtIns->execute();
            $stmtIns->close();
        }
    }

    // Forzar descarga
    $safeFilename = str_replace(["\r", "\n", '"', '\\'], '', basename($rutaWord));
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header("Content-Disposition: attachment; filename=\"{$safeFilename}\"; filename*=UTF-8''" . rawurlencode(basename($rutaWord)));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($rutaWord));
    flush();
    readfile($rutaWord);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    error_log('Error regenerar_word: ' . $e->getMessage());
    echo json_encode(['exito' => false, 'mensaje' => 'Error generando Word: ' . $e->getMessage()]);
    exit;
}

/**
 * Convierte una ruta relativa almacenada en BD a una ruta absoluta dentro del proyecto.
 */
function resolverRutaProyectoDesdeRelativa(string $rutaRelativa): string
{
    $baseProyecto = dirname(__DIR__);
    $rutaNormalizada = str_replace('\\', '/', trim($rutaRelativa));
    $rutaNormalizada = preg_replace('#^/+#', '', $rutaNormalizada) ?? $rutaNormalizada;
    $rutaNormalizada = preg_replace('#^(?:\.\./)+#', '', $rutaNormalizada) ?? $rutaNormalizada;

    return $baseProyecto . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaNormalizada);
}

/**
 * Calcula la ruta relativa al proyecto a partir de una ruta absoluta.
 */
function obtenerRutaRelativaProyecto(string $rutaAbsoluta): string
{
    $baseProyecto = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $rutaNormalizada = str_replace('\\', '/', $rutaAbsoluta);

    if (strpos($rutaNormalizada, $baseProyecto . '/') === 0) {
        return ltrim(substr($rutaNormalizada, strlen($baseProyecto)), '/');
    }

    return '';
}

/**
 * Busca el cuadro de porcentajes de una empresa con criterios flexibles y fallback acotado por empresa.
 */
function buscarCuadroPorcentajesParaEmpresa(int $idEmpresa, string $razonSocial): string
{
    $db = db();

    $consultas = [
        'SELECT ruta_relativa
         FROM archivos
         WHERE id_empresa = ?
           AND UPPER(TRIM(COALESCE(asunto, ""))) = "GENERADO PORCENTAJES"
           AND ruta_relativa IS NOT NULL
           AND LOWER(ruta_relativa) LIKE "uploads/%"
           AND LOWER(ruta_relativa) REGEXP "\\.(xlsx|xls|xlsm|csv)$"
         ORDER BY subido_en DESC, id_archivo DESC
         LIMIT 1',
        'SELECT ruta_relativa
         FROM archivos
         WHERE id_empresa = ?
           AND UPPER(TRIM(COALESCE(tipo, ""))) = "CUADRO PORCENTAJES"
           AND ruta_relativa IS NOT NULL
           AND LOWER(ruta_relativa) LIKE "uploads/%"
           AND LOWER(ruta_relativa) REGEXP "\\.(xlsx|xls|xlsm|csv)$"
         ORDER BY subido_en DESC, id_archivo DESC
         LIMIT 1',
    ];

    foreach ($consultas as $sql) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('i', $idEmpresa);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['ruta_relativa'])) {
            $ruta = resolverRutaProyectoDesdeRelativa((string)$row['ruta_relativa']);
            if (is_file($ruta)) {
                return $ruta;
            }
        }
    }

    $baseNombreEmpresa = normalizarNombreArchivoEmpresa($razonSocial);
    if ($baseNombreEmpresa === '') {
        return '';
    }

    $baseUploads = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    $patrones = [
        $baseUploads . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.xlsx',
        $baseUploads . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.xls',
        $baseUploads . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.xlsm',
        $baseUploads . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.csv',
        $baseUploads . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.xlsx',
        $baseUploads . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.xls',
        $baseUploads . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.xlsm',
        $baseUploads . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $baseNombreEmpresa . '*.csv',
    ];

    $candidatos = [];
    foreach ($patrones as $patron) {
        foreach (glob($patron) ?: [] as $archivo) {
            $nombre = basename($archivo);
            $nombreUpper = strtoupper($nombre);
            if (
                str_contains($nombreUpper, 'PLAN_IGUALDAD') ||
                str_starts_with($nombreUpper, 'REGISTRO_') ||
                str_contains($nombreUpper, '_TOMA_DE_DATOS_')
            ) {
                continue;
            }
            $candidatos[$archivo] = filemtime($archivo) ?: 0;
        }
    }

    if ($candidatos !== []) {
        arsort($candidatos);
        $mejorArchivo = array_key_first($candidatos);
        if (is_string($mejorArchivo) && $mejorArchivo !== '' && is_file($mejorArchivo)) {
            return $mejorArchivo;
        }
    }

    return '';
}

