<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Administrador</title>

    <link rel="stylesheet" href="../css/global.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
</head>

<body class="bg-light">
    <?php $view = $view ?? 'menu'; ?>
    <div class="container-fluid py-4">
        <div class="row g-3">

            <!-- SIDEBAR -->
            <aside class="col-12 col-lg-3 col-xl-2">
                <div class="card shadow-sm border-0 sidebar">
                    <div class="card-body">
                        <!-- Header Sidebar -->
                        <div class="sidebar-header">
                            <div class="sidebar-avatar">⚙️</div>
                            <h5 class="sidebar-title">Panel Admin</h5>
                        </div>

                        <!-- User Info -->
                        <div class="sidebar-user-info">
                            <div class="info-label">Usuario Actual</div>
                            <div class="info-value"><?= h($adminUsername ?? 'admin') ?></div>
                            <?php if (!empty($adminEmail)): ?>
                                <div class="info-email">📧 <?= h($adminEmail) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Navegación -->
                        <nav class="sidebar-nav">
                            <!-- Panel Admin -->
                            <a class="nav-button <?= ($view === 'menu') ? 'active' : '' ?>" href="admin.php?view=menu">
                                <span class="nav-icon">📊</span>
                                <span>Panel Admin</span>
                            </a>

                            <!-- Usuarios -->
                            <a class="nav-button <?= in_array($view, ['ver_usuarios', 'add', 'edit', 'delete'], true) ? 'active' : '' ?>" href="admin.php?view=ver_usuarios">
                                <span class="nav-icon">👥</span>
                                <span>Usuarios</span>
                            </a>

                            <!-- Empresas -->
                            <a class="nav-button" href="../model/empresa.php?view=ver_empresas&from=admin">
                                <span class="nav-icon">🏢</span>
                                <span>Directorio de Empresas</span>
                            </a>

                            <!-- Área Privada Collapse -->
                            <?php $isPrivateView = in_array($view, ['privada', 'perfil', 'reuniones'], true); ?>
                            <button class="nav-button nav-collapse <?= $isPrivateView ? 'active' : '' ?>"
                                type="button" data-bs-toggle="collapse" data-bs-target="#menuAreaPrivada"
                                aria-expanded="<?= $isPrivateView ? 'true' : 'false' ?>">
                                <span class="nav-icon">🔐</span>
                                <span>Área Privada</span>
                                <span class="collapse-icon">▾</span>
                            </button>

                            <div id="menuAreaPrivada" class="collapse nav-submenu <?= $isPrivateView ? 'show' : '' ?>">
                                <a class="nav-subbutton <?= ($view === 'perfil') ? 'active' : '' ?>" href="admin.php?view=perfil">
                                    <span>👤</span>
                                    <span>Mi Cuenta</span>
                                </a>
                                <a class="nav-subbutton <?= ($view === 'reuniones') ? 'active' : '' ?>" href="admin.php?view=reuniones">
                                    <span>📅</span>
                                    <span>Mis Reuniones</span>
                                </a>
                            </div>

                            <!-- Cerrar Sesión -->
                            <a class="nav-button nav-logout" href="<?= h(app_path('/php/logout.php')) ?>">
                                <span class="nav-icon">🚪</span>
                                <span>Cerrar Sesión</span>
                            </a>
                        </nav>
                    </div>
                </div>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="col-12 col-lg-9 col-xl-10">
                <div class="card panel mx-auto shadow-sm border-0 <?= in_array($view, ['menu', 'ver_usuarios'], true) ? 'panel-wide' : '' ?> <?= ($view === 'seguimiento_tecnicos') ? 'panel-followup' : '' ?> <?= ($view === 'reuniones') ? 'panel-reuniones' : '' ?>">
                    <div class="card-body p-4">

                        <header class="d-flex align-items-center justify-content-between mb-3">
                            <h4 class="mb-0">Panel de Administrador</h4>
                        </header>

                        <?php if (!empty($_GET['msg'])): ?>
                            <div class="alert alert-info py-2"><?= h($_GET['msg']) ?></div>
                        <?php endif; ?>

                        <?php if ($view === 'menu'): ?>
                            <div class="admin-kpi-grid">
                                <div>
                                    <a class="admin-kpi-card" href="../model/empresa.php?view=ver_empresas&from=admin">
                                        <div class="admin-kpi-head">
                                            <span>Clientes</span>
                                            <span class="admin-kpi-icon">🏢</span>
                                        </div>
                                        <div class="admin-kpi-value"><?= (int)$totalClientes ?></div>
                                    </a>
                                </div>
                                <div>
                                    <a class="admin-kpi-card" href="admin.php?view=seguimiento_tecnicos">
                                        <div class="admin-kpi-head">
                                            <span>Técnicos</span>
                                            <span class="admin-kpi-icon">🧑‍💻</span>
                                        </div>
                                        <div class="admin-kpi-value"><?= (int)$totalTecnicos ?></div>
                                    </a>
                                </div>
                                <div>
                                    <a class="admin-kpi-card" href="../model/empresa.php?view=ver_contratos&from=admin">
                                        <div class="admin-kpi-head">
                                            <span>Servicios de Empresas</span>
                                            <span class="admin-kpi-icon">💼</span>
                                        </div>
                                        <div class="admin-kpi-value"><?= (int)$totalPlanesIgualdad ?></div>
                                    </a>
                                </div>
                                <div>
                                    <a class="admin-kpi-card" href="../model/empresa.php?view=ver_contratos&tipo_contrato=MANTENIMIENTO&from=admin">
                                        <div class="admin-kpi-head">
                                            <span>Mantenimientos</span>
                                            <span class="admin-kpi-icon">🛠️</span>
                                        </div>
                                        <div class="admin-kpi-value"><?= (int)$totalMantenimientos ?></div>
                                    </a>
                                </div>
                            </div>

                            <section class="operational-wrap">
                                <h5 class="mb-1">Resumen operativo</h5>
                                <p class="text-muted mb-3">Vista global del estado de cartera.</p>

                                <?php if (empty($adminOperationalSummary)): ?>
                                    <div class="alert alert-light border mb-0">No hay empresas con contratos para mostrar en el resumen.</div>
                                <?php else: ?>
                                    <?php foreach ($adminOperationalSummary as $item): ?>
                                        <article class="operational-item">
                                            <div class="operational-top">
                                                <div>
                                                    <div class="operational-title"><?= h((string)($item['razon_social'] ?? '')) ?></div>
                                                    <div class="operational-meta"><?= h((string)($item['plan'] ?? '')) ?> · Tecnico: <?= h((string)($item['tecnico'] ?? 'Sin tecnico asignado')) ?></div>
                                                </div>
                                                <span class="operational-state"><?= h((string)($item['estado'] ?? 'Pendiente')) ?></span>
                                            </div>
                                            <div class="operational-progress">
                                                <div class="operational-progress-bar" style="width: <?= (int)($item['progreso'] ?? 0) ?>%;"></div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </section>

                        <?php elseif ($view === 'ver_usuarios'): ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Gestión de usuarios</h6>
                                <a class="btn btn-primary btn-sm" href="admin.php?view=add">Agregar usuario</a>
                            </div>

                            <!-- Barra superior: Mostrar + Buscar -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="text-muted small">Mostrando 10 entradas</div>

                                <form method="get" action="admin.php" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="view" value="ver_usuarios">
                                    <label class="mb-0">Buscar:</label>
                                    <input class="form-control form-control-sm" name="q" value="<?= h($searchQ ?? '') ?>" style="width: 220px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
                                    <?php if (!empty($searchQ)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="admin.php?view=ver_usuarios">Limpiar</a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <table class="table table-bordered align-middle text-center usuarios-table">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Apellidos</th>
                                        <th>Email</th>
                                        <th>Razón social</th>
                                        <th>Tipo usuario</th>
                                        <th class="col-actions">#</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <td><?= h($u['nombre_usuario'] ?? '') ?></td>
                                            <td><?= h($u['apellidos'] ?? '') ?></td>
                                            <td><?= h($u['email'] ?? '') ?></td>
                                            <td><?= h($u['razon_social'] ?? '') ?></td>
                                            <td><?= h($u['rol'] ?? '') ?></td>
                                            <td class="col-actions">
                                                <div class="actions-nowrap">
                                                    <a class="btn btn-success btn-sm"
                                                        href="admin.php?view=edit&id_usuario=<?= (int)$u['id_usuario'] ?>" title="Editar">✏️</a>

                                                    <form method="post" action="../controller/admin_controller.php" class="d-inline"
                                                        onsubmit="return confirm('¿Seguro que quieres eliminar el usuario <?= h($u['nombre_usuario'] ?? '') ?>?');">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                                                        <button class="btn btn-outline-danger btn-sm" type="submit" title="Eliminar">🗑</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php
                            $perPage = 10;
                            $totalUsuarios = (int)($totalUsuarios ?? count($usuarios));
                            $currentPage = (int)($currentPage ?? 1);
                            $totalPages = (int)($totalPages ?? 1);

                            $start = ($totalUsuarios === 0) ? 0 : (($currentPage - 1) * $perPage + 1);
                            $end = min($currentPage * $perPage, $totalUsuarios);

                            $qParam = (string)($searchQ ?? '');
                            ?>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                                <div class="text-muted small">
                                    Mostrando <?= $start ?> a <?= $end ?> de <?= $totalUsuarios ?> Entradas
                                </div>

                                <nav aria-label="Paginación usuarios">
                                    <ul class="pagination pagination-sm mb-0">

                                        <!-- Anterior -->
                                        <?php
                                        $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
                                        $prevPage = max(1, $currentPage - 1);
                                        ?>
                                        <li class="page-item <?= $prevDisabled ?>">
                                            <a class="page-link"
                                                href="admin.php?view=ver_usuarios&page=<?= $prevPage ?>&q=<?= urlencode($qParam) ?>">Anterior</a>
                                        </li>

                                        <!-- Páginas (simple: 1..N) -->
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                                <a class="page-link"
                                                    href="admin.php?view=ver_usuarios&page=<?= $p ?>&q=<?= urlencode($qParam) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <!-- Siguiente -->
                                        <?php
                                        $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
                                        $nextPage = min($totalPages, $currentPage + 1);
                                        ?>
                                        <li class="page-item <?= $nextDisabled ?>">
                                            <a class="page-link"
                                                href="admin.php?view=ver_usuarios&page=<?= $nextPage ?>&q=<?= urlencode($qParam) ?>">Siguiente</a>
                                        </li>

                                    </ul>
                                </nav>
                            </div>

                        <?php elseif ($view === 'seguimiento_tecnicos'): ?>

                            <div class="seguimiento-wrap">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <h4 class="mb-1">Seguimiento por técnico</h4>
                                        <div class="text-muted">Selecciona un técnico para ver sus empresas asignadas y el avance.</div>
                                    </div>
                                </div>

                                <?php if (empty($seguimientoTecnicos)): ?>
                                    <div class="alert alert-light border mt-3 mb-0">No hay técnicos asignados para mostrar.</div>
                                <?php else: ?>
                                    <div class="seguimiento-tecnicos-grid">
                                        <?php foreach ($seguimientoTecnicos as $tecnicoSeg): ?>
                                            <?php
                                            $tecnicoIdActual = (int)($tecnicoSeg['id_usuario'] ?? 0);
                                            $tecnicoEsActivo = ($tecnicoIdActual === (int)($seguimientoTecnicoSeleccionadoId ?? 0));
                                            ?>
                                            <a class="seguimiento-tecnico-card <?= $tecnicoEsActivo ? 'active' : '' ?>"
                                                href="admin.php?view=seguimiento_tecnicos&id_tecnico=<?= $tecnicoIdActual ?>">
                                                <div class="seguimiento-tecnico-name"><?= h((string)($tecnicoSeg['nombre_usuario'] ?? '')) ?></div>
                                                <div class="seguimiento-tecnico-count"><?= (int)($tecnicoSeg['total_empresas'] ?? 0) ?> empresas asignadas</div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="seguimiento-detalle">
                                        <h3 class="mb-1"><?= h((string)($seguimientoTecnicoSeleccionado['nombre_usuario'] ?? 'Técnico')) ?></h3>
                                        <div class="text-muted mb-3">Empresas asignadas y estado de avance.</div>

                                        <?php if (empty($seguimientoTecnicoEmpresas)): ?>
                                            <div class="alert alert-light border mb-0">Este técnico no tiene empresas asignadas.</div>
                                        <?php else: ?>
                                            <?php foreach ($seguimientoTecnicoEmpresas as $empresaSeg): ?>
                                                <article class="seguimiento-empresa-item">
                                                    <div class="seguimiento-empresa-top">
                                                        <div>
                                                            <div class="seguimiento-empresa-title"><?= h((string)($empresaSeg['razon_social'] ?? '')) ?></div>
                                                            <div class="seguimiento-empresa-meta"><?= h((string)($empresaSeg['plan'] ?? '')) ?></div>
                                                        </div>
                                                        <div class="seguimiento-badges">
                                                            <span class="seguimiento-pill"><?= h((string)($empresaSeg['estado'] ?? 'Pendiente')) ?></span>
                                                            <span class="seguimiento-pill"><?= (int)($empresaSeg['progreso'] ?? 0) ?>% completado</span>
                                                        </div>
                                                    </div>
                                                    <div class="seguimiento-progress">
                                                        <div class="seguimiento-progress-bar" style="width: <?= (int)($empresaSeg['progreso'] ?? 0) ?>%;"></div>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($view === 'add'): ?>

                            <h6 class="text-center mb-3">Agregar usuario</h6>

                            <?php if (!empty($addError)): ?>
                                <div class="alert alert-danger py-2 mb-3"><?= h($addError) ?></div>
                            <?php endif; ?>

                            <form method="post" action="../controller/admin_controller.php" class="vstack gap-2">
                                <?= csrf_input() ?>
                                <input type="hidden" name="accion" value="crear">

                                <div>
                                    <label class="form-label">Nombre de usuario *</label>
                                    <input class="form-control" name="nombre_usuario" required value="<?= h($addOld['nombre_usuario'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label">Nombre y Apellidos</label>
                                    <input class="form-control" name="apellidos" value="<?= h($addOld['apellidos'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label mb-1">Email *</label>
                                    <input class="form-control" name="email" type="email" required value="<?= h($addOld['email'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label">Telefono</label>
                                    <input class="form-control" name="telefono" value="<?= h($addOld['telefono'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label">Direccion</label>
                                    <input class="form-control" name="direccion" value="<?= h($addOld['direccion'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label">Localidad</label>
                                    <input class="form-control" name="localidad" value="<?= h($addOld['localidad'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label">Rol *</label>
                                    <select class="form-select" id="addUserRol" name="rol_id" required>
                                        <option value="">-- seleccionar --</option>
                                        <?php foreach ($roles as $r): ?>
                                            <?php
                                            $rolNombreNormalizado = strtoupper(str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], trim((string)($r['nombre'] ?? ''))));
                                            ?>
                                            <option value="<?= (int)$r['id'] ?>" data-role-normalized="<?= h($rolNombreNormalizado) ?>" <?= ((int)($addOld['rol_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                                                <?= h($r['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>


                                <div>
                                    <label class="form-label" id="addUserEmpresaLabel">Empresas asignadas</label>
                                    <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: #fff;">
                                        <?php if (empty($empresas)): ?>
                                            <div class="text-muted small">No hay empresas disponibles.</div>
                                        <?php else: ?>
                                            <?php foreach ($empresas as $e): ?>
                                                <?php
                                                $isChecked = in_array((int)$e['id_empresa'], array_map('intval', $addOld['empresas'] ?? []), true);
                                                ?>
                                                <div class="form-check mb-1">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        id="empresa_add_<?= (int)$e['id_empresa'] ?>"
                                                        name="empresas[]"
                                                        value="<?= (int)$e['id_empresa'] ?>"
                                                        <?= $isChecked ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="empresa_add_<?= (int)$e['id_empresa'] ?>">
                                                        <?= h($e['razon_social']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-text text-muted small mt-1">Selecciona una o varias empresas.</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary w-100" type="submit">Crear</button>
                                    <a class="btn btn-outline-secondary w-100" href="admin.php?view=ver_usuarios">Volver</a>
                                </div>
                            </form>

                        <?php elseif ($view === 'edit'): ?>

                            <?php
                            $selectedId = isset($_GET['id_usuario']) ? (int)$_GET['id_usuario'] : 0;
                            $selectedUser = null;
                            foreach ($usuarios as $uu) {
                                if ((int)$uu['id_usuario'] === $selectedId) {
                                    $selectedUser = $uu;
                                    break;
                                }
                            }
                            if ($selectedUser === null && !empty($usuarios)) {
                                $selectedUser = $usuarios[0];
                                $selectedId = (int)$selectedUser['id_usuario'];
                            }
                            ?>

                            <h6 class="text-center mb-4">Editar usuario</h6>

                            <?php if ($selectedUser === null): ?>
                                <div class="alert alert-warning">Usuario no encontrado.</div>
                            <?php else: ?>
                                <form method="post" action="../controller/admin_controller.php" class="edit-user-form">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="accion" value="editar">
                                    <input type="hidden" name="id_usuario" value="<?= (int)$selectedUser['id_usuario'] ?>">

                                    <div class="row g-4">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Nombre</label>
                                            <input class="form-control edit-input" name="nombre_usuario" value="<?= h($selectedUser['nombre_usuario'] ?? '') ?>" required>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Apellidos</label>
                                            <input class="form-control edit-input" name="apellidos" value="<?= h($selectedUser['apellidos'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Email</label>
                                            <input class="form-control edit-input" name="email" type="email" value="<?= h($selectedUser['email'] ?? '') ?>" required>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Teléfono</label>
                                            <input class="form-control edit-input" name="telefono" value="<?= h($selectedUser['telefono'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Contraseña</label>
                                            <div class="input-group">
                                                <input id="adminEditPassword" class="form-control edit-input" name="password" type="password" placeholder="" minlength="6">
                                                <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="adminEditPassword" aria-label="Mostrar contraseña">Mostrar</button>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-center w-100">Localidad</label>
                                            <input class="form-control edit-input" name="localidad" value="<?= h($selectedUser['localidad'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-8">
                                            <label class="form-label text-center w-100">Dirección</label>
                                            <input class="form-control edit-input" name="direccion" value="<?= h($selectedUser['direccion'] ?? '') ?>">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-center w-100">Tipo de usuario</label>
                                            <select class="form-select edit-input" name="rol_id" required>
                                                <?php foreach ($roles as $r): ?>
                                                    <option value="<?= (int)$r['id'] ?>" <?= ((int)$r['id'] === (int)($selectedUser['rol_id'] ?? 0)) ? 'selected' : '' ?>>
                                                        <?= h($r['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <div class="empresas-card">
                                                <div class="empresas-title">Empresas</div>
                                                <div class="d-flex justify-content-center">
                                                    <a class="btn btn-dark px-4" href="lista_empresa.php?id_usuario=<?= (int)$selectedUser['id_usuario'] ?>">Editar empresas</a>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="d-flex justify-content-center gap-4 mt-2">
                                                <button class="btn btn-primary px-5" type="submit">Actualizar</button>
                                                <a class="btn btn-danger px-5" href="admin.php?view=ver_usuarios">Cancelar</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>

                        <?php elseif ($view === 'delete'): ?>

                            <h6 class="text-center mb-3">Eliminar usuarios</h6>

                            <div class="vstack gap-2">
                                <?php foreach ($usuarios as $u): ?>
                                    <form method="post" action="../controller/admin_controller.php"
                                        class="border rounded bg-light p-2"
                                        onsubmit="return confirm('¿Seguro que quieres eliminar el usuario <?= h($u['nombre_usuario'] ?? '') ?>?');">
                                        <?= csrf_input() ?>

                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_usuario" value="<?= (int)($u['id_usuario'] ?? 0) ?>">

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div><strong><?= h($u['nombre_usuario'] ?? '') ?></strong></div>
                                                <div class="text-muted small"><?= h($u['rol'] ?? '') ?></div>
                                                <div class="text-muted small"><?= h($u['email'] ?? '') ?></div>
                                            </div>
                                            <button class="btn btn-danger" type="submit">Eliminar</button>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-3">
                                <a class="btn btn-outline-secondary w-100" href="admin.php?view=ver_usuarios">Volver</a>
                            </div>

                        <?php elseif ($view === 'privada'): ?>

                            <div class="alert alert-light border mb-0">
                                Selecciona una opcion de Area Privada: <strong>Mi cuenta</strong> o <strong>Mis reuniones</strong>.
                            </div>

                        <?php elseif ($view === 'perfil'): ?>

                            <div class="d-flex justify-content-center">
                                <div class="card shadow-sm border-0" style="max-width: 520px; width: 100%;">
                                    <div class="card-body p-4">
                                        <h3 class="text-center mb-4">Mi Cuenta</h3>

                                        <?php if (!empty($adminPerfil)): ?>
                                            <form method="post" action="../controller/admin_controller.php" class="vstack gap-3">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="accion" value="editar_perfil">
                                                <input type="hidden" name="id" value="<?= (int)($adminPerfil['id_usuario'] ?? 0) ?>">

                                                <input class="form-control" name="nombre_usuario"
                                                    value="<?= h($adminPerfil['nombre_usuario'] ?? '') ?>" placeholder="Nombre" required>

                                                <input class="form-control" name="apellidos"
                                                    value="<?= h($adminPerfil['apellidos'] ?? '') ?>" placeholder="Apellidos">

                                                <input class="form-control" name="email" type="email"
                                                    value="<?= h($adminPerfil['email'] ?? '') ?>" placeholder="Email" required>

                                                <input class="form-control" name="telefono"
                                                    value="<?= h($adminPerfil['telefono'] ?? '') ?>" placeholder="Teléfono">

                                                <input class="form-control" name="direccion"
                                                    value="<?= h($adminPerfil['direccion'] ?? '') ?>" placeholder="Dirección">

                                                <input class="form-control" name="localidad"
                                                    value="<?= h($adminPerfil['localidad'] ?? '') ?>" placeholder="Localidad">

                                                <div class="input-group">
                                                    <input id="adminPerfilPassword" class="form-control" name="password" type="password" placeholder="" autocomplete="new-password" minlength="6">
                                                    <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="adminPerfilPassword" aria-label="Mostrar contraseña">Mostrar</button>
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
                            $adminCalendarEvents = [];
                            foreach ($adminTodasReuniones as $reunion) {
                                $idReunion = (int)($reunion['id_reunion'] ?? 0);
                                $objetivoReunion = trim((string)($reunion['objetivo'] ?? ''));
                                $fechaReunion = (string)($reunion['fecha_reunion'] ?? '');
                                $horaReunion = (string)($reunion['hora_reunion'] ?? '');
                                $participantesReunion = trim((string)($reunion['participantes'] ?? ''));
                                $titulo = ($objetivoReunion !== '' ? $objetivoReunion : 'Reunion');
                                $adminCalendarEvents[] = [
                                    'id' => (string)$idReunion,
                                    'title' => $titulo,
                                    'start' => $fechaReunion . 'T' . $horaReunion,
                                    'allDay' => false,
                                    'extendedProps' => [
                                        'objetivo' => $objetivoReunion,
                                        'fecha' => $fechaReunion,
                                        'hora' => $horaReunion,
                                        'participantes' => $participantesReunion,
                                    ],
                                ];
                            }
                            ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">📅 Reuniones</h6>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <h6 class="mb-3">Crear Nueva Reunión</h6>
                                    <form method="post" action="../controller/admin_controller.php" class="row g-2 align-items-end">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="accion" value="crear_reunion">
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">📅 Fecha</label>
                                            <input class="form-control" type="date" name="fecha_reunion" required>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <label class="form-label">🕐 Hora</label>
                                            <input class="form-control" type="time" name="hora_reunion" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">👤 Asignar a Cliente</label>
                                            <select class="form-select" name="id_cliente_reunion">
                                                <option value="0">Sin asignar a cliente</option>
                                                <?php if (empty($adminClientesReunion)): ?>
                                                    <option value="0" disabled>Sin clientes disponibles</option>
                                                <?php else: ?>
                                                    <?php foreach ($adminClientesReunion as $clienteReunion): ?>
                                                        <?php
                                                        $idClienteReunion = (int)($clienteReunion['id_usuario'] ?? 0);
                                                        $nombreClienteReunion = trim((string)($clienteReunion['nombre_usuario'] ?? ''));
                                                        $apellidosClienteReunion = trim((string)($clienteReunion['apellidos'] ?? ''));
                                                        $emailClienteReunion = trim((string)($clienteReunion['email'] ?? ''));
                                                        $empresaClienteReunion = trim((string)($clienteReunion['razon_social'] ?? ''));
                                                        $labelClienteReunion = trim($nombreClienteReunion . ' ' . $apellidosClienteReunion);
                                                        if ($labelClienteReunion === '') {
                                                            $labelClienteReunion = 'Cliente #' . $idClienteReunion;
                                                        }
                                                        if ($empresaClienteReunion !== '') {
                                                            $labelClienteReunion .= ' - ' . $empresaClienteReunion;
                                                        }
                                                        if ($emailClienteReunion !== '') {
                                                            $labelClienteReunion .= ' (' . $emailClienteReunion . ')';
                                                        }
                                                        ?>
                                                        <option value="<?= $idClienteReunion ?>"><?= h($labelClienteReunion) ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">📝 Asunto</label>
                                            <input class="form-control" type="text" name="objetivo" maxlength="1000" placeholder="(opcional)">
                                        </div>
                                        <div class="col-12 d-flex justify-content-end">
                                            <button class="btn btn-primary" type="submit">➕ Agregar Reunión</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <h6 class="mb-3">📆 Calendario de todas las reuniones</h6>
                                    <div id="adminReunionesCalendar" class="border rounded p-3 bg-white reuniones-calendar-wrap"></div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-body">
                                    <h6 class="mb-3">📅 Todas Tus Reuniones</h6>
                                    <?php if (empty($adminReuniones)): ?>
                                        <div class="alert alert-light border mb-0">El calendario se muestra aunque no tengas reuniones asignadas.</div>
                                    <?php else: ?>
                                        <div class="vstack gap-3">
                                            <?php foreach ($adminReuniones as $reunionLista): ?>
                                                <?php
                                                $idReunionLista = (int)($reunionLista['id_reunion'] ?? 0);
                                                $objetivoLista = trim((string)($reunionLista['objetivo'] ?? ''));
                                                $fechaListaRaw = (string)($reunionLista['fecha_reunion'] ?? '');
                                                $horaListaRaw = (string)($reunionLista['hora_reunion'] ?? '');
                                                $horaLista = substr($horaListaRaw, 0, 5);
                                                $resumenFecha = trim($fechaListaRaw . ' · ' . $horaLista, ' ·');
                                                ?>
                                                <div class="cita-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div class="me-auto">
                                                        <div class="cita-item-title">📄 <?= h($objetivoLista !== '' ? $objetivoLista : 'Reunión') ?></div>
                                                        <div class="cita-item-subtitle">Cita programada</div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <span class="cita-pill">Reunión</span>
                                                        <span class="cita-pill"><?= h($resumenFecha !== '' ? $resumenFecha : 'Sin fecha') ?></span>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form method="post" action="../controller/admin_controller.php" class="mt-2 row g-2 align-items-end" style="min-width: 320px;">
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
                                                                    <label class="form-label mb-1">Objetivo</label>
                                                                    <input class="form-control form-control-sm" type="text" name="objetivo" maxlength="1000" value="<?= h($objetivoLista) ?>" placeholder="Objetivo (opcional)">
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end">
                                                                    <button class="btn btn-success btn-sm" type="submit">Guardar</button>
                                                                </div>
                                                            </form>
                                                        </details>
                                                        <form method="post" action="../controller/admin_controller.php" onsubmit="return confirm('¿Eliminar esta reunión?');">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="accion" value="eliminar_reunion">
                                                            <input type="hidden" name="id_reunion" value="<?= $idReunionLista ?>">
                                                            <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            </div>
                    </div>

                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-body">
                            <h6 class="mb-3">🌐 Todas las reuniones del sistema</h6>
                            <?php if (empty($adminTodasReuniones)): ?>
                                <div class="alert alert-light border mb-0">No hay reuniones registradas.</div>
                            <?php else: ?>
                                <div class="vstack gap-3">
                                    <?php foreach ($adminTodasReuniones as $reunionGlobal): ?>
                                        <?php
                                        $objetivoGlobal = trim((string)($reunionGlobal['objetivo'] ?? ''));
                                        $fechaGlobalRaw = (string)($reunionGlobal['fecha_reunion'] ?? '');
                                        $horaGlobalRaw = (string)($reunionGlobal['hora_reunion'] ?? '');
                                        $horaGlobal = substr($horaGlobalRaw, 0, 5);
                                        $participantesGlobal = trim((string)($reunionGlobal['participantes'] ?? ''));
                                        $resumenGlobal = trim($fechaGlobalRaw . ' · ' . $horaGlobal, ' ·');
                                        ?>
                                        <div class="cita-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div class="me-auto">
                                                <div class="cita-item-title">📄 <?= h($objetivoGlobal !== '' ? $objetivoGlobal : 'Reunión') ?></div>
                                                <div class="cita-item-subtitle">Participantes: <?= h($participantesGlobal !== '' ? $participantesGlobal : 'Sin participantes') ?></div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="cita-pill">Reunión</span>
                                                <span class="cita-pill"><?= h($resumenGlobal !== '' ? $resumenGlobal : 'Sin fecha') ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="adminReunionDetalleModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">📅 Detalle de Reunión</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <div><strong>Fecha:</strong> <span id="adminDetalleFecha"></span></div>
                                    <div><strong>Hora:</strong> <span id="adminDetalleHora"></span></div>
                                    <div class="mt-2"><strong>Asunto:</strong></div>
                                    <div id="adminDetalleObjetivo" class="text-muted"></div>
                                    <div class="mt-2"><strong>Participantes:</strong></div>
                                    <div id="adminDetalleParticipantes" class="text-muted"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
                </div>
        </div>
        </main>

    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>
    <script>
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

        (function() {
            const roleSelect = document.getElementById('addUserRol');
            const companyLabel = document.getElementById('addUserEmpresaLabel');
            const companyCheckboxes = document.querySelectorAll('input[name="empresas[]"]');

            if (!roleSelect || !companyLabel) {
                return;
            }

            function updateCompanyRequirementByRole() {
                const selectedOption = roleSelect.options[roleSelect.selectedIndex] || null;
                const selectedRole = String(selectedOption?.dataset?.roleNormalized || '').toUpperCase();
                const isCliente = selectedRole === 'CLIENTE';

                companyLabel.textContent = isCliente ? 'Empresas asignadas *' : 'Empresas asignadas';

                if (companyCheckboxes.length > 0) {
                    if (isCliente) {
                        const updateRequiredState = () => {
                            const isAnyChecked = Array.from(companyCheckboxes).some(cb => cb.checked);
                            companyCheckboxes.forEach(cb => {
                                cb.required = !isAnyChecked;
                            });
                        };
                        
                        companyCheckboxes.forEach(cb => {
                            cb.addEventListener('change', updateRequiredState);
                        });
                        updateRequiredState();
                    } else {
                        companyCheckboxes.forEach(cb => {
                            cb.required = false;
                        });
                    }
                }
            }

            roleSelect.addEventListener('change', updateCompanyRequirementByRole);
            updateCompanyRequirementByRole();
        })();
    </script>
    <?php if ($view === 'reuniones'): ?>
        <script>
            (function() {
                const calendarEl = document.getElementById('adminReunionesCalendar');
                if (!calendarEl || typeof FullCalendar === 'undefined') {
                    return;
                }

                const events = <?= json_encode($adminCalendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                const detalleFecha = document.getElementById('adminDetalleFecha');
                const detalleHora = document.getElementById('adminDetalleHora');
                const detalleObjetivo = document.getElementById('adminDetalleObjetivo');
                const detalleParticipantes = document.getElementById('adminDetalleParticipantes');
                const modalEl = document.getElementById('adminReunionDetalleModal');
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
                            detalleParticipantes.textContent = (props.participantes && props.participantes.trim() !== '') ? props.participantes : 'Sin participantes';
                            detalleModal.show();
                        }
                    }
                });

                calendar.render();
            })();
        </script>
    <?php endif; ?>
</body>

</html>