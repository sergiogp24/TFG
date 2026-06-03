<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();
require_role('CLIENTE');
require_once __DIR__ . '/../php/helpers.php';
require_once __DIR__ . '/../php/mails.php';
require __DIR__ . '/../config/config.php';

/**
 * Página: Área Cliente
 * --------------------
 * Esta plantilla muestra el panel del cliente con vistas para:
 * - gestión de empresas asignadas
 * - subida de registro retributivo
 * - creación/gestión de reuniones
 * - acceso al demo de mantenimiento (iframe)
 *
 * Requiere que el usuario esté autenticado con rol `CLIENTE`.
 */


function formatear_fecha_resumen(string $fecha): string
{
    /**
     * Formatea una fecha YYYY-MM-DD a un resumen corto para mostrar en tarjetas,
     * p.ej. "5 may" o "12 ene". Devuelve cadena vacía en caso de error.
     */
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return '';
    }

    $meses = [
        'ene',
        'feb',
        'mar',
        'abr',
        'may',
        'jun',
        'jul',
        'ago',
        'sep',
        'oct',
        'nov',
        'dic',
    ];

    $dia = date('j', $timestamp);
    $mesIndex = (int)date('n', $timestamp) - 1;

    return $dia . ' ' . ($meses[$mesIndex] ?? date('m', $timestamp));
}

function empresa_tiene_registro_retributivo(int $idEmpresa): bool
{
    /**
     * Comprueba si una empresa tiene ya subido un registro retributivo.
     * Busca tanto archivos asociados a `cliente_medida` como archivos
     * directamente ligados a la empresa.
     */
    if ($idEmpresa <= 0) {
        return false;
    }

    $sql = 'SELECT 1 FROM archivos a
        INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
        INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
        WHERE UPPER(TRIM(a.tipo)) IN ("REGISTRO_RETRIBUTIVO", "REGISTRO_PROPIO_CLIENTE") AND ac.id_empresa = ?
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

    $sqlDirecto = 'SELECT 1 FROM archivos a WHERE UPPER(TRIM(a.tipo)) IN ("REGISTRO_RETRIBUTIVO", "REGISTRO_PROPIO_CLIENTE") AND a.id_empresa = ? LIMIT 1';

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

function empresa_tiene_datos_cuantitativos(int $idEmpresa): bool
{
    /**
     * Comprueba si la empresa tiene al menos un registro en tablas de datos
     * cuantitativos (bajas, formaciones, excedencias, permisos).
     */
    if ($idEmpresa <= 0) return false;
    $db = db();
    $sql = "SELECT 1 FROM bajas WHERE id_empresa = ? UNION SELECT 1 FROM area_formaciones WHERE id_empresa = ? UNION SELECT 1 FROM area_excedencias WHERE id_empresa = ? UNION SELECT 1 FROM area_Permisos_retribuidos WHERE id_empresa = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('iiii', $idEmpresa, $idEmpresa, $idEmpresa, $idEmpresa);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function empresa_tiene_cuestionario_cualitativo(int $idEmpresa): bool
{
    /**
     * Comprueba existencia de al menos un registro en los cuestionarios
     * cualitativos más relevantes usados en la UI.
     */
    if ($idEmpresa <= 0) return false;
    $db = db();
    $sql = "SELECT 1 FROM cuestionario_seleccion_personal WHERE id_empresa = ? UNION SELECT 1 FROM cuestionario_promocion_profesional WHERE id_empresa = ? UNION SELECT 1 FROM cuestionario_formacion WHERE id_empresa = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('iii', $idEmpresa, $idEmpresa, $idEmpresa);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function empresa_tiene_documentacion(int $idEmpresa): bool
{
    /**
     * Comprueba si la empresa tiene documentación cargada con tipo 'DOCUMENTACION'.
     */
    if ($idEmpresa <= 0) return false;
    $db = db();
    $sql = "SELECT 1 FROM archivos WHERE id_empresa = ? AND UPPER(TRIM(tipo)) = 'DOCUMENTACION' LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

$view = (string)($_GET['view'] ?? 'empresas');
// $view: determina qué sección mostrar. Valores: menu, mi_espacio, privada,
// perfil, reuniones, empresas, mantenimiento, plan.
if (!in_array($view, ['menu', 'mi_espacio', 'privada', 'perfil', 'reuniones', 'empresas', 'mantenimiento', 'plan'], true)) {
    $view = 'empresas';
}

$msg = (string)($_GET['msg'] ?? '');
$sessionUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');
$rol = strtoupper((string)($_SESSION['user']['rol'] ?? 'CLIENTE'));
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$idEmpresaSeleccionada = (int)($_GET['id_empresa'] ?? 0);
$empresasDisponibles = [];
$empresaAsignada = null;
$clientePerfil = null;
$clienteReuniones = [];
$proximaReunion = null;
$clienteTecnicosEmpresa = [];

if ($usuarioId > 0) {
    $stmtEmpresas = db()->prepare(
        'SELECT t.id_empresa, t.razon_social, GROUP_CONCAT(DISTINCT ce.tipo_contrato SEPARATOR ", ") AS tipo_contrato
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
         LEFT JOIN contrato_empresa ce ON ce.id_empresa = t.id_empresa 
            AND STR_TO_DATE(CONCAT(ce.fin_contratacion, " 23:59:59"), "%Y-%m-%d %H:%i:%s") >= NOW()
         GROUP BY t.id_empresa, t.razon_social
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
                'tipo_contrato' => trim((string)($rowEmpresa['tipo_contrato'] ?? '')),
            ];
        }
        $stmtEmpresas->close();
    }

    // Cargar perfil del usuario (cliente) para mostrar/editarlos en la vista 'perfil'

    $stmtPerfil = db()->prepare(
        'SELECT id_usuario, nombre_usuario, apellidos, email, telefono, direccion, localidad
         FROM usuario
         WHERE id_usuario = ?
         LIMIT 1'
    );
    if ($stmtPerfil) {
        $stmtPerfil->bind_param('i', $usuarioId);
        $stmtPerfil->execute();
        $clientePerfil = $stmtPerfil->get_result()->fetch_assoc();
        $stmtPerfil->close();
    }

    correo_enviar_recordatorio_rr_reuniones_vencidas(db());
    db()->query("DELETE FROM reuniones WHERE STR_TO_DATE(CONCAT(fecha_reunion, ' ', hora_reunion), '%Y-%m-%d %H:%i') <= NOW()");
    $stmtReuniones = db()->prepare(
        'SELECT r.id_reunion, r.objetivo, r.hora_reunion, r.fecha_reunion, r.id_empresa, er.razon_social AS empresa_reunion
         FROM reuniones r
         LEFT JOIN empresa er ON er.id_empresa = r.id_empresa
         INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
         WHERE ur.id_usuario = ?
         ORDER BY r.fecha_reunion ASC, r.hora_reunion ASC, r.id_reunion ASC'
    );
    if ($stmtReuniones) {
        $stmtReuniones->bind_param('i', $usuarioId);
        $stmtReuniones->execute();
        $resReuniones = $stmtReuniones->get_result();
        while ($rowReunion = $resReuniones->fetch_assoc()) {
            $clienteReuniones[] = $rowReunion;
        }
        $stmtReuniones->close();
    }

    if (in_array($view, ['privada', 'reuniones'], true)) {

        $tecnicosEmpresaMap = [];

        $stmtTecnicosEmpresa = db()->prepare(
            'SELECT DISTINCT ue.id_empresa, e.razon_social, u.id_usuario, u.nombre_usuario, u.apellidos
             FROM usuario_empresa ue
             INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
             INNER JOIN usuario u ON u.id_usuario = ue.id_usuario
             INNER JOIN rol r ON r.id = u.rol_id
             WHERE UPPER(TRIM(r.nombre)) LIKE "PERSONAL TECNICO%"
               AND ue.id_empresa IN (
                   SELECT t.id_empresa
                   FROM (
                       SELECT ue2.id_empresa
                       FROM usuario_empresa ue2
                       WHERE ue2.id_usuario = ?

                       UNION

                       SELECT e2.id_empresa
                       FROM empresa e2
                       WHERE e2.id_usuario = ?
                   ) t
               )
             ORDER BY e.razon_social ASC, u.nombre_usuario ASC, u.apellidos ASC'
        );
        if ($stmtTecnicosEmpresa) {
            $stmtTecnicosEmpresa->bind_param('ii', $usuarioId, $usuarioId);
            $stmtTecnicosEmpresa->execute();
            $resTecnicosEmpresa = $stmtTecnicosEmpresa->get_result();
            while ($rowTecnicoEmpresa = $resTecnicosEmpresa->fetch_assoc()) {
                $key = (int)($rowTecnicoEmpresa['id_empresa'] ?? 0) . ':' . (int)($rowTecnicoEmpresa['id_usuario'] ?? 0);
                $tecnicosEmpresaMap[$key] = [
                    'id_empresa' => (int)($rowTecnicoEmpresa['id_empresa'] ?? 0),
                    'razon_social' => trim((string)($rowTecnicoEmpresa['razon_social'] ?? '')),
                    'id_usuario' => (int)($rowTecnicoEmpresa['id_usuario'] ?? 0),
                    'nombre_usuario' => trim((string)($rowTecnicoEmpresa['nombre_usuario'] ?? '')),
                    'apellidos' => trim((string)($rowTecnicoEmpresa['apellidos'] ?? '')),
                ];
            }
            $stmtTecnicosEmpresa->close();
        }

        $stmtTecnicosPropietarios = db()->prepare(
            'SELECT DISTINCT e.id_empresa, e.razon_social, u.id_usuario, u.nombre_usuario, u.apellidos
             FROM empresa e
             INNER JOIN usuario u ON u.id_usuario = e.id_usuario
             INNER JOIN rol r ON r.id = u.rol_id
             WHERE UPPER(TRIM(r.nombre)) LIKE "PERSONAL TECNICO%"
               AND e.id_empresa IN (
                   SELECT t.id_empresa
                   FROM (
                       SELECT ue2.id_empresa
                       FROM usuario_empresa ue2
                       WHERE ue2.id_usuario = ?

                       UNION

                       SELECT e2.id_empresa
                       FROM empresa e2
                       WHERE e2.id_usuario = ?
                   ) t
               )
             ORDER BY e.razon_social ASC, u.nombre_usuario ASC, u.apellidos ASC'
        );
        if ($stmtTecnicosPropietarios) {
            $stmtTecnicosPropietarios->bind_param('ii', $usuarioId, $usuarioId);
            $stmtTecnicosPropietarios->execute();
            $resTecnicosPropietarios = $stmtTecnicosPropietarios->get_result();
            while ($rowTecnicoPropietario = $resTecnicosPropietarios->fetch_assoc()) {
                $key = (int)($rowTecnicoPropietario['id_empresa'] ?? 0) . ':' . (int)($rowTecnicoPropietario['id_usuario'] ?? 0);
                $tecnicosEmpresaMap[$key] = [
                    'id_empresa' => (int)($rowTecnicoPropietario['id_empresa'] ?? 0),
                    'razon_social' => trim((string)($rowTecnicoPropietario['razon_social'] ?? '')),
                    'id_usuario' => (int)($rowTecnicoPropietario['id_usuario'] ?? 0),
                    'nombre_usuario' => trim((string)($rowTecnicoPropietario['nombre_usuario'] ?? '')),
                    'apellidos' => trim((string)($rowTecnicoPropietario['apellidos'] ?? '')),
                ];
            }
            $stmtTecnicosPropietarios->close();
        }

        $clienteTecnicosEmpresa = array_values($tecnicosEmpresaMap);
    }

    $stmtProximaReunion = db()->prepare(
        'SELECT r.id_reunion, r.objetivo, r.hora_reunion, r.fecha_reunion
                         FROM reuniones r
                         INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
                         WHERE ur.id_usuario = ?
                             AND r.tipo = "FechaLimite"
                             AND STR_TO_DATE(CONCAT(r.fecha_reunion, " ", r.hora_reunion), "%Y-%m-%d %H:%i") >= NOW()
                         ORDER BY r.fecha_reunion ASC, r.hora_reunion ASC, r.id_reunion ASC
                         LIMIT 1'
    );
    if ($stmtProximaReunion) {
        $stmtProximaReunion->bind_param('i', $usuarioId);
        $stmtProximaReunion->execute();
        $proximaReunion = $stmtProximaReunion->get_result()->fetch_assoc() ?: null;
        $stmtProximaReunion->close();
    }
}

if (!empty($empresasDisponibles)) {
    $empresaAsignada = null;

    foreach (($empresasDisponibles ?? []) as $empresaDisponible) {
        $idEmpresaDisponible = (int)($empresaDisponible['id_empresa'] ?? 0);
        if ($idEmpresaDisponible > 0 && empresa_tiene_registro_retributivo($idEmpresaDisponible)) {
            $empresaAsignada = $empresaDisponible;
            break;
        }
    }

    if ($empresaAsignada === null) {
        $empresaAsignada = $empresasDisponibles[0];
    }

    if ($idEmpresaSeleccionada > 0) {
        foreach (($empresasDisponibles ?? []) as $empresaDisponible) {
            if ((int)($empresaDisponible['id_empresa'] ?? 0) === $idEmpresaSeleccionada) {
                $empresaAsignada = $empresaDisponible;
                break;
            }
        }
    }
}

$sinEmpresaAsignada = ($empresaAsignada === null);
$idEmpresaAsignada = (int)($empresaAsignada['id_empresa'] ?? 0);

$empresaTipoContrato = strtoupper(trim((string)($empresaAsignada['tipo_contrato'] ?? '')));
$empresaTienePlan = ($empresaTipoContrato !== '' && strpos($empresaTipoContrato, 'PLAN IGUALDAD') !== false);
$empresaTieneMantenimiento = ($empresaTipoContrato !== '' && strpos($empresaTipoContrato, 'MANTENIMIENTO') !== false);

// Contar mantenimientos en curso para la empresa asignada
$mantenimientosEnCursoCount = 0;
if ($idEmpresaAsignada > 0) {
    $stmtMant = db()->prepare("SELECT COUNT(1) AS c FROM contrato_empresa ce WHERE ce.id_empresa = ? AND UPPER(TRIM(ce.tipo_contrato)) = 'MANTENIMIENTO' AND STR_TO_DATE(CONCAT(ce.fin_contratacion, ' 23:59:59'), '%Y-%m-%d %H:%i:%s') >= NOW()");
    if ($stmtMant) {
        $stmtMant->bind_param('i', $idEmpresaAsignada);
        $stmtMant->execute();
        $row = $stmtMant->get_result()->fetch_assoc();
        $mantenimientosEnCursoCount = (int)($row['c'] ?? 0);
        $stmtMant->close();
    }
}

$registroSubido = (!$sinEmpresaAsignada && empresa_tiene_registro_retributivo($idEmpresaAsignada));
$datosCuantitativosSubidos = (!$sinEmpresaAsignada && empresa_tiene_datos_cuantitativos($idEmpresaAsignada));
$datosCualitativosSubidos = (!$sinEmpresaAsignada && empresa_tiene_cuestionario_cualitativo($idEmpresaAsignada));
$documentacionSubida = (!$sinEmpresaAsignada && empresa_tiene_documentacion($idEmpresaAsignada));

// Lógica de desbloqueo secuencial (Gamificación)
$paso1Completado = $registroSubido;

// Paso 2 (Datos) se desbloquea al completar el Paso 1
// Paso 3 (Documentación) se desbloquea si el Paso 2 está completado (cuantitativo, cualitativo o documentación rápida)
$paso2Desbloqueado = $paso1Completado;
$paso2Completado = ($datosCuantitativosSubidos || $datosCualitativosSubidos || $documentacionSubida);

$paso3Desbloqueado = $paso2Completado;
$paso3Completado = false; // Ver documentación es informativo/enlace, no tiene "completado" per se, pero podemos marcarlo si ha visitado? No, dejemoslo como enlace.

$pendientesEspacio = 0;
$empresasPendientesLista = [];
if (!empty($empresasDisponibles)) {
    foreach (($empresasDisponibles ?? []) as $empresa) {
        $idEmp = (int)($empresa['id_empresa'] ?? 0);
        if ($idEmp > 0 && !empresa_tiene_registro_retributivo($idEmp)) {
            $pendientesEspacio++;
            $empresasPendientesLista[] = $empresa['razon_social'] ?? 'Sin nombre';
        }
    }
}
$mantenimientosTotalesCount = 0;
if (!empty($empresasDisponibles)) {
    foreach (($empresasDisponibles ?? []) as $empItem) {
        $tipo = strtoupper(trim((string)($empItem['tipo_contrato'] ?? '')));
        if ($tipo !== '' && strpos($tipo, 'MANTENIMIENTO') !== false) {
            $mantenimientosTotalesCount++;
        }
    }
}

$globalCssVersion = @filemtime(__DIR__ . '/../css/global.css') ?: time();
$adminCssVersion = @filemtime(__DIR__ . '/../css/admin.css') ?: time();
$clienteCssVersion = @filemtime(__DIR__ . '/../css/cliente.css') ?: time();
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Area Retributiva</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../css/global.css?v=<?= (int)$globalCssVersion ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?= (int)$adminCssVersion ?>">
    <link rel="stylesheet" href="../css/cliente.css?v=<?= (int)$clienteCssVersion ?>">
</head>

<body class="bg-light cliente-page">
    <?php $view = $view ?? 'mi_espacio'; ?>
    <div class="container-fluid py-4">
        <div class="row g-3 align-items-start">

            <!-- SIDEBAR -->
            <!-- SIDEBAR -->
            <!-- Fragmento: barra lateral con navegación del usuario (perfil, empresas, logout) -->
            <?php include __DIR__ . '/../php/fragments/sidebar.php'; ?>

            <main class="col-12 col-md-9 col-xl-10">
                <div class="card panel <?= in_array($view, ['menu', 'mi_espacio', 'reuniones', 'plan'], true) ? 'panel-wide' : '' ?> shadow-sm border-0">
                    <div class="card-body p-4">
                        <!-- HEADER DENTRO DE LA TARJETA -->
                        <div class="mb-4">
                            <?php if (in_array($view, ['privada', 'perfil', 'reuniones'], true)): ?>
                                <h2 class="fw-bold mb-1">Panel de Cliente</h2>
                                <p class="text-muted small mb-0">Gestión de área privada y reuniones</p>
                            <?php elseif ($view === 'mi_espacio'): ?>
                                <h2 class="fw-bold mb-1">Mi Espacio</h2>
                                <p class="text-muted small mb-0">Resumen de actividad y estado de tareas</p>
                            <?php else: ?>
                                <h2 class="fw-bold mb-1">Mi Espacio</h2>
                                <p class="text-muted small mb-0">Acceso rápido a tus gestiones</p>
                            <?php endif; ?>
                            <hr class="mt-3 mb-0 opacity-10">
                            <?php
                            $empresaParamNav = ((int)($idEmpresaAsignada ?? 0) > 0) ? '&id_empresa=' . (int)$idEmpresaAsignada : '';
                            // If a company is selected, show prominent switches between Plan and Mantenimiento
                            if ((int)($idEmpresaAsignada ?? 0) > 0 && in_array($view, ['mi_espacio', 'plan', 'mantenimiento'], true)): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3 mb-0">
                                    <div></div>
                                    <div class="text-center">
                                        <a class="btn <?= ($view === 'plan') ? 'btn-primary' : 'btn-outline-primary' ?> btn-lg px-4" href="index_cliente.php?view=plan<?= h($empresaParamNav) ?>">Plan de Igualdad</a>
                                        <a class="btn <?= ($view === 'mantenimiento') ? 'btn-dark' : 'btn-outline-dark' ?> btn-lg px-4" href="index_cliente.php?view=mantenimiento<?= h($empresaParamNav) ?>">Mantenimiento</a>
                                    </div>
                                    <div class="text-end">
                                        <a href="index_cliente.php?view=empresas<?= h($empresaParamNav) ?>" class="btn btn-outline-secondary btn-sm">Volver a empresas</a>
                                    </div>
                                </div>
                            <?php elseif ($view !== 'mi_espacio' && $view !== 'empresas'): ?>
                                <?php $volverHref = 'index_cliente.php?view=mi_espacio' . $empresaParamNav; ?>
                                <div class="d-flex justify-content-end mt-2">
                                    <a href="<?= h($volverHref) ?>" class="btn btn-outline-secondary btn-sm">Volver</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($msg !== ''): ?>
                            <div class="alert alert-info py-2"><?= h($msg) ?></div>
                        <?php endif; ?>

                        <?php if ($view === 'empresas'): ?>
                            <div class="text-center mb-5">
                                <h3 class="fw-bold">Mis Empresas</h3>
                                <p class="text-muted">Selecciona una empresa para gestionar su registro y documentación</p>
                            </div>

                            <div class="row g-3 mb-4 justify-content-center">
                                <div class="col-12 col-md-5 col-lg-4">
                                    <div class="space-stat-card h-100">
                                        <div class="space-stat-label">Empresas con Registro retributivo pendiente de subida</div>
                                        <div class="space-stat-value"><?= (int)$pendientesEspacio ?></div>
                                        <div class="space-stat-icon" <?= $pendientesEspacio > 0 ? 'style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#modalEmpresasPendientes" title="Ver empresas pendientes"' : '' ?>>▣</div>
                                        <?php if ($pendientesEspacio > 0): ?>
                                            <div class="mt-2 text-end">
                                                <button class="btn btn-sm btn-link text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#modalEmpresasPendientes">Ver detalles</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-12 col-md-5 col-lg-4">
                                    <div class="space-stat-card h-100">
                                        <div class="space-stat-label">Mantenimientos en curso</div>
                                        <div class="space-stat-value"><?= (int)$mantenimientosTotalesCount ?></div>
                                        <div class="space-stat-icon">🔧</div>
                                        <?php if ($mantenimientosTotalesCount > 0): ?>
                                            <div class="mt-2 text-end">
                                                <a href="index_cliente.php?view=mantenimiento" class="btn btn-sm btn-link p-0">Ver mantenimientos</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 justify-content-center">
                                <?php if (empty($empresasDisponibles)): ?>
                                    <div class="col-12 text-center">
                                        <div class="alert alert-warning">No tienes empresas asignadas aún.</div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($empresasDisponibles as $emp): ?>
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <a href="index_cliente.php?view=mi_espacio&id_empresa=<?= (int)$emp['id_empresa'] ?>" class="text-decoration-none">
                                                <div class="card h-100 border-0 shadow-sm company-card" style="transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border-radius: 12px;">
                                                    <div class="card-body p-4 text-center">
                                                        <div class="mb-3" style="font-size: 3rem;">🏢</div>
                                                        <h5 class="fw-bold text-dark mb-2"><?= h($emp['razon_social']) ?></h5>
                                                        <?php if (!empty($emp['tipo_contrato'])): ?>
                                                            <p class="text-muted small mb-0">📋 <?= h($emp['tipo_contrato']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <style>
                                .company-card:hover {
                                    transform: translateY(-5px);
                                    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
                                }
                            </style>
                        <?php elseif ($view === 'privada'): ?>
                            <div class="alert alert-light border mb-0">
                                Selecciona una opcion de Area Privada: <strong>Mi cuenta</strong> o <strong>Mis reuniones</strong>.
                            </div>
                            //Mi perfil cliente
                        <?php elseif ($view === 'perfil'): ?>
                            <div class="d-flex justify-content-center">
                                <div class="card shadow-sm border-0" style="max-width: 520px; width: 100%;">
                                    <div class="card-body p-4">
                                        <h3 class="text-center mb-4">Mi cuenta</h3>

                                        <?php if (!empty($clientePerfil)): ?>
                                            <form method="post" action="<?= h(app_path('/controller/cliente_controller.php')) ?>" class="vstack gap-3">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="accion" value="editar_perfil">
                                                <input type="hidden" name="id" value="<?= (int)($clientePerfil['id_usuario'] ?? 0) ?>">

                                                <input class="form-control" name="nombre_usuario"
                                                    value="<?= h($clientePerfil['nombre_usuario'] ?? '') ?>" placeholder="Nombre" required>

                                                <input class="form-control" name="apellidos"
                                                    value="<?= h($clientePerfil['apellidos'] ?? '') ?>" placeholder="Apellidos">

                                                <input class="form-control" name="email" type="email"
                                                    value="<?= h($clientePerfil['email'] ?? '') ?>" placeholder="Email" required>

                                                <input class="form-control" name="telefono"
                                                    value="<?= h($clientePerfil['telefono'] ?? '') ?>" placeholder="Teléfono">

                                                <input class="form-control" name="direccion"
                                                    value="<?= h($clientePerfil['direccion'] ?? '') ?>" placeholder="Dirección">

                                                <input class="form-control" name="localidad"
                                                    value="<?= h($clientePerfil['localidad'] ?? '') ?>" placeholder="Localidad">

                                                <div class="input-group">
                                                    <input id="clientePerfilPassword" class="form-control" name="password" type="password" placeholder="" autocomplete="new-password" minlength="6">
                                                    <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="clientePerfilPassword" aria-label="Mostrar contraseña">Mostrar</button>
                                                </div>

                                                <div class="d-flex justify-content-center pt-2">
                                                    <button class="btn btn-dark px-5" type="submit">Actualizar</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($view === 'reuniones'): ?>
                            <?php
                            $clienteCalendarEvents = [];
                            foreach (($clienteReuniones ?? []) as $reunion) {
                                $idReunion = (int)($reunion['id_reunion'] ?? 0);
                                $objetivoReunion = trim((string)($reunion['objetivo'] ?? ''));
                                $fechaReunion = (string)($reunion['fecha_reunion'] ?? '');
                                $horaReunionRaw = (string)($reunion['hora_reunion'] ?? '');
                                $horaReunion = date('H:i', strtotime($horaReunionRaw));
                                $titulo = ($objetivoReunion !== '' ? $objetivoReunion : 'Reunion');
                                $clienteCalendarEvents[] = [
                                    'id' => (string)$idReunion,
                                    'title' => $titulo,
                                    'start' => $fechaReunion . 'T' . $horaReunion,
                                    'allDay' => false,
                                    'extendedProps' => [
                                        'objetivo' => $objetivoReunion,
                                        'fecha' => $fechaReunion,
                                        'hora' => $horaReunion,
                                    ],
                                ];
                            }
                            ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">📅 Mis Reuniones</h6>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <h6 class="mb-3">Crear Nueva Reunión</h6>
                                    <form method="post" action="<?= h(app_path('/controller/cliente_controller.php')) ?>" class="row g-2 align-items-end">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="accion" value="crear_reunion">
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">🏢 Empresa</label>
                                            <select class="form-select" id="clienteSelectEmpresaReunion" name="id_empresa_reunion" required>
                                                <option value="0">Selecciona una empresa</option>
                                                <?php foreach (($empresasDisponibles ?? []) as $empresaReunion): ?>
                                                    <option value="<?= (int)($empresaReunion['id_empresa'] ?? 0) ?>">
                                                        <?= h((string)($empresaReunion['razon_social'] ?? '')) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">👤 Personal Técnico</label>
                                            <select class="form-select" id="clienteSelectTecnicoReunion" name="id_tecnico_reunion" disabled>
                                                <option value="0">Sin asignar personal técnico</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <label class="form-label">📅 Fecha de la Reunión</label>
                                            <input class="form-control" type="date" name="fecha_reunion" required>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <label class="form-label">🕐 Hora</label>
                                            <input class="form-control" type="time" name="hora_reunion" required>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <label class="form-label">📝 Asunto</label>
                                            <input class="form-control" type="text" name="objetivo" maxlength="1000" placeholder="Asunto de la reunión">
                                        </div>
                                        <div class="col-12 d-flex justify-content-end">
                                            <button class="btn btn-primary" type="submit">Agregar Reunión</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div id="clienteReunionesCalendar" class="border rounded p-2 bg-white cliente-reuniones-calendar"></div>

                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-body p-4">
                                    <h4 class="citas-title mb-3">📅 Todas Tus Reuniones</h4>
                                    <?php if (empty($clienteReuniones)): ?>
                                        <div class="alert alert-light border mb-0">El calendario se muestra aunque no tengas reuniones asignadas.</div>
                                    <?php else: ?>
                                        <div class="citas-list d-grid gap-3">
                                            <?php foreach (($clienteReuniones ?? []) as $reunionLista): ?>
                                                <?php
                                                $idReunionLista = (int)($reunionLista['id_reunion'] ?? 0);
                                                $objetivoLista = trim((string)($reunionLista['objetivo'] ?? ''));
                                                $fechaListaRaw = (string)($reunionLista['fecha_reunion'] ?? '');
                                                $horaListaRaw = (string)($reunionLista['hora_reunion'] ?? '');
                                                $horaLista = substr($horaListaRaw, 0, 5);
                                                $resumenFecha = trim($fechaListaRaw . ' · ' . $horaLista, " ·");
                                                ?>
                                                <div class="cita-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div class="me-auto">
                                                        <div class="cita-item-title">📄 <?= h($objetivoLista !== '' ? $objetivoLista : 'Reunión') ?></div>
                                                        <div class="cita-item-subtitle">Cita programada</div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                                                        <span class="cita-pill">Reunión</span>
                                                        <span class="cita-pill"><?= h($resumenFecha !== '' ? $resumenFecha : 'Sin fecha') ?></span>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form method="post" action="<?= h(app_path('/controller/cliente_controller.php')) ?>" class="mt-2 row g-2 align-items-end" style="min-width: 320px;">
                                                                <?= csrf_input() ?>
                                                                <input type="hidden" name="accion" value="editar_reunion">
                                                                <input type="hidden" name="id_reunion" value="<?= $idReunionLista ?>">
                                                                <div class="col-12 col-md-4">
                                                                    <label class="form-label mb-1">Fecha</label>
                                                                    <input class="form-control form-control-sm" type="date" name="fecha_reunion" value="<?= h($fechaListaRaw) ?>" required>
                                                                </div>
                                                                <div class="col-12 col-md-3">
                                                                    <label class="form-label mb-1">Hora</label>
                                                                    <input class="form-control form-control-sm" type="time" name="hora_reunion" value="<?= h($horaLista) ?>" required>
                                                                </div>
                                                                <div class="col-12 col-md-5">
                                                                    <label class="form-label mb-1">Asunto</label>
                                                                    <input class="form-control form-control-sm" type="text" name="objetivo" maxlength="1000" value="<?= h($objetivoLista) ?>" placeholder="Asunto (opcional)">
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end">
                                                                    <button class="btn btn-success btn-sm" type="submit">Guardar</button>
                                                                </div>
                                                            </form>
                                                        </details>
                                                        <form method="post" action="<?= h(app_path('/controller/cliente_controller.php')) ?>" onsubmit="return confirm('¿Eliminar esta reunión?');">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="accion" value="eliminar_reunion">
                                                            <input type="hidden" name="id_reunion" value="<?= $idReunionLista ?>">
                                                            <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="modal fade" id="clienteReunionDetalleModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">📅 Detalle de Reunión</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div><strong>Fecha:</strong> <span id="clienteDetalleFecha"></span></div>
                                            <div><strong>Hora:</strong> <span id="clienteDetalleHora"></span></div>
                                            <div class="mt-2"><strong>Asunto:</strong></div>
                                            <div id="clienteDetalleObjetivo" class="text-muted"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($view === 'plan'): ?>
                            <div class="space-shell mb-8">
                                <div class="text-center mb-5">
                                    <div class="space-kicker">PLAN DE IGUALDAD</div>
                                    <h3 class="mb-1 fw-bold">Qué tengo que hacer ahora</h3>
                                    <div class="text-muted small">Guía paso a paso para ir completando la info que necesitamos para tu Plan de Igualdad.</div>
                                </div>
                                <div class="d-flex justify-content-center mb-4">
                                    <div class="card border-0 shadow-sm" style="max-width:540px; width:100%;">
                                        <div class="card-body text-center py-3">
                                            <div class="small text-muted">🔔Fecha Límite Subida Registro Retributivo</div>
                                            <?php if (!empty($proximaReunion)): ?>
                                                <?php
                                                $fechaRes = formatear_fecha_resumen((string)($proximaReunion['fecha_reunion'] ?? ''));
                                                $horaRes = substr((string)($proximaReunion['hora_reunion'] ?? ''), 0, 5);
                                                ?>
                                                <h5 class="fw-bold mb-1"><?= h($proximaReunion['objetivo'] ?: 'Reunión fecha límite') ?></h5>
                                                <div class="text-muted"><?= h($fechaRes) ?><?= $horaRes !== '' ? ' · ' . h($horaRes) : '' ?></div>
                                            <?php else: ?>
                                                <div class="text-muted">Ya has subido el registro retributivo.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-12 col-lg-10 col-xl-8 mx-auto">
                                        <div class="space-panel h-100">
                                            <div class="space-task-list d-grid gap-3">
                                                <p>Lo primero que tenemos que hacer es subir el registro retributivo si ya disponen de él, en caso contrario descargar la plantilla y completarla, sigue los pasos indicados.</p>
                                                <!-- Paso 1: Registro Retributivo -->
                                                <a href="index_cliente.php?view=menu&id_empresa=<?= (int)$idEmpresaAsignada ?>" class="space-task-item text-decoration-none text-dark d-block border <?= $paso1Completado ? 'border-success bg-light' : 'border-primary' ?> p-3 rounded" style="cursor: pointer; transition: background-color 0.2s;">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong>Paso 1:</strong> Subir registro retributivo o descargar plantilla.
                                                        </div>
                                                        <span class="fs-4"><?= $paso1Completado ? '✅' : '❌' ?></span>
                                                    </div>
                                                </a>

                                                <!-- Paso 2: Datos Cuantitativos -->
                                                <?php if ($paso2Desbloqueado): ?>
                                                    <button type="button" class="btn-open-paso2 space-task-item text-decoration-none text-dark d-block border <?= $paso2Completado ? 'border-success bg-light' : 'border-primary' ?> p-3 rounded" style="cursor: pointer; transition: background-color 0.2s; background: none; border: inherit; padding: inherit; width: 100%; text-align: left;">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>Paso 2:</strong> Completar datos cuantitativos y cuestionarios cualitativos del formulario.
                                                            </div>
                                                            <span class="fs-4"><?= $paso2Completado ? '✅' : '❌' ?></span>
                                                        </div>
                                                    </button>
                                                <?php else: ?>
                                                    <div class="space-task-item text-decoration-none text-muted d-block border border-secondary p-3 rounded bg-light" style="cursor: not-allowed; opacity: 0.7;">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>Paso 2:</strong> Completar datos cuantitativos y cuestionarios cualitativos del formulario.
                                                                <div class="small mt-1">Bloqueado. Completa el Paso 1.</div>
                                                            </div>
                                                            <span class="fs-4">🔒</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Paso 3: Ver Documentación -->
                                                <?php if ($paso3Desbloqueado): ?>
                                                    <a href="<?= h(app_path('/php/ver_archivos.php?id_empresa=' . $idEmpresaAsignada)) ?>" class="space-task-item text-decoration-none text-dark d-block border border-primary p-3 rounded" style="cursor: pointer; transition: background-color 0.2s;">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>Paso 3:</strong> Ver Documentación.
                                                            </div>
                                                            <span class="fs-4">📂</span>
                                                        </div>
                                                    </a>
                                                <?php else: ?>
                                                    <div class="space-task-item text-decoration-none text-muted d-block border border-secondary p-3 rounded bg-light" style="cursor: not-allowed; opacity: 0.7;">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>Paso 3:</strong> Ver Documentación.
                                                                <div class="small mt-1">Bloqueado. Sube al menos datos cuantitativos o cualitativos en el Paso 2.</div>
                                                            </div>
                                                            <span class="fs-4">🔒</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($view === 'mi_espacio'): ?>
                            <div class="space-shell mb-8">
                                <div class="text-center mb-5">
                                    <div class="space-kicker">MI ESPACIO</div>
                                    <h3 class="mb-1 fw-bold">Acceso rápido a tus gestiones</h3>
                                    <div class="text-muted">Selecciona una opción para continuar.</div>
                                </div>
                            </div>
                        <?php elseif ($view === 'mantenimiento'): ?>
                            <div class="space-shell mb-8">
                                <div class="text-center mb-4">
                                    <div class="space-kicker">MANTENIMIENTO</div>
                                    <h3 class="mb-1 fw-bold">Mantenimiento - <?= h($empresaAsignada['razon_social'] ?? '') ?></h3>
                                    <div class="text-muted">Área de mantenimiento para la empresa seleccionada. (En desarrollo)</div>
                                </div>

                                <div class="row g-3 justify-content-center">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm p-0">
                                            <!-- Iframe con demo estático de mantenimiento (sin funcionalidades) -->
                                            <iframe src="mantenimiento_demo.php?id_empresa=<?= (int)$idEmpresaAsignada ?>" title="Mantenimiento demo" style="width:100%;height:760px;border:0;border-radius:8px;display:block;"></iframe>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="registro-shell">
                                <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-4">
                                    <div>
                                        <div class="space-kicker">Registro retributivo</div>
                                        <h3 class="mb-1">Subir Documento del Registro Retributivo</h3>
                                        <div class="text-muted">Si ya tiene el formato del ministerio subalo. Si no, se descargue la plantilla del ministerio y rellenala, si le parece muy complicado puede preguntar o descargar Toma de datos</div>
                                    </div>
                                </div>

                                <form action="../php/procesar_registro_retributivo.php" method="POST" enctype="multipart/form-data">
                                    <?= csrf_input() ?>
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-7">
                                            <div class="upload-action-card h-100">
                                                <div class="upload-action-icon">⇪</div>
                                                <h5 class="mb-1">Subir registro retributivo</h5>
                                                <div class="upload-action-text mb-3">Completa los datos mínimos y envía el archivo del registro.</div>
                                                <!-- Formulario de subida: permite múltiples tipos de archivo y acepta plantillas ministerio o registros propios -->

                                                <?php if (!$sinEmpresaAsignada): ?>
                                                    <div class="mb-3">
                                                        <label for="menuEmpresaSelect" class="form-label">Empresa / Referencia</label>
                                                        <select id="menuEmpresaSelect" class="form-select" required>
                                                            <?php foreach (($empresasDisponibles ?? []) as $emp): ?>
                                                                <option value="<?= (int)($emp['id_empresa'] ?? 0) ?>" <?= ((int)($emp['id_empresa'] ?? 0) === $idEmpresaAsignada) ? 'selected' : '' ?>>
                                                                    <?= h((string)($emp['razon_social'] ?? '')) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="hidden" name="id_empresa" value="<?= (int)($idEmpresaAsignada ?? 0) ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mb-3">
                                                        <label for="nombre_empresa_cliente" class="form-label">Empresa / Referencia</label>
                                                        <input
                                                            id="nombre_empresa_cliente"
                                                            type="text"
                                                            class="form-control"
                                                            value="Sin empresa asignada"
                                                            readonly>
                                                    </div>
                                                    <div class="alert alert-warning mb-3">
                                                        <strong>⚠️ No tienes empresas asignadas.</strong><br>
                                                        Contacta con el administrador para que te asigne empresas antes de subir documentos.
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mb-3">
                                                    <label for="Asunto" class="form-label">Observaciones (Opcional)</label>
                                                    <input type="text" id="Asunto" name="asunto" class="form-control">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="modoRegistroCliente" class="form-label">Origen del registro</label>
                                                    <select
                                                        id="modoRegistroCliente"
                                                        name="modo_registro"
                                                        class="form-select"
                                                        <?= $sinEmpresaAsignada ? 'disabled' : '' ?>
                                                        required>
                                                        <option value="HERRAMIENTA" selected>Herramienta de Registro Retributivo Del Ministerio</option>
                                                        <option value="PROPIO">Tu propio registro retributivo</option>
                                                    </select>
                                                </div>

                                                <input type="hidden" id="tipoRegistroCliente" name="tipo" value="REGISTRO_RETRIBUTIVO">

                                                <div class="mb-3">
                                                    <label for="archivoRegistro" class="form-label">Archivo</label>
                                                    <input
                                                        id="archivoRegistro"
                                                        type="file"
                                                        name="excel[]"
                                                        class="form-control"
                                                        accept=".docx,.doc,.pdf,.xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                                                        multiple
                                                        <?= $sinEmpresaAsignada ? 'disabled' : '' ?>
                                                        required>
                                                </div>

                                                <button class="btn btn-dark px-4" type="submit" <?= $sinEmpresaAsignada ? 'disabled' : '' ?>>Subir archivo</button>
                                            </div>
                                        </div>

                                        <div class="col-12 col-xl-5">
                                            <div class="upload-action-card h-100">
                                                <div class="upload-action-icon">▣</div>
                                                <h5 class="mb-1">Descargar plantilla del Registro Retributivo</h5>
                                                <div class="upload-action-text mb-3">Si no dispones del Registro Retributivo puedes descargarte la plantilla del Ministerio o la plantilla de Toma de Datos para rellenarla.</div>

                                                <div class="d-flex flex-wrap gap-2">
                                                    <a class="btn btn-outline-secondary px-4 align-self-start" href="<?= h(app_path('/php/download_archivo.php?id=1')) ?>">
                                                        Descargar plantilla Ministerio
                                                    </a>
                                                    <a class="btn btn-outline-info px-4 align-self-start" href="<?= h(app_path('/php/download_archivo.php?id=2')) ?>">
                                                        Descargar toma de datos
                                                    </a>
                                                </div>


                                                <div class="mt-4">
                                                    <label class="form-label d-block mb-2">Datos Cuantitativos / Cuestionarios Cualitativos</label>

                                                    <?php if (!$registroSubido): ?>
                                                        <div class="alert alert-warning py-2 mb-0">
                                                            Debes subir primero el Registro Retributivo para desbloquear los Datos Cuantitativos / Cuestionarios Cualitativos.
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="bajas">Ver Datos Cuantitativos / Cuestionarios Cualitativos</button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                            </div>
                            </form>
                    </div>
                <?php endif; ?>
                </div>
        </div>
        </main>
    </div>
    </div>

    <div class="modal fade" id="modalComplementoFormularios" tabindex="-1" aria-labelledby="modalComplementoFormulariosLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalComplementoFormulariosLabel">Complemento formularios</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0" style="min-height: 70vh;">
                    <iframe id="complementoFormulariosFrame" title="Formulario complemento" style="width:100%; height:70vh; border:0;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pendientesEspacio > 0): ?>
        <div class="modal fade" id="modalEmpresasPendientes" tabindex="-1" aria-labelledby="modalEmpresasPendientesLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEmpresasPendientesLabel">Empresas con Registro Pendiente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($empresasPendientesLista as $empName): ?>
                                <li class="list-group-item">📁 <?= h($empName) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>
    <script>
        // Script global: toggles (mostrar/ocultar) contraseñas y comportamientos sencillos
        (function() {
            const toggleButtons = document.querySelectorAll('[data-password-toggle]');
            if (!toggleButtons.length) {
                return;
            }

            toggleButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const targetId = button.getAttribute('data-target');
                    if (!targetId) {
                        return;
                    }

                    const input = document.getElementById(targetId);
                    if (!input) {
                        return;
                    }

                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    button.textContent = isPassword ? 'Ocultar' : 'Mostrar';
                    button.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
                });
            });
        })();
    </script>
    <?php if ($view === 'reuniones'): ?>
        <script>
            (function() {
                const calendarEl = document.getElementById('clienteReunionesCalendar');
                if (!calendarEl || typeof FullCalendar === 'undefined') {
                    return;
                }

                const events = <?= json_encode($clienteCalendarEvents ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                const detalleFecha = document.getElementById('clienteDetalleFecha');
                const detalleHora = document.getElementById('clienteDetalleHora');
                const detalleObjetivo = document.getElementById('clienteDetalleObjetivo');
                const modalEl = document.getElementById('clienteReunionDetalleModal');
                const detalleModal = (modalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalEl) : null;
                const isMobile = window.matchMedia('(max-width: 767.98px)').matches;

                const calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'es',
                    initialView: 'dayGridMonth',
                    height: isMobile ? 'auto' : 760,
                    events: events,
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    },
                    eventClick: function(info) {
                        const ev = info.event;
                        const props = ev.extendedProps || {};

                        if (detalleModal) {
                            detalleFecha.textContent = props.fecha || '-';
                            detalleHora.textContent = props.hora || '-';
                            detalleObjetivo.textContent = (props.objetivo && props.objetivo.trim() !== '') ? props.objetivo : 'Sin objetivo';
                            detalleModal.show();
                        }
                    }
                });

                calendar.render();
            })();
        </script>
        <script>
            (function() {
                const selectEmpresa = document.getElementById('clienteSelectEmpresaReunion');
                const selectTecnico = document.getElementById('clienteSelectTecnicoReunion');
                if (!selectEmpresa || !selectTecnico) {
                    return;
                }

                const tecnicos = <?= json_encode($clienteTecnicosEmpresa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

                function renderTecnicos(idEmpresa) {
                    selectTecnico.innerHTML = '';

                    const optionDefault = document.createElement('option');
                    optionDefault.value = '0';
                    optionDefault.textContent = 'Sin asignar al personal técnico';
                    selectTecnico.appendChild(optionDefault);

                    if (!idEmpresa || idEmpresa === '0') {
                        selectTecnico.disabled = true;
                        return;
                    }

                    const filtrados = tecnicos.filter((t) => String(t.id_empresa) === String(idEmpresa));
                    filtrados.forEach((t) => {
                        const option = document.createElement('option');
                        option.value = String(t.id_usuario || 0);
                        const nombre = String(t.nombre_usuario || '').trim();
                        const apellidos = String(t.apellidos || '').trim();
                        const empresa = String(t.razon_social || '').trim();
                        const nombreCompleto = (nombre + ' ' + apellidos).trim();
                        option.textContent = empresa !== '' ?
                            ((nombreCompleto !== '' ? nombreCompleto : 'Técnico') + ' - ' + empresa) :
                            (nombreCompleto !== '' ? nombreCompleto : 'Técnico');
                        selectTecnico.appendChild(option);
                    });

                    selectTecnico.disabled = false;
                }

                selectEmpresa.addEventListener('change', function() {
                    renderTecnicos(this.value);
                });

                renderTecnicos(selectEmpresa.value);
            })();
        </script>
    <?php endif; ?>

    <?php if ($view === 'menu'): ?>
        <script>
            (function() {
                const modoSelect = document.getElementById('modoRegistroCliente');
                const tipoInput = document.getElementById('tipoRegistroCliente');
                const empresaSelect = document.getElementById('menuEmpresaSelect');

                if (!modoSelect || !tipoInput) {
                    return;
                }

                const syncTipoRegistro = () => {
                    tipoInput.value = (modoSelect.value === 'PROPIO') ?
                        'REGISTRO_PROPIO_CLIENTE' :
                        'REGISTRO_RETRIBUTIVO';
                };

                syncTipoRegistro();
                modoSelect.addEventListener('change', syncTipoRegistro);

                // Manejar cambio de empresa en el selector
                if (empresaSelect) {
                    empresaSelect.addEventListener('change', function() {
                        const idEmpresa = this.value;
                        if (idEmpresa) {
                            window.location.href = 'index_cliente.php?view=menu&id_empresa=' + encodeURIComponent(idEmpresa);
                        }
                    });
                }
            })();
        </script>
    <?php endif; ?>

    <script>
        (function() {
            const modalElement = document.getElementById('modalComplementoFormularios');
            const iframe = document.getElementById('complementoFormulariosFrame');
            const botones = document.querySelectorAll('.btn-open-complemento');
            const modal = (modalElement && iframe && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalElement) : null;
            const empresaSelect = document.getElementById('nombre_empresa_cliente');
            const idEmpresaClienteInicial = <?= ($empresaAsignada !== null) ? (int)$empresaAsignada['id_empresa'] : 0 ?>;

            function abrirComplementoFormularios(tab) {
                if (!modal || !iframe) {
                    return;
                }

                let idEmpresaCliente = idEmpresaClienteInicial;
                if (empresaSelect && empresaSelect.value !== '') {
                    const parsed = Number.parseInt(empresaSelect.value, 10);
                    if (Number.isFinite(parsed) && parsed > 0) {
                        idEmpresaCliente = parsed;
                    }
                }

                let src = 'complemento_formularios.php?embed=1&tab=' + encodeURIComponent(tab || 'bajas');
                if (idEmpresaCliente > 0) {
                    src += '&id_empresa=' + encodeURIComponent(String(idEmpresaCliente));
                }
                iframe.src = src;
                modal.show();
            }

            // Manejar botones con clase .btn-open-complemento
            botones.forEach((btn) => {
                btn.addEventListener('click', function() {
                    const tab = (btn.getAttribute('data-tab') || 'bajas').trim();
                    abrirComplementoFormularios(tab);
                });
            });

            // Manejar botón del Paso 2 en mi_espacio
            const btnPaso2 = document.querySelector('.btn-open-paso2');
            if (btnPaso2) {
                btnPaso2.addEventListener('click', function() {
                    abrirComplementoFormularios('bajas');
                });
            }

            if (modalElement && iframe) {
                modalElement.addEventListener('hidden.bs.modal', function() {
                    iframe.src = '';
                    window.location.reload();
                });
            }

            // Permite que iframes hijos (mantenimiento_demo) abran este modal
            window.addEventListener('message', function (e) {
                if (e.data && e.data.type === 'abrirComplemento') {
                    abrirComplementoFormularios(e.data.tab || 'bajas');
                }
            });

        })();
    </script>


    <?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>

</html>