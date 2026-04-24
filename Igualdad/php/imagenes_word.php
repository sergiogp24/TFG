<?php

declare(strict_types=1);

use PhpOffice\PhpWord\TemplateProcessor;
use CpChart\Chart\Pie;
use CpChart\Data;
use CpChart\Image;

const HOJA_IMAGEN0 = 0; // Hoja 0

/**
 * Genera y asigna imagenes de la plantilla.
 * - IMAGEN0: pastel sexo desde C8/D8 (fija)
 * - IMAGEN1/2/3/4/5/7/8/9/10/11/12/13/14/15/16/17/18/19/20/21/22/23/24/25/26: barras agrupadas via plantilla generica por configuracion.
 */
function generarImagenesPlantilla(TemplateProcessor $template, $spreadsheet): array
{
    $imagenes = [];

    $rutaImagen0 = asignarImagen0($template, $spreadsheet);
    if ($rutaImagen0 !== null) {
        $imagenes[] = $rutaImagen0;
    }

    $configuracionesGenericas = [
        configImagen1(),
        configImagen2(),
        configImagen3(),
        configImagen4(),
        configImagen5(),
        configImagen6(),
        configImagen7(),
        configImagen8(),
        configImagen9(),
        configImagen10(),
        configImagen11(),
        configImagen12(),
        configImagen13(),
        configImagen14(),
        configImagen15(),
        configImagen16(),
        configImagen17(),
        configImagen18(),
        configImagen19(),
        configImagen20(),
        configImagen21(),
        configImagen22(),
        configImagen23(),
        configImagen24(),
        configImagen25(),
        configImagen26(),
    ];

    foreach ($configuracionesGenericas as $cfgImagen) {
        $ruta = asignarImagenConfigurada($template, $spreadsheet, $cfgImagen);
        if ($ruta !== null) {
            $imagenes[] = $ruta;
        }
    }

    return $imagenes;
}

/**
 * Flujo independiente para ${IMAGEN0}.
 */
function asignarImagen0(TemplateProcessor $template, $spreadsheet): ?string
{
    $totales = obtenerTotalesDesdeCeldas($spreadsheet, HOJA_IMAGEN0, 'C8', 'D8');
    if ($totales === null) {
        $template->setValue('IMAGEN0', '');
        return null;
    }

    $rutaTemporal = sys_get_temp_dir() . '/grafico_imagen0_' . uniqid('', true) . '.png';
    $ok = generarImagen0Pastel($totales['mujeres'], $totales['hombres'], $rutaTemporal);
    if (!$ok || !file_exists($rutaTemporal)) {
        $template->setValue('IMAGEN0', '');
        return null;
    }

    $template->setImageValue('IMAGEN0', [
        'path' => $rutaTemporal,
        'width' => 380,
        'height' => 240,
    ]);

    return $rutaTemporal;
}

/**
 * Lee dos celdas numericas y devuelve mujeres/hombres.
 */
function obtenerTotalesDesdeCeldas($spreadsheet, int $indiceHoja, string $celdaMujeres, string $celdaHombres): ?array
{
    if ($indiceHoja < 0 || $indiceHoja >= $spreadsheet->getSheetCount()) {
        return null;
    }

    $sheet = $spreadsheet->getSheet($indiceHoja);
    $m = normalizarNumeroExcel($sheet->getCell($celdaMujeres)->getCalculatedValue());
    $h = normalizarNumeroExcel($sheet->getCell($celdaHombres)->getCalculatedValue());

    if ($m === null || $h === null) {
        return null;
    }

    return ['mujeres' => $m, 'hombres' => $h];
}

/**
 * Genera un grafico pastel para mujeres/hombres.
 */
function generarImagen0Pastel(float $mujeres, float $hombres, string $ruta): bool
{
    $puedeUsarCpChart = extension_loaded('gd')
        && class_exists(Data::class)
        && class_exists(Image::class)
        && class_exists(Pie::class);

    if (!$puedeUsarCpChart) {
        return generarImagen0PastelQuickChart($mujeres, $hombres, $ruta);
    }

    try {
        $total = $mujeres + $hombres;
        $data = new Data();
        $data->addPoints([$mujeres, $hombres], 'Values');
        $data->addPoints(['Mujeres', 'Hombres'], 'Labels');
        $data->setAbscissa('Labels');

        $image = new Image(380, 240, $data);
        aplicarFuenteCpChart($image);

        $image->drawFilledRectangle(0, 0, 379, 239, ['R' => 255, 'G' => 255, 'B' => 255]);
        $image->drawRectangle(0, 0, 379, 239, ['R' => 210, 'G' => 210, 'B' => 210]);
        $image->drawText(20, 22, 'Distribucion por sexo', ['R' => 40, 'G' => 40, 'B' => 40]);

        $pie = new Pie($image, $data);
        $pie->draw2DPie(190, 130, [
            'Radius' => 80,
            'WriteValues' => PIE_VALUE_PERCENTAGE,
            'ValueRounding' => 1,
            'DataGapAngle' => 8,
            'DataGapRadius' => 4,
            'Border' => true,
        ]);
        $pie->drawPieLegend(280, 78, ['Style' => LEGEND_NOBORDER, 'Mode' => LEGEND_VERTICAL]);

        if ($total === 0) {
            $image->drawText(125, 210, 'Sin datos en C8/D8', ['R' => 120, 'G' => 120, 'B' => 120]);
        }

        $image->render($ruta);
        return file_exists($ruta);
    } catch (\Throwable $e) {
        return generarImagen0PastelQuickChart($mujeres, $hombres, $ruta);
    }
}

function generarImagen0PastelQuickChart(float $mujeres, float $hombres, string $ruta): bool
{
    if (!extension_loaded('curl')) {
        return false;
    }

    $total = $mujeres + $hombres;
    $chartConfig = [
        'type' => 'pie',
        'data' => [
            'labels' => ['Mujeres', 'Hombres'],
            'datasets' => [[
                'data' => [$mujeres, $hombres],
                'backgroundColor' => ['#e15759', '#4e79a7'],
                'borderColor' => '#ffffff',
                'borderWidth' => 1,
            ]],
        ],
        'options' => [
            'plugins' => [
                'legend' => ['position' => 'right'],
                'title' => [
                    'display' => true,
                    'text' => 'Distribucion por sexo',
                ],
            ],
        ],
    ];

    if ($total === 0.0) {
        $chartConfig['options']['plugins']['subtitle'] = [
            'display' => true,
            'text' => 'Sin datos en C8/D8',
        ];
    }

    $payload = json_encode([
        'width' => 380,
        'height' => 240,
        'format' => 'png',
        'backgroundColor' => 'white',
        'chart' => $chartConfig,
    ]);

    if ($payload === false) {
        return false;
    }

    $ch = curl_init('https://quickchart.io/chart');
    if ($ch === false) {
        return false;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($imageData === false || $httpCode !== 200) {
        return false;
    }

    return file_put_contents($ruta, $imageData) !== false;
}

/**
 * Normaliza numeros de Excel (soporta floats, comas decimales, porcentajes y texto).
 */
function normalizarNumeroExcel($valor): ?float
{
    if ($valor === null || $valor === '') {
        return null;
    }

    if (is_int($valor) || is_float($valor)) {
        return (float)$valor;
    }

    $texto = trim((string)$valor);
    if ($texto === '') {
        return null;
    }

    $esPorcentaje = str_contains($texto, '%');
    $texto = str_replace(['%', ' '], '', $texto);

    $tieneComa = str_contains($texto, ',');
    $tienePunto = str_contains($texto, '.');

    if ($tieneComa && $tienePunto) {
        // 1.234,56 -> 1234.56
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    } elseif ($tieneComa) {
        // 123,45 -> 123.45
        $texto = str_replace(',', '.', $texto);
    }

    if (!is_numeric($texto)) {
        return null;
    }

    $numero = (float)$texto;
    if ($esPorcentaje && $numero > 1) {
        $numero /= 100;
    }

    return $numero;
}
/**
 * PLANTILLA GENÉRICA para imágenes tipo barras agrupadas.
 * Reutiliza helpers comunes existentes:
 * - normalizarNumeroExcel()
 * - aplicarFuenteCpChart()
 *
 * Idea:
 * 1) Defines config por imagen (hoja, filas, columnas, título, placeholder, colores).
 * 2) Llamas a asignarImagenConfigurada().
 * 3) Si mañana cambian columnas o leyendas, tocas solo el array de config.
 */

/**
 * Config para IMAGEN1.EDAD
 */
function configImagen1(): array
{
    return [
        'placeholder' => 'IMAGEN1',
        'indiceHoja' => 1,
        'filaInicio' => 20,
        'filaFin' => 32,
        'usarRangoDinamicoDesdeTotal' => false,
        'rellenarCerosSiNoNumerico' => true,
        'colCategorias' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'D',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'E',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR EDAD Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN2.ANTIGUEDAD
 */
function configImagen2(): array
{
    return [
        'placeholder' => 'IMAGEN2',
        'indiceHoja' => 2,
        'filaInicio' => 14,
        'filaFin' => 19,
        'colCategorias' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'D',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'E',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR ANTIGUEDAD Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN3.NIVELDEESTUDIOS
 */
function configImagen3(): array
{
    return [
        'placeholder' => 'IMAGEN3',
        'indiceHoja' => 3,
        'filaInicio' => 3,
        'filaFin' => 100,

        // Columna de categorías (tramos)
        'colCategorias' => 'B',
        'usarRangoDinamicoDesdeTotal' => true,
        'colTotalEtiqueta' => 'B',
        'colTotalFormula' => 'C',

        // Series del gráfico (puedes añadir o quitar)
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'C',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'G',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],

        // Apariencia
        'titulo' => 'PLANTILLA DESAGREGADA POR NIVEL DEESTUDIOS Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN4.MODALIDADCONTRATO
 */
function configImagen4(): array
{
    return [
        'placeholder' => 'IMAGEN4',
        'indiceHoja' => 4,
        'filaInicio' => 12,
        'filaFin' => 16,
        'colCategorias' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'D',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'E',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR MODALIDAD DE CONTRATO Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN5.PORCENTAJEJORNADA
 */
function configImagen5(): array
{
    return [
        'placeholder' => 'IMAGEN5',
        'indiceHoja' => 5,
        'filaInicio' => 10,
        'filaFin' => 13,
        'colCategorias' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'D',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'E',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PORCENTAJE DE JORNADA Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN6.PUESTOSPROFESIONALES
 */
function configImagen6(): array
{
    return [
        'placeholder' => 'IMAGEN6',
        'indiceHoja' => 6,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategorias' => 'B',
        'usarRangoDinamicoDesdeTotal' => true,
        'colTotalEtiqueta' => 'B',
        'colTotalFormula' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'C',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'G',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PUESTOS PROFESIONALES Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN7.PUESTOSDIRECTIVOS
 */
function configImagen7(): array
{
    return [
        'placeholder' => 'IMAGEN7',
        'indiceHoja' => 7,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategorias' => 'B',
        'usarRangoDinamicoDesdeTotal' => true,
        'colTotalEtiqueta' => 'B',
        'colTotalFormula' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'C',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'G',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PUESTOS DIRECTIVOS Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN8.AREASFUNCIONALES
 */
function configImagen8(): array
{
    return [
        'placeholder' => 'IMAGEN8',
        'indiceHoja' => 8,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategorias' => 'B',
        'usarRangoDinamicoDesdeTotal' => true,
        'colTotalEtiqueta' => 'B',
        'colTotalFormula' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'C',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'G',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR AREAS FUNCIONALES Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN9.TURNOSDETRABAJO
 */
function configImagen9(): array
{
    return [
        'placeholder' => 'IMAGEN9',
        'indiceHoja' => 9,
        'filaInicio' => 17,
        'filaFin' => 20,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR TURNOS DE TRABAJO Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN10.HIJOS
 */
function configImagen10(): array
{
    return [
        'placeholder' => 'IMAGEN10',
        'indiceHoja' => 10,
        'filaInicio' => 12,
        'filaFin' => 15,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR HIJOS Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN11.BAJAS
 */
function configImagen11(): array
{
    return [
        'placeholder' => 'IMAGEN11',
        'indiceHoja' => 11,
        'filaInicio' => 11,
        'filaFin' => 14,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR BAJAS Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN12.EXCEDENCIAS
 */
function configImagen12(): array
{
    return [
        'placeholder' => 'IMAGEN12',
        'indiceHoja' => 12,
        'filaInicio' => 32,
        'filaFin' => 37,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR EXCEDENCIAS Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN13.PERMISOSRETRIBUIDOS
 */
function configImagen13(): array
{
    return [
        'placeholder' => 'IMAGEN13',
        'indiceHoja' => 13,
        'filaInicio' => 9,
        'filaFin' => 11,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PERMISOS RETRIBUIDOS Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN14.FORMACION
 */
function configImagen14(): array
{
    return [
        'placeholder' => 'IMAGEN14',
        'indiceHoja' => 12,
        'filaInicio' => 8,
        'filaFin' => 9,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR FORMACIÓN Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN15.GRUPOPROFESIONAL
 */
function configImagen15(): array
{
    return [
        'placeholder' => 'IMAGEN15',
        'indiceHoja' => 15,
        'filaInicio' => 8,
        'filaFin' => 9,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR GRUPO PROFESIONAL Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN16.RETRIBUCIONES
 */
function configImagen16(): array
{
    return [
        'placeholder' => 'IMAGEN16',
        'indiceHoja' => 16,
        'filaInicio' => 17,
        'filaFin' => 27,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR RETRIBUCIONES Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN17.EDADNNCC
 */
function configImagen17(): array
{
    return [
        'placeholder' => 'IMAGEN17',
        'indiceHoja' => 17,
        'filaInicio' => 12,
        'filaFin' => 16,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR EDAD NNCC Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN18.MODALIDADCONTRATONNCC
 */
function configImagen18(): array
{
    return [
        'placeholder' => 'IMAGEN18',
        'indiceHoja' => 18,
        'filaInicio' => 18,
        'filaFin' => 29,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR MODALIDAD DE CONTRATO NNCC Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN19.PORCENTAJEJORNADANNCC
 */
function configImagen19(): array
{
    return [
        'placeholder' => 'IMAGEN19',
        'indiceHoja' => 19,
        'filaInicio' => 3,
        'filaFin' => 6,
        'colCategorias' => 'B',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'C',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'G',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PORCENTAJE DE JORNADA NNCC Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN20.PUESTOSPROFESIONALESNNCC
 */
function configImagen20(): array
{
    return [
        'placeholder' => 'IMAGEN20',
        'indiceHoja' => 20,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategorias' => 'B',
        'usarRangoDinamicoDesdeTotal' => true,
        'colTotalEtiqueta' => 'B',
        'colTotalFormula' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'C',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'G',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PUESTOS PROFESIONALES NNCC Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN21.AREASFUNCIONALESNNCC
 */
function configImagen21(): array
{
    return [
        'placeholder' => 'IMAGEN21',
        'indiceHoja' => 21,
        'filaInicio' => 3,
        'filaFin' => 100,
        'colCategorias' => 'B',
        'usarRangoDinamicoDesdeTotal' => true,
        'colTotalEtiqueta' => 'B',
        'colTotalFormula' => 'C',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'C',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'G',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR ÁREAS FUNCIONALES NNCC Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN22.EDADPERSONALSUBROGADO
 */
function configImagen22(): array
{
    return [
        'placeholder' => 'IMAGEN22',
        'indiceHoja' => 22,
        'filaInicio' => 12,
        'filaFin' => 29,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR EDAD PERSONAL SUBROGADO Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN23.MODALIDADCONTRATOPERSONALSUB
 */
function configImagen23(): array
{
    return [
        'placeholder' => 'IMAGEN23',
        'indiceHoja' => 23,
        'filaInicio' => 12,
        'filaFin' => 29,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR MODALIDAD DE CONTRATO PERSONAL SUBROGADO Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN24.PORCENTAJEJORNADAPERSONALSUB
 */
function configImagen24(): array
{
    return [
        'placeholder' => 'IMAGEN24',
        'indiceHoja' => 24,
        'filaInicio' => 12,
        'filaFin' => 29,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PORCENTAJE DE JORNADA PERSONAL SUBROGADO Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Config para IMAGEN25.PUESTOSDIRECTIVOSPERSONALSUB
 */
function configImagen25(): array
{
    return [
        'placeholder' => 'IMAGEN25',
        'indiceHoja' => 25,
        'filaInicio' => 12,
        'filaFin' => 29,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PUESTOS DIRECTIVOS PERSONAL SUBROGADO Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}


/**
 * Config para IMAGEN26.PUESTOSPROFPERSONALSUBROGADO
 */
function configImagen26(): array
{
    return [
        'placeholder' => 'IMAGEN26',
        'indiceHoja' => 26,
        'filaInicio' => 12,
        'filaFin' => 29,
        'colCategorias' => 'D',
        'series' => [
            [
                'key' => 'Mujeres',
                'label' => 'MUJERES',
                'columna' => 'E',
                'color' => ['R' => 68, 'G' => 114, 'B' => 196],
            ],
            [
                'key' => 'Hombres',
                'label' => 'HOMBRES',
                'columna' => 'F',
                'color' => ['R' => 237, 'G' => 125, 'B' => 49],
            ],
        ],
        'titulo' => 'PLANTILLA DESAGREGADA POR PUESTOS PROFESIONALES PERSONAL SUBROGADO Y SEXO',
        'ancho' => 520,
        'alto' => 280,
        'fondo' => ['R' => 236, 'G' => 236, 'B' => 236],
    ];
}

/**
 * Única función de alto nivel para asignar una imagen por configuración.
 */
function asignarImagenConfigurada(TemplateProcessor $template, $spreadsheet, array $cfg): ?string
{
    $datos = obtenerDatosAgrupadosDesdeConfig($spreadsheet, $cfg);
    if ($datos === null) {
        error_log('No hay datos válidos para ' . $cfg['placeholder'] . '.');
        return null;
    }

    $rutaTemporal = sys_get_temp_dir() . '/grafico_' . strtolower($cfg['placeholder']) . '_' . uniqid('', true) . '.png';

    $ok = generarGraficoBarrasAgrupadasDesdeConfig(
        $datos['labels'],
        $datos['seriesValues'],
        $cfg,
        $rutaTemporal
    );

    if (!$ok || !file_exists($rutaTemporal)) {
        error_log('No se pudo generar el gráfico para ' . $cfg['placeholder'] . '.');
        return null;
    }

    $template->setImageValue($cfg['placeholder'], [
        'path' => $rutaTemporal,
        'width' => $cfg['ancho'],
        'height' => $cfg['alto'],
    ]);

    return $rutaTemporal;
}

/**
 * Lee categorías + N series usando configuración.
 * Si una fila no tiene categoría o tiene algún valor no numérico, se descarta.
 */
function obtenerDatosAgrupadosDesdeConfig($spreadsheet, array $cfg): ?array
{
    $indiceHoja = (int)$cfg['indiceHoja'];
    if ($indiceHoja < 0 || $indiceHoja >= $spreadsheet->getSheetCount()) {
        return null;
    }

    $sheet = $spreadsheet->getSheet($indiceHoja);
    [$filaInicio, $filaFin] = resolverRangoFilasDesdeTotales($sheet, $cfg);
    if ($filaInicio > $filaFin) {
        return null;
    }

    $labels = [];
    $seriesValues = [];
    foreach ($cfg['series'] as $serie) {
        $seriesValues[$serie['key']] = [];
    }

    for ($fila = $filaInicio; $fila <= $filaFin; $fila++) {
        $textoCategoria = trim((string)$sheet->getCell($cfg['colCategorias'] . $fila)->getCalculatedValue());
        if ($textoCategoria === '') {
            continue;
        }

        $valoresFila = [];
        $filaValida = true;
        $rellenarCerosSiNoNumerico = (bool)($cfg['rellenarCerosSiNoNumerico'] ?? false);

        foreach ($cfg['series'] as $serie) {
            $valorRaw = $sheet->getCell($serie['columna'] . $fila)->getCalculatedValue();
            $valor = normalizarNumeroExcel($valorRaw);
            if ($valor === null) {
                if ($rellenarCerosSiNoNumerico) {
                    $valor = 0.0;
                } else {
                    $filaValida = false;
                    break;
                }
            }
            $valoresFila[$serie['key']] = $valor;
        }

        if (!$filaValida) {
            continue;
        }

        $labels[] = formatearEtiquetaCorta($textoCategoria);
        foreach ($cfg['series'] as $serie) {
            $seriesValues[$serie['key']][] = $valoresFila[$serie['key']];
        }
    }

    if ($labels === []) {
        return null;
    }

    return [
        'labels' => $labels,
        'seriesValues' => $seriesValues,
    ];
}

/**
 * Resuelve el rango de filas para graficos. Si hay configuracion dinamica,
 * usa la fila TOTAL y su formula SUM para deducir el tramo real de datos.
 *
 * @return array{0:int,1:int}
 */
function resolverRangoFilasDesdeTotales($sheet, array $cfg): array
{
    $filaInicio = (int)($cfg['filaInicio'] ?? 1);
    $filaFin = (int)($cfg['filaFin'] ?? $sheet->getHighestDataRow());

    if (!($cfg['usarRangoDinamicoDesdeTotal'] ?? false)) {
        return [$filaInicio, $filaFin];
    }

    $colTotalEtiqueta = (string)($cfg['colTotalEtiqueta'] ?? 'B');
    $etiquetaTotal = strtoupper(trim((string)($cfg['etiquetaTotal'] ?? 'TOTAL')));
    $filaBusquedaInicio = (int)($cfg['filaBusquedaInicio'] ?? $filaInicio);
    $filaBusquedaFin = (int)($cfg['filaBusquedaFin'] ?? $sheet->getHighestDataRow());

    $filaTotal = buscarFilaPorEtiqueta($sheet, $colTotalEtiqueta, $etiquetaTotal, $filaBusquedaInicio, $filaBusquedaFin);
    if ($filaTotal === null) {
        return [$filaInicio, $filaFin];
    }

    $colTotalFormula = (string)($cfg['colTotalFormula'] ?? '');
    if ($colTotalFormula !== '') {
        $valorCeldaTotal = $sheet->getCell($colTotalFormula . $filaTotal)->getValue();
        $rangoFormula = extraerRangoDesdeFormulaSuma((string)$valorCeldaTotal);
        if ($rangoFormula !== null) {
            return [$rangoFormula['inicio'], $rangoFormula['fin']];
        }
    }

    return [$filaInicio, max($filaInicio, $filaTotal - 1)];
}

function buscarFilaPorEtiqueta($sheet, string $columna, string $etiqueta, int $filaInicio, int $filaFin): ?int
{
    for ($fila = $filaInicio; $fila <= $filaFin; $fila++) {
        $valor = strtoupper(trim((string)$sheet->getCell($columna . $fila)->getCalculatedValue()));
        if ($valor === $etiqueta) {
            return $fila;
        }
    }

    return null;
}

/**
 * Extrae rango inicio-fin desde formulas tipo =SUM(C3:C10) o =SUMA(C3:C10).
 *
 * @return array{inicio:int,fin:int}|null
 */
function extraerRangoDesdeFormulaSuma(string $formula): ?array
{
    $texto = trim($formula);
    if ($texto === '') {
        return null;
    }

    if (preg_match('/=\s*SUMA?\(\s*[A-Z]+\$?(\d+)\s*:\s*[A-Z]+\$?(\d+)\s*\)/i', $texto, $m) !== 1) {
        return null;
    }

    $inicio = (int)$m[1];
    $fin = (int)$m[2];
    if ($inicio <= 0 || $fin <= 0) {
        return null;
    }

    if ($inicio > $fin) {
        [$inicio, $fin] = [$fin, $inicio];
    }

    return ['inicio' => $inicio, 'fin' => $fin];
}

/**
 * Render de barras agrupadas (múltiples series) con estilo similar a tu IMAGEN2.
 */

// Genera gráfico de barras agrupadas usando QuickChart
function generarGraficoBarrasAgrupadasDesdeConfig(
    array $labels,
    array $seriesValues,
    array $cfg,
    string $ruta
): bool {
    // Aumentar altura si es menor de 350
    $alto = $cfg['alto'] < 350 ? 400 : $cfg['alto'];
    $chartConfig = [
        'type' => 'bar',
        'data' => [
            'labels' => $labels,
            'datasets' => [],
        ],
        'options' => [
            'responsive' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'fullSize' => true,
                    'align' => 'start',
                    'labels' => [
                        'font' => [
                            'size' => 16,
                            'weight' => 'bold',
                            'family' => 'Arial, sans-serif',
                        ],
                        'boxWidth' => 30,
                        'padding' => 20,
                    ],
                ],
                'title' => ['display' => true, 'text' => $cfg['titulo'] ?? ''],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 30,
                        'autoSkip' => false,
                        'font' => ['size' => 14],
                    ],
                ],
                'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Personas']],
            ],
        ],
    ];

    // Añadir datasets
    foreach ($cfg['series'] as $serie) {
        $key = $serie['key'];
        $color = $serie['color'] ?? ['R' => 0, 'G' => 0, 'B' => 0];
        $chartConfig['data']['datasets'][] = [
            'label' => $serie['label'],
            'data' => $seriesValues[$key],
            'backgroundColor' => sprintf('rgb(%d,%d,%d)', $color['R'], $color['G'], $color['B']),
        ];
    }

    $quickchartUrl = 'https://quickchart.io/chart';
    $payload = json_encode(['width' => $cfg['ancho'], 'height' => $alto, 'format' => 'png', 'chart' => $chartConfig]);

    $ch = curl_init($quickchartUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($imageData !== false && $httpCode === 200) {
        file_put_contents($ruta, $imageData);
        return file_exists($ruta);
    }
    return false;
}

/**
 * Helper pequeño para partir etiquetas largas del eje X.
 */
function formatearEtiquetaCorta(string $texto): string
{
    $texto = preg_replace('/\s+/', ' ', trim($texto));
    if ($texto === null || $texto === '') {
        return '';
    }

    return wordwrap($texto, 20, "\n", true);
}

/**
 * Ejemplo de uso real dentro de tu flujo:
 *
 * $cfgImagen4 = configImagen4();
 * $rutaImagen4 = asignarImagenConfigurada($template, $spreadsheet, $cfgImagen4);
 * if ($rutaImagen4 !== null) {
 *     $imagenes[] = $rutaImagen4;
 * }
 */

/**
 * Fuente comun para c-pchart.
 */
function aplicarFuenteCpChart(Image $image): void
{
    $fontPath = __DIR__ . '/../vendor/szymach/c-pchart/resources/fonts/verdana.ttf';
    if (file_exists($fontPath)) {
        $image->setFontProperties(['FontName' => $fontPath, 'FontSize' => 9]);
    }
}
