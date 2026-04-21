<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();
require __DIR__ . '/../config/config.php';

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? 'CLIENTE'));
$esStaff = in_array($rol, ['ADMINISTRADOR', 'TECNICO'], true);

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$msg = (string)($_GET['msg'] ?? '');
$sessionUsername = (string)($_SESSION['user']['username'] ?? $_SESSION['user']['nombre_usuario'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$panelCss = ($rol === 'TECNICO')
    ? '../css/tecnico.css'
    : (($rol === 'CLIENTE') ? '../css/empresa.css' : '../css/admin.css');
$empresaSesion = '';

if (!$esStaff && $usuarioId > 0) {
    $stmtEmpresa = db()->prepare(
        'SELECT e.razon_social
         FROM usuario_empresa ue
         INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
         WHERE ue.id_usuario = ?
         ORDER BY e.razon_social ASC
         LIMIT 1'
    );

    if ($stmtEmpresa) {
        $stmtEmpresa->bind_param('i', $usuarioId);
        $stmtEmpresa->execute();
        $rowEmpresa = $stmtEmpresa->get_result()->fetch_assoc();
        $stmtEmpresa->close();
        $empresaSesion = trim((string)($rowEmpresa['razon_social'] ?? ''));
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Area Documental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="<?= $panelCss ?>">
</head>

<body class="bg-light">

    <?php if ($esStaff): ?>
        <div class="container-fluid py-4">
            <div class="row g-3">

                <aside class="col-12 col-lg-3 col-xl-2">
                    <div class="card shadow-sm border-0 sidebar">
                        <div class="card-body">
                            <div class="sidebar-header">
                                <div class="sidebar-avatar"><?= ($rol === 'TECNICO') ? '👨‍💼' : '🧑‍💼' ?></div>
                                <h5 class="sidebar-title"><?= ($rol === 'TECNICO') ? 'Panel Técnico' : 'Panel Admin' ?></h5>
                            </div>

                            <div class="sidebar-user-info">
                                <div class="info-label">Usuario Actual</div>
                                <div class="info-value"><?= h($sessionUsername) ?></div>
                                <?php if ($sessionEmail !== ''): ?>
                                    <div class="info-email">📧 <?= h($sessionEmail) ?></div>
                                <?php endif; ?>
                            </div>

                            <nav class="sidebar-nav">
                                <a class="nav-button" href="<?= h(($rol === 'TECNICO') ? app_path('/model/tecnico.php?view=menu') : app_path('/model/admin.php?view=menu')) ?>">
                                    <span class="nav-icon">📊</span>
                                    <span>Mi Panel</span>
                                </a>

                                <?php if ($rol === 'ADMINISTRADOR'): ?>
                                    <a class="nav-button" href="<?= h(app_path('/model/admin.php?view=ver_usuarios')) ?>">
                                        <span class="nav-icon">👥</span>
                                        <span>Usuarios</span>
                                    </a>
                                <?php endif; ?>

                                <button class="nav-button nav-collapse"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                                    aria-expanded="false">
                                    <span class="nav-icon">🏢</span>
                                    <span>Empresas</span>
                                    <span class="collapse-icon">▾</span>
                                </button>

                                <div id="menuEmpresas" class="collapse nav-submenu">
                                    <a class="nav-subbutton" href="<?= h(app_path('/model/empresa.php?view=ver_empresas' . (($rol === 'TECNICO') ? '&from=tecnico' : '&from=admin'))) ?>">
                                        <span><?= ($rol === 'TECNICO') ? '📋' : '📊' ?></span>
                                        <span><?= ($rol === 'TECNICO') ? 'Mis Empresas' : 'Directorio de empresas' ?></span>
                                    </a>
                                    <a class="nav-subbutton" href="<?= h(app_path('/model/empresa.php?view=ver_planes' . (($rol === 'TECNICO') ? '&from=tecnico' : '&from=admin'))) ?>">
                                        <span>🗂️</span>
                                        <span>Ver Planes</span>
                                    </a>
                                    <a class="nav-subbutton" href="<?= h(app_path('/model/empresa.php?view=ver_contratos' . (($rol === 'TECNICO') ? '&from=tecnico' : '&from=admin'))) ?>">
                                        <span>🧾</span>
                                        <span>Servicios aceptados</span>
                                    </a>
                                </div>

                                <button class="nav-button nav-collapse"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#menuAreaPrivadaTipos"
                                    aria-expanded="false">
                                    <span class="nav-icon">🔐</span>
                                    <span>Área Privada</span>
                                    <span class="collapse-icon">▾</span>
                                </button>

                                <div id="menuAreaPrivadaTipos" class="collapse nav-submenu">
                                    <a class="nav-subbutton" href="<?= h(($rol === 'TECNICO') ? app_path('/model/tecnico.php?view=perfil') : app_path('/model/admin.php?view=perfil')) ?>">
                                        <span>👤</span>
                                        <span>Mi cuenta</span>
                                    </a>
                                    <a class="nav-subbutton" href="<?= h(($rol === 'TECNICO') ? app_path('/model/tecnico.php?view=reuniones') : app_path('/model/admin.php?view=reuniones')) ?>">
                                        <span>📅</span>
                                        <span>Mis reuniones</span>
                                    </a>
                                </div>

                                <a class="nav-button active" href="index_documentos_tipo.php">
                                    <span class="nav-icon">📁</span>
                                    <span>Subir documentos por tipo</span>
                                </a>

                                <a class="nav-button nav-logout" href="<?= h(app_path('/php/logout.php')) ?>">
                                    <span class="nav-icon">🚪</span>
                                    <span>Cerrar sesión</span>
                                </a>
                            </nav>
                        </div>
                    </div>
                </aside>

                <main class="col-12 col-lg-9 col-xl-10">
                    <div class="card p-4 shadow-sm border-0">
                        <h5>Subir Documento por Tipo</h5>

                        <?php if ($msg !== ''): ?>
                            <div class="alert alert-info py-2"><?= h($msg) ?></div>
                        <?php endif; ?>

                        <form action="../php/procesar_documento_tipo.php"
                            method="POST"
                            enctype="multipart/form-data">
                            <label for="asunto_staff">Asunto:</label>
                            <input type="text" id="asunto_staff" name="asunto" class="form-control mb-3" required>

                            <label for="tipo_staff">Tipo de archivo:</label>
                            <select id="tipo_staff" name="tipo" class="form-control mb-3" required>
                                <option value="">-- Selecciona un tipo --</option>
                                <option value="IGUALDAD">IGUALDAD</option>
                                <option value="SELECCION">SELECCION</option>
                                <option value="SALUD">SALUD</option>
                                <option value="COMUNICACION">COMUNICACION</option>
                                <option value="LGTBI">LGTBI</option>
                                <option value="TOMA DE DATOS">TOMA DE DATOS</option>
                            </select>

                            <input type="file"
                                name="archivo"
                                class="form-control mb-3"
                                accept=".docx,.doc,.pdf,.xlsx,.xls,.csv"
                                required>

                            <button class="btn btn-primary" type="submit">Subir documentacion</button>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    <?php else: ?>
        <div class="container-fluid py-4">
            <div class="row g-3">

                <aside class="col-12 col-lg-3 col-xl-2">
                    <div class="card shadow-sm border-0 sidebar">
                        <div class="card-body">
                            <div class="sidebar-header">
                                <div class="sidebar-avatar">👤</div>
                                <h5 class="sidebar-title">Panel Cliente</h5>
                            </div>

                            <div class="sidebar-user-info">
                                <div class="info-label">Usuario Actual</div>
                                <div class="info-value"><?= h($sessionUsername) ?></div>
                                <?php if ($sessionEmail !== ''): ?>
                                    <div class="info-email">📧 <?= h($sessionEmail) ?></div>
                                <?php endif; ?>
                            </div>

                            <nav class="sidebar-nav">
                                <a class="nav-button" href="index_cliente.php?view=mi_espacio">
                                    <span class="nav-icon">🏠</span>
                                    <span>Inicio</span>
                                </a>

                                <a class="nav-button active" href="index_documentos_tipo.php">
                                    <span class="nav-icon">📁</span>
                                    <span>Subir documentos por tipo</span>
                                </a>

                                <a class="nav-button" href="../php/archivos_subidos.php">
                                    <span class="nav-icon">📦</span>
                                    <span>Archivos subidos</span>
                                </a>

                                <a class="nav-button nav-logout" href="<?= h(app_path('/php/logout.php')) ?>">
                                    <span class="nav-icon">🚪</span>
                                    <span>Cerrar sesión</span>
                                </a>
                            </nav>
                        </div>
                    </div>
                </aside>

                <main class="col-12 col-lg-9 col-xl-10">
                    <div class="card p-4 shadow-sm border-0">
                        <h5>Subir Documento por Tipo</h5>

                        <?php if ($msg !== ''): ?>
                            <div class="alert alert-info py-2"><?= h($msg) ?></div>
                        <?php endif; ?>

                        <form action="../php/procesar_documento_tipo.php"
                            method="POST"
                            enctype="multipart/form-data">
                            <label for="referencia_empresa_cliente">Empresa / Referencia:</label>
                            <input type="text"
                                id="referencia_empresa_cliente"
                                name="referencia_empresa"
                                class="form-control mb-3"
                                value="<?= h($empresaSesion) ?>"
                                data-empresa-base="<?= h($empresaSesion) ?>"
                                readonly>

                            <label for="asunto_cliente">Asunto:</label>
                            <input type="text" id="asunto_cliente" name="asunto" class="form-control mb-3" required>

                            <label for="tipo_cliente">Tipo de archivo:</label>
                            <select id="tipo_cliente" name="tipo" class="form-control mb-3" required>
                                <option value="">-- Selecciona un tipo --</option>
                                <option value="IGUALDAD">IGUALDAD</option>
                                <option value="SELECCION">SELECCION</option>
                                <option value="SALUD">SALUD</option>
                                <option value="COMUNICACION">COMUNICACION</option>
                                <option value="LGTBI">LGTBI</option>
                                <option value="TOMA DE DATOS">TOMA DE DATOS</option>
                            </select>

                            <input type="file"
                                name="archivo"
                                class="form-control mb-3"
                                accept=".docx,.doc,.pdf,.xlsx,.xls,.csv"
                                required>

                            <button class="btn btn-primary" type="submit">Subir documento</button>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const referenciaInput = document.getElementById('referencia_empresa_cliente');
            const tipoSelect = document.getElementById('tipo_cliente');

            if (!referenciaInput || !tipoSelect) {
                return;
            }

            const empresaBase = (referenciaInput.dataset.empresaBase || '').trim();

            function actualizarReferencia() {
                if (empresaBase === '') {
                    return;
                }

                const tipoActual = (tipoSelect.value || '').trim().toUpperCase();

                if (tipoActual === 'TOMA DE DATOS') {
                    referenciaInput.value = empresaBase + ' - TOMA DE DATOS';
                    return;
                }

                referenciaInput.value = empresaBase;
            }

            tipoSelect.addEventListener('change', actualizarReferencia);
            actualizarReferencia();
        })();
    </script>
</body>

</html>