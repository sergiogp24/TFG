<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Obtiene los datos de edad de empleados por rango y sexo.
 *
 * @param mysqli $db
 * @param int $idEmpresa
 * @param int $idAnoDatos
 * @param string $fechaReferencia Fecha para calcular edad (YYYY-MM-DD)
 * @return array{mujeres: int[], hombres: int[]} Con 13 elementos cada uno (rangos de edad)
 */
function obtenerConteosPorRangoEdad(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.fecha_nacimiento,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.fecha_nacimiento IS NOT NULL
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de edades.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 13, 0);
    $hombres = array_fill(0, 13, 0);

    // Siempre usar la fecha actual para calcular la edad
    $fechaRef = new \DateTime();

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);

        if (!($esMujer || $esHombre)) {
            continue;
        }

        $fechaNac = \DateTime::createFromFormat('Y-m-d', (string)$row['fecha_nacimiento']);
        if ($fechaNac === false) {
            continue;
        }

        $edad = $fechaNac->diff($fechaRef)->y;
        $indiceRango = obtenerIndiceRangoEdad($edad);
        if ($indiceRango === -1) {
            continue;
        }
        if ($esMujer) {
            $mujeres[$indiceRango]++;
        } else {
            $hombres[$indiceRango]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

/**
 * Retorna el índice del rango de edad (0-12).
 *
 * 0: < 20
 * 1: 20-24
 * 2: 25-29
 * 3: 30-34
 * 4: 35-39
 * 5: 40-44
 * 6: 45-49
 * 7: 50-54
 * 8: 55-59
 * 9: 60
 * 10: 61
 * 11: 62
 * 12: 63
 */
function obtenerIndiceRangoEdad(int $edad): int
{
    if ($edad < 0) return -1;
    if ($edad < 20) return 0;
    if ($edad >= 20 && $edad <= 24) return 1;
    if ($edad >= 25 && $edad <= 29) return 2;
    if ($edad >= 30 && $edad <= 34) return 3;
    if ($edad >= 35 && $edad <= 39) return 4;
    if ($edad >= 40 && $edad <= 44) return 5;
    if ($edad >= 45 && $edad <= 49) return 6;
    if ($edad >= 50 && $edad <= 54) return 7;
    if ($edad >= 55 && $edad <= 59) return 8;
    if ($edad === 60) return 9;
    if ($edad === 61) return 10;
    if ($edad === 62) return 11;
    if ($edad >= 63) return 12;
    return -1;
}

/**
 * Obtiene los datos de antigüedad de empleados por rango y sexo.
 *
 * @param mysqli $db
 * @param int $idEmpresa
 * @param int $idAnoDatos
 * @param string $fechaReferencia Fecha para calcular antigüedad (YYYY-MM-DD)
 * @return array{mujeres: int[], hombres: int[]} Con 6 elementos cada uno (rangos de antigüedad)
 */
function obtenerConteosPorRangoAntiguedad(mysqli $db, int $idEmpresa, int $idAnoDatos, string $fechaReferencia): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.inicio_contratacion,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.inicio_contratacion IS NOT NULL
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de antigüedad.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 6, 0);
    $hombres = array_fill(0, 6, 0);

    $fechaRef = \DateTime::createFromFormat('Y-m-d', $fechaReferencia);
    if ($fechaRef === false) {
        $fechaRef = new \DateTime();
    }

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);

        if (!($esMujer || $esHombre)) {
            continue;
        }

        $fechaInicio = \DateTime::createFromFormat('Y-m-d', (string)$row['inicio_contratacion']);
        if ($fechaInicio === false) {
            continue;
        }

        $antiguedad = $fechaRef->diff($fechaInicio)->y;
        $indiceRango = obtenerIndiceRangoAntiguedad($antiguedad);

        if ($esMujer) {
            $mujeres[$indiceRango]++;
        } else {
            $hombres[$indiceRango]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

/**
 * Retorna el índice del rango de antigüedad (0-5).
 *
 * 0: < 1 año
 * 1: 1-3 años
 * 2: 3-5 años
 * 3: 5-10 años
 * 4: 10-15 años
 * 5: > 15 años
 */
function obtenerIndiceRangoAntiguedad(int $antiguedad): int
{
    if ($antiguedad < 1) return 0;
    if ($antiguedad >= 1 && $antiguedad <= 3) return 1;
    if ($antiguedad >= 3 && $antiguedad < 5) return 2;
    if ($antiguedad >= 5 && $antiguedad < 10) return 3;
    if ($antiguedad >= 10 && $antiguedad < 15) return 4;
    return 5;
}

/**
 * Obtiene los conteos por modalidad de contrato y sexo.
 *
 * Índices (0-4):
 * 0 Indefinido jornada completa
 * 1 Indefinido jornada parcial
 * 2 Fijo discontinuo
 * 3 Duración determinada jornada completa
 * 4 Duración determinada jornada parcial
 *
 * @return array{mujeres: int[], hombres: int[]}
 */
function obtenerConteosPorModalidadContrato(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.clave_contrato,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.clave_contrato IS NOT NULL
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de modalidad de contrato.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 5, 0);
    $hombres = array_fill(0, 5, 0);

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);

        if (!($esMujer || $esHombre)) {
            continue;
        }

        $claveContrato = (int)($row['clave_contrato'] ?? 0);
        $indice = obtenerIndiceModalidadContrato($claveContrato);
        if ($indice === null) {
            continue;
        }

        if ($esMujer) {
            $mujeres[$indice]++;
        } else {
            $hombres[$indice]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

/**
 * Obtiene los conteos por modalidad de contrato y sexo para altas en periodo.
 *
 * Solo incluye registros cuya inicio_contratacion este entre fecha_inicio y fecha_fin de ano_datos.
 *
 * @return array{mujeres: int[], hombres: int[]}
 */
function obtenerConteosPorModalidadContratoEnPeriodo(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.clave_contrato,
            de.inicio_contratacion,
            ad.fecha_inicio,
            ad.fecha_fin,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.clave_contrato IS NOT NULL
          AND de.inicio_contratacion IS NOT NULL
          AND ad.fecha_inicio IS NOT NULL
          AND ad.fecha_fin IS NOT NULL
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de modalidad de contrato en periodo.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 5, 0);
    $hombres = array_fill(0, 5, 0);

    while ($row = $result->fetch_assoc()) {
        $inicioContratacion = \DateTime::createFromFormat('Y-m-d', (string)($row['inicio_contratacion'] ?? ''));
        $fechaInicio = \DateTime::createFromFormat('Y-m-d', (string)($row['fecha_inicio'] ?? ''));
        $fechaFin = \DateTime::createFromFormat('Y-m-d', (string)($row['fecha_fin'] ?? ''));
        if ($inicioContratacion === false || $fechaInicio === false || $fechaFin === false) {
            continue;
        }

        if ($inicioContratacion < $fechaInicio || $inicioContratacion > $fechaFin) {
            continue;
        }

        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $claveContrato = (int)($row['clave_contrato'] ?? 0);
        $indice = obtenerIndiceModalidadContrato($claveContrato);
        if ($indice === null) {
            continue;
        }

        if ($esMujer) {
            $mujeres[$indice]++;
        } else {
            $hombres[$indice]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

/**
 * Mapea la clave de contrato al índice de modalidad esperado en la hoja.
 */
function obtenerIndiceModalidadContrato(int $claveContrato): ?int
{
    static $mapa = null;
    if ($mapa === null) {
        $mapa = [
            100 => 0,
            109 => 0,
            130 => 0,
            139 => 0,
            150 => 0,
            189 => 0,
            200 => 1,
            209 => 1,
            230 => 1,
            239 => 1,
            250 => 1,
            289 => 1,
            300 => 2,
            309 => 2,
            330 => 2,
            339 => 2,
            350 => 2,
            389 => 2,
            401 => 3,
            402 => 3,
            403 => 3,
            408 => 3,
            410 => 3,
            418 => 3,
            420 => 3,
            421 => 3,
            430 => 3,
            441 => 3,
            450 => 3,
            452 => 3,
            501 => 4,
            502 => 4,
            503 => 4,
            508 => 4,
            510 => 4,
            518 => 4,
            520 => 4,
            530 => 4,
            540 => 4,
            541 => 4,
            550 => 4,
            552 => 4,
        ];
    }

    return $mapa[$claveContrato] ?? null;
}

/**
 * Obtiene los conteos por porcentaje de jornada y sexo.
 *
 * Índices (0-3):
 * 0: 0%-38%
 * 1: 39%-75%
 * 2: 76%-99%
 * 3: 100%
 *
 * @return array{mujeres: int[], hombres: int[]}
 */
function obtenerConteosPorPorcentajeJornada(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.porc_jornada,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.porc_jornada IS NOT NULL
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de porcentaje de jornada.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 4, 0);
    $hombres = array_fill(0, 4, 0);

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);

        if (!($esMujer || $esHombre)) {
            continue;
        }

        $porcentajeRaw = (float)($row['porc_jornada'] ?? 0);
        if ($porcentajeRaw < 0) {
            continue;
        }

        // En BD puede venir en decimal (0.60 = 60%) o en porcentaje (60).
        $porcentaje = $porcentajeRaw <= 1 ? $porcentajeRaw * 100 : $porcentajeRaw;

        $indice = null;
        if ($porcentaje <= 38) {
            $indice = 0;
        } elseif ($porcentaje <= 75) {
            $indice = 1;
        } elseif ($porcentaje <= 99) {
            $indice = 2;
        } else {
            $indice = 3;
        }

        if ($esMujer) {
            $mujeres[$indice]++;
        } else {
            $hombres[$indice]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

/**
 * Obtiene los conteos por porcentaje de jornada y sexo para altas en periodo.
 *
 * Aplica las mismas reglas de clasificacion de hoja 5, pero solo considera personas
 * cuya inicio_contratacion cae dentro de fecha_inicio y fecha_fin de ano_datos.
 *
 * Indices (0-3):
 * 0: 0%-38%
 * 1: 39%-75%
 * 2: 76%-99%
 * 3: 100%
 *
 * @return array{mujeres: int[], hombres: int[]}
 */
function obtenerConteosPorPorcentajeJornadaEnPeriodo(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.porc_jornada,
            de.inicio_contratacion,
            ad.fecha_inicio,
            ad.fecha_fin,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.porc_jornada IS NOT NULL
          AND de.inicio_contratacion IS NOT NULL
          AND ad.fecha_inicio IS NOT NULL
          AND ad.fecha_fin IS NOT NULL
          AND de.inicio_contratacion BETWEEN ad.fecha_inicio AND ad.fecha_fin
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de porcentaje de jornada en periodo.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 4, 0);
    $hombres = array_fill(0, 4, 0);

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);

        if (!($esMujer || $esHombre)) {
            continue;
        }

        $porcentajeRaw = (float)($row['porc_jornada'] ?? 0);
        if ($porcentajeRaw < 0) {
            continue;
        }

        // En BD puede venir en decimal (0.60 = 60%) o en porcentaje (60).
        $porcentaje = $porcentajeRaw <= 1 ? $porcentajeRaw * 100 : $porcentajeRaw;

        $indice = null;
        if ($porcentaje <= 38) {
            $indice = 0;
        } elseif ($porcentaje <= 75) {
            $indice = 1;
        } elseif ($porcentaje <= 99) {
            $indice = 2;
        } else {
            $indice = 3;
        }

        if ($esMujer) {
            $mujeres[$indice]++;
        } else {
            $hombres[$indice]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

/**
 * Obtiene conteos por puesto profesional (agrupado por nombre normalizado).
 *
 * @return array<int, array{puesto:string,mujeres:int,hombres:int}>
 */
function obtenerConteosPorPuestoProfesional(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.puesto_empresa,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.puesto_empresa IS NOT NULL
          AND TRIM(de.puesto_empresa) <> ''
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de puestos profesionales.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $acumulado = [];

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $puestoOriginal = trim((string)($row['puesto_empresa'] ?? ''));
        if ($puestoOriginal === '') {
            continue;
        }

        $clave = normalizarTextoPuesto($puestoOriginal);
        if ($clave === '') {
            continue;
        }

        if (!isset($acumulado[$clave])) {
            $acumulado[$clave] = [
                'puesto' => $puestoOriginal,
                'mujeres' => 0,
                'hombres' => 0,
            ];
        }

        if ($esMujer) {
            $acumulado[$clave]['mujeres']++;
        } else {
            $acumulado[$clave]['hombres']++;
        }
    }

    // Orden estable por total descendente y luego alfabético.
    $filas = array_values($acumulado);
    usort($filas, static function (array $a, array $b): int {
        $totalA = $a['mujeres'] + $a['hombres'];
        $totalB = $b['mujeres'] + $b['hombres'];
        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }
        return strcasecmp($a['puesto'], $b['puesto']);
    });

    return $filas;
}

/**
 * Obtiene conteos por puesto profesional para altas en periodo de ano_datos.
 *
 * Aplica la misma agrupacion de hoja 6, pero solo incluye filas donde
 * inicio_contratacion este entre fecha_inicio y fecha_fin de ano_datos.
 *
 * @return array<int, array{puesto:string,mujeres:int,hombres:int}>
 */
function obtenerConteosPorPuestoProfesionalEnPeriodo(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.puesto_empresa,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.puesto_empresa IS NOT NULL
          AND TRIM(de.puesto_empresa) <> ''
          AND de.inicio_contratacion IS NOT NULL
          AND ad.fecha_inicio IS NOT NULL
          AND ad.fecha_fin IS NOT NULL
          AND de.inicio_contratacion BETWEEN ad.fecha_inicio AND ad.fecha_fin
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de puestos profesionales en periodo.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $acumulado = [];

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $puestoOriginal = trim((string)($row['puesto_empresa'] ?? ''));
        if ($puestoOriginal === '') {
            continue;
        }

        $clave = normalizarTextoPuesto($puestoOriginal);
        if ($clave === '') {
            continue;
        }

        if (!isset($acumulado[$clave])) {
            $acumulado[$clave] = [
                'puesto' => $puestoOriginal,
                'mujeres' => 0,
                'hombres' => 0,
            ];
        }

        if ($esMujer) {
            $acumulado[$clave]['mujeres']++;
        } else {
            $acumulado[$clave]['hombres']++;
        }
    }

    $filas = array_values($acumulado);
    usort($filas, static function (array $a, array $b): int {
        $totalA = $a['mujeres'] + $a['hombres'];
        $totalB = $b['mujeres'] + $b['hombres'];
        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }
        return strcasecmp($a['puesto'], $b['puesto']);
    });

    return $filas;
}

function normalizarTextoPuesto(string $texto): string
{
    $v = trim($texto);
    if ($v === '') {
        return '';
    }

    $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
    if ($transliterado !== false) {
        $v = $transliterado;
    }

    $v = strtoupper($v);
    $v = preg_replace('/\s+/', ' ', $v) ?? $v;

    return trim($v);
}

/**
 * Obtiene conteos de puestos directivos (solo grupo 01) agrupados por puesto.
 *
 * @return array<int, array{puesto:string,mujeres:int,hombres:int}>
 */
function obtenerConteosPuestosDirectivosGrupo01(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.puesto_empresa,
            de.agrup_class_prof,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.puesto_empresa IS NOT NULL
          AND TRIM(de.puesto_empresa) <> ''
          AND de.agrup_class_prof IS NOT NULL
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de puestos directivos.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $acumulado = [];

    while ($row = $result->fetch_assoc()) {
        if (!esGrupo01((string)($row['agrup_class_prof'] ?? ''))) {
            continue;
        }

        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $puestoOriginal = trim((string)($row['puesto_empresa'] ?? ''));
        if ($puestoOriginal === '') {
            continue;
        }

        $clave = normalizarTextoPuesto($puestoOriginal);
        if ($clave === '') {
            continue;
        }

        if (!isset($acumulado[$clave])) {
            $acumulado[$clave] = [
                'puesto' => $puestoOriginal,
                'mujeres' => 0,
                'hombres' => 0,
            ];
        }

        if ($esMujer) {
            $acumulado[$clave]['mujeres']++;
        } else {
            $acumulado[$clave]['hombres']++;
        }
    }

    $filas = array_values($acumulado);
    usort($filas, static function (array $a, array $b): int {
        $totalA = $a['mujeres'] + $a['hombres'];
        $totalB = $b['mujeres'] + $b['hombres'];
        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }
        return strcasecmp($a['puesto'], $b['puesto']);
    });

    return $filas;
}

function esGrupo01(string $valor): bool
{
    $v = trim($valor);
    if ($v === '') {
        return false;
    }

    $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
    if ($transliterado !== false) {
        $v = $transliterado;
    }

    $v = strtoupper($v);
    $v = preg_replace('/\s+/', '', $v) ?? $v;

    return in_array($v, ['01', '1', 'GRUPO01', 'GRUPO1'], true);
}

/**
 * Obtiene conteos por área funcional (dpto_empresa), agrupados por nombre.
 *
 * @return array<int, array{area:string,mujeres:int,hombres:int}>
 */
function obtenerConteosPorAreaFuncional(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.dpto_empresa,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.dpto_empresa IS NOT NULL
          AND TRIM(de.dpto_empresa) <> ''
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
           
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de areas funcionales.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $acumulado = [];

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $areaOriginal = trim((string)($row['dpto_empresa'] ?? ''));
        if ($areaOriginal === '') {
            continue;
        }

        $clave = normalizarTextoPuesto($areaOriginal);
        if ($clave === '') {
            continue;
        }

        if (!isset($acumulado[$clave])) {
            $acumulado[$clave] = [
                'area' => $areaOriginal,
                'mujeres' => 0,
                'hombres' => 0,
            ];
        }

        if ($esMujer) {
            $acumulado[$clave]['mujeres']++;
        } else {
            $acumulado[$clave]['hombres']++;
        }
    }

    $filas = array_values($acumulado);
    usort($filas, static function (array $a, array $b): int {
        $totalA = $a['mujeres'] + $a['hombres'];
        $totalB = $b['mujeres'] + $b['hombres'];
        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }
        return strcasecmp($a['area'], $b['area']);
    });

    return $filas;
}

/**
 * Obtiene conteos por area funcional para altas en periodo de ano_datos.
 *
 * Aplica la misma agrupacion de hoja 8, pero solo incluye filas donde
 * inicio_contratacion este entre fecha_inicio y fecha_fin de ano_datos.
 *
 * @return array<int, array{area:string,mujeres:int,hombres:int}>
 */
function obtenerConteosPorAreaFuncionalEnPeriodo(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.dpto_empresa,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.dpto_empresa IS NOT NULL
          AND TRIM(de.dpto_empresa) <> ''
          AND de.inicio_contratacion IS NOT NULL
          AND ad.fecha_inicio IS NOT NULL
          AND ad.fecha_fin IS NOT NULL
          AND de.inicio_contratacion BETWEEN ad.fecha_inicio AND ad.fecha_fin
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de areas funcionales en periodo.');
    }
    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $acumulado = [];

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $areaOriginal = trim((string)($row['dpto_empresa'] ?? ''));
        if ($areaOriginal === '') {
            continue;
        }

        $clave = normalizarTextoPuesto($areaOriginal);
        if ($clave === '') {
            continue;
        }

        if (!isset($acumulado[$clave])) {
            $acumulado[$clave] = [
                'area' => $areaOriginal,
                'mujeres' => 0,
                'hombres' => 0,
            ];
        }

        if ($esMujer) {
            $acumulado[$clave]['mujeres']++;
        } else {
            $acumulado[$clave]['hombres']++;
        }
    }

    $filas = array_values($acumulado);
    usort($filas, static function (array $a, array $b): int {
        $totalA = $a['mujeres'] + $a['hombres'];
        $totalB = $b['mujeres'] + $b['hombres'];
        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }
        return strcasecmp($a['area'], $b['area']);
    });

    return $filas;
}

/**
 * Obtiene los conteos por numero de hijos y sexo.
 *
 * Indices (0-3):
 * 0: 0 hijos
 * 1: 1 hijo
 * 2: 2 hijos
 * 3: 3 o mas hijos
 *
 * @return array{mujeres: int[], hombres: int[]}
 */
function obtenerConteosPorHijos(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
     
SELECT
    de.sexo,
    de.hijos,
    de.salario_base_eq,
    de.salario_base_ef
FROM datos_empleados de
INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
WHERE ce.id_empresa = ?
  AND de.id_ano_datos = ?
    AND de.hijos IS NOT NULL
    AND COALESCE(de.salario_base_eq, 0) <> 0
    AND COALESCE(de.salario_base_ef, 0) <> 0;
        "
    );
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de hijos.');
    }
    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 4, 0);
    $hombres = array_fill(0, 4, 0);

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $hijos = (int)($row['hijos'] ?? 0);
        if ($hijos < 0) {
            continue;
        }

        $indice = 0;
        if ($hijos === 0) {
            $indice = 0;
        } elseif ($hijos === 1) {
            $indice = 1;
        } elseif ($hijos === 2) {
            $indice = 2;
        } else {
            $indice = 3;
        }

        if ($esMujer) {
            $mujeres[$indice]++;
        } else {
            $hombres[$indice]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

/**
 * Obtiene los conteos por grupo profesional y sexo.
 *
 * Indices (0-10):
 * 0=I, 1=II, 2=III, 3=IV, 4=V, 5=VI, 6=VII, 7=VIII, 8=IX, 9=X, 10=XI
 *
 * @return array{mujeres: int[], hombres: int[]}
 */
function obtenerConteosPorGrupoProfesional(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.grupo_profesional,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ? 
          AND de.grupo_profesional IS NOT NULL
          AND TRIM(de.grupo_profesional) <> ''
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de grupo profesional.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 11, 0);
    $hombres = array_fill(0, 11, 0);

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $indice = obtenerIndiceGrupoProfesional((string)($row['grupo_profesional'] ?? ''));
        if ($indice === null) {
            continue;
        }

        if ($esMujer) {
            $mujeres[$indice]++;
        } else {
            $hombres[$indice]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

function obtenerIndiceGrupoProfesional(string $valor): ?int
{
    $v = trim($valor);
    if ($v === '') {
        return null;
    }

    $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
    if ($transliterado !== false) {
        $v = $transliterado;
    }

    $v = strtoupper($v);
    $v = preg_replace('/\s+/', '', $v) ?? $v;
    $v = str_replace(['.', '-', '_'], '', $v);

    $mapa = [
        '1' => 0,
        'I' => 0,
        '2' => 1,
        'II' => 1,
        '3' => 2,
        'III' => 2,
        '4' => 3,
        'IV' => 3,
        '5' => 4,
        'V' => 4,
        '6' => 5,
        'VI' => 5,
        '7' => 6,
        'VII' => 6,
        '8' => 7,
        'VIII' => 7,
        '9' => 8,
        'IX' => 8,
        '10' => 9,
        'X' => 9,
        '11' => 10,
        'XI' => 10,
    ];

    return $mapa[$v] ?? null;
}

/**
 * Hoja 17: conteos por edad para personas contratadas dentro del periodo de ano_datos.
 *
 * Solo incluye filas donde inicio_contratacion este entre fecha_inicio y fecha_fin.
 * Rango de edad soportado en la hoja: <20, 20-24, ..., 60, 61, 62.
 *
 * @return array{mujeres: int[], hombres: int[]}
 */
function obtenerConteosEdadContratacionPeriodo(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            de.sexo,
            de.fecha_nacimiento,
            de.inicio_contratacion,
            ad.fecha_inicio,
            ad.fecha_fin,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ?
          AND de.id_ano_datos = ?
          AND de.fecha_nacimiento IS NOT NULL
          AND de.inicio_contratacion IS NOT NULL
          AND ad.fecha_inicio IS NOT NULL
          AND ad.fecha_fin IS NOT NULL
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de hoja 17.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $mujeres = array_fill(0, 12, 0);
    $hombres = array_fill(0, 12, 0);

    while ($row = $result->fetch_assoc()) {
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) {
            continue;
        }

        $fechaNacimiento = \DateTime::createFromFormat('Y-m-d', (string)$row['fecha_nacimiento']);
        $inicioContratacion = \DateTime::createFromFormat('Y-m-d', (string)$row['inicio_contratacion']);
        $fechaInicio = \DateTime::createFromFormat('Y-m-d', (string)$row['fecha_inicio']);
        $fechaFin = \DateTime::createFromFormat('Y-m-d', (string)$row['fecha_fin']);

        if ($fechaNacimiento === false || $inicioContratacion === false || $fechaInicio === false || $fechaFin === false) {
            continue;
        }

        // Solo contrataciones dentro del periodo del ano_datos.
        if ($inicioContratacion < $fechaInicio || $inicioContratacion > $fechaFin) {
            continue;
        }

        $edad = $fechaFin->diff($fechaNacimiento)->y;
        $indice = obtenerIndiceEdadHoja17($edad);
        if ($indice === null) {
            continue;
        }

        if ($esMujer) {
            $mujeres[$indice]++;
        } else {
            $hombres[$indice]++;
        }
    }

    return ['mujeres' => $mujeres, 'hombres' => $hombres];
}

function obtenerIndiceEdadHoja17(int $edad): ?int
{
    if ($edad < 0) return null;
    if ($edad < 20) return 0;
    if ($edad <= 24) return 1;
    if ($edad <= 29) return 2;
    if ($edad <= 34) return 3;
    if ($edad <= 39) return 4;
    if ($edad <= 44) return 5;
    if ($edad <= 49) return 6;
    if ($edad <= 54) return 7;
    if ($edad <= 59) return 8;
    if ($edad === 60) return 9;
    if ($edad === 61) return 10;
    if ($edad === 62) return 11;
    return null;
}

/**
 * Genera el archivo de porcentajes para una empresa a partir de la plantilla base.
 *
 * Escribe en hoja 0 (Total Plantilla):
 * - C3: total mujeres
 * - E3: total hombres
 * - C8: total mujeres
 * - D8: total hombres
 *
 * Escribe en hoja 1 (EDAD):
 * - C3:C15, G3:G14: rangos de edad para mujeres y hombres
 * - C16: total mujeres, G15: total hombres
 * - D20:D32, E20:E32: segundo bloque sin totales
 *
 * Escribe en hoja 3 (NIVEL DE ESTUDIOS):
 * - C3:C(N): niveles por sexo (dinámico)
 * - G3:G(N): niveles por sexo (dinámico)
 * - Fila de totales dinámica
 *
 * Escribe en hoja 4 (MODALIDAD DEL CONTRATO):
 * - C3:C7 y G3:G7: modalidades por sexo
 * - C8: total mujeres, G8: total hombres
 *
 * Escribe en hoja 5 (PORCENTAJE DE JORNADA):
 * - C3:C6 y G3:G6: rangos por sexo
 * - C7: total mujeres, G7: total hombres
 *
 * Escribe en hoja 6 (PUESTOS PROFESIONALES):
 * - B3:B12: nombre del puesto (agrupado)
 * - C3:C12: total mujeres por puesto
 * - G3:G12: total hombres por puesto
 *
 * Escribe en hoja 7 (PUESTOS DIRECTIVOS):
 * - B3:B12: nombre del puesto (solo grupo 01)
 * - C3:C12: total mujeres por puesto
 * - G3:G12: total hombres por puesto
 *
 * Escribe en hoja 8 (AREAS FUNCIONALES):
 * - B3:B12: nombre del departamento/area
 * - C3:C12: total mujeres por area
 * - G3:G12: total hombres por area
 *
 * Escribe en hoja 10 (HIJOS):
 * - C3:C6: 0, 1, 2, 3 o mas hijos (mujeres)
 * - G3:G6: 0, 1, 2, 3 o mas hijos (hombres)
 *
 * Escribe en hoja 15 (GRUPO PROFESIONAL):
 * - C3:C13: profesional 1..11 (mujeres)
 * - G3:G13: profesional 1..11 (hombres)
 *
 * Escribe en hoja 17 (EDAD POR CONTRATACION EN PERIODO):
 * - C3:C14: rangos de edad (mujeres)
 * - G3:G14: rangos de edad (hombres)
 * Solo personas con inicio_contratacion dentro de fecha_inicio y fecha_fin de ano_datos.
 *
 * Escribe en hoja 18 (MODALIDAD CONTRATO EN PERIODO):
 * - C3:C7 y G3:G7: modalidades por sexo
 * - C8: total mujeres, G8: total hombres
 * Solo personas con inicio_contratacion dentro de fecha_inicio y fecha_fin de ano_datos.
 *
 * Escribe en hoja 19 (PORCENTAJE DE JORNADA EN PERIODO):
 * - C3:C6: rangos por sexo (mujeres)
 * - G3:G6: rangos por sexo (hombres)
 * Solo personas con inicio_contratacion dentro de fecha_inicio y fecha_fin de ano_datos.
 *
 * Escribe en hoja 20 (PUESTOS PROFESIONALES EN PERIODO):
 * - B3:B12: nombre del puesto (agrupado)
 * - C3:C12: total mujeres por puesto
 * - G3:G12: total hombres por puesto
 * Solo personas con inicio_contratacion dentro de fecha_inicio y fecha_fin de ano_datos.
 *
 * Escribe en hoja 21 (AREAS FUNCIONALES EN PERIODO):
 * - B3:B12: nombre del departamento/area
 * - C3:C12: total mujeres por area
 * - G3:G12: total hombres por area
 * Solo personas con inicio_contratacion dentro de fecha_inicio y fecha_fin de ano_datos.
 */
function generarCuadroPorcentajesEmpresa(mysqli $db, int $idEmpresa, int $idAnoDatos, string $razonSocial): string
{
    $stmt = $db->prepare(
        "
SELECT
    SUM(CASE WHEN t.sexo_normalizado = 'MUJER' THEN 1 ELSE 0 END) AS total_mujeres,
    SUM(CASE WHEN t.sexo_normalizado = 'HOMBRE' THEN 1 ELSE 0 END) AS total_hombres
FROM (
    SELECT de.id,
           CASE
               WHEN LOWER(TRIM(de.sexo)) IN ('m', 'mujer', 'f', 'femenino') THEN 'MUJER'
               WHEN LOWER(TRIM(de.sexo)) IN ('h', 'hombre', 'masculino') THEN 'HOMBRE'
               ELSE NULL
           END AS sexo_normalizado
    FROM datos_empleados de
    INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
    INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
    WHERE ce.id_empresa = ?
      AND de.id_ano_datos = ?
            AND COALESCE(de.salario_base_eq, 0) <> 0
            AND COALESCE(de.salario_base_ef, 0) <> 0
) t"
    );

    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de totales por sexo.');
    }

    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $totales = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $totalMujeres = (int)($totales['total_mujeres'] ?? 0);
    $totalHombres = (int)($totales['total_hombres'] ?? 0);

    // Obtener conteos por rango de edad (usando fecha actual como referencia)
    $fechaReferencia = date('Y-m-d');
    $conteosEdad = obtenerConteosPorRangoEdad($db, $idEmpresa, $idAnoDatos, $fechaReferencia);
    $conteosAntiguedad = obtenerConteosPorRangoAntiguedad($db, $idEmpresa, $idAnoDatos, $fechaReferencia);
    $conteosEstudios = obtenerConteosEstudiosDinamico($db, $idEmpresa, $idAnoDatos);
    $conteosContrato = obtenerConteosPorModalidadContrato($db, $idEmpresa, $idAnoDatos);
    $conteosJornada = obtenerConteosPorPorcentajeJornada($db, $idEmpresa, $idAnoDatos);
    $conteosPuestos = obtenerConteosPorPuestoProfesional($db, $idEmpresa, $idAnoDatos);
    $conteosPuestosDirectivos = obtenerConteosPuestosDirectivosGrupo01($db, $idEmpresa, $idAnoDatos);
    $conteosAreas = obtenerConteosPorAreaFuncional($db, $idEmpresa, $idAnoDatos);
    $conteosHijos = obtenerConteosPorHijos($db, $idEmpresa, $idAnoDatos);
    $conteosGrupoProfesional = obtenerConteosPorGrupoProfesional($db, $idEmpresa, $idAnoDatos);
    $conteosEdadContratacionPeriodo = obtenerConteosEdadContratacionPeriodo($db, $idEmpresa, $idAnoDatos);
    $conteosContratoEnPeriodo = obtenerConteosPorModalidadContratoEnPeriodo($db, $idEmpresa, $idAnoDatos);
    $conteosJornadaEnPeriodo = obtenerConteosPorPorcentajeJornadaEnPeriodo($db, $idEmpresa, $idAnoDatos);
    $conteosPuestosEnPeriodo = obtenerConteosPorPuestoProfesionalEnPeriodo($db, $idEmpresa, $idAnoDatos);
    $conteosAreasEnPeriodo = obtenerConteosPorAreaFuncionalEnPeriodo($db, $idEmpresa, $idAnoDatos);

    $plantillaPath = __DIR__ . '/../cuadro_porcentajes/CUADRO_PORCENTAJES.xlsx';
    if (!is_file($plantillaPath)) {
        throw new RuntimeException('No existe la plantilla CUADRO_PORCENTAJES.xlsx.');
    }

    $destinoDir = __DIR__ . '/../empresa_porcentajes';
    if (!is_dir($destinoDir) && !mkdir($destinoDir, 0755, true)) {
        throw new RuntimeException('No se pudo crear el directorio empresa_porcentajes.');
    }

    $spreadsheet = IOFactory::load($plantillaPath);

    // ================== HOJA 0: Total Plantilla ==================
    $sheet = $spreadsheet->getSheet(0);
    $sheet->setCellValue('C3', $totalMujeres);
    $sheet->setCellValue('E3', $totalHombres);
    $sheet->setCellValue('C8', $totalMujeres);
    $sheet->setCellValue('D8', $totalHombres);


    // ================== HOJA 1: EDAD ==================
    try {
        $sheetEdad = $spreadsheet->getSheetByName('EDAD');
        if ($sheetEdad === null) {
            $sheetEdad = $spreadsheet->getSheetByName('edad');
        }
        if ($sheetEdad === null) {
            $sheetEdad = $spreadsheet->getSheet(1);
        }

        // Primer bloque: C3:C16 para mujeres, G3:G15 para hombres
        $filasRangos = ['C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10', 'C11', 'C12', 'C13', 'C14', 'C15'];
        // Añadir columna de Total Franja (L) y su suma total
        $numFilasEdad = count($filasRangos);
        for ($i = 0; $i < $numFilasEdad; $i++) {
            $filaExcel = 3 + $i;
            $sheetEdad->setCellValue('L' . $filaExcel, '=C' . $filaExcel . '+G' . $filaExcel);
        }
        // Fila de total general de franja
        $filaTotalEdad = 3 + $numFilasEdad;
        $sheetEdad->setCellValue('L' . $filaTotalEdad, '=SUM(L3:L' . ($filaTotalEdad - 1) . ')');
        $filasRangos = ['C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10', 'C11', 'C12', 'C13', 'C14', 'C15'];
        $filasRangosHombres = ['G3', 'G4', 'G5', 'G6', 'G7', 'G8', 'G9', 'G10', 'G11', 'G12', 'G13', 'G14', 'G15'];

        foreach ($conteosEdad['mujeres'] as $indice => $conteo) {
            if (isset($filasRangos[$indice])) {
                $sheetEdad->setCellValue($filasRangos[$indice], $conteo);
            }
        }

        foreach ($conteosEdad['hombres'] as $indice => $conteo) {
            if (isset($filasRangosHombres[$indice])) {
                $sheetEdad->setCellValue($filasRangosHombres[$indice], $conteo);
            }
        }


        // Segundo bloque: D20:D32 para mujeres, E20:E32 para hombres (sin totales)
        $filasBloque2 = ['D20', 'D21', 'D22', 'D23', 'D24', 'D25', 'D26', 'D27', 'D28', 'D29', 'D30', 'D31', 'D32'];
        $filasBloque2Hombres = ['E20', 'E21', 'E22', 'E23', 'E24', 'E25', 'E26', 'E27', 'E28', 'E29', 'E30', 'E31', 'E32'];

        foreach ($conteosEdad['mujeres'] as $indice => $conteo) {
            if (isset($filasBloque2[$indice])) {
                $sheetEdad->setCellValue($filasBloque2[$indice], $conteo);
            }
        }

        foreach ($conteosEdad['hombres'] as $indice => $conteo) {
            if (isset($filasBloque2Hombres[$indice])) {
                $sheetEdad->setCellValue($filasBloque2Hombres[$indice], $conteo);
            }
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja EDAD: ' . $e->getMessage());
    }

    // ================== HOJA 2: ANTIGUEDAD ==================
    try {
        $sheetAntiguedad = $spreadsheet->getSheetByName('ANTIGUEDAD');
        if ($sheetAntiguedad === null) {
            $sheetAntiguedad = $spreadsheet->getSheetByName('antiguedad');
        }
        if ($sheetAntiguedad === null) {
            $sheetAntiguedad = $spreadsheet->getSheet(2);
        }

        // Primer bloque: C3:C8 para mujeres, G3:G8 para hombres (con totales)
        $filasAntiguedad = ['C3', 'C4', 'C5', 'C6', 'C7', 'C8'];
        $filasAntiguedadHombres = ['G3', 'G4', 'G5', 'G6', 'G7', 'G8'];

        foreach ($conteosAntiguedad['mujeres'] as $indice => $conteo) {
            if (isset($filasAntiguedad[$indice])) {
                $sheetAntiguedad->setCellValue($filasAntiguedad[$indice], $conteo);
            }
        }

        foreach ($conteosAntiguedad['hombres'] as $indice => $conteo) {
            if (isset($filasAntiguedadHombres[$indice])) {
                $sheetAntiguedad->setCellValue($filasAntiguedadHombres[$indice], $conteo);
            }
        }

        // Total mujeres en C9
        $sheetAntiguedad->setCellValue('C9', $totalMujeres);
        // Total hombres en G9
        $sheetAntiguedad->setCellValue('G9', $totalHombres);

        // Segundo bloque: D14:D19 para mujeres, E14:E19 para hombres (sin totales)
        $filasBloque2Antiguedad = ['D14', 'D15', 'D16', 'D17', 'D18', 'D19'];
        $filasBloque2AntiguedadHombres = ['E14', 'E15', 'E16', 'E17', 'E18', 'E19'];

        foreach ($conteosAntiguedad['mujeres'] as $indice => $conteo) {
            if (isset($filasBloque2Antiguedad[$indice])) {
                $sheetAntiguedad->setCellValue($filasBloque2Antiguedad[$indice], $conteo);
            }
        }

        foreach ($conteosAntiguedad['hombres'] as $indice => $conteo) {
            if (isset($filasBloque2AntiguedadHombres[$indice])) {
                $sheetAntiguedad->setCellValue($filasBloque2AntiguedadHombres[$indice], $conteo);
            }
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja ANTIGUEDAD: ' . $e->getMessage());
    }


    // ================== HOJA 3: NIVEL DE ESTUDIOS (ESCALABLE) ==================
    try {
        $sheetEstudios = $spreadsheet->getSheetByName('NIVEL DE ESTUDIOS');
        if ($sheetEstudios === null) {
            $sheetEstudios = $spreadsheet->getSheetByName('nivel de estudios');
        }
        if ($sheetEstudios === null) {
            $sheetEstudios = $spreadsheet->getSheet(3);
        }

        // Elimina filas antiguas de datos (opcional, para limpiar)
        $filaPlantilla = 3;
        $numFilas = count($conteosEstudios);
        $filaTotalNueva = $filaPlantilla + $numFilas;
        $highestRow = $sheetEstudios->getHighestRow();
        if ($highestRow > $filaPlantilla) {
            $sheetEstudios->removeRow($filaPlantilla, $highestRow - $filaPlantilla + 1);
        }

        // Pinta los datos dinámicamente
        for ($i = 0; $i < $numFilas; $i++) {
            $filaExcel = $filaPlantilla + $i;
            $row = $conteosEstudios[$i];
            $sheetEstudios->setCellValue('B' . $filaExcel, $row['estudio']);
            $sheetEstudios->setCellValue('C' . $filaExcel, $row['mujeres']);
            $sheetEstudios->setCellValue('G' . $filaExcel, $row['hombres']);
            // Total de franja (mujeres + hombres)
            $sheetEstudios->setCellValue('L' . $filaExcel, '=C' . $filaExcel . '+G' . $filaExcel);
            // Fórmulas por fila
            $sheetEstudios->setCellValue('D' . $filaExcel, '=C' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetEstudios->setCellValue('E' . $filaExcel, '=C' . $filaExcel . '/$C$' . $filaTotalNueva . '*100');
            $sheetEstudios->setCellValue('F' . $filaExcel, '=C' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetEstudios->setCellValue('H' . $filaExcel, '=G' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetEstudios->setCellValue('I' . $filaExcel, '=G' . $filaExcel . '/$G$' . $filaTotalNueva . '*100');
            $sheetEstudios->setCellValue('J' . $filaExcel, '=G' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetEstudios->setCellValue('K' . $filaExcel, '=F' . $filaExcel . '+J' . $filaExcel);
            $sheetEstudios->setCellValue('M' . $filaExcel, '=H' . $filaExcel . '-D' . $filaExcel);
            $sheetEstudios->setCellValue('N' . $filaExcel, '=IF(G' . $filaExcel . '=0,0,C' . $filaExcel . '/G' . $filaExcel . ')');
        }

        // Fila de totales
        $sheetEstudios->setCellValue('B' . $filaTotalNueva, 'TOTAL');
        $sheetEstudios->setCellValue('C' . $filaTotalNueva, '=SUM(C' . $filaPlantilla . ':C' . ($filaTotalNueva - 1) . ')');
        $sheetEstudios->setCellValue('G' . $filaTotalNueva, '=SUM(G' . $filaPlantilla . ':G' . ($filaTotalNueva - 1) . ')');
        $sheetEstudios->setCellValue('L' . $filaTotalNueva, '=SUM(L' . $filaPlantilla . ':L' . ($filaTotalNueva - 1) . ')');
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja NIVEL DE ESTUDIOS: ' . $e->getMessage());
    }

    // ================== HOJA 4: MODALIDAD DEL CONTRATO ==================
    try {
        $sheetContrato = $spreadsheet->getSheetByName('MODALIDAD DEL CONTRATO');
        if ($sheetContrato === null) {
            $sheetContrato = $spreadsheet->getSheetByName('modalidad del contrato');
        }
        if ($sheetContrato === null) {
            $sheetContrato = $spreadsheet->getSheetByName('MODALIDAD CONTRATO');
        }
        if ($sheetContrato === null) {
            $sheetContrato = $spreadsheet->getSheetByName('modalidad contrato');
        }
        if ($sheetContrato === null) {
            $sheetContrato = $spreadsheet->getSheet(4);
        }

        $filasContratoMujeres = ['C3', 'C4', 'C5', 'C6', 'C7'];
        $filasContratoHombres = ['G3', 'G4', 'G5', 'G6', 'G7'];

        foreach ($conteosContrato['mujeres'] as $indice => $conteo) {
            if (isset($filasContratoMujeres[$indice])) {
                $sheetContrato->setCellValue($filasContratoMujeres[$indice], $conteo);
            }
        }

        foreach ($conteosContrato['hombres'] as $indice => $conteo) {
            if (isset($filasContratoHombres[$indice])) {
                $sheetContrato->setCellValue($filasContratoHombres[$indice], $conteo);
            }
        }

        $sheetContrato->setCellValue('C8', array_sum($conteosContrato['mujeres']));
        $sheetContrato->setCellValue('G8', array_sum($conteosContrato['hombres']));

        // Segundo bloque, mismas categorías sin totales.
        $filasContratoBloque2Mujeres = ['D13', 'D14', 'D15', 'D16', 'D17'];
        $filasContratoBloque2Hombres = ['E13', 'E14', 'E15', 'E16', 'E17'];

        foreach ($conteosContrato['mujeres'] as $indice => $conteo) {
            if (isset($filasContratoBloque2Mujeres[$indice])) {
                $sheetContrato->setCellValue($filasContratoBloque2Mujeres[$indice], $conteo);
            }
        }

        foreach ($conteosContrato['hombres'] as $indice => $conteo) {
            if (isset($filasContratoBloque2Hombres[$indice])) {
                $sheetContrato->setCellValue($filasContratoBloque2Hombres[$indice], $conteo);
            }
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja MODALIDAD DEL CONTRATO: ' . $e->getMessage());
    }

    // ================== HOJA 5: PORCENTAJE DE JORNADA ==================
    try {
        $sheetJornada = $spreadsheet->getSheetByName('PORCENTAJE DE JORNADA');
        if ($sheetJornada === null) {
            $sheetJornada = $spreadsheet->getSheetByName('porcentaje de jornada');
        }
        if ($sheetJornada === null) {
            $sheetJornada = $spreadsheet->getSheetByName('JORNADA');
        }
        if ($sheetJornada === null) {
            $sheetJornada = $spreadsheet->getSheetByName('jornada');
        }
        if ($sheetJornada === null) {
            $sheetJornada = $spreadsheet->getSheet(5);
        }

        $filasJornadaMujeres = ['C3', 'C4', 'C5', 'C6'];
        $filasJornadaHombres = ['G3', 'G4', 'G5', 'G6'];

        foreach ($conteosJornada['mujeres'] as $indice => $conteo) {
            if (isset($filasJornadaMujeres[$indice])) {
                $sheetJornada->setCellValue($filasJornadaMujeres[$indice], $conteo);
            }
        }

        foreach ($conteosJornada['hombres'] as $indice => $conteo) {
            if (isset($filasJornadaHombres[$indice])) {
                $sheetJornada->setCellValue($filasJornadaHombres[$indice], $conteo);
            }
        }

        $sheetJornada->setCellValue('C7', array_sum($conteosJornada['mujeres']));
        $sheetJornada->setCellValue('G7', array_sum($conteosJornada['hombres']));
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja PORCENTAJE DE JORNADA: ' . $e->getMessage());
    }

    // ================== HOJA 6: PUESTOS PROFESIONALES ==================
    try {
        $sheetPuestos = $spreadsheet->getSheetByName('PUESTOS PROFESIONALES');
        if ($sheetPuestos === null) {
            $sheetPuestos = $spreadsheet->getSheetByName('puestos profesionales');
        }
        if ($sheetPuestos === null) {
            $sheetPuestos = $spreadsheet->getSheetByName('PUESTOS');
        }
        if ($sheetPuestos === null) {
            $sheetPuestos = $spreadsheet->getSheetByName('puestos');
        }
        if ($sheetPuestos === null) {
            $sheetPuestos = $spreadsheet->getSheet(6);
        }

        // BLOQUE SIMPLE Y ROBUSTO: solo pintar datos, fórmulas y crear la fila de totales
        $numFilas = count($conteosPuestos);
        $filaPlantilla = 3;
        $filaTotalNueva = $filaPlantilla + $numFilas;
        // Limpia las filas de datos antiguas (opcional, si quieres limpiar)
        $highestRow = $sheetPuestos->getHighestRow();
        if ($highestRow > $filaPlantilla) {
            $sheetPuestos->removeRow($filaPlantilla, $highestRow - $filaPlantilla + 1);
        }

        // Pinta los datos y las fórmulas
        for ($i = 0; $i < $numFilas; $i++) {
            $filaExcel = $filaPlantilla + $i;
            $puesto = $conteosPuestos[$i];
            $sheetPuestos->setCellValue('B' . $filaExcel, $puesto['puesto']);
            $sheetPuestos->setCellValue('C' . $filaExcel, $puesto['mujeres']);
            $sheetPuestos->setCellValue('G' . $filaExcel, $puesto['hombres']);
            // Fórmulas dinámicas por fila
            // D: Índice distribución mujeres =C{fila}/L{fila}*100
            // Si quieres mostrar el resultado calculado en PHP, usa:
            // $valor = $L != 0 ? $fmt_coma($C/$L*100) : '';
            // $sheetPuestos->setCellValue('D' . $filaExcel, $valor);
            // Pero si quieres mantener la fórmula en Excel, puedes dejarlo así.
            $sheetPuestos->setCellValue('D' . $filaExcel, '=C' . $filaExcel . '/L' . $filaExcel . '*100');
            // E: Índice concentración mujeres =C{fila}/$C${filaTotalNueva}*100
            $sheetPuestos->setCellValue('E' . $filaExcel, '=C' . $filaExcel . '/$C$' . $filaTotalNueva . '*100');
            // F: % Total Mujeres =C{fila}/$L${filaTotalNueva}*100
            // Si quieres mostrar el resultado calculado en PHP, usa:
            // $valor = $LTotal != 0 ? $fmt_coma($C/$LTotal*100) : '';
            // $sheetPuestos->setCellValue('F' . $filaExcel, $valor);
            $sheetPuestos->setCellValue('F' . $filaExcel, '=C' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            // H: Índice distribución hombres =G{fila}/L{fila}*100
            $sheetPuestos->setCellValue('H' . $filaExcel, '=G' . $filaExcel . '/L' . $filaExcel . '*100');
            // I: Índice concentración hombres =G{fila}/$G${filaTotalNueva}*100
            $sheetPuestos->setCellValue('I' . $filaExcel, '=G' . $filaExcel . '/$G$' . $filaTotalNueva . '*100');
            // J: % Total Hombres =G{fila}/$L${filaTotalNueva}*100
            $sheetPuestos->setCellValue('J' . $filaExcel, '=G' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            // K: % Total =F{fila}+J{fila}
            $sheetPuestos->setCellValue('K' . $filaExcel, '=F' . $filaExcel . '+J' . $filaExcel);
            // L: Total Franja =C{fila}+G{fila}
            $sheetPuestos->setCellValue('L' . $filaExcel, '=C' . $filaExcel . '+G' . $filaExcel);
            // M: Brecha de Género =H{fila}-D{fila}
            $sheetPuestos->setCellValue('M' . $filaExcel, '=H' . $filaExcel . '-D' . $filaExcel);
            // N: Índice Feminización =C{fila}/G{fila}
            $sheetPuestos->setCellValue('N' . $filaExcel, '=C' . $filaExcel . '/G' . $filaExcel);
        }

        // Crea la fila de totales
        $sheetPuestos->setCellValue('B' . $filaTotalNueva, 'TOTAL');
        $sheetPuestos->setCellValue('C' . $filaTotalNueva, '=SUM(C' . $filaPlantilla . ':C' . ($filaTotalNueva - 1) . ')');
        $sheetPuestos->setCellValue('G' . $filaTotalNueva, '=SUM(G' . $filaPlantilla . ':G' . ($filaTotalNueva - 1) . ')');
        $sheetPuestos->setCellValue('L' . $filaTotalNueva, '=SUM(L' . $filaPlantilla . ':L' . ($filaTotalNueva - 1) . ')');
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja PUESTOS PROFESIONALES: ' . $e->getMessage());
    }

    // ================== HOJA 7: PUESTOS DIRECTIVOS ==================
    try {
        $sheetDirectivos = $spreadsheet->getSheetByName('PUESTOS DIRECTIVOS');
        if ($sheetDirectivos === null) {
            $sheetDirectivos = $spreadsheet->getSheetByName('puestos directivos');
        }
        if ($sheetDirectivos === null) {
            $sheetDirectivos = $spreadsheet->getSheetByName('DIRECTIVOS');
        }
        if ($sheetDirectivos === null) {
            $sheetDirectivos = $spreadsheet->getSheetByName('directivos');
        }
        if ($sheetDirectivos === null) {
            $sheetDirectivos = $spreadsheet->getSheet(7);
        }

        // BLOQUE SIMPLE Y ROBUSTO: solo pintar datos, fórmulas y crear la fila de totales
        $numFilas = count($conteosPuestosDirectivos);
        $filaPlantilla = 3;
        $filaTotalNueva = $filaPlantilla + $numFilas;
        // Limpia las filas de datos antiguas (opcional, si quieres limpiar)
        $highestRow = $sheetDirectivos->getHighestRow();
        if ($highestRow > $filaPlantilla) {
            $sheetDirectivos->removeRow($filaPlantilla, $highestRow - $filaPlantilla + 1);
        }

        // Pinta los datos y las fórmulas
        for ($i = 0; $i < $numFilas; $i++) {
            $filaExcel = $filaPlantilla + $i;
            $puesto = $conteosPuestosDirectivos[$i];
            $sheetDirectivos->setCellValue('B' . $filaExcel, $puesto['puesto']);
            $sheetDirectivos->setCellValue('C' . $filaExcel, $puesto['mujeres']);
            $sheetDirectivos->setCellValue('G' . $filaExcel, $puesto['hombres']);
            // Fórmulas dinámicas por fila
            $sheetDirectivos->setCellValue('D' . $filaExcel, '=C' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetDirectivos->setCellValue('E' . $filaExcel, '=C' . $filaExcel . '/$C$' . $filaTotalNueva . '*100');
            $sheetDirectivos->setCellValue('F' . $filaExcel, '=C' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetDirectivos->setCellValue('H' . $filaExcel, '=G' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetDirectivos->setCellValue('I' . $filaExcel, '=G' . $filaExcel . '/$G$' . $filaTotalNueva . '*100');
            $sheetDirectivos->setCellValue('J' . $filaExcel, '=G' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetDirectivos->setCellValue('K' . $filaExcel, '=F' . $filaExcel . '+J' . $filaExcel);
            $sheetDirectivos->setCellValue('L' . $filaExcel, '=C' . $filaExcel . '+G' . $filaExcel);
            $sheetDirectivos->setCellValue('M' . $filaExcel, '=H' . $filaExcel . '-D' . $filaExcel);
            $sheetDirectivos->setCellValue('N' . $filaExcel, '=C' . $filaExcel . '/G' . $filaExcel);
        }

        // Crea la fila de totales
        $sheetDirectivos->setCellValue('B' . $filaTotalNueva, 'TOTAL');
        $sheetDirectivos->setCellValue('C' . $filaTotalNueva, '=SUM(C' . $filaPlantilla . ':C' . ($filaTotalNueva - 1) . ')');
        $sheetDirectivos->setCellValue('G' . $filaTotalNueva, '=SUM(G' . $filaPlantilla . ':G' . ($filaTotalNueva - 1) . ')');
        $sheetDirectivos->setCellValue('L' . $filaTotalNueva, '=SUM(L' . $filaPlantilla . ':L' . ($filaTotalNueva - 1) . ')');
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja PUESTOS DIRECTIVOS: ' . $e->getMessage());
    }

    // ================== HOJA 8: AREAS FUNCIONALES ==================
    try {
        $sheetAreas = $spreadsheet->getSheetByName('AREAS FUNCIONALES');
        if ($sheetAreas === null) {
            $sheetAreas = $spreadsheet->getSheetByName('areas funcionales');
        }
        if ($sheetAreas === null) {
            $sheetAreas = $spreadsheet->getSheetByName('AREA FUNCIONAL');
        }
        if ($sheetAreas === null) {
            $sheetAreas = $spreadsheet->getSheetByName('area funcional');
        }
        if ($sheetAreas === null) {
            $sheetAreas = $spreadsheet->getSheet(8);
        }

        // BLOQUE SIMPLE Y ROBUSTO: solo pintar datos, fórmulas y crear la fila de totales
        $numFilas = count($conteosAreas);
        $filaPlantilla = 3;
        $filaTotalNueva = $filaPlantilla + $numFilas;
        // Limpia las filas de datos antiguas (opcional, si quieres limpiar)
        $highestRow = $sheetAreas->getHighestRow();
        if ($highestRow > $filaPlantilla) {
            $sheetAreas->removeRow($filaPlantilla, $highestRow - $filaPlantilla + 1);
        }

        // Pinta los datos y las fórmulas
        for ($i = 0; $i < $numFilas; $i++) {
            $filaExcel = $filaPlantilla + $i;
            $area = $conteosAreas[$i];
            $sheetAreas->setCellValue('B' . $filaExcel, $area['area']);
            $sheetAreas->setCellValue('C' . $filaExcel, $area['mujeres']);
            $sheetAreas->setCellValue('G' . $filaExcel, $area['hombres']);
            // Fórmulas dinámicas por fila
            $sheetAreas->setCellValue('D' . $filaExcel, '=C' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetAreas->setCellValue('E' . $filaExcel, '=C' . $filaExcel . '/$C$' . $filaTotalNueva . '*100');
            $sheetAreas->setCellValue('F' . $filaExcel, '=C' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetAreas->setCellValue('H' . $filaExcel, '=G' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetAreas->setCellValue('I' . $filaExcel, '=G' . $filaExcel . '/$G$' . $filaTotalNueva . '*100');
            $sheetAreas->setCellValue('J' . $filaExcel, '=G' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetAreas->setCellValue('K' . $filaExcel, '=F' . $filaExcel . '+J' . $filaExcel);
            $sheetAreas->setCellValue('L' . $filaExcel, '=C' . $filaExcel . '+G' . $filaExcel);
            $sheetAreas->setCellValue('M' . $filaExcel, '=H' . $filaExcel . '-D' . $filaExcel);
            $sheetAreas->setCellValue('N' . $filaExcel, '=C' . $filaExcel . '/G' . $filaExcel);
        }

        // Crea la fila de totales
        $sheetAreas->setCellValue('B' . $filaTotalNueva, 'TOTAL');
        $sheetAreas->setCellValue('C' . $filaTotalNueva, '=SUM(C' . $filaPlantilla . ':C' . ($filaTotalNueva - 1) . ')');
        $sheetAreas->setCellValue('G' . $filaTotalNueva, '=SUM(G' . $filaPlantilla . ':G' . ($filaTotalNueva - 1) . ')');
        $sheetAreas->setCellValue('L' . $filaTotalNueva, '=SUM(L' . $filaPlantilla . ':L' . ($filaTotalNueva - 1) . ')');
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja AREAS FUNCIONALES: ' . $e->getMessage());
    }

    // ================== HOJA 10: HIJOS ==================
    try {
        $sheetHijos = $spreadsheet->getSheetByName('HIJOS');
        if ($sheetHijos === null) {
            $sheetHijos = $spreadsheet->getSheetByName('hijos');
        }
        if ($sheetHijos === null) {
            $sheetHijos = $spreadsheet->getSheet(10);
        }

        $filasMujeresHijos = ['C3', 'C4', 'C5', 'C6'];
        $filasHombresHijos = ['G3', 'G4', 'G5', 'G6'];

        foreach ($conteosHijos['mujeres'] as $indice => $conteo) {
            if (isset($filasMujeresHijos[$indice])) {
                $sheetHijos->setCellValue($filasMujeresHijos[$indice], $conteo);
            }
        }

        foreach ($conteosHijos['hombres'] as $indice => $conteo) {
            if (isset($filasHombresHijos[$indice])) {
                $sheetHijos->setCellValue($filasHombresHijos[$indice], $conteo);
            }
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja HIJOS: ' . $e->getMessage());
    }

    // ================== HOJA 15: GRUPO PROFESIONAL ==================
    try {
        $sheetGrupoProfesional = $spreadsheet->getSheetByName('GRUPO PROFESIONAL');
        if ($sheetGrupoProfesional === null) {
            $sheetGrupoProfesional = $spreadsheet->getSheetByName('grupo profesional');
        }
        if ($sheetGrupoProfesional === null) {
            $sheetGrupoProfesional = $spreadsheet->getSheet(15);
        }

        $filasMujeres = ['C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10', 'C11', 'C12', 'C13'];
        $filasHombres = ['G3', 'G4', 'G5', 'G6', 'G7', 'G8', 'G9', 'G10', 'G11', 'G12', 'G13'];

        foreach ($conteosGrupoProfesional['mujeres'] as $indice => $conteo) {
            if (isset($filasMujeres[$indice])) {
                $sheetGrupoProfesional->setCellValue($filasMujeres[$indice], $conteo);
            }
        }

        foreach ($conteosGrupoProfesional['hombres'] as $indice => $conteo) {
            if (isset($filasHombres[$indice])) {
                $sheetGrupoProfesional->setCellValue($filasHombres[$indice], $conteo);
            }
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja GRUPO PROFESIONAL: ' . $e->getMessage());
    }

    // ================== HOJA 17: EDAD (ALTAS EN PERIODO) ==================
    try {
        $sheetHoja17 = $spreadsheet->getSheetByName('EDAD');
        if ($sheetHoja17 === null) {
            $sheetHoja17 = $spreadsheet->getSheetByName('edad');
        }
        // Para evitar capturar la hoja 1 (tambien llamada EDAD), usamos indice como prioridad final.
        if ($sheetHoja17 === null || $spreadsheet->getIndex($sheetHoja17) !== 17) {
            $sheetHoja17 = $spreadsheet->getSheet(17);
        }

        $filasMujeres = ['C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10', 'C11', 'C12', 'C13', 'C14'];
        $filasHombres = ['G3', 'G4', 'G5', 'G6', 'G7', 'G8', 'G9', 'G10', 'G11', 'G12', 'G13', 'G14'];

        foreach ($conteosEdadContratacionPeriodo['mujeres'] as $indice => $conteo) {
            if (isset($filasMujeres[$indice])) {
                $sheetHoja17->setCellValue($filasMujeres[$indice], $conteo);
            }
        }

        foreach ($conteosEdadContratacionPeriodo['hombres'] as $indice => $conteo) {
            if (isset($filasHombres[$indice])) {
                $sheetHoja17->setCellValue($filasHombres[$indice], $conteo);
            }
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja 17 EDAD: ' . $e->getMessage());
    }

    // ================== HOJA 18: MODALIDAD CONTRATO (ALTAS EN PERIODO) ==================
    try {
        $sheetHoja18 = $spreadsheet->getSheetByName('MODALIDAD DEL CONTRATO');
        if ($sheetHoja18 === null) {
            $sheetHoja18 = $spreadsheet->getSheetByName('modalidad del contrato');
        }
        if ($sheetHoja18 === null || $spreadsheet->getIndex($sheetHoja18) !== 18) {
            $sheetHoja18 = $spreadsheet->getSheet(18);
        }

        $filasMujeres = ['C3', 'C4', 'C5', 'C6', 'C7'];
        $filasHombres = ['G3', 'G4', 'G5', 'G6', 'G7'];

        foreach ($conteosContratoEnPeriodo['mujeres'] as $indice => $conteo) {
            if (isset($filasMujeres[$indice])) {
                $sheetHoja18->setCellValue($filasMujeres[$indice], $conteo);
            }
        }

        foreach ($conteosContratoEnPeriodo['hombres'] as $indice => $conteo) {
            if (isset($filasHombres[$indice])) {
                $sheetHoja18->setCellValue($filasHombres[$indice], $conteo);
            }
        }

        $sheetHoja18->setCellValue('C8', array_sum($conteosContratoEnPeriodo['mujeres']));
        $sheetHoja18->setCellValue('G8', array_sum($conteosContratoEnPeriodo['hombres']));
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja 18 MODALIDAD CONTRATO: ' . $e->getMessage());
    }

    // ================== HOJA 19: PORCENTAJE DE JORNADA (ALTAS EN PERIODO) ==================
    try {
        $sheetHoja19 = $spreadsheet->getSheetByName('PORCENTAJE DE JORNADA');
        if ($sheetHoja19 === null) {
            $sheetHoja19 = $spreadsheet->getSheetByName('porcentaje de jornada');
        }
        if ($sheetHoja19 === null || $spreadsheet->getIndex($sheetHoja19) !== 19) {
            $sheetHoja19 = $spreadsheet->getSheet(19);
        }

        $filasMujeres = ['C3', 'C4', 'C5', 'C6'];
        $filasHombres = ['G3', 'G4', 'G5', 'G6'];

        foreach ($conteosJornadaEnPeriodo['mujeres'] as $indice => $conteo) {
            if (isset($filasMujeres[$indice])) {
                $sheetHoja19->setCellValue($filasMujeres[$indice], $conteo);
            }
        }

        foreach ($conteosJornadaEnPeriodo['hombres'] as $indice => $conteo) {
            if (isset($filasHombres[$indice])) {
                $sheetHoja19->setCellValue($filasHombres[$indice], $conteo);
            }
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja 19 PORCENTAJE DE JORNADA: ' . $e->getMessage());
    }

    // ================== HOJA 20: PUESTOS PROFESIONALES (ALTAS EN PERIODO) ==================

    try {
        $sheetHoja20 = $spreadsheet->getSheetByName('PUESTOS PROFESIONALES');
        if ($sheetHoja20 === null) {
            $sheetHoja20 = $spreadsheet->getSheetByName('puestos profesionales');
        }
        if ($sheetHoja20 === null || $spreadsheet->getIndex($sheetHoja20) !== 20) {
            $sheetHoja20 = $spreadsheet->getSheet(20);
        }

        // BLOQUE DINÁMICO Y ROBUSTO: limpia, pinta filas, fórmulas y totales
        $numFilas = count($conteosPuestosEnPeriodo);
        $filaPlantilla = 3;
        $filaTotalNueva = $filaPlantilla + $numFilas;
        // Limpia las filas de datos antiguas
        $highestRow = $sheetHoja20->getHighestRow();
        if ($highestRow > $filaPlantilla) {
            $sheetHoja20->removeRow($filaPlantilla, $highestRow - $filaPlantilla + 1);
        }

        // Pinta los datos y las fórmulas
        for ($i = 0; $i < $numFilas; $i++) {
            $filaExcel = $filaPlantilla + $i;
            $puesto = $conteosPuestosEnPeriodo[$i];
            $sheetHoja20->setCellValue('B' . $filaExcel, $puesto['puesto']);
            $sheetHoja20->setCellValue('C' . $filaExcel, $puesto['mujeres']);
            $sheetHoja20->setCellValue('G' . $filaExcel, $puesto['hombres']);
            // Fórmulas dinámicas por fila
            $sheetHoja20->setCellValue('D' . $filaExcel, '=C' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetHoja20->setCellValue('E' . $filaExcel, '=C' . $filaExcel . '/$C$' . $filaTotalNueva . '*100');
            $sheetHoja20->setCellValue('F' . $filaExcel, '=C' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetHoja20->setCellValue('H' . $filaExcel, '=G' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetHoja20->setCellValue('I' . $filaExcel, '=G' . $filaExcel . '/$G$' . $filaTotalNueva . '*100');
            $sheetHoja20->setCellValue('J' . $filaExcel, '=G' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetHoja20->setCellValue('K' . $filaExcel, '=F' . $filaExcel . '+J' . $filaExcel);
            $sheetHoja20->setCellValue('L' . $filaExcel, '=C' . $filaExcel . '+G' . $filaExcel);
            $sheetHoja20->setCellValue('M' . $filaExcel, '=H' . $filaExcel . '-D' . $filaExcel);
            $sheetHoja20->setCellValue('N' . $filaExcel, '=C' . $filaExcel . '/G' . $filaExcel);
        }

        // Crea la fila de totales
        $sheetHoja20->setCellValue('B' . $filaTotalNueva, 'TOTAL');
        $sheetHoja20->setCellValue('C' . $filaTotalNueva, '=SUM(C' . $filaPlantilla . ':C' . ($filaTotalNueva - 1) . ')');
        $sheetHoja20->setCellValue('G' . $filaTotalNueva, '=SUM(G' . $filaPlantilla . ':G' . ($filaTotalNueva - 1) . ')');
        $sheetHoja20->setCellValue('L' . $filaTotalNueva, '=SUM(L' . $filaPlantilla . ':L' . ($filaTotalNueva - 1) . ')');
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja 20 PUESTOS PROFESIONALES: ' . $e->getMessage());
    }

    // ================== HOJA 21: AREAS FUNCIONALES (ALTAS EN PERIODO) ==================

    try {
        $sheetHoja21 = $spreadsheet->getSheetByName('AREAS FUNCIONALES');
        if ($sheetHoja21 === null) {
            $sheetHoja21 = $spreadsheet->getSheetByName('areas funcionales');
        }
        if ($sheetHoja21 === null || $spreadsheet->getIndex($sheetHoja21) !== 21) {
            $sheetHoja21 = $spreadsheet->getSheet(21);
        }

        // BLOQUE DINÁMICO Y ROBUSTO: limpia, pinta filas, fórmulas y totales
        $numFilas = count($conteosAreasEnPeriodo);
        $filaPlantilla = 3;
        $filaTotalNueva = $filaPlantilla + $numFilas;
        // Limpia las filas de datos antiguas
        $highestRow = $sheetHoja21->getHighestRow();
        if ($highestRow > $filaPlantilla) {
            $sheetHoja21->removeRow($filaPlantilla, $highestRow - $filaPlantilla + 1);
        }

        // Pinta los datos y las fórmulas
        for ($i = 0; $i < $numFilas; $i++) {
            $filaExcel = $filaPlantilla + $i;
            $area = $conteosAreasEnPeriodo[$i];
            $sheetHoja21->setCellValue('B' . $filaExcel, $area['area']);
            $sheetHoja21->setCellValue('C' . $filaExcel, $area['mujeres']);
            $sheetHoja21->setCellValue('G' . $filaExcel, $area['hombres']);
            // Fórmulas dinámicas por fila
            $sheetHoja21->setCellValue('D' . $filaExcel, '=C' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetHoja21->setCellValue('E' . $filaExcel, '=C' . $filaExcel . '/$C$' . $filaTotalNueva . '*100');
            $sheetHoja21->setCellValue('F' . $filaExcel, '=C' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetHoja21->setCellValue('H' . $filaExcel, '=G' . $filaExcel . '/L' . $filaExcel . '*100');
            $sheetHoja21->setCellValue('I' . $filaExcel, '=G' . $filaExcel . '/$G$' . $filaTotalNueva . '*100');
            $sheetHoja21->setCellValue('J' . $filaExcel, '=G' . $filaExcel . '/$L$' . $filaTotalNueva . '*100');
            $sheetHoja21->setCellValue('K' . $filaExcel, '=F' . $filaExcel . '+J' . $filaExcel);
            $sheetHoja21->setCellValue('L' . $filaExcel, '=C' . $filaExcel . '+G' . $filaExcel);
            $sheetHoja21->setCellValue('M' . $filaExcel, '=H' . $filaExcel . '-D' . $filaExcel);
            $sheetHoja21->setCellValue('N' . $filaExcel, '=C' . $filaExcel . '/G' . $filaExcel);
        }

        // Crea la fila de totales
        $sheetHoja21->setCellValue('B' . $filaTotalNueva, 'TOTAL');
        $sheetHoja21->setCellValue('C' . $filaTotalNueva, '=SUM(C' . $filaPlantilla . ':C' . ($filaTotalNueva - 1) . ')');
        $sheetHoja21->setCellValue('G' . $filaTotalNueva, '=SUM(G' . $filaPlantilla . ':G' . ($filaTotalNueva - 1) . ')');
        $sheetHoja21->setCellValue('L' . $filaTotalNueva, '=SUM(L' . $filaPlantilla . ':L' . ($filaTotalNueva - 1) . ')');
    } catch (\Throwable $e) {
        throw new RuntimeException('Error al rellenar hoja 21 AREAS FUNCIONALES: ' . $e->getMessage());
    }

    $nombreArchivoEmpresa = normalizarNombreArchivoEmpresa($razonSocial) . '.xlsx';
    $rutaSalida = $destinoDir . '/' . $nombreArchivoEmpresa;

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($rutaSalida);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $rutaSalida;
}

function normalizarNombreArchivoEmpresa(string $nombre): string
{
    $normalizado = trim($nombre);
    $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalizado);
    if ($transliterado !== false) {
        $normalizado = $transliterado;
    }

    $normalizado = preg_replace('/[^A-Za-z0-9 _.-]/', '', $normalizado) ?? '';
    $normalizado = preg_replace('/\s+/', '_', $normalizado) ?? '';
    $normalizado = trim($normalizado, '._-');

    return $normalizado !== '' ? $normalizado : 'empresa';
}

/**
 * Obtiene los estudios distintos y cuenta mujeres y hombres para cada uno, de forma dinámica.
 * @return array<int, array{estudio:string, mujeres:int, hombres:int}>
 */
function obtenerConteosEstudiosDinamico(mysqli $db, int $idEmpresa, int $idAnoDatos): array
{
    $stmt = $db->prepare(
        "
        SELECT
            TRIM(de.estudios) AS estudio,
            de.sexo,
            de.salario_base_eq,
            de.salario_base_ef
        FROM datos_empleados de
        INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
        INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
        WHERE ce.id_empresa = ? AND de.id_ano_datos = ? AND de.estudios IS NOT NULL AND TRIM(de.estudios) <> ''
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
          AND de.salario_base_eq != 0 AND de.salario_base_ef != 0
        "
    );
    if (!$stmt) throw new RuntimeException('No se pudo preparar la consulta de estudios dinámicos.');
    $stmt->bind_param('ii', $idEmpresa, $idAnoDatos);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $acumulado = [];
    while ($row = $result->fetch_assoc()) {
        $estudio = trim((string)($row['estudio'] ?? ''));
        if ($estudio === '') continue;
        $sexo = strtolower(trim((string)($row['sexo'] ?? '')));
        $esMujer = in_array($sexo, ['m', 'mujer', 'f', 'femenino'], true);
        $esHombre = in_array($sexo, ['h', 'hombre', 'masculino'], true);
        if (!($esMujer || $esHombre)) continue;
        if (!isset($acumulado[$estudio])) {
            $acumulado[$estudio] = ['estudio' => $estudio, 'mujeres' => 0, 'hombres' => 0];
        }
        if ($esMujer) $acumulado[$estudio]['mujeres']++;
        else $acumulado[$estudio]['hombres']++;
    }
    // Ordenar por total descendente y luego alfabético
    $filas = array_values($acumulado);
    usort($filas, static function ($a, $b) {
        $ta = $a['mujeres'] + $a['hombres'];
        $tb = $b['mujeres'] + $b['hombres'];
        if ($ta !== $tb) return $tb <=> $ta;
        return strcasecmp($a['estudio'], $b['estudio']);
    });
    return $filas;
}
