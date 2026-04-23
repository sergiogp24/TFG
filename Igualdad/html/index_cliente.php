<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();
require_role('CLIENTE');
require_once __DIR__ . '/../php/helpers.php';
require_once __DIR__ . '/../php/mails.php';
require __DIR__ . '/../config/config.php';

function ensure_reuniones_empresa_column(mysqli $db): void
{
    $check = $db->query("\n        SELECT 1\n        FROM information_schema.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = 'reuniones'\n          AND COLUMN_NAME = 'id_empresa'\n        LIMIT 1\n    ");
    $exists = ($check instanceof mysqli_result) && ($check->num_rows > 0);
    if ($check instanceof mysqli_result) {
        $check->close();
    }

    if (!$exists) {
        $db->query('ALTER TABLE reuniones ADD COLUMN id_empresa INT NULL');
    }
}

function formatear_fecha_resumen(string $fecha): string
{
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return '';
    }

    $meses = [
        'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    $dia = date('j', $timestamp);
    $mesIndex = (int)date('n', $timestamp) - 1;

    return $dia . ' ' . ($meses[$mesIndex] ?? date('m', $timestamp));
}

function empresa_tiene_registro_retributivo(int $idEmpresa): bool
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

function obtener_word_final_empresa(int $idEmpresa): ?array
{
    if ($idEmpresa <= 0) {
        return null;
    }

    $sql = '
        SELECT id_archivo, nombre_original, subido_en
        FROM archivos
        WHERE UPPER(TRIM(tipo)) = "WORD_FINAL" AND id_empresa = ?
        ORDER BY subido_en DESC, id_archivo DESC
        LIMIT 1';

    $stmt = db()->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

$view = (string)($_GET['view'] ?? 'mi_espacio');
if (!in_array($view, ['menu', 'mi_espacio', 'privada', 'perfil', 'reuniones'], true)) {
    $view = 'mi_espacio';
}

$msg = (string)($_GET['msg'] ?? '');
$sessionUsername = (string)($_SESSION['user']['username'] ?? $_SESSION['user']['nombre_usuario'] ?? 'usuario');
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
    ensure_reuniones_empresa_column(db());
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

    if (in_array($view, ['privada', 'reuniones'], true)) {
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

        $tecnicosEmpresaMap = [];

        $stmtTecnicosEmpresa = db()->prepare(
            'SELECT DISTINCT ue.id_empresa, e.razon_social, u.id_usuario, u.nombre_usuario, u.apellidos
             FROM usuario_empresa ue
             INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
             INNER JOIN usuario u ON u.id_usuario = ue.id_usuario
             INNER JOIN rol r ON r.id = u.rol_id
             WHERE UPPER(TRIM(r.nombre)) LIKE "TECNICO%"
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
             WHERE UPPER(TRIM(r.nombre)) LIKE "TECNICO%"
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

    foreach ($empresasDisponibles as $empresaDisponible) {
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
        foreach ($empresasDisponibles as $empresaDisponible) {
            if ((int)($empresaDisponible['id_empresa'] ?? 0) === $idEmpresaSeleccionada) {
                $empresaAsignada = $empresaDisponible;
                break;
            }
        }
    }
}

$sinEmpresaAsignada = ($empresaAsignada === null);
$idEmpresaAsignada = (int)($empresaAsignada['id_empresa'] ?? 0);
$registroSubido = (!$sinEmpresaAsignada && empresa_tiene_registro_retributivo($idEmpresaAsignada));

// BLOQUEO DE ACCESO A COMPLEMENTOS DESDE AQUÍ
if ($idEmpresaAsignada > 0 && !$registroSubido) {
    
    // Opcional: puedes ocultar o deshabilitar aquí los enlaces/botones a complementos
    // Por ejemplo, si tienes un botón o enlace a complemento_formularios.php:
    echo '<style>.btn-complemento, .link-complemento { pointer-events: none; opacity: 0.5; }</style>';
}
$idEmpresaWordFinalSeleccionada = (int)($_GET['id_empresa_word_final'] ?? 0);
$wordFinalPorEmpresa = [];

if (!empty($empresasDisponibles)) {
    foreach ($empresasDisponibles as $empresaDisponible) {
        $idEmpresaDisponible = (int)($empresaDisponible['id_empresa'] ?? 0);
        if ($idEmpresaDisponible <= 0) {
            continue;
        }

        $wordFinal = obtener_word_final_empresa($idEmpresaDisponible);
        if ($wordFinal !== null) {
            $wordFinalPorEmpresa[$idEmpresaDisponible] = $wordFinal;
        }
    }

    if ($idEmpresaWordFinalSeleccionada <= 0) {
        $idEmpresaWordFinalSeleccionada = $idEmpresaAsignada;
    }

    $empresaWordFinalValida = false;
    foreach ($empresasDisponibles as $empresaDisponible) {
        if ((int)($empresaDisponible['id_empresa'] ?? 0) === $idEmpresaWordFinalSeleccionada) {
            $empresaWordFinalValida = true;
            break;
        }
    }

    if (!$empresaWordFinalValida) {
        $idEmpresaWordFinalSeleccionada = (int)($empresasDisponibles[0]['id_empresa'] ?? 0);
    }
}

$wordFinalSeleccionado = $wordFinalPorEmpresa[$idEmpresaWordFinalSeleccionada] ?? null;

$pendientesEspacio = 0;
$empresasPendientesLista = [];
if (!empty($empresasDisponibles)) {
    foreach ($empresasDisponibles as $empresa) {
        $idEmp = (int)($empresa['id_empresa'] ?? 0);
        if ($idEmp > 0 && !empresa_tiene_registro_retributivo($idEmp)) {
            $pendientesEspacio++;
            $empresasPendientesLista[] = $empresa['razon_social'] ?? 'Sin nombre';
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
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../css/global.css?v=<?= (int)$globalCssVersion ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?= (int)$adminCssVersion ?>">
    <link rel="stylesheet" href="../css/cliente.css?v=<?= (int)$clienteCssVersion ?>">
</head>

<body class="bg-light cliente-page">
    <?php $view = $view ?? 'mi_espacio'; ?>
    <div class="container-fluid py-4">
        <div class="row g-3">

            <!-- SIDEBAR -->
            <aside class="col-12 col-lg-3 col-xl-2">
                <div class="card shadow-sm border-0 sidebar">
                    <div class="card-body">
                        <!-- Header Sidebar -->
                        <div class="sidebar-header">
                            <div class="sidebar-avatar" style="background: linear-gradient(135deg, var(--color-blue), var(--color-teal));">💼</div>
                            <h5 class="sidebar-title">Panel Cliente</h5>
                        </div>

                        <!-- User Info -->
                        <div class="sidebar-user-info">
                            <div class="info-label">Usuario Actual</div>
                            <div class="info-value"><?= h($sessionUsername) ?></div>
                            <?php if ($sessionEmail !== ''): ?>
                                <div class="info-email">📧 <?= h($sessionEmail) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Navegación -->
                        <nav class="sidebar-nav">
                            <!-- Mi Espacio -->
                            <a class="nav-button <?= ($view === 'mi_espacio') ? 'active' : '' ?>" href="index_cliente.php?view=mi_espacio">
                                <span class="nav-icon">📊</span>
                                <span>Mi Espacio</span>
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
                                <a class="nav-subbutton <?= ($view === 'perfil') ? 'active' : '' ?>" href="index_cliente.php?view=perfil">
                                    <span>👤</span>
                                    <span>Mi Cuenta</span>
                                </a>
                                <a class="nav-subbutton <?= ($view === 'reuniones') ? 'active' : '' ?>" href="index_cliente.php?view=reuniones">
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

            <main class="col-12 col-lg-9 col-xl-10">
                <div class="card panel <?= in_array($view, ['menu', 'mi_espacio', 'reuniones'], true) ? 'panel-wide' : '' ?> mx-auto shadow-sm border-0">
                    <div class="card-body p-4">
                        <header class="d-flex align-items-center justify-content-between mb-3">
                            <?php if (in_array($view, ['privada', 'perfil', 'reuniones'], true)): ?>
                                <h4 class="mb-0">Panel de Cliente</h4>
                            <?php elseif ($view === 'mi_espacio'): ?>
                                <h4 class="mb-0">Mi espacio</h4>
                            <?php else: ?>
                                <h4 class="mb-0">Subir Documento del Registro Retributivo</h4>
                            <?php endif; ?>
                        </header>

                        <?php if ($msg !== ''): ?>
                            <div class="alert alert-info py-2"><?= h($msg) ?></div>
                        <?php endif; ?>

                        <?php if ($view === 'privada'): ?>
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
                            foreach ($clienteReuniones as $reunion) {
                                $idReunion = (int)($reunion['id_reunion'] ?? 0);
                                $objetivoReunion = trim((string)($reunion['objetivo'] ?? ''));
                                $fechaReunion = (string)($reunion['fecha_reunion'] ?? '');
                                $horaReunion = (string)($reunion['hora_reunion'] ?? '');
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
                                                <?php foreach ($empresasDisponibles as $empresaReunion): ?>
                                                    <option value="<?= (int)($empresaReunion['id_empresa'] ?? 0) ?>">
                                                        <?= h((string)($empresaReunion['razon_social'] ?? '')) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">👤 Técnico</label>
                                            <select class="form-select" id="clienteSelectTecnicoReunion" name="id_tecnico_reunion" disabled>
                                                <option value="0">Sin asignar a técnico</option>
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
                                            <?php foreach ($clienteReuniones as $reunionLista): ?>
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
                        <?php elseif ($view === 'mi_espacio'): ?>
                            <div class="space-shell mb-8">
                                <div class="d-flex align-items-end justify-content-between flex-wrap gap-6 mb-6">
                                    <div>
                                        <div class="space-kicker">Mi espacio</div>
                                        <h3 class="mb-1">Resumen rápido de trabajo</h3>
                                        <div class="text-muted">Acceso directo a lo importante de tu día a día.</div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-12 col-md-4">
                                        <div class="space-stat-card h-100">
                                            <div class="space-stat-label">Próxima reunión</div>
                                            <div class="space-stat-value">
                                                <?php if (!empty($proximaReunion)): ?>
                                                    <?php
                                                    $fechaResumen = formatear_fecha_resumen((string)($proximaReunion['fecha_reunion'] ?? ''));
                                                    $horaResumen = substr((string)($proximaReunion['hora_reunion'] ?? ''), 0, 5);
                                                    echo h(trim($fechaResumen . ' · ' . $horaResumen, " ·"));
                                                    ?>
                                                <?php else: ?>
                                                    Sin reuniones
                                                <?php endif; ?>
                                            </div>
                                            <div class="space-stat-icon">◔</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <div class="space-stat-card h-100">
                                            <div class="space-stat-label">Clientes con Registro retributivo pendiente</div>
                                            <div class="space-stat-value"><?= (int)$pendientesEspacio ?></div>
                                            <div class="space-stat-icon" <?= $pendientesEspacio > 0 ? 'style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#modalEmpresasPendientes" title="Ver empresas pendientes"' : '' ?>>▣</div>
                                            <?php if ($pendientesEspacio > 0): ?>
                                            <div class="mt-2 text-end">
                                                <button class="btn btn-sm btn-link text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#modalEmpresasPendientes">Ver detalles</button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-12 col-xl-5">
                                        <div class="space-panel h-100">
                                            <h4 class="mb-1">Qué tengo que hacer ahora</h4>
                                            <div class="text-muted small mb-3">Vista guiada para el cliente.</div>

                                            <div class="space-task-list d-grid gap-3">
                                                <div class="space-task-item">Subir registro retributivo si ya disponen de él</div>
                                                <div class="space-task-item">Si no lo tienen, descargar la plantilla y completarla</div>
                                                <div class="space-task-item">Completar los datos cuantitativos del formulario</div>
                                                <div class="space-task-item">Completar cuestionario cualitativo</div>
                                                <div class="space-task-item">Revisar próximas reuniones</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-xl-7">
                                        <div class="space-panel h-100">
                                            <h4 class="mb-1">Accesos rápidos</h4>
                                            <div class="text-muted small mb-3">Entradas directas a tus áreas visibles.</div>

                                            <div class="row g-3">
                                                <div class="col-12 col-md-4">
                                                    <a class="space-quick-card h-100" href="index_cliente.php?view=menu">
                                                        <div class="space-quick-icon">⛨</div>
                                                        <div class="space-quick-title">Plan de igualdad</div>
                                                        <div class="space-quick-text">Registro retributivo, datos cuantitativos y cuestionario cualitativo.</div>
                                                    </a>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <a class="space-quick-card h-100" href="mantenimiento.php">
                                                        <div class="space-quick-icon">🔧</div>
                                                        <div class="space-quick-title">Mantenimiento</div>
                                                        <div class="space-quick-text">Áreas, medidas y formularios del mantenimiento.</div>
                                                    </a>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <div class="space-quick-card h-100">
                                                        <div class="space-quick-icon">📄</div>
                                                        <div class="space-quick-title">Word final</div>
                                                        <div class="space-quick-text mb-2">Selecciona empresa y descarga el documento final.</div>

                                                        <?php if ($sinEmpresaAsignada || empty($empresasDisponibles)): ?>
                                                            <div class="alert alert-warning py-2 mb-0">
                                                                No tienes empresas asignadas para descargar el Word final.
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="row g-2 align-items-end">
                                                                <div class="col-12">
                                                                    <label for="id_empresa_word_final" class="form-label small mb-1">Selecciona empresa</label>
                                                                    <select id="id_empresa_word_final" name="id_empresa_word_final" class="form-select form-select-sm">
                                                                        <?php foreach ($empresasDisponibles as $empresaDisponible): ?>
                                                                            <?php $idEmpresaOpcionWord = (int)($empresaDisponible['id_empresa'] ?? 0); ?>
                                                                            <option value="<?= $idEmpresaOpcionWord ?>" <?= ($idEmpresaOpcionWord === $idEmpresaWordFinalSeleccionada) ? 'selected' : '' ?>>
                                                                                <?= h((string)($empresaDisponible['razon_social'] ?? '')) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="mt-2">
                                                                <a
                                                                    id="btnDescargarWordFinal"
                                                                    class="btn btn-outline-success px-4"
                                                                    href="<?= h(($wordFinalSeleccionado !== null) ? app_path('/php/download_archivo_subido.php?kind=archivos&id=' . (int)($wordFinalSeleccionado['id_archivo'] ?? 0)) : '#') ?>"
                                                                    <?= ($wordFinalSeleccionado === null) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                                                                    Descargar Word final
                                                                </a>
                                                            </div>

                                                            <?php $fechaWordFinal = (string)($wordFinalSeleccionado['subido_en'] ?? ''); ?>
                                                            <div id="wordFinalEstado" class="small <?= ($wordFinalSeleccionado !== null) ? 'text-muted' : 'text-info' ?> mt-2">
                                                                <?php if ($wordFinalSeleccionado !== null && $fechaWordFinal !== ''): ?>
                                                                    Ultima subida: <?= h($fechaWordFinal) ?>
                                                                <?php else: ?>
                                                                    Todavia no hay Word final subido para la empresa seleccionada.
                                                                <?php endif; ?>
                                                            </div>

                                                            <div id="wordFinalData" class="d-none">
                                                                <?php foreach ($empresasDisponibles as $empresaDisponible): ?>
                                                                    <?php $idEmpresaOpcionWord = (int)($empresaDisponible['id_empresa'] ?? 0); ?>
                                                                    <?php $wordFinalEmpresa = $wordFinalPorEmpresa[$idEmpresaOpcionWord] ?? null; ?>
                                                                    <?php $downloadWordUrl = ''; ?>
                                                                    <?php if ($wordFinalEmpresa !== null): ?>
                                                                        <?php $downloadWordUrl = app_path('/php/download_archivo_subido.php?kind=archivos&id=' . (int)($wordFinalEmpresa['id_archivo'] ?? 0)); ?>
                                                                    <?php endif; ?>
                                                                    <?php $subidoEnWord = ($wordFinalEmpresa !== null) ? (string)($wordFinalEmpresa['subido_en'] ?? '') : ''; ?>
                                                                    <span
                                                                        class="word-final-item"
                                                                        data-id-empresa="<?= $idEmpresaOpcionWord ?>"
                                                                        data-download-url="<?= h($downloadWordUrl) ?>"
                                                                        data-subido-en="<?= h($subidoEnWord) ?>"></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
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
                                        <div class="text-muted">Si ya existe, se sube. Si no, se descarga la plantilla, se rellena y se vuelve a subir.</div>
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

                                                <?php if (!$sinEmpresaAsignada): ?>
                                                    <div class="mb-3">
                                                        <label for="nombre_empresa_cliente" class="form-label">Empresa / Referencia</label>
                                                        <select id="nombre_empresa_cliente" name="id_empresa" class="form-select" required>
                                                            <option value="" selected>-- seleccionar --</option>
                                                            <?php foreach ($empresasDisponibles as $empresaDisponible): ?>
                                                                <?php $idEmpresaOpcion = (int)($empresaDisponible['id_empresa'] ?? 0); ?>
                                                                <option value="<?= $idEmpresaOpcion ?>">
                                                                    <?= h((string)($empresaDisponible['razon_social'] ?? '')) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
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
                                                    <label for="Asunto" class="form-label">Observaciones</label>
                                                    <input type="text" id="Asunto" name="asunto" class="form-control">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="Tipo" class="form-label">Tipo de archivo</label>
                                                    <select id="Tipo" name="tipo" class="form-select" required>
                                                        <option value="REGISTRO_RETRIBUTIVO">Registro Retributivo</option>
                                                    </select>
                                                </div>

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
                                                <h5 class="mb-1">Descargar plantilla</h5>
                                                <div class="upload-action-text mb-3">Si necesitas el formato base, bájalo y rellénalo antes de volver a subirlo.</div>

                                                <a class="btn btn-outline-secondary px-4 align-self-start" href="<?= h(app_path('/php/download_archivo.php?id=1')) ?>">
                                                    Descargar plantilla
                                                </a>

                                                <div class="mt-4">
                                                    <label class="form-label d-block">Datos Cuantitativos</label>
                                                    <?php if (!$registroSubido): ?>
                                                        <div class="alert alert-warning py-2 mb-0">
                                                            Debes subir primero el Registro Retributivo para poder subir los datos sobre Bajas, Formacion, Excedencias y Permisos.
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="bajas">Bajas</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="formacion">Formacion</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="excedencias">Excedencias</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="permisos">Permisos retributivos</button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                 <div class="mt-4">
                                                    <label class="form-label d-block">Cuestionario Cualitativo</label>
                                                    <?php if (!$registroSubido): ?>
                                                        <div class="alert alert-warning py-2 mb-0">
                                                            Debes subir primero el Registro Retributivo para poder subir los cuestionarios sobre Seleccion Peronal, Promocion Profesional, Formación, Conciliación, Infrarepresentación femenina, Salud Laboral, Acoso Sexual y Violencia de Género.
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="seleccion_personal">Seleccion Personal</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="promocion_profesional">Promoción Profesional</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="cuestionario_formacion">Formación</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="conciliacion">Conciliación y Corresponsabilidad</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="infrarrepresentacion">Infrarepresentación femenina</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="salud_laboral">Salud Laboral</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="acoso_sexual">Prevención del Acoso Sexual y por Razón de sexo</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="violencia_genero">Violencia de Género</button>
                                                            <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="comunicacion">Comunicación e identidad corporativa</button>
                                                        </div>
                                                    <?php endif; ?>
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

    <?php if ($view === 'menu'): ?>
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
    <?php endif; ?>

    <?php if ($view === 'mi_espacio' && $pendientesEspacio > 0): ?>
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

                const events = <?= json_encode($clienteCalendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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
                    optionDefault.textContent = 'Sin asignar a técnico';
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
                        option.textContent = empresa !== ''
                            ? ((nombreCompleto !== '' ? nombreCompleto : 'Técnico') + ' - ' + empresa)
                            : (nombreCompleto !== '' ? nombreCompleto : 'Técnico');
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
                const modalElement = document.getElementById('modalComplementoFormularios');
                const iframe = document.getElementById('complementoFormulariosFrame');
                const botones = document.querySelectorAll('.btn-open-complemento');
                const modal = (modalElement && iframe && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalElement) : null;
                const empresaSelect = document.getElementById('nombre_empresa_cliente');
                const idEmpresaClienteInicial = <?= ($empresaAsignada !== null) ? (int)$empresaAsignada['id_empresa'] : 0 ?>;

                botones.forEach((btn) => {
                    btn.addEventListener('click', function() {
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

                        const tab = (btn.getAttribute('data-tab') || 'bajas').trim();
                        let src = 'complemento_formularios.php?embed=1&tab=' + encodeURIComponent(tab);
                        if (idEmpresaCliente > 0) {
                            src += '&id_empresa=' + encodeURIComponent(String(idEmpresaCliente));
                        }
                        iframe.src = src;
                        modal.show();
                    });
                });

                if (modalElement && iframe) {
                    modalElement.addEventListener('hidden.bs.modal', function() {
                        iframe.src = '';
                    });
                }

            })();
        </script>
    <?php endif; ?>
    <?php if ($view === 'mi_espacio'): ?>
        <script>
            (function() {
                const selectWordFinal = document.getElementById('id_empresa_word_final');
                const btnDescargarWordFinal = document.getElementById('btnDescargarWordFinal');
                const estadoWordFinal = document.getElementById('wordFinalEstado');
                const itemsWordFinal = document.querySelectorAll('.word-final-item');

                if (!selectWordFinal || !btnDescargarWordFinal || !estadoWordFinal || itemsWordFinal.length === 0) {
                    return;
                }

                const dataPorEmpresa = {};
                itemsWordFinal.forEach((item) => {
                    const idEmpresa = Number.parseInt(item.getAttribute('data-id-empresa') || '0', 10);
                    if (!Number.isFinite(idEmpresa) || idEmpresa <= 0) {
                        return;
                    }

                    dataPorEmpresa[idEmpresa] = {
                        downloadUrl: item.getAttribute('data-download-url') || '',
                        subidoEn: item.getAttribute('data-subido-en') || ''
                    };
                });

                function actualizarWordFinal() {
                    const idEmpresa = Number.parseInt(selectWordFinal.value || '0', 10);
                    const data = (Number.isFinite(idEmpresa) && idEmpresa > 0) ? (dataPorEmpresa[idEmpresa] || null) : null;
                    const downloadUrl = data ? (data.downloadUrl || '') : '';
                    const subidoEn = data ? (data.subidoEn || '') : '';

                    if (downloadUrl !== '') {
                        btnDescargarWordFinal.href = downloadUrl;
                        btnDescargarWordFinal.classList.remove('disabled');
                        btnDescargarWordFinal.removeAttribute('aria-disabled');
                        btnDescargarWordFinal.removeAttribute('tabindex');
                        estadoWordFinal.classList.remove('text-info');
                        estadoWordFinal.classList.add('text-muted');
                        estadoWordFinal.textContent = (subidoEn !== '')
                            ? ('Ultima subida: ' + subidoEn)
                            : 'Word final disponible para descarga.';
                        return;
                    }

                    btnDescargarWordFinal.href = '#';
                    btnDescargarWordFinal.classList.add('disabled');
                    btnDescargarWordFinal.setAttribute('aria-disabled', 'true');
                    btnDescargarWordFinal.setAttribute('tabindex', '-1');
                    estadoWordFinal.classList.remove('text-muted');
                    estadoWordFinal.classList.add('text-info');
                    estadoWordFinal.textContent = 'Todavia no hay Word final subido para la empresa seleccionada.';
                }

                selectWordFinal.addEventListener('change', actualizarWordFinal);
                actualizarWordFinal();
            })();
        </script>
    <?php endif; ?>
</body>

</html>