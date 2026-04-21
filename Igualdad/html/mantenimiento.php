<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mantenimiento</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="<?= (strtoupper((string)($_SESSION['user']['rol'] ?? '')) === 'CLIENTE') ? '../css/empresa.css' : '../css/admin.css' ?>">
</head>
<body class="bg-light">
<?php
$view = $view ?? 'ver_formacion';
$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
?>
<div class="container-fluid py-4">
    <div class="row g-3">
        <aside class="col-12 col-lg-3 col-xl-2">
            <div class="card shadow-sm border-0 sidebar">
                <div class="card-body">
                    <h5 class="mb-1">Mantenimiento</h5>
                    <div class="text-muted small mb-3">
                        Sesión: <strong><?= h($adminUsername ?? 'usuario') ?></strong>
                        <?php if (!empty($adminEmail)): ?><div>Email: <strong><?= h($adminEmail) ?></strong></div><?php endif; ?>
                        <?php if ($rol !== ''): ?><div>Rol: <strong><?= h($rol) ?></strong></div><?php endif; ?>
                    </div>

                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-dark text-start" href="../model/empresa.php?view=ver_empresas">Inicio</a>
                        <?php if (!empty($maintenanceEmpresa['id_empresa'])): ?>
                            <a class="btn btn-outline-dark text-start" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        <?php endif; ?>
                        <?php foreach (($maintenanceSidebarItems ?? []) as $sidebarItem): ?>
                            <a class="btn btn-outline-dark text-start <?= ($view === ($sidebarItem['view'] ?? '')) ? 'active' : '' ?>" href="mantenimiento.php?view=<?= h((string)($sidebarItem['view'] ?? '')) ?>&id_empresa=<?= (int)($maintenanceEmpresa['id_empresa'] ?? 0) ?>"><?= h((string)($sidebarItem['label'] ?? '')) ?></a>
                        <?php endforeach; ?>
                        <a class="btn btn-outline-secondary text-start" href="../php/logout.php">Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </aside>

        <main class="col-12 col-lg-9 col-xl-10">
            <div class="card panel mx-auto shadow-sm border-0 panel-wide">
                <div class="card-body p-4">
                    <header class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="mb-0">Mantenimiento</h4>
                    </header>

                    <?php if (!empty($_GET['msg'])): ?>
                        <div class="alert alert-info py-2"><?= h($_GET['msg']) ?></div>
                    <?php endif; ?>

                    <?php if ($maintenanceEmpresa === null): ?>
                        <div class="alert alert-warning">No se encontró la empresa seleccionada.</div>
                    <?php elseif ($view === 'ver_formacion'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Formación</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Formación disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addFormacionModal">Agregar Formación</button>
                            </div>

                            <div class="modal fade" id="addFormacionModal" tabindex="-1" aria-labelledby="addFormacionModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addFormacionModalLabel">Agregar Formación</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_formacion">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Nombre</label>
                                                        <input class="form-control" name="nombre" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Fecha inicio</label>
                                                        <input class="form-control" type="date" name="fecha_inicio" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Fecha fin</label>
                                                        <input class="form-control" type="date" name="fecha_fin" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Dentro/Fuera Laboral</label>
                                                        <select class="form-select" name="laboral" required>
                                                            <option value="">-- Seleccionar --</option>
                                                            <option value="Dentro">Dentro</option>
                                                            <option value="Fuera">Fuera</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Voluntaria/Obligatoria</label>
                                                        <select class="form-select" name="voluntaria_obligatoria" required>
                                                            <option value="">-- Seleccionar --</option>
                                                            <option value="Voluntaria">Voluntaria</option>
                                                            <option value="Obligatoria">Obligatoria</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Modalidad</label>
                                                        <input class="form-control" name="modalidad" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">N° Horas</label>
                                                        <input class="form-control" type="number" min="0" name="n_horas" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">N° Hombres</label>
                                                        <input class="form-control" type="number" min="0" name="n_hombres" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">N° Mujeres</label>
                                                        <input class="form-control" type="number" min="0" name="n_mujeres" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Informado plantilla</label>
                                                        <input class="form-control" name="informado_plantilla" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Criterio selección</label>
                                                        <input class="form-control" name="criterio_seleccion" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Formación</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_formacion">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_formacion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Fecha Inicio</th>
                                            <th>Fecha Fin</th>
                                            <th>Dentro/Fuera Laboral</th>
                                            <th>Voluntaria/Obligatoria</th>
                                            <th>Modalidad</th>
                                            <th>N° Horas</th>
                                            <th>N° Hombres</th>
                                            <th>N° Mujeres</th>
                                            <th>Informado plantilla</th>
                                            <th>Criterio selección</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_formacion'] ?? 0) ?></td>
                                                <td><?= h($row['nombre'] ?? '') ?></td>
                                                <td><?= h($row['fecha_inicio'] ?? '') ?></td>
                                                <td><?= h($row['fecha_fin'] ?? '') ?></td>
                                                <td><?= h($row['laboral'] ?? '') ?></td>
                                                <td><?= h($row['voluntaria_obligatoria'] ?? '') ?></td>
                                                <td><?= h($row['modalidad'] ?? '') ?></td>
                                                <td><?= (int)($row['n_horas'] ?? 0) ?></td>
                                                <td><?= (int)($row['n_hombres'] ?? 0) ?></td>
                                                <td><?= (int)($row['n_mujeres'] ?? 0) ?></td>
                                                <td><?= h($row['informado_plantilla'] ?? '') ?></td>
                                                <td><?= h($row['criterio_seleccion'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="12" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación formación">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_formacion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_formacion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_formacion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_infra'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Infrarrepresentación femenina</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Infrarrepresentación femenina disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addInfraModal">Agregar Infrarrepresentación femenina</button>
                            </div>

                            <div class="modal fade" id="addInfraModal" tabindex="-1" aria-labelledby="addInfraModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addInfraModalLabel">Agregar Infrarrepresentación femenina</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_infra">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Plantilla mujeres</label>
                                                        <input class="form-control" type="number" min="0" name="plantilla_mujeres" value="0" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Plantilla hombres</label>
                                                        <input class="form-control" type="number" min="0" name="plantilla_hombres" value="0" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Infrarrepresentación femenina</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_infra">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_infra&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Plantilla mujeres</th>
                                            <th>Plantilla hombres</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_infra'] ?? 0) ?></td>
                                                <td><?= (int)($row['plantilla_mujeres'] ?? 0) ?></td>
                                                <td><?= (int)($row['plantilla_hombres'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="3" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación infrarrepresentación femenina">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_infra&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_infra&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_infra&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_acoso'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Acoso</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Acoso disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addAcosoModal">Agregar Acoso</button>
                            </div>

                            <div class="modal fade" id="addAcosoModal" tabindex="-1" aria-labelledby="addAcosoModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addAcosoModalLabel">Agregar Acoso</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_acoso">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label">Incidente</label>
                                                        <input class="form-control" name="incidente" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Fecha alta</label>
                                                        <input class="form-control" type="date" name="fecha_alta" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Procedimiento</label>
                                                        <input class="form-control" name="procedimiento" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Grado de incidencia</label>
                                                        <input class="form-control" name="grado_incidencia" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Acciones</label>
                                                        <input class="form-control" name="acciones">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Acoso</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_acoso">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_acoso&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Incidente</th>
                                            <th>Procedimiento</th>
                                            <th>Grado incidencia</th>
                                            <th>Fecha alta</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_acoso'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['incidente'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['procedimiento'] ?? '') ?></td>
                                                <td><?= h($row['grado_incidencia'] ?? '') ?></td>
                                                <td><?= h($row['fecha_alta'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['acciones'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="6" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación acoso">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_acoso&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_acoso&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_acoso&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_violencia'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Violencia de género</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Violencia de género disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addViolenciaModal">Agregar Violencia de género</button>
                            </div>

                            <div class="modal fade" id="addViolenciaModal" tabindex="-1" aria-labelledby="addViolenciaModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addViolenciaModalLabel">Agregar Violencia de género</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_violencia">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Fecha alta</label>
                                                        <input class="form-control" type="date" name="fecha_alta" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Solicita mujeres</label>
                                                        <input class="form-control" type="number" min="0" name="solicita_mujeres" value="0" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Acciones</label>
                                                        <input class="form-control" name="acciones" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Observaciones</label>
                                                        <input class="form-control" name="observaciones" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Violencia de género</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_violencia">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_violencia&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Acciones</th>
                                            <th>Observaciones</th>
                                            <th>Fecha alta</th>
                                            <th>Solicita mujeres</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_violencia'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['acciones'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['observaciones'] ?? '') ?></td>
                                                <td><?= h($row['fecha_alta'] ?? '') ?></td>
                                                <td><?= (int)($row['solicita_mujeres'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="5" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación violencia de género">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_violencia&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_violencia&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_violencia&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_retribuciones'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Retribuciones</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Retribuciones disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addRetribucionesModal">Agregar Retribuciones</button>
                            </div>

                            <div class="modal fade" id="addRetribucionesModal" tabindex="-1" aria-labelledby="addRetribucionesModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addRetribucionesModalLabel">Agregar Retribuciones</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_retribuciones">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label">Permisos</label>
                                                        <input class="form-control" name="permisos" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Número mujeres</label>
                                                        <input class="form-control" type="number" min="0" name="num_mujeres" value="0" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Número hombres</label>
                                                        <input class="form-control" type="number" min="0" name="num_hombres" value="0" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Retribuciones</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_retribuciones">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_retribuciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Permisos</th>
                                            <th>Número mujeres</th>
                                            <th>Número hombres</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_retribuciones'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['permisos'] ?? '') ?></td>
                                                <td><?= (int)($row['num_mujeres'] ?? 0) ?></td>
                                                <td><?= (int)($row['num_hombres'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="4" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación retribuciones">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_retribuciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_retribuciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_retribuciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_promocion'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Promoción y ascenso profesional</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Promoción y ascenso profesional disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addPromocionModal">Agregar Promoción y ascenso profesional</button>
                            </div>

                            <div class="modal fade" id="addPromocionModal" tabindex="-1" aria-labelledby="addPromocionModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addPromocionModalLabel">Agregar Promoción y ascenso profesional</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_promocion">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label">Puesto origen</label><input class="form-control" name="puesto_origen" required></div>
                                                    <div class="col-md-6"><label class="form-label">Puesto destino</label><input class="form-control" name="puesto_destino" required></div>
                                                    <div class="col-md-3"><label class="form-label">Aumento económico</label><input class="form-control" type="number" min="0" name="aumento_economico" value="0" required></div>
                                                    <div class="col-md-3"><label class="form-label">Nº candidaturas</label><input class="form-control" type="number" min="0" name="n_candidaturas" value="0" required></div>
                                                    <div class="col-md-3"><label class="form-label">Nº hombres</label><input class="form-control" type="number" min="0" name="n_hombres" value="0" required></div>
                                                    <div class="col-md-3"><label class="form-label">Nº mujeres</label><input class="form-control" type="number" min="0" name="n_mujeres" value="0" required></div>
                                                    <div class="col-md-6"><label class="form-label">Responsable</label><input class="form-control" name="responsable" required></div>
                                                    <div class="col-md-6"><label class="form-label">Cargo responsable</label><input class="form-control" name="cargo_responsable" required></div>
                                                    <div class="col-md-3"><label class="form-label">Género responsable</label><select class="form-select" name="genero_responsable" required><option value="">-- Seleccionar --</option><option value="Masculino">Masculino</option><option value="Femenino">Femenino</option></select></div>
                                                    <div class="col-md-3"><label class="form-label">Género promocionado</label><select class="form-select" name="genero_promocionado" required><option value="">-- Seleccionar --</option><option value="Masculino">Masculino</option><option value="Femenino">Femenino</option></select></div>
                                                    <div class="col-md-3"><label class="form-label">Interna / Externa</label><select class="form-select" name="interna_externa"><option value="">-- Seleccionar --</option><option value="Interna">Interna</option><option value="Externa">Externa</option></select></div>
                                                    <div class="col-md-3"><label class="form-label">Fecha de alta</label><input class="form-control" type="date" name="fecha_de_alta" required></div>
                                                    <div class="col-md-4"><label class="form-label">Contrato inicial</label><input class="form-control" name="contrato_inicial" required></div>
                                                    <div class="col-md-4"><label class="form-label">Contrato final</label><input class="form-control" name="contrato_final" required></div>
                                                    <div class="col-md-4"><label class="form-label">Tipo promoción</label><input class="form-control" name="tipo_promocion" required></div>
                                                    <div class="col-md-4"><label class="form-label">Porcentaje jornada</label><input class="form-control" type="number" min="0" name="porcentaje_jornada" value="0" required></div>
                                                    <div class="col-md-4"><label class="form-label">Conciliación</label><select class="form-select" name="disfruta_conciliacion"><option value="">-- Seleccionar --</option><option value="1">Sí</option><option value="0">No</option></select></div>
                                                    <div class="col-12"><label class="form-label">Criterio</label><input class="form-control" name="criterio" required></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Promoción y ascenso profesional</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_promocion">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_promocion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Puesto origen</th>
                                            <th>Puesto destino</th>
                                            <th>Aumento económico</th>
                                            <th>Responsable</th>
                                            <th>Género promocionado</th>
                                            <th>Fecha alta</th>
                                            <th>Tipo promoción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_promocion'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['puesto_origen'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['puesto_destino'] ?? '') ?></td>
                                                <td><?= (int)($row['aumento_economico'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['responsable'] ?? '') ?></td>
                                                <td><?= h($row['genero_promocionado'] ?? '') ?></td>
                                                <td><?= h($row['fecha_de_alta'] ?? '') ?></td>
                                                <td><?= h($row['tipo_promocion'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="8" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación promoción y ascenso profesional">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_promocion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_promocion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_promocion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_condiciones'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Condiciones de trabajo</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Condiciones de trabajo disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addCondicionesModal">Agregar Condiciones de trabajo</button>
                            </div>

                            <div class="modal fade" id="addCondicionesModal" tabindex="-1" aria-labelledby="addCondicionesModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addCondicionesModalLabel">Agregar Condiciones de trabajo</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_condiciones">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label">Nº conversiones contrato</label><input class="form-control" name="n_conversiones_contrato" required></div>
                                                    <div class="col-md-6"><label class="form-label">Nº jornadas ampliadas</label><input class="form-control" name="n_jornadas_ampliadas" required></div>
                                                    <div class="col-md-6"><label class="form-label">Evaluación condiciones trabajo</label><input class="form-control" name="evaluacion_condiciones_trabajo" required></div>
                                                    <div class="col-md-6"><label class="form-label">Muestreo</label><input class="form-control" name="muestreo" required></div>
                                                    <div class="col-md-4"><label class="form-label">Contrataciones realizadas</label><input class="form-control" type="number" min="0" name="contrataciones_realizadas" value="0" required></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Condiciones de trabajo</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_condiciones">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_condiciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nº conversiones contrato</th>
                                            <th>Nº jornadas ampliadas</th>
                                            <th>Evaluación condiciones trabajo</th>
                                            <th>Muestreo</th>
                                            <th>Contrataciones realizadas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_condiciones'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['n_conversiones_contrato'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['n_jornadas_ampliadas'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['evaluacion_condiciones_trabajo'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['muestreo'] ?? '') ?></td>
                                                <td><?= (int)($row['contrataciones_realizadas'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="6" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación condiciones de trabajo">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_condiciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_condiciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_condiciones&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_salud'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Salud laboral</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Salud laboral disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addSaludModal">Agregar Salud laboral</button>
                            </div>

                            <div class="modal fade" id="addSaludModal" tabindex="-1" aria-labelledby="addSaludModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addSaludModalLabel">Agregar Salud laboral</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_salud">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                                                    <div class="col-md-6"><label class="form-label">Procedencia</label><input class="form-control" name="procedencia" required></div>
                                                    <div class="col-12"><label class="form-label">Observaciones</label><input class="form-control" name="observaciones"></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Salud laboral</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_salud">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_salud&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Procedencia</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_salud'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['nombre'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['procedencia'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['observaciones'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="4" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación salud laboral">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_salud&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_salud&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_salud&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_responsable_igualdad'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Responsable de igualdad</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay áreas de Responsable de igualdad disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addResponsableIgualdadModal">Agregar Responsable de igualdad</button>
                            </div>

                            <div class="modal fade" id="addResponsableIgualdadModal" tabindex="-1" aria-labelledby="addResponsableIgualdadModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addResponsableIgualdadModalLabel">Agregar Responsable de igualdad</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_responsable_igualdad">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                                                    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Responsable de igualdad</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_responsable_igualdad">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_responsable_igualdad&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_responsable_de_igualdad'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['nombre'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['email'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="3" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación responsable de igualdad">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_responsable_igualdad&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_responsable_igualdad&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_responsable_igualdad&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($view === 'ver_seleccion'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Proceso de selección y contratación</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <?php if (empty($maintenanceMedidas)): ?>
                            <div class="alert alert-warning">No hay medidas de Proceso de selección y contratación disponibles para esta empresa.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addSeleccionModal">Agregar Proceso de selección y contratación</button>
                            </div>

                            <div class="modal fade" id="addSeleccionModal" tabindex="-1" aria-labelledby="addSeleccionModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addSeleccionModalLabel">Agregar Proceso de selección y contratación</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <form method="post" action="../controller/mantenimiento_controller.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="accion" value="add_seleccion">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label">Puesto actual</label><input class="form-control" name="puesto_actual" required></div>
                                                    <div class="col-md-3"><label class="form-label">Fecha alta</label><input class="form-control" type="date" name="fecha_alta" required></div>
                                                    <div class="col-md-3"><label class="form-label">Responsable Int/Ext</label><input class="form-control" name="responsable_Int_Ext" required></div>
                                                    <div class="col-md-6"><label class="form-label">Responsable</label><input class="form-control" name="responsable" required></div>
                                                    <div class="col-md-3"><label class="form-label">Género cargo responsable</label><select class="form-select" name="crgo_responsable" required><option value="">-- Seleccionar --</option><option value="Masculino">Masculino</option><option value="Femenino">Femenino</option></select></div>
                                                    <div class="col-md-3"><label class="form-label">Género seleccionado</label><select class="form-select" name="gnro_seleccionado" required><option value="">-- Seleccionar --</option><option value="Masculino">Masculino</option><option value="Femenino">Femenino</option></select></div>
                                                    <div class="col-md-3"><label class="form-label">Candidatas mujeres</label><input class="form-control" type="number" min="0" name="c_mujeres" value="0" required></div>
                                                    <div class="col-md-3"><label class="form-label">Candidatos hombres</label><input class="form-control" type="number" min="0" name="c_hombres" value="0" required></div>
                                                    <div class="col-md-6"><label class="form-label">Criterio selección</label><input class="form-control" name="criterio_seleccion" required></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">Guardar Proceso de selección y contratación</button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                    <span>Entradas</span>
                                </div>

                                <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_seleccion">
                                    <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_seleccion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Puesto actual</th>
                                            <th>Fecha alta</th>
                                            <th>Responsable</th>
                                            <th>Responsable Int/Ext</th>
                                            <th>Género cargo responsable</th>
                                            <th>Género seleccionado</th>
                                            <th>C. mujeres</th>
                                            <th>C. hombres</th>
                                            <th>Criterio selección</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                            <tr>
                                                <td><?= (int)($row['id_seleccion'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['puesto_actual'] ?? '') ?></td>
                                                <td><?= h($row['fecha_alta'] ?? '') ?></td>
                                                <td class="text-start"><?= h($row['responsable'] ?? '') ?></td>
                                                <td><?= h($row['responsable_Int_Ext'] ?? '') ?></td>
                                                <td><?= h($row['crgo_responsable'] ?? '') ?></td>
                                                <td><?= h($row['gnro_seleccionado'] ?? '') ?></td>
                                                <td><?= (int)($row['c_mujeres'] ?? 0) ?></td>
                                                <td><?= (int)($row['c_hombres'] ?? 0) ?></td>
                                                <td class="text-start"><?= h($row['criterio_seleccion'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenanceRows)): ?>
                                            <tr><td colspan="10" class="text-muted">No hay información</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalRows);
                            $qParam = (string)$searchQ;
                            ?>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                                <nav aria-label="Paginación proceso de selección y contratación">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_seleccion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link" href="mantenimiento.php?view=ver_seleccion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_seleccion&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Ejercicio</h6>
                            <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Volver a empresa</a>
                        </div>

                        <div class="mb-3 p-3 border rounded bg-light">
                            <div><strong>Empresa:</strong> <?= h($maintenanceEmpresa['razon_social'] ?? '') ?></div>
                        </div>

                        <div class="mb-3">
                            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addEjercicioModal">Agregar Ejercicio</button>
                        </div>

                        <div class="modal fade" id="addEjercicioModal" tabindex="-1" aria-labelledby="addEjercicioModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addEjercicioModalLabel">Agregar Ejercicio</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form method="post" action="../controller/mantenimiento_controller.php">
                                        <div class="modal-body">
                                            <input type="hidden" name="accion" value="add_ejercicio">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label">Medida</label>
                                                    <textarea class="form-control" name="medida" rows="3" required></textarea>
                                                </div>
                                                <div class="col-md-3"><label class="form-label">Solicita mujeres</label><input class="form-control" type="number" min="0" name="solicita_mujeres" value="0" required></div>
                                                <div class="col-md-3"><label class="form-label">Solicita hombres</label><input class="form-control" type="number" min="0" name="solicita_hombres" value="0" required></div>
                                                <div class="col-md-3"><label class="form-label">Concede mujeres</label><input class="form-control" type="number" min="0" name="concede_mujeres" value="0" required></div>
                                                <div class="col-md-3"><label class="form-label">Concede hombres</label><input class="form-control" type="number" min="0" name="concede_hombres" value="0" required></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="btn btn-primary" type="submit">Guardar Ejercicio</button>
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span>Mostrar</span>
                                <select class="form-select form-select-sm" style="width: 90px;" disabled><option selected>10</option></select>
                                <span>Entradas</span>
                            </div>
                            <form method="get" action="mantenimiento.php" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="view" value="ver_ejercicio">
                                <input type="hidden" name="id_empresa" value="<?= (int)$maintenanceEmpresa['id_empresa'] ?>">
                                <label class="mb-0">Buscar:</label>
                                <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                <?php if (!empty($searchQ)): ?>
                                    <a class="btn btn-outline-danger btn-sm" href="mantenimiento.php?view=ver_ejercicio&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>">Limpiar</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>ID</th>
                                        <th>Medida</th>
                                        <th>Solicita Mujeres</th>
                                        <th>Solicita Hombres</th>
                                        <th>Concede Mujeres</th>
                                        <th>Concede Hombres</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($maintenanceRows ?? []) as $row): ?>
                                        <tr>
                                            <td><?= (int)($row['id_ejercicio'] ?? 0) ?></td>
                                            <td class="text-start"><?= h($row['medida'] ?? '') ?></td>
                                            <td><?= (int)($row['solicita_mujeres'] ?? 0) ?></td>
                                            <td><?= (int)($row['solicita_hombres'] ?? 0) ?></td>
                                            <td><?= (int)($row['concede_mujeres'] ?? 0) ?></td>
                                            <td><?= (int)($row['concede_hombres'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($maintenanceRows)): ?>
                                        <tr><td colspan="6" class="text-muted">No hay información</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php
                        $start = ($totalRows === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                        $end = min($currentPage * $perPage, $totalRows);
                        $qParam = (string)$searchQ;
                        ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                            <div class="text-muted small">Mostrando <?= $start ?> a <?= $end ?> de <?= $totalRows ?> Entradas</div>
                            <nav aria-label="Paginación ejercicio">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="mantenimiento.php?view=ver_ejercicio&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= max(1, $currentPage - 1) ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                            <a class="page-link" href="mantenimiento.php?view=ver_ejercicio&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="mantenimiento.php?view=ver_ejercicio&id_empresa=<?= (int)$maintenanceEmpresa['id_empresa'] ?>&page=<?= min($totalPages, $currentPage + 1) ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>