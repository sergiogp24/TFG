<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Empresas</title>

    <!-- CSS (reutiliza los mismos estilos del admin) -->
    <link rel="stylesheet" href="../css/empresa.css">
        <link rel="stylesheet" href="../css/admin_usuarios.css">
    <link rel="stylesheet" href="../css/admin_edit_usuario.css">
    <link rel="stylesheet" href="../css/empresa_layout.css">


    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php $view = $view ?? 'ver_empresas'; ?>

    <div class="container-fluid py-4">
        <div class="row g-3">

            <!-- SIDEBAR -->
            <aside class="col-12 col-lg-3 col-xl-2">
                <div class="card shadow-sm border-0 sidebar">
                    <div class="card-body">
                        <h5 class="mb-1">Panel Empresas</h5>

                        <div class="text-muted small mb-3">
                            Sesión: <strong><?= h($adminUsername ?? 'admin') ?></strong>
                            <?php if (!empty($adminEmail)): ?>
                                <div>Email: <strong><?= h($adminEmail) ?></strong></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-dark text-start <?= ($view === 'ver_empresas') ? 'active' : '' ?>"
                                href="empresa.php?view=ver_empresas">Inicio</a>

                            <!-- USUARIOS (link a admin.php) -->
                            <button class="btn btn-outline-dark text-start d-flex justify-content-between align-items-center"
                                type="button" data-bs-toggle="collapse" data-bs-target="#menuUsuarios"
                                aria-expanded="false">
                                <span>Usuarios</span><span>▾</span>
                            </button>

                            <div id="menuUsuarios" class="collapse">
                                <div class="d-grid gap-2 ps-3 pt-2">
                                    <a class="btn btn-outline-secondary text-start"
                                        href="admin.php?view=ver_usuarios">Ver</a>
                                    <a class="btn btn-outline-secondary text-start"
                                        href="admin.php?view=add">Añadir</a>
                                </div>
                            </div>

                            <!-- EMPRESAS -->
                            <button class="btn btn-outline-dark text-start d-flex justify-content-between align-items-center <?= in_array($view, ['ver_empresas', 'add_empresas', 'edit_empresas', 'delete_empresas', 'ver_planes', 'edit_plan', 'ver_contratos', 'add_contratos', 'edit_contratos', 'delete_contratos'], true) ? 'active' : '' ?>"
                                type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                                aria-expanded="<?= in_array($view, ['ver_empresas', 'add_empresas', 'edit_empresas', 'delete_empresas', 'ver_planes', 'edit_plan', 'ver_contratos', 'add_contratos', 'edit_contratos', 'delete_contratos'], true) ? 'true' : 'false' ?>">
                                <span>Empresas</span><span>▾</span>
                            </button>

                            <div id="menuEmpresas" class="collapse <?= in_array($view, ['ver_empresas', 'add_empresas', 'edit_empresas', 'delete_empresas', 'ver_planes', 'edit_plan', 'ver_contratos', 'add_contratos', 'edit_contratos', 'delete_contratos'], true) ? 'show' : '' ?>">
                                <div class="d-grid gap-2 ps-3 pt-2">
                                    <a class="btn btn-outline-secondary text-start" href="empresa.php?view=ver_empresas">Ver</a>
                                    <a class="btn btn-outline-secondary text-start" href="empresa.php?view=add_empresas">Añadir</a>
                                    <a class="btn btn-outline-secondary text-start" href="empresa.php?view=ver_planes">Ver Planes</a>
                                    <a class="btn btn-outline-secondary text-start" href="empresa.php?view=ver_contratos">Ver Contratos</a>
                                    <a class="btn btn-outline-secondary text-start" href="empresa.php?view=add_contratos">Añadir Contratos</a>
                                </div>
                            </div>

                            <a class="btn btn-outline-dark text-start" href="../html/index_cliente.php">
                                Subir registro retributivo
                            </a>
                            <a class="btn btn-outline-dark text-start <?= ($view === 'perfil') ? 'active' : '' ?>" href="admin.php?view=perfil">Area Privada</a>

                            <a class="btn btn-outline-secondary text-start" href="/Igualdad/php/logout.php">
                                Cerrar sesión
                            </a>
                        </div>

                    </div>
                </div>
            </aside>

            <!-- CONTENT -->
            <main class="col-12 col-lg-9 col-xl-10">
                <div class="card panel mx-auto shadow-sm border-0 <?= in_array($view, ['ver_empresas', 'ver_planes', 'ver_contratos'], true) ? 'panel-wide' : '' ?>">
                    <div class="card-body p-4">

                        <header class="d-flex align-items-center justify-content-between mb-3">
                            <h4 class="mb-0">Gestión de Empresas</h4>
                        </header>

                        <?php if (!empty($_GET['msg'])): ?>
                            <div class="alert alert-info py-2"><?= h($_GET['msg']) ?></div>
                        <?php endif; ?>

                        <?php if ($view === 'ver_empresas'): ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Listado de empresas</h6>
                                <a class="btn btn-primary btn-sm" href="empresa.php?view=add_empresas">Agregar Empresa</a>
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

                                <form method="get" action="empresa.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_empresas">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="empresa.php?view=ver_empresas">Limpiar</a>
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
                                            <th class="col-actions">#</th>
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
                                                        <!-- VER EMPRESA (si no tienes vista detalle, puedes dejarlo apuntando a ver_empresas) -->
                                                        <a class="btn btn-outline-secondary btn-sm btn-icon"
                                                            href="empresa.php?view=ver_empresas&id_empresa=<?= (int)$e['id_empresa'] ?>" title="Ver">👁</a>

                                                        <!-- EDITAR -->
                                                        <a class="btn btn-success btn-sm btn-icon"
                                                            href="empresa.php?view=edit_empresas&id_empresa=<?= (int)$e['id_empresa'] ?>" title="Editar">✏️</a>

                                                        <!-- SUBIR ARCHIVO -->
                                                        <a class="btn btn-primary btn-sm btn-icon"
                                                            href="subir_archivo_empresa.php?id_empresa=<?= (int)$e['id_empresa'] ?>" title="Subir archivo">⬆️</a>

                                                        <!-- ELIMINAR -->
                                                        <form method="post" action="../controller/empresa_controller.php" class="d-inline"
                                                            onsubmit="return confirm('¿Seguro que quieres eliminar la empresa <?= h($e['razon_social'] ?? '') ?>?');">
                                                            <input type="hidden" name="accion" value="eliminar_empresas">
                                                            <input type="hidden" name="id_empresa" value="<?= (int)$e['id_empresa'] ?>">
                                                            <button class="btn btn-outline-danger btn-sm btn-icon" type="submit" title="Eliminar">🗑</button>
                                                        </form>
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
                                                href="empresa.php?view=ver_empresas&page=<?= $prevPage ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>

                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link"
                                                    href="empresa.php?view=ver_empresas&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="page-item <?= $nextDisabled ?>">
                                            <a class="page-link"
                                                href="empresa.php?view=ver_empresas&page=<?= $nextPage ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>

                        <?php elseif ($view === 'add_empresas'): ?>

                            <h6 class="text-center mb-3">Agregar empresa</h6>

                            <form method="post" action="../controller/empresa_controller.php" class="vstack gap-2">
                                <input type="hidden" name="accion" value="add_empresas">

                                <div>
                                    <label class="form-label">Razón Social</label>
                                    <input class="form-control" name="razon_social" required>
                                </div>

                                <div>
                                    <label class="form-label">NIF</label>
                                    <input class="form-control" name="nif" required>
                                </div>

                                <div>
                                    <label class="form-label">Domicilio Social</label>
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
                                    <label class="form-label mb-1">Email</label>
                                    <input class="form-control" name="email" type="email">
                                </div>

                                <div>
                                    <label class="form-label">Teléfono</label>
                                    <input class="form-control" name="telefono">
                                </div>

                                <div>
                                    <label class="form-label">Sector</label>
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

                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary w-100" type="submit">Crear</button>
                                    <a class="btn btn-outline-secondary w-100" href="empresa.php?view=ver_empresas">Volver</a>
                                </div>
                            </form>

                        <?php elseif ($view === 'edit_empresas'): ?>

                            <h6 class="text-center mb-4">Editar empresa</h6>

                            <?php if ($selectedEmpresa === null): ?>
                                <div class="alert alert-warning">Empresa no encontrada.</div>
                                <a class="btn btn-outline-secondary w-100" href="empresa.php?view=ver_empresas">Volver</a>
                            <?php else: ?>
                                <form method="post" action="../controller/empresa_controller.php" class="edit-user-form">
                                    <input type="hidden" name="accion" value="editar_empresas">
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

                                        <div class="col-12">
                                            <div class="d-flex justify-content-center gap-4 mt-2">
                                                <button class="btn btn-primary px-5" type="submit">Actualizar</button>
                                                <a class="btn btn-danger px-5" href="empresa.php?view=ver_empresas">Cancelar</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>

                        <?php elseif ($view === 'delete_empresas'): ?>

                            <h6 class="text-center mb-3">Eliminar empresas</h6>

                            <div class="vstack gap-2">
                                <?php foreach (($empresas ?? []) as $e): ?>
                                    <form method="post" action="../controller/empresa_controller.php"
                                        class="border rounded bg-light p-2"
                                        onsubmit="return confirm('¿Seguro que quieres eliminar la empresa <?= h($e['razon_social'] ?? '') ?>?');">
                                        <input type="hidden" name="accion" value="eliminar_empresas">
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

                            <div class="mt-3">
                                <a class="btn btn-outline-secondary w-100" href="empresa.php?view=ver_empresas">Volver</a>
                            </div>



                    </div>
                               <?php elseif ($view === 'ver_planes'): ?>

                    <!-- VER PLANES -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Planes asignados a empresas</h6>
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

                        <form method="get" action="empresa.php" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="view" value="ver_planes">
                            <label class="mb-0">Buscar:</label>
                            <input class="form-control form-control-sm" name="q" value="<?= h($searchPlanesQ ?? '') ?>" style="width: 220px;">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                            <?php if (!empty($searchPlanesQ)): ?>
                                <a class="btn btn-outline-danger btn-sm" href="empresa.php?view=ver_planes">Limpiar</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if (empty($planes)): ?>
                        <div class="alert alert-light border">No hay planes creados aún.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center planes-table">
                                <thead class="table-secondary">
                                    <tr>
                                        <th class="w-50px">#</th>
                                        <th class="wrap">Empresa</th>
                                        <th>Inicio plan</th>
                                        <th>Fin plan</th>
                                        <th class="col-actions">#</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1 + (((int)($currentPagePlanes ?? 1) - 1) * 10); ?>
                                    <?php foreach ($planes as $p): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td class="wrap"><?= h($p['razon_social']) ?></td>
                                            <td><?= h($p['inicio_plan']) ?></td>
                                            <td><?= h($p['fin_plan']) ?></td>
                                            <td class="col-actions">
                                                <div class="actions-nowrap">
                                                    <a class="btn btn-outline-primary btn-sm"
                                                        href="?view=ver_medidas&id_empresa=<?= (int)$p['id_empresa'] ?>"
                                                        title="Ver medidas">Ver medidas</a>
                                                    <a class="btn btn-success btn-sm btn-icon"
                                                        href="?view=edit_plan&id_empresa=<?= (int)$p['id_empresa'] ?>"
                                                        title="Editar">✏️</a>
                                                    <form method="post" action="../controller/empresa_controller.php" class="d-inline"
                                                        onsubmit="return confirm('¿Seguro que quieres eliminar el plan de esta empresa?');">
                                                        <input type="hidden" name="accion" value="delete_plan_empresa">
                                                        <input type="hidden" name="id_empresa" value="<?= (int)$p['id_empresa'] ?>">
                                                        <button class="btn btn-outline-danger btn-sm btn-icon" type="submit" title="Eliminar">🗑</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($planes)): ?>
                                        <tr>
                                            <td colspan="5" class="text-muted">No hay planes creados aún.</td>
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
                                            href="empresa.php?view=ver_planes&page=<?= $prevPagePlanes ?>&q=<?= urlencode($qParamPlanes) ?>">Anterior</a>
                                    </li>

                                    <?php for ($p = 1; $p <= $totalPagesPlanes; $p++): ?>
                                        <li class="page-item <?= ($p === $currentPagePlanes) ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="empresa.php?view=ver_planes&page=<?= $p ?>&q=<?= urlencode($qParamPlanes) ?>"><?= $p ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $nextDisabledPlanes ?>">
                                        <a class="page-link"
                                            href="empresa.php?view=ver_planes&page=<?= $nextPagePlanes ?>&q=<?= urlencode($qParamPlanes) ?>">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                     <?php elseif ($view === 'ver_medidas'): ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Áreas y medidas del plan</h6>
                        <a class="btn btn-outline-secondary btn-sm" href="?view=ver_planes">Volver a planes</a>
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
                                    <?php $medidasArea = $area['medidas'] ?? []; ?>
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

                    <!-- EDITAR PLAN -->
                    <h6 class="text-center mb-3">Editar plan</h6>

                    <?php if (!empty($editPlanError)): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= h($editPlanError) ?></div>
                    <?php endif; ?>

                    <?php if ($editPlan === null): ?>
                        <div class="alert alert-warning">Plan no encontrado.</div>
                        <a class="btn btn-outline-secondary" href="?view=ver_planes">Volver</a>
                    <?php else: ?>

                        <form method="post" action="../controller/empresa_controller.php" class="vstack gap-3">
                            <input type="hidden" name="accion" value="edit_plan">
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
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary w-100" type="submit">Guardar cambios</button>
                                <a class="btn btn-outline-secondary w-100" href="?view=ver_planes">Volver</a>
                            </div>
                        </form>

                    <?php endif; ?>

                <?php elseif ($view === 'ver_contratos'): ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Listado de contratos</h6>
                        <a class="btn btn-primary btn-sm" href="empresa.php?view=add_contratos">Añadir contrato</a>
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
                                <label class="mb-0">Buscar:</label>
                                <input class="form-control form-control-sm" name="q" value="<?= h($searchContratoQ ?? '') ?>" style="width: 220px;">
                                <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                <?php if (!empty($searchContratoQ)): ?>
                                    <a class="btn btn-outline-danger btn-sm" href="empresa.php?view=ver_contratos">Limpiar</a>
                                <?php endif; ?>
                            </form>
                        </div>

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
                                                    <a class="btn btn-success btn-sm"
                                                        href="empresa.php?view=edit_contratos&id_contrato=<?= (int)($c['id_contrato_empresa'] ?? 0) ?>"
                                                        title="Editar contrato">Editar</a>
                                                    <form method="post" action="../controller/empresa_controller.php" class="d-inline"
                                                        onsubmit="return confirm('¿Seguro que quieres eliminar este contrato?');">
                                                        <input type="hidden" name="accion" value="delete_contratos">
                                                        <input type="hidden" name="id_contrato_empresa" value="<?= (int)($c['id_contrato_empresa'] ?? 0) ?>">
                                                        <button class="btn btn-outline-danger btn-sm" type="submit" title="Eliminar contrato">Eliminar</button>
                                                    </form>
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
                                            href="empresa.php?view=ver_contratos&page=<?= $prevPageContratos ?>&q=<?= urlencode($qParamContratos) ?>">Anterior</a>
                                    </li>

                                    <?php for ($p = 1; $p <= $totalPagesContratos; $p++): ?>
                                        <li class="page-item <?= ($p === $currentPageContratos) ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="empresa.php?view=ver_contratos&page=<?= $p ?>&q=<?= urlencode($qParamContratos) ?>"><?= $p ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $nextDisabledContratos ?>">
                                        <a class="page-link"
                                            href="empresa.php?view=ver_contratos&page=<?= $nextPageContratos ?>&q=<?= urlencode($qParamContratos) ?>">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php elseif ($view === 'edit_contratos'): ?>

                    <h6 class="mb-3">Editar contrato</h6>

                    <?php if (!empty($editContratoError)): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= h($editContratoError) ?></div>
                    <?php endif; ?>

                    <?php if ($selectedContrato === null): ?>
                        <div class="alert alert-warning">Contrato no encontrado.</div>
                        <a class="btn btn-outline-secondary w-100" href="empresa.php?view=ver_contratos">Volver</a>
                    <?php else: ?>
                        <form method="post" action="../controller/empresa_controller.php" class="vstack gap-2">
                            <input type="hidden" name="accion" value="edit_contratos">
                            <input type="hidden" name="id_contrato_empresa" value="<?= (int)($selectedContrato['id_contrato_empresa'] ?? 0) ?>">

                            <div>
                                <label class="form-label">Empresa</label>
                                <select class="form-select" name="id_empresa" required>
                                    <option value="">-- seleccionar Empresa --</option>
                                    <?php foreach (($empresasForContrato ?? []) as $e): ?>
                                        <option value="<?= (int)$e['id_empresa'] ?>"
                                            <?= ((int)($selectedContrato['id_empresa'] ?? 0) === (int)$e['id_empresa']) ? 'selected' : '' ?>>
                                            <?= h($e['razon_social']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="form-label">Tipo de contrato</label>
                                <select class="form-select" name="tipo_contrato" required>
                                    <?php foreach (($tiposContrato ?? []) as $tipo): ?>
                                        <option value="<?= h($tipo) ?>" <?= (($selectedContrato['tipo_contrato'] ?? 'COMPLETO') === $tipo) ? 'selected' : '' ?>>
                                            <?= h($tipo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="form-label">Inicio contratación</label>
                                <input class="form-control" type="date" name="inicio_contratacion" required
                                    value="<?= h($selectedContrato['inicio_contratacion'] ?? '') ?>">
                            </div>

                            <div>
                                <label class="form-label">Fin contratación</label>
                                <input class="form-control" type="date" name="fin_contratacion" required
                                    value="<?= h($selectedContrato['fin_contratacion'] ?? '') ?>">
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary w-100" type="submit">Guardar cambios</button>
                                <a class="btn btn-outline-secondary w-100" href="empresa.php?view=ver_contratos">Volver</a>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php elseif ($view === 'add_contratos'): ?>

                    <h6 class="mb-3">Añadir contrato</h6>

                    <?php if (!empty($contratoError)): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= h($contratoError) ?></div>
                    <?php endif; ?>

                    <form method="post" action="../controller/empresa_controller.php" class="vstack gap-2">
                        <input type="hidden" name="accion" value="add_contratos">

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
                            <label class="form-label">Tipo de contrato</label>
                            <select class="form-select js-tipo-contrato" name="tipo_contrato" required>
                                <?php foreach (($tiposContrato ?? []) as $tipo): ?>
                                    <option value="<?= h($tipo) ?>" <?= (($addContratoOld['tipo_contrato'] ?? 'COMPLETO') === $tipo) ? 'selected' : '' ?>>
                                        <?= h($tipo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="maintenance-fields border rounded p-3 bg-light" style="display: <?= in_array(($addContratoOld['tipo_contrato'] ?? 'COMPLETO'), ['COMPLETO', 'MANTENIMIENTO'], true) ? 'block' : 'none' ?>;">
                            <div>
                                <label class="form-label">Inicio plan</label>
                                <input class="form-control js-contrato-plan-date" type="date" name="inicio_plan" value="<?= h($addContratoOld['inicio_plan'] ?? '') ?>">
                            </div>

                            <div>
                                <label class="form-label">Fin plan</label>
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
                            <button class="btn btn-primary w-100" type="submit">Guardar contrato</button>
                            <a class="btn btn-outline-secondary w-100" href="empresa.php?view=ver_contratos">Volver</a>
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

        document.querySelectorAll('.js-edit-area-check').forEach(function(radio) {
            radio.addEventListener('change', function() {
                syncEditPlanArea();
            });
        });

        document.querySelectorAll('.js-edit-area-check').forEach(function(check) {
            check.addEventListener('change', function() {
                var areaId = this.getAttribute('data-area');
                var group = document.querySelector('.js-edit-medidas-group[data-area="' + areaId + '"]');
                if (!group) return;

                if (!this.checked) {
                    group.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = false;
                    });
                }
            });
        });

        syncEditPlanArea();

        // add_contratos: mostrar medidas cuando el tipo es COMPLETO o MANTENIMIENTO
        function syncContratoMedidas() {
            var tipoSelect = document.querySelector('.js-tipo-contrato');
            var panel = document.querySelector('.js-contrato-medidas-panel');
            if (!tipoSelect || !panel) return;

            var tipo = String(tipoSelect.value || '').toUpperCase();
            var showPlanMedidas = (tipo === 'MANTENIMIENTO' || tipo === 'COMPLETO');
            panel.style.display = showPlanMedidas ? 'block' : 'none';
        }

        var tipoContratoSelect = document.querySelector('.js-tipo-contrato');
        if (tipoContratoSelect) {
            tipoContratoSelect.addEventListener('change', syncContratoMedidas);
            syncContratoMedidas();
        }

        document.querySelectorAll('.js-contrato-area-check').forEach(function(check) {
            check.addEventListener('change', function() {
                var areaId = this.getAttribute('data-area');
                var group = document.querySelector('.js-contrato-medidas-group[data-area="' + areaId + '"]');
                if (!group) return;

                var medidaChecks = group.querySelectorAll('input[type="checkbox"]');
                if (this.checked) {
                    group.style.display = 'block';
                    medidaChecks.forEach(function(cb) { cb.disabled = false; });
                } else {
                    group.style.display = 'none';
                    medidaChecks.forEach(function(cb) {
                        cb.checked = false;
                        cb.disabled = true;
                    });
                }
            });
        });

        function syncContratoPlanFields() {
            var tipoSelect = document.querySelector('.js-tipo-contrato');
            var fields = document.querySelector('.maintenance-fields');
            if (!tipoSelect || !fields) return;

            var tipo = String(tipoSelect.value || '').toUpperCase();
            var showPlanMedidas = (tipo === 'MANTENIMIENTO' || tipo === 'COMPLETO');
            fields.style.display = showPlanMedidas ? 'block' : 'none';

            fields.querySelectorAll('.js-contrato-plan-date').forEach(function(input) {
                input.required = showPlanMedidas;
            });
        }

        if (tipoContratoSelect) {
            tipoContratoSelect.addEventListener('change', syncContratoPlanFields);
            syncContratoPlanFields();
        }
    </script>
</body>

</html>