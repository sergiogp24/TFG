<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? 'CLIENTE'));
$esStaff = in_array($rol, ['ADMINISTRADOR', 'TECNICO'], true);

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$msg = (string)($_GET['msg'] ?? '');
$sessionUsername = (string)($_SESSION['user']['username'] ?? $_SESSION['user']['nombre_usuario'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');

$view = 'area_documentos_tipo';
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Area Documental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="../css/empresa_layout.css">
    <link rel="stylesheet" href="../css/empresa.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <?php if ($esStaff): ?>
        <div class="container-fluid py-4">
            <div class="row g-3">

                <aside class="col-12 col-lg-3 col-xl-2">
                    <div class="card shadow-sm border-0 sidebar">
                        <div class="card-body">
                            <h5 class="mb-1">Panel</h5>

                            <div class="text-muted small mb-3">
                                Sesion: <strong><?= h($sessionUsername) ?></strong>
                                <?php if ($sessionEmail !== ''): ?>
                                    <div>Email: <strong><?= h($sessionEmail) ?></strong></div>
                                <?php endif; ?>
                                <div>Rol: <strong><?= h($rol) ?></strong></div>
                            </div>

                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-dark text-start"
                                    href="/igualdad/model/empresa.php?view=ver_empresas">
                                    Inicio
                                </a>

                                <?php if ($rol === 'ADMINISTRADOR'): ?>
                                    <button class="btn btn-outline-dark text-start d-flex justify-content-between align-items-center"
                                        type="button" data-bs-toggle="collapse" data-bs-target="#menuUsuarios"
                                        aria-expanded="false">
                                        <span>Usuarios</span><span>▾</span>
                                    </button>

                                    <div id="menuUsuarios" class="collapse">
                                        <div class="d-grid gap-2 ps-3 pt-2">
                                            <a class="btn btn-outline-secondary text-start" href="/Igualdad/model/admin.php?view=ver_usuarios">Ver</a>
                                            <a class="btn btn-outline-secondary text-start" href="/Igualdad/model/admin.php?view=add">Añadir</a>
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
                                        <a class="btn btn-outline-secondary text-start" href="/Igualdad/model/empresa.php?view=ver_empresas">Ver</a>
                                        <a class="btn btn-outline-secondary text-start" href="/Igualdad/model/empresa.php?view=add_empresas">Añadir</a>
                                    </div>
                                </div>

                                <a class="btn btn-outline-dark text-start" href="index_cliente.php">
                                    Subir registro retributivo
                                </a>

                                <a class="btn btn-outline-dark text-start active" href="index_documentos_tipo.php">
                                    Subir documentos por tipo
                                </a>

                                <a class="btn btn-outline-dark text-start" href="/Igualdad/model/admin.php?view=perfil">
                                    Área Privada
                                </a>

                                <a class="btn btn-outline-secondary text-start" href="/Igualdad/php/logout.php">
                                    Cerrar sesion
                                </a>
                            </div>
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
        <div class="container mt-5">
            <div class="card p-4">
                <h5>Subir Documento por Tipo</h5>

                <?php if ($msg !== ''): ?>
                    <div class="alert alert-info py-2"><?= h($msg) ?></div>
                <?php endif; ?>

                <form action="../php/procesar_documento_tipo.php"
                    method="POST"
                    enctype="multipart/form-data">
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
                    </select>

                    <input type="file"
                        name="archivo"
                        class="form-control mb-3"
                        accept=".docx,.doc,.pdf,.xlsx,.xls,.csv"
                        required>

                    <button class="btn btn-primary" type="submit">Subir documento</button>
                </form>

                <a class="btn btn-link mt-3" href="../php/logout.php">Cerrar sesion</a>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
