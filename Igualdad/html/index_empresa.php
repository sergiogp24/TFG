<?php
$view = $view ?? 'ver_empresas';
$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$isAdmin = ($rol === 'ADMINISTRADOR');
$isTecnico = ($rol === 'TECNICO');
$isCliente = ($rol === 'CLIENTE');
$canEditPlanes = ($isAdmin || $isTecnico);
$idEmpresaVista = (int)($_GET['id_empresa'] ?? ($idEmpresaContexto ?? 0));
$vistaEmpresaEspecifica = in_array($view, ['ver_empresa', 'ver_planes', 'ver_contratos', 'ver_medidas', 'edit_empresas'], true) && $idEmpresaVista > 0;
$sessionUsername = h($adminUsername ?? 'usuario');
$sessionEmail = h($adminEmail ?? '');
$panelCss = $isTecnico ? '../css/tecnico.css' : ($isCliente ? '../css/empresa.css' : '../css/admin.css');

// Detectar de dónde viene el usuario
$fromPanel = (string)($_GET['from'] ?? ($isTecnico ? 'tecnico' : ($isCliente ? 'cliente' : '')));
$fromParam = $fromPanel !== '' ? '&from=' . urlencode($fromPanel) : '';
$volverLink = '';
$volverLinkText = '';

if ($fromPanel === 'tecnico') {
    $volverLink = 'tecnico.php?view=menu';
    $volverLinkText = 'Volver a Mi Panel';
} elseif ($fromPanel === 'admin') {
    $volverLink = 'admin.php?view=menu';
    $volverLinkText = 'Volver a Mi Panel';
} elseif ($fromPanel === 'cliente') {
    $volverLink = 'index_cliente.php?view=mi_espacio';
    $volverLinkText = 'Volver a Mi Panel';
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Empresas</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="<?= $panelCss ?>">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row g-3">

            <!-- SIDEBAR -->
            <aside class="col-12 col-lg-3 col-xl-2">
                <div class="card shadow-sm border-0 sidebar">
                    <div class="card-body">
                        <!-- Header Sidebar -->
                        <div class="sidebar-header">
                            <?php if ($isTecnico): ?>
                                <div class="sidebar-avatar">👨‍💼</div>
                                <h5 class="sidebar-title">Panel Técnico</h5>
                            <?php else: ?>
                                <div class="sidebar-avatar" style="background: linear-gradient(135deg, var(--color-teal), var(--color-green));">🏢</div>
                                <h5 class="sidebar-title">Panel Empresas</h5>
                            <?php endif; ?>
                        </div>

                        <!-- User Info -->
                        <div class="sidebar-user-info">
                            <div class="info-label">Usuario Actual</div>
                            <div class="info-value"><?= $sessionUsername ?></div>
                            <?php if ($sessionEmail !== ''): ?>
                                <div class="info-email">📧 <?= $sessionEmail ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Navegación -->
                        <nav class="sidebar-nav">
                            <?php if ($isTecnico): ?>
                                <a class="nav-button" href="tecnico.php?view=menu">
                                    <span class="nav-icon">📊</span>
                                    <span>Mi Panel</span>
                                </a>

                                <button class="nav-button nav-collapse active" 
                                    type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                                    aria-expanded="true">
                                    <span class="nav-icon">🏢</span>
                                    <span>Empresas</span>
                                    <span class="collapse-icon">▾</span>
                                </button>

                                <div id="menuEmpresas" class="collapse nav-submenu show">
                                    <a class="nav-subbutton" href="../model/empresa.php?view=ver_empresas&from=tecnico">
                                        <span>📋</span>
                                        <span>Mis Empresas</span>
                                    </a>
                                    <a class="nav-subbutton" href="../model/empresa.php?view=ver_planes&from=tecnico">
                                        <span>🗂️</span>
                                        <span>Mis Planes</span>
                                    </a>
                                    <a class="nav-subbutton" href="../model/empresa.php?view=ver_contratos&tipo_contrato=MANTENIMIENTO&from=tecnico">
                                        <span>🛠️</span>
                                        <span>Mis Mantenimientos</span>
                                    </a>
                                </div>

                                <button class="nav-button nav-collapse" 
                                    type="button" data-bs-toggle="collapse" data-bs-target="#menuAreaPrivada"
                                    aria-expanded="false">
                                    <span class="nav-icon">🔐</span>
                                    <span>Área Privada</span>
                                    <span class="collapse-icon">▾</span>
                                </button>

                                <div id="menuAreaPrivada" class="collapse nav-submenu">
                                    <a class="nav-subbutton" href="tecnico.php?view=perfil">
                                        <span>👤</span>
                                        <span>Mi Cuenta</span>
                                    </a>
                                    <a class="nav-subbutton" href="tecnico.php?view=reuniones">
                                        <span>📅</span>
                                        <span>Mis Reuniones</span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <a class="nav-button" href="admin.php?view=menu">
                                    <span class="nav-icon">📊</span>
                                    <span>Panel Admin</span>
                                </a>

                                <!-- Usuarios -->
                                <a class="nav-button <?= in_array($view, ['ver_usuarios', 'add', 'edit', 'delete'], true) ? 'active' : '' ?>" href="admin.php?view=ver_usuarios">
                                    <span class="nav-icon">👥</span>
                                    <span>Usuarios</span>
                                </a>

                                <a class="nav-subbutton" href="admin.php?view=ver_empresas">
                                    <span class="nav-icon">🏢</span>
                                    <span>Directorio de Empresas</span>
                                </a>

                                <a class="nav-button <?= ($view === 'perfil') ? 'active' : '' ?>" href="admin.php?view=perfil">
                                    <span class="nav-icon">🔐</span>
                                    <span>Área Privada</span>
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($volverLink)): ?>
                            <!-- Volver al Panel -->
                            <a class="nav-button" href="<?= h($volverLink) ?>" style="border-color: var(--color-blue); color: var(--color-blue);">
                                <span class="nav-icon">⬅️</span>
                                <span><?= h($volverLinkText) ?></span>
                            </a>
                            <?php endif; ?>

                            <!-- Cerrar Sesión -->
                            <a class="nav-button nav-logout" href="<?= h(app_path('/php/logout.php')) ?>">
                                <span class="nav-icon">🚪</span>
                                <span>Cerrar Sesión</span>
                            </a>
                        </nav>
                    </div>
                </div>
            </aside>

            <!-- CONTENT -->
            <main class="col-12 col-lg-9 col-xl-10">
                <div class="card panel mx-auto shadow-sm border-0 <?= in_array($view, ['ver_empresas', 'ver_empresa', 'ver_planes', 'ver_contratos'], true) ? 'panel-wide' : '' ?>">
                    <div class="card-body p-4">

                        <header class="d-flex align-items-center justify-content-between mb-3">
                            <h4 class="mb-0">Gestión de Empresas</h4>
                        </header>

                        <?php if (!empty($_GET['msg'])): ?>
                            <div class="mb-3">
                                <div class="alert alert-info alert-dismissible fade show py-2 px-3 d-inline-flex align-items-center gap-2 mb-0 js-flash-msg" role="alert">
                                    <span class="small fw-semibold mb-0"><?= h($_GET['msg']) ?></span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($alertasAsignacion)): ?>
                            <?php foreach ($alertasAsignacion as $alertaAsignada): ?>
                                <div class="alert alert-warning py-2"><?= h((string)$alertaAsignada) ?></div>
                            <?php endforeach; ?>
                            <script>
                                (function() {
                                    const avisos = <?= json_encode(array_values($alertasAsignacion), JSON_UNESCAPED_UNICODE) ?>;
                                    const avisosValidos = avisos.filter(function(texto) {
                                        return !!texto;
                                    });

                                    if (avisosValidos.length === 1) {
                                        alert(avisosValidos[0]);
                                        return;
                                    }

                                    if (avisosValidos.length > 1) {
                                        alert('Tienes nuevas empresas asignadas:\n\n- ' + avisosValidos.join('\n- '));
                                    }
                                })();
                            </script>
                        <?php endif; ?>

                        <?php if ($view === 'ver_empresas'): ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Listado de empresas</h6>
                                <?php if (!$isTecnico): ?>
                                    <a class="btn btn-primary btn-sm" href="../model/empresa.php?view=add_empresas<?= $fromParam ?>">Agregar Empresa</a>
                                <?php endif; ?>
                            </div>

                            <!-- Barra superior: Mostrar (fijo 10) + Buscar -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled>
                                        <option selected>10</option>
                                    </select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="../model/empresa.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_empresas">
                                    <?php if ($fromPanel !== ''): ?>
                                        <input type="hidden" name="from" value="<?= h($fromPanel) ?>">
                                    <?php endif; ?>
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="../model/empresa.php?view=ver_empresas<?= $fromParam ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th class="w-50px">#</th>
                                            <th class="wrap">Razón Social</th>
                                            <th class="w-120px">NIF</th>
                                            <th class="w-150px">Responsable</th>
                                            <th class="wrap">Sector de la Empresa</th>
                                            <th class="w-120px">Teléfono</th>
                                            <th class="wrap">Email</th>
                                            <th class="col-actions">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1 + (((int)($currentPage ?? 1) - 1) * 10); ?>
                                        <?php foreach (($empresas ?? []) as $e): ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td class="wrap"><?= h($e['razon_social'] ?? '') ?></td>
                                                <td><?= h($e['nif'] ?? '') ?></td>
                                                <td><?= h($e['responsable'] ?? '') ?></td>
                                                <td class="wrap"><?= h($e['sector'] ?? '') ?></td>
                                                <td><?= h($e['telefono'] ?? '') ?></td>
                                                <td class="wrap"><?= h($e['email'] ?? '') ?></td>

                                                <td class="col-actions">
                                                    <div class="actions-nowrap">
                                                        <!-- VER EMPRESA -->
                                                        <a class="btn btn-primary btn-sm shared-table-action-btn"
                                                            href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$e['id_empresa'] ?><?= $fromParam ?>" title="Ver empresa">Ver</a>

                                                        <!-- EDITAR -->
                                                        <a class="btn btn-success btn-sm shared-table-action-btn"
                                                            href="../model/empresa.php?view=edit_empresas&id_empresa=<?= (int)$e['id_empresa'] ?><?= $fromParam ?>" title="Editar empresa">Editar</a>

                                                        <?php if (!$isTecnico): ?>
                                                            <!-- ELIMINAR -->
                                                            <form method="post" action="../controller/empresa_controller.php" class="d-inline"
                                                                onsubmit="return confirm('¿Seguro que quieres eliminar la empresa <?= h($e['razon_social'] ?? '') ?>?');">
                                                                <input type="hidden" name="accion" value="eliminar_empresas">
                                                                <?= csrf_input() ?>
                                                                <input type="hidden" name="id_empresa" value="<?= (int)$e['id_empresa'] ?>">
                                                                <button class="btn btn-outline-danger btn-sm shared-table-action-btn" type="submit" title="Eliminar empresa">Eliminar</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php if (empty($empresas)): ?>
                                            <tr>
                                                <td colspan="8" class="text-muted">No hay empresas.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pie: Mostrando X a Y de Z + paginación -->
                            <?php
                            $perPage = 10;
                            $totalEmpresas = (int)($totalEmpresas ?? count($empresas ?? []));
                            $currentPage = (int)($currentPage ?? 1);
                            $totalPages = (int)($totalPages ?? 1);

                            $start = ($totalEmpresas === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalEmpresas);

                            $qParam = (string)($searchQ ?? '');
                            ?>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">
                                    Mostrando <?= $start ?> a <?= $end ?> de <?= $totalEmpresas ?> Entradas
                                </div>

                                <nav aria-label="Paginación empresas">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php
                                        $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
                                        $prevPage = max(1, $currentPage - 1);

                                        $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
                                        $nextPage = min($totalPages, $currentPage + 1);
                                        ?>

                                        <li class="page-item <?= $prevDisabled ?>">
                                            <a class="page-link"
                                                href="../model/empresa.php?view=ver_empresas&page=<?= $prevPage ?>&q=<?= urlencode($qParam) ?><?= $fromParam ?>">Anterior</a>
                                        </li>

                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link"
                                                    href="../model/empresa.php?view=ver_empresas&page=<?= $p ?>&q=<?= urlencode($qParam) ?><?= $fromParam ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="page-item <?= $nextDisabled ?>">
                                            <a class="page-link"
                                                href="../model/empresa.php?view=ver_empresas&page=<?= $nextPage ?>&q=<?= urlencode($qParam) ?><?= $fromParam ?>">Siguiente</a>
                                        </li>
                                    </ul>
                            </div>

                        <?php elseif ($view === 'ver_empresa'): ?>

                            <div class="d-flex justify-content-between align-items-center mb-3 ge-company-topbar">
                                <h6 class="mb-0 ge-company-title">Detalles de la empresa</h6>
                                <a class="btn btn-secondary btn-sm" href="../model/empresa.php?view=ver_empresas<?= $fromParam ?>">Volver al listado</a>
                            </div>

                            <?php if ($detalleEmpresa === null): ?>
                                <div class="alert alert-warning">No se encontró la empresa seleccionada.</div>
                            <?php else: ?>
                                <?php $detalleEmpresaId = (int)($detalleEmpresa['id_empresa'] ?? 0); ?>
                                <div class="ge-hero mb-3">
                                    <div class="ge-hero-badge">Empresa activa</div>
                                    <h5 class="ge-hero-name mb-1"><?= h($detalleEmpresa['razon_social'] ?? 'Empresa') ?></h5>
                                    <div class="ge-hero-meta">
                                        <span class="ge-meta-pill">NIF: <?= h($detalleEmpresa['nif'] ?? '-') ?></span>
                                        <span class="ge-meta-pill">Sector: <?= h($detalleEmpresa['sector'] ?? '-') ?></span>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm mb-3 ge-company-card">
                                    <div class="card-body">
                                        <div class="ge-section-label">Menú de la empresa</div>
                                        <div class="ge-action-grid">
                                            <?php if ($isTecnico): ?>
                                                <a class="btn btn-primary btn-sm ge-company-action-btn" href="../model/empresa.php?view=ver_planes&id_empresa=<?= $detalleEmpresaId ?>&from=tecnico">🗂 Mis Planes</a>
                                                <a class="btn btn-primary btn-sm ge-company-action-btn" href="../model/empresa.php?view=ver_contratos&tipo_contrato=MANTENIMIENTO&id_empresa=<?= $detalleEmpresaId ?>&from=tecnico">🛠️ Mis Mantenimientos</a>
                                            <?php else: ?>
                                                <a class="btn btn-primary btn-sm ge-company-action-btn" href="../model/empresa.php?view=ver_contratos&id_empresa=<?= $detalleEmpresaId ?><?= $fromParam ?>">🧾 Servicios aceptados</a>
                                                <?php if (!$isAdmin): ?>
                                                    <a class="btn btn-primary btn-sm ge-company-action-btn" href="../model/empresa.php?view=ver_planes&id_empresa=<?= $detalleEmpresaId ?><?= $fromParam ?>">🗂 Ver planes</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <a class="btn btn-primary btn-sm ge-company-action-btn" href="<?= h(app_path('/php/archivos_subidos.php?id_empresa=' . $detalleEmpresaId)) ?>">📁 Archivos subidos</a>
                                            <?php if (!$isAdmin): ?>
                                            <a class="btn btn-primary btn-sm ge-company-action-btn" href="<?= h(app_path('/html/index_staff.php?id_empresa=' . $detalleEmpresaId)) ?>">📊 Subir registro retributivo</a>
                                            <?php endif; ?>
                                            <a class="btn btn-secondary btn-sm ge-company-action-btn" href="../model/empresa.php?view=edit_empresas&id_empresa=<?= $detalleEmpresaId ?><?= $fromParam ?>">✏️ Editar empresa</a>
                                            <a class="btn btn-secondary btn-sm ge-company-action-btn" href="../model/empresa.php?view=ver_medidas&id_empresa=<?= $detalleEmpresaId ?><?= $fromParam ?>">📌 Ver áreas y medidas</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm mb-3 ge-company-card">
                                    <div class="card-body">
                                        <div class="ge-section-label">Datos de la empresa</div>
                                        <div class="ge-info-grid">
                                            <div class="ge-info-item">
                                                <div class="ge-info-label">🏷 Razón social</div>
                                                <div class="ge-info-value"><?= h($detalleEmpresa['razon_social'] ?? '') ?></div>
                                            </div>
                                            <div class="ge-info-item">
                                                <div class="ge-info-label">🪪 NIF</div>
                                                <div class="ge-info-value"><?= h($detalleEmpresa['nif'] ?? '') ?></div>
                                            </div>
                                            <div class="ge-info-item">
                                                <div class="ge-info-label">👤 Responsable</div>
                                                <div class="ge-info-value"><?= h($detalleEmpresa['responsable'] ?? '') ?></div>
                                            </div>
                                            <div class="ge-info-item">
                                                <div class="ge-info-label">🏭 Sector</div>
                                                <div class="ge-info-value"><?= h($detalleEmpresa['sector'] ?? '') ?></div>
                                            </div>
                                            <div class="ge-info-item">
                                                <div class="ge-info-label">📧 Email</div>
                                                <div class="ge-info-value"><?= h($detalleEmpresa['email'] ?? '') ?></div>
                                            </div>
                                            <div class="ge-info-item">
                                                <div class="ge-info-label">📱 Teléfono</div>
                                                <div class="ge-info-value"><?= h($detalleEmpresa['telefono'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="ge-tech-box mb-3">
                                    <span class="ge-tech-label">Técnico asignado</span>
                                    <strong class="ge-tech-value"><?= h($detalleUsuario['nombre_usuario'] ?? 'Sin técnico asignado') ?></strong>
                                </div>

                                <h6 class="mb-2 ge-areas-title">Áreas contratadas</h6>
                                <p class="ge-areas-subtitle">Selecciona un área para abrir su módulo de mantenimiento.</p>

                                <?php if (empty($detalleEmpresaAreas)): ?>
                                    <div class="alert alert-light border mb-0">Esta empresa no tiene áreas o medidas contratadas.</div>
                                <?php else: ?>
                                    <?php
                                    $areasFlat = [];
                                    foreach ($detalleEmpresaAreas as $area) {
                                        $nombreArea = trim((string)($area['nombre'] ?? ''));
                                        if ($nombreArea !== '') {
                                            $areasFlat[$nombreArea] = $nombreArea;
                                        }
                                    }
                                    ?>

                                    <?php if (empty($areasFlat)): ?>
                                        <div class="alert alert-light border mb-0">Esta empresa no tiene áreas contratadas.</div>
                                    <?php else: ?>
                                        <div class="ge-area-grid">
                                            <?php foreach ($areasFlat as $nombreArea): ?>
                                                <?php
                                                $idEmpresaDetalle = (int)($detalleEmpresa['id_empresa'] ?? 0);
                                                $nombreAreaNorm = mb_strtolower(trim((string)$nombreArea), 'UTF-8');
                                                $maintenanceHref = '';
                                                if (str_contains($nombreAreaNorm, 'formacion') || str_contains($nombreAreaNorm, 'formación')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_formacion&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'ejercicio')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_ejercicio&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'infrarrepresent') || str_contains($nombreAreaNorm, 'infra')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_infra&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'acoso')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_acoso&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'violencia')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_violencia&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'retribu')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_retribuciones&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'seleccion') || str_contains($nombreAreaNorm, 'selección') || str_contains($nombreAreaNorm, 'contratacion') || str_contains($nombreAreaNorm, 'contratación')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_seleccion&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'responsable de igualdad') || (str_contains($nombreAreaNorm, 'responsable') && str_contains($nombreAreaNorm, 'igualdad'))) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_responsable_igualdad&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'salud') || str_contains($nombreAreaNorm, 'laboral')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_salud&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'condicion') || str_contains($nombreAreaNorm, 'trabajo')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_condiciones&id_empresa=' . $idEmpresaDetalle;
                                                } elseif (str_contains($nombreAreaNorm, 'promocion') || str_contains($nombreAreaNorm, 'promoción') || str_contains($nombreAreaNorm, 'ascenso')) {
                                                    $maintenanceHref = 'mantenimiento.php?view=ver_promocion&id_empresa=' . $idEmpresaDetalle;
                                                }
                                                ?>

                                                <?php if ($maintenanceHref !== ''): ?>
                                                    <a
                                                        class="btn btn-outline-primary ge-area-card"
                                                        href="<?= h($maintenanceHref) ?>"
                                                        title="Abrir mantenimiento de <?= h($nombreArea) ?>">
                                                        <?= h($nombreArea) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary ge-area-card ge-area-card-disabled"
                                                        disabled
                                                        title="Sin módulo de mantenimiento para esta área">
                                                        <?= h($nombreArea) ?>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                        <?php elseif ($view === 'add_empresas'): ?>

                            <h6 class="text-center mb-3">Agregar empresa</h6>

                            <form method="post" action="../controller/empresa_controller.php" class="vstack gap-2" enctype="multipart/form-data">
                                <input type="hidden" name="accion" value="add_empresas">
                                <?= csrf_input() ?>

                                <div>
                                    <label class="form-label">Razón Social *</label>
                                    <input class="form-control" name="razon_social" required>
                                </div>

                                <div>
                                    <label class="form-label">NIF *</label>
                                    <input class="form-control" name="nif" required>
                                </div>

                                <div>
                                    <label class="form-label">Domicilio Social </label>
                                    <input class="form-control" name="domicilio_social">
                                </div>

                                <div>
                                    <label class="form-label">Forma Jurídica</label>
                                    <input class="form-control" name="forma_juridica">
                                </div>

                                <div>
                                    <label class="form-label">Año Constitucional</label>
                                    <input class="form-control" name="ano_constitucional">
                                </div>

                                <div>
                                    <label class="form-label">Responsable</label>
                                    <input class="form-control" name="responsable">
                                </div>

                                <div>
                                    <label class="form-label">Cargo</label>
                                    <input class="form-control" name="cargo">
                                </div>

                                <div>
                                    <label class="form-label">Contacto</label>
                                    <input class="form-control" name="contacto">
                                </div>

                                <div>
                                    <label class="form-label mb-1">Email *</label>
                                    <input class="form-control" name="email" type="email">
                                </div>

                                <div>
                                    <label class="form-label">Teléfono *</label>
                                    <input class="form-control" name="telefono">
                                </div>

                                <div>
                                    <label class="form-label">Sector *</label>
                                    <input class="form-control" name="sector">
                                </div>

                                <div>
                                    <label class="form-label">CNAE</label>
                                    <input class="form-control" name="cnae">
                                </div>

                                <div>
                                    <label class="form-label">Convenio</label>
                                    <input class="form-control" name="convenio">
                                </div>

                                <div>
                                    <label class="form-label">Número Mujeres</label>
                                    <input class="form-control" name="personas_mujeres">
                                </div>

                                <div>
                                    <label class="form-label">Número Hombres</label>
                                    <input class="form-control" name="personas_hombres">
                                </div>

                                <div>
                                    <label class="form-label">Total Personas</label>
                                    <input class="form-control" name="personas_totales">
                                </div>

                                <div>
                                    <label class="form-label">Centros de Trabajo</label>
                                    <input class="form-control" name="centros_trabajo">
                                </div>

                                <div>
                                    <label class="form-label">Recogida de información</label>
                                    <input class="form-control" name="recogida_informacion">
                                </div>

                                <div>
                                    <label class="form-label">Vigencia del Plan</label>
                                    <input class="form-control" name="vigencia_plan">
                                </div>

                                <?php if (!$isTecnico): ?>
                                    <div>
                                        <details class="tecnicos-dropdown">
                                            <summary class="btn btn-outline-primary w-100 text-start">Asignar tecnicos</summary>

                                            <div class="row g-2 mb-2 mt-2 align-items-stretch">
                                                <div class="col-12 col-lg-7">
                                                    <input id="tecnicosFilterAdd" class="form-control" type="search" placeholder="Buscar tecnico...">
                                                </div>
                                                <div class="col-12 col-lg-5 d-flex gap-2 flex-wrap justify-content-lg-end tecnicos-toolbar-actions">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm tecnicos-toolbar-btn" onclick="toggleTecnicos('add', true)">Marcar todos</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm tecnicos-toolbar-btn" onclick="toggleTecnicos('add', false)">Desmarcar</button>
                                                </div>
                                            </div>

                                            <div class="border rounded" id="tecnicosListAdd" style="max-height: 230px; overflow: auto;">
                                                <?php foreach (($tecnicosDisponibles ?? []) as $tecnico): ?>
                                                    <?php $nombreTecnico = (string)($tecnico['nombre_usuario'] ?? ''); ?>
                                                    <?php $emailTecnico = (string)($tecnico['email'] ?? ''); ?>
                                                    <label
                                                        class="d-flex align-items-center gap-2 px-3 py-2 border-bottom"
                                                        data-name="<?= h(mb_strtolower(trim($nombreTecnico . ' ' . $emailTecnico), 'UTF-8')) ?>">
                                                        <input
                                                            class="form-check-input m-0 tecnico-checkbox-add"
                                                            type="checkbox"
                                                            name="tecnicos[]"
                                                            value="<?= (int)($tecnico['id_usuario'] ?? 0) ?>">
                                                        <span>
                                                            <?= h($nombreTecnico) ?>
                                                            <?php if ($emailTecnico !== ''): ?>
                                                                (<?= h($emailTecnico) ?>)
                                                            <?php endif; ?>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <label class="form-label">Subir Toma De Datos: </label>
                                    <input class="form-control" type="file" name="archivos_empresa[]" multiple>
                                    <div class="form-text">Formatos permitidos: xlsx, xls, docx, doc, pdf (max 50MB por archivo).</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary w-100" type="submit">Crear</button>
                                    <a class="btn btn-outline-secondary w-100" href="../model/empresa.php?view=ver_empresas">Volver</a>
                                </div>
                            </form>

                        <?php elseif ($view === 'edit_empresas'): ?>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="mb-0">Editar empresa</h6>
                                <?php if ($selectedEmpresa !== null): ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)($selectedEmpresa['id_empresa'] ?? 0) ?>">Volver a la empresa</a>
                                <?php endif; ?>
                            </div>

                            <?php if ($selectedEmpresa === null): ?>
                                <div class="alert alert-warning">Empresa no encontrada.</div>
                                <a class="btn btn-outline-secondary w-100" href="../model/empresa.php?view=ver_empresas">Volver</a>
                            <?php else: ?>
                                <form method="post" action="../controller/empresa_controller.php" class="edit-user-form" enctype="multipart/form-data">
                                    <input type="hidden" name="accion" value="editar_empresas">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="id_empresa" value="<?= (int)($selectedEmpresa['id_empresa'] ?? 0) ?>">

                                    <div class="row g-4">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Razón social</label>
                                            <input class="form-control edit-input" name="razon_social" value="<?= h($selectedEmpresa['razon_social'] ?? '') ?>" required>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">NIF</label>
                                            <input class="form-control edit-input" name="nif" value="<?= h($selectedEmpresa['nif'] ?? '') ?>" required>
                                        </div>

                                        <div class="col-12 col-md-8">
                                            <label class="form-label text-center w-100">Domicilio social</label>
                                            <input class="form-control edit-input" name="domicilio_social" value="<?= h($selectedEmpresa['domicilio_social'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Forma jurídica</label>
                                            <input class="form-control edit-input" name="forma_juridica" value="<?= h($selectedEmpresa['forma_juridica'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Año constitución</label>
                                            <input class="form-control edit-input" name="ano_constitucional" value="<?= h($selectedEmpresa['ano_constitucional'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Responsable</label>
                                            <input class="form-control edit-input" name="responsable" value="<?= h($selectedEmpresa['responsable'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Cargo</label>
                                            <input class="form-control edit-input" name="cargo" value="<?= h($selectedEmpresa['cargo'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Contacto</label>
                                            <input class="form-control edit-input" name="contacto" value="<?= h($selectedEmpresa['contacto'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Email</label>
                                            <input class="form-control edit-input" name="email" type="email" value="<?= h($selectedEmpresa['email'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Teléfono</label>
                                            <input class="form-control edit-input" name="telefono" value="<?= h($selectedEmpresa['telefono'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Sector</label>
                                            <input class="form-control edit-input" name="sector" value="<?= h($selectedEmpresa['sector'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">CNAE</label>
                                            <input class="form-control edit-input" name="cnae" value="<?= h($selectedEmpresa['cnae'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-8">
                                            <label class="form-label text-center w-100">Convenio</label>
                                            <input class="form-control edit-input" name="convenio" value="<?= h($selectedEmpresa['convenio'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Mujeres</label>
                                            <input class="form-control edit-input" name="personas_mujeres" value="<?= h($selectedEmpresa['personas_mujeres'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Hombres</label>
                                            <input class="form-control edit-input" name="personas_hombres" value="<?= h($selectedEmpresa['personas_hombres'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Total</label>
                                            <input class="form-control edit-input" name="personas_totales" value="<?= h($selectedEmpresa['personas_total'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Centros de trabajo</label>
                                            <input class="form-control edit-input" name="centros_trabajo" value="<?= h($selectedEmpresa['centros_trabajo'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Recogida de información</label>
                                            <input class="form-control edit-input" name="recogida_informacion" value="<?= h($selectedEmpresa['recogida_informacion'] ?? '') ?>">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label text-center w-100">Vigencia del plan</label>
                                            <input class="form-control edit-input" name="vigencia_plan" value="<?= h($selectedEmpresa['vigencia_plan'] ?? '') ?>">
                                        </div>

                                        <?php if (!$isTecnico): ?>
                                            <div class="col-12 col-lg-6 mt-2">
                                                <details class="tecnicos-dropdown">
                                                    <summary class="btn btn-outline-primary w-100 text-start">Asignar tecnicos</summary>

                                                    <div class="border rounded" id="tecnicosListEdit" style="max-height: 230px; overflow: auto;">
                                                        <?php foreach (($tecnicosDisponibles ?? []) as $tecnico): ?>
                                                            <?php $idTecnico = (int)($tecnico['id_usuario'] ?? 0); ?>
                                                            <?php $nombreTecnico = (string)($tecnico['nombre_usuario'] ?? ''); ?>
                                                            <?php $emailTecnico = (string)($tecnico['email'] ?? ''); ?>
                                                            <label
                                                                class="d-flex align-items-center gap-2 px-3 py-2 border-bottom"
                                                                data-name="<?= h(mb_strtolower(trim($nombreTecnico . ' ' . $emailTecnico), 'UTF-8')) ?>">
                                                                <input
                                                                    class="form-check-input m-0 tecnico-checkbox-edit"
                                                                    type="checkbox"
                                                                    name="tecnicos[]"
                                                                    value="<?= $idTecnico ?>"
                                                                    <?= in_array($idTecnico, ($tecnicosAsignadosEmpresa ?? []), true) ? 'checked' : '' ?>>
                                                                <span>
                                                                    <?= h($nombreTecnico) ?>
                                                                    <?php if ($emailTecnico !== ''): ?>
                                                                        (<?= h($emailTecnico) ?>)
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </details>
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" name="id_usuario" value="<?= (int)($selectedEmpresa['id_usuario'] ?? 0) ?>">
                                        <?php endif; ?>

                                        <div class="col-12 col-lg-6 mt-2">
                                            <label class="form-label text-center w-100">Subir Toma De Datos: </label>
                                            <input class="form-control edit-input" type="file" name="archivos_empresa[]" multiple>
                                            <div class="form-text">Formatos permitidos: xlsx, xls, docx, doc, pdf (max 50MB por archivo).</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="d-flex justify-content-center gap-4 mt-2">
                                                <button class="btn btn-primary px-5" type="submit">Actualizar</button>
                                                <a class="btn btn-danger px-5" href="../model/empresa.php?view=ver_empresas">Cancelar</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>

                        <?php elseif ($view === 'delete_empresas'): ?>

                            <h6 class="text-center mb-3">Eliminar empresas</h6>

                            <?php if ($isTecnico): ?>
                                <div class="alert alert-warning text-center mb-0">
                                    No tienes permisos para eliminar empresas.
                                </div>
                            <?php else: ?>

                            <div class="vstack gap-2">
                                <?php foreach (($empresas ?? []) as $e): ?>
                                    <form method="post" action="../controller/empresa_controller.php"
                                        class="border rounded bg-light p-2"
                                        onsubmit="return confirm('¿Seguro que quieres eliminar la empresa <?= h($e['razon_social'] ?? '') ?>?');">
                                        <input type="hidden" name="accion" value="eliminar_empresas">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id_empresa" value="<?= (int)($e['id_empresa'] ?? 0) ?>">

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div><strong><?= h($e['razon_social'] ?? '') ?></strong></div>
                                                <div class="text-muted small">NIF: <?= h($e['nif'] ?? '') ?></div>
                                                <div class="text-muted small">Email: <?= h($e['email'] ?? '') ?></div>
                                                <div class="text-muted small">Teléfono: <?= h($e['telefono'] ?? '') ?></div>
                                            </div>
                                            <button class="btn btn-danger" type="submit">Eliminar</button>
                                        </div>
                                    </form>
                                <?php endforeach; ?>

                                <?php if (empty($empresas)): ?>
                                    <div class="alert alert-light border mb-0 text-center text-muted">
                                        No hay empresas para eliminar.
                                    </div>
                                <?php endif; ?>

                            </div>

                            <?php endif; ?>

                            <div class="mt-3">
                                <a class="btn btn-outline-secondary w-100" href="../model/empresa.php?view=ver_empresas">Volver</a>
                            </div>



                    </div>
                               <?php elseif ($view === 'ver_planes'): ?>

                    <!-- VER PLANES -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Planes asignados a empresas</h6>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!$isTecnico): ?>
                            <a class="btn btn-primary btn-sm" href="../model/empresa.php?view=add_contratos&tipo_contrato=PLAN%20IGUALDAD<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>">Añadir Plan igualdad</a>
                            <?php endif; ?>
                            <?php if (($empresaContexto ?? null) !== null): ?>
                                <a class="btn btn-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$empresaContexto['id_empresa'] ?>">Volver a la empresa</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Barra superior: Mostrar (fijo 10) + Buscar -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span>Mostrar</span>
                            <select class="form-select form-select-sm" style="width: 90px;" disabled>
                                <option selected>10</option>
                            </select>
                            <span>Entradas</span>
                        </div>

                        <form method="get" action="../model/empresa.php" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="view" value="ver_planes">
                            <?php if (($idEmpresaContexto ?? 0) > 0): ?>
                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaContexto ?>">
                            <?php endif; ?>
                            <label class="mb-0">Buscar:</label>
                            <input class="form-control form-control-sm" name="q" value="<?= h($searchPlanesQ ?? '') ?>" style="width: 220px;">
                            <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
                            <?php if (!empty($searchPlanesQ)): ?>
                                <a class="btn btn-danger btn-sm" href="../model/empresa.php?view=ver_planes<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>">Limpiar</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if (($empresaContexto ?? null) !== null): ?>
                        <div class="alert alert-light border">
                            Mostrando planes de <strong><?= h((string)($empresaContexto['razon_social'] ?? '')) ?></strong>.
                        </div>
                    <?php endif; ?>

                    <?php if (empty($planes)): ?>
                        <div class="alert alert-light border">No hay planes creados aún.</div>
                    <?php else: ?>
                        <div class="contratos-table-wrap">
                            <table class="table table-bordered align-middle text-center contratos-table">
                                <thead class="table-secondary">
                                    <tr>
                                        <th class="w-50px">#</th>
                                        <th>Empresa</th>
                                        <th>Tipo</th>
                                        <th>Inicio plan</th>
                                        <th>Fin plan</th>
                                        <?php if ($canEditPlanes): ?>
                                            <th class="col-actions">Acciones</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1 + (((int)($currentPagePlanes ?? 1) - 1) * 10); ?>
                                    <?php foreach ($planes as $p): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td><?= h($p['razon_social']) ?></td>
                                            <td><?= h((string)($p['tipo_contrato'] ?? 'PLAN IGUALDAD')) ?></td>
                                            <td><?= h($p['inicio_plan']) ?></td>
                                            <td><?= h($p['fin_plan']) ?></td>
                                            <?php if ($canEditPlanes): ?>
                                                <td class="col-actions">
                                                    <div class="actions-nowrap">
                                                        <a class="btn btn-success btn-sm shared-table-action-btn"
                                                            href="?view=edit_plan&id_empresa=<?= (int)$p['id_empresa'] ?>"
                                                            title="Editar">Editar</a>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($planes)): ?>
                                        <tr>
                                            <td colspan="<?= $canEditPlanes ? '6' : '5' ?>" class="text-muted">No hay planes creados aún.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pie: Mostrando X a Y de Z + paginación -->
                        <?php
                        $perPagePlanes = 10;
                        $totalPlanesCount = (int)($totalPlanesCount ?? count($planes ?? []));
                        $currentPagePlanes = (int)($currentPagePlanes ?? 1);
                        $totalPagesPlanes = (int)($totalPagesPlanes ?? 1);

                        $startPlanes = ($totalPlanesCount === 0) ? 0 : (($currentPagePlanes - 1) * $perPagePlanes + 1);
                        $endPlanes = min($currentPagePlanes * $perPagePlanes, $totalPlanesCount);

                        $qParamPlanes = (string)($searchPlanesQ ?? '');
                        ?>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                            <div class="text-muted small">
                                Mostrando <?= $startPlanes ?> a <?= $endPlanes ?> de <?= $totalPlanesCount ?> Entradas
                            </div>

                            <nav aria-label="Paginación planes">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $prevDisabledPlanes = ($currentPagePlanes <= 1) ? 'disabled' : '';
                                    $prevPagePlanes = max(1, $currentPagePlanes - 1);

                                    $nextDisabledPlanes = ($currentPagePlanes >= $totalPagesPlanes) ? 'disabled' : '';
                                    $nextPagePlanes = min($totalPagesPlanes, $currentPagePlanes + 1);
                                    ?>

                                    <li class="page-item <?= $prevDisabledPlanes ?>">
                                        <a class="page-link"
                                            href="../model/empresa.php?view=ver_planes<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>&page=<?= $prevPagePlanes ?>&q=<?= urlencode($qParamPlanes) ?>">Anterior</a>
                                    </li>

                                    <?php for ($p = 1; $p <= $totalPagesPlanes; $p++): ?>
                                        <li class="page-item <?= ($p === $currentPagePlanes) ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="../model/empresa.php?view=ver_planes<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>&page=<?= $p ?>&q=<?= urlencode($qParamPlanes) ?>"><?= $p ?></a>
                                            </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $nextDisabledPlanes ?>">
                                        <a class="page-link"
                                            href="../model/empresa.php?view=ver_planes<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>&page=<?= $nextPagePlanes ?>&q=<?= urlencode($qParamPlanes) ?>">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                     <?php elseif ($view === 'ver_medidas'): ?>

                        <?php
                        $idEmpresaVolverMedidas = (int)($idEmpresaContexto ?? 0);
                        if ($idEmpresaVolverMedidas <= 0 && !empty($verMedidasPlan['id_empresa'])) {
                            $idEmpresaVolverMedidas = (int)$verMedidasPlan['id_empresa'];
                        }
                        ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Áreas y medidas del plan</h6>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($idEmpresaVolverMedidas > 0): ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= $idEmpresaVolverMedidas ?>">Volver a la empresa</a>
                                <?php endif; ?>
                            </div>
                    </div>

                    <?php if ($verMedidasPlan === null): ?>
                        <div class="alert alert-warning">No se encontró el plan para la empresa seleccionada.</div>
                    <?php else: ?>
                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($verMedidasPlan['razon_social'] ?? '') ?></div>
                            <div><strong>Inicio plan:</strong> <?= h($verMedidasPlan['inicio_plan'] ?? '') ?></div>
                            <div><strong>Fin plan:</strong> <?= h($verMedidasPlan['fin_plan'] ?? '') ?></div>
                        </div>

                        <?php if (empty($verMedidasAreas)): ?>
                            <div class="alert alert-light border mb-0">No hay áreas o medidas asociadas a este plan.</div>
                        <?php else: ?>
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-muted mb-2">Vista de solo lectura con las áreas y medidas ya seleccionadas.</div>

                                <?php foreach ($verMedidasAreas as $areaId => $area): ?>
                                    <?php
                                    $medidasArea = $area['medidas'] ?? [];
                                    ?>
                                    <div class="border rounded p-2 mb-2 bg-white">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="ver_area_<?= (int)$areaId ?>"
                                                checked
                                                disabled>
                                            <label class="form-check-label fw-semibold" for="ver_area_<?= (int)$areaId ?>">
                                                <?= h($area['nombre'] ?? '') ?>
                                            </label>
                                        </div>
                                        <div class="ms-4 mt-2">
                                            <?php if (empty($medidasArea)): ?>
                                                <div class="small text-muted">Sin medidas para esta área.</div>
                                            <?php else: ?>
                                                <?php foreach ($medidasArea as $m): ?>
                                                    <div class="form-check mb-1">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            id="ver_medida_<?= (int)$m['id_medida'] ?>"
                                                            checked
                                                            disabled>
                                                        <label class="form-check-label" for="ver_medida_<?= (int)$m['id_medida'] ?>">
                                                            <?= h($m['descripcion'] ?? '') ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>


                <?php elseif ($view === 'edit_plan'): ?>

                    <?php
                    $idEmpresaVolverEditPlan = (int)($idEmpresaContexto ?? 0);
                    if ($idEmpresaVolverEditPlan <= 0 && !empty($editPlan['id_empresa'])) {
                        $idEmpresaVolverEditPlan = (int)$editPlan['id_empresa'];
                    }
                    ?>

                    <!-- EDITAR PLAN -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Editar plan</h6>
                        <?php if ($idEmpresaVolverEditPlan > 0): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= $idEmpresaVolverEditPlan ?>">Volver a la empresa</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($editPlanError)): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= h($editPlanError) ?></div>
                    <?php endif; ?>

                    <?php if (!$canEditPlanes): ?>
                        <div class="alert alert-warning">No tienes permisos para editar planes.</div>
                        <a class="btn btn-outline-secondary" href="?view=ver_planes<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>">Volver</a>
                    <?php elseif ($editPlan === null): ?>
                        <div class="alert alert-warning">Plan no encontrado.</div>
                        <a class="btn btn-outline-secondary" href="?view=ver_planes<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>">Volver</a>
                    <?php else: ?>

                        <form method="post" action="../controller/empresa_controller.php" class="vstack gap-3">
                            <input type="hidden" name="accion" value="edit_plan">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id_empresa" value="<?= (int)$editPlan['id_empresa'] ?>">

                            <!-- Empresa -->
                            <div>
                                <label class="form-label fw-semibold">Empresa</label>
                                <input class="form-control" value="<?= h($editPlan['razon_social']) ?>" disabled>
                            </div>

                            <!-- Fechas -->
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Inicio plan</label>
                                    <input class="form-control" type="date" name="inicio_plan" required
                                        value="<?= h($editPlan['inicio_plan']) ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Fin plan</label>
                                    <input class="form-control" type="date" name="fin_plan" required
                                        value="<?= h($editPlan['fin_plan']) ?>">
                                </div>
                            </div>

                            <!-- Área + medidas -->
                            <div class="border rounded p-3 bg-light">
                                <label class="form-label fw-semibold">Área del Plan y sus medidas</label>
                                <div class="small text-muted mb-2">Marca las áreas y sus medidas. Se guardarán todas en esta empresa.</div>

                                <?php foreach ($editAreasPlan as $area): ?>
                                    <?php
                                    $areaId     = (int)$area['id_plan'];
                                    $checkedArea = in_array($areaId, $editAreasSeleccionadas, true);
                                    $medidasArea = $editMedidasPorArea[$areaId] ?? [];
                                    ?>
                                    <div class="border rounded p-2 mb-2 bg-white">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input js-edit-area-check"
                                                type="checkbox"
                                                id="edit_area_<?= $areaId ?>"
                                                name="areas[]"
                                                value="<?= $areaId ?>"
                                                data-area="<?= $areaId ?>"
                                                <?= in_array($areaId, $editAreasSeleccionadas, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="edit_area_<?= $areaId ?>">
                                                <?= h($area['nombre']) ?>
                                            </label>
                                        </div>
                                        <div class="ms-4 mt-2 js-edit-medidas-group" data-area="<?= $areaId ?>"
                                            style="display: <?= $checkedArea ? 'block' : 'none' ?>;">
                                            <?php if (empty($medidasArea)): ?>
                                                <div class="small text-muted">Sin medidas para esta área.</div>
                                            <?php else: ?>
                                                <?php foreach ($medidasArea as $m): ?>
                                                    <?php $idMedida = (int)$m['id_medida']; ?>
                                                    <div class="form-check mb-1">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            id="edit_medida_<?= $idMedida ?>"
                                                            name="medidas[<?= $areaId ?>][]"
                                                            value="<?= $idMedida ?>"
                                                            <?= in_array($idMedida, $editPlanMedidasByArea[$areaId] ?? [], true) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="edit_medida_<?= $idMedida ?>">
                                                            <?= h($m['descripcion']) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <div class="mt-3 pt-2 border-top">
                                                <label class="form-label small fw-semibold">Agregar medida personalizada</label>
                                                <input 
                                                    type="text" 
                                                    class="form-control form-control-sm" 
                                                    name="medidas_personalizadas[<?= $areaId ?>]" 
                                                    placeholder="Descripción de la nueva medida"
                                                    <?= !$checkedArea ? 'disabled' : '' ?>
                                                    value="<?= h($selectedContrato['medidas_personalizadas'][$areaId] ?? '') ?>"
                                                >
                                                <small class="text-muted d-block mt-1">Escribe una medida que no esté en la lista anterior</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary w-100" type="submit">Guardar cambios</button>
                                <a class="btn btn-outline-secondary w-100" href="?view=ver_planes<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?>">Volver</a>
                            </div>
                        </form>

                    <?php endif; ?>

                <?php elseif ($view === 'ver_contratos'): ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><?= (($tipoContratoFiltro ?? '') === 'MANTENIMIENTO') ? 'Mantenimientos de empresas' : 'Servicios aceptados' ?></h6>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (($empresaContexto ?? null) !== null): ?>
                                <a class="btn btn-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$empresaContexto['id_empresa'] ?><?= $fromParam ?>">Volver a la empresa</a>
                            <?php endif; ?>
                            <?php if (!$isTecnico): ?>
                            <a class="btn btn-primary btn-sm" href="../model/empresa.php?view=add_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?><?= $fromParam ?>"><?= (($tipoContratoFiltro ?? '') === 'MANTENIMIENTO') ? 'Añadir Mantenimiento' : ((($tipoContratoFiltro ?? '') === 'PLAN IGUALDAD') ? 'Añadir Plan igualdad' : 'Añadir Servicios') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (isset($tablaContratoExiste) && $tablaContratoExiste === false): ?>
                        <div class="alert alert-warning border mb-0">
                            La tabla contrato_empresa no existe en la base de datos. Crea la tabla para poder listar contratos.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span>Mostrar</span>
                                <select class="form-select form-select-sm" style="width: 90px;" disabled>
                                    <option selected>10</option>
                                </select>
                                <span>Entradas</span>
                            </div>

                            <form method="get" action="empresa.php" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="view" value="ver_contratos">
                                <?php if (($idEmpresaContexto ?? 0) > 0): ?>
                                    <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaContexto ?>">
                                <?php endif; ?>
                                <?php if (($tipoContratoFiltro ?? '') !== ''): ?>
                                    <input type="hidden" name="tipo_contrato" value="<?= h((string)$tipoContratoFiltro) ?>">
                                <?php endif; ?>
                                <?php if (($fromPanel ?? '') !== ''): ?>
                                    <input type="hidden" name="from" value="<?= h((string)$fromPanel) ?>">
                                <?php endif; ?>
                                <label class="mb-0">Buscar:</label>
                                <input class="form-control form-control-sm" name="q" value="<?= h($searchContratoQ ?? '') ?>" style="width: 220px;">
                                <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
                                <?php if (!empty($searchContratoQ)): ?>
                                    <a class="btn btn-danger btn-sm" href="../model/empresa.php?view=ver_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?><?= $fromParam ?>">Limpiar</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <?php if (($empresaContexto ?? null) !== null): ?>
                            <div class="alert alert-light border">
                                Mostrando contratos de <strong><?= h((string)($empresaContexto['razon_social'] ?? '')) ?></strong>.
                            </div>
                        <?php endif; ?>

                        <div class="contratos-table-wrap">
                            <table class="table table-bordered align-middle text-center contratos-table">
                                <thead class="table-secondary">
                                    <tr>
                                        <th class="w-50px">#</th>
                                        <th>Empresa</th>
                                        <th>Tipo</th>
                                        <th>Inicio contratación</th>
                                        <th>Fin contratación</th>
                                        <th class="col-actions">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1 + (((int)($currentPageContratos ?? 1) - 1) * 10); ?>
                                    <?php foreach (($contratos ?? []) as $c): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td><?= h($c['razon_social'] ?? '') ?></td>
                                            <td><?= h($c['tipo_contrato'] ?? '') ?></td>
                                            <td><?= h($c['inicio_contratacion'] ?? '') ?></td>
                                            <td><?= h($c['fin_contratacion'] ?? '') ?></td>
                                            <td class="col-actions">
                                                <div class="actions-nowrap">
                                                    <a class="btn btn-primary btn-sm shared-table-action-btn"
                                                        href="?view=ver_medidas&id_empresa=<?= (int)($c['id_empresa'] ?? 0) ?>"
                                                        title="Ver medidas">Ver medidas</a>
                                                    <a class="btn btn-success btn-sm shared-table-action-btn"
                                                        href="../model/empresa.php?view=edit_contratos&id_contrato=<?= (int)($c['id_contrato_empresa'] ?? 0) ?><?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?><?= $fromParam ?>"
                                                        title="Editar contrato">Editar</a>
                                                    <?php if (!$isTecnico): ?>
                                                    <form method="post" action="../controller/empresa_controller.php" class="d-inline"
                                                        onsubmit="return confirm('¿Seguro que quieres eliminar este contrato?');">
                                                        <input type="hidden" name="accion" value="delete_contratos">
                                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="id_contrato_empresa" value="<?= (int)($c['id_contrato_empresa'] ?? 0) ?>">
                                                        <?php if (($idEmpresaContexto ?? 0) > 0): ?>
                                                            <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaContexto ?>">
                                                        <?php endif; ?>
                                                        <?php if (($tipoContratoFiltro ?? '') !== ''): ?>
                                                            <input type="hidden" name="tipo_contrato" value="<?= h((string)$tipoContratoFiltro) ?>">
                                                        <?php endif; ?>
                                                        <?php if (($fromPanel ?? '') !== ''): ?>
                                                            <input type="hidden" name="from" value="<?= h((string)$fromPanel) ?>">
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline-danger btn-sm shared-table-action-btn" type="submit" title="Eliminar contrato">Eliminar</button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($contratos)): ?>
                                        <tr>
                                            <td colspan="6" class="text-muted">No hay contratos.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php
                        $perPageContratos = 10;
                        $totalContratos = (int)($totalContratos ?? count($contratos ?? []));
                        $currentPageContratos = (int)($currentPageContratos ?? 1);
                        $totalPagesContratos = (int)($totalPagesContratos ?? 1);

                        $startContratos = ($totalContratos === 0) ? 0 : (($currentPageContratos - 1) * $perPageContratos + 1);
                        $endContratos = min($currentPageContratos * $perPageContratos, $totalContratos);

                        $qParamContratos = (string)($searchContratoQ ?? '');
                        ?>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                            <div class="text-muted small">
                                Mostrando <?= $startContratos ?> a <?= $endContratos ?> de <?= $totalContratos ?> Entradas
                            </div>

                            <nav aria-label="Paginación contratos">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $prevDisabledContratos = ($currentPageContratos <= 1) ? 'disabled' : '';
                                    $prevPageContratos = max(1, $currentPageContratos - 1);

                                    $nextDisabledContratos = ($currentPageContratos >= $totalPagesContratos) ? 'disabled' : '';
                                    $nextPageContratos = min($totalPagesContratos, $currentPageContratos + 1);
                                    ?>

                                    <li class="page-item <?= $prevDisabledContratos ?>">
                                        <a class="page-link"
                                            href="../model/empresa.php?view=ver_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?>&page=<?= $prevPageContratos ?>&q=<?= urlencode($qParamContratos) ?><?= $fromParam ?>">Anterior</a>
                                    </li>

                                    <?php for ($p = 1; $p <= $totalPagesContratos; $p++): ?>
                                        <li class="page-item <?= ($p === $currentPageContratos) ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="../model/empresa.php?view=ver_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?>&page=<?= $p ?>&q=<?= urlencode($qParamContratos) ?><?= $fromParam ?>"><?= $p ?></a>
                                            </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $nextDisabledContratos ?>">
                                        <a class="page-link"
                                            href="../model/empresa.php?view=ver_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?>&page=<?= $nextPageContratos ?>&q=<?= urlencode($qParamContratos) ?><?= $fromParam ?>">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php elseif ($view === 'edit_contratos'): ?>

                    <h6 class="mb-3">Editar Servicios</h6>

                    <?php if (!empty($editContratoError)): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= h($editContratoError) ?></div>
                    <?php endif; ?>

                    <?php if ($selectedContrato === null): ?>
                        <div class="alert alert-warning">Contrato no encontrado.</div>
                        <a class="btn btn-outline-secondary w-100" href="../model/empresa.php?view=ver_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?><?= $fromParam ?>">Volver</a>
                    <?php else: ?>
                        <form method="post" action="../controller/empresa_controller.php" class="vstack gap-2">
                            <input type="hidden" name="accion" value="edit_contratos">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id_contrato_empresa" value="<?= (int)($selectedContrato['id_contrato_empresa'] ?? 0) ?>">
                            <input type="hidden" name="id_empresa" value="<?= (int)($selectedContrato['id_empresa'] ?? 0) ?>">
                            <?php if (($tipoContratoFiltro ?? '') !== ''): ?>
                                <input type="hidden" name="tipo_contrato_context" value="<?= h((string)$tipoContratoFiltro) ?>">
                            <?php endif; ?>
                            <?php if (($fromPanel ?? '') !== ''): ?>
                                <input type="hidden" name="from" value="<?= h((string)$fromPanel) ?>">
                            <?php endif; ?>

                            <div>
                                <label class="form-label">Empresa</label>
                                <input class="form-control" type="text" value="<?= h((string)($selectedContrato['empresa_nombre'] ?? '')) ?>" readonly>
                            </div>

                            <div>
                                <label class="form-label">Tipo de Servicio</label>
                                <select class="form-select js-tipo-contrato" name="tipo_contrato" required>
                                    <?php foreach (($tiposContrato ?? []) as $tipo): ?>
                                        <option value="<?= h($tipo) ?>" <?= (($selectedContrato['tipo_contrato'] ?? 'PLAN IGUALDAD') === $tipo) ? 'selected' : '' ?>>
                                            <?= h($tipo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php $tipoContratoActualEdit = strtoupper(trim((string)($selectedContrato['tipo_contrato'] ?? 'PLAN IGUALDAD'))); ?>
                            <div class="maintenance-fields border rounded p-3 bg-light" style="display: <?= ($tipoContratoActualEdit === 'MANTENIMIENTO') ? 'block' : 'none' ?>;">
                                <div class="small text-muted mb-2">Estos datos solo son obligatorios cuando el servicio es de Mantenimiento.</div>
                                <div>
                                    <label class="form-label">Fecha inicio vigencia</label>
                                    <input class="form-control js-contrato-plan-date" type="date" name="inicio_plan" value="<?= h($selectedContrato['inicio_plan'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label">Fecha fin vigencia</label>
                                    <input class="form-control js-contrato-plan-date" type="date" name="fin_plan" value="<?= h($selectedContrato['fin_plan'] ?? '') ?>">
                                </div>

                                <div class="border rounded p-3 bg-light mt-3">
                                    <label class="form-label fw-semibold">Áreas del Plan y sus medidas</label>
                                    <div class="small text-muted mb-2">Marca un Área para desplegar y elegir sus medidas.</div>

                                    <?php foreach (($areasPlanContrato ?? []) as $area): ?>
                                        <?php
                                        $areaId      = (int)$area['id_plan'];
                                        $checkedArea = in_array($areaId, array_map('intval', $selectedContrato['areas'] ?? []), true);
                                        $medidasArea = $medidasPorAreaContrato[$areaId] ?? [];
                                        ?>
                                        <div class="border rounded p-2 mb-2 bg-white">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input js-contrato-area-check"
                                                    type="checkbox"
                                                    id="contrato_area_<?= $areaId ?>"
                                                    name="areas[]"
                                                    value="<?= $areaId ?>"
                                                    data-area="<?= $areaId ?>"
                                                    <?= $checkedArea ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-semibold" for="contrato_area_<?= $areaId ?>">
                                                    <?= h($area['nombre']) ?>
                                                </label>
                                            </div>
                                            <div class="ms-4 mt-2 js-contrato-medidas-group" data-area="<?= $areaId ?>"
                                                style="display: <?= $checkedArea ? 'block' : 'none' ?>;">
                                                <?php if (empty($medidasArea)): ?>
                                                    <div class="small text-muted">Sin medidas para esta Área del Plan.</div>
                                                <?php else: ?>
                                                    <?php foreach ($medidasArea as $m): ?>
                                                        <?php $idMedida = (int)$m['id_medida']; ?>
                                                        <div class="form-check mb-1">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                id="contrato_medida_<?= $idMedida ?>"
                                                                name="medidas[<?= $areaId ?>][]"
                                                                value="<?= $idMedida ?>"
                                                                <?= $checkedArea ? '' : 'disabled' ?>
                                                                <?= in_array($idMedida, $selectedContrato['medidas'][$areaId] ?? []) ? 'checked' : '' ?>
                                                            >
                                                            <label class="form-check-label" for="contrato_medida_<?= $idMedida ?>">
                                                                <?= h($m['descripcion']) ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <div class="mt-3 pt-2 border-top">
                                                    <label class="form-label small fw-semibold">Agregar medida personalizada</label>
                                                    <input 
                                                        type="text" 
                                                        class="form-control form-control-sm" 
                                                        name="medidas_personalizadas[<?= $areaId ?>]" 
                                                        placeholder="Descripción de la nueva medida"
                                                        <?= !$checkedArea ? 'disabled' : '' ?>
                                                        value="<?= h($selectedContrato['medidas_personalizadas'][$areaId] ?? '') ?>"
                                                    >
                                                    <small class="text-muted d-block mt-1">Escribe una medida que no esté en la lista anterior</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Inicio contratación</label>
                                <input class="form-control" type="date" name="inicio_contratacion" required value="<?= h($selectedContrato['inicio_contratacion'] ?? '') ?>">
                            </div>

                            <div>
                                <label class="form-label">Fin contratación</label>
                                <input class="form-control" type="date" name="fin_contratacion" required value="<?= h($selectedContrato['fin_contratacion'] ?? '') ?>">
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary w-100" type="submit">Guardar cambios</button>
                                <a class="btn btn-outline-secondary w-100" href="../model/empresa.php?view=ver_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?><?= $fromParam ?>">Volver</a>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php elseif ($view === 'add_contratos'): ?>

                    <h6 class="mb-3"><?= (($tipoContratoForzadoAdd ?? '') === 'PLAN IGUALDAD') ? 'Añadir Plan igualdad' : ((($tipoContratoForzadoAdd ?? '') === 'MANTENIMIENTO') ? 'Añadir Mantenimiento' : 'Añadir Servicios') ?></h6>

                    <?php if (!empty($contratoError)): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= h($contratoError) ?></div>
                    <?php endif; ?>

                    <form method="post" action="../controller/empresa_controller.php" class="vstack gap-2">
                        <input type="hidden" name="accion" value="add_contratos">
                        <?= csrf_input() ?>
                        <?php if (($fromPanel ?? '') !== ''): ?>
                            <input type="hidden" name="from" value="<?= h((string)$fromPanel) ?>">
                        <?php endif; ?>

                        <div>
                            <label class="form-label">Empresa</label>
                            <select class="form-select" name="id_empresa" required>
                                <option value="">-- seleccionar Empresa --</option>
                                <?php foreach (($empresasForContrato ?? []) as $e): ?>
                                    <option value="<?= (int)$e['id_empresa'] ?>"
                                        <?= ((int)($addContratoOld['id_empresa'] ?? 0) === (int)$e['id_empresa']) ? 'selected' : '' ?>>
                                        <?= h($e['razon_social']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Tipo de Servicio</label>
                            <?php if (($tipoContratoForzadoAdd ?? '') !== ''): ?>
                                <input type="hidden" name="tipo_contrato" value="<?= h((string)$tipoContratoForzadoAdd) ?>">
                                <input class="form-control" type="text" value="<?= h((string)$tipoContratoForzadoAdd) ?>" readonly>
                            <?php else: ?>
                                <select class="form-select js-tipo-contrato" name="tipo_contrato" required>
                                    <?php foreach (($tiposContrato ?? []) as $tipo): ?>
                                        <option value="<?= h($tipo) ?>" <?= (($addContratoOld['tipo_contrato'] ?? 'PLAN IGUALDAD') === $tipo) ? 'selected' : '' ?>>
                                            <?= h($tipo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <?php $tipoContratoActualAdd = strtoupper(trim((string)((($tipoContratoForzadoAdd ?? '') !== '') ? $tipoContratoForzadoAdd : ($addContratoOld['tipo_contrato'] ?? 'PLAN IGUALDAD')))); ?>
                        <div class="maintenance-fields border rounded p-3 bg-light" style="display: <?= ($tipoContratoActualAdd === 'MANTENIMIENTO') ? 'block' : 'none' ?>;">
                            <div class="small text-muted mb-2">Estos datos solo son obligatorios cuando el servicio es de Mantenimiento.</div>
                            <div>
                                <label class="form-label">Fecha inicio vigencia</label>
                                <input class="form-control js-contrato-plan-date" type="date" name="inicio_plan" value="<?= h($addContratoOld['inicio_plan'] ?? '') ?>">
                            </div>

                            <div>
                                <label class="form-label">Fecha fin vigencia</label>
                                <input class="form-control js-contrato-plan-date" type="date" name="fin_plan" value="<?= h($addContratoOld['fin_plan'] ?? '') ?>">
                            </div>

                            <div class="border rounded p-3 bg-light mt-3">
                                <label class="form-label fw-semibold">Áreas del Plan y sus medidas</label>
                                <div class="small text-muted mb-2">Marca un Área para desplegar y elegir sus medidas.</div>

                                <?php foreach (($areasPlanContrato ?? []) as $area): ?>
                                    <?php
                                    $areaId      = (int)$area['id_plan'];
                                    $checkedArea = in_array($areaId, array_map('intval', $addContratoOld['areas'] ?? []), true);
                                    $medidasArea = $medidasPorAreaContrato[$areaId] ?? [];
                                    ?>
                                    <div class="border rounded p-2 mb-2 bg-white">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input js-contrato-area-check"
                                                type="checkbox"
                                                id="contrato_area_<?= $areaId ?>"
                                                name="areas[]"
                                                value="<?= $areaId ?>"
                                                data-area="<?= $areaId ?>"
                                                <?= $checkedArea ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="contrato_area_<?= $areaId ?>">
                                                <?= h($area['nombre']) ?>
                                            </label>
                                        </div>
                                        <div class="ms-4 mt-2 js-contrato-medidas-group" data-area="<?= $areaId ?>"
                                            style="display: <?= $checkedArea ? 'block' : 'none' ?>;">
                                            <?php if (empty($medidasArea)): ?>
                                                <div class="small text-muted">Sin medidas para esta Área del Plan.</div>
                                            <?php else: ?>
                                                <?php foreach ($medidasArea as $m): ?>
                                                    <?php $idMedida = (int)$m['id_medida']; ?>
                                                    <div class="form-check mb-1">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            id="contrato_medida_<?= $idMedida ?>"
                                                            name="medidas[<?= $areaId ?>][]"
                                                            value="<?= $idMedida ?>"
                                                            <?= $checkedArea ? '' : 'disabled' ?>>
                                                        <label class="form-check-label" for="contrato_medida_<?= $idMedida ?>">
                                                            <?= h($m['descripcion']) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <div class="mt-3 pt-2 border-top">
                                                <label class="form-label small fw-semibold">Agregar medida personalizada</label>
                                                <input 
                                                    type="text" 
                                                    class="form-control form-control-sm" 
                                                    name="medidas_personalizadas[<?= $areaId ?>]" 
                                                    placeholder="Descripción de la nueva medida"
                                                    <?= !$checkedArea ? 'disabled' : '' ?>
                                                    value="<?= h($addContratoOld['medidas_personalizadas'][$areaId] ?? '') ?>"
                                                >
                                                <small class="text-muted d-block mt-1">Escribe una medida que no esté en la lista anterior</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Inicio contratación</label>
                            <input class="form-control" type="date" name="inicio_contratacion" required value="<?= h($addContratoOld['inicio_contratacion'] ?? '') ?>">
                        </div>

                        <div>
                            <label class="form-label">Fin contratación</label>
                            <input class="form-control" type="date" name="fin_contratacion" required value="<?= h($addContratoOld['fin_contratacion'] ?? '') ?>">
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary w-100" type="submit">Guardar Servicios</button>
                            <a class="btn btn-outline-secondary w-100" href="../model/empresa.php?view=ver_contratos<?= ($idEmpresaContexto > 0) ? '&id_empresa=' . (int)$idEmpresaContexto : '' ?><?= (($tipoContratoFiltro ?? '') !== '') ? '&tipo_contrato=' . urlencode((string)$tipoContratoFiltro) : '' ?><?= $fromParam ?>">Volver</a>
                        </div>
                    </form>

                <?php endif; ?>

                </div>
        </div>
        </main>

    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // add_medidas: checkboxes de área
        document.querySelectorAll('.js-area-check').forEach(function(check) {
            check.addEventListener('change', function() {
                var areaId = this.getAttribute('data-area');
                var group = document.querySelector('.js-medidas-group[data-area="' + areaId + '"]');
                if (!group) return;
                if (this.checked) {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                    group.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = false;
                        cb.disabled = true;
                    });
                }
            });
        });

        // edit_plan: mismo comportamiento visual que add_medidas
        function syncEditPlanArea() {
            document.querySelectorAll('.js-edit-medidas-group').forEach(function(g) {
                var areaId = g.getAttribute('data-area');
                var areaCheck = document.querySelector('.js-edit-area-check[data-area="' + areaId + '"]');
                var isCheckedArea = areaCheck ? areaCheck.checked : false;

                g.style.display = isCheckedArea ? 'block' : 'none';
                g.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                    cb.disabled = !isCheckedArea;
                });
            });
        }

        document.querySelectorAll('.js-edit-area-check').forEach(function(check) {
            check.addEventListener('change', function() {
                syncEditPlanArea();

                var areaId = this.getAttribute('data-area');
                var group = document.querySelector('.js-edit-medidas-group[data-area="' + areaId + '"]');
                if (!group) return;

                if (!this.checked) {
                    group.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = false;
                        cb.disabled = true;
                    });
                }
            });
        });

        syncEditPlanArea();

        var tipoContratoSelect = document.querySelector('.js-tipo-contrato');
        document.querySelectorAll('.js-contrato-area-check').forEach(function(check) {
            check.addEventListener('change', function() {
                var areaId = this.getAttribute('data-area');
                var group = document.querySelector('.js-contrato-medidas-group[data-area="' + areaId + '"]');
                if (!group) return;

                var medidaChecks = group.querySelectorAll('input[type="checkbox"]');
                var medidaInput = group.querySelector('input[type="text"][name*="medidas_personalizadas"]');
                
                if (this.checked) {
                    group.style.display = 'block';
                    medidaChecks.forEach(function(cb) { cb.disabled = false; });
                    if (medidaInput) medidaInput.disabled = false;
                } else {
                    group.style.display = 'none';
                    medidaChecks.forEach(function(cb) {
                        cb.checked = false;
                        cb.disabled = true;
                    });
                    if (medidaInput) {
                        medidaInput.value = '';
                        medidaInput.disabled = true;
                    }
                }
            });
        });

        function syncContratoPlanFields() {
            var tipoSelect = document.querySelector('.js-tipo-contrato');
            var tipoInput = document.querySelector('input[name="tipo_contrato"]');
            var fields = document.querySelector('.maintenance-fields');
            if (!fields) return;

            var tipo = '';
            if (tipoSelect) {
                tipo = String(tipoSelect.value || '').toUpperCase();
            } else if (tipoInput) {
                tipo = String(tipoInput.value || '').toUpperCase();
            }
            var showPlanMedidas = (tipo === 'MANTENIMIENTO');
            fields.style.display = showPlanMedidas ? 'block' : 'none';

            fields.querySelectorAll('.js-contrato-plan-date').forEach(function(input) {
                input.required = showPlanMedidas;
                input.disabled = !showPlanMedidas;
                if (!showPlanMedidas) input.value = '';
            });

            fields.querySelectorAll('.js-contrato-area-check').forEach(function(areaCheck) {
                areaCheck.disabled = !showPlanMedidas;
                if (!showPlanMedidas) {
                    areaCheck.checked = false;
                }
            });

            fields.querySelectorAll('.js-contrato-medidas-group').forEach(function(group) {
                if (!showPlanMedidas) {
                    group.style.display = 'none';
                }

                group.querySelectorAll('input').forEach(function(input) {
                    input.disabled = !showPlanMedidas;
                    if (!showPlanMedidas) {
                        if (input.type === 'checkbox') {
                            input.checked = false;
                        } else {
                            input.value = '';
                        }
                    }
                });
            });
        }

        if (tipoContratoSelect) {
            tipoContratoSelect.addEventListener('change', syncContratoPlanFields);
        }
        syncContratoPlanFields();

        function toggleTecnicos(scope, on) {
            document.querySelectorAll('.tecnico-checkbox-' + scope).forEach(function(cb) {
                cb.checked = on;
            });
        }

        function initTecnicosFilter(filterId, listId) {
            var filter = document.getElementById(filterId);
            var list = document.getElementById(listId);

            if (!filter || !list) return;

            filter.addEventListener('input', function() {
                var q = String(filter.value || '').trim().toLowerCase();
                list.querySelectorAll('[data-name]').forEach(function(row) {
                    var name = String(row.getAttribute('data-name') || '');
                    row.style.display = name.includes(q) ? '' : 'none';
                });
            });
        }

        initTecnicosFilter('tecnicosFilterAdd', 'tecnicosListAdd');

        var flashMsg = document.querySelector('.js-flash-msg');
        if (flashMsg && typeof bootstrap !== 'undefined') {
            window.setTimeout(function() {
                var alertInstance = bootstrap.Alert.getOrCreateInstance(flashMsg);
                alertInstance.close();
            }, 3500);
        }
    </script>
</body>

</html>
