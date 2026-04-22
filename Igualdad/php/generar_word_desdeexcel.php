<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/generar_cuadro_porcentajes.php';
require_once __DIR__ . '/imagenes_word.php';

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Rellena el Word plantilla SIN romper formato
 */
function rellenarWordPlanIgualdad(string $rutaExcel, string $razonSocial, ?string $anioRegistro = null, ?int $idEmpresa = null): string
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

        $nombreArchivoEmpresa = normalizarNombreArchivoEmpresa($razonSocial);
        $nombreArchivoEmpresa = ($anioRegistro !== null && $anioRegistro !== '') 
            ? $nombreArchivoEmpresa . '_' . $anioRegistro 
            : $nombreArchivoEmpresa;
        $rutaWordFinal = $destDirWord . DIRECTORY_SEPARATOR . $nombreArchivoEmpresa . '_PLAN_IGUALDAD.docx';

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
                $reemplazosEmpresa = obtenerReemplazosEmpresaDesdeBD($dbConn, $razonSocial);
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
        // EXCEL
        // =========================
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($rutaExcel);

        foreach ($spreadsheet->getWorksheetIterator() as $index => $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $fila = $row->getRowIndex();
                $valorBX = $sheet->getCell('BX' . $fila)->getCalculatedValue();
                $valorCC = $sheet->getCell('CC' . $fila)->getCalculatedValue();

                // Si BX o CC valen 0 (resultado calculado), no se procesa la fila.
                if (esCeroNumerico($valorBX) || esCeroNumerico($valorCC)) {
                    continue;
                }

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {

                    $col = $cell->getColumn();
                    $valor = $cell->getCalculatedValue();

                    $placeholder = $col . $fila . '_' . $index;

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
        // IMAGENES DE LA PLANTILLA
        // =========================
        $imagenes = generarImagenesPlantilla($template, $spreadsheet);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // =========================
        // GUARDAR
        // =========================
        $template->saveAs($rutaWordFinal);

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
function obtenerReemplazosEmpresaDesdeBD(mysqli $db, string $razonSocial): array
{
    $stmt = $db->prepare("\n        SELECT * FROM empresa\n        WHERE UPPER(TRIM(razon_social)) = ?\n        LIMIT 1\n    ");

    $razonSocial = mb_strtoupper(trim($razonSocial));
    $stmt->bind_param('s', $razonSocial);
    $stmt->execute();

    $empresa = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    return $empresa;
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
        error_log('[obtenerValoresCuestionariosDesdeBD] No se pudo resolver id_empresa para razon social: ' . $razonSocial);
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
            error_log("[obtenerValoresCuestionariosDesdeBD] Tabla {$tabla} sin columnas disponibles.");
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
                INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
                WHERE q.id_empresa = ?
                  AND ce.id_empresa = ?
                  AND (YEAR(ad.fecha_inicio) = ? OR YEAR(ad.fecha_fin) = ?)
                ORDER BY q.id_ano_datos DESC
                LIMIT 1
            ";
            $stmtAnio = $db->prepare($sqlAnio);
            if ($stmtAnio) {
                $stmtAnio->bind_param('iiii', $idEmpresa, $idEmpresa, $anio, $anio);
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

        error_log("[obtenerValoresCuestionariosDesdeBD] tabla={$tabla} empresa={$idEmpresa} anio=" . ($anio ?? 'null') . " campos=" . count($fila));
    }

    error_log("[obtenerValoresCuestionariosDesdeBD] empresa={$idEmpresa} total_campos=" . count($valores));
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
        $categoriaPrincipalRaw = $sheet->getCell($colCategoriaPrincipal . $fila)->getCalculatedValue();
        $categoriaAltRaw = $sheet->getCell($cfg['colCategoriaAlt'] . $fila)->getCalculatedValue();
        $categoriaPrincipal = trim((string)$categoriaPrincipalRaw);
        $categoriaAlt = trim((string)$categoriaAltRaw);

        if ($categoriaPrincipal === '' && $categoriaAlt === '') {
            continue;
        }

        $categoriaBase = $categoriaPrincipal !== '' ? $categoriaPrincipal : $categoriaAlt;
        $filaData = [];

        foreach ($cfg['columnas'] as $placeholder => $columna) {
            $valor = $sheet->getCell($columna . $fila)->getCalculatedValue();
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
            'hay_personas_referencia',
            'ocupacion_mujeres',
            'porcentaje_mujeres',
            'mujeres_sin_cualificacion',
            'existe_plan',
            'acciones_reforzar',
            'ultimos_5_anios',
            'analisis_segregar',
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