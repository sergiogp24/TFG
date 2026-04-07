<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Administrador</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_usuarios.css">
    <link rel="stylesheet" href="../css/admin_edit_usuario.css">
    <link rel="stylesheet" href="../css/admin_layout.css">

    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
 <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
</head>

<body class="bg-light">
    <?php $view = $view ?? 'ver_usuarios'; ?>
    <div class="container-fluid py-4">
        <div class="row g-3">

            <!-- SIDEBAR -->
            <aside class="col-12 col-lg-3 col-xl-2">
                <div class="card shadow-sm border-0 sidebar">
                    <div class="card-body">
                        <h5 class="mb-1">Panel Admin</h5>

                        <div class="text-muted small mb-3">
                            Sesión: <strong><?= h($adminUsername ?? 'admin') ?></strong>
                            <?php if (!empty($adminEmail)): ?>
                                <div>Email: <strong><?= h($adminEmail) ?></strong></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-dark text-start <?= ($view === 'menu') ? 'active' : '' ?>" href="empresa.php?view=ver_empresas">Inicio</a>

                            <!-- USUARIOS -->
                            <button class="btn btn-outline-dark text-start d-flex justify-content-between align-items-center <?= in_array($view, ['ver_usuarios', 'add', 'edit', 'delete'], true) ? 'active' : '' ?>"
                                type="button" data-bs-toggle="collapse" data-bs-target="#menuUsuarios"
                                aria-expanded="<?= in_array($view, ['ver_usuarios', 'add', 'edit', 'delete'], true) ? 'true' : 'false' ?>">
                                <span>Usuarios</span><span>▾</span>
                            </button>

                            <div id="menuUsuarios" class="collapse <?= in_array($view, ['ver_usuarios', 'add', 'edit', 'delete'], true) ? 'show' : '' ?>">
                                <div class="d-grid gap-2 ps-3 pt-2">
                                    <a class="btn btn-outline-secondary text-start <?= ($view === 'ver_usuarios') ? 'active' : '' ?>"
                                        href="admin.php?view=ver_usuarios">Ver</a>
                                    <a class="btn btn-outline-secondary text-start <?= ($view === 'add') ? 'active' : '' ?>"
                                        href="admin.php?view=add">Añadir</a>
                                </div>
                            </div>

                            <!-- EMPRESAS (Ahora va a empresa.php, no a admin.php) -->
                            <button class="btn btn-outline-dark text-start d-flex justify-content-between align-items-center"
                                type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                                aria-expanded="false">
                                <span>Empresas</span><span>▾</span>
                            </button>

                            <div id="menuEmpresas" class="collapse">
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

                            <a class="btn btn-outline-dark text-start" href="../html/index_documentos_tipo.php">
                                Subir documentos (Igualdad/Seleccion/Salud/Comunicacion/LGTBI)
                            </a>

                            <a class="btn btn-outline-dark text-start <?= ($view === 'perfil') ? 'active' : '' ?>" href="admin.php?view=perfil">Area Privada</a>

                            <a class="btn btn-outline-secondary text-start" href="/Igualdad/php/logout.php">
                                Cerrar sesión
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- CONTENT (ADMIN: SOLO USUARIOS/PERFIL) -->
            <main class="col-12 col-lg-9 col-xl-10">
                <div class="card panel mx-auto shadow-sm border-0 <?= in_array($view, ['ver_usuarios'], true) ? 'panel-wide' : '' ?>">
                    <div class="card-body p-4">

                        <header class="d-flex align-items-center justify-content-between mb-3">
                            <h4 class="mb-0">Panel de Administrador</h4>
                        </header>

                        <?php if (!empty($_GET['msg'])): ?>
                            <div class="alert alert-info py-2"><?= h($_GET['msg']) ?></div>
                        <?php endif; ?>

                        <?php if ($view === 'menu'): ?>
                            <div class="alert alert-light border mb-0">
                                Usa el menú lateral para acceder a todas las opciones del panel.
                            </div>

                        <?php elseif ($view === 'ver_usuarios'): ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Gestión de usuarios</h6>
                                <a class="btn btn-primary btn-sm" href="admin.php?view=add">Agregar usuario</a>
                            </div>

                            <!-- Barra superior: Mostrar + Buscar -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Mostrar</span>
                                    <select class="form-select form-select-sm" style="width: 90px;" disabled>
                                        <option selected>10</option>
                                    </select>
                                    <span>Entradas</span>
                                </div>

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

                        <?php elseif ($view === 'add'): ?>

                            <h6 class="text-center mb-3">Agregar usuario</h6>

                            <?php if (!empty($addError)): ?>
                                <div class="alert alert-danger py-2 mb-3"><?= h($addError) ?></div>
                            <?php endif; ?>

                            <form method="post" action="../controller/admin_controller.php" class="vstack gap-2">
                                <input type="hidden" name="accion" value="crear">

                                <div>
                                    <label class="form-label">Nombre de usuario</label>
                                    <input class="form-control" name="nombre_usuario" required value="<?= h($addOld['nombre_usuario'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label">Apellidos</label>
                                    <input class="form-control" name="apellidos" value="<?= h($addOld['apellidos'] ?? '') ?>">
                                </div>

                                <div>
                                    <label class="form-label mb-1">Email</label>
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
                                    <label class="form-label">Contraseña</label>
                                    <input class="form-control" name="password" type="password" required minlength="6">
                                </div>

                                <div>
                                    <label class="form-label">Rol</label>
                                    <select class="form-select" name="rol_id" required>
                                        <option value="">-- seleccionar --</option>
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= (int)$r['id'] ?>" <?= ((int)($addOld['rol_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                                                <?= h($r['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                            <input class="form-control edit-input" name="password" type="password" placeholder="••••••••" minlength="6">
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

                        <?php elseif ($view === 'perfil'): ?>

                            <div class="d-flex justify-content-center">
                                <div class="card shadow-sm border-0" style="max-width: 520px; width: 100%;">
                                    <div class="card-body p-4">
                                        <h3 class="text-center mb-4">Area Privada</h3>


                                        <?php if (empty($adminPerfil)): ?>

                                        <?php else: ?>

                                            <form method="post" action="../controller/admin_controller.php" class="vstack gap-3">
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

                                                <input class="form-control" name="password" type="password" placeholder="••••••••" autocomplete="new-password" minlength="6">

                                                <div class="d-flex justify-content-center pt-2">
                                                    <button class="btn btn-dark px-5" type="submit">Actualizar</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>

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
</body>

</html>