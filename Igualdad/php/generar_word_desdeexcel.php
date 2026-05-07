<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/generar_cuadro_porcentajes.php';
require_once __DIR__ . '/imagenes_word.php';

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Rellena el Word plantilla SIN romper formato
 */
function rellenarWordPlanIgualdad(string $rutaExcel, string $razonSocial, ?string $anioRegistro = null, ?int $idEmpresa = null, ?string $rutaWordDestino = null, ?string $rutaExcelOriginal = null): string
{
    try {

        $plantillaWord = __DIR__ . '/../assets/MODELO PLAN IGUALDAD.docx';

        if (!file_exists($plantillaWord)) {
            throw new RuntimeException('No existe la plantilla Word.');
        }

        // =========================
        // RUTAS
        // =========================
        $parentDir = dirname(__DIR__);
        $destDirWord = $parentDir . DIRECTORY_SEPARATOR . 'uploads';

        if (!is_dir($destDirWord)) {
            mkdir($destDirWord, 0755, true);
        }

        if ($rutaWordDestino !== null && $rutaWordDestino !== '') {
            $rutaWordFinal = $rutaWordDestino;
            $dirDestino = dirname($rutaWordFinal);
            if (!is_dir($dirDestino)) {
                mkdir($dirDestino, 0755, true);
            }
        } else {
            $nombreArchivoEmpresa = normalizarNombreArchivoEmpresa($razonSocial);
            $nombreArchivoEmpresa = ($anioRegistro !== null && $anioRegistro !== '')
                ? $nombreArchivoEmpresa . '_' . $anioRegistro
                : $nombreArchivoEmpresa;
            $rutaWordFinal = $destDirWord . DIRECTORY_SEPARATOR . $nombreArchivoEmpresa . '_PLAN_IGUALDAD.docx';
        }

        // =========================
        // TEMPLATE (CLAVE)
        // =========================
        $template = new TemplateProcessor($plantillaWord);

        // =========================
        // DATOS EMPRESA
        // =========================
        $reemplazosEmpresa = [];

        if (function_exists('db')) {
            $dbConn = db();
            if ($dbConn instanceof mysqli) {
                $reemplazosEmpresa = obtenerReemplazosEmpresaDesdeBD($dbConn, $razonSocial, $idEmpresa);
            }
        }

        foreach ($reemplazosEmpresa as $clave => $valor) {
            $template->setValue($clave, escaparTextoWord(formatearNumeroConComaSiAplica($valor, false)));
        }

        // Reemplazar año si se proporcionó
        if ($anioRegistro !== null && $anioRegistro !== '') {
            $template->setValue('anioRegistro', $anioRegistro);
        }

        // =========================
        // CUESTIONARIOS DESDE BD
        // =========================
        $valoresCuestionarios = [];
        if (function_exists('db')) {
            $dbConn = db();
            if ($dbConn instanceof mysqli) {
                $valoresCuestionarios = obtenerValoresCuestionariosDesdeBD($dbConn, $razonSocial, $anioRegistro, $idEmpresa);
            }
        }

        // Cada placeholder se reemplaza una sola vez: valor BD si existe, si no cadena vacia.
        $defaultsCuestionarios = array_fill_keys(obtenerCamposCuestionariosConfigurados(), '');
        $cuestionariosFinal = array_merge($defaultsCuestionarios, $valoresCuestionarios);
        foreach ($cuestionariosFinal as $clave => $valor) {
            $template->setValue($clave, escaparTextoWord(formatearNumeroConComaSiAplica($valor, false)));
        }

        // =========================
        // MEDIDAS SELECCIONADAS
        // =========================
        if (function_exists('db')) {
            $dbConn = db();
            if ($dbConn instanceof mysqli) {
                $idEmpresaConsulta = $idEmpresa;
                if (($idEmpresaConsulta === null || $idEmpresaConsulta <= 0) && function_exists('obtenerIdEmpresaPorRazonSocial')) {
                    $idEmpresaConsulta = obtenerIdEmpresaPorRazonSocial($dbConn, $razonSocial);
                }

                if ($idEmpresaConsulta !== null && $idEmpresaConsulta > 0) {
                    $medidasSeleccionadas = obtenerMedidasSelec($dbConn, $idEmpresaConsulta);
                    error_log("DEBUG: Empresa ID=$idEmpresaConsulta, Medidas encontradas: " . count($medidasSeleccionadas));
                    if (!empty($medidasSeleccionadas)) {
                        foreach ($medidasSeleccionadas as $area => $medidas) {
                            error_log("  Area $area: " . count($medidas) . " medidas");
                        }
                    }
                    rellenarMedidasSeleccionadasEnWord($template, $medidasSeleccionadas);

                    // Después de la llamada a rellenarMedidasSeleccionadasEnWord
                    error_log("=== MEDIDAS SELECCIONADAS DEBUG ===");
                    foreach ($medidasSeleccionadas as $area => $medidas) {
                        error_log("Área ID: $area");
                        foreach ($medidas as $idx => $datos) {
                            error_log("  Medida $idx: {$datos['medida']} - {$datos['indicador']}");
                        }
                    }
                }
            }
        }

        // =========================
        // EXCEL Optimiazdo no calcula formulas y salta celdas vacias
        // =========================
        // Aumentar timeout para lectura de Excel grande
        set_time_limit(300);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($rutaExcel);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);  // No leer celdas vacías (más rápido)
        $spreadsheet = $reader->load($rutaExcel);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetIndex = $spreadsheet->getIndex($sheet, true);
            if ($sheetIndex < 0) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $fila = $row->getRowIndex();
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);

                foreach ($cellIterator as $cell) {

                    $col = $cell->getColumn();
                    $valor = leerValorCeldaExcel($cell);

                    $placeholder = $col . $fila . '_' . $sheetIndex;

                    $template->setValue($placeholder, formatearValorWord($valor));
                }
            }
        }

        // =========================
        // TABLAS DINAMICAS
        // =========================
        rellenarTablaDinamicaHoja3($template, $spreadsheet);
        rellenarTablaDinamicaHoja6($template, $spreadsheet);
        rellenarTablaDinamicaHoja7($template, $spreadsheet);
        rellenarTablaDinamicaHoja8($template, $spreadsheet);
        rellenarTablaDinamicaHoja14($template, $spreadsheet);
        rellenarTablaDinamicaHoja20($template, $spreadsheet);
        rellenarTablaDinamicaHoja21($template, $spreadsheet);

        // =========================
        // TABLA DE PROMEDIO (Hoja 6 - Lectura Dinámica)
        // =========================
        // Leer de $rutaExcelOriginal si existe (el Excel del registro retributivo subido),
        // de lo contrario usar el spreadsheet del cuadro de porcentajes
        $datosTablaPromedio = [];
        if ($rutaExcelOriginal !== null && $rutaExcelOriginal !== '' && file_exists($rutaExcelOriginal)) {
            try {
                $readerOriginal = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($rutaExcelOriginal);
                $readerOriginal->setReadDataOnly(true);
                $readerOriginal->setReadEmptyCells(false);
                $spreadsheetOriginal = $readerOriginal->load($rutaExcelOriginal);
                $datosTablaPromedio = leerTablaHoja6Dinamicamente($spreadsheetOriginal);
                $spreadsheetOriginal->disconnectWorksheets();
                unset($spreadsheetOriginal);
            } catch (\Throwable $e) {
                error_log('Error cargando Excel original para tabla de promedio: ' . $e->getMessage());
                // Fallback: intentar con el spreadsheet actual
                $datosTablaPromedio = leerTablaHoja6Dinamicamente($spreadsheet);
            }
        } else {
            // Si no hay Excel original, usar el del cuadro de porcentajes
            $datosTablaPromedio = leerTablaHoja6Dinamicamente($spreadsheet);
        }

        // El placeholder tabla_promedio se resuelve después, directamente sobre el DOCX.

        // =========================
        // IMAGENES DE LA PLANTILLA
        // =========================
        $imagenes = generarImagenesPlantilla($template, $spreadsheet);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // =========================
        // Asegurarnos de que TODAS las medidas no seleccionadas queden vacías o en "0"
        // =========================
        $maxAreas = 20;
        $maxMedidasPorArea = 40;
        
        // Iterar sobre TODAS las áreas y medidas posibles
        for ($area = 1; $area <= $maxAreas; $area++) {
            // Si el área no está seleccionada, blanquear completamente (0 a 40)
            if (!isset($medidasSeleccionadas[$area])) {
                for ($med = 1; $med <= $maxMedidasPorArea; $med++) {
                    $template->setValue('Area' . $area . 'Med' . $med, '');
                    $template->setValue('Area' . $area . 'Med' . $med . 'Ind', '');
                }
            } else {
                // El área SÍ está seleccionada, pero podría tener menos de 40 medidas seleccionadas
                // Contar cuántas medidas fueron realmente seleccionadas en esta área
                $medidasReales = $medidasSeleccionadas[$area];
                $maxMedidaSeleccionada = count($medidasReales);
                
                // Blanquear las medidas NO seleccionadas (de maxMedidaSeleccionada+1 a 40)
                for ($med = $maxMedidaSeleccionada + 1; $med <= $maxMedidasPorArea; $med++) {
                    $template->setValue('Area' . $area . 'Med' . $med, '');
                    $template->setValue('Area' . $area . 'Med' . $med . 'Ind', '');
                }
            }
        }

        // =========================
        // GUARDAR
        // =========================
        if (file_exists($rutaWordFinal)) {
            @unlink($rutaWordFinal);
        }
        $template->saveAs($rutaWordFinal);

        // Post-procesamiento: insertar tabla de promedio si existe
        if (!empty($datosTablaPromedio)) {
            try {
                procesarTablaPromediaEnDocx($rutaWordFinal, $datosTablaPromedio);
            } catch (\Throwable $e) {
                error_log('Advertencia: No se pudo insertar tabla de promedio: ' . $e->getMessage());
                // Continuar sin insertar la tabla, el documento está guardado con el placeholder
            }
        }

        $mensajeRuta = 'DEBUG WORD: archivo generado en ' . $rutaWordFinal;
        error_log($mensajeRuta);
        if (isset($debugWrite) && is_callable($debugWrite)) {
            $debugWrite($mensajeRuta);
        }

        // Limpiar imágenes temporales
        foreach ($imagenes as $img) {
            if (file_exists($img)) {
                unlink($img);
            }
        }

        return $rutaWordFinal;
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al generar Word: ' . $e->getMessage());
    }
}

/**
 * ESCAPAR TEXTO PARA WORD
 */
function escaparTextoWord($valor): string
{
    if (is_array($valor)) {
        $valor = implode(', ', array_map(static function ($item): string {
            if ($item === null) {
                return '';
            }
            if (is_scalar($item)) {
                return (string)$item;
            }
            return (string)json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $valor));
    } elseif (is_object($valor) && !method_exists($valor, '__toString')) {
        $valor = (string)json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Formatea numeros con coma decimal cuando aplica.
 */
function formatearNumeroConComaSiAplica($valor, bool $vacioComoCero = true): string
{
    if ($valor === null || $valor === '') {
        return $vacioComoCero ? '0' : '';
    }

    if (is_array($valor)) {
        return escaparTextoWord($valor);
    }

    if (is_object($valor)) {
        if (method_exists($valor, '__toString')) {
            $valor = (string)$valor;
        } else {
            return escaparTextoWord($valor);
        }
    }

    if (is_numeric($valor)) {
        $textoOriginal = (string)$valor;
        if (is_float($valor) || strpos($textoOriginal, '.') !== false) {
            $texto = number_format((float)$valor, 2, '.', '');
            $texto = rtrim(rtrim($texto, '0'), '.');
            return reemplazarPuntoDecimalEnTexto($texto);
        }

        return reemplazarPuntoDecimalEnTexto($textoOriginal);
    }

    return reemplazarPuntoDecimalEnTexto((string)$valor);
}

/**
 * Reemplaza el separador decimal punto por coma cuando aparece entre digitos.
 */
function reemplazarPuntoDecimalEnTexto(string $texto): string
{
    return preg_replace('/(?<=\d)\.(?=\d)/', ',', $texto) ?? $texto;
}

/**
 * FORMATEAR VALORES
 */
function formatearValorWord($valor): string
{
    if ($valor === null || $valor === '' || (is_string($valor) && strpos($valor, '#') !== false)) {
        return '0';
    }

    return formatearNumeroConComaSiAplica($valor, true);
}

/**
 * Lee una celda usando el valor cacheado y solo recalcula si es formula.
 */
function leerValorCeldaExcel($cell)
{
    if (is_object($cell) && method_exists($cell, 'isFormula') && $cell->isFormula()) {
        return $cell->getCalculatedValue();
    }

    if (is_object($cell) && method_exists($cell, 'getValue')) {
        return $cell->getValue();
    }

    return null;
}

function esCeroNumerico($valor): bool
{
    if (is_int($valor) || is_float($valor)) {
        return (float)$valor == 0.0;
    }

    if (is_string($valor)) {
        $v = trim(str_replace(' ', '', $valor));
        if ($v === '') {
            return false;
        }

        $v = str_replace(',', '.', $v);
        if (is_numeric($v)) {
            return (float)$v == 0.0;
        }
    }

    return false;
}

/**
 * DATOS EMPRESA
 */
function obtenerReemplazosEmpresaDesdeBD(mysqli $db, string $razonSocial, ?int $idEmpresa = null): array
{
    $empresa = [];

    if ($idEmpresa !== null && $idEmpresa > 0) {
        $stmt = $db->prepare("SELECT * FROM empresa WHERE id_empresa = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $idEmpresa);
            $stmt->execute();
            $empresa = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }

    if ($empresa === []) {
        $stmt = $db->prepare("\n        SELECT * FROM empresa\n        WHERE UPPER(TRIM(razon_social)) = ?\n        LIMIT 1\n    ");

        if ($stmt) {
            $razonSocial = mb_strtoupper(trim($razonSocial));
            $stmt->bind_param('s', $razonSocial);
            $stmt->execute();

            $empresa = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }

    if ($empresa === []) {
        return [];
    }

    $idEmpresaReal = (int)($empresa['id_empresa'] ?? 0);
    $cnaes = [];
    if ($idEmpresaReal > 0) {
        $cnaes = obtenerCnaesEmpresaDesdeBD($db, $idEmpresaReal);
        if (!empty($cnaes)) {
            $empresa['cnae_list'] = $cnaes;
            $empresa['cnae'] = implode(', ', $cnaes);
        } elseif (isset($empresa['cnae'])) {
            $cnaeLegacy = trim((string)$empresa['cnae']);
            if ($cnaeLegacy !== '') {
                $empresa['cnae_list'] = [$cnaeLegacy];
                $empresa['cnae'] = $cnaeLegacy;
            }
        }
    }

    return $empresa;
}

function obtenerCnaesEmpresaDesdeBD(mysqli $db, int $idEmpresa): array
{
    if ($idEmpresa <= 0) {
        return [];
    }

    $stmt = $db->prepare("SELECT nombre FROM cnae WHERE id_empresa = ? ORDER BY id ASC");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $result = $stmt->get_result();

    $cnaes = [];
    while ($fila = $result->fetch_assoc()) {
        $nombre = trim((string)($fila['nombre'] ?? ''));
        if ($nombre !== '') {
            $cnaes[] = $nombre;
        }
    }

    $stmt->close();

    return $cnaes;
}

function obtenerMedidasSelec(mysqli $db, int $idEmpresa): array
{
    if ($idEmpresa <= 0) {
        error_log("DEBUG: ID empresa inválido: " . $idEmpresa);
        return [];
    }

    $stmt = $db->prepare(
        "SELECT
            ap.id_plan,
            ap.nombre AS nombre_plan,
            m.id_medida,
            m.descripcion AS medida_seleccionada,
            m.indicador AS indicador_seleccionado,
            cm.id_cliente_medida
        FROM areas_contratadas ac
        INNER JOIN area_plan ap ON ac.id_plan = ap.id_plan
        INNER JOIN cliente_medida cm ON ac.id_areas_contratadas = cm.id_areas_contratadas
        INNER JOIN medida m ON cm.id_medida = m.id_medida
        WHERE ac.id_empresa = ?
        ORDER BY ap.id_plan, m.id_medida, cm.id_cliente_medida"
    );
    
    if (!$stmt) {
        error_log("ERROR: Falló la preparación de la consulta: " . $db->error);
        return [];
    }

    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $result = $stmt->get_result();

    $medidasPorArea = [];
    $idPlanActual = null;
    $indiceMedida = 0;
    
    while ($fila = $result->fetch_assoc()) {
        $idPlan = (int)($fila['id_plan'] ?? 0);
        
        // Si cambiamos de área, reiniciamos el índice de medida
        if ($idPlan !== $idPlanActual) {
            $idPlanActual = $idPlan;
            $indiceMedida = 0;
            if (!isset($medidasPorArea[$idPlan])) {
                $medidasPorArea[$idPlan] = [];
            }
        }

        $medida = trim((string)($fila['medida_seleccionada'] ?? ''));
        $indicador = trim((string)($fila['indicador_seleccionado'] ?? ''));
        
        if ($medida !== '' || $indicador !== '') {
            $indiceMedida++;
            $medidasPorArea[$idPlan][$indiceMedida] = [
                'medida' => $medida,
                'indicador' => $indicador,
            ];
            error_log("DEBUG BD: Área $idPlan, Medida $indiceMedida: " . substr($medida, 0, 50));
        }
    }

    $stmt->close();
    
    error_log("DEBUG BD: Total áreas encontradas: " . count($medidasPorArea));
    
    return $medidasPorArea;
}

/**
 * Reemplaza placeholders del tipo Area1Med1, Area1Med2, etc. con las medidas seleccionadas.
 */
function rellenarMedidasSeleccionadasEnWord(TemplateProcessor $template, array $medidasPorArea): void
{
    $maxMedidasPorArea = 40;

    // Rellenar con los datos reales usando los IDs de área REALES de la BD
    foreach ($medidasPorArea as $idPlanReal => $medidas) {
        if (!is_array($medidas)) {
            continue;
        }

        error_log("DEBUG WORD: Procesando área real ID: " . $idPlanReal . " con " . count($medidas) . " medidas");

        foreach ($medidas as $indiceMedida => $datosMedida) {
            if (!is_array($datosMedida)) {
                continue;
            }

            // Placeholders SIN ${} - TemplateProcessor los maneja automáticamente
            $placeholderMedida = 'Area' . $idPlanReal . 'Med' . $indiceMedida;
            $placeholderIndicador = 'Area' . $idPlanReal . 'Med' . $indiceMedida . 'Ind';

            $textoMedida = (string)($datosMedida['medida'] ?? '');
            $textoIndicador = (string)($datosMedida['indicador'] ?? '');

            error_log("DEBUG WORD: Seteando '{$placeholderMedida}' = '" . substr($textoMedida, 0, 50) . "...'");
            error_log("DEBUG WORD: Seteando '{$placeholderIndicador}' = '" . substr($textoIndicador, 0, 50) . "...'");

            // Usar setValue directamente
            $template->setValue($placeholderMedida, escaparTextoWord($textoMedida));
            $template->setValue($placeholderIndicador, escaparTextoWord($textoIndicador));
        }
    }
    
    // Debug: Verificar que los placeholders se establecieron
    error_log("DEBUG WORD: Total áreas procesadas: " . count($medidasPorArea));
}



/**
 * Obtiene los valores de cuestionarios permitidos para una empresa.
 * Prioriza la fila del año solicitado y, si no existe, usa la más reciente de la empresa.
 */
function obtenerValoresCuestionariosDesdeBD(mysqli $db, string $razonSocial, ?string $anioRegistro, ?int $idEmpresaForzado = null): array
{
    $idEmpresa = ($idEmpresaForzado !== null && $idEmpresaForzado > 0)
        ? $idEmpresaForzado
        : obtenerIdEmpresaPorRazonSocial($db, $razonSocial);
    if ($idEmpresa === null) {
        return [];
    }

    $anio = extraerAnioDesdeTexto($anioRegistro);
    $configCuestionarios = obtenerConfigCuestionarios();
    $valores = [];

    foreach ($configCuestionarios as $tabla => $campos) {
        $camposUnicos = array_values(array_unique(array_filter($campos, static fn($campo) => is_string($campo) && $campo !== '')));
        if ($camposUnicos === []) {
            continue;
        }

        $columnasDisponibles = obtenerColumnasTabla($db, $tabla);
        if ($columnasDisponibles === []) {
            continue;
        }

        $camposValidos = array_values(array_filter(
            $camposUnicos,
            static fn(string $campo): bool => isset($columnasDisponibles[$campo])
        ));

        if ($camposValidos === []) {
            continue;
        }

        $select = implode(', ', array_map(static fn(string $campo): string => "`{$campo}`", $camposValidos));
        $fila = [];

        if ($anio !== null) {
            $sqlAnio = "
                SELECT {$select}
                FROM `{$tabla}` q
                INNER JOIN ano_datos ad ON ad.id_ano_datos = q.id_ano_datos
                WHERE q.id_empresa = ?
                  AND (YEAR(ad.fecha_inicio) = ? OR YEAR(ad.fecha_fin) = ?)
                ORDER BY q.id_ano_datos DESC
                LIMIT 1
            ";
            $stmtAnio = $db->prepare($sqlAnio);
            if ($stmtAnio) {
                $stmtAnio->bind_param('iii', $idEmpresa, $anio, $anio);
                $stmtAnio->execute();
                $fila = $stmtAnio->get_result()->fetch_assoc() ?: [];
                $stmtAnio->close();
            }
        }

        if ($fila === []) {
            $sqlUltima = "SELECT {$select} FROM `{$tabla}` WHERE id_empresa = ? ORDER BY id_ano_datos DESC LIMIT 1";
            $stmtUltima = $db->prepare($sqlUltima);
            if ($stmtUltima) {
                $stmtUltima->bind_param('i', $idEmpresa);
                $stmtUltima->execute();
                $fila = $stmtUltima->get_result()->fetch_assoc() ?: [];
                $stmtUltima->close();
            }
        }

        foreach ($camposValidos as $campo) {
            if (array_key_exists($campo, $fila) && $fila[$campo] !== null && $fila[$campo] !== '') {
                $valores[$campo] = $fila[$campo];
            }
        }
    }

    return $valores;
}

/**
 * Devuelve las columnas reales de una tabla para evitar SELECT con columnas inexistentes.
 *
 * @return array<string, true>
 */
function obtenerColumnasTabla(mysqli $db, string $tabla): array
{
    static $cache = [];

    if (isset($cache[$tabla])) {
        return $cache[$tabla];
    }

    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $cache[$tabla] = [];
        return $cache[$tabla];
    }

    $stmt->bind_param('s', $tabla);
    $stmt->execute();
    $result = $stmt->get_result();

    $columnas = [];
    while ($fila = $result->fetch_assoc()) {
        if (isset($fila['COLUMN_NAME']) && $fila['COLUMN_NAME'] !== '') {
            $columnas[(string)$fila['COLUMN_NAME']] = true;
        }
    }

    $stmt->close();
    $cache[$tabla] = $columnas;

    return $cache[$tabla];
}

/**
 * Busca el id_empresa a partir de la razón social.
 */
function obtenerIdEmpresaPorRazonSocial(mysqli $db, string $razonSocial): ?int
{
    $stmt = $db->prepare("SELECT id_empresa FROM empresa WHERE UPPER(TRIM(razon_social)) = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $razonSocialNormalizada = mb_strtoupper(trim($razonSocial));
    $stmt->bind_param('s', $razonSocialNormalizada);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila || !isset($fila['id_empresa'])) {
        // Fallback tolerante: compara razón social normalizada para evitar fallos por espacios/acentos/puntuación.
        $sql = "SELECT id_empresa, razon_social FROM empresa";
        $res = $db->query($sql);
        if (!$res) {
            return null;
        }

        $objetivo = normalizarRazonSocialComparacion($razonSocial);
        while ($row = $res->fetch_assoc()) {
            if (!isset($row['id_empresa'], $row['razon_social'])) {
                continue;
            }

            if (normalizarRazonSocialComparacion((string)$row['razon_social']) === $objetivo) {
                return (int)$row['id_empresa'];
            }
        }

        return null;
    }

    return (int)$fila['id_empresa'];
}

function extraerAnioDesdeTexto(?string $texto): ?int
{
    if ($texto === null) {
        return null;
    }

    $valor = trim($texto);
    if ($valor === '') {
        return null;
    }

    if (preg_match('/\b(\d{4})\b/', $valor, $m) === 1) {
        return (int)$m[1];
    }

    return null;
}

function normalizarRazonSocialComparacion(string $texto): string
{
    $texto = mb_strtoupper(trim($texto));
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    $texto = preg_replace('/[^\p{L}\p{N}]+/u', '', $texto) ?? $texto;
    return $texto;
}

/**
 * Rellena una tabla dinámica desde una hoja concreta.
 */
function rellenarTablaDinamicaPorConfig(
    TemplateProcessor $template,
    \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
    array $cfg,
    string $nombreHoja
): void {
    if ($cfg['indiceHoja'] < 0 || $cfg['indiceHoja'] >= $spreadsheet->getSheetCount()) {
        return;
    }

    $sheet = $spreadsheet->getSheet((int)$cfg['indiceHoja']);
    $rows = [];
    $colCategoriaPrincipal = (string)$cfg['columnas'][$cfg['ancla']];

    for ($fila = (int)$cfg['filaInicio']; $fila <= (int)$cfg['filaFin']; $fila++) {
        $categoriaPrincipalRaw = leerValorCeldaExcel($sheet->getCell($colCategoriaPrincipal . $fila));
        $categoriaAltRaw = leerValorCeldaExcel($sheet->getCell($cfg['colCategoriaAlt'] . $fila));
        $categoriaPrincipal = trim((string)$categoriaPrincipalRaw);
        $categoriaAlt = trim((string)$categoriaAltRaw);

        if ($categoriaPrincipal === '' && $categoriaAlt === '') {
            continue;
        }

        $categoriaBase = $categoriaPrincipal !== '' ? $categoriaPrincipal : $categoriaAlt;
        $filaData = [];

        foreach ($cfg['columnas'] as $placeholder => $columna) {
            $valor = leerValorCeldaExcel($sheet->getCell($columna . $fila));
            $filaData[$placeholder] = formatearValorWord($valor);
        }

        $filaData[$cfg['ancla']] = formatearValorWord($categoriaBase);
        $rows[] = $filaData;
    }

    if ($rows === []) {
        return;
    }

    try {
        $template->cloneRowAndSetValues($cfg['ancla'], $rows);
    } catch (\Throwable $e) {
        error_log('Tabla dinámica ' . $nombreHoja . ' omitida: ' . $e->getMessage());
    }
}

function rellenarTablaDinamicaHoja3(TemplateProcessor $template, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
{
    $cfg = [
        'indiceHoja' => 3,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategoriaAlt' => 'A',
        'columnas' => [
            'f3_c' => 'B',
            'f3_m' => 'C',
            'f3_dm' => 'D',
            'f3_cm' => 'E',
            'f3_pm' => 'F',
            'f3_h' => 'G',
            'f3_dh' => 'H',
            'f3_ch' => 'I',
            'f3_ph' => 'J',
            'f3_pt' => 'K',
            'f3_tf' => 'L',
            'f3_bg' => 'M',
            'f3_if' => 'N',
        ],
        'ancla' => 'f3_c',
    ];

    rellenarTablaDinamicaPorConfig($template, $spreadsheet, $cfg, 'hoja 3');
}

function rellenarTablaDinamicaHoja6(TemplateProcessor $template, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
{
    $cfg = [
        'indiceHoja' => 6,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategoriaAlt' => 'A',
        'columnas' => [
            'f6_c' => 'B',
            'f6_m' => 'C',
            'f6_dm' => 'D',
            'f6_cm' => 'E',
            'f6_pm' => 'F',
            'f6_h' => 'G',
            'f6_dh' => 'H',
            'f6_ch' => 'I',
            'f6_ph' => 'J',
            'f6_pt' => 'K',
            'f6_tf' => 'L',
            'f6_bg' => 'M',
            'f6_if' => 'N',
        ],
        'ancla' => 'f6_c',
    ];

    rellenarTablaDinamicaPorConfig($template, $spreadsheet, $cfg, 'hoja 6');
}

function rellenarTablaDinamicaHoja7(TemplateProcessor $template, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
{
    $cfg = [
        'indiceHoja' => 7,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategoriaAlt' => 'A',
        'columnas' => [
            'f7_c' => 'B',
            'f7_m' => 'C',
            'f7_dm' => 'D',
            'f7_cm' => 'E',
            'f7_pm' => 'F',
            'f7_h' => 'G',
            'f7_dh' => 'H',
            'f7_ch' => 'I',
            'f7_ph' => 'J',
            'f7_pt' => 'K',
            'f7_tf' => 'L',
            'f7_bg' => 'M',
            'f7_if' => 'N',
        ],
        'ancla' => 'f7_c',
    ];

    rellenarTablaDinamicaPorConfig($template, $spreadsheet, $cfg, 'hoja 7');
}

function rellenarTablaDinamicaHoja8(TemplateProcessor $template, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
{
    $cfg = [
        'indiceHoja' => 8,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategoriaAlt' => 'A',
        'columnas' => [
            'f8_c' => 'B',
            'f8_m' => 'C',
            'f8_dm' => 'D',
            'f8_cm' => 'E',
            'f8_pm' => 'F',
            'f8_h' => 'G',
            'f8_dh' => 'H',
            'f8_ch' => 'I',
            'f8_ph' => 'J',
            'f8_pt' => 'K',
            'f8_tf' => 'L',
            'f8_bg' => 'M',
            'f8_if' => 'N',
        ],
        'ancla' => 'f8_c',
    ];

    rellenarTablaDinamicaPorConfig($template, $spreadsheet, $cfg, 'hoja 8');
}

function rellenarTablaDinamicaHoja14(TemplateProcessor $template, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
{
    $cfg = [
        'indiceHoja' => 14,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategoriaAlt' => 'A',
        'columnas' => [
            'f14_c' => 'B',
            'f14_m' => 'C',
            'f14_dm' => 'D',
            'f14_cm' => 'E',
            'f14_pm' => 'F',
            'f14_h' => 'G',
            'f14_dh' => 'H',
            'f14_ch' => 'I',
            'f14_ph' => 'J',
            'f14_pt' => 'K',
            'f14_tf' => 'L',
            'f14_bg' => 'M',
            'f14_if' => 'N',
        ],
        'ancla' => 'f14_c',
    ];

    rellenarTablaDinamicaPorConfig($template, $spreadsheet, $cfg, 'hoja 14');
}

function rellenarTablaDinamicaHoja20(TemplateProcessor $template, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
{
    $cfg = [
        'indiceHoja' => 20,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategoriaAlt' => 'A',
        'columnas' => [
            'f20_c' => 'B',
            'f20_m' => 'C',
            'f20_dm' => 'D',
            'f20_cm' => 'E',
            'f20_pm' => 'F',
            'f20_h' => 'G',
            'f20_dh' => 'H',
            'f20_ch' => 'I',
            'f20_ph' => 'J',
            'f20_pt' => 'K',
            'f20_tf' => 'L',
            'f20_bg' => 'M',
            'f20_if' => 'N',
        ],
        'ancla' => 'f20_c',
    ];

    rellenarTablaDinamicaPorConfig($template, $spreadsheet, $cfg, 'hoja 20');
}

function rellenarTablaDinamicaHoja21(TemplateProcessor $template, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
{
    $cfg = [
        'indiceHoja' => 21,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategoriaAlt' => 'A',
        'columnas' => [
            'f21_c' => 'B',
            'f21_m' => 'C',
            'f21_dm' => 'D',
            'f21_cm' => 'E',
            'f21_pm' => 'F',
            'f21_h' => 'G',
            'f21_dh' => 'H',
            'f21_ch' => 'I',
            'f21_ph' => 'J',
            'f21_pt' => 'K',
            'f21_tf' => 'L',
            'f21_bg' => 'M',
            'f21_if' => 'N',
        ],
        'ancla' => 'f21_c',
    ];

    rellenarTablaDinamicaPorConfig($template, $spreadsheet, $cfg, 'hoja 21');
}

/**
 * Configuracion centralizada de tablas y campos de cuestionarios.
 *
 * @return array<string, array<int, string>>
 */
function obtenerConfigCuestionarios(): array
{
    return [
        'cuestionario_seleccion_personal' => [
            'factores_determinantes',
            'incorporacion_nuevo_personal',
            'publicacion_interna',
            'personas_responsables',
            'caracteristicas_candidaturas',
            'entrevista_salida',
            'sistema_reclutamiento',
            'definicion_perfiles',
            'metodos_seleccion',
            'ultima_decision',
            'barreras_internas_externas',
        ],
        'cuestionario_promocion_profesional' => [
            'metodologia',
            'metodologia_evaluacion',
            'personas_intervienen',
            'formacion_ligada',
            'acciones_fomentar',
            'requisitos',
            'planes_carrera',
            'comunicacion_vacantes',
            'dificultades_promocion',
        ],
        'cuestionario_formacion' => [
            'deteccion_formativas',
            'difusion_ofertas',
            'puede_solicitar',
            'compensacion_fuera',
            'posibilidad_formacion',
            'formacion_mujeres',
            'existencia_plan',
            'asisten_igualmente',
            'criterios_seleccion',
            'impartacion_fuera',
            'ayudas_formacion',
            'formacion_igualdad',
            'coste_medio',
            'formacion_reciclaje',
        ],
        'cuestionario_conciliacion_corresponsabilidad' => [
            'ordenacion_tiempo',
            'quienes_utilizan',
            'reduccion_jornada',
            'mecanismos_disponibles',
            'cuantas_personas',
            'canales_informacion',
        ],
        'cuestionario_infrarrepresentacion_femenina' => [
            'barreras_internas',
            'hay_mujeres',
        ],
        'cuestionario_salud_laboral' => [
            'seguridad_salud',
            'medidas_linea',
            'incluido_perspectiva',
            'permite_desconexion',
        ],
        'cuestionario_prevencion_acoso_sexual' => [
            'conocen_acoso',
            'protocolo_prevencion',
            'medidas_sensibilizacion',
        ],
        'cuestionario_violencia_genero' => [
            'conocimiento_contratada',
            'prevision_progama',
        ],
        'cuestionario_comunicacion_identidad_corporativa' => [
            'canales_comunicacion',
            'campanas_comunicacion',
            'imagen_empresa',
            'existencia_comunicacion',
            'frecuencia',
            'lenguaje_imagen',
            'objetivos',
            'filosofia',
            'procesos_calidad',
            'responsabilidad_social',
        ],
    ];
}

/**
 * Devuelve todos los placeholders de cuestionarios definidos en configuracion.
 *
 * @return array<int, string>
 */
function obtenerCamposCuestionariosConfigurados(): array
{
    $campos = [];
    foreach (obtenerConfigCuestionarios() as $listaCampos) {
        foreach ($listaCampos as $campo) {
            if (is_string($campo) && $campo !== '') {
                $campos[$campo] = true;
            }
        }
    }

    return array_keys($campos);
}

/**
 * Lee dinámicamente la tabla de la hoja 6 desde B8 en adelante.
 * Determina automáticamente las filas y columnas con datos.
 * 
 * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
 * @return array<int, array<string, mixed>>
 */
function leerTablaHoja6Dinamicamente(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
{
    if ($spreadsheet->getSheetCount() <= 6) {
        return [];
    }

    try {
        $sheet = $spreadsheet->getSheet(6);
        $datos = [];
        
        // Empezar desde fila 8, columna B
        $filaInicio = 8;
        $colInicio = 'B';
        $filaFin = $sheet->getHighestDataRow();
        $colFin = $sheet->getHighestColumn();
        
        // Si no hay datos, retornar vacío
        if ($filaFin < $filaInicio) {
            return [];
        }
        
        // Leer encabezados de la primera fila (fila 8)
        $encabezados = [];
        $colIndice = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colInicio);
        $colFinIndice = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colFin);
        
        for ($c = $colIndice; $c <= $colFinIndice; $c++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $valor = leerValorCeldaExcel($sheet->getCell($col . $filaInicio));
            $valorStr = convertirValorAStringSeguro($valor);
            if ($valorStr !== '') {
                $encabezados[$col] = $valorStr;
            }
        }
        
        if (empty($encabezados)) {
            return [];
        }
        
        // Leer filas de datos (desde fila 9 en adelante)
        for ($fila = $filaInicio + 1; $fila <= $filaFin; $fila++) {
            $filaData = [];
            $tieneAlgunDato = false;
            
            foreach ($encabezados as $col => $encabezado) {
                $valor = leerValorCeldaExcel($sheet->getCell($col . $fila));
                $valorStr = convertirValorAStringSeguro($valor);

                if ($valorStr !== '') {
                    $tieneAlgunDato = true;
                    $filaData[$encabezado] = formatearValorWord($valorStr);
                } else {
                    $filaData[$encabezado] = '';
                }
            }
            
            // Solo agregar fila si tiene al menos un dato
            if ($tieneAlgunDato) {
                $datos[] = $filaData;
            }
        }
        
        return $datos;
    } catch (\Throwable $e) {
        error_log('Error leyendo tabla hoja 6: ' . $e->getMessage());
        return [];
    }
}
/**
 * Genera una tabla HTML5 a partir de los datos leídos.
 * Diseñada para ser compatible con Word y mostrar correctamente.
 * 
 * @param array<int, array<string, mixed>> $datosTabla
 * @return string HTML de la tabla
 */
function generarTablaPromediaHTML(array $datosTabla): string
{
    if (empty($datosTabla)) {
        return '';
    }
    
    $encabezados = array_keys($datosTabla[0]);
    
    $html = '<table border="1" cellpadding="5" cellspacing="0">' . PHP_EOL;
    
    // Encabezados
    $html .= '<tr>' . PHP_EOL;
    foreach ($encabezados as $encabezado) {
        $html .= '<td style="background-color:#D3D3D3; text-align:center; font-weight:bold;">' 
            . htmlspecialchars((string)$encabezado, ENT_QUOTES, 'UTF-8') 
            . '</td>' . PHP_EOL;
    }
    $html .= '</tr>' . PHP_EOL;
    
    // Filas de datos
    foreach ($datosTabla as $fila) {
        $html .= '<tr>' . PHP_EOL;
        foreach ($encabezados as $encabezado) {
            $valor = $fila[$encabezado] ?? '';
            $valorStr = convertirValorAStringSeguro($valor);
            
            $esNumerico = is_numeric($valorStr) && $valorStr !== '';
            $alineacion = $esNumerico ? 'right' : 'left';
            
            $html .= '<td style="text-align:' . $alineacion . ';">'
                . htmlspecialchars($valorStr, ENT_QUOTES, 'UTF-8')
                . '</td>' . PHP_EOL;
        }
        $html .= '</tr>' . PHP_EOL;
    }
    
    $html .= '</table>' . PHP_EOL;
    return $html;
}
/**
 * Post-procesa el DOCX para insertar la tabla de promedio en el XML.
 * Busca ${tabla_promedio} en document.xml y lo reemplaza con una tabla Word XML.
 * 
 * @param string $rutaDocx Ruta al archivo DOCX
 * @param array<int, array<string, mixed>> $datosTabla Datos de la tabla
 * @throws RuntimeException
 */
function procesarTablaPromediaEnDocx(string $rutaDocx, array $datosTabla): void
{
    if (!file_exists($rutaDocx)) {
        throw new RuntimeException('El archivo DOCX no existe: ' . $rutaDocx);
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive no está disponible');
    }

    if (empty($datosTabla)) {
        return;
    }

    // Crear temporal para descomprimir
    $zip = new \ZipArchive();
    if ($zip->open($rutaDocx) !== true) {
        throw new RuntimeException('No se pudo abrir el DOCX: ' . $rutaDocx);
    }

    // Leer document.xml
    $xmlContent = $zip->getFromName('word/document.xml');
    if ($xmlContent === false) {
        $zip->close();
        throw new RuntimeException('No se encontró word/document.xml en el DOCX');
    }

    // Generar tabla en XML de Word
    $tablaXml = generarTablaPromediaXML($datosTabla);

    // Reemplazar el párrafo completo que contiene el placeholder por la tabla XML.
    // El placeholder está dividido en varios <w:t>, por lo que no se puede sustituir solo el texto.
    $patron = '/<w:p\b[^>]*>.*?\$\{.*?tabla_promedio.*?\}.*?<\/w:p>/s';
    $xmlContentNuevo = preg_replace($patron, $tablaXml, $xmlContent, 1);

    if ($xmlContentNuevo === null || $xmlContentNuevo === $xmlContent) {
        $zip->close();
        throw new RuntimeException('No se pudo reemplazar el párrafo de la tabla_promedio en document.xml');
    }

    $xmlContent = $xmlContentNuevo;

    // Escribir de vuelta al DOCX
    $zip->addFromString('word/document.xml', $xmlContent);
    $zip->close();
}

/**
 * Genera una tabla en formato XML de Word 2007+ (w:tbl).
 * 
 * @param array<int, array<string, mixed>> $datosTabla
 * @return string XML válido para insertar en document.xml
 */
function generarTablaPromediaXML(array $datosTabla): string
{
    if (empty($datosTabla)) {
        return '';
    }

    $encabezados = array_keys($datosTabla[0]);
    $numColumnas = count($encabezados);
    $anchoColumna = (int)(9000 / max(1, $numColumnas));

    $xml = '<w:tbl xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" 
                  xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml">' . PHP_EOL;

    // Propiedades de la tabla
    $xml .= '<w:tblPr>' . PHP_EOL;
    $xml .= '<w:tblW w:w="9000" w:type="dxa"/>' . PHP_EOL;
    $xml .= '<w:tblBorders>' . PHP_EOL;
    $xml .= '<w:top w:val="single" w:sz="12" w:space="0" w:color="000000"/>' . PHP_EOL;
    $xml .= '<w:left w:val="single" w:sz="12" w:space="0" w:color="000000"/>' . PHP_EOL;
    $xml .= '<w:bottom w:val="single" w:sz="12" w:space="0" w:color="000000"/>' . PHP_EOL;
    $xml .= '<w:right w:val="single" w:sz="12" w:space="0" w:color="000000"/>' . PHP_EOL;
    $xml .= '<w:insideH w:val="single" w:sz="12" w:space="0" w:color="000000"/>' . PHP_EOL;
    $xml .= '<w:insideV w:val="single" w:sz="12" w:space="0" w:color="000000"/>' . PHP_EOL;
    $xml .= '</w:tblBorders>' . PHP_EOL;
    $xml .= '</w:tblPr>' . PHP_EOL;

    // Grid
    $xml .= '<w:tblGrid>' . PHP_EOL;
    for ($i = 0; $i < $numColumnas; $i++) {
        $xml .= '<w:gridCol w:w="' . $anchoColumna . '"/>' . PHP_EOL;
    }
    $xml .= '</w:tblGrid>' . PHP_EOL;

    // Encabezado
    $xml .= '<w:tr>' . PHP_EOL;
    $xml .= '<w:trPr><w:trHeight w:val="400" w:type="atLeast"/></w:trPr>' . PHP_EOL;
    foreach ($encabezados as $encabezado) {
        $xml .= '<w:tc>' . PHP_EOL;
        $xml .= '<w:tcPr>' . PHP_EOL;
        $xml .= '<w:shd w:fill="D3D3D3"/>' . PHP_EOL;
        $xml .= '<w:tcW w:w="' . $anchoColumna . '" w:type="dxa"/>' . PHP_EOL;
        $xml .= '<w:vAlign w:val="center"/>' . PHP_EOL;
        $xml .= '</w:tcPr>' . PHP_EOL;
        $xml .= '<w:p>' . PHP_EOL;
        $xml .= '<w:pPr>' . PHP_EOL;
        $xml .= '<w:jc w:val="center"/>' . PHP_EOL;
        $xml .= '<w:spacing w:before="0" w:after="0"/>' . PHP_EOL;
        $xml .= '</w:pPr>' . PHP_EOL;
        $xml .= '<w:r>' . PHP_EOL;
        $xml .= '<w:rPr><w:b/></w:rPr>' . PHP_EOL;
        $xml .= '<w:t>' . escaparXMLWord((string)$encabezado) . '</w:t>' . PHP_EOL;
        $xml .= '</w:r>' . PHP_EOL;
        $xml .= '</w:p>' . PHP_EOL;
        $xml .= '</w:tc>' . PHP_EOL;
    }
    $xml .= '</w:tr>' . PHP_EOL;

    // Datos
    foreach ($datosTabla as $fila) {
        $xml .= '<w:tr>' . PHP_EOL;
        $xml .= '<w:trPr><w:trHeight w:val="300" w:type="atLeast"/></w:trPr>' . PHP_EOL;
        foreach ($encabezados as $encabezado) {
            $valor = $fila[$encabezado] ?? '';
            $valorStr = convertirValorAStringSeguro($valor);

            $esNumerico = is_numeric($valorStr) && $valorStr !== '';
            $alineacion = $esNumerico ? 'right' : 'left';

            $xml .= '<w:tc>' . PHP_EOL;
            $xml .= '<w:tcPr>' . PHP_EOL;
            $xml .= '<w:tcW w:w="' . $anchoColumna . '" w:type="dxa"/>' . PHP_EOL;
            $xml .= '</w:tcPr>' . PHP_EOL;
            $xml .= '<w:p>' . PHP_EOL;
            $xml .= '<w:pPr>' . PHP_EOL;
            $xml .= '<w:jc w:val="' . $alineacion . '"/>' . PHP_EOL;
            $xml .= '</w:pPr>' . PHP_EOL;
            $xml .= '<w:r>' . PHP_EOL;
            $xml .= '<w:t xml:space="preserve">' . escaparXMLWord($valorStr) . '</w:t>' . PHP_EOL;
            $xml .= '</w:r>' . PHP_EOL;
            $xml .= '</w:p>' . PHP_EOL;
            $xml .= '</w:tc>' . PHP_EOL;
        }
        $xml .= '</w:tr>' . PHP_EOL;
    }

    $xml .= '</w:tbl>' . PHP_EOL;
    return $xml;
}

/**
 * Escapa caracteres especiales en XML.
 */
function escaparXMLWord(string $texto): string
{
    $texto = str_replace('&', '&amp;', $texto);
    $texto = str_replace('<', '&lt;', $texto);
    $texto = str_replace('>', '&gt;', $texto);
    $texto = str_replace('"', '&quot;', $texto);
    $texto = str_replace("'", '&apos;', $texto);
    return $texto;
}

/**
 * Convierte cualquier valor (nulo, array anidado, objeto) en string seguro.
 */
function convertirValorAStringSeguro($valor): string
{
    if ($valor === null) {
        return '';
    }
    if (is_array($valor)) {
        // Procesa recursivamente cada elemento
        $partes = array_map(function ($item) {
            return convertirValorAStringSeguro($item);
        }, $valor);
        return implode(', ', $partes);
    }
    if (is_object($valor)) {
        if (method_exists($valor, '__toString')) {
            return (string)$valor;
        }
        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return (string)$valor;
}