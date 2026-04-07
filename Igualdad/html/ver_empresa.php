<!doctype html>
<html lang="es">
<head>
  <!-- Codificación y responsive -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Título de la página -->
  <title>Asignar empresas</title>

  <!-- Bootstrap  -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Estilos propios -->
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="stylesheet" href="../css/asignar_empresas.css">
</head>

<body class="bg-light">
<div class="container py-4">

  <!-- Cabecera: título, info del usuario y botón volver -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h5 m-0">Asignar empresas</h1>

      <!-- Información del usuario al que le vamos a asignar empresas -->
      <div class="text-muted small">
        Usuario: <strong><?= h($usuario['nombre_usuario']) ?></strong>
        (<?= h($usuario['email']) ?>)
      </div>
    </div>

    <!-- Botón "Volver" a la vista de edición del usuario -->
    <a class="btn btn-outline-secondary btn-sm"
       href="admin.php?view=edit&id_usuario=<?= (int)$usuario['id_usuario'] ?>">
      Volver
    </a>
  </div>

  <!-- Mensajes de éxito y error -->
  <?php if (!empty($ok)): ?>
    <div class="alert alert-success"><?= h($ok) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <!-- Tarjeta con el formulario -->
  <div class="card shadow-sm">
    <div class="card-body">

      <!--Formulario POST:Envía el id del usuario + el array empresas[] con los ids marcados.-->
      <form method="post">
        <!-- ID del usuario al que se le asignan empresas -->
        <input type="hidden" name="id_usuario" value="<?= (int)$usuario['id_usuario'] ?>">

        <!-- Fila de utilidades: buscador + botones marcar/desmarcar -->
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-6">
            <!-- Input de búsqueda  -->
            <input id="filter" class="form-control" type="search" placeholder="Buscar empresa…">
          </div>

          <div class="col-12 col-md-6 d-flex gap-2 justify-content-md-end">
            <!-- Botones que marcan/desmarcan todas las empresas-->
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(true)">Marcar todas</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(false)">Desmarcar</button>
          </div>
        </div>

        <!--Listado de empresas:
          - Cada empresa es un label clickable (mejora la UX).
          - data-name guarda el nombre en minúsculas para filtrar con JS.
          - checkbox name="empresas[]" => se envía como array al POST.
          - Si el id está en $checked, la empresa aparece marcada (checked).-->
        <div class="empresa-list border rounded" id="list">
          <?php foreach ($empresas as $e): ?>
            <?php $eid = (int)$e['id_empresa']; ?>

            <label class="empresa-item d-flex align-items-center gap-2 px-3 py-2 border-bottom"
                   data-name="<?= h(mb_strtolower((string)$e['razon_social'])) ?>">

              <input class="form-check-input m-0 empresa-checkbox"
                     type="checkbox"
                     name="empresas[]"
                     value="<?= $eid ?>"
                     <?= in_array($eid, $checked, true) ? 'checked' : '' ?>>

              <span><?= h($e['razon_social']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <!-- Botones finales: guardar o cancelar -->
        <div class="d-flex justify-content-center gap-4 mt-4">
          <!-- Envía el formulario -->
          <button class="btn btn-success px-5" type="submit">Guardar</button>

          <!-- Cancelar: vuelve a editar usuario sin guardar cambios -->
          <a class="btn btn-danger px-5" href="admin.php?view=edit&id_usuario=<?= (int)$usuario['id_usuario'] ?>">
            Cancelar
          </a>
        </div>

      </form>

    </div>
  </div>

</div>

<script>
  /**
   * Marca o desmarca todos los checkboxes de empresas.
   * @param {boolean} on - true para marcar todas, false para desmarcar.
   */
  function toggleAll(on){
    document.querySelectorAll('.empresa-checkbox').forEach(cb => cb.checked = on);
  }

  // Referencias al input de filtro y al contenedor de la lista
  const filter = document.getElementById('filter');
  const list = document.getElementById('list');

  // Filtrado en tiempo real (client-side) por nombre de empresa
  if (filter && list) {
    filter.addEventListener('input', () => {
      const q = filter.value.trim().toLowerCase();
      // Para cada fila (label) que tenga data-name:
      // si el nombre incluye el texto buscado => se muestra; si no => se oculta.
      list.querySelectorAll('[data-name]').forEach(row => {
        row.style.display = (row.getAttribute('data-name') || '').includes(q) ? '' : 'none';
      });
    });
  }
</script>
</body>
</html>