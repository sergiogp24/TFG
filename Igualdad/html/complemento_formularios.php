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

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

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

    $sql = '
        SELECT 1
        FROM archivos a
        INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
        INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
        WHERE UPPER(TRIM(a.tipo)) = "REGISTRO_RETRIBUTIVO" AND ac.id_empresa = ?
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

    $sqlDirecto = '
        SELECT 1
        FROM archivos a
        WHERE UPPER(TRIM(a.tipo)) = "REGISTRO_RETRIBUTIVO" AND a.id_empresa = ?
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

$msg = trim((string)($_GET['msg'] ?? ''));
$tab = trim((string)($_GET['tab'] ?? 'bajas'));
$embed = ((string)($_GET['embed'] ?? '') === '1');
$idEmpresaSeleccionada = (int)($_GET['id_empresa'] ?? 0);

$cuestionarioTabs = [
    'cuestionario_seleccion_personal' => [
        'label' => 'Cuest. seleccion personal',
        'table' => 'cuestionario_seleccion_personal',
        'title' => 'Cuestionario seleccion personal',
        'fields' => [
            ['name' => 'factores_determinantes', 'label' => 'Factores determinantes'],
            ['name' => 'incorporacion_nuevo_personal', 'label' => 'Incorporacion nuevo personal'],
            ['name' => 'publicacion_interna', 'label' => 'Publicacion interna'],
            ['name' => 'personas_responsables', 'label' => 'Personas responsables'],
            ['name' => 'caracteristicas_candidaturas', 'label' => 'Caracteristicas candidaturas'],
            ['name' => 'entrevista_salida', 'label' => 'Entrevista salida'],
            ['name' => 'sistema_reclutamiento', 'label' => 'Sistema reclutamiento'],
            ['name' => 'definicion_perfiles', 'label' => 'Definicion perfiles'],
            ['name' => 'metodos_seleccion', 'label' => 'Metodos seleccion'],
            ['name' => 'ultima_decision', 'label' => 'Ultima decision'],
            ['name' => 'barreras_internas_externas', 'label' => 'Barreras internas/externas'],
        ],
    ],
    'cuestionario_promocion_profesional' => [
        'label' => 'Cuest. promocion profesional',
        'table' => 'cuestionario_promocion_profesional',
        'title' => 'Cuestionario promocion profesional',
        'fields' => [
            ['name' => 'metodologia', 'label' => 'Metodologia'],
            ['name' => 'metodologia_evaluacion', 'label' => 'Metodologia evaluacion'],
            ['name' => 'personas_intervienen', 'label' => 'Personas intervienen'],
            ['name' => 'formacion_ligada', 'label' => 'Formacion ligada'],
            ['name' => 'acciones_fomentar', 'label' => 'Acciones fomentar'],
            ['name' => 'requisitos', 'label' => 'Requisitos'],
            ['name' => 'planes_carrera', 'label' => 'Planes carrera'],
            ['name' => 'comunicacion_vacantes', 'label' => 'Comunicacion vacantes'],
            ['name' => 'dificultades_promocion', 'label' => 'Dificultades promocion'],
        ],
    ],
    'cuestionario_formacion' => [
        'label' => 'Cuest. formacion',
        'table' => 'cuestionario_formacion',
        'title' => 'Cuestionario formacion',
        'fields' => [
            ['name' => 'deteccion_formativas', 'label' => 'Deteccion formativas'],
            ['name' => 'difusion_ofertas', 'label' => 'Difusion ofertas'],
            ['name' => 'puede_solicitar', 'label' => 'Puede solicitar'],
            ['name' => 'compensacion_fuera', 'label' => 'Compensacion fuera'],
            ['name' => 'posibilidad_formacion', 'label' => 'Posibilidad formacion'],
            ['name' => 'formacion_mujeres', 'label' => 'Formacion mujeres'],
            ['name' => 'existencia_plan', 'label' => 'Existencia plan'],
            ['name' => 'asisten_igualmente', 'label' => 'Asisten igualmente'],
            ['name' => 'criterios_seleccion', 'label' => 'Criterios seleccion'],
            ['name' => 'impartacion_fuera', 'label' => 'Impartacion fuera'],
            ['name' => 'ayudas_formacion', 'label' => 'Ayudas formacion'],
            ['name' => 'formacion_igualdad', 'label' => 'Formacion igualdad'],
            ['name' => 'coste_medio', 'label' => 'Coste medio'],
            ['name' => 'formacion_reciclaje', 'label' => 'Formacion reciclaje'],
        ],
    ],
    'cuestionario_conciliacion_corresponsabilidad' => [
        'label' => 'Cuest. conciliacion',
        'table' => 'cuestionario_conciliacion_corresponsabilidad',
        'title' => 'Cuestionario conciliacion corresponsabilidad',
        'fields' => [
            ['name' => 'ordenacion_tiempo', 'label' => 'Ordenacion tiempo'],
            ['name' => 'quienes_utilizan', 'label' => 'Quienes utilizan'],
            ['name' => 'reduccion_jornada', 'label' => 'Reduccion jornada'],
            ['name' => 'mecanismos_disponibles', 'label' => 'Mecanismos disponibles'],
            ['name' => 'cuantas_personas', 'label' => 'Cuantas personas'],
            ['name' => 'canales_informacion', 'label' => 'Canales informacion'],
        ],
    ],
    'cuestionario_infrarrepresentacion_femenina' => [
        'label' => 'Cuest. infrarrepresentacion',
        'table' => 'cuestionario_infrarrepresentacion_femenina',
        'title' => 'Cuestionario infrarrepresentacion femenina',
        'fields' => [
            ['name' => 'barreras_internas', 'label' => 'Barreras internas'],
            ['name' => 'hay_mujeres', 'label' => 'Hay mujeres'],
        ],
    ],
    'cuestionario_salud_laboral' => [
        'label' => 'Cuest. salud laboral',
        'table' => 'cuestionario_salud_laboral',
        'title' => 'Cuestionario salud laboral',
        'fields' => [
            ['name' => 'seguridad_salud', 'label' => 'Seguridad salud'],
            ['name' => 'medidas_linea', 'label' => 'Medidas en linea'],
            ['name' => 'incluido_perspectiva', 'label' => 'Incluido perspectiva'],
            ['name' => 'permite_desconexion', 'label' => 'Permite desconexion'],
        ],
    ],
    'cuestionario_prevencion_acoso_sexual' => [
        'label' => 'Cuest. prevencion acoso',
        'table' => 'cuestionario_prevencion_acoso_sexual',
        'title' => 'Cuestionario prevencion acoso sexual',
        'fields' => [
            ['name' => 'conocen_acoso', 'label' => 'Conocen acoso'],
            ['name' => 'protocolo_prevencion', 'label' => 'Protocolo prevencion'],
            ['name' => 'medidas_sensibilizacion', 'label' => 'Medidas sensibilizacion'],
        ],
    ],
    'cuestionario_violencia_genero' => [
        'label' => 'Cuest. violencia genero',
        'table' => 'cuestionario_violencia_genero',
        'title' => 'Cuestionario violencia genero',
        'fields' => [
            ['name' => 'conocimiento_contratada', 'label' => 'Conocimiento contratada'],
            ['name' => 'prevision_progama', 'label' => 'Prevision programa'],
        ],
    ],
    'cuestionario_comunicacion_identidad_corporativa' => [
        'label' => 'Cuest. comunicacion identidad',
        'table' => 'cuestionario_comunicacion_identidad_corporativa',
        'title' => 'Cuestionario comunicacion identidad corporativa',
        'fields' => [
            ['name' => 'canales_comunicacion', 'label' => 'Canales comunicacion'],
            ['name' => 'campanas_comunicacion', 'label' => 'Campanas comunicacion'],
            ['name' => 'imagen_empresa', 'label' => 'Imagen empresa'],
            ['name' => 'existencia_comunicacion', 'label' => 'Existencia comunicacion'],
            ['name' => 'frecuencia', 'label' => 'Frecuencia'],
            ['name' => 'lenguaje_imagen', 'label' => 'Lenguaje imagen'],
            ['name' => 'objetivos', 'label' => 'Objetivos'],
            ['name' => 'filosofia', 'label' => 'Filosofia'],
            ['name' => 'procesos_calidad', 'label' => 'Procesos calidad'],
            ['name' => 'responsabilidad_social', 'label' => 'Responsabilidad social'],
        ],
    ],
];

$tabsPermitidas = array_merge(['bajas', 'formacion', 'excedencias', 'permisos'], array_keys($cuestionarioTabs));
if (!in_array($tab, $tabsPermitidas, true)) {
    $tab = 'bajas';
}

$tabHrefExtra = $embed ? '&embed=1' : '';
$urlVolverRegistro = $esStaff ? 'index_staff.php' : 'index_cliente.php';

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


$complementosBloqueados = (!$empresaFijada || !$empresaTieneRegistro);

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

        $resFormacion = complemento_fetch_simple_rows('area_formaciones', $idEmpresaSeleccionada, ['tipo', 'n_mujeres', 'n_hombres']);
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

                                <a class="nav-button" href="index_documentos_tipo.php">
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
                    <h5 class="mb-3">Complemento Formularios</h5>

                    <?php if ($msg !== ''): ?>
                        <div class="alert alert-info py-2"><?= h($msg) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($erroresListado)): ?>
                        <div class="alert alert-warning py-2">
                            <?php foreach ($erroresListado as $err): ?>
                                <div><?= h($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4 d-flex align-items-center gap-3">
                        <label class="form-label mb-0 fw-bold">Empresa:</label>
                        <?php if ($embed): ?>
                            <input type="text" class="form-control w-auto" value="<?= h($empresaFijadaNombre) ?>" readonly>
                        <?php else: ?>
                            <select class="form-select w-auto" onchange="window.location.href='complemento_formularios.php?tab=<?= h($tab) ?><?= $embed ? '&embed=1' : '' ?>&id_empresa=' + this.value;">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($empresasDisponibles as $empresa): ?>
                                    <option value="<?= (int)$empresa['id_empresa'] ?>" <?= ((int)$empresa['id_empresa'] === $idEmpresaSeleccionada) ? 'selected' : '' ?>><?= h($empresa['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <?php if ($complementosBloqueados): ?>
                        <div class="alert alert-warning mb-4">
                            Debes subir primero el Registro Retributivo en esta empresa para desbloquear los complementos de formularios.
                        </div>
                    <?php endif; ?>

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
                            <?php foreach ($cuestionarioTabs as $tabCuestionario => $configCuestionario): ?>
                                <a class="btn <?= $tab === $tabCuestionario ? 'btn-primary' : 'btn-outline-primary' ?><?= $complementosBloqueados ? ' disabled opacity-50' : '' ?>" href="<?= $complementosBloqueados ? '#' : 'complemento_formularios.php?tab=' . urlencode($tabCuestionario) . $tabHrefExtra ?>" tabindex="<?= $complementosBloqueados ? '-1' : '0' ?>"><?= h((string)($configCuestionario['label'] ?? $tabCuestionario)) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($complementosBloqueados): ?>
                        <div class="alert alert-secondary mb-0">
                            Selecciona una empresa que ya haya subido su Registro Retributivo para ver y usar sus complementos.
                        </div>
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

                            <div>
                                <label class="form-label" for="bajas_motivo">Motivo</label>
                                <input id="bajas_motivo" type="text" name="motivo" class="form-control" maxlength="255">
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
                                            <th>Motivo</th>
                                            <th>Mujeres</th>
                                            <th>Hombres</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bajasRows as $row): ?>
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
                                                <td><?= h((string)($row['motivo'] ?? '')) ?></td>
                                                <td><?= (int)($row['num_mujeres'] ?? 0) ?></td>
                                                <td><?= (int)($row['num_hombres'] ?? 0) ?></td>
                                                <td>
                                                    <?php if ($puedeEditarTablas): ?>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form class="mt-2 vstack gap-2" action="../controller/complemento_formulario_controler.php" method="POST">
                                                                <input type="hidden" name="accion" value="editar_baja">
                                                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                                <input type="hidden" name="id_bajas" value="<?= (int)($row['id_bajas'] ?? 0) ?>">

                                                                <select name="tipo_baja" class="form-control form-control-sm" required>
                                                                    <option value="TEMPORALES" <?= $esTemporal ? 'selected' : '' ?>>Temporales</option>
                                                                    <option value="DEFINITIVAS" <?= !$esTemporal ? 'selected' : '' ?>>Definitivas</option>
                                                                </select>
                                                                <input type="text" name="motivo" class="form-control form-control-sm" value="<?= h((string)($row['motivo'] ?? '')) ?>" placeholder="Motivo">
                                                                <select name="tipo_temporal" class="form-control form-control-sm">
                                                                    <option value="">-- Tipo temporal --</option>
                                                                    <option value="Enfermedad Común" <?= $tipoTemporal === 'Enfermedad Común' ? 'selected' : '' ?>>Enfermedad Común</option>
                                                                    <option value="Accidente Laboral" <?= $tipoTemporal === 'Accidente Laboral' ? 'selected' : '' ?>>Accidente Laboral</option>
                                                                    <option value="Riesgo embarazo" <?= $tipoTemporal === 'Riesgo embarazo' ? 'selected' : '' ?>>Riesgo embarazo</option>
                                                                    <option value="COVID" <?= $tipoTemporal === 'COVID' ? 'selected' : '' ?>>COVID</option>
                                                                </select>
                                                                <select name="tipo_definitiva" class="form-control form-control-sm">
                                                                    <option value="">-- Tipo definitiva --</option>
                                                                    <option value="Despido" <?= $tipoDefinitiva === 'Despido' ? 'selected' : '' ?>>Despido</option>
                                                                    <option value="Fallecimiento" <?= $tipoDefinitiva === 'Fallecimiento' ? 'selected' : '' ?>>Fallecimiento</option>
                                                                    <option value="Jubilación" <?= $tipoDefinitiva === 'Jubilación' ? 'selected' : '' ?>>Jubilación</option>
                                                                    <option value="Finalización contrato" <?= $tipoDefinitiva === 'Finalización contrato' ? 'selected' : '' ?>>Finalización contrato</option>
                                                                    <option value="No superación de periodo de prueba" <?= $tipoDefinitiva === 'No superación de periodo de prueba' ? 'selected' : '' ?>>No superación de periodo de prueba</option>
                                                                    <option value="Baja voluntaria" <?= $tipoDefinitiva === 'Baja voluntaria' ? 'selected' : '' ?>>Baja voluntaria</option>
                                                                </select>
                                                                <div class="row g-2">
                                                                    <div class="col-6">
                                                                        <input type="number" min="0" name="num_mujeres" class="form-control form-control-sm" value="<?= (int)($row['num_mujeres'] ?? 0) ?>" required>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <input type="number" min="0" name="num_hombres" class="form-control form-control-sm" value="<?= (int)($row['num_hombres'] ?? 0) ?>" required>
                                                                    </div>
                                                                </div>
                                                                <button type="submit" class="btn btn-sm btn-success">Guardar cambios</button>
                                                            </form>
                                                        </details>
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
                                <input id="formacion_tipo" type="text" name="tipo" class="form-control" maxlength="100">
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="formacion_n_mujeres">Numero de mujeres</label>
                                    <input id="formacion_n_mujeres" type="number" min="0" name="n_mujeres" class="form-control" value="0" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="formacion_n_hombres">Numero de hombres</label>
                                    <input id="formacion_n_hombres" type="number" min="0" name="n_hombres" class="form-control" value="0" required>
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
                                            <th>Tipo</th>
                                            <th>Mujeres</th>
                                            <th>Hombres</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formacionRows as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_registro'] ?? 0) ?></td>
                                                <td><?= h((string)($row['tipo'] ?? '')) ?></td>
                                                <td><?= (int)($row['n_mujeres'] ?? 0) ?></td>
                                                <td><?= (int)($row['n_hombres'] ?? 0) ?></td>
                                                <td>
                                                    <?php if ($puedeEditarTablas): ?>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form class="mt-2 vstack gap-2" action="../controller/complemento_formulario_controler.php" method="POST">
                                                                <input type="hidden" name="accion" value="editar_formacion">
                                                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                                <input type="hidden" name="id_registro" value="<?= (int)($row['id_registro'] ?? 0) ?>">
                                                                <input type="text" name="tipo" class="form-control form-control-sm" value="<?= h((string)($row['tipo'] ?? '')) ?>" required>
                                                                <div class="row g-2">
                                                                    <div class="col-6"><input type="number" min="0" name="n_mujeres" class="form-control form-control-sm" value="<?= (int)($row['n_mujeres'] ?? 0) ?>" required></div>
                                                                    <div class="col-6"><input type="number" min="0" name="n_hombres" class="form-control form-control-sm" value="<?= (int)($row['n_hombres'] ?? 0) ?>" required></div>
                                                                </div>
                                                                <button type="submit" class="btn btn-sm btn-success">Guardar cambios</button>
                                                            </form>
                                                        </details>
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

                            <div>
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
                                        <?php foreach ($excedenciasRows as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_registro'] ?? 0) ?></td>
                                                <td><?= h((string)($row['motivo'] ?? '')) ?></td>
                                                <td><?= h((string)($row['tipo'] ?? '')) ?></td>
                                                <td><?= (int)($row['n_mujeres'] ?? 0) ?></td>
                                                <td><?= (int)($row['n_hombres'] ?? 0) ?></td>
                                                <td>
                                                    <?php if ($puedeEditarTablas): ?>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form class="mt-2 vstack gap-2" action="../controller/complemento_formulario_controler.php" method="POST">
                                                                <input type="hidden" name="accion" value="editar_excedencia">
                                                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                                <input type="hidden" name="id_registro" value="<?= (int)($row['id_registro'] ?? 0) ?>">
                                                                <input type="text" name="motivo" class="form-control form-control-sm" value="<?= h((string)($row['motivo'] ?? '')) ?>">
                                                                <select name="tipo" class="form-control form-control-sm" required>
                                                                    <option value="Excedencias Voluntarias" <?= (string)($row['tipo'] ?? '') === 'Excedencias Voluntarias' ? 'selected' : '' ?>>Excedencias Voluntarias</option>
                                                                    <option value="Excedencias Cuidado Menores" <?= (string)($row['tipo'] ?? '') === 'Excedencias Cuidado Menores' ? 'selected' : '' ?>>Excedencias Cuidado Menores</option>
                                                                    <option value="Excedencias Cuidado de Personas Mayores" <?= (string)($row['tipo'] ?? '') === 'Excedencias Cuidado de Personas Mayores' ? 'selected' : '' ?>>Excedencias Cuidado de Personas Mayores</option>
                                                                </select>
                                                                <div class="row g-2">
                                                                    <div class="col-6"><input type="number" min="0" name="n_mujeres" class="form-control form-control-sm" value="<?= (int)($row['n_mujeres'] ?? 0) ?>" required></div>
                                                                    <div class="col-6"><input type="number" min="0" name="n_hombres" class="form-control form-control-sm" value="<?= (int)($row['n_hombres'] ?? 0) ?>" required></div>
                                                                </div>
                                                                <button type="submit" class="btn btn-sm btn-success">Guardar cambios</button>
                                                            </form>
                                                        </details>
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

                            <div>
                                <label class="form-label" for="permisos_motivo">Motivo</label>
                                <input id="permisos_motivo" type="text" name="motivo" class="form-control" maxlength="100">
                            </div>

                            <div>
                                <label class="form-label" for="permisos_tipo">Tipo</label>
                                <select id="permisos_tipo" name="tipo" class="form-control" required>
                                    <option value="">-- Selecciona tipo de permiso --</option>
                                    <option value="Lactancia">Lactancia</option>
                                    <option value="Nacimiento">Nacimiento</option>
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
                                        <?php foreach ($permisosRows as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_registro'] ?? 0) ?></td>
                                                <td><?= h((string)($row['motivo'] ?? '')) ?></td>
                                                <td><?= h((string)($row['tipo'] ?? '')) ?></td>
                                                <td><?= (int)($row['n_mujeres'] ?? 0) ?></td>
                                                <td><?= (int)($row['n_hombres'] ?? 0) ?></td>
                                                <td>
                                                    <?php if ($puedeEditarTablas): ?>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form class="mt-2 vstack gap-2" action="../controller/complemento_formulario_controler.php" method="POST">
                                                                <input type="hidden" name="accion" value="editar_permiso">
                                                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                                <input type="hidden" name="id_registro" value="<?= (int)($row['id_registro'] ?? 0) ?>">
                                                                <input type="text" name="motivo" class="form-control form-control-sm" value="<?= h((string)($row['motivo'] ?? '')) ?>">
                                                                <select name="tipo" class="form-control form-control-sm" required>
                                                                    <option value="Lactancia" <?= (string)($row['tipo'] ?? '') === 'Lactancia' ? 'selected' : '' ?>>Lactancia</option>
                                                                    <option value="Nacimiento" <?= (string)($row['tipo'] ?? '') === 'Nacimiento' ? 'selected' : '' ?>>Nacimiento</option>
                                                                </select>
                                                                <div class="row g-2">
                                                                    <div class="col-6"><input type="number" min="0" name="n_mujeres" class="form-control form-control-sm" value="<?= (int)($row['n_mujeres'] ?? 0) ?>" required></div>
                                                                    <div class="col-6"><input type="number" min="0" name="n_hombres" class="form-control form-control-sm" value="<?= (int)($row['n_hombres'] ?? 0) ?>" required></div>
                                                                </div>
                                                                <button type="submit" class="btn btn-sm btn-success">Guardar cambios</button>
                                                            </form>
                                                        </details>
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
                                <?php foreach ($camposCuestionarioActivo as $campo): ?>
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
                                            <?php foreach ($camposCuestionarioActivo as $campo): ?>
                                                <th><?= h((string)($campo['label'] ?? 'Campo')) ?></th>
                                            <?php endforeach; ?>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filasCuestionarioActivo as $fila): ?>
                                            <tr>
                                                <td><?= (int)($fila['id_registro'] ?? 0) ?></td>
                                                <?php foreach ($camposCuestionarioActivo as $campo): ?>
                                                    <td><?= h((string)($fila[(string)($campo['name'] ?? '')] ?? '')) ?></td>
                                                <?php endforeach; ?>
                                                <td>
                                                    <?php if ($puedeEditarTablas): ?>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form class="mt-2 vstack gap-2" action="../controller/complemento_formulario_controler.php" method="POST">
                                                                <input type="hidden" name="accion" value="<?= h('editar_' . $tab) ?>">
                                                                <?= csrf_input() ?>
                                                                <input type="hidden" name="embed" value="<?= $embed ? '1' : '0' ?>">
                                                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSeleccionada ?>">
                                                                <input type="hidden" name="id_registro" value="<?= (int)($fila['id_registro'] ?? 0) ?>">
                                                                <?php foreach ($camposCuestionarioActivo as $campo): ?>
                                                                    <?php $nombreCampo = (string)($campo['name'] ?? ''); ?>
                                                                    <input
                                                                        type="text"
                                                                        name="<?= h($nombreCampo) ?>"
                                                                        class="form-control form-control-sm"
                                                                        placeholder="<?= h((string)($campo['label'] ?? $nombreCampo)) ?>"
                                                                        value="<?= h((string)($fila[$nombreCampo] ?? '')) ?>"
                                                                    >
                                                                <?php endforeach; ?>
                                                                <button type="submit" class="btn btn-sm btn-success">Guardar cambios</button>
                                                            </form>
                                                        </details>
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
                </div>
            </main>
        </div>
    </div>

    <script>
        (function() {
            const tipoBaja = document.getElementById('tipo_baja');
            const bloqueTemporales = document.getElementById('bloque_temporales');
            const bloqueDefinitivas = document.getElementById('bloque_definitivas');
            const tipoTemporal = document.getElementById('tipo_temporal');
            const tipoDefinitiva = document.getElementById('tipo_definitiva');

            if (!tipoBaja || !bloqueTemporales || !bloqueDefinitivas || !tipoTemporal || !tipoDefinitiva) {
                return;
            }

            function actualizarBaja() {
                const valor = (tipoBaja.value || '').toUpperCase();
                const esTemporal = valor === 'TEMPORALES';
                const esDefinitiva = valor === 'DEFINITIVAS';

                bloqueTemporales.classList.toggle('d-none', !esTemporal);
                bloqueDefinitivas.classList.toggle('d-none', !esDefinitiva);

                tipoTemporal.required = esTemporal;
                tipoDefinitiva.required = esDefinitiva;

                if (!esTemporal) {
                    tipoTemporal.value = '';
                }
                if (!esDefinitiva) {
                    tipoDefinitiva.value = '';
                }
            }

            tipoBaja.addEventListener('change', actualizarBaja);
            actualizarBaja();
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>