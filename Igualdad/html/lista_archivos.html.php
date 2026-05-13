<?php

declare(strict_types=1);

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdmin = ($rol === 'ADMINISTRADOR');
$esCliente = ($rol === 'CLIENTE');
$esTecnico = ($rol === 'TECNICO');
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
  <title>Documentación</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="<?= $panelCss ?>">
</head>

<body class="bg-light">
  <div class="container-fluid py-4">
    <div class="row g-3">
      <!-- SIDEBAR -->
      <?php include __DIR__ . '/../php/fragments/sidebar.php'; ?>

      <main class="col-12 col-lg-9 col-xl-10">
        <div class="card panel mx-auto shadow-sm border-0 panel-wide">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="mb-0">Documentación</h4>
              <div class="d-flex gap-2">
                <?php if ($idEmpresaFiltro > 0): ?>
                  <?php if ($esCliente): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="../html/index_cliente.php?view=mi_espacio&id_empresa=<?= $idEmpresaFiltro ?>">Volver a la empresa</a>
                  <?php else: ?>
                    <a class="btn btn-outline-secondary btn-sm" href="../model/empresa.php?view=ver_empresa&id_empresa=<?= $idEmpresaFiltro ?>">Volver a la empresa</a>
                  <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalSubirArchivo">
                  <span>➕</span> Subir Documento
                </button>
              </div>
            </div>

            <!-- Modal Subir Archivo -->
            <div class="modal fade" id="modalSubirArchivo" tabindex="-1" aria-labelledby="modalSubirArchivoLabel" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="POST" action="<?= h(app_path('/php/ver_archivos.php')) ?>" enctype="multipart/form-data">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="subir_archivo">
                    <input type="hidden" name="id_empresa_filtro" value="<?= $idEmpresaFiltro ?>">
                    
                    <div class="modal-header">
                      <h5 class="modal-title" id="modalSubirArchivoLabel">Subir Nuevo Documento</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label for="id_empresa_subida" class="form-label">Empresa</label>
                        <?php if ($idEmpresaFiltro > 0): ?>
                          <input type="text" class="form-control" value="<?= h($empresaFiltroNombre) ?>" readonly>
                          <input type="hidden" name="id_empresa" value="<?= $idEmpresaFiltro ?>">
                        <?php else: ?>
                          <select name="id_empresa" id="id_empresa_subida" class="form-select" required>
                            <option value="">Selecciona una empresa...</option>
                            <?php foreach (($empresasUsuario ?? []) as $emp): ?>
                              <option value="<?= (int)$emp['id_empresa'] ?>"><?= h($emp['razon_social']) ?></option>
                            <?php endforeach; ?>
                          </select>
                        <?php endif; ?>
                      </div>
                      <div class="mb-3">
                        <label for="asunto_subida" class="form-label">Asunto / Descripción</label>
                        <input type="text" name="asunto" id="asunto_subida" class="form-control" placeholder="Ej: Escrituras, CIF, etc." required>
                      </div>
                      <div class="mb-3">
                        <label for="archivo_subida" class="form-label">Archivo</label>
                        <input type="file" name="archivo" id="archivo_subida" class="form-control" required>
                        <div class="form-text">Formatos permitidos: PDF, DOC, DOCX, XLS, XLSX, CSV, JPG, PNG.</div>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">Subir Documento</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <?php if (!empty($_GET['msg'])): ?>
              <div class="alert alert-success py-2"><?= h((string)($_GET['msg'] ?? '')) ?></div>
            <?php endif; ?>
            <?php if (!empty($_GET['error'])): ?>
              <div class="alert alert-danger py-2"><?= h((string)($_GET['error'] ?? '')) ?></div>
            <?php endif; ?>

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
                      <th>Fecha subida</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (($archivosListado ?? []) as $archivo): ?>
                      <tr>
                        <td class="text-start"><?= h((string)($archivo['categoria'] ?? '')) ?></td>
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
  <script>
    (function() {
      const formRegenerarWord = document.getElementById('formRegenerarWordArchivos');
      const btnRegenerarWord = document.getElementById('btnRegenerarWordArchivos');
      const msgRegenerarWord = document.getElementById('msgRegenerarWordArchivos');

      if (!formRegenerarWord || !btnRegenerarWord || !msgRegenerarWord) {
        return;
      }

      formRegenerarWord.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(formRegenerarWord);
        const url = formRegenerarWord.getAttribute('action');

        btnRegenerarWord.disabled = true;
        btnRegenerarWord.textContent = '⏳ Regenerando...';
        msgRegenerarWord.innerHTML = '';

        try {
          const response = await fetch(url, {
            method: 'POST',
            body: formData
          });

          const raw = await response.text();
          let data = null;
          try {
            data = JSON.parse(raw);
          } catch (_) {
            data = null;
          }

          if (!data || typeof data !== 'object') {
            const msg = response.status === 401 || response.status === 403
              ? 'Sesion expirada o acceso denegado. Recarga la pagina e inicia sesion de nuevo.'
              : 'El servidor devolvio una respuesta no valida. Revisa el log del procesador.';
            msgRegenerarWord.innerHTML = '<div class="alert alert-danger py-2 mb-0">❌ ' + msg + '</div>';
            btnRegenerarWord.textContent = '🔄 Actualizar Word';
            btnRegenerarWord.disabled = false;
            return;
          }

          if (response.ok && data.exito) {
            msgRegenerarWord.innerHTML = '<div class="alert alert-success py-2 mb-0">✅ ' + data.mensaje + '</div>';
            btnRegenerarWord.textContent = '🔄 Actualizar Word';
            
            // Reload after 2 seconds
            setTimeout(() => {
              window.location.reload();
            }, 2000);
          } else {
            msgRegenerarWord.innerHTML = '<div class="alert alert-danger py-2 mb-0">❌ ' + (data.mensaje || 'Error al regenerar el Word') + '</div>';
            btnRegenerarWord.textContent = '🔄 Actualizar Word';
            btnRegenerarWord.disabled = false;
          }
        } catch (error) {
          msgRegenerarWord.innerHTML = '<div class="alert alert-danger py-2 mb-0">❌ Error: ' + error.message + '</div>';
          btnRegenerarWord.textContent = '🔄 Actualizar Word';
          btnRegenerarWord.disabled = false;
        }
      });
    })();
  </script>
</body>

</html>