<?php
declare(strict_types=1);

function registrarLogProcesarRegistroRetributivo(string $mensaje): void
{
    $archivos = [
        __DIR__ . '/../uploads/procesar_registro_retributivo_error.log',
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'procesar_registro_retributivo_error.log',
    ];

    foreach ($archivos as $archivo) {
        $dir = dirname($archivo);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            continue;
        }

        $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
        if (@file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
    }

    error_log($mensaje);
}

set_exception_handler(static function (Throwable $e): void {
    registrarLogProcesarRegistroRetributivo("EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString());
});

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/mails.php';
require_once __DIR__ . '/generar_cuadro_porcentajes.php';
require_once __DIR__ . '/generar_word_desdeexcel.php';
require_login();


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function redirigirMenuSubida(string $urlMenuSubida, string $mensaje, ?int $exito = null, int $idEmpresaContexto = 0): void
{
    $separator = str_contains($urlMenuSubida, '?') ? '&' : '?';
    $to = $urlMenuSubida . $separator . 'msg=' . urlencode($mensaje);

    if ($exito !== null && $exito !== 0) {
        $to .= '&success=' . $exito;
    }

    if ($idEmpresaContexto > 0) {
        $to .= '&id_empresa=' . $idEmpresaContexto;
    }

    header('Location: ' . $to);
    exit;
}
// ================== FUNCIONES ==================
function convertirFechaExcel($valor)
{
    if ($valor === null || $valor === '') return null;
    if (is_numeric($valor)) return date('Y-m-d', Date::excelToTimestamp((float)$valor));
    $timestamp = strtotime((string)$valor);
    return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
}

function limpiarRazonSocial($texto)
{
    if (empty($texto)) return null;
    $texto = preg_replace('/\s+/', ' ', trim((string)$texto));
    return mb_strtoupper((string)$texto);
}

function ultimaFilaConDatosEnRango($sheet, string $colInicio, string $colFin, int $filaInicio = 1): int
{
    $colIniIdx = Coordinate::columnIndexFromString($colInicio);
    $colFinIdx = Coordinate::columnIndexFromString($colFin);
    $maxFila = $sheet->getHighestDataRow();

    for ($fila = $maxFila; $fila >= $filaInicio; $fila--) {
        for ($c = $colIniIdx; $c <= $colFinIdx; $c++) {
            $col = Coordinate::stringFromColumnIndex($c);
            $valor = $sheet->getCell($col . $fila)->getCalculatedValue();
            if ($valor !== null && trim((string)$valor) !== '') {
                return $fila;
            }
        }
    }

    return $filaInicio - 1;
}

function registrarArchivoGeneradoEnTabla(
    mysqli $db,
    string $tipo,
    string $rutaAbsoluta,
    string $rutaRelativa,
    ?string $asunto = null,
    int $idEmpresa = 0,
    ?int $idClienteMedida = null
): void {
    if (!is_file($rutaAbsoluta)) {
        throw new RuntimeException('No existe el archivo para registrar en tabla: ' . $rutaAbsoluta);
    }

    $nombreArchivo = basename($rutaAbsoluta);
    $tamano = (int)filesize($rutaAbsoluta);
    $mime = (string)(mime_content_type($rutaAbsoluta) ?: 'application/octet-stream');
    $sha256 = (string)(hash_file('sha256', $rutaAbsoluta) ?: '');
    $idEmpresaDb = $idEmpresa > 0 ? $idEmpresa : null;
    $asuntoDb = $asunto;

    $stmtDup = $db->prepare('SELECT id_archivo FROM archivos WHERE tipo = ? AND ruta_relativa = ? LIMIT 1');
    if (!$stmtDup) {
        throw new RuntimeException('Error prepare buscar archivo generado: ' . $db->error);
    }

    $stmtDup->bind_param('ss', $tipo, $rutaRelativa);
    $stmtDup->execute();
    $rowDup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();

    if ($rowDup) {
        $idArchivo = (int)$rowDup['id_archivo'];
        $stmtUpd = $db->prepare(
            'UPDATE archivos
             SET asunto = ?, nombre_original = ?, nombre_guardado = ?, ruta_relativa = ?, tamano_bytes = ?, mime = ?, sha256 = ?, id_cliente_medida = ?, id_empresa = ?
             WHERE id_archivo = ?'
        );

        if (!$stmtUpd) {
            throw new RuntimeException('Error prepare actualizar archivo generado: ' . $db->error);
        }

        $stmtUpd->bind_param(
            'ssssissiii',
            $asuntoDb,
            $nombreArchivo,
            $nombreArchivo,
            $rutaRelativa,
            $tamano,
            $mime,
            $sha256,
            $idClienteMedida,
            $idEmpresaDb,
            $idArchivo
        );
        $stmtUpd->execute();
        $stmtUpd->close();
        return;
    }

    $stmtIns = $db->prepare(
        'INSERT INTO archivos
         (tipo, asunto, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_cliente_medida, id_empresa)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmtIns) {
        throw new RuntimeException('Error prepare insertar archivo generado: ' . $db->error);
    }

    $stmtIns->bind_param(
        'sssssissii',
        $tipo,
        $asuntoDb,
        $nombreArchivo,
        $nombreArchivo,
        $rutaRelativa,
        $tamano,
        $mime,
        $sha256,
        $idClienteMedida,
        $idEmpresaDb
    );
    $stmtIns->execute();
    $stmtIns->close();
}

// ================== VALIDACIONES INICIALES ==================
$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$debeGenerarDerivados = in_array($rol, ['ADMINISTRADOR', 'TECNICO'], true);
$urlMenuSubida = in_array($rol, ['ADMINISTRADOR', 'TECNICO'], true)
    ? app_path('/html/index_staff.php')
    : app_path('/html/index_cliente.php');
$idEmpresaPost = isset($_POST['id_empresa']) ? (int)$_POST['id_empresa'] : 0;

if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    redirigirMenuSubida($urlMenuSubida, 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.', null, $idEmpresaPost);
}

if (!isset($_FILES['excel']) || empty($_POST['tipo'])) {
    redirigirMenuSubida($urlMenuSubida, 'No se envió archivo o tipo.', null, $idEmpresaPost);
}

$tipo = strtoupper(trim((string)$_POST['tipo']));
$tiposPermitidos = ['REGISTRO_RETRIBUTIVO', 'TOMA DE DATOS'];
if ($rol === 'TECNICO') {
    $tiposPermitidos[] = 'WORD_FINAL';
}
if (!in_array($tipo, $tiposPermitidos, true)) {
    redirigirMenuSubida($urlMenuSubida, 'El tipo seleccionado no es valido para este usuario.', null, $idEmpresaPost);
}

if ($tipo === 'WORD_FINAL' && $rol !== 'TECNICO') {
    redirigirMenuSubida($urlMenuSubida, 'Solo el tecnico puede subir WORD_FINAL.', null, $idEmpresaPost);
}

$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);

$empresaNombreVista = trim((string)($_POST['nombre_empresa'] ?? $_POST['referencia_empresa'] ?? ''));
if ($empresaNombreVista !== '') {
    $empresaNombreVista = trim((string)explode(',', $empresaNombreVista)[0]);
}
if (mb_strtolower($empresaNombreVista, 'UTF-8') === 'sin empresa asignada') {
    $empresaNombreVista = '';
}

$empresaNombreArchivo = preg_replace('/[\\\\\/:"*?<>|]+/', '-', $empresaNombreVista ?? '');
$empresaNombreArchivo = preg_replace('/\s+/', ' ', (string)$empresaNombreArchivo);
$empresaNombreArchivo = trim((string)$empresaNombreArchivo, " .-");

$db = db();

if ($idEmpresaPost > 0) {
    if ($rol === 'CLIENTE') {
        $stmtEmpresaPost = $db->prepare(
            'SELECT e.razon_social
             FROM usuario_empresa ue
             INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
             WHERE ue.id_usuario = ? AND e.id_empresa = ?
             LIMIT 1'
        );

        if ($stmtEmpresaPost) {
            $stmtEmpresaPost->bind_param('ii', $usuarioId, $idEmpresaPost);
            $stmtEmpresaPost->execute();
            $rowEmpresaPost = $stmtEmpresaPost->get_result()->fetch_assoc();
            $stmtEmpresaPost->close();
            $empresaNombreVista = trim((string)($rowEmpresaPost['razon_social'] ?? ''));
        }
    } else {
        $stmtEmpresaPost = $db->prepare(
            'SELECT razon_social
             FROM empresa
             WHERE id_empresa = ?
             LIMIT 1'
        );

        if ($stmtEmpresaPost) {
            $stmtEmpresaPost->bind_param('i', $idEmpresaPost);
            $stmtEmpresaPost->execute();
            $rowEmpresaPost = $stmtEmpresaPost->get_result()->fetch_assoc();
            $stmtEmpresaPost->close();
            $empresaNombreVista = trim((string)($rowEmpresaPost['razon_social'] ?? ''));
        }
    }

    if ($empresaNombreVista === '') {
        redirigirMenuSubida($urlMenuSubida, 'La empresa seleccionada no es válida para este usuario.', null, $idEmpresaPost);
    }

    $empresaNombreArchivo = preg_replace('~[^A-Za-z0-9 _.-]+~', '-', $empresaNombreVista ?? '');
    $empresaNombreArchivo = preg_replace('~\s+~', ' ', (string)$empresaNombreArchivo);
    $empresaNombreArchivo = trim((string)$empresaNombreArchivo, " .-");
}

if ($empresaNombreVista === '') {
    if ($usuarioId > 0) {
        $stmtEmpresaSesion = $db->prepare(
            'SELECT e.razon_social
             FROM usuario_empresa ue
             INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
             WHERE ue.id_usuario = ?
             ORDER BY e.razon_social ASC
             LIMIT 1'
        );

        if ($stmtEmpresaSesion) {
            $stmtEmpresaSesion->bind_param('i', $usuarioId);
            $stmtEmpresaSesion->execute();
            $rowEmpresaSesion = $stmtEmpresaSesion->get_result()->fetch_assoc();
            $stmtEmpresaSesion->close();
            $empresaNombreVista = trim((string)($rowEmpresaSesion['razon_social'] ?? ''));
            $empresaNombreArchivo = preg_replace('~[^A-Za-z0-9 _.-]+~', '-', $empresaNombreVista ?? '');
            $empresaNombreArchivo = preg_replace('~\s+~', ' ', (string)$empresaNombreArchivo);
            $empresaNombreArchivo = trim((string)$empresaNombreArchivo, " .-");
        }
    }
}

$idEmpresaContexto = $idEmpresaPost;
if ($idEmpresaContexto <= 0 && $empresaNombreVista !== '') {
    $stmtEmpresaId = $db->prepare(
        'SELECT id_empresa FROM empresa WHERE razon_social = ? LIMIT 1'
    );
    if ($stmtEmpresaId) {
        $stmtEmpresaId->bind_param('s', $empresaNombreVista);
        $stmtEmpresaId->execute();
        $rowEmpresaId = $stmtEmpresaId->get_result()->fetch_assoc();
        $stmtEmpresaId->close();
        $idEmpresaContexto = (int)($rowEmpresaId['id_empresa'] ?? 0);
    }
}

$idClienteMedidaContexto = null;
if ($idEmpresaContexto > 0) {
    $stmtClienteMedida = $db->prepare(
        'SELECT cm.id_cliente_medida
         FROM cliente_medida cm
         INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
         WHERE ac.id_empresa = ?
         ORDER BY cm.id_cliente_medida ASC
         LIMIT 1'
    );
    if ($stmtClienteMedida) {
        $stmtClienteMedida->bind_param('i', $idEmpresaContexto);
        $stmtClienteMedida->execute();
        $rowClienteMedida = $stmtClienteMedida->get_result()->fetch_assoc();
        $stmtClienteMedida->close();
        if ($rowClienteMedida) {
            $idClienteMedidaContexto = (int)$rowClienteMedida['id_cliente_medida'];
        }
    }
}

// Contadores globales
$totalInsertadasGlobal = 0;
$totalArchivosGuardados = 0;
$totalErroresGlobal = 0;
$totalDuplicadosGlobal = 0;
$erroresMensajes = [];

// ================== Normalizar arrays ==================
$files = $_FILES['excel'];
$names = is_array($files['name']) ? $files['name'] : [$files['name']];
$tmp_names = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
$errors = is_array($files['error']) ? $files['error'] : [$files['error']];

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ================== PROCESAR ARCHIVOS ==================
foreach ($names as $i => $originalName) {
    if (empty($originalName) || $errors[$i] !== UPLOAD_ERR_OK) {
        continue;
    }

    $tmpName = $tmp_names[$i];
    $ext = pathinfo((string)$originalName, PATHINFO_EXTENSION);
    $displayOriginalName = (string)$originalName;
    if ($tipo === 'TOMA DE DATOS') {
        if ($empresaNombreArchivo === '') {
            $empresaNombreArchivo = 'EMPRESA_' . $idEmpresaContexto;
        }

        $empresaNombreToken = preg_replace('~\s+~', '_', $empresaNombreArchivo);
        $empresaNombreToken = mb_strtoupper((string)$empresaNombreToken, 'UTF-8');
        $displayOriginalName = $empresaNombreToken . '_TOMA_DE_DATOS';
        if ($ext !== '') {
            $displayOriginalName .= '.' . $ext;
        }
    }

    $uniqueSuffix = substr(md5(uniqid('', true)), 0, 8);
    if ($tipo === 'TOMA DE DATOS') {
        $nombreGuardado = $empresaNombreToken . '_TOMA_DE_DATOS_' . $uniqueSuffix;
        if ($ext !== '') {
            $nombreGuardado .= '.' . $ext;
        }
    } elseif ($tipo === 'WORD_FINAL') {
        if ($empresaNombreArchivo === '') {
            $empresaNombreArchivo = 'EMPRESA_' . $idEmpresaContexto;
        }
        $empresaNombreToken = preg_replace('~\s+~', '_', $empresaNombreArchivo);
        $empresaNombreToken = mb_strtoupper((string)$empresaNombreToken, 'UTF-8');
        $nombreGuardado = $empresaNombreToken . '_WORD_FINAL_' . $uniqueSuffix;
        if ($ext !== '') {
            $nombreGuardado .= '.' . $ext;
        }
    } else {
        // Para REGISTRO_RETRIBUTIVO: usamos nombre temporal, lo renombraremos después de leer Excel
        $nombreGuardado = 'registro_temp_' . uniqid('', true) . '.' . $ext;
    }
    $rutaCompleta = $uploadDir . $nombreGuardado;

    if (!move_uploaded_file($tmpName, $rutaCompleta)) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error subiendo archivo: {$originalName}";
        continue;
    }

    if ($tipo === 'TOMA DE DATOS') {
        $totalArchivosGuardados++;
        continue;
    }

    if ($tipo === 'WORD_FINAL') {
        if ($idEmpresaContexto <= 0) {
            $totalErroresGlobal++;
            $erroresMensajes[] = 'No se pudo resolver la empresa para WORD_FINAL.';
            continue;
        }

        $idEmpresaWordFinal = $idEmpresaContexto;
        $sha256 = hash_file('sha256', $rutaCompleta);
        $size = filesize($rutaCompleta);
        $mime = mime_content_type($rutaCompleta);
        $rutaRelativa = 'uploads/' . $nombreGuardado;

        $stmtDup = $db->prepare(
            'SELECT id_archivo FROM archivos WHERE UPPER(TRIM(tipo)) = "WORD_FINAL" AND id_empresa = ? LIMIT 1'
        );
        $idArchivoExistente = null;
        if ($stmtDup) {
            $stmtDup->bind_param('i', $idEmpresaWordFinal);
            $stmtDup->execute();
            $resultDup = $stmtDup->get_result()->fetch_assoc();
            $stmtDup->close();
            if ($resultDup) {
                $idArchivoExistente = (int)$resultDup['id_archivo'];
            }
        }

        if ($idArchivoExistente !== null) {
            $stmtUpdate = $db->prepare(
                'UPDATE archivos
                 SET nombre_original = ?, nombre_guardado = ?, ruta_relativa = ?, tamano_bytes = ?, mime = ?, sha256 = ?, id_cliente_medida = ?, id_empresa = ?
                 WHERE id_archivo = ?'
            );
            if ($stmtUpdate) {
                $stmtUpdate->bind_param(
                    'sssissiii',
                    $displayOriginalName,
                    $nombreGuardado,
                    $rutaRelativa,
                    $size,
                    $mime,
                    $sha256,
                    $idClienteMedidaContexto,
                    $idEmpresaWordFinal,
                    $idArchivoExistente
                );
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        } else {
            $stmtArchivo = $db->prepare(
                'INSERT INTO archivos
                 (tipo, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_cliente_medida, id_empresa)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$stmtArchivo) {
                $totalErroresGlobal++;
                $erroresMensajes[] = 'Error prepare archivos WORD_FINAL: ' . $db->error;
                continue;
            }

            $tipoWordFinal = 'WORD_FINAL';
            $stmtArchivo->bind_param(
                'ssssissii',
                $tipoWordFinal,
                $displayOriginalName,
                $nombreGuardado,
                $rutaRelativa,
                $size,
                $mime,
                $sha256,
                $idClienteMedidaContexto,
                $idEmpresaWordFinal
            );

            if (!$stmtArchivo->execute()) {
                $totalErroresGlobal++;
                $erroresMensajes[] = 'Error insert WORD_FINAL: ' . $stmtArchivo->error;
                $stmtArchivo->close();
                continue;
            }

            $stmtArchivo->close();
        }

        $totalArchivosGuardados++;
        continue;
    }

    // Para REGISTRO_RETRIBUTIVO, primero leer Excel para obtener datos antes de guardar en BD
    $extLower = strtolower((string)$ext);
    $extRequiereZip = in_array($extLower, ['xlsx', 'xlsm', 'xltx', 'xltm', 'ods'], true);
    if ($extRequiereZip && !class_exists('ZipArchive')) {
        $totalErroresGlobal++;
        $erroresMensajes[] = 'El archivo se subio, pero no se pudo procesar: falta la extension ZIP de PHP (ZipArchive). Reinicia Apache despues de activarla en php.ini.';
        continue;
    }

    // ================== Leer Excel ==================
    try {
        $spreadsheet = IOFactory::load($rutaCompleta);
    } catch (\Throwable $e) {
        $totalErroresGlobal++;
        error_log(sprintf('[registro_retributivo.leer_excel] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
        $erroresMensajes[] = 'Error leyendo el archivo Excel.';
        continue;
    }

    // ================== Hoja 0: Empresa ==================
    $sheetEmpresa = $spreadsheet->getSheet(0);
    $razon_social = limpiarRazonSocial($sheetEmpresa->getCell('C6')->getCalculatedValue());
    $fecha_inicio = convertirFechaExcel($sheetEmpresa->getCell('E10')->getCalculatedValue());
    $fecha_fin = convertirFechaExcel($sheetEmpresa->getCell('G10')->getCalculatedValue());

    if (empty($razon_social) || !$fecha_inicio || !$fecha_fin) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Datos de empresa incompletos en hoja 0.";
        continue;
    }

    // Extraer año de fecha_inicio para renombrar archivo
    $timestampInicio = strtotime((string)$fecha_inicio);
    if ($timestampInicio === false) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Fecha de inicio inválida en hoja 0.";
        continue;
    }
    $anioRegistro = date('Y', $timestampInicio);
    $anoRegistro = $anioRegistro . '-01-01';

    // Buscar empresa
    $stmtEmpresa = $db->prepare("
        SELECT id_empresa 
        FROM empresa 
        WHERE UPPER(TRIM(REPLACE(razon_social,'  ',' '))) = ?
    ");

    if (!$stmtEmpresa) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error prepare empresa: " . $db->error;
        continue;
    }

    $stmtEmpresa->bind_param("s", $razon_social);
    $stmtEmpresa->execute();
    $empresa = $stmtEmpresa->get_result()->fetch_assoc();
    $stmtEmpresa->close();

    if (!$empresa) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Empresa '{$razon_social}' no encontrada.";
        continue;
    }
    $id_empresa = (int)$empresa['id_empresa'];
    
    // Renombrar archivo con nombre formateado: EMPRESA_NOMBRE_AAAA
    if ($tipo === 'REGISTRO_RETRIBUTIVO') {
        $empresaNombreToken = preg_replace('~\s+~', '_', $empresaNombreArchivo);
        $empresaNombreToken = mb_strtoupper((string)$empresaNombreToken, 'UTF-8');
        $nombreGuardadoNuevo = $empresaNombreToken . '_' . $anioRegistro . '.' . $ext;
        $rutaCompleta = $uploadDir . $nombreGuardadoNuevo;
        
        if (!rename($uploadDir . $nombreGuardado, $rutaCompleta)) {
            $totalErroresGlobal++;
            $erroresMensajes[] = "Error renombrando archivo para empresa '{$razon_social}'.";
            continue;
        }
        $nombreGuardado = $nombreGuardadoNuevo;
    }

    $idClienteMedidaArchivo = null;
    $stmtClienteMedidaArchivo = $db->prepare(
        'SELECT cm.id_cliente_medida
         FROM cliente_medida cm
         INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
         WHERE ac.id_empresa = ?
         ORDER BY cm.id_cliente_medida ASC
         LIMIT 1'
    );
    if ($stmtClienteMedidaArchivo) {
        $stmtClienteMedidaArchivo->bind_param('i', $id_empresa);
        $stmtClienteMedidaArchivo->execute();
        $rowClienteMedidaArchivo = $stmtClienteMedidaArchivo->get_result()->fetch_assoc();
        $stmtClienteMedidaArchivo->close();
        if ($rowClienteMedidaArchivo) {
            $idClienteMedidaArchivo = (int)$rowClienteMedidaArchivo['id_cliente_medida'];
        }
    }

    // ================== Guardar archivo en BD ==================
    $sha256 = hash_file('sha256', $rutaCompleta);
    $size = filesize($rutaCompleta);
    $mime = mime_content_type($rutaCompleta);
    $rutaRelativa = 'uploads/' . $nombreGuardado;

    // Verificar si el archivo ya existe para actualizarlo
    $stmtDup = $db->prepare(
        "SELECT id_archivo FROM archivos WHERE tipo = ? AND sha256 = ? LIMIT 1"
    );
    $idArchivoExistente = null;
    if ($stmtDup) {
        $stmtDup->bind_param('ss', $tipo, $sha256);
        $stmtDup->execute();
        $resultDup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($resultDup) {
            $idArchivoExistente = (int)$resultDup['id_archivo'];
        }
    }

    // Si ya existe, actualizar el registro
    if ($idArchivoExistente !== null) {
        // Para REGISTRO_RETRIBUTIVO usar idClienteMedidaArchivo; para TOMA DE DATOS usar idClienteMedidaContexto
        $idClienteMedidaUsado = ($tipo === 'REGISTRO_RETRIBUTIVO') ? $idClienteMedidaArchivo : $idClienteMedidaContexto;

        if ($idClienteMedidaUsado !== null) {
            $stmtUpdate = $db->prepare(" 
                UPDATE archivos 
                SET nombre_original = ?, nombre_guardado = ?, ruta_relativa = ?, tamano_bytes = ?, mime = ?, id_cliente_medida = ?, id_empresa = ?
                WHERE id_archivo = ?
            ");
            if ($stmtUpdate) {
                $stmtUpdate->bind_param("sssisiii", $displayOriginalName, $nombreGuardado, $rutaRelativa, $size, $mime, $idClienteMedidaUsado, $id_empresa, $idArchivoExistente);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        } else {
            $stmtUpdate = $db->prepare("
                UPDATE archivos 
                SET nombre_original = ?, nombre_guardado = ?, ruta_relativa = ?, tamano_bytes = ?, mime = ?, id_empresa = ?
                WHERE id_archivo = ?
            ");
            if ($stmtUpdate) {
                $stmtUpdate->bind_param("sssisii", $displayOriginalName, $nombreGuardado, $rutaRelativa, $size, $mime, $id_empresa, $idArchivoExistente);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }
        $totalArchivosGuardados++;
    } else {

        // Para REGISTRO_RETRIBUTIVO usar idClienteMedidaArchivo; para TOMA DE DATOS usar idClienteMedidaContexto
        $idClienteMedidaUsado = ($tipo === 'REGISTRO_RETRIBUTIVO') ? $idClienteMedidaArchivo : $idClienteMedidaContexto;

        if ($idClienteMedidaUsado !== null) {
            $stmtArchivo = $db->prepare("
                INSERT INTO archivos 
                (tipo, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_cliente_medida, id_empresa)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmtArchivo = $db->prepare("
                INSERT INTO archivos 
                (tipo, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_empresa)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }

        if (!$stmtArchivo) {
            $totalErroresGlobal++;
            $erroresMensajes[] = "Error prepare archivos: " . $db->error;
            continue;
        }

        if ($idClienteMedidaUsado !== null) {
            $stmtArchivo->bind_param("ssssissii", $tipo, $displayOriginalName, $nombreGuardado, $rutaRelativa, $size, $mime, $sha256, $idClienteMedidaUsado, $id_empresa);
        } else {
            $stmtArchivo->bind_param("ssssissi", $tipo, $displayOriginalName, $nombreGuardado, $rutaRelativa, $size, $mime, $sha256, $id_empresa);
        }

        if (!$stmtArchivo->execute()) {
            $totalErroresGlobal++;
            $erroresMensajes[] = "Error insert archivos: " . $stmtArchivo->error;
            $stmtArchivo->close();
            continue;
        }

        $stmtArchivo->close();
        $totalArchivosGuardados++;
    }

    $extLower = strtolower((string)$ext);
    $extRequiereZip = in_array($extLower, ['xlsx', 'xlsm', 'xltx', 'xltm', 'ods'], true);
    if ($extRequiereZip && !class_exists('ZipArchive')) {
        $totalErroresGlobal++;
        $erroresMensajes[] = 'El archivo se subio, pero no se pudo procesar: falta la extension ZIP de PHP (ZipArchive). Reinicia Apache despues de activarla en php.ini.';
        continue;
    }

    // ================== Contrato ==================
    // SOLO comprobar que exista contrato para la empresa; NO insertar.
    $stmtContrato = $db->prepare("
        SELECT id_contrato_empresa
        FROM contrato_empresa
        WHERE id_empresa = ?
        ORDER BY id_contrato_empresa DESC
        LIMIT 1
    ");

    if (!$stmtContrato) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error prepare contrato: " . $db->error;
        continue;
    }

    $stmtContrato->bind_param("i", $id_empresa);
    $stmtContrato->execute();
    $contrato = $stmtContrato->get_result()->fetch_assoc();
    $stmtContrato->close();

    if (!$contrato) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "La empresa '{$razon_social}' no tiene contrato dado de alta.";
        continue;
    }

    $id_contrato_empresa = (int)$contrato['id_contrato_empresa'];

    // ================== Año Datos ==================
    $stmtAno = $db->prepare("
        INSERT INTO ano_datos (fecha_inicio, fecha_fin, id_contrato_empresa)
        VALUES (?,?,?)
    ");

    if (!$stmtAno) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error prepare ano_datos: " . $db->error;
        continue;
    }

    $stmtAno->bind_param("ssi", $fecha_inicio, $fecha_fin, $id_contrato_empresa);

    if (!$stmtAno->execute()) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error insert ano_datos: " . $stmtAno->error;
        $stmtAno->close();
        continue;
    }

    $id_ano_datos = $stmtAno->insert_id;
    $stmtAno->close();

    // ================== Hoja 3 (pestaña "Datos"): Empleados ==================
    $sheetEmpleados = $spreadsheet->getSheetByName('Datos');
    if ($sheetEmpleados === null) $sheetEmpleados = $spreadsheet->getSheetByName('DATOS');

    if ($sheetEmpleados === null) {
        if ($spreadsheet->getSheetCount() > 2) {
            $sheetEmpleados = $spreadsheet->getSheet(2); // fallback hoja 3 visual
        } else {
            $totalErroresGlobal++;
            $nombres = array_map(fn($s) => $s->getTitle(), $spreadsheet->getAllSheets());
            $erroresMensajes[] = "No existe hoja 'Datos'. Disponibles: " . implode(', ', $nombres);
            continue;
        }
    }

    $inicioFila = 8;
    $finFila = ultimaFilaConDatosEnRango($sheetEmpleados, 'B', 'CC', $inicioFila);

    if ($finFila < $inicioFila) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "La hoja '{$sheetEmpleados->getTitle()}' no tiene datos desde fila {$inicioFila}.";
        continue;
    }

    $rows = $sheetEmpleados->rangeToArray(
        "B{$inicioFila}:CC{$finFila}",
        null,
        true,
        false,
        true // claves por columna: B..CC
    );

    // Valor fijo de la fÃ³rmula Excel: MIN($D$4;[@[Fecha Fin Sit. Contract.]])
    $fechaCorteFFinCal = convertirFechaExcel($sheetEmpleados->getCell('D4')->getCalculatedValue());

    $stmtEmp = $db->prepare("
        INSERT INTO datos_empleados (
            id, sexo, fecha_nacimiento, estudios, situacion_familiar, hijos, inicio_contratacion,
            fin_contratacion, fecha_antiguedad, inicio_sit, fin_sit, porc_jornada, porc_reducida,
            motivo_reduccion, clave_contrato, area_empresa, dpto_empresa, puesto_empresa,
            horario, trabajo_turnos, escala_empresa, agrup_class_prof, agrup_valor_pto,
            convenio_area, categoria_profesional, grupo_profesional, nivel, salario, f_fin_cal, prc_normaliz, prc_anualiz, check_equi, salario_base_eq, salario_base_ef,
            grupo_cotizacion_seg_social, ano_registro, id_ano_datos
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    if (!$stmtEmp) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error prepare datos_empleados: " . $db->error;
        continue;
    }

    $v = static function (array $row, string $col) {
        if (!array_key_exists($col, $row)) return null;
        $x = $row[$col];
        if ($x === null) return null;
        if (is_array($x)) return null; // Prevent array to string conversion
        $x = trim((string)$x);
        return $x === '' ? null : $x;
    };

    $minFechaIso = static function (?string $a, ?string $b): ?string {
        if ($a === null) return $b;
        if ($b === null) return $a;
        return ($a <= $b) ? $a : $b;
    };

    $normalizarId = static function (?string $idRaw): ?string {
        if ($idRaw === null) return null;
        $id = strtoupper(trim($idRaw));
        return $id === '' ? null : $id;
    };

    // Replica: =SI(MAX(SI([id]=[@id];[F.fin.cal]))=[@[F.fin.cal]];"Si";"NO")
    $maxFFinCalPorId = [];
    foreach ($rows as $r) {
        $idTmp = $normalizarId($v($r, 'B'));
        $finSitTmp = $v($r, 'L') !== null ? convertirFechaExcel($v($r, 'L')) : null;
        $fFinCalTmp = $minFechaIso($fechaCorteFFinCal, $finSitTmp);
        if ($idTmp === null || $fFinCalTmp === null) {
            continue;
        }
        if (!isset($maxFFinCalPorId[$idTmp]) || $fFinCalTmp > $maxFFinCalPorId[$idTmp]) {
            $maxFFinCalPorId[$idTmp] = $fFinCalTmp;
        }
    }

    foreach ($rows as $filaExcel => $r) {

        $id = $normalizarId($v($r, 'B'));
        // Normalizar sexo: solo 'MUJER' o 'HOMBRE', ignorar valores no reconocidos
        $sexoRaw = $v($r, 'C');
        $sexo = null;
        if ($sexoRaw !== null) {
            $s = strtoupper(trim((string)$sexoRaw));
            if (in_array($s, ['M', 'MUJER', 'F', 'FEMENINO'], true)) {
                $sexo = 'MUJER';
            } elseif (in_array($s, ['H', 'HOMBRE', 'MASCULINO'], true)) {
                $sexo = 'HOMBRE';
            }
        }
        // Si no es 'MUJER' o 'HOMBRE', se deja como null y no se contara
        $fecha_nacimiento = convertirFechaExcel($v($r, 'D'));
        $estudios = $v($r, 'E');
        $situacion_familiar = $v($r, 'F') !== null ? (int)$v($r, 'F') : null;
        $hijos = $v($r, 'G') !== null ? (int)$v($r, 'G') : null;
        $inicio_contratacion = convertirFechaExcel($v($r, 'H'));
        $fin_contratacion = convertirFechaExcel($v($r, 'I'));
        $fecha_antiguedad = convertirFechaExcel($v($r, 'J'));
        $inicio_sit = convertirFechaExcel($v($r, 'K'));
        $fin_sit = convertirFechaExcel($v($r, 'L'));

        $rawJornada = $v($r, 'M');
        $rawReducida = $v($r, 'N');
        $porc_jornada = $rawJornada !== null ? (float)str_replace([',', '%'], ['.', ''], $rawJornada) : null;
        $porc_reducida = $rawReducida !== null ? (float)str_replace([',', '%'], ['.', ''], $rawReducida) : null;

        $motivo_reduccion = $v($r, 'O');
        $clave_contrato = $v($r, 'P') !== null ? (int)$v($r, 'P') : null;
        $area_empresa = $v($r, 'Q');
        $dpto_empresa = $v($r, 'R');
        $puesto_empresa = $v($r, 'S');
        $horarioRaw = $v($r, 'T');
        $horario = null;
        if ($horarioRaw !== null) {
            $horarioNormalizado = strtoupper(trim((string)$horarioRaw));
            if (in_array($horarioNormalizado, ['CONTINUO', 'PARTIDO'], true)) {
                $horario = $horarioNormalizado;
            } elseif (in_array($horarioNormalizado, ['C', 'CONT'], true)) {
                $horario = 'CONTINUO';
            } elseif (in_array($horarioNormalizado, ['P', 'PART'], true)) {
                $horario = 'PARTIDO';
            }
        }

        $trabajoTurnosRaw = $v($r, 'U');
        $trabajo_turnos = null;
        if ($trabajoTurnosRaw !== null) {
            $trabajoTurnosNormalizado = strtoupper(trim((string)$trabajoTurnosRaw));
            if (in_array($trabajoTurnosNormalizado, ['SI', 'NO'], true)) {
                $trabajo_turnos = $trabajoTurnosNormalizado;
            } elseif (in_array($trabajoTurnosNormalizado, ['S', 'TRUE', '1'], true)) {
                $trabajo_turnos = 'SI';
            } elseif (in_array($trabajoTurnosNormalizado, ['N', 'FALSE', '0'], true)) {
                $trabajo_turnos = 'NO';
            }
        }
        $escala_empresa = $v($r, 'V');
        $agrup_class_prof = $v($r, 'W');
        $agrup_valor_pto = $v($r, 'X');
        $convenio_area = $v($r, 'Y');
        $categoria_profesional = $v($r, 'Z');
        $grupo_profesional = $v($r, 'AA');
        $nivel = $v($r, 'AB') !== null ? (int)$v($r, 'AB') : null;
        // Replicar fÃ³rmulas Excel para f_fin_cal y salario_base_eq
        $salario   = $v($r, 'AD') !== null ? (float)$v($r, 'AD') : 0;
        $f_fin_cal = $minFechaIso($fechaCorteFFinCal, $fin_sit);
        
        $normaliz = $v($r, 'BP') !== null ? (float)$v($r, 'BP') : 0;
        $anualiz  = $v($r, 'BQ') !== null ? (float)$v($r, 'BQ') : 0;

        $check_equi = null;
        if ($id !== null && $f_fin_cal !== null && isset($maxFFinCalPorId[$id])) {
            $check_equi = ($maxFFinCalPorId[$id] === $f_fin_cal) ? 'SI' : 'NO';
        }

        if ($normaliz != 0 && $anualiz != 0 && $check_equi !== null) {
            $factor = ($check_equi === 'SI') ? 1 : 0;
            $salario_base_eq = $salario * (1 / $normaliz) * (1 / $anualiz) * $factor;
        } else {
            $salario_base_eq = null;
        }
        $salario_base_ef = $v($r, 'CC') !== null ? (float)$v($r, 'CC') : null;
        $grupo_cotizacion_seg_social = $v($r, 'AC') !== null ? (int)$v($r, 'AC') : null;

        // Saltar fila vacÃ­a
        $todosNull = (
            $id === null && $sexo === null && $fecha_nacimiento === null && $estudios === null &&
            $situacion_familiar === null && $hijos === null && $inicio_contratacion === null &&
            $fin_contratacion === null && $fecha_antiguedad === null && $inicio_sit === null &&
            $fin_sit === null && $porc_jornada === null && $porc_reducida === null &&
            $motivo_reduccion === null && $clave_contrato === null && $area_empresa === null &&
            $dpto_empresa === null && $puesto_empresa === null && $horario === null &&
            $trabajo_turnos === null && $escala_empresa === null && $agrup_class_prof === null &&
            $agrup_valor_pto === null && $convenio_area === null && $categoria_profesional === null &&
            $grupo_profesional === null && $nivel === null && $salario === null && $salario_base_eq === null && $salario_base_ef === null && $grupo_cotizacion_seg_social === null
        );

        if ($todosNull) {
            continue;
        }

        $okBind = $stmtEmp->bind_param(
            "ssssiisssssddsisssssssssssidsddsddisi",
            $id,
            $sexo,
            $fecha_nacimiento,
            $estudios,
            $situacion_familiar,
            $hijos,
            $inicio_contratacion,
            $fin_contratacion,
            $fecha_antiguedad,
            $inicio_sit,
            $fin_sit,
            $porc_jornada,
            $porc_reducida,
            $motivo_reduccion,
            $clave_contrato,
            $area_empresa,
            $dpto_empresa,
            $puesto_empresa,
            $horario,
            $trabajo_turnos,
            $escala_empresa,
            $agrup_class_prof,
            $agrup_valor_pto,
            $convenio_area,
            $categoria_profesional,
            $grupo_profesional,
            $nivel,
            $salario,
            $f_fin_cal,
            $prc_normaliz,
            $prc_anualiz,
            $check_equi,
            $salario_base_eq,
            $salario_base_ef,
            $grupo_cotizacion_seg_social,
            $anoRegistro,
            $id_ano_datos
        );

        if (!$okBind) {
            $totalErroresGlobal++;
            $erroresMensajes[] = "Error bind fila {$filaExcel}: " . $stmtEmp->error;
            continue;
        }

        if (!$stmtEmp->execute()) {
            $totalErroresGlobal++;
            $erroresMensajes[] = "Error insert fila {$filaExcel}: " . $stmtEmp->error;
            continue;
        }

        $totalInsertadasGlobal++;
    }

    $stmtEmp->close();

    if ($debeGenerarDerivados) {
        try {
            $rutaExcelPorcentajes = generarCuadroPorcentajesEmpresa($db, $id_empresa, $id_ano_datos, $razon_social);
            registrarArchivoGeneradoEnTabla(
                $db,
                'CUADRO PORCENTAJES',
                $rutaExcelPorcentajes,
                'uploads/' . basename($rutaExcelPorcentajes),
                'GENERADO PORCENTAJES',
                $id_empresa,
                $idClienteMedidaArchivo
            );
        } catch (\Throwable $e) {
            $totalErroresGlobal++;
            error_log(sprintf('[registro_retributivo.generar_porcentajes] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
            $erroresMensajes[] = "No se pudo generar el cuadro de porcentajes para '{$razon_social}'.";
            continue;
        }

        try {
            $rutaWordPlan = rellenarWordPlanIgualdad($rutaExcelPorcentajes, $razon_social, $anioRegistro, $id_empresa);
            registrarArchivoGeneradoEnTabla(
                $db,
                'REGISTRO_RETRIBUTIVO',
                $rutaWordPlan,
                'uploads/' . basename($rutaWordPlan),
                'GENERADO WORD',
                $id_empresa,
                $idClienteMedidaArchivo
            );
        } catch (\Throwable $e) {
            $totalErroresGlobal++;
            error_log(sprintf('[registro_retributivo.generar_word] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
            $erroresMensajes[] = "No se pudo generar el Word para '{$razon_social}'.";
            continue;
        }
    }
}

// ================== RESPUESTA FINAL ==================
if ($totalInsertadasGlobal > 0 && $totalErroresGlobal === 0) {
    // Actualizar la reunión "Subir R.R" quitando la empresa correspondiente del objetivo
    try {
        $stmtGetReunion = $db->prepare(
            "SELECT r.id_reunion, r.objetivo, u.email, u.nombre_usuario FROM reuniones r 
             INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion 
             INNER JOIN usuario u ON u.id_usuario = ur.id_usuario
             WHERE ur.id_usuario = ? AND r.objetivo LIKE 'Subir R.R%' 
             LIMIT 1"
        );
        if ($stmtGetReunion) {
            $stmtGetReunion->bind_param('i', $usuarioId);
            $stmtGetReunion->execute();
            $resultReunion = $stmtGetReunion->get_result();
            if ($rowReunion = $resultReunion->fetch_assoc()) {
                $idReunion = (int)$rowReunion['id_reunion'];
                $objetivoActual = (string)$rowReunion['objetivo'];
                $emailUsuario = $rowReunion['email'];
                $nombreUsuario = $rowReunion['nombre_usuario'];

                // Extraer empresas del objetivo
                $empresas = [];
                if (stripos($objetivoActual, ' - ') !== false) {
                    $empresasStr = trim(substr($objetivoActual, stripos($objetivoActual, ' - ') + 3));
                    $empresas = array_map('trim', explode(',', $empresasStr));
                }

                // Obtener el nombre de la empresa subida
                $stmtEmpresa = $db->prepare("SELECT razon_social FROM empresa WHERE id_empresa = ? LIMIT 1");
                $stmtEmpresa->bind_param('i', $id_empresa);
                $stmtEmpresa->execute();
                $resEmpresa = $stmtEmpresa->get_result();
                $nombreEmpresaSubida = '';
                if ($rowEmp = $resEmpresa->fetch_assoc()) {
                    $nombreEmpresaSubida = trim((string)($rowEmp['razon_social'] ?? ''));
                }
                $stmtEmpresa->close();

                // Quitar la empresa subida del array
                $empresas = array_filter($empresas, function($e) use ($nombreEmpresaSubida) {
                    return mb_strtoupper($e) !== mb_strtoupper($nombreEmpresaSubida);
                });

                if (empty($empresas)) {
                    // Si ya no quedan empresas, eliminar la reunión
                    $stmtDelUserReunion = $db->prepare("DELETE FROM usuario_reunion WHERE id_reunion = ?");
                    if ($stmtDelUserReunion) {
                        $stmtDelUserReunion->bind_param('i', $idReunion);
                        $stmtDelUserReunion->execute();
                        $stmtDelUserReunion->close();
                    }
                    $stmtDelReunion = $db->prepare("DELETE FROM reuniones WHERE id_reunion = ?");
                    if ($stmtDelReunion) {
                        $stmtDelReunion->bind_param('i', $idReunion);
                        $stmtDelReunion->execute();
                        $stmtDelReunion->close();
                    }
                } else {
                    // Si quedan empresas, actualizar el objetivo
                    $nuevoObjetivo = 'Subir R.R - ' . implode(', ', $empresas);
                    $stmtUpd = $db->prepare("UPDATE reuniones SET objetivo = ? WHERE id_reunion = ?");
                    if ($stmtUpd) {
                        $stmtUpd->bind_param('si', $nuevoObjetivo, $idReunion);
                        $stmtUpd->execute();
                        $stmtUpd->close();
                    }
                }

                // Enviar email de confirmación
                try {
                    correo_enviar_confirmacion_registro_retributivo($emailUsuario, $nombreUsuario);
                } catch (\Throwable $mailError) {
                    registrarLogProcesarRegistroRetributivo("Error al enviar email de recordatorio: " . $mailError->getMessage());
                }
            }
            $stmtGetReunion->close();
        }
    } catch (\Throwable $e) {
        registrarLogProcesarRegistroRetributivo("Error al actualizar/eliminar reunión 'Subir R.R': " . $e->getMessage());
    }
    
    redirigirMenuSubida($urlMenuSubida, 'Subido con Exito', 1, $idEmpresaContexto);
}

if ($tipo === 'TOMA DE DATOS' && $totalArchivosGuardados > 0 && $totalErroresGlobal === 0) {
    redirigirMenuSubida($urlMenuSubida, 'Toma de Datos subida con Exito', 1, $idEmpresaContexto);
}

if ($totalArchivosGuardados > 0 && $totalErroresGlobal === 0) {
    redirigirMenuSubida($urlMenuSubida, 'Archivo procesado correctamente', 1, $idEmpresaContexto);
}

$mensajeError = !empty($erroresMensajes) ? $erroresMensajes[0] : 'No se pudo procesar el archivo.';
redirigirMenuSubida($urlMenuSubida, $mensajeError, 0, $idEmpresaContexto);