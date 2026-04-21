<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Tecnico</title>

    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/tecnico.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
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
                            <div class="sidebar-avatar">👨‍💼</div>
                            <h5 class="sidebar-title">Panel Técnico</h5>
                        </div>

                        <!-- User Info -->
                        <div class="sidebar-user-info">
                            <div class="info-label">Usuario Actual</div>
                            <div class="info-value"><?= h($tecnicoUsername ?? 'técnico') ?></div>
                            <?php if (!empty($tecnicoEmail)): ?>
                                <div class="info-email">📧 <?= h($tecnicoEmail) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Navegación -->
                        <nav class="sidebar-nav">
                            <!-- Mi Panel -->
                            <a class="nav-button <?= ($view === 'menu') ? 'active' : '' ?>" href="tecnico.php?view=menu">
                                <span class="nav-icon">📊</span>
                                <span>Mi Panel</span>
                            </a>

                            <!-- Empresas Collapse -->
                            <button class="nav-button nav-collapse <?= !in_array($view, ['menu', 'perfil', 'privada', 'reuniones', 'contacto_empresa'], true) ? 'active' : '' ?>"
                                type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                                aria-expanded="<?= !in_array($view, ['menu', 'perfil', 'privada', 'reuniones', 'contacto_empresa'], true) ? 'true' : 'false' ?>">
                                <span class="nav-icon">🏢</span>
                                <span>Empresas</span>
                                <span class="collapse-icon">▾</span>
                            </button>

                            <div id="menuEmpresas" class="collapse nav-submenu <?= !in_array($view, ['menu', 'perfil', 'privada', 'reuniones', 'contacto_empresa'], true) ? 'show' : '' ?>">
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
                                <a class="nav-subbutton <?= ($view === 'perfil') ? 'active' : '' ?>" href="tecnico.php?view=perfil">
                                    <span>👤</span>
                                    <span>Mi Cuenta</span>
                                </a>
                                <a class="nav-subbutton <?= ($view === 'reuniones') ? 'active' : '' ?>" href="tecnico.php?view=reuniones">
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
                <div class="card panel mx-auto shadow-sm border-0">
                    <div class="card-body p-4">

                        <?php if (!empty($_GET['msg'])): ?>
                            <div class="alert alert-info">
                                ✅ <?= h($_GET['msg']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($view === 'menu'): ?>
                            <!-- HEADER CON BIENVENIDA -->
                            <div class="content-header">
                                <h2>Bienvenido, <?= h($tecnicoUsername ?? 'Técnico') ?></h2>
                                <p>Panel de control y gestión de empresas</p>
                            </div>

                            <!-- NOTIFICACIONES -->
                            <?php if (!empty($avisosTecnico)): ?>
                                <div class="alerts-section">
                                    <?php foreach ($avisosTecnico as $aviso): ?>
                                        <div class="alert alert-info alert-with-icon">
                                            <span class="icon">ℹ️</span>
                                            <div>
                                                <strong><?= h((string)($aviso['mensaje'] ?? 'Notificación')) ?></strong>
                                                <?php if (!empty($aviso['detalle'])): ?>
                                                    <div class="alert-detail"><?= h((string)$aviso['detalle']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- DASHBOARD CON MÉTRICAS -->
                            <div class="dashboard-grid">
                                <a class="metric-card" href="../model/empresa.php?view=ver_empresas&from=tecnico" style="text-decoration:none;color:inherit;">
                                    <div class="metric-label">Empresas Asignadas</div>
                                    <div class="metric-value">
                                        <span><?= (int)($tecnicoStats['empresas_asignadas'] ?? 0) ?></span>
                                        <div class="metric-icon">🏢</div>
                                    </div>
                                    <div class="metric-change positive">Empresas activas asignadas</div>
                                </a>

                                <a class="metric-card purple" href="../model/empresa.php?view=ver_planes&from=tecnico" style="text-decoration:none;color:inherit;">
                                    <div class="metric-label">Mis Planes</div>
                                    <div class="metric-value">
                                        <span><?= (int)($tecnicoStats['mis_planes'] ?? 0) ?></span>
                                        <div class="metric-icon">🗂️</div>
                                    </div>
                                    <div class="metric-change positive">Planes activos</div>
                                </a>

                                <a class="metric-card amber" href="../model/empresa.php?view=ver_contratos&tipo_contrato=MANTENIMIENTO&from=tecnico" style="text-decoration:none;color:inherit;">
                                    <div class="metric-label">Mis Mantenimientos</div>
                                    <div class="metric-value">
                                        <span><?= (int)($tecnicoStats['mis_mantenimientos'] ?? 0) ?></span>
                                        <div class="metric-icon">🛠️</div>
                                    </div>
                                    <div class="metric-change positive">Contratos de mantenimiento</div>
                                </a>

                                <a class="metric-card red" href="tecnico.php?view=reuniones" style="text-decoration:none;color:inherit;">
                                    <div class="metric-label">Reuniones Programadas</div>
                                    <div class="metric-value">
                                        <span><?= (int)($tecnicoStats['reuniones_programadas'] ?? 0) ?></span>
                                        <div class="metric-icon">📅</div>
                                    </div>
                                    <div class="metric-change positive">Pendientes por realizar</div>
                                </a>
                            </div>

                            <!-- ACCIONES RÁPIDAS -->
                            <div class="quick-actions">
                                <h6 class="quick-actions-title">⚙️ Acciones Rápidas</h6>
                                <div class="action-buttons">
                                    <a href="tecnico.php?view=perfil" class="btn btn-primary">👤 Mi Perfil</a>
                                    <a href="tecnico.php?view=reuniones" class="btn btn-primary">📅 Reuniones</a>
                                </div>
                            </div>

                            <!-- INFORMACIÓN DE SESIÓN -->
                            <div class="session-info">
                                <h6>Información de Sesión</h6>
                                <div class="session-details">
                                    <div class="session-item">
                                        <span class="session-label">Usuario</span>
                                        <span class="session-value"><?= h($tecnicoUsername ?? 'Técnico') ?></span>
                                    </div>
                                    <div class="session-item">
                                        <span class="session-label">Email</span>
                                        <span class="session-value"><?= h($tecnicoEmail ?? '-') ?></span>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($view === 'contacto_empresa'): ?>

                            <div class="profile-container">
                                <div class="profile-card">
                                    <h3>📧 Contactar con Empresa</h3>

                                    <?php if (empty($tecnicoEmpresasContacto)): ?>
                                        <div class="alert alert-warning mb-0">No tienes empresas asignadas para enviar correos.</div>
                                    <?php else: ?>
                                        <form method="post" action="../controller/tecnico_controller.php" class="profile-form">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="accion" value="contactar_empresa">

                                            <div class="form-group">
                                                <label class="form-label">Empresa</label>
                                                <select class="form-select" name="id_empresa" required>
                                                    <option value="">-- Seleccionar empresa --</option>
                                                    <?php foreach ($tecnicoEmpresasContacto as $empresaContacto): ?>
                                                        <?php $emailEmpresaContacto = trim((string)($empresaContacto['email'] ?? '')); ?>
                                                        <option value="<?= (int)($empresaContacto['id_empresa'] ?? 0) ?>">
                                                            <?= h((string)($empresaContacto['razon_social'] ?? '')) ?><?= $emailEmpresaContacto !== '' ? ' · ' . h($emailEmpresaContacto) : ' · Sin correo asignado' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Asunto</label>
                                                <input class="form-control" type="text" name="asunto" maxlength="150" required placeholder="Escribe el asunto del correo">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Mensaje</label>
                                                <textarea class="form-control" name="mensaje" rows="6" maxlength="3000" required placeholder="Escribe tu mensaje para la empresa"></textarea>
                                            </div>

                                            <div class="form-actions">
                                                <button class="btn btn-primary" type="submit">📨 Enviar correo</button>
                                                <a href="tecnico.php?view=menu" class="btn btn-outline-danger">Cancelar</a>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif ($view === 'privada'): ?>

                            <div class="empty-state">
                                <div class="empty-icon">🔐</div>
                                <div class="empty-title">Área Privada</div>
                                <div class="empty-message">Selecciona una opción: <strong>Mi Cuenta</strong> o <strong>Mis Reuniones</strong></div>
                            </div>
                        //Mi perfil tecnico
                        <?php elseif ($view === 'perfil'): ?>

                            <div class="profile-container">
                                <div class="profile-card">
                                    <h3>👤 Mi Perfil</h3>

                                    <?php if (!empty($tecnicoPerfil)): ?>
                                        <form method="post" action="../controller/tecnico_controller.php" class="profile-form">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="accion" value="editar_perfil">
                                            <input type="hidden" name="id" value="<?= (int)($tecnicoPerfil['id_usuario'] ?? 0) ?>">

                                            <div class="form-group">
                                                <label class="form-label">Nombre</label>
                                                <input class="form-control" name="nombre_usuario"
                                                    value="<?= h($tecnicoPerfil['nombre_usuario'] ?? '') ?>" placeholder="Tu nombre" required>
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Apellidos</label>
                                                <input class="form-control" name="apellidos"
                                                    value="<?= h($tecnicoPerfil['apellidos'] ?? '') ?>" placeholder="Tus apellidos">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Email</label>
                                                <input class="form-control" name="email" type="email"
                                                    value="<?= h($tecnicoPerfil['email'] ?? '') ?>" placeholder="tu@email.com" required>
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Teléfono</label>
                                                <input class="form-control" name="telefono"
                                                    value="<?= h($tecnicoPerfil['telefono'] ?? '') ?>" placeholder="+34 123 456 789">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Dirección</label>
                                                <input class="form-control" name="direccion"
                                                    value="<?= h($tecnicoPerfil['direccion'] ?? '') ?>" placeholder="Tu dirección">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Localidad</label>
                                                <input class="form-control" name="localidad"
                                                    value="<?= h($tecnicoPerfil['localidad'] ?? '') ?>" placeholder="Tu ciudad">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">Contraseña (dejar en blanco para no cambiar)</label>
                                                <div class="input-group">
                                                    <input id="tecnicoPerfilPassword" class="form-control" name="password" type="password" placeholder="" autocomplete="new-password" minlength="6">
                                                    <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="tecnicoPerfilPassword" aria-label="Mostrar contraseña">Mostrar</button>
                                                </div>
                                            </div>

                                            <div class="form-actions">
                                                <button class="btn btn-primary" type="submit">💾 Guardar Cambios</button>
                                                <a href="tecnico.php?view=menu" class="btn btn-outline-danger">Cancelar</a>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif ($view === 'reuniones'): ?>

                            <div class="reuniones-container">
                                <div class="reuniones-header">
                                    <h2>📅 Mis Reuniones</h2>
                                    <p>Programa y gestiona tus reuniones aquí</p>
                                </div>

                                <!-- FORMULARIO CREAR REUNIÓN -->
                                <div class="section-card">
                                    <h6>Crear Nueva Reunión</h6>
                                    <?php $sinEmpresasAsignadas = empty($tecnicoEmpresas); ?>
                                    <?php if ($sinEmpresasAsignadas): ?>
                                        <div class="alert alert-warning mb-3">No tienes empresas asignadas. Pide al administrador que te asigne al menos una empresa para crear reuniones.</div>
                                    <?php endif; ?>
                                    <form method="post" action="../controller/tecnico_controller.php" class="row g-3 align-items-end">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="accion" value="crear_reunion">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">🏢 Empresa</label>
                                            <select class="form-select" id="tecnicoSelectEmpresa" name="id_empresa" required <?= $sinEmpresasAsignadas ? 'disabled' : '' ?>>
                                                <option value="0">Selecciona una empresa</option>
                                                <?php foreach ($tecnicoEmpresas as $empresa): ?>
                                                    <option value="<?= (int)$empresa['id_empresa'] ?>">
                                                        <?= h($empresa['razon_social']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if ($sinEmpresasAsignadas): ?>
                                                    <option value="0" selected>No hay empresas asignadas</option>
                                                <?php endif; ?>
                                            </select>
                                            <?php if ($sinEmpresasAsignadas): ?>
                                                <input type="hidden" name="id_empresa" value="0">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">👤 Cliente</label>
                                            <select class="form-select" id="tecnicoSelectCliente" name="id_cliente_reunion" disabled>
                                                <option value="0">Solo para mí</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">📅 Fecha</label>
                                            <input class="form-control" type="date" name="fecha_reunion" required>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">🕐 Hora</label>
                                            <input class="form-control" type="time" name="hora_reunion" required>
                                        </div>
                                        <div class="col-12 col-md-5">
                                            <label class="form-label">📝 Asunto</label>
                                            <input class="form-control" type="text" name="objetivo" maxlength="1000" placeholder="Asunto (opcional)">
                                        </div>
                                        <div class="col-12 d-flex justify-content-end">
                                            <button class="btn btn-primary" type="submit" <?= $sinEmpresasAsignadas ? 'disabled' : '' ?>>
                                                <span class="btn-icon">➕</span>Agregar Reunión
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <?php
                                $tecnicoCalendarEvents = [];
                                foreach ($tecnicoTodasReuniones as $reunion) {
                                    $idReunion = (int)($reunion['id_reunion'] ?? 0);
                                    $objetivoReunion = trim((string)($reunion['objetivo'] ?? ''));
                                    $fechaReunion = (string)($reunion['fecha_reunion'] ?? '');
                                    $horaReunion = (string)($reunion['hora_reunion'] ?? '');
                                    $participantesReunion = trim((string)($reunion['participantes'] ?? ''));
                                    $titulo = ($objetivoReunion !== '' ? $objetivoReunion : 'Reunion');
                                    $tecnicoCalendarEvents[] = [
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

                                <!-- CALENDARIO -->
                                <div class="section-card">
                                    <h6>📋 Calendario</h6>
                                    <div id="tecnicoReunionesCalendar" class="cliente-reuniones-calendar"></div>
                                </div>

                                <!-- LISTADO DE CITAS -->
                                <div class="section-card">
                                    <h6>📅 Todas Tus Reuniones</h6>
                                    <?php if (empty($tecnicoReuniones)): ?>
                                        <div class="empty-state empty-state-meetings mb-0">
                                            <div class="empty-icon">📭</div>
                                            <div class="empty-title">Sin Reuniones</div>
                                            <div class="empty-message">El calendario se muestra aunque no tengas reuniones asignadas.</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="citas-list">
                                            <?php foreach ($tecnicoReuniones as $reunionLista): ?>
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
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <span class="cita-pill">Reunión</span>
                                                        <span class="cita-pill"><?= h($resumenFecha !== '' ? $resumenFecha : 'Sin fecha') ?></span>
                                                        <details>
                                                            <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                            <form method="post" action="../controller/tecnico_controller.php" class="mt-2 row g-2 align-items-end" style="min-width: 320px;">
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
                                                        <form method="post" action="../controller/tecnico_controller.php" onsubmit="return confirm('¿Eliminar esta reunión?');">
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

                                <div class="section-card">
                                    <h6>🏢 Todas las Reuniones de Tus Empresas</h6>
                                    <?php if (empty($tecnicoTodasReuniones)): ?>
                                        <div class="empty-state empty-state-meetings mb-0">
                                            <div class="empty-icon">📭</div>
                                            <div class="empty-title">Sin Reuniones</div>
                                            <div class="empty-message">No hay reuniones en tus empresas asignadas.</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="citas-list">
                                            <?php foreach ($tecnicoTodasReuniones as $reunionEmpresa): ?>
                                                <?php
                                                $objetivoEmpresa = trim((string)($reunionEmpresa['objetivo'] ?? ''));
                                                $fechaEmpresa = (string)($reunionEmpresa['fecha_reunion'] ?? '');
                                                $horaEmpresaRaw = (string)($reunionEmpresa['hora_reunion'] ?? '');
                                                $horaEmpresa = substr($horaEmpresaRaw, 0, 5);
                                                $participantesEmpresa = trim((string)($reunionEmpresa['participantes'] ?? ''));
                                                $resumenFechaEmpresa = trim($fechaEmpresa . ' · ' . $horaEmpresa, " ·");
                                                ?>
                                                <div class="cita-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div class="me-auto">
                                                        <div class="cita-item-title">📄 <?= h($objetivoEmpresa !== '' ? $objetivoEmpresa : 'Reunión') ?></div>
                                                        <div class="cita-item-subtitle"><?= h($participantesEmpresa !== '' ? $participantesEmpresa : 'Sin participantes') ?></div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <span class="cita-pill">Empresa</span>
                                                        <span class="cita-pill"><?= h($resumenFechaEmpresa !== '' ? $resumenFechaEmpresa : 'Sin fecha') ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- MODAL DE DETALLE DE REUNIÓN -->
                                <div class="modal fade" id="tecnicoReunionDetalleModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">📅 Detalle de Reunión</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body meeting-modal-body">
                                                <div class="meeting-detail-item">
                                                    <span class="detail-label">Fecha:</span>
                                                    <span id="tecnicoDetalleFecha" class="detail-value"></span>
                                                </div>
                                                <div class="meeting-detail-item">
                                                    <span class="detail-label">Hora:</span>
                                                    <span id="tecnicoDetalleHora" class="detail-value"></span>
                                                </div>
                                                <div class="meeting-detail-item">
                                                    <span class="detail-label">Asunto:</span>
                                                    <span id="tecnicoDetalleObjetivo" class="detail-value"></span>
                                                </div>
                                                <div class="meeting-detail-item">
                                                    <span class="detail-label">Participantes:</span>
                                                    <span id="tecnicoDetalleParticipantes" class="detail-value"></span>
                                                </div>
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
    </script>
    <?php if ($view === 'reuniones'): ?>
        <script>
            (function() {
                const calendarEl = document.getElementById('tecnicoReunionesCalendar');
                if (!calendarEl || typeof FullCalendar === 'undefined') {
                    return;
                }

                const events = <?= json_encode($tecnicoCalendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                const detalleFecha = document.getElementById('tecnicoDetalleFecha');
                const detalleHora = document.getElementById('tecnicoDetalleHora');
                const detalleObjetivo = document.getElementById('tecnicoDetalleObjetivo');
                const detalleParticipantes = document.getElementById('tecnicoDetalleParticipantes');
                const modalEl = document.getElementById('tecnicoReunionDetalleModal');
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
                            if (detalleParticipantes) {
                                detalleParticipantes.textContent = (props.participantes && props.participantes.trim() !== '') ? props.participantes : 'Sin participantes';
                            }
                            detalleModal.show();
                        }
                    }
                });

                calendar.render();
            })();
        </script>

        <script>
            (function() {
                const selectEmpresa = document.getElementById('tecnicoSelectEmpresa');
                const selectCliente = document.getElementById('tecnicoSelectCliente');
                if (!selectEmpresa || !selectCliente) {
                    return;
                }

                const clientes = <?= json_encode($tecnicoClientesEmpresa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

                function renderClientes(idEmpresa) {
                    selectCliente.innerHTML = '';

                    const optionSolo = document.createElement('option');
                    optionSolo.value = '0';
                    optionSolo.textContent = 'Solo para mí';
                    selectCliente.appendChild(optionSolo);

                    if (!idEmpresa || idEmpresa === '0') {
                        selectCliente.disabled = true;
                        return;
                    }

                    const filtrados = clientes.filter(c => String(c.id_empresa) === String(idEmpresa));

                    filtrados.forEach(c => {
                        const option = document.createElement('option');
                        option.value = String(c.id_usuario);
                        const nombre = (c.nombre_usuario || '').trim();
                        const apellidos = (c.apellidos || '').trim();
                        option.textContent = (nombre + ' ' + apellidos).trim();
                        selectCliente.appendChild(option);
                    });

                    selectCliente.disabled = false;
                }

                selectEmpresa.addEventListener('change', function() {
                    renderClientes(this.value);
                });

                renderClientes(selectEmpresa.value);
            })();
        </script>
    <?php endif; ?>
</body>

</html>