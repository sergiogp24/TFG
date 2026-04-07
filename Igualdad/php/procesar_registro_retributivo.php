<?php

declare(strict_types=1);
set_time_limit(0); // Sin límite de tiempo de ejecución



require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';
require __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/generar_cuadro_porcentajes.php';
require_once __DIR__ . '/generar_word_desdeexcel.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

// ================== VALIDACIONES INICIALES ==================
if (!isset($_FILES['excel']) || empty($_POST['tipo'])) {
    die("No se envió archivo o tipo.");
}

$tipo = strtoupper(trim((string)$_POST['tipo']));
$tiposPermitidos = ['REGISTRO_RETRIBUTIVO', 'TOMA DE DATOS'];
if (!in_array($tipo, $tiposPermitidos, true)) {
    die("El tipo debe ser REGISTRO_RETRIBUTIVO o TOMA DE DATOS.");
}

$empresaNombreVista = trim((string)($_POST['nombre_empresa'] ?? ''));
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

// Ajusta esta ruta si tu menú está en otra URL:
$urlMenuSubida = '/Igualdad/html/index_cliente.php';

// Contadores globales
$totalInsertadasGlobal = 0;
$totalArchivosGuardados = 0;
$totalErroresGlobal = 0;
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
            $totalErroresGlobal++;
            $erroresMensajes[] = 'No se pudo determinar la empresa para guardar el archivo de TOMA DE DATOS.';
            continue;
        }

        $empresaNombreToken = preg_replace('/\s+/', '_', $empresaNombreArchivo);
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
    } else {
        $nombreGuardado = 'registro_' . uniqid('', true) . '.' . $ext;
    }
    $rutaCompleta = $uploadDir . $nombreGuardado;

    if (!move_uploaded_file($tmpName, $rutaCompleta)) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error subiendo archivo: {$originalName}";
        continue;
    }

    // ================== Guardar archivo ==================
    $sha256 = hash_file('sha256', $rutaCompleta);
    $size = filesize($rutaCompleta);
    $mime = mime_content_type($rutaCompleta);
    $rutaRelativa = 'uploads/' . $nombreGuardado;

    $stmtArchivo = $db->prepare("
        INSERT INTO archivos 
        (tipo, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmtArchivo) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error prepare archivos: " . $db->error;
        continue;
    }

    $stmtArchivo->bind_param("ssssiis", $tipo, $displayOriginalName, $nombreGuardado, $rutaRelativa, $size, $mime, $sha256);

    if (!$stmtArchivo->execute()) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error insert archivos: " . $stmtArchivo->error;
        $stmtArchivo->close();
        continue;
    }

    $stmtArchivo->close();
    $totalArchivosGuardados++;

    if ($tipo === 'TOMA DE DATOS') {
        // Para TOMA DE DATOS solo guardamos el archivo y su metadato en BD.
        continue;
    }

    // ================== Leer Excel ==================
    try {
        $spreadsheet = IOFactory::load($rutaCompleta);
    } catch (\Throwable $e) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error leyendo Excel: " . $e->getMessage();
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

    // Valor fijo de la fórmula Excel: MIN($D$4;[@[Fecha Fin Sit. Contract.]])
    $fechaCorteFFinCal = convertirFechaExcel($sheetEmpleados->getCell('D4')->getCalculatedValue());

    $stmtEmp = $db->prepare("
        INSERT INTO datos_empleados (
            id, sexo, fecha_nacimiento, estudios, situacion_familiar, hijos, inicio_contratacion,
            fin_contratacion, fecha_antiguedad, inicio_sit, fin_sit, porc_jornada, porc_reducida,
            motivo_reduccion, clave_contrato, area_empresa, dpto_empresa, puesto_empresa,
            horario, trabajo_turnos, escala_empresa, agrup_class_prof, agrup_valor_pto,
            convenio_area, categoria_profesional, grupo_profesional, nivel, salario, f_fin_cal, prc_normaliz, prc_anualiz, check_equi, salario_base_eq, salario_base_ef,
            grupo_cotizacion_seg_social, id_ano_datos
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
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
        // Si no es 'MUJER' o 'HOMBRE', se deja como null y no se contará
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
        $horario = $v($r, 'T') !== null ? strtoupper((string)$v($r, 'T')) : null;
        $trabajo_turnos = $v($r, 'U') !== null ? strtoupper((string)$v($r, 'U')) : null;
        $escala_empresa = $v($r, 'V');
        $agrup_class_prof = $v($r, 'W');
        $agrup_valor_pto = $v($r, 'X');
        $convenio_area = $v($r, 'Y');
        $categoria_profesional = $v($r, 'Z');
        $grupo_profesional = $v($r, 'AA');
        $nivel = $v($r, 'AB') !== null ? (int)$v($r, 'AB') : null;
        // Replicar fórmulas Excel para f_fin_cal y salario_base_eq
        $salario   = $v($r, 'AD') !== null ? (float)$v($r, 'AD') : 0;
        $f_fin_cal = $minFechaIso($fechaCorteFFinCal, $fin_sit);
        
        $normaliz = $v($r, 'BP') !== null ? (float)$v($r, 'BP') : 0;
        $anualiz  = $v($r, 'BQ') !== null ? (float)$v($r, 'BQ') : 0;

        $check_equi = null;
        if ($id !== null && $f_fin_cal !== null && isset($maxFFinCalPorId[$id])) {
            $check_equi = ($maxFFinCalPorId[$id] === $f_fin_cal) ? 'Sí' : 'NO';
        }

        if ($normaliz != 0 && $anualiz != 0 && $check_equi !== null) {
            $factor = in_array(strtolower($check_equi), ['si', 'sí'], true) ? 1 : 0;
            $salario_base_eq = $salario * (1 / $normaliz) * (1 / $anualiz) * $factor;
        } else {
            $salario_base_eq = null;
        }
        $salario_base_ef = $v($r, 'CC') !== null ? (float)$v($r, 'CC') : null;
        $grupo_cotizacion_seg_social = $v($r, 'AC') !== null ? (int)$v($r, 'AC') : null;

        // Saltar fila vacía
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
            "ssssiisssssddsisssssssssssidsddsddii",
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
            $normaliz,
            $anualiz,
            $check_equi,
            $salario_base_eq,
            $salario_base_ef,
            $grupo_cotizacion_seg_social,
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

    try {
        $rutaExcelPorcentajes = generarCuadroPorcentajesEmpresa($db, $id_empresa, $id_ano_datos, $razon_social);
    } catch (\Throwable $e) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error generando cuadro de porcentajes para '{$razon_social}': " . $e->getMessage();
        continue;
    }

    try {
        rellenarWordPlanIgualdad($rutaExcelPorcentajes, $razon_social);
    } catch (\Throwable $e) {
        $totalErroresGlobal++;
        $erroresMensajes[] = "Error generando Word para '{$razon_social}': " . $e->getMessage();
        continue;
    }
}

// ================== RESPUESTA FINAL ==================
if ($totalInsertadasGlobal > 0 && $totalErroresGlobal === 0) {
    header('Location: ' . $urlMenuSubida . '?ok=1&msg=' . urlencode('Subido con éxito'));
    exit;
}

if ($tipo === 'TOMA DE DATOS' && $totalArchivosGuardados > 0 && $totalErroresGlobal === 0) {
    header('Location: ' . $urlMenuSubida . '?ok=1&msg=' . urlencode('Toma de Datos subida con éxito'));
    exit;
}

$mensajeError = !empty($erroresMensajes) ? $erroresMensajes[0] : 'No se pudo procesar el archivo.';
header('Location: ' . $urlMenuSubida . '?ok=0&msg=' . urlencode($mensajeError));
exit;