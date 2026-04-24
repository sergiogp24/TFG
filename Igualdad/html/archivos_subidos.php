<?php

declare(strict_types=1);

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdmin = ($rol === 'ADMINISTRADOR');
$esCliente = ($rol === 'CLIENTE');
$esTecnico = ($rol === 'TECNICO');
$panelCss = $esTecnico ? '../css/tecnico.css' : ($esCliente ? '../css/empresa.css' : '../css/admin.css');
$sessionUsername = (string)($_SESSION['user']['nombre_usuario'] ?? $_SESSION['user']['username'] ?? 'usuario');
$sessionEmail = (string)($_SESSION['user']['email'] ?? '');
$idEmpresaFiltro = (int)($_GET['id_empresa'] ?? 0);

if (!$esAdmin && !$esCliente && !$esTecnico) {
  http_response_code(403);
  exit('Acceso denegado');
}
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Archivos subidos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="<?= $panelCss ?>">
</head>

<body class="bg-light">
  <div class="container-fluid py-4">
    <div class="row g-3">
      <aside class="col-12 col-lg-3 col-xl-2">
        <div class="card shadow-sm border-0 sidebar">
          <div class="card-body">
            <div class="sidebar-header">
              <div class="sidebar-avatar"><?= $esAdmin ? '🧑‍💼' : ($esTecnico ? '👨‍💼' : '👤') ?></div>
              <h5 class="sidebar-title"><?= $esAdmin ? 'Panel Admin' : ($esTecnico ? 'Panel Técnico' : 'Panel Cliente') ?></h5>
            </div>

            <div class="sidebar-user-info">
              <div class="info-label">Usuario Actual</div>
              <div class="info-value"><?= h($sessionUsername) ?></div>
              <?php if ($sessionEmail !== ''): ?><div class="info-email">📧 <?= h($sessionEmail) ?></div><?php endif; ?>
            </div>

            <nav class="sidebar-nav">
              <?php if ($esAdmin): ?>
                <a class="nav-button" href="../model/admin.php?view=menu">
                  <span class="nav-icon">📊</span>
                  <span>Mi Panel</span>
                </a>
                <a class="nav-button" href="../model/admin.php?view=ver_usuarios">
                  <span class="nav-icon">👥</span>
                  <span>Usuarios</span>
                </a>
                <a class="nav-button" href="../model/empresa.php?view=ver_empresas&from=admin">
                  <span class="nav-icon">🏢</span>
                  <span>Directorio de empresas</span>
                </a>
                <button class="nav-button nav-collapse"
                  type="button" data-bs-toggle="collapse" data-bs-target="#menuAreaPrivadaArchivosAdmin"
                  aria-expanded="false">
                  <span class="nav-icon">🔐</span>
                  <span>Área Privada</span>
                  <span class="collapse-icon">▾</span>
                </button>

                <div id="menuAreaPrivadaArchivosAdmin" class="collapse nav-submenu">
                  <a class="nav-subbutton" href="../model/admin.php?view=perfil">
                    <span>👤</span>
                    <span>Mi cuenta</span>
                  </a>
                  <a class="nav-subbutton" href="../model/admin.php?view=reuniones">
                    <span>📅</span>
                    <span>Mis reuniones</span>
                  </a>
                </div>
              <?php else: ?>
                <a class="nav-button" href="<?= $esTecnico ? '../model/tecnico.php?view=menu' : '../html/index_cliente.php?view=mi_espacio' ?>">
                  <span class="nav-icon">🏠</span>
                  <span>Inicio</span>
                </a>
                <?php if ($esTecnico): ?>
                  <a class="nav-button" href="../model/empresa.php?view=ver_empresas&from=tecnico">
                    <span class="nav-icon">🏢</span>
                    <span>Mis Empresas</span>
                  </a>
                <?php endif; ?>
                <button class="nav-button nav-collapse"
                  type="button" data-bs-toggle="collapse" data-bs-target="#menuAreaPrivadaArchivosUser"
                  aria-expanded="false">
                  <span class="nav-icon">🔐</span>
                  <span>Área Privada</span>
                  <span class="collapse-icon">▾</span>
                </button>

                <div id="menuAreaPrivadaArchivosUser" class="collapse nav-submenu">
                  <a class="nav-subbutton" href="<?= $esTecnico ? '../model/tecnico.php?view=perfil' : '../html/index_cliente.php?view=perfil' ?>">
                    <span>👤</span>
                    <span>Mi cuenta</span>
                  </a>
                  <a class="nav-subbutton" href="<?= $esTecnico ? '../model/tecnico.php?view=reuniones' : '../html/index_cliente.php?view=reuniones' ?>">
                    <span>📅</span>
                    <span>Mis reuniones</span>
                  </a>
                </div>
              <?php endif; ?>
              <a class="nav-button active" href="../php/archivos_subidos.php<?= $idEmpresaFiltro > 0 ? '?id_empresa=' . $idEmpresaFiltro : '' ?>">
                <span class="nav-icon">📦</span>
                <span>Archivos subidos</span>
              </a>
              <a class="nav-button nav-logout" href="../php/logout.php">
                <span class="nav-icon">🚪</span>
                <span>Cerrar sesión</span>
              </a>
            </nav>
          </div>
        </div>
      </aside>

      <main class="col-12 col-lg-9 col-xl-10">
        <div class="card panel mx-auto shadow-sm border-0 panel-wide">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="mb-0">Archivos subidos</h4>
              <?php if ($idEmpresaFiltro > 0): ?>
                <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= $idEmpresaFiltro ?>">Volver a la empresa</a>
              <?php endif; ?>
            </div>

            <?php if (!empty($_GET['msg'])): ?>
              <div class="alert alert-success py-2"><?= h((string)($_GET['msg'] ?? '')) ?></div>
            <?php endif; ?>
            <?php if (!empty($_GET['error'])): ?>
              <div class="alert alert-danger py-2"><?= h((string)($_GET['error'] ?? '')) ?></div>
            <?php endif; ?>

            <div class="alert alert-light border mb-3">
              Aquí se agrupan los archivos subidos por el cliente y los documentos generados en <strong>empresa_porcentajes</strong> y <strong>empresa_word</strong>.
            </div>

            <?php if ($idEmpresaFiltro > 0 && !empty($empresaFiltroNombre)): ?>
              <div class="alert alert-light border mb-3">
                Mostrando archivos de <strong><?= h((string)$empresaFiltroNombre) ?></strong>.
              </div>
            <?php endif; ?>

            <?php if (empty($archivosListado)): ?>
              <div class="alert alert-warning mb-0">No hay archivos para mostrar.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                  <thead class="table-secondary">
                    <tr>
                      <th>Origen</th>
                      <th>Empresa</th>
                      <th>Asunto</th>
                      <th>Archivo</th>
                      <th>Tipo</th>
                      <th>Tamaño</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($archivosListado as $archivo): ?>
                      <tr>
                        <td class="text-start"><?= h((string)($archivo['categoria'] ?? '')) ?></td>
                        <td class="text-start"><?= h((string)($archivo['empresa'] ?? '')) ?></td>
                        <td class="text-start"><?= h((string)($archivo['asunto'] ?? '')) ?></td>
                        <td class="text-start"><?= h((string)($archivo['nombre'] ?? '')) ?></td>
                        <td><?= h((string)($archivo['tipo'] ?? '')) ?></td>
                        <td><?= h((string)($archivo['tamano'] ?? '')) ?></td>
                        <td>
                          <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <a class="btn btn-primary btn-sm" href="<?= h((string)($archivo['descarga'] ?? '#')) ?>">Descargar</a>
                            <?php if ($esAdmin || $esTecnico): ?>
                              <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este archivo?');">
                                <input type="hidden" name="accion" value="eliminar_archivo">
                                <?= csrf_input() ?>
                                <input type="hidden" name="source" value="<?= h((string)($archivo['source'] ?? '')) ?>">
                                <?php if ($idEmpresaFiltro > 0): ?>
                                  <input type="hidden" name="id_empresa" value="<?= $idEmpresaFiltro ?>">
                                <?php endif; ?>
                                <?php if ($archivo['source'] === 'archivos'): ?>
                                  <input type="hidden" name="file_id" value="<?= (int)($archivo['id_archivo'] ?? 0) ?>">
                                <?php else: ?>
                                  <input type="hidden" name="file_name" value="<?= h((string)($archivo['file_name'] ?? '')) ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
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