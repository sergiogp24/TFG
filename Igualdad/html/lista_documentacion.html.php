<?php

declare(strict_types=1);

/**
 * Plantilla: Documentación Final
 * -----------------------------
 * Muestra los ficheros considerados como documentación final por empresa.
 * - Acceso: ADMINISTRADOR, TECNICO, CLIENTE.
 * - Presenta listado con descarga y permite filtrar por empresa mediante GET.
 */

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));

// Flags de rol usados en la vista para mostrar/ocultar acciones
$esAdmin = ($rol === 'ADMINISTRADOR');
$esCliente = ($rol === 'CLIENTE');
$esTecnico = ($rol === 'TECNICO');

// Selección de hoja de estilos por tipo de usuario para mantener consistencia
$panelCss = $esTecnico ? '../css/tecnico.css' : ($esCliente ? '../css/empresa.css' : '../css/admin.css');
$sessionUsername = (string)($_SESSION['user']['nombre_usuario'] ?? 'usuario');
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
  <title>Documentación Final</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="<?= $panelCss ?>">
</head>

<body class="bg-light">
  <div class="container-fluid py-4">
    <div class="row g-3">
      <!-- Barra lateral: navegación contextual según rol -->
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
              <?php endif; ?>
              
              <a class="nav-button" href="../php/ver_archivos.php<?= $idEmpresaFiltro > 0 ? '?id_empresa=' . $idEmpresaFiltro : '' ?>">
                <span class="nav-icon">📦</span>
                <span>Archivos subidos</span>
              </a>

              <a class="nav-button active" href="../php/ver_documentacion.php<?= $idEmpresaFiltro > 0 ? '?id_empresa=' . $idEmpresaFiltro : '' ?>">
                <span class="nav-icon">📄</span>
                <span>Documentación Final</span>
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
              <h4 class="mb-0">Documentación Final</h4>
              <div class="d-flex gap-2">
                <?php if ($idEmpresaFiltro > 0): ?>
                  <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= $idEmpresaFiltro ?>">Volver a la empresa</a>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($idEmpresaFiltro > 0 && !empty($empresaFiltroNombre)): ?>
              <div class="alert alert-light border mb-3">
                Mostrando documentación de <strong><?= h((string)$empresaFiltroNombre) ?></strong>.
              </div>
            <?php endif; ?>

            <?php if (empty($archivosListado)): ?>
              <!-- No se han encontrado documentos para los criterios actuales -->
              <div class="alert alert-warning mb-0">No hay documentación final disponible aún.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered align-middle text-center usuarios-table table-fit">
                  <thead class="table-secondary">
                    <tr>
                      <th>Empresa</th>
                      <th>Asunto</th>
                      <th>Archivo</th>
                      <th>Tipo</th>
                      <th>Tamaño</th>
                      <th>Fecha</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (($archivosListado ?? []) as $archivo): ?>
                      <tr>
                        <td class="text-start"><?= h((string)($archivo['empresa'] ?? '')) ?></td>
                        <td class="text-start"><?= h((string)($archivo['asunto'] ?? '')) ?></td>
                        <td class="text-start"><?= h((string)($archivo['nombre'] ?? '')) ?></td>
                        <td><?= h((string)($archivo['tipo'] ?? '')) ?></td>
                        <td><?= h((string)($archivo['tamano'] ?? '')) ?></td>
                        <td><?= isset($archivo['subido_en']) ? h(date('d/m/Y H:i', strtotime($archivo['subido_en']))) : '' ?></td>
                        <td>
                          <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <a
                              class="btn btn-primary btn-sm<?= (($archivo['descarga'] ?? '#') === '#') ? ' disabled' : '' ?>"
                              href="<?= h((string)($archivo['descarga'] ?? '#')) ?>"
                              <?= (($archivo['descarga'] ?? '#') === '#') ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Descargar</a>
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
  <!-- Carga de dependencias JS: Bootstrap bundle para componentes (modals, tooltips, etc.) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
