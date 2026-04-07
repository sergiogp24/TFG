<?php

declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();

/**
 * Rol del usuario logueado (según tu login: ADMINISTRADOR | TECNICO | CLIENTE)
 * Todos los usuarios pueden acceder; solo cambia la vista según el rol
 */
$rol = strtoupper((string)($_SESSION['user']['rol'] ?? 'CLIENTE'));

/**
 * Staff = ADMINISTRADOR o TECNICO -> se muestra layout con sidebar
 * Otros -> se muestra vista "simple"
 */
$esStaff = in_array($rol, ['ADMINISTRADOR', 'TECNICO'], true);

/** Escape HTML */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$msg = (string)($_GET['msg'] ?? '');

/**
 * Datos de sesión para pintar usuario en el sidebar.
 * (En tu login guardabas username en $_SESSION['user']['username']).
 */
$sessionUsername = (string)($_SESSION['user']['username'] ?? $_SESSION['user']['nombre_usuario'] ?? 'usuario');
$sessionEmail    = (string)($_SESSION['user']['email'] ?? '');


/**
 * IMPORTANTE:
 * Esta página NO es admin.php ni empresa.php, por eso tu variable $view no se inicializa aquí.
 * Para que los "active" funcionen y los colapsables se abran, definimos una vista fija.
 */
$view = 'area_retributiva'; // identificador interno solo para marcar el menú activo
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Área Retributiva</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Estilos del layout lateral -->
    <link rel="stylesheet" href="../css/empresa_layout.css">
    <link rel="stylesheet" href="../css/empresa.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <?php if ($esStaff): ?>
        <!-- VISTA STAFF ADMIN/TECNICO -->
        <div class="container-fluid py-4">
            <div class="row g-3">

                <!-- SIDEBAR -->
                <aside class="col-12 col-lg-3 col-xl-2">
                    <div class="card shadow-sm border-0 sidebar">
                        <div class="card-body">
                            <h5 class="mb-1">Panel</h5>

                            <div class="text-muted small mb-3">
                                Sesión: <strong><?= h($sessionUsername) ?></strong>
                                <?php if ($sessionEmail !== ''): ?>
                                    <div>Email: <strong><?= h($sessionEmail) ?></strong></div>
                                <?php endif; ?>
                                <div>Rol: <strong><?= h($rol) ?></strong></div>
                            </div>

                            <div class="d-grid gap-2">
                                <!-- Inicio (te mando a empresas como en tu menú actual) -->
                                <a class="btn btn-outline-dark text-start"
                                    href="/igualdad/model/empresa.php?view=ver_empresas">
                                    Inicio
                                </a>

                                <!-- USUARIOS (solo ADMIN debería verlo; si quieres que TECNICO también lo vea, quita el if) -->
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

                                <!-- EMPRESAS -->
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

                                <!-- ÁREA RETRIBUTIVA (esta página) -->
                                <a class="btn btn-outline-dark text-start active" href="index_cliente.php">
                                    Subir registro retributivo
                                </a>

                                <a class="btn btn-outline-dark text-start" href="/Igualdad/html/index_documentos_tipo.php">
                                    Subir documentos por tipo
                                </a>

                                <!-- Área privada / perfil (tu ruta actual) -->
                                <a class="btn btn-outline-dark text-start" href="/Igualdad/model/admin.php?view=perfil">
                                    Área Privada
                                </a>

                                <!-- Logout -->
                                <a class="btn btn-outline-secondary text-start" href="/Igualdad/php/logout.php">
                                    Cerrar sesión
                                </a>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- CONTENIDO PRINCIPAL -->
                <main class="col-12 col-lg-9 col-xl-10">
                    <div class="card p-4 shadow-sm border-0">
                        <h5>Subir Documento del Registro Retributivo</h5>

                        <?php if ($msg !== ''): ?>
                            <div class="alert alert-info py-2"><?= h($msg) ?></div>
                        <?php endif; ?>

                        <form action="../php/procesar_registro_retributivo.php"
                            method="POST"
                            enctype="multipart/form-data">
                            <label for="Asunto">Asunto:</label>
                            <input type="text" id="Asunto" name="asunto" class="form-control mb-3">

                            <label for="Tipo">Tipo de archivo:</label>
                            <select id="Tipo" name="tipo" class="form-control mb-3" required>
                                <option value="">-- Para subir el registro retributivo selecciona "Registro Retributivo" --</option>
                                <option value="REGISTRO_RETRIBUTIVO">Registro Retributivo</option>
                                <option value="TOMA DE DATOS">Toma de Datos</option>
                                <option value="CUADRO PORCENTAJES">Cuadro Porcentajes</option>
                            </select>

                            <input type="file"
                                name="excel[]"
                                class="form-control mb-3"
                                accept=".docx,.doc,.pdf,.xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                                multiple
                                required>

                            <button class="btn btn-primary" type="submit">Subir documentacion</button>

                            <div class="mt-3">
                                <label class="form-label">Descargar plantilla (si no tienes un formato):</label>
                                <a class="btn btn-outline-secondary" href="/Igualdad/php/download_archivo.php?id=1">
                                    Descargar formato
                                </a>
                            </div>
                        </form>
                    </div>
                </main>

            </div>
        </div>

    <?php else: ?>
        <!--  VISTA CLIENTE  -->
        <div class="container mt-5">
            <div class="card p-4">
                <h5>Subir Excel y convertir a Word</h5>

                <?php if ($msg !== ''): ?>
                    <div class="alert alert-info py-2"><?= h($msg) ?></div>
                <?php endif; ?>

                <form action="../php/procesar_registro_retributivo.php"
                    method="POST"
                    enctype="multipart/form-data">
                    <label for="Asunto">Asunto:</label>
                    <input type="text" id="Asunto" name="asunto" class="form-control mb-3" required>

                    <label for="Tipo">Tipo de archivo:</label>
                    <select id="Tipo" name="tipo" class="form-control mb-3" required>
                        <option value="">-- Selecciona un tipo --</option>
                        <option value="REGISTRO_RETRIBUTIVO">Registro Retributivo</option>
                        <option value="TOMA DE DATOS">Toma de Datos</option>
                        <option value="CUADRO PORCENTAJES">Cuadro Porcentajes</option>
                    </select>

                    <input type="file"
                        name="excel[]"
                        class="form-control mb-3"
                        accept=".docx,.doc,.pdf,.xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                        multiple
                        required>

                    <button class="btn btn-primary" type="submit">Subir documento</button>

                    <div class="mt-3">
                        <label class="form-label">Descargar plantilla (si necesitas el formato):</label>
                        <a class="btn btn-outline-secondary" href="/Igualdad/php/download_archivo.php?id=1">
                            Descargar formato
                        </a>
                    </div>
                </form>

                <a class="btn btn-outline-dark mt-3" href="/Igualdad/html/index_documentos_tipo.php">
                    Ir a subida de documentos por tipo
                </a>

                <a class="btn btn-link mt-3" href="../php/logout.php">Cerrar sesión</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- NECESARIO para que funcionen los botones collapse del menú lateral -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>