<!doctype html>
<html lang="es">
<!--
    Plantilla: Panel Técnico
    ------------------------
    Panel de control para usuarios con rol TÉCNICO. Contiene:
    - Vista `menu` con métricas y accesos rápidos.
    - Formularios para `contacto_empresa`, `perfil` y `reuniones`.
    - Scripts para calendario (FullCalendar) y manejo de UI.
    Esta plantilla asume que el controlador prepara variables como
    `$tecnicoUsername`, `$tecnicoStats`, `$tecnicoEmpresas`, `$tecnicoReuniones`,
    y otras listas necesarias para cada vista.
-->

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Tecnico</title>

    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/tecnico.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php
    // Determina qué sub-vista renderizar. Por defecto mostramos el 'menu' del técnico.
    $view = $view ?? 'menu';
    ?>
    <div class="container-fluid py-4">
        <div class="row g-3">

            <!-- SIDEBAR -->
            <?php include __DIR__ . '/../php/fragments/sidebar.php'; ?>

            <!-- MAIN CONTENT -->
            <main class="col-12 col-md-9 col-xl-10">
                <div class="card panel shadow-sm border-0">
                    <div class="card-body p-4">
                        <!-- HEADER DENTRO DE LA TARJETA -->
                        <div class="mb-4">
                            <h2 class="fw-bold mb-1">Bienvenido, <?= h($tecnicoUsername ?? 'Técnico') ?></h2>
                            <p class="text-muted small mb-0">Panel de control y gestión de empresas</p>
                            <hr class="mt-3 mb-0 opacity-10">
                        </div>

                        <?php if (!empty($_GET['msg'])): ?>
                            <div class="alert alert-info">
                                ✅ <?= h($_GET['msg']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($view === 'menu'): ?>

                            <!-- Sección: Menú principal del técnico (métricas, avisos y acciones rápidas) -->

                            <!-- NOTIFICACIONES -->
                            <?php if (!empty($avisosTecnico)): ?>
                                <div class="alerts-section">
                                    <?php foreach (($avisosTecnico ?? []) as $aviso): ?>
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

                            <!-- Sección: Contacto con empresa — formulario para enviar emails a empresas asignadas -->

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
                                                    <?php foreach (($tecnicoEmpresasContacto ?? []) as $empresaContacto): ?>
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

                            <!-- Sección: Perfil técnico — formulario para editar datos personales y contraseña -->

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

                            <!-- Sección: Reuniones — crear, listar y calendario de reuniones del técnico -->

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
                                                <?php foreach (($tecnicoEmpresas ?? []) as $empresa): ?>
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
                                foreach (($tecnicoTodasReuniones ?? []) as $reunion) {
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
                                            <?php foreach (($tecnicoReuniones ?? []) as $reunionLista): ?>
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
                                            <?php foreach (($tecnicoTodasReuniones ?? []) as $reunionEmpresa): ?>
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
    <!-- Script: toggles para mostrar/ocultar contraseñas en formularios -->
    <script>
        (function() {
            // Selecciona todos los botones que controlan visibilidad de contraseña
            const toggleButtons = document.querySelectorAll('[data-password-toggle]');
            if (!toggleButtons.length) {
                // No hay botones en esta página, salir
                return;
            }

            // Para cada botón añadimos el handler de click que alterna el tipo del input
            toggleButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    // Obtener el id del input objetivo desde el atributo data-target
                    const targetId = button.getAttribute('data-target');
                    if (!targetId) {
                        return; // atributo mal formado
                    }

                    // Localizar el input y validar
                    const input = document.getElementById(targetId);
                    if (!input) {
                        return; // input no encontrado
                    }

                    // Alternar entre 'password' y 'text'
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';

                    // Actualizar texto del botón y etiqueta accesible
                    button.textContent = isPassword ? 'Ocultar' : 'Mostrar';
                    button.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
                });
            });
        })();
    </script>
    <?php if ($view === 'reuniones'): ?>
        <!-- Scripts específicos para la vista 'reuniones': inicialización de calendario y carga de clientes por empresa -->
        <script>
            (function() {
                // Inicialización del calendario de reuniones usando FullCalendar
                const calendarEl = document.getElementById('tecnicoReunionesCalendar');
                // Salir si no existe el contenedor o la librería no está cargada
                if (!calendarEl || typeof FullCalendar === 'undefined') {
                    return;
                }

                // Eventos serializados desde PHP
                const events = <?= json_encode($tecnicoCalendarEvents ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

                // Elementos del modal donde mostraremos el detalle al clicar un evento
                const detalleFecha = document.getElementById('tecnicoDetalleFecha');
                const detalleHora = document.getElementById('tecnicoDetalleHora');
                const detalleObjetivo = document.getElementById('tecnicoDetalleObjetivo');
                const detalleParticipantes = document.getElementById('tecnicoDetalleParticipantes');
                const modalEl = document.getElementById('tecnicoReunionDetalleModal');
                const detalleModal = (modalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalEl) : null;
                const isMobile = window.matchMedia('(max-width: 767.98px)').matches;

                // Crear instancia del calendario con opciones básicas
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
                    // Al hacer click en un evento mostramos un modal con detalles
                    eventClick: function(info) {
                        const ev = info.event;
                        const props = ev.extendedProps || {};

                        if (detalleModal) {
                            // Rellenar campos del modal desde extendedProps
                            detalleFecha.textContent = props.fecha || '-';
                            detalleHora.textContent = props.hora || '-';
                            detalleObjetivo.textContent = (props.objetivo && props.objetivo.trim() !== '') ? props.objetivo : 'Sin objetivo';
                            if (detalleParticipantes) {
                                detalleParticipantes.textContent = (props.participantes && props.participantes.trim() !== '') ? props.participantes : 'Sin participantes';
                            }
                            // Mostrar modal (Bootstrap)
                            detalleModal.show();
                        }
                    }
                });

                // Render del calendario
                calendar.render();
            })();
        </script>

        <script>
            (function() {
                // Gestiona el select de clientes dependiendo de la empresa seleccionada
                const selectEmpresa = document.getElementById('tecnicoSelectEmpresa');
                const selectCliente = document.getElementById('tecnicoSelectCliente');
                if (!selectEmpresa || !selectCliente) {
                    return; // elementos no presentes
                }

                // Datos de clientes por empresa serializados desde PHP
                const clientes = <?= json_encode($tecnicoClientesEmpresa ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

                // Rellena el select de clientes según la id de empresa indicada
                function renderClientes(idEmpresa) {
                    // Limpiar opciones previas
                    selectCliente.innerHTML = '';

                    // Opción por defecto: 'Solo para mí'
                    const optionSolo = document.createElement('option');
                    optionSolo.value = '0';
                    optionSolo.textContent = 'Solo para mí';
                    selectCliente.appendChild(optionSolo);

                    // Si no hay empresa seleccionada, mantenemos deshabilitado
                    if (!idEmpresa || idEmpresa === '0') {
                        selectCliente.disabled = true;
                        return;
                    }

                    // Filtrar clientes que pertenezcan a la empresa seleccionada
                    const filtrados = clientes.filter(c => String(c.id_empresa) === String(idEmpresa));

                    // Añadir una opción por cada cliente encontrado
                    filtrados.forEach(c => {
                        const option = document.createElement('option');
                        option.value = String(c.id_usuario);
                        const nombre = (c.nombre_usuario || '').trim();
                        const apellidos = (c.apellidos || '').trim();
                        const razonSocial = (c.razon_social || '').trim();
                        const nombreCompleto = (nombre + ' ' + apellidos).trim();
                        option.textContent = razonSocial !== ''
                            ? ((nombreCompleto !== '' ? nombreCompleto : 'Cliente') + ' - ' + razonSocial)
                            : (nombreCompleto !== '' ? nombreCompleto : 'Cliente');
                        selectCliente.appendChild(option);
                    });

                    // Habilitar el select ahora que tiene opciones válidas
                    selectCliente.disabled = false;
                }

                // Cuando la empresa cambia, re-renderizamos la lista de clientes
                selectEmpresa.addEventListener('change', function() {
                    renderClientes(this.value);
                });

                // Inicializar con el valor actual (útil al cargar la página con un valor por defecto)
                renderClientes(selectEmpresa.value);
            })();
        </script>
    <?php endif; ?>
</body>

</html>