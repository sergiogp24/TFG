<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();
require_once __DIR__ . '/../php/helpers.php';
require __DIR__ . '/../config/config.php';

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? 'CLIENTE'));
$esStaff = in_array($rol, ['ADMINISTRADOR', 'TECNICO'], true);
$esTecnico = ($rol === 'TECNICO');

if (!$esStaff) {
    header('Location: index_cliente.php');
    exit;
}

function empresa_tiene_registro_retributivo(int $idEmpresa): bool
{
    if ($idEmpresa <= 0) {
        return false;
    }

    // Primero intentar con la relación cliente_medida
    $sql = '
        SELECT 1
        FROM archivos a
        INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
        INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
        WHERE a.tipo = ? AND ac.id_empresa = ?
        LIMIT 1';

    $stmt = db()->prepare($sql);
    if ($stmt) {
        $tipoRegistro = 'REGISTRO_RETRIBUTIVO';
        $stmt->bind_param('si', $tipoRegistro, $idEmpresa);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($result) {
            return true;
        }
    }

    // Si no encuentra con cliente_medida, buscar directamente si existe REGISTRO_RETRIBUTIVO para esa empresa
    // (en caso de que se haya guardado sin id_cliente_medida pero con id_empresa)
    $sql2 = '
        SELECT 1
        FROM archivos a
        WHERE a.tipo = ? AND a.id_empresa = ?
        LIMIT 1';

    $stmt2 = db()->prepare($sql2);
    if (!$stmt2) {
        return false;
    }

    $tipoRegistro = 'REGISTRO_RETRIBUTIVO';
    $stmt2->bind_param('si', $tipoRegistro, $idEmpresa);
    $stmt2->execute();
    $ok = (bool)$stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    return $ok;
}

$msg = (string)($_GET['msg'] ?? '');
$sessionUsername = (string)($_SESSION['user']['username'] ?? $_SESSION['user']['nombre_usuario'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$idEmpresaSeleccionada = (int)($_GET['id_empresa'] ?? 0);
$empresaFijada = false;
$empresasDisponibles = [];

if ($rol === 'ADMINISTRADOR') {
    if ($idEmpresaSeleccionada > 0) {
        $stmtEmpresas = db()->prepare(
            'SELECT id_empresa, razon_social
             FROM empresa
             WHERE id_empresa = ?
             LIMIT 1'
        );
    } else {
        $stmtEmpresas = db()->prepare(
            'SELECT id_empresa, razon_social
             FROM empresa
             ORDER BY razon_social ASC'
        );
    }

    if ($stmtEmpresas) {
        if ($idEmpresaSeleccionada > 0) {
            $stmtEmpresas->bind_param('i', $idEmpresaSeleccionada);
        }
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
} elseif ($usuarioId > 0) {
    if ($idEmpresaSeleccionada > 0) {
        $stmtEmpresas = db()->prepare(
            'SELECT e.id_empresa, e.razon_social
             FROM usuario_empresa ue
             INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
             WHERE ue.id_usuario = ? AND e.id_empresa = ?
             LIMIT 1'
        );
    } else {
        $stmtEmpresas = db()->prepare(
            'SELECT e.id_empresa, e.razon_social
             FROM usuario_empresa ue
             INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
             WHERE ue.id_usuario = ?
             ORDER BY e.razon_social ASC'
        );
    }

    if ($stmtEmpresas) {
        if ($idEmpresaSeleccionada > 0) {
            $stmtEmpresas->bind_param('ii', $usuarioId, $idEmpresaSeleccionada);
        } else {
            $stmtEmpresas->bind_param('i', $usuarioId);
        }
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
}

if ($idEmpresaSeleccionada > 0 && !empty($empresasDisponibles)) {
    $empresaFijada = (count($empresasDisponibles) === 1 && (int)$empresasDisponibles[0]['id_empresa'] === $idEmpresaSeleccionada);
}

$vistaEmpresaEspecifica = ($idEmpresaSeleccionada > 0 && $empresaFijada);

$idEmpresaFormulario = 0;
$nombreEmpresaFormulario = '';
if (!empty($empresasDisponibles)) {
    if ($idEmpresaSeleccionada > 0) {
        foreach ($empresasDisponibles as $empresa) {
            if ((int)($empresa['id_empresa'] ?? 0) === $idEmpresaSeleccionada) {
                $idEmpresaFormulario = (int)$empresa['id_empresa'];
                $nombreEmpresaFormulario = trim((string)$empresa['razon_social']);
                break;
            }
        }
    }

    if ($idEmpresaFormulario <= 0) {
        $idEmpresaFormulario = (int)($empresasDisponibles[0]['id_empresa'] ?? 0);
        $nombreEmpresaFormulario = trim((string)($empresasDisponibles[0]['razon_social'] ?? ''));
    }
}

$sinEmpresaFormulario = ($idEmpresaFormulario <= 0);
$registroSubido = (!$sinEmpresaFormulario && empresa_tiene_registro_retributivo($idEmpresaFormulario));
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Area Retributiva</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="<?= $esTecnico ? '../css/tecnico.css' : '../css/admin.css' ?>">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row g-3">

            <aside class="col-12 col-lg-3 col-xl-2">
                <div class="card shadow-sm border-0 sidebar">
                    <div class="card-body">
                        <?php if ($esTecnico): ?>
                            <div class="sidebar-header">
                                <div class="sidebar-avatar">👨‍💼</div>
                                <h5 class="sidebar-title">Panel Técnico</h5>
                            </div>

                            <div class="sidebar-user-info">
                                <div class="info-label">Usuario Actual</div>
                                <div class="info-value"><?= h($sessionUsername) ?></div>
                                <?php if ($sessionEmail !== ''): ?>
                                    <div class="info-email">📧 <?= h($sessionEmail) ?></div>
                                <?php endif; ?>
                            </div>

                            <nav class="sidebar-nav">
                                <a class="nav-button" href="<?= h(app_path('/model/tecnico.php?view=menu')) ?>">
                                    <span class="nav-icon">📊</span>
                                    <span>Mi Panel</span>
                                </a>

                                <button class="nav-button nav-collapse"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                                    aria-expanded="false">
                                    <span class="nav-icon">🏢</span>
                                    <span>Empresas</span>
                                    <span class="collapse-icon">▾</span>
                                </button>

                                <div id="menuEmpresas" class="collapse nav-submenu">
                                    <a class="nav-subbutton" href="<?= h(app_path('/model/empresa.php?view=ver_empresas&from=tecnico')) ?>">
                                        <span>📋</span>
                                        <span>Mis Empresas</span>
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
                                    <a class="nav-subbutton" href="<?= h(app_path('/model/tecnico.php?view=perfil')) ?>">
                                        <span>👤</span>
                                        <span>Mi Cuenta</span>
                                    </a>
                                    <a class="nav-subbutton" href="<?= h(app_path('/model/tecnico.php?view=reuniones')) ?>">
                                        <span>📅</span>
                                        <span>Mis Reuniones</span>
                                    </a>
                                </div>

                                <a class="nav-button nav-logout" href="<?= h(app_path('/php/logout.php')) ?>">
                                    <span class="nav-icon">🚪</span>
                                    <span>Cerrar Sesión</span>
                                </a>
                            </nav>
                        <?php else: ?>
                            <h5 class="mb-1">Panel</h5>

                            <div class="text-muted small mb-3">
                                Sesion: <strong><?= h($sessionUsername) ?></strong>
                                <?php if ($sessionEmail !== ''): ?>
                                    <div>Email: <strong><?= h($sessionEmail) ?></strong></div>
                                <?php endif; ?>
                                <div>Rol: <strong><?= h($rol) ?></strong></div>
                            </div>

                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-dark text-start" href="<?= h(app_path('/model/admin.php?view=menu')) ?>">
                                    Panel administrador
                                </a>

                                <?php if (!$vistaEmpresaEspecifica): ?>
                                    <button class="btn btn-outline-dark text-start d-flex justify-content-between align-items-center"
                                        type="button" data-bs-toggle="collapse" data-bs-target="#menuUsuarios"
                                        aria-expanded="false">
                                        <span>Usuarios</span><span>▾</span>
                                    </button>

                                    <div id="menuUsuarios" class="collapse">
                                        <div class="d-grid gap-2 ps-3 pt-2">
                                            <a class="btn btn-outline-secondary text-start" href="<?= h(app_path('/model/admin.php?view=ver_usuarios')) ?>">Ver</a>
                                            <a class="btn btn-outline-secondary text-start" href="<?= h(app_path('/model/admin.php?view=add')) ?>">Anadir</a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <button class="btn btn-outline-dark text-start d-flex justify-content-between align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                                    aria-expanded="false">
                                    <span>Empresas</span><span>▾</span>
                                </button>

                                <div id="menuEmpresas" class="collapse">
                                    <div class="d-grid gap-2 ps-3 pt-2">
                                        <a class="btn btn-outline-secondary text-start" href="<?= h(app_path('/model/empresa.php?view=ver_empresas')) ?>">Directorio de empresas</a>
                                    </div>
                                </div>

                                <a class="btn btn-outline-dark text-start" href="<?= h(app_path('/model/admin.php?view=perfil')) ?>">
                                    Area Privada
                                </a>

                                <a class="btn btn-outline-secondary text-start" href="<?= h(app_path('/php/logout.php')) ?>">
                                    Cerrar sesion
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

            <main class="col-12 col-lg-9 col-xl-10">
                <div class="card p-4 shadow-sm border-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Subir Documento del Registro Retributivo</h5>
                        <?php if ($vistaEmpresaEspecifica): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_path('/model/empresa.php?view=ver_empresa&id_empresa=' . (int)$idEmpresaSeleccionada)) ?>">
                                Volver a la empresa
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($msg !== ''): ?>
                        <div class="alert alert-info py-2"><?= h($msg) ?></div>
                    <?php endif; ?>

                    <form action="../php/procesar_registro_retributivo.php" method="POST" enctype="multipart/form-data">
                      <?= csrf_input() ?>
                        <label for="nombre_empresa_staff">Empresa / Referencia:</label>
                        <?php if (!$sinEmpresaFormulario): ?>
                            <select id="nombre_empresa_staff" name="id_empresa" class="form-control mb-3" required>
                                <option value="">-- seleccionar empresa --</option>
                                <?php foreach ($empresasDisponibles as $empresa): ?>
                                    <option value="<?= (int)$empresa['id_empresa'] ?>" <?= ((int)$empresa['id_empresa'] === $idEmpresaFormulario) ? 'selected' : '' ?>>
                                        <?= h($empresa['razon_social']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input id="nombre_empresa_staff" type="text" class="form-control mb-3" value="No hay empresas disponibles" readonly>
                            <div class="alert alert-warning py-2">No tienes empresas asignadas para subir archivos.</div>
                        <?php endif; ?>

                        <label for="Asunto">Observaciones:</label>
                        <input type="text" id="Asunto" name="asunto" class="form-control mb-3">

                        <label for="Tipo">Tipo de archivo:</label>
                        <select id="Tipo" name="tipo" class="form-control mb-3" required>
                            <option value="REGISTRO_RETRIBUTIVO">Registro Retributivo</option>
                            <?php if ($esTecnico): ?>
                                <option value="WORD_FINAL">WORD_FINAL</option>
                            <?php endif; ?>
                        </select>

                        <input type="file"
                            name="excel[]"
                            class="form-control mb-3"
                            accept=".docx,.doc,.pdf,.xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                            multiple
                            required>

                        <button class="btn btn-primary" type="submit" <?= $sinEmpresaFormulario ? 'disabled' : '' ?>>Subir documentacion</button>

                        <div class="mt-3">
                            <label class="form-label">Descargar plantilla (si no tienes un formato):</label>
                            <a class="btn btn-outline-secondary" href="<?= h(app_path('/php/download_archivo.php?id=1')) ?>">
                                Descargar formato
                            </a>
                        </div>
                        <div class="mt-4">
                            <label class="form-label d-block">Datos Cuantitativos / Cuestionarios Cualitativos:</label>
                            <?php if (!$registroSubido): ?>
                                <div class="alert alert-warning py-2">
                                    Debes subir primero el Registro Retributivo para desbloquear los Datos Cuantitativos / Cuestionarios Cualitativos.
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-open-complemento" data-tab="bajas">Ver Datos Cuantitativos / Cuestionarios Cualitativos</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="modalComplementoFormularios" tabindex="-1" aria-labelledby="modalComplementoFormulariosLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalComplementoFormulariosLabel">Datos Cuantitativos / Cuestionarios Cualitativos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0" style="min-height: 70vh;">
                    <iframe id="complementoFormulariosFrame" title="Formulario complemento" style="width:100%; height:70vh; border:0;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const modalElement = document.getElementById('modalComplementoFormularios');
            const iframe = document.getElementById('complementoFormulariosFrame');
            const botones = document.querySelectorAll('.btn-open-complemento');

            if (!modalElement || !iframe || botones.length === 0 || typeof bootstrap === 'undefined') {
                return;
            }

            const modal = new bootstrap.Modal(modalElement);
            const idEmpresaFija = <?= $idEmpresaFormulario > 0 ? (int)$idEmpresaFormulario : 0 ?>;

            botones.forEach((btn) => {
                btn.addEventListener('click', function() {
                    const tab = (btn.getAttribute('data-tab') || 'bajas').trim();
                    let src = 'complemento_formularios.php?embed=1&tab=' + encodeURIComponent(tab);
                    if (idEmpresaFija > 0) {
                        src += '&id_empresa=' + encodeURIComponent(String(idEmpresaFija));
                    }
                    iframe.src = src;
                    modal.show();
                });
            });

            modalElement.addEventListener('hidden.bs.modal', function() {
                iframe.src = '';
            });
        })();
    </script>
</body>

</html>