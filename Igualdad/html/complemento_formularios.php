<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();
require __DIR__ . '/../config/config.php';

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? 'CLIENTE'));
$esAdmin = ($rol === 'ADMINISTRADOR');
$esStaff = in_array($rol, ['ADMINISTRADOR', 'TECNICO'], true);
$puedeEditarTablas = $esStaff;
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$sessionUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');
$panelCss = ($rol === 'TECNICO')
    ? '../css/tecnico.css'
    : (($rol === 'CLIENTE') ? '../css/empresa.css' : '../css/admin.css');
$empresasDisponibles = [];

require_once __DIR__ . '/../php/helpers.php';

function complemento_has_column(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '::' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $db = db();
    $tableEsc = $db->real_escape_string($table);
    $columnEsc = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $ok = ($res instanceof mysqli_result) && ($res->num_rows > 0);
    if ($res instanceof mysqli_result) {
        $res->free();
    }

    $cache[$key] = $ok;
    return $ok;
}

function complemento_primary_key(string $table): ?string
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $db = db();
    $tableEsc = $db->real_escape_string($table);
    $res = $db->query("SHOW KEYS FROM `{$tableEsc}` WHERE Key_name = 'PRIMARY'");
    if (!($res instanceof mysqli_result)) {
        $cache[$table] = null;
        return null;
    }

    $bestCol = null;
    $bestSeq = PHP_INT_MAX;
    while ($row = $res->fetch_assoc()) {
        $seq = (int)($row['Seq_in_index'] ?? 0);
        $col = (string)($row['Column_name'] ?? '');
        if ($col !== '' && $seq > 0 && $seq < $bestSeq) {
            $bestSeq = $seq;
            $bestCol = $col;
        }
    }
    $res->free();

    $cache[$table] = $bestCol;
    return $cache[$table];
}

function complemento_tipo_definitiva_db_a_ui(string $tipoDb): string
{
    $map = [
        'Despido' => 'Despido',
        'Fallecimiento' => 'Fallecimiento',
        'Finalización contrato' => 'Finalización contrato',
        'Finalizacion contrato' => 'Finalización contrato',
        'Jubilación' => 'Jubilación',
        'Jubilacion' => 'Jubilación',
        'No superación de periodo de prueba' => 'No superación de periodo de prueba',
        'No supera periodo de prueba' => 'No superación de periodo de prueba',
        'Baja voluntaria' => 'Baja voluntaria',
    ];

    return $map[$tipoDb] ?? $tipoDb;
}

function complemento_fetch_simple_rows(string $table, int $idEmpresa, array $fields): array
{
    $pk = complemento_primary_key($table);
    if ($pk === null || !complemento_has_column($table, 'id_empresa')) {
        return ['rows' => [], 'error' => 'No se encontro clave primaria o columna id_empresa.'];
    }

    $db = db();
    $tableEsc = $db->real_escape_string($table);
    $pkEsc = str_replace('`', '``', $pk);

    $fieldSql = [];
    foreach ($fields as $f) {
        $fEsc = str_replace('`', '``', $f);
        $fieldSql[] = "`{$fEsc}`";
    }

    $sql = "SELECT `{$pkEsc}` AS id_registro, " . implode(', ', $fieldSql) . " FROM `{$tableEsc}` WHERE id_empresa = ? ORDER BY `{$pkEsc}` DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['rows' => $rows, 'error' => ''];
}

function complemento_fetch_bajas_rows(int $idEmpresa): array
{
    $pk = complemento_primary_key('bajas');
    if ($pk === null) {
        return ['rows' => [], 'error' => 'No se encontro clave primaria en bajas.'];
    }

    if (!complemento_has_column('bajas', 'id_empresa')) {
        return ['rows' => [], 'error' => 'La tabla bajas no tiene columna id_empresa todavía.'];
    }

    $db = db();
    $pkEsc = str_replace('`', '``', $pk);
    $sql = "
        SELECT
            b.`{$pkEsc}` AS id_bajas,
            b.tipo AS tipo_baja,
            COALESCE(bt.motivo, bd.motivo, '') AS motivo,
            bt.tipo AS tipo_temporal,
            bd.tipo AS tipo_definitiva,
            COALESCE(bt.num_mujeres, bd.num_mujeres, 0) AS num_mujeres,
            COALESCE(bt.num_hombres, bd.num_hombres, 0) AS num_hombres
        FROM bajas b
        LEFT JOIN baja_temporales bt ON bt.id_bajas = b.id_bajas
        LEFT JOIN baja_definitivas bd ON bd.id_bajas = b.id_bajas
        WHERE b.id_empresa = ?
    ";

    $sql .= ' ORDER BY b.`' . $pkEsc . '` DESC';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['rows' => $rows, 'error' => ''];
}

function complemento_empresa_tiene_registro_retributivo(int $idEmpresa): bool
{
    if ($idEmpresa <= 0) {
        return false;
    }

    $sql = ' SELECT 1 FROM archivos a INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
        INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas WHERE UPPER(TRIM(a.tipo)) IN ("REGISTRO_RETRIBUTIVO", "REGISTRO_PROPIO_CLIENTE") AND ac.id_empresa = ?
        LIMIT 1';

    $stmt = db()->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($ok) {
        return true;
    }

    $sqlDirecto = 'SELECT 1 FROM archivos a WHERE UPPER(TRIM(a.tipo)) IN ("REGISTRO_RETRIBUTIVO", "REGISTRO_PROPIO_CLIENTE") AND a.id_empresa = ?
        LIMIT 1';

    $stmtDirecto = db()->prepare($sqlDirecto);
    if (!$stmtDirecto) {
        return false;
    }

    $stmtDirecto->bind_param('i', $idEmpresa);
    $stmtDirecto->execute();
    $ok = (bool)$stmtDirecto->get_result()->fetch_assoc();
    $stmtDirecto->close();

    return $ok;
}

function complemento_contrato_id_para_medidas(int $idEmpresa): int
{
    if ($idEmpresa <= 0) {
        return 0;
    }

    $sql = 'SELECT id_contrato_empresa FROM contrato_empresa WHERE id_empresa = ? ORDER BY
            CASE
                WHEN UPPER(TRIM(tipo_contrato)) LIKE "PLAN IGUALDAD%" THEN 0
                WHEN UPPER(TRIM(tipo_contrato)) LIKE "MANTENIMIENTO%" THEN 1
                ELSE 2
            END,
            id_contrato_empresa DESC
        LIMIT 1';

    $stmt = db()->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['id_contrato_empresa'] ?? 0);
}

$msg = trim((string)($_GET['msg'] ?? ''));
$tab = trim((string)($_GET['tab'] ?? 'bajas'));
$embed = ((string)($_GET['embed'] ?? '') === '1');
$soloMedidas = ((string)($_GET['solo_medidas'] ?? '') === '1');
$idEmpresaSeleccionada = (int)($_GET['id_empresa'] ?? 0);

$cuestionarioTabs = [
    'cuestionario_seleccion_personal' => [
        'label' => 'Cuestionario seleccion personal',
        'table' => 'cuestionario_seleccion_personal',
        'title' => 'Cuestionario seleccion personal',
        'fields' => [
            ['name' => 'factores_determinantes', 'label' => 'Factores determinantes del inicio de la seleccion'],
            ['name' => 'sistema_reclutamiento', 'label' => 'Sistemas de reclutamiento'],
            ['name' => 'incorporacion_nuevo_personal', 'label' => 'Incorporacion de nuevo personal'],
            ['name' => 'definicion_perfiles', 'label' => 'Definicion de perfiles'],
            ['name' => 'publicacion_interna', 'label' => 'Publicacion interna de vacantes'],
            ['name' => 'metodos_seleccion', 'label' => 'Metodos de seleccion'],
            ['name' => 'personas_responsables', 'label' => 'Personas responsables de la seleccion'],
            ['name' => 'ultima_decision', 'label' => 'Ultima decision'],
            ['name' => 'caracteristicas_candidaturas', 'label' => 'Caracteristicas generales de las candidaturas'],
            ['name' => 'barreras_internas_externas', 'label' => 'Barreras internas o externas para la incorporacion de mujeres'],
            ['name' => 'entrevista_salida', 'label' => 'Entrevista de salida'],
        ],
    ],
    'cuestionario_promocion_profesional' => [
        'label' => 'Cuestionario promocion profesional',
        'table' => 'cuestionario_promocion_profesional',
        'title' => 'Cuestionario promocion profesional',
        'fields' => [
            ['name' => 'metodologia', 'label' => 'Metodologia'],
            ['name' => 'metodologia_evaluacion', 'label' => 'Metodologia estandar de evaluacion del personal'],
            ['name' => 'personas_intervienen', 'label' => 'Personas que intervienen en la decision de promocion'],
            ['name' => 'formacion_ligada', 'label' => 'Formacion ligada a la promocion'],
            ['name' => 'acciones_fomentar', 'label' => 'Acciones para fomentar la promocion de las mujeres'],
            ['name' => 'requisitos', 'label' => 'Requisitos'],
            ['name' => 'planes_carrera', 'label' => 'Planes de carrera'],
            ['name' => 'comunicacion_vacantes', 'label' => 'Comunicacion interna de vacantes'],
            ['name' => 'dificultades_promocion', 'label' => 'Dificultades en la promocion de mujeres'],
        ],
    ],
    'cuestionario_formacion' => [
        'label' => 'Cuestionario formacion',
        'table' => 'cuestionario_formacion',
        'title' => 'Cuestionario formacion',
        'fields' => [
            ['name' => 'deteccion_formativas', 'label' => 'Deteccion de las necesidades formativas'],
            ['name' => 'difusion_ofertas', 'label' => 'Difusion ofertas'],
            ['name' => 'puede_solicitar', 'label' => '¿Puede solicitar el personal un curso del Plan de Formacion?'],
            ['name' => 'compensacion_fuera', 'label' => 'Compensacion por formacion fuera de horario laboral'],
            ['name' => 'posibilidad_formacion', 'label' => 'Posibilidad de realizar una formacion no relacionada con el puesto'],
            ['name' => 'formacion_mujeres', 'label' => 'Formacion especifica para mujeres'],
            ['name' => 'existencia_plan', 'label' => 'Existencia o no del plan de formacion'],
            ['name' => 'asisten_igualmente', 'label' => '¿Asisten igualmente a formaciones hombres y mujeres?'],
            ['name' => 'criterios_seleccion', 'label' => 'Criterios de seleccion'],
            ['name' => 'impartacion_fuera', 'label' => 'Imparticion de formacion fuera de horario laboral'],
            ['name' => 'ayudas_formacion', 'label' => 'Ayudas para la formacion externa'],
            ['name' => 'formacion_igualdad', 'label' => 'Formacion en igualdad'],
            ['name' => 'coste_medio', 'label' => 'Coste medio de la formacion'],
            ['name' => 'formacion_reciclaje', 'label' => 'Formacion de reciclaje'],
        ],
    ],
    'cuestionario_conciliacion_corresponsabilidad' => [
        'label' => 'Cuestionario conciliacion corresponsabilidad',
        'table' => 'cuestionario_conciliacion_corresponsabilidad',
        'title' => 'Cuestionario conciliacion corresponsabilidad',
        'fields' => [
            ['name' => 'ordenacion_tiempo', 'label' => 'Ordenacion del tiempo de trabajo y conciliacion de la vida laboral y familiar'],
            ['name' => 'quienes_utilizan', 'label' => '¿Quienes utilizan mas estas medidas? Razones.'],
            ['name' => 'reduccion_jornada', 'label' => 'La reduccion de jornada, ¿afecta a la situacion profesional?'],
            ['name' => 'mecanismos_disponibles', 'label' => 'Mecanismos disponibles'],
            ['name' => 'cuantas_personas', 'label' => '¿Cuantas personas fueron madres/padres?'],
            ['name' => 'canales_informacion', 'label' => 'Canales de informacion y difusion'],
        ],
    ],
    'cuestionario_infrarrepresentacion_femenina' => [
        'label' => 'Cuestionario infrarrepresentacion femenina',
        'table' => 'cuestionario_infrarrepresentacion_femenina',
        'title' => 'Cuestionario infrarrepresentacion femenina',
        'fields' => [
            ['name' => 'barreras_internas', 'label' => 'Barreras internas, externas y/o sectoriales para la incorporacion de mujeres'],
            ['name' => 'hay_mujeres', 'label' => '¿Hay mujeres con cargos de responsabilidad?'],
        ],
    ],
    'cuestionario_salud_laboral' => [
        'label' => 'Cuestionario salud laboral',
        'table' => 'cuestionario_salud_laboral',
        'title' => 'Cuestionario salud laboral',
        'fields' => [
            ['name' => 'seguridad_salud', 'label' => 'Seguridad, salud laboral y equipamientos'],
            ['name' => 'medidas_linea', 'label' => 'Medidas en esta linea'],
            ['name' => 'incluido_perspectiva', 'label' => '¿Se ha incluido la perspectiva de genero en el plan de prevencion de riesgos laborales?'],
            ['name' => 'permite_desconexion', 'label' => '¿Se permite la desconexion digital?'],
        ],
    ],
    'cuestionario_prevencion_acoso_sexual' => [
        'label' => 'Cuestionario prevencion acoso sexual',
        'table' => 'cuestionario_prevencion_acoso_sexual',
        'title' => 'Cuestionario prevencion acoso sexual',
        'fields' => [
            ['name' => 'conocen_acoso', 'label' => 'Se conocen casos de acosos sexual y/o por razon de sexo en el ultimo año'],
            ['name' => 'protocolo_prevencion', 'label' => 'Protocolo de Prevencion y Actuacion contra el acoso sexual y por razon de sexo'],
            ['name' => 'medidas_sensibilizacion', 'label' => 'Medidas de sensibilizacion, prevencion, deteccion contra el acoso sexual y por razon de genero.'],
        ],
    ],
    'cuestionario_violencia_genero' => [
        'label' => 'Cuestionario violencia genero',
        'table' => 'cuestionario_violencia_genero',
        'title' => 'Cuestionario violencia genero',
        'fields' => [
            ['name' => 'conocimiento_contratada', 'label' => '¿Conocimiento de tener contratada alguna mujer del colectivo?'],
            ['name' => 'prevision_progama', 'label' => '¿Prevision de algun programa para mujeres en esta circunstancia?'],
        ],
    ],
    'cuestionario_comunicacion_identidad_corporativa' => [
        'label' => 'Cuestionario comunicacion identidad corporativa',
        'table' => 'cuestionario_comunicacion_identidad_corporativa',
        'title' => 'Cuestionario comunicacion identidad corporativa',
        'fields' => [
            ['name' => 'canales_comunicacion', 'label' => 'Canales de comunicacion interna'],
            ['name' => 'campanas_comunicacion', 'label' => 'Campañas de comunicacion y sensibilizacion'],
            ['name' => 'imagen_empresa', 'label' => '¿La imagen de la empresa transmite valores de igualdad? Razones'],
            ['name' => 'existencia_comunicacion', 'label' => 'Existencia de canales de comunicacion con la empresa'],
            ['name' => 'frecuencia', 'label' => 'Frecuencia de uso de los canales'],
            ['name' => 'lenguaje_imagen', 'label' => 'Lenguaje e imagen web'],
            ['name' => 'objetivos', 'label' => 'Objetivos empresariales'],
            ['name' => 'filosofia', 'label' => 'Filosofia de la empresa'],
            ['name' => 'procesos_calidad', 'label' => 'Procesos de calidad'],
            ['name' => 'responsabilidad_social', 'label' => 'Responsabilidad Social Corporativa'],
        ],
    ],
];

$tabsPermitidas = array_merge(['bajas', 'formacion', 'excedencias', 'permisos'], array_keys($cuestionarioTabs));
if (!in_array($tab, $tabsPermitidas, true)) {
    $tab = 'bajas';
}

if ($soloMedidas) {
    $tab = 'bajas';
}

$tabHrefExtra = $embed ? '&embed=1' : '';
$tabHrefExtra .= $soloMedidas ? '&solo_medidas=1' : '';
$urlVolverRegistro = $esStaff ? 'subir_registro.html.php' : 'index_cliente.php';

if ($esAdmin) {
    $stmtEmpresas = db()->prepare(
        'SELECT id_empresa, razon_social
         FROM empresa
         ORDER BY razon_social ASC'
    );

    if ($stmtEmpresas) {
        $stmtEmpresas->execute();
        $resEmpresas = $stmtEmpresas->get_result();
        while ($rowEmpresa = $resEmpresas->fetch_assoc()) {
            $empresasDisponibles[] = [
                'id_empresa' => (int)($rowEmpresa['id_empresa'] ?? 0),
                'razon_social' => trim((string)($rowEmpresa['razon_social'] ?? '')),
            ];
        }
        $stmtEmpresas->close();
    }
} elseif ($usuarioId > 0) {
    $stmtEmpresas = db()->prepare(
        'SELECT t.id_empresa, t.razon_social
         FROM (
             SELECT e.id_empresa, e.razon_social
             FROM usuario_empresa ue
             INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
             WHERE ue.id_usuario = ?

             UNION

             SELECT e.id_empresa, e.razon_social
             FROM empresa e
             WHERE e.id_usuario = ?
            ) t
            ORDER BY t.razon_social ASC'
    );

    if ($stmtEmpresas) {
        $stmtEmpresas->bind_param('ii', $usuarioId, $usuarioId);
        $stmtEmpresas->execute();
        $resEmpresas = $stmtEmpresas->get_result();
        while ($rowEmpresa = $resEmpresas->fetch_assoc()) {
            $empresasDisponibles[] = [
                'id_empresa' => (int)($rowEmpresa['id_empresa'] ?? 0),
                'razon_social' => trim((string)($rowEmpresa['razon_social'] ?? '')),
            ];
        }
        $stmtEmpresas->close();
    }
}

$empresaFijada = false;
$empresaFijadaNombre = '';
if ($idEmpresaSeleccionada > 0) {
    foreach ($empresasDisponibles as $empresa) {
        if ((int)($empresa['id_empresa'] ?? 0) === $idEmpresaSeleccionada) {
            $empresaFijada = true;
            $empresaFijadaNombre = trim((string)($empresa['razon_social'] ?? ''));
            break;
        }
    }

    if (!$empresaFijada) {
        $idEmpresaSeleccionada = 0;
    }
}

if (!$empresaFijada && !empty($empresasDisponibles)) {
    $empresaConRegistro = $empresasDisponibles[0];
    $idEmpresaSeleccionada = (int)($empresaConRegistro['id_empresa'] ?? 0);
    $empresaFijadaNombre = trim((string)($empresaConRegistro['razon_social'] ?? ''));
    $empresaFijada = ($idEmpresaSeleccionada > 0);
}

if ($empresaFijada) {
    $tabHrefExtra .= '&id_empresa=' . urlencode((string)$idEmpresaSeleccionada);
}

$empresaTieneRegistro = ($idEmpresaSeleccionada > 0) ? complemento_empresa_tiene_registro_retributivo($idEmpresaSeleccionada) : false;
$idContratoMedidas = ($soloMedidas && $idEmpresaSeleccionada > 0) ? complemento_contrato_id_para_medidas($idEmpresaSeleccionada) : 0;
$fromMedidas = ($rol === 'TECNICO') ? 'tecnico' : (($rol === 'ADMINISTRADOR') ? 'admin' : 'empresa');
$urlEdicionMedidas = ($idContratoMedidas > 0)
    ? app_path('/model/empresa.php?view=edit_contratos&id_contrato=' . $idContratoMedidas . '&from=' . urlencode($fromMedidas) . '&solo_medidas_embed=1')
    : '';


$complementosBloqueados = (!$empresaFijada || (!$soloMedidas && !$empresaTieneRegistro));

$bajasRows = [];
$formacionRows = [];
$excedenciasRows = [];
$permisosRows = [];
$cuestionarioRows = [];
$erroresListado = [];

if ($idEmpresaSeleccionada > 0) {
    try {
        $resBajas = complemento_fetch_bajas_rows($idEmpresaSeleccionada);
        $bajasRows = $resBajas['rows'] ?? [];
        if (($resBajas['error'] ?? '') !== '') {
            $erroresListado[] = (string)$resBajas['error'];
        }

        $resFormacion = complemento_fetch_simple_rows('area_formaciones', $idEmpresaSeleccionada, ['tipo', 'n_mujeres', 'n_hombres', 'n_horas', 'modalidad', 'perfil_puesto', 'horario', 'caracter']);
        $formacionRows = $resFormacion['rows'] ?? [];
        if (($resFormacion['error'] ?? '') !== '') {
            $erroresListado[] = 'Formacion: ' . $resFormacion['error'];
        }

        $resExcedencias = complemento_fetch_simple_rows('area_excedencias', $idEmpresaSeleccionada, ['motivo', 'tipo', 'n_mujeres', 'n_hombres']);
        $excedenciasRows = $resExcedencias['rows'] ?? [];
        if (($resExcedencias['error'] ?? '') !== '') {
            $erroresListado[] = 'Excedencias: ' . $resExcedencias['error'];
        }

        $resPermisos = complemento_fetch_simple_rows('area_Permisos_retribuidos', $idEmpresaSeleccionada, ['motivo', 'tipo', 'n_mujeres', 'n_hombres']);
        $permisosRows = $resPermisos['rows'] ?? [];
        if (($resPermisos['error'] ?? '') !== '') {
            $erroresListado[] = 'Permisos: ' . $resPermisos['error'];
        }

        foreach ($cuestionarioTabs as $tabCuestionario => $configCuestionario) {
            $fieldsCuestionario = array_column(($configCuestionario['fields'] ?? []), 'name');
            $resCuestionario = complemento_fetch_simple_rows(
                (string)($configCuestionario['table'] ?? ''),
                $idEmpresaSeleccionada,
                $fieldsCuestionario
            );
            $cuestionarioRows[$tabCuestionario] = $resCuestionario['rows'] ?? [];
            if (($resCuestionario['error'] ?? '') !== '') {
                $erroresListado[] = (string)($configCuestionario['title'] ?? $tabCuestionario) . ': ' . $resCuestionario['error'];
            }
        }
    } catch (Throwable $e) {
        error_log(sprintf('[complemento_formularios.carga_listados] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
        $erroresListado[] = 'No se pudieron cargar los listados. Intentalo de nuevo.';
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Complemento Formularios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="<?= $panelCss ?>">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row g-3">
            <?php if (!$embed): ?>
                <aside class="col-12 col-lg-3 col-xl-2">
                    <div class="card shadow-sm border-0 sidebar">
                        <div class="card-body">
                            <div class="sidebar-header">
                                <div class="sidebar-avatar"><?= ($rol === 'TECNICO') ? '👨‍💼' : (($rol === 'ADMINISTRADOR') ? '🧑‍💼' : '👤') ?></div>
                                <h5 class="sidebar-title">Complementos</h5>
                            </div>

                            <div class="sidebar-user-info">
                                <div class="info-label">Usuario Actual</div>
                                <div class="info-value"><?= h($sessionUsername) ?></div>
                                <?php if ($sessionEmail !== ''): ?>
                                    <div class="info-email">📧 <?= h($sessionEmail) ?></div>
                                <?php endif; ?>
                            </div>

                            <nav class="sidebar-nav">
                                <?php if ($rol === 'TECNICO'): ?>
                                    <a class="nav-button" href="<?= h(app_path('/model/tecnico.php?view=menu')) ?>">
                                        <span class="nav-icon">📊</span>
                                        <span>Mi Panel</span>
                                    </a>
                                    <a class="nav-button" href="<?= h(app_path('/model/empresa.php?view=ver_empresas&from=tecnico')) ?>">
                                        <span class="nav-icon">🏢</span>
                                        <span>Mis Empresas</span>
                                    </a>
                                <?php elseif ($rol === 'ADMINISTRADOR'): ?>
                                    <a class="nav-button" href="<?= h(app_path('/model/admin.php?view=menu')) ?>">
                                        <span class="nav-icon">📊</span>
                                        <span>Mi Panel</span>
                                    </a>
                                    <a class="nav-button" href="<?= h(app_path('/model/empresa.php?view=ver_empresas&from=admin')) ?>">
                                        <span class="nav-icon">🏢</span>
                                        <span>Directorio Empresas</span>
                                    </a>
                                <?php else: ?>
                                    <a class="nav-button" href="<?= h(app_path('/html/index_cliente.php?view=mi_espacio')) ?>">
                                        <span class="nav-icon">🏠</span>
                                        <span>Mi Espacio</span>
                                    </a>
                                    <a class="nav-button" href="<?= h(app_path('/model/empresa.php?view=ver_empresas')) ?>">
                                        <span class="nav-icon">🏢</span>
                                        <span>Empresas</span>
                                    </a>
                                <?php endif; ?>

                                <a class="nav-button" href="subir_documento_tipo.html.php">
                                    <span class="nav-icon">📁</span>
                                    <span>Subir Documentos</span>
                                </a>
                                <a class="nav-button nav-logout" href="<?= h(app_path('/php/logout.php')) ?>">
                                    <span class="nav-icon">🚪</span>
                                    <span>Cerrar Sesión</span>
                                </a>
                            </nav>
                        </div>
                    </div>
                </aside>
            <?php endif; ?>

            <main class="<?= $embed ? 'col-12' : 'col-12 col-lg-9 col-xl-10' ?>">
                <div class="card p-4 shadow-sm border-0">

                    <?php if ($msg !== ''): ?>
                        <div class="alert alert-info py-2"><?= h($msg) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($erroresListado)): ?>
                        <div class="alert alert-warning py-2">
                            <?php foreach (($erroresListado ?? []) as $err): ?>
                                <div><?= h($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <label class="form-label mb-0 fw-bold">Empresa:</label>
                            <?php if ($embed): ?>
                                <input type="text" class="form-control w-auto" value="<?= h($empresaFijadaNombre) ?>" readonly>
                            <?php else: ?>
                                <select class="form-select w-auto" onchange="window.location.href='complemento_formularios.php?tab=<?= h($tab) ?><?= $embed ? '&embed=1' : '' ?>&id_empresa=' + this.value;">
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach (($empresasDisponibles ?? []) as $empresa): ?>
                                        <option value="<?= (int)$empresa['id_empresa'] ?>" <?= ((int)$empresa['id_empresa'] === $idEmpresaSeleccionada) ? 'selected' : '' ?>><?= h($empresa['razon_social']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Apartado de subir documentación rápida -->
                        <?php if ($idEmpresaSeleccionada > 0 && !$complementosBloqueados): ?>
                            <div class="card bg-light border-0 shadow-sm" style="max-width: 500px;">
                                <div class="card-body p-2">
                                    <form action="../controller/complemento_formulario_controler.php" method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="accion" value="subir_documentacion_rapida">
                                        <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                        <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                        <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                         <label class="form-label mb-0 fw-bold">Subir Documentación si la tienes en archivo:</label>
                                        <div class="col-auto">
                                        <label class="form-label mb-0 fw-bold">¿Que hay en tu archivo?:</label>    <input type="text" name="asunto" class="form-control form-control-sm" placeholder="Asunto del documento..." required>
                                        </div>
                                        <div class="col-auto">
                                            <input type="file" name="archivo" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-primary btn-sm">Subir</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($complementosBloqueados): ?>
                        <div class="alert alert-warning mb-4">
                            <?= $soloMedidas
                                ? 'Selecciona una empresa para continuar con las medidas.'
                                : 'Debes subir primero el Registro Retributivo o un Registro Propio en esta empresa para desbloquear los complementos de formularios.' ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$soloMedidas): ?>
                        <div class="mb-2">
                            <h6 class="fw-bold text-uppercase text-muted" style="font-size: 0.85rem;">Datos Cuantitativos</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn <?= $tab === 'bajas' ? 'btn-primary' : 'btn-outline-primary' ?><?= $complementosBloqueados ? ' disabled opacity-50' : '' ?>" href="<?= $complementosBloqueados ? '#' : 'complemento_formularios.php?tab=bajas' . $tabHrefExtra ?>" tabindex="<?= $complementosBloqueados ? '-1' : '0' ?>">Bajas</a>
                                <a class="btn <?= $tab === 'formacion' ? 'btn-primary' : 'btn-outline-primary' ?><?= $complementosBloqueados ? ' disabled opacity-50' : '' ?>" href="<?= $complementosBloqueados ? '#' : 'complemento_formularios.php?tab=formacion' . $tabHrefExtra ?>" tabindex="<?= $complementosBloqueados ? '-1' : '0' ?>">Formacion</a>
                                <a class="btn <?= $tab === 'excedencias' ? 'btn-primary' : 'btn-outline-primary' ?><?= $complementosBloqueados ? ' disabled opacity-50' : '' ?>" href="<?= $complementosBloqueados ? '#' : 'complemento_formularios.php?tab=excedencias' . $tabHrefExtra ?>" tabindex="<?= $complementosBloqueados ? '-1' : '0' ?>">Excedencias</a>
                                <a class="btn <?= $tab === 'permisos' ? 'btn-primary' : 'btn-outline-primary' ?><?= $complementosBloqueados ? ' disabled opacity-50' : '' ?>" href="<?= $complementosBloqueados ? '#' : 'complemento_formularios.php?tab=permisos' . $tabHrefExtra ?>" tabindex="<?= $complementosBloqueados ? '-1' : '0' ?>">Permisos retributivos</a>
                            </div>
                        </div>

                        <div class="mb-4 mt-3">
                            <h6 class="fw-bold text-uppercase text-muted" style="font-size: 0.85rem;">Cuestionarios Cualitativos</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach (($cuestionarioTabs ?? []) as $tabCuestionario => $configCuestionario): ?>
                                    <a class="btn <?= $tab === $tabCuestionario ? 'btn-primary' : 'btn-outline-primary' ?><?= $complementosBloqueados ? ' disabled opacity-50' : '' ?>" href="<?= $complementosBloqueados ? '#' : 'complemento_formularios.php?tab=' . urlencode($tabCuestionario) . $tabHrefExtra ?>" tabindex="<?= $complementosBloqueados ? '-1' : '0' ?>"><?= h((string)($configCuestionario['label'] ?? $tabCuestionario)) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($complementosBloqueados): ?>
                        <div class="alert alert-secondary mb-0">
                            <?= $soloMedidas
                                ? 'Selecciona una empresa para ver y usar sus medidas.'
                                : 'Selecciona una empresa que ya haya subido su Registro Retributivo o un Registro Propio para ver y usar sus complementos.' ?>
                        </div>
                    <?php else: ?>

                        <?php if ($soloMedidas): ?>
                            <?php if ($idContratoMedidas <= 0): ?>
                                <div class="alert alert-warning mb-0">
                                    No se ha encontrado un contrato para esta empresa. Crea o asigna el contrato y vuelve a intentarlo.
                                </div>
                            <?php else: ?>
                                <iframe
                                    id="medidasEditorFrame"
                                    title="Editor de medidas"
                                    src="<?= h($urlEdicionMedidas) ?>"
                                    style="width: 100%; min-height: 74vh; border: 1px solid #dee2e6; border-radius: 0.5rem; background: #fff;"
                                    loading="lazy"></iframe>
                            <?php endif; ?>
                        <?php else: ?>

                        <?php if ($tab === 'bajas'): ?>
                            <form action="../controller/complemento_formulario_controler.php" method="POST" class="vstack gap-3">
                                <input type="hidden" name="accion" value="bajas">
                                <?= csrf_input() ?>
                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">

                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">

                                <div>
                                    <label class="form-label">Tipo de baja</label>
                                    <select id="tipo_baja" name="tipo_baja" class="form-control" required>
                                        <option value="">-- Selecciona tipo de baja --</option>
                                        <option value="TEMPORALES">Temporales</option>
                                        <option value="DEFINITIVAS">Definitivas</option>
                                    </select>
                                </div>



                                <div id="bloque_temporales">
                                    <label class="form-label" for="tipo_temporal">Tipo temporal</label>
                                    <select id="tipo_temporal" name="tipo_temporal" class="form-control">
                                        <option value="">-- Selecciona tipo temporal --</option>
                                        <option value="Enfermedad Común">Enfermedad Común</option>
                                        <option value="Accidente Laboral">Accidente Laboral</option>
                                        <option value="Riesgo embarazo">Riesgo embarazo</option>
                                        <option value="COVID">COVID</option>
                                    </select>
                                </div>

                                <div id="bloque_definitivas" class="d-none">
                                    <label class="form-label" for="tipo_definitiva">Tipo definitiva</label>
                                    <select id="tipo_definitiva" name="tipo_definitiva" class="form-control">
                                        <option value="">-- Selecciona tipo definitiva --</option>
                                        <option value="Despido">Despido</option>
                                        <option value="Fallecimiento">Fallecimiento</option>
                                        <option value="Jubilación">Jubilación</option>
                                        <option value="Finalización contrato">Finalización contrato</option>
                                        <option value="No superación de periodo de prueba">No superación de periodo de prueba</option>
                                        <option value="Baja voluntaria">Baja voluntaria</option>
                                    </select>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="bajas_num_mujeres">Numero de mujeres</label>
                                        <input id="bajas_num_mujeres" type="number" min="0" name="num_mujeres" class="form-control" value="0" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="bajas_num_hombres">Numero de hombres</label>
                                        <input id="bajas_num_hombres" type="number" min="0" name="num_hombres" class="form-control" value="0" required>
                                    </div>
                                </div>

                                <div>
                                    <button type="submit" class="btn btn-primary">Guardar bajas</button>
                                </div>
                            </form>

                            <hr class="my-4">
                            <h6 class="mb-3">Bajas registradas</h6>

                            <?php if ($idEmpresaSeleccionada <= 0): ?>
                                <div class="alert alert-secondary py-2">Selecciona una empresa para ver el listado.</div>
                            <?php elseif (empty($bajasRows)): ?>
                                <div class="alert alert-secondary py-2">No hay bajas registradas para esta empresa.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tipo</th>
                                                <th>Detalle</th>
                                                <th>Mujeres</th>
                                                <th>Hombres</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($bajasRows ?? []) as $row): ?>
                                                <?php
                                                $tipoBaja = (string)($row['tipo_baja'] ?? '');
                                                $tipoTemporal = (string)($row['tipo_temporal'] ?? '');
                                                $tipoDefinitivaDb = (string)($row['tipo_definitiva'] ?? '');
                                                $tipoDefinitiva = complemento_tipo_definitiva_db_a_ui($tipoDefinitivaDb);
                                                $esTemporal = strtoupper($tipoBaja) === 'TEMPORALES';
                                                ?>
                                                <tr>
                                                    <td><?= (int)($row['id_bajas'] ?? 0) ?></td>
                                                    <td><?= h($tipoBaja) ?></td>
                                                    <td><?= h($esTemporal ? $tipoTemporal : $tipoDefinitiva) ?></td>
                                                    <td><?= (int)($row['num_mujeres'] ?? 0) ?></td>
                                                    <td><?= (int)($row['num_hombres'] ?? 0) ?></td>
                                                    <td>
                                                        <?php if ($puedeEditarTablas): ?>
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-outline-primary btn-sm btn-edit-baja"
                                                                data-id="<?= (int)($row['id_bajas'] ?? 0) ?>"
                                                                data-tipo-baja="<?= h($tipoBaja) ?>"
                                                                data-tipo-temporal="<?= h($tipoTemporal) ?>"
                                                                data-tipo-definitiva="<?= h($tipoDefinitivaDb) ?>"
                                                                data-num-mujeres="<?= (int)($row['num_mujeres'] ?? 0) ?>"
                                                                data-num-hombres="<?= (int)($row['num_hombres'] ?? 0) ?>">
                                                                Editar
                                                            </button>
                                                        <?php endif; ?>

                                                        <form class="mt-2" action="../controller/complemento_formulario_controler.php" method="POST" onsubmit="return confirm('¿Eliminar esta baja?');">
                                                            <input type="hidden" name="accion" value="eliminar_baja">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                            <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                            <input type="hidden" name="id_bajas" value="<?= (int)($row['id_bajas'] ?? 0) ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($tab === 'formacion'): ?>
                            <form action="../controller/complemento_formulario_controler.php" method="POST" class="vstack gap-3">
                                <input type="hidden" name="accion" value="formacion">
                                <?= csrf_input() ?>
                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">

                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">

                                <div>
                                    <label class="form-label" for="formacion_tipo">Tipo</label>
                                    <input id="formacion_tipo" type="text" name="tipo" class="form-control" maxlength="100" required>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="formacion_n_horas">Horas totales</label>
                                        <input id="formacion_n_horas" type="number" min="0" name="n_horas" class="form-control" value="0">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="formacion_n_mujeres">Mujeres</label>
                                        <input id="formacion_n_mujeres" type="number" min="0" name="n_mujeres" class="form-control" value="0" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="formacion_n_hombres">Hombres</label>
                                        <input id="formacion_n_hombres" type="number" min="0" name="n_hombres" class="form-control" value="0" required>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="formacion_modalidad">Modalidad</label>
                                        <select id="formacion_modalidad" name="modalidad" class="form-select">
                                            <option value="">Selecciona...</option>
                                            <option value="Presencial">Presencial</option>
                                            <option value="Online">Online</option>
                                            <option value="Mixta">Mixta</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="formacion_horario">Horario</label>
                                        <select id="formacion_horario" name="horario" class="form-select">
                                            <option value="">Selecciona...</option>
                                            <option value="Dentro del horario">Dentro del horario</option>
                                            <option value="Fuera del horario">Fuera del horario</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="formacion_perfil_puesto">Perfil (Puesto)</label>
                                        <input id="formacion_perfil_puesto" type="text" name="perfil_puesto" class="form-control" placeholder="Ej: Operarios">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="formacion_caracter">Carácter</label>
                                        <select id="formacion_caracter" name="caracter" class="form-select">
                                            <option value="">Selecciona...</option>
                                            <option value="Obligatoria">Obligatoria</option>
                                            <option value="Voluntaria">Voluntaria</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <button type="submit" class="btn btn-primary">Guardar formacion</button>
                                </div>
                            </form>

                            <hr class="my-4">
                            <h6 class="mb-3">Formacion registrada</h6>
                            <?php if ($idEmpresaSeleccionada <= 0): ?>
                                <div class="alert alert-secondary py-2">Selecciona una empresa para ver el listado.</div>
                            <?php elseif (empty($formacionRows)): ?>
                                <div class="alert alert-secondary py-2">No hay formacion registrada para esta empresa.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Formación</th>
                                                <th>Horas</th>
                                                <th>Mujeres</th>
                                                <th>Hombres</th>
                                                <th>Modalidad</th>
                                                <th>Perfil (Puesto)</th>
                                                <th>Horario</th>
                                                <th>Carácter</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($formacionRows ?? []) as $row): ?>
                                                <tr>
                                                    <td><?= (int)($row['id_registro'] ?? 0) ?></td>
                                                    <td><?= h((string)($row['tipo'] ?? '')) ?></td>
                                                    <td><?= h((string)($row['n_horas'] ?? 0)) ?></td>
                                                    <td><?= (int)($row['n_mujeres'] ?? 0) ?></td>
                                                    <td><?= (int)($row['n_hombres'] ?? 0) ?></td>
                                                    <td><?= h((string)($row['modalidad'] ?? '-')) ?></td>
                                                    <td><?= h((string)($row['perfil_puesto'] ?? '-')) ?></td>
                                                    <td><?= h((string)($row['horario'] ?? '-')) ?></td>
                                                    <td><?= h((string)($row['caracter'] ?? '-')) ?></td>
                                                    <td>
                                                        <?php if ($puedeEditarTablas): ?>
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-outline-primary btn-sm btn-edit-formacion"
                                                                data-id="<?= (int)($row['id_registro'] ?? 0) ?>"
                                                                data-tipo="<?= h((string)($row['tipo'] ?? '')) ?>"
                                                                data-n-mujeres="<?= (int)($row['n_mujeres'] ?? 0) ?>"
                                                                data-n-hombres="<?= (int)($row['n_hombres'] ?? 0) ?>"
                                                                data-n-horas="<?= (int)($row['n_horas'] ?? 0) ?>"
                                                                data-modalidad="<?= h((string)($row['modalidad'] ?? '')) ?>"
                                                                data-perfil-puesto="<?= h((string)($row['perfil_puesto'] ?? '')) ?>"
                                                                data-horario="<?= h((string)($row['horario'] ?? '')) ?>"
                                                                data-caracter="<?= h((string)($row['caracter'] ?? '')) ?>">
                                                                Editar
                                                            </button>
                                                        <?php endif; ?>
                                                        <form class="mt-2" action="../controller/complemento_formulario_controler.php" method="POST" onsubmit="return confirm('¿Eliminar este registro de formación?');">
                                                            <input type="hidden" name="accion" value="eliminar_formacion">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                            <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                            <input type="hidden" name="id_registro" value="<?= (int)($row['id_registro'] ?? 0) ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($tab === 'excedencias'): ?>
                            <form action="../controller/complemento_formulario_controler.php" method="POST" class="vstack gap-3">
                                <input type="hidden" name="accion" value="excedencias">
                                <?= csrf_input() ?>
                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">

                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">

                                <div id="excedencias_motivo_container" class="d-none">
                                    <label class="form-label" for="excedencias_motivo">Motivo</label>
                                    <input id="excedencias_motivo" type="text" name="motivo" class="form-control" maxlength="100">
                                </div>

                                <div>
                                    <label class="form-label" for="excedencias_tipo">Tipo</label>
                                    <select id="excedencias_tipo" name="tipo" class="form-control" required>
                                        <option value="">-- Selecciona tipo de excedencia --</option>
                                        <option value="Excedencias Voluntarias">Excedencias Voluntarias</option>
                                        <option value="Excedencias Cuidado Menores">Excedencias Cuidado Menores</option>
                                        <option value="Excedencias Cuidado de Personas Mayores">Excedencias Cuidado de Personas Mayores</option>
                                        <option value="Otros">Otros</option>
                                    </select>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="excedencias_n_mujeres">Numero de mujeres</label>
                                        <input id="excedencias_n_mujeres" type="number" min="0" name="n_mujeres" class="form-control" value="0" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="excedencias_n_hombres">Numero de hombres</label>
                                        <input id="excedencias_n_hombres" type="number" min="0" name="n_hombres" class="form-control" value="0" required>
                                    </div>
                                </div>

                                <div>
                                    <button type="submit" class="btn btn-primary">Guardar excedencias</button>
                                </div>
                            </form>

                            <hr class="my-4">
                            <h6 class="mb-3">Excedencias registradas</h6>
                            <?php if ($idEmpresaSeleccionada <= 0): ?>
                                <div class="alert alert-secondary py-2">Selecciona una empresa para ver el listado.</div>
                            <?php elseif (empty($excedenciasRows)): ?>
                                <div class="alert alert-secondary py-2">No hay excedencias registradas para esta empresa.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Motivo</th>
                                                <th>Tipo</th>
                                                <th>Mujeres</th>
                                                <th>Hombres</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($excedenciasRows ?? []) as $row): ?>
                                                <tr>
                                                    <td><?= (int)($row['id_registro'] ?? 0) ?></td>
                                                    <td><?= h((string)($row['motivo'] ?? '')) ?></td>
                                                    <td><?= h((string)($row['tipo'] ?? '')) ?></td>
                                                    <td><?= (int)($row['n_mujeres'] ?? 0) ?></td>
                                                    <td><?= (int)($row['n_hombres'] ?? 0) ?></td>
                                                    <td>
                                                        <?php if ($puedeEditarTablas): ?>
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-outline-primary btn-sm btn-edit-excedencia"
                                                                data-id="<?= (int)($row['id_registro'] ?? 0) ?>"
                                                                data-motivo="<?= h((string)($row['motivo'] ?? '')) ?>"
                                                                data-tipo="<?= h((string)($row['tipo'] ?? '')) ?>"
                                                                data-n-mujeres="<?= (int)($row['n_mujeres'] ?? 0) ?>"
                                                                data-n-hombres="<?= (int)($row['n_hombres'] ?? 0) ?>">
                                                                Editar
                                                            </button>
                                                        <?php endif; ?>
                                                        <form class="mt-2" action="../controller/complemento_formulario_controler.php" method="POST" onsubmit="return confirm('¿Eliminar este registro de excedencia?');">
                                                            <input type="hidden" name="accion" value="eliminar_excedencia">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                            <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                            <input type="hidden" name="id_registro" value="<?= (int)($row['id_registro'] ?? 0) ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($tab === 'permisos'): ?>
                            <form action="../controller/complemento_formulario_controler.php" method="POST" class="vstack gap-3">
                                <input type="hidden" name="accion" value="permisos_retribuidos">
                                <?= csrf_input() ?>
                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">

                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">

                                <div id="permisos_motivo_container" class="d-none">
                                    <label class="form-label" for="permisos_motivo">Motivo</label>
                                    <input id="permisos_motivo" type="text" name="motivo" class="form-control" maxlength="100">
                                </div>

                                <div>
                                    <label class="form-label" for="permisos_tipo">Tipo</label>
                                    <select id="permisos_tipo" name="tipo" class="form-control" required>
                                        <option value="">-- Selecciona tipo de permiso --</option>
                                        <option value="Lactancia">Lactancia</option>
                                        <option value="Nacimiento">Nacimiento</option>
                                        <option value="Matrimonio">Matrimonio</option>
                                        <option value="Hospitalizacion de familiares">Hospitalizacion de familiares</option>
                                        <option value="Otros">Otros</option>
                                    </select>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="permisos_n_mujeres">Numero de mujeres</label>
                                        <input id="permisos_n_mujeres" type="number" min="0" name="n_mujeres" class="form-control" value="0" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="permisos_n_hombres">Numero de hombres</label>
                                        <input id="permisos_n_hombres" type="number" min="0" name="n_hombres" class="form-control" value="0" required>
                                    </div>
                                </div>

                                <div>
                                    <button type="submit" class="btn btn-primary">Guardar permisos retributivos</button>
                                </div>
                            </form>

                            <hr class="my-4">
                            <h6 class="mb-3">Permisos retributivos registrados</h6>
                            <?php if ($idEmpresaSeleccionada <= 0): ?>
                                <div class="alert alert-secondary py-2">Selecciona una empresa para ver el listado.</div>
                            <?php elseif (empty($permisosRows)): ?>
                                <div class="alert alert-secondary py-2">No hay permisos retributivos registrados para esta empresa.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Motivo</th>
                                                <th>Tipo</th>
                                                <th>Mujeres</th>
                                                <th>Hombres</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($permisosRows ?? []) as $row): ?>
                                                <tr>
                                                    <td><?= (int)($row['id_registro'] ?? 0) ?></td>
                                                    <td><?= h((string)($row['motivo'] ?? '')) ?></td>
                                                    <td><?= h((string)($row['tipo'] ?? '')) ?></td>
                                                    <td><?= (int)($row['n_mujeres'] ?? 0) ?></td>
                                                    <td><?= (int)($row['n_hombres'] ?? 0) ?></td>
                                                    <td>
                                                        <?php if ($puedeEditarTablas): ?>
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-outline-primary btn-sm btn-edit-permiso"
                                                                data-id="<?= (int)($row['id_registro'] ?? 0) ?>"
                                                                data-motivo="<?= h((string)($row['motivo'] ?? '')) ?>"
                                                                data-tipo="<?= h((string)($row['tipo'] ?? '')) ?>"
                                                                data-n-mujeres="<?= (int)($row['n_mujeres'] ?? 0) ?>"
                                                                data-n-hombres="<?= (int)($row['n_hombres'] ?? 0) ?>">
                                                                Editar
                                                            </button>
                                                        <?php endif; ?>
                                                        <form class="mt-2" action="../controller/complemento_formulario_controler.php" method="POST" onsubmit="return confirm('¿Eliminar este permiso retributivo?');">
                                                            <input type="hidden" name="accion" value="eliminar_permiso">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                            <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                            <input type="hidden" name="id_registro" value="<?= (int)($row['id_registro'] ?? 0) ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (isset($cuestionarioTabs[$tab])): ?>
                            <?php
                            $configCuestionarioActivo = $cuestionarioTabs[$tab];
                            $filasCuestionarioActivo = $cuestionarioRows[$tab] ?? [];
                            $camposCuestionarioActivo = $configCuestionarioActivo['fields'] ?? [];
                            ?>
                            <form action="../controller/complemento_formulario_controler.php" method="POST" class="vstack gap-3">
                                <input type="hidden" name="accion" value="<?= h($tab) ?>">
                                <?= csrf_input() ?>
                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">

                                <div class="row g-3">
                                    <?php foreach (($camposCuestionarioActivo ?? []) as $campo): ?>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label"><?= h((string)($campo['label'] ?? 'Campo')) ?></label>
                                            <input type="text" name="<?= h((string)($campo['name'] ?? '')) ?>" class="form-control">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div>
                                    <button type="submit" class="btn btn-primary">Guardar cuestionario</button>
                                </div>
                            </form>

                            <hr class="my-4">
                            <h6 class="mb-3">Registros de <?= h((string)($configCuestionarioActivo['title'] ?? $tab)) ?></h6>

                            <?php if ($idEmpresaSeleccionada <= 0): ?>
                                <div class="alert alert-secondary py-2">Selecciona una empresa para ver el listado.</div>
                            <?php elseif (empty($filasCuestionarioActivo)): ?>
                                <div class="alert alert-secondary py-2">No hay registros para esta empresa.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <?php foreach (($camposCuestionarioActivo ?? []) as $campo): ?>
                                                    <th><?= h((string)($campo['label'] ?? 'Campo')) ?></th>
                                                <?php endforeach; ?>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($filasCuestionarioActivo ?? []) as $fila): ?>
                                                <tr>
                                                    <td><?= (int)($fila['id_registro'] ?? 0) ?></td>
                                                    <?php foreach (($camposCuestionarioActivo ?? []) as $campo): ?>
                                                        <td><?= h((string)($fila[(string)($campo['name'] ?? '')] ?? '')) ?></td>
                                                    <?php endforeach; ?>
                                                    <td>
                                                        <?php if ($puedeEditarTablas): ?>
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-outline-primary btn-sm btn-edit-cuestionario"
                                                                data-id="<?= (int)($fila['id_registro'] ?? 0) ?>"
                                                                data-tab="<?= h($tab) ?>"
                                                                data-values='<?= h(json_encode($fila)) ?>'>
                                                                Editar
                                                            </button>
                                                        <?php endif; ?>
                                                        <form class="mt-2" action="../controller/complemento_formulario_controler.php" method="POST" onsubmit="return confirm('¿Eliminar este cuestionario?');">
                                                            <input type="hidden" name="accion" value="<?= h('eliminar_' . $tab) ?>">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                            <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                            <input type="hidden" name="id_registro" value="<?= (int)($fila['id_registro'] ?? 0) ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- MODALES DE EDICION -->
    <div class="modal fade" id="modalEditarGenerico" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Editar Registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formEditarGenerico" action="../controller/complemento_formulario_controler.php" method="POST" class="vstack gap-3">
                        <input type="hidden" name="accion" id="edit_accion" value="">
                        <?= csrf_input() ?>
                        <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                        <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                        <input type="hidden" name="id_registro" id="edit_id_registro" value="">
                        <input type="hidden" name="id_bajas" id="edit_id_baja" value="">

                        <div id="edit_fields_container">
                            <!-- Los campos se inyectarán aquí vía JS -->
                        </div>

                        <div class="pt-3">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">GUARDAR CAMBIOS</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modalEditar = new bootstrap.Modal(document.getElementById('modalEditarGenerico'));
            const formEditar = document.getElementById('formEditarGenerico');
            const container = document.getElementById('edit_fields_container');
            const inputAccion = document.getElementById('edit_accion');
            const inputIdRegistro = document.getElementById('edit_id_registro');
            const inputIdBaja = document.getElementById('edit_id_baja');
            const selectTipoBaja = document.getElementById('tipo_baja');
            const bloqueTemporales = document.getElementById('bloque_temporales');
            const bloqueDefinitivas = document.getElementById('bloque_definitivas');

            const actualizarBloquesBajas = () => {
                if (!selectTipoBaja || !bloqueTemporales || !bloqueDefinitivas) {
                    return;
                }

                const esTemporal = selectTipoBaja.value === 'TEMPORALES';
                const esDefinitiva = selectTipoBaja.value === 'DEFINITIVAS';

                bloqueTemporales.classList.toggle('d-none', esDefinitiva);
                bloqueDefinitivas.classList.toggle('d-none', !esDefinitiva);
            };

            if (selectTipoBaja) {
                selectTipoBaja.addEventListener('change', actualizarBloquesBajas);
                actualizarBloquesBajas();
            }

            const selectExcedenciasTipo = document.getElementById('excedencias_tipo');
            const containerExcedenciasMotivo = document.getElementById('excedencias_motivo_container');
            const selectPermisosTipo = document.getElementById('permisos_tipo');
            const containerPermisosMotivo = document.getElementById('permisos_motivo_container');

            if (selectExcedenciasTipo && containerExcedenciasMotivo) {
                selectExcedenciasTipo.addEventListener('change', function() {
                    containerExcedenciasMotivo.classList.toggle('d-none', this.value !== 'Otros');
                });
            }
            if (selectPermisosTipo && containerPermisosMotivo) {
                selectPermisosTipo.addEventListener('change', function() {
                    containerPermisosMotivo.classList.toggle('d-none', this.value !== 'Otros');
                });
            }

            // Bajas
            document.querySelectorAll('.btn-edit-baja').forEach(btn => {
                btn.addEventListener('click', function() {
                    container.innerHTML = `
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Tipo de baja</label>
                                <select name="tipo_baja" class="form-select" required>
                                    <option value="TEMPORALES" ${this.dataset.tipoBaja === 'TEMPORALES' ? 'selected' : ''}>Temporales</option>
                                    <option value="DEFINITIVAS" ${this.dataset.tipoBaja === 'DEFINITIVAS' ? 'selected' : ''}>Definitivas</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Tipo temporal</label>
                                <select name="tipo_temporal" class="form-select">
                                    <option value="">-- Selecciona --</option>
                                    <option value="Enfermedad Común" ${this.dataset.tipoTemporal === 'Enfermedad Común' ? 'selected' : ''}>Enfermedad Común</option>
                                    <option value="Accidente Laboral" ${this.dataset.tipoTemporal === 'Accidente Laboral' ? 'selected' : ''}>Accidente Laboral</option>
                                    <option value="Riesgo embarazo" ${this.dataset.tipoTemporal === 'Riesgo embarazo' ? 'selected' : ''}>Riesgo embarazo</option>
                                    <option value="COVID" ${this.dataset.tipoTemporal === 'COVID' ? 'selected' : ''}>COVID</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Tipo definitiva</label>
                                <select name="tipo_definitiva" class="form-select">
                                    <option value="">-- Selecciona --</option>
                                    <option value="Despido" ${this.dataset.tipoDefinitiva === 'Despido' ? 'selected' : ''}>Despido</option>
                                    <option value="Fallecimiento" ${this.dataset.tipoDefinitiva === 'Fallecimiento' ? 'selected' : ''}>Fallecimiento</option>
                                    <option value="Jubilación" ${this.dataset.tipoDefinitiva === 'Jubilación' ? 'selected' : ''}>Jubilación</option>
                                    <option value="Finalización contrato" ${this.dataset.tipoDefinitiva === 'Finalización contrato' ? 'selected' : ''}>Finalización contrato</option>
                                    <option value="No superación de periodo de prueba" ${this.dataset.tipoDefinitiva === 'No superación de periodo de prueba' ? 'selected' : ''}>No superación de periodo de prueba</option>
                                    <option value="Baja voluntaria" ${this.dataset.tipoDefinitiva === 'Baja voluntaria' ? 'selected' : ''}>Baja voluntaria</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-6">
                                <label class="form-label fw-bold">Nº Mujeres</label>
                                <input type="number" name="num_mujeres" class="form-control" value="${this.dataset.numMujeres}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Nº Hombres</label>
                                <input type="number" name="num_hombres" class="form-control" value="${this.dataset.numHombres}" required>
                            </div>
                        </div>
                    `;
                    inputAccion.value = 'editar_baja';
                    inputIdBaja.value = this.dataset.id;
                    inputIdRegistro.value = '';
                    modalEditar.show();
                });
            });

            // Formacion
            document.querySelectorAll('.btn-edit-formacion').forEach(btn => {
                btn.addEventListener('click', function() {
                    container.innerHTML = `
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la formación</label>
                            <input type="text" name="tipo" class="form-control" value="${this.dataset.tipo}" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-4">
                                <label class="form-label fw-bold">Horas</label>
                                <input type="number" name="n_horas" class="form-control" value="${this.dataset.nHoras || 0}">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold">Mujeres</label>
                                <input type="number" name="n_mujeres" class="form-control" value="${this.dataset.nMujeres}" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold">Hombres</label>
                                <input type="number" name="n_hombres" class="form-control" value="${this.dataset.nHombres}" required>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Modalidad</label>
                                <select name="modalidad" class="form-select">
                                    <option value="" ${!this.dataset.modalidad ? 'selected' : ''}>Selecciona...</option>
                                    <option value="Presencial" ${this.dataset.modalidad === 'Presencial' ? 'selected' : ''}>Presencial</option>
                                    <option value="Online" ${this.dataset.modalidad === 'Online' ? 'selected' : ''}>Online</option>
                                    <option value="Mixta" ${this.dataset.modalidad === 'Mixta' ? 'selected' : ''}>Mixta</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Horario</label>
                                <select name="horario" class="form-select">
                                    <option value="" ${!this.dataset.horario ? 'selected' : ''}>Selecciona...</option>
                                    <option value="Dentro del horario" ${this.dataset.horario === 'Dentro del horario' ? 'selected' : ''}>Dentro del horario</option>
                                    <option value="Fuera del horario" ${this.dataset.horario === 'Fuera del horario' ? 'selected' : ''}>Fuera del horario</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Perfil (Puesto)</label>
                                <input type="text" name="perfil_puesto" class="form-control" value="${this.dataset.perfilPuesto || ''}">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Carácter</label>
                                <select name="caracter" class="form-select">
                                    <option value="" ${!this.dataset.caracter ? 'selected' : ''}>Selecciona...</option>
                                    <option value="Obligatoria" ${this.dataset.caracter === 'Obligatoria' ? 'selected' : ''}>Obligatoria</option>
                                    <option value="Voluntaria" ${this.dataset.caracter === 'Voluntaria' ? 'selected' : ''}>Voluntaria</option>
                                </select>
                            </div>
                        </div>
                    `;
                    inputAccion.value = 'editar_formacion';
                    inputIdRegistro.value = this.dataset.id;
                    inputIdBaja.value = '';
                    modalEditar.show();
                });
            });

            // Excedencias
            document.querySelectorAll('.btn-edit-excedencia').forEach(btn => {
                btn.addEventListener('click', function() {
                    container.innerHTML = `
                        <div class="mb-3 ${this.dataset.tipo === 'Otros' ? '' : 'd-none'}" id="edit_excedencias_motivo_container">
                            <label class="form-label fw-bold">Motivo</label>
                            <input type="text" name="motivo" class="form-control" value="${this.dataset.motivo}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de excedencia</label>
                            <select name="tipo" class="form-select" required onchange="document.getElementById('edit_excedencias_motivo_container').classList.toggle('d-none', this.value !== 'Otros')">
                                <option value="Excedencias Voluntarias" ${this.dataset.tipo === 'Excedencias Voluntarias' ? 'selected' : ''}>Excedencias Voluntarias</option>
                                <option value="Excedencias Cuidado Menores" ${this.dataset.tipo === 'Excedencias Cuidado Menores' ? 'selected' : ''}>Excedencias Cuidado Menores</option>
                                <option value="Excedencias Cuidado de Personas Mayores" ${this.dataset.tipo === 'Excedencias Cuidado de Personas Mayores' ? 'selected' : ''}>Excedencias Cuidado de Personas Mayores</option>
                                <option value="Otros" ${this.dataset.tipo === 'Otros' ? 'selected' : ''}>Otros</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Nº Mujeres</label>
                                <input type="number" name="n_mujeres" class="form-control" value="${this.dataset.nMujeres}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Nº Hombres</label>
                                <input type="number" name="n_hombres" class="form-control" value="${this.dataset.nHombres}" required>
                            </div>
                        </div>
                    `;
                    inputAccion.value = 'editar_excedencia';
                    inputIdRegistro.value = this.dataset.id;
                    inputIdBaja.value = '';
                    modalEditar.show();
                });
            });

            // Permisos
            document.querySelectorAll('.btn-edit-permiso').forEach(btn => {
                btn.addEventListener('click', function() {
                    container.innerHTML = `
                        <div class="mb-3 ${this.dataset.tipo === 'Otros' ? '' : 'd-none'}" id="edit_permisos_motivo_container">
                            <label class="form-label fw-bold">Motivo</label>
                            <input type="text" name="motivo" class="form-control" value="${this.dataset.motivo}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de permiso</label>
                            <select name="tipo" class="form-select" required onchange="document.getElementById('edit_permisos_motivo_container').classList.toggle('d-none', this.value !== 'Otros')">
                                <option value="Lactancia" ${this.dataset.tipo === 'Lactancia' ? 'selected' : ''}>Lactancia</option>
                                <option value="Nacimiento" ${this.dataset.tipo === 'Nacimiento' ? 'selected' : ''}>Nacimiento</option>
                                <option value="Matrimonio" ${this.dataset.tipo === 'Matrimonio' ? 'selected' : ''}>Matrimonio</option>
                                <option value="Hospitalizacion de familiares" ${this.dataset.tipo === 'Hospitalizacion de familiares' ? 'selected' : ''}>Hospitalizacion de familiares</option>
                                <option value="Otros" ${this.dataset.tipo === 'Otros' ? 'selected' : ''}>Otros</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Nº Mujeres</label>
                                <input type="number" name="n_mujeres" class="form-control" value="${this.dataset.nMujeres}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Nº Hombres</label>
                                <input type="number" name="n_hombres" class="form-control" value="${this.dataset.nHombres}" required>
                            </div>
                        </div>
                    `;
                    inputAccion.value = 'editar_permiso';
                    inputIdRegistro.value = this.dataset.id;
                    inputIdBaja.value = '';
                    modalEditar.show();
                });
            });

            // Cuestionarios
            const cuestionarioFields = <?= json_encode($cuestionarioTabs) ?>;
            document.querySelectorAll('.btn-edit-cuestionario').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tab = this.dataset.tab;
                    const values = JSON.parse(this.dataset.values);
                    const config = cuestionarioFields[tab];
                    
                    let fieldsHtml = '<div class="row g-3">';
                    config.fields.forEach(f => {
                        fieldsHtml += `
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">${f.label}</label>
                                <input type="text" name="${f.name}" class="form-control" value="${values[f.name] || ''}">
                            </div>
                        `;
                    });
                    fieldsHtml += '</div>';

                    container.innerHTML = fieldsHtml;
                    inputAccion.value = 'editar_' + tab;
                    inputIdRegistro.value = this.dataset.id;
                    inputIdBaja.value = '';
                    modalEditar.show();
                });
            });
        });

    </script>
</body>
</html>