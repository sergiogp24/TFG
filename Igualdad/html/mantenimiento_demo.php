<?php
declare(strict_types=1);

$idEmpresa = (int)($_GET['id_empresa'] ?? 0);
$areasJson = '[]';
$puestos   = [];

if ($idEmpresa > 0) {
    try {
        require_once __DIR__ . '/../config/config.php';
        $db = db();

        // Medidas agrupadas por área con id_cliente_medida
        $stmt = $db->prepare(
            "SELECT ap.id_plan, ap.nombre AS nombre_plan,
                    m.id_medida, m.descripcion AS medida, m.indicador,
                    MIN(cm.id_cliente_medida) AS id_cliente_medida
             FROM areas_contratadas ac
             INNER JOIN area_plan ap ON ac.id_plan = ap.id_plan
             INNER JOIN cliente_medida cm ON ac.id_areas_contratadas = cm.id_areas_contratadas
             INNER JOIN medida m ON cm.id_medida = m.id_medida
             WHERE ac.id_empresa = ?
             GROUP BY ap.id_plan, ap.nombre, m.id_medida, m.descripcion, m.indicador
             ORDER BY ap.id_plan, m.id_medida"
        );
        if ($stmt) {
            $stmt->bind_param('i', $idEmpresa);
            $stmt->execute();
            $result    = $stmt->get_result();
            $areasPorId = [];
            while ($row = $result->fetch_assoc()) {
                $idPlan = (int)$row['id_plan'];
                if (!isset($areasPorId[$idPlan])) {
                    $areasPorId[$idPlan] = [
                        'id'      => 'area_' . $idPlan,
                        'nombre'  => trim((string)($row['nombre_plan'] ?? '')),
                        'medidas' => [],
                    ];
                }
                $titulo    = trim((string)($row['medida']    ?? ''));
                $indicador = trim((string)($row['indicador'] ?? ''));
                if ($titulo !== '') {
                    $areasPorId[$idPlan]['medidas'][] = [
                        'id_cliente_medida' => (int)($row['id_cliente_medida'] ?? 0),
                        'titulo'    => $titulo,
                        'estado'    => 'pendiente',
                        'progreso'  => 0,
                        'fecha'     => date('d/m/Y'),
                        'detalle'   => $indicador !== '' ? $indicador : $titulo,
                        'indicador' => $indicador,
                    ];
                }
            }
            $stmt->close();
            $areas     = array_values(array_filter($areasPorId, fn($a) => count($a['medidas']) > 0));
            $areasJson = json_encode($areas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        }

        // Puestos de empresa para el desplegable
        $stmtP = $db->prepare(
            "SELECT DISTINCT de.puesto_empresa
             FROM datos_empleados de
             INNER JOIN ano_datos ad ON ad.id_ano_datos = de.id_ano_datos
             INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
             WHERE ce.id_empresa = ?
               AND de.puesto_empresa IS NOT NULL
               AND TRIM(de.puesto_empresa) != ''
             ORDER BY de.puesto_empresa ASC"
        );
        if ($stmtP) {
            $stmtP->bind_param('i', $idEmpresa);
            $stmtP->execute();
            $resP = $stmtP->get_result();
            while ($r = $resP->fetch_assoc()) {
                $puestos[] = trim((string)$r['puesto_empresa']);
            }
            $stmtP->close();
        }
    } catch (\Throwable $e) {
        $areasJson = '[]';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mi Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mantenimiento_demo.css">
</head>
<body>
<div class="demo-app">
    <main class="main">

        <header class="topbar">
            <div class="topbar-left">
                <h2>Mi Mantenimiento</h2>
                <p>Consulta, gestiona y realiza el seguimiento de las medidas de tu plan de mantenimiento.</p>
            </div>
            <div class="topbar-actions">
                <button class="btn btn-outline-secondary">Ver resumen ejecutivo</button>
                <button class="btn btn-primary">Descargar Plan</button>
            </div>
        </header>

        <div class="stats-bar">
            <div class="stat-card">
                <span class="stat-value" id="statMedidas">—</span>
                <span class="stat-label">Medidas activas</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-success" id="statCompletadas">—</span>
                <span class="stat-label">Completadas</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-warning" id="statProgreso">—</span>
                <span class="stat-label">En progreso</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-secondary" id="statPendientes">—</span>
                <span class="stat-label">Pendientes</span>
            </div>
            <div class="stat-card">
                <span class="stat-value text-accent" id="statPct">—</span>
                <span class="stat-label">Completado global</span>
            </div>
        </div>

        <section class="measures">
            <div class="areas-wrapper">
                <p class="areas-label">Áreas de actuación</p>
                <div class="areas" id="areasBtns"></div>
            </div>

            <div class="content">
                <div class="left">

                    <!-- Tabla de medidas -->
                    <div class="measure-panel">
                        <div class="panel-header">
                            <div>
                                <h5 id="areaNombre">—</h5>
                                <span class="measure-count" id="medidasCount"></span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table measures-table">
                                <thead>
                                    <tr>
                                        <th>Medida</th>
                                        <th>Estado</th>
                                        <th style="width:160px">Progreso</th>
                                        <th>Actualización</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="medidasTbody"></tbody>
                            </table>
                        </div>
                        <div id="paginacionMedidas" class="paginacion-bar"></div>
                    </div>

                    <!-- Formulario de la medida -->
                    <div class="measure-panel mt-3">
                        <div class="panel-header mb-3">
                            <h6 id="datosMedidaTitle">Datos de la medida</h6>
                        </div>
                        <div id="formGuardarFeedback" style="display:none" class="mb-3"></div>
                        <div class="row g-3">

                            <div class="col-md-4">
                                <label class="form-label form-label-sm">Puesto de trabajo</label>
                                <select class="form-select form-select-sm" id="selectPuesto">
                                    <option value="">-- Seleccionar puesto --</option>
                                    <?php foreach ($puestos as $puesto): ?>
                                        <option value="<?= htmlspecialchars($puesto, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($puesto, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($puestos)): ?>
                                        <option disabled>Sin datos de empleados</option>
                                    <?php endif; ?>
                                    <option value="__nuevo__">➕ Nuevo puesto...</option>
                                </select>
                                <input type="text" id="inputNuevoPuesto" class="form-control form-control-sm mt-2"
                                       placeholder="Escribe el nuevo puesto" style="display:none">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label form-label-sm">Fecha de publicación</label>
                                <input type="date" id="inputFechaPublicacion" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-5">
                                <label class="form-label form-label-sm">Archivo oferta de empleo</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="file" id="inputArchivoOferta"
                                           accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" style="display:none">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirArchivo">
                                        Subir archivo
                                    </button>
                                    <span id="nombreArchivoOferta" class="small text-muted">Ningún archivo seleccionado</span>
                                    <span id="estadoSubidaArchivo"></span>
                                </div>
                            </div>

                            <!-- Candidaturas -->
                            <div class="col-12">
                                <label class="form-label form-label-sm">Candidaturas</label>
                                <div class="candidaturas-row">
                                    <div class="candidatura-item">
                                        <label class="candidatura-genero">
                                            <input type="radio" name="genero_candidatura" value="MUJER">
                                            <span class="candidatura-chip candidatura-chip--mujer">♀ Mujer</span>
                                        </label>
                                        <label class="candidatura-genero">
                                            <input type="radio" name="genero_candidatura" value="HOMBRE">
                                            <span class="candidatura-chip candidatura-chip--hombre">♂ Hombre</span>
                                        </label>
                                        <input id="inputCandidaturaNum"
                                               class="form-control form-control-sm candidatura-num"
                                               type="number" min="0" placeholder="Nº candidaturas">
                                    </div>
                                </div>
                            </div>

                            <!-- Criterio de selección -->
                            <div class="col-12">
                                <label class="form-label form-label-sm">Criterio de selección</label>
                                <div class="criteria-row">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="criterio" value="FORMACION" checked>
                                        <label class="form-check-label">Formación</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="criterio" value="DISPONIBILIDAD">
                                        <label class="form-check-label">Disponibilidad</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="criterio" value="EXPERIENCIA">
                                        <label class="form-check-label">Experiencia</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="criterio" value="OTROS">
                                        <label class="form-check-label">Otros</label>
                                    </div>
                                    <input type="text" id="inputCriterioOtros"
                                           class="form-control form-control-sm"
                                           style="max-width:220px"
                                           placeholder="Especificar otros criterios">
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end pt-2">
                                <button type="button" class="btn btn-primary px-4" id="btnGuardarMedida">
                                    Guardar datos
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action cards -->
                    <div class="action-cards mt-3">
                        <div class="action-card">
                            <div class="action-icon" style="background:#10b981">💾</div>
                            <div class="action-body">
                                <div class="action-title">Registro Retributivo</div>
                                <div class="action-desc">Sube el registro retributivo para el análisis de brechas salariales.</div>
                                <a href="index_cliente.php?view=menu&id_empresa=<?= $idEmpresa ?>" target="_parent" class="btn btn-sm btn-success mt-2">Subir registro retributivo</a>
                            </div>
                        </div>
                        <div class="action-card">
                            <div class="action-icon" style="background:#3b82f6">📊</div>
                            <div class="action-body">
                                <div class="action-title">Datos Cuantitativos</div>
                                <div class="action-desc">Introduce los datos cuantitativos del año para el seguimiento del plan.</div>
                                <button class="btn btn-sm btn-outline-primary mt-2"
                                        onclick="window.parent.postMessage({type:'abrirComplemento',tab:'bajas'},'*')">
                                    Introducir datos
                                </button>
                            </div>
                        </div>
                        <div class="action-card">
                            <div class="action-icon" style="background:#8b5cf6">📁</div>
                            <div class="action-body">
                                <div class="action-title">Ver mi Documentación</div>
                                <div class="action-desc">Consulta y gestiona toda la documentación del plan de igualdad.</div>
                                <a href="../php/ver_archivos.php?id_empresa=<?= $idEmpresa ?>" target="_parent" class="btn btn-sm btn-outline-secondary mt-2">Ver documentación</a>
                            </div>
                        </div>
                    </div>

                    <!-- Data tiles -->
                    <div class="data-tiles mt-3">
                        <div class="tile" data-modal="modalBajas">📉 Bajas</div>
                        <div class="tile" data-modal="modalExcedencias">📈 Excedencias</div>
                        <div class="tile" data-modal="modalReducciones">🕒 Reducciones de jornada</div>
                        <div class="tile">🤝 Contrataciones</div>
                        <div class="tile" data-modal="modalPromociones">🚀 Promociones</div>
                        <div class="tile" data-modal="modalFormacion">🎓 Formación</div>
                    </div>

                </div><!-- /left -->

                <!-- Panel derecho -->
                <div class="right">
                    <aside class="detail-panel">
                        <div class="panel-header mb-3">
                            <h5>Detalle de la medida</h5>
                        </div>
                        <div id="detailContent">
                            <p class="text-muted small">Pulsa <strong>Ver</strong> en una medida para ver su detalle aquí.</p>
                        </div>
                    </aside>
                </div>

            </div>
        </section>
    </main>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODALES DE DATOS
══════════════════════════════════════════════════════════ -->

<!-- MODAL BAJAS -->
<div class="modal fade" id="modalBajas" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">📉 Registrar Baja</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="feedbackBajas"></div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label form-label-sm">Tipo de baja</label>
            <select class="form-select form-select-sm" id="bajaTipo">
              <option value="">-- Seleccionar --</option>
              <option value="TEMPORALES">Temporal</option>
              <option value="DEFINITIVAS">Definitiva</option>
            </select>
          </div>
          <div class="col-12" id="bajaSubtipoWrap">
            <label class="form-label form-label-sm">Subtipo</label>
            <select class="form-select form-select-sm" id="bajaSubtipo"></select>
          </div>
          <div class="col-12">
            <label class="form-label form-label-sm">Motivo</label>
            <input type="text" class="form-control form-control-sm" id="bajaMotivo" placeholder="Descripción del motivo">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Nº Mujeres</label>
            <input type="number" class="form-control form-control-sm" id="bajaMujeres" min="0" value="0">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Nº Hombres</label>
            <input type="number" class="form-control form-control-sm" id="bajaHombres" min="0" value="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" id="btnGuardarBajas">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL EXCEDENCIAS -->
<div class="modal fade" id="modalExcedencias" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">📈 Registrar Excedencia</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="feedbackExcedencias"></div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label form-label-sm">Tipo</label>
            <select class="form-select form-select-sm" id="excedenciaTipo">
              <option value="">-- Seleccionar --</option>
              <option>Excedencias Voluntarias</option>
              <option>Excedencias Cuidado Menores</option>
              <option>Excedencias Cuidado de Personas Mayores</option>
              <option>Otros</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label form-label-sm">Motivo</label>
            <input type="text" class="form-control form-control-sm" id="excedenciaMotivo" placeholder="Descripción">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Nº Mujeres</label>
            <input type="number" class="form-control form-control-sm" id="excedenciaMujeres" min="0" value="0">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Nº Hombres</label>
            <input type="number" class="form-control form-control-sm" id="excedenciaHombres" min="0" value="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" id="btnGuardarExcedencias">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL REDUCCIONES DE JORNADA -->
<div class="modal fade" id="modalReducciones" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">🕒 Reducción de jornada</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="feedbackReducciones"></div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label form-label-sm">Motivo de reducción</label>
            <select class="form-select form-select-sm" id="reduccionTipo">
              <option value="">-- Seleccionar --</option>
              <option>Cuidado de menores</option>
              <option>Cuidado de mayores</option>
              <option>Estudios</option>
              <option>Otros</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Nº Mujeres</label>
            <input type="number" class="form-control form-control-sm" id="reduccionMujeres" min="0" value="0">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Nº Hombres</label>
            <input type="number" class="form-control form-control-sm" id="reduccionHombres" min="0" value="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" id="btnGuardarReducciones">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL PROMOCIONES -->
<div class="modal fade" id="modalPromociones" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">🚀 Registrar Promoción</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="feedbackPromociones"></div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label form-label-sm">Puesto origen</label>
            <input type="text" class="form-control form-control-sm" id="promPuestoOrigen">
          </div>
          <div class="col-md-6">
            <label class="form-label form-label-sm">Puesto destino</label>
            <input type="text" class="form-control form-control-sm" id="promPuestoDestino">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Fecha de alta</label>
            <input type="date" class="form-control form-control-sm" id="promFechaAlta">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Nº Candidaturas</label>
            <input type="number" class="form-control form-control-sm" id="promCandidaturas" min="0" value="0">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">Nº Mujeres</label>
            <input type="number" class="form-control form-control-sm" id="promMujeres" min="0" value="0">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">Nº Hombres</label>
            <input type="number" class="form-control form-control-sm" id="promHombres" min="0" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Género promocionado</label>
            <select class="form-select form-select-sm" id="promGeneroPromocionado">
              <option value="">-- Seleccionar --</option>
              <option>Masculino</option>
              <option>Femenino</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Interna / Externa</label>
            <select class="form-select form-select-sm" id="promInternaExterna">
              <option value="">-- Seleccionar --</option>
              <option>Interna</option>
              <option>Externa</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Tipo de promoción</label>
            <input type="text" class="form-control form-control-sm" id="promTipo">
          </div>
          <div class="col-md-6">
            <label class="form-label form-label-sm">Responsable</label>
            <input type="text" class="form-control form-control-sm" id="promResponsable">
          </div>
          <div class="col-md-6">
            <label class="form-label form-label-sm">Cargo responsable</label>
            <input type="text" class="form-control form-control-sm" id="promCargoResponsable">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Género responsable</label>
            <select class="form-select form-select-sm" id="promGeneroResponsable">
              <option value="">-- Seleccionar --</option>
              <option>Masculino</option>
              <option>Femenino</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Contrato inicial</label>
            <input type="text" class="form-control form-control-sm" id="promContratoInicial">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Contrato final</label>
            <input type="text" class="form-control form-control-sm" id="promContratoFinal">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">% Jornada</label>
            <input type="number" class="form-control form-control-sm" id="promPctJornada" min="0" max="100" value="100">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Aumento económico (€)</label>
            <input type="number" class="form-control form-control-sm" id="promAumento" min="0" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">¿Disfruta conciliación?</label>
            <select class="form-select form-select-sm" id="promConciliacion">
              <option value="">-- Seleccionar --</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label form-label-sm">Criterio</label>
            <input type="text" class="form-control form-control-sm" id="promCriterio">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" id="btnGuardarPromociones">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL FORMACIÓN -->
<div class="modal fade" id="modalFormacion" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">🎓 Registrar Formación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="feedbackFormacion"></div>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label form-label-sm">Nombre / Tipo de formación</label>
            <input type="text" class="form-control form-control-sm" id="formTipo">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Nº Horas</label>
            <input type="number" class="form-control form-control-sm" id="formHoras" min="0" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Nº Mujeres</label>
            <input type="number" class="form-control form-control-sm" id="formMujeres" min="0" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Nº Hombres</label>
            <input type="number" class="form-control form-control-sm" id="formHombres" min="0" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Perfil / Puesto</label>
            <input type="text" class="form-control form-control-sm" id="formPerfil">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Modalidad</label>
            <select class="form-select form-select-sm" id="formModalidad">
              <option value="">-- Seleccionar --</option>
              <option>Presencial</option>
              <option>Online</option>
              <option>Mixta</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Horario</label>
            <select class="form-select form-select-sm" id="formHorario">
              <option value="">-- Seleccionar --</option>
              <option>Dentro del horario</option>
              <option>Fuera del horario</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Carácter</label>
            <select class="form-select form-select-sm" id="formCaracter">
              <option value="">-- Seleccionar --</option>
              <option>Obligatoria</option>
              <option>Voluntaria</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" id="btnGuardarFormacion">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    const AREAS       = <?= $areasJson ?>;
    const ID_EMPRESA  = <?= $idEmpresa ?>;
    const POR_PAGINA  = 5;
    let paginaActual  = 1;
    let areaActiva    = null;
    let idClienteMedidaActiva = 0;
    let rutaArchivoSubido     = '';

    // Mapeo nombre de área → tipo ENUM de la tabla archivos
    const AREA_TIPO_MAP = {
        'responsable': 'RESPONSABLE_IGUALDAD',
        'seleccion': 'SELECCION',
        'clasificacion': 'CLASIFICACION_PROFESIONAL',
        'formacion': 'FORMACION',
        'promocion': 'PROMOCION_ASCENSO',
        'condiciones': 'CONDICIONES_TRABAJO',
        'salud': 'SALUD',
        'vida': 'EJERCICIO_CORRESPONSABLE_VIDA_PERSONAL, FAMILIAR_Y_LABORAL',
        'infrarrep': 'INFRARREPRESENTACION_FEMENINA',
        'auditoria': 'RETRIBUCIONES_AUDITORIA_SALARIAL',
        'acoso': 'PREVENCION_ACOSO',
        'violencia': 'VIOLENCIA_GENERO',
        'comunicacion': 'COMUNICACION_SENSIBILIZACION',
        'lgtbi': 'LGTBI',
    };

    function tipoArchivoPorArea(nombreArea) {
        const norm = nombreArea.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        for (const [clave, tipo] of Object.entries(AREA_TIPO_MAP)) {
            if (norm.includes(clave)) return tipo;
        }
        return 'DOCUMENTACION';
    }

    // ── Helpers ──────────────────────────────────────────────
    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
    function badgeHtml(estado) {
        const map = {
            completada: '<span class="badge badge-done">Completada</span>',
            progreso:   '<span class="badge badge-progress">En progreso</span>',
            pendiente:  '<span class="badge badge-pending">Pendiente</span>',
        };
        return map[estado] || '';
    }
    function progressColor(e) {
        return e === 'completada' ? 'bg-success' : e === 'progreso' ? 'bg-warning' : '';
    }

    // ── Stats ─────────────────────────────────────────────────
    function calcStats() {
        let total = 0, comp = 0, prog = 0, pend = 0, sum = 0;
        AREAS.forEach(a => a.medidas.forEach(m => {
            total++; sum += m.progreso;
            if (m.estado === 'completada') comp++;
            else if (m.estado === 'progreso') prog++;
            else pend++;
        }));
        document.getElementById('statMedidas').textContent     = total;
        document.getElementById('statCompletadas').textContent = comp;
        document.getElementById('statProgreso').textContent    = prog;
        document.getElementById('statPendientes').textContent  = pend;
        document.getElementById('statPct').textContent         = (total > 0 ? Math.round(sum / total) : 0) + '%';
    }

    // ── Botones de área ───────────────────────────────────────
    function renderAreaBtns() {
        const c = document.getElementById('areasBtns');
        if (AREAS.length === 0) {
            c.innerHTML = '<p class="text-muted small mb-0">No hay áreas con medidas asignadas.</p>';
            return;
        }
        AREAS.forEach((area, idx) => {
            const btn = document.createElement('button');
            btn.className = 'area-btn' + (idx === 0 ? ' active' : '');
            btn.textContent = area.nombre;
            btn.addEventListener('click', function () {
                document.querySelectorAll('.area-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                renderMedidas(area, 1);
                resetDetail();
                resetForm();
            });
            c.appendChild(btn);
        });
    }

    // ── Tabla de medidas con paginación ───────────────────────
    function renderMedidas(area, pagina) {
        pagina = pagina || 1;
        paginaActual = pagina;
        areaActiva   = area;

        const nombreLimpio = area.nombre.replace(/^\S+\s/, '');
        document.getElementById('areaNombre').textContent       = area.nombre;
        document.getElementById('medidasCount').textContent     = area.medidas.length + ' medida' + (area.medidas.length !== 1 ? 's' : '');
        document.getElementById('datosMedidaTitle').textContent = 'Datos de la medida — ' + nombreLimpio;

        const totalPaginas = Math.ceil(area.medidas.length / POR_PAGINA);
        const inicio = (pagina - 1) * POR_PAGINA;
        const slice  = area.medidas.slice(inicio, inicio + POR_PAGINA);

        const tbody = document.getElementById('medidasTbody');
        tbody.innerHTML = '';
        slice.forEach((m, i) => {
            const idxReal = inicio + i;
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + esc(m.titulo) + '</td>' +
                '<td>' + badgeHtml(m.estado) + '</td>' +
                '<td>' +
                    '<div class="progress-wrap"><div class="progress-bar-fill ' + progressColor(m.estado) + '" style="width:' + m.progreso + '%"></div></div>' +
                    '<span class="progress-pct' + (m.progreso === 0 ? ' text-muted' : '') + '">' + m.progreso + '%</span>' +
                '</td>' +
                '<td class="text-muted small">' + esc(m.fecha) + '</td>' +
                '<td><button class="btn btn-sm btn-ghost" data-idx="' + idxReal + '">Ver</button></td>';
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('[data-idx]').forEach(btn => {
            btn.addEventListener('click', function () {
                const m = area.medidas[parseInt(this.dataset.idx)];
                document.querySelectorAll('#medidasTbody tr').forEach(r => r.classList.remove('row-selected'));
                this.closest('tr').classList.add('row-selected');
                idClienteMedidaActiva = m.id_cliente_medida || 0;
                showDetail(m);
                resetForm();
            });
        });

        // Seleccionar automáticamente la primera medida de la página visible
        const primeraFila = tbody.querySelector('tr');
        if (primeraFila) {
            const primerBtn = primeraFila.querySelector('[data-idx]');
            if (primerBtn) primerBtn.click();
        }

        renderPaginacion(area, pagina, totalPaginas);
    }

    function renderPaginacion(area, paginaActual, totalPaginas) {
        const bar = document.getElementById('paginacionMedidas');
        if (totalPaginas <= 1) { bar.innerHTML = ''; return; }
        const desde = (paginaActual - 1) * POR_PAGINA + 1;
        const hasta = Math.min(paginaActual * POR_PAGINA, area.medidas.length);
        let html = '<div class="pag-info">' + desde + '–' + hasta + ' de ' + area.medidas.length + ' medidas</div><div class="pag-btns">';
        html += '<button class="pag-btn pag-nav" ' + (paginaActual === 1 ? 'disabled' : '') + ' data-p="' + (paginaActual - 1) + '">← Anterior</button>';
        for (let p = 1; p <= totalPaginas; p++) {
            html += '<button class="pag-btn pag-num' + (p === paginaActual ? ' pag-active' : '') + '" data-p="' + p + '">' + p + '</button>';
        }
        html += '<button class="pag-btn pag-nav" ' + (paginaActual === totalPaginas ? 'disabled' : '') + ' data-p="' + (paginaActual + 1) + '">Siguiente →</button></div>';
        bar.innerHTML = html;
        bar.querySelectorAll('[data-p]').forEach(btn => {
            btn.addEventListener('click', function () {
                renderMedidas(area, parseInt(this.dataset.p));
                resetDetail();
            });
        });
    }

    // ── Panel derecho ─────────────────────────────────────────
    function showDetail(m) {
        document.getElementById('detailContent').innerHTML =
            badgeHtml(m.estado) +
            '<h6 class="fw-semibold mt-2">' + esc(m.titulo) + '</h6>' +
            '<p class="text-muted small">' + esc(m.detalle) + '</p>' +
            (m.indicador ? '<div class="detail-section-label">Indicador de seguimiento</div><p class="small mb-0">' + esc(m.indicador) + '</p>' : '') +
            '<div class="detail-divider"></div>' +
            '<div class="mb-3"><label class="form-label form-label-sm">Calendarización</label><div class="d-flex gap-2"><input type="date" class="form-control form-control-sm"><input type="date" class="form-control form-control-sm"></div></div>' +
            '<label class="form-label form-label-sm">Evidencias / Documentación</label>' +
            '<div id="listaEvidencias" class="mb-2"></div>' +
            '<div class="upload-box mt-2" id="uploadBoxEvidencia">' +
                '<input type="file" id="inputEvidencia" style="display:none" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.xls,.xlsx">' +
                '<div class="upload-icon">☁️</div>' +
                '<div class="small text-muted">Arrastra aquí tu archivo o haz clic para buscar</div>' +
                '<button type="button" class="btn btn-outline-primary btn-sm mt-2" id="btnSubirEvidencia">Subir nueva evidencia</button>' +
                '<div id="estadoEvidencia" class="small mt-1"></div>' +
            '</div>' +
            '<div class="d-flex justify-content-end mt-3 pt-2" style="border-top:1px solid var(--border)">' +
                '<div id="feedbackDetailPanel" class="small me-auto"></div>' +
                '<button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardarDetalle">Guardar</button>' +
            '</div>';

        // Conectar el botón de subida de evidencia
        const btnEv  = document.getElementById('btnSubirEvidencia');
        const inputEv = document.getElementById('inputEvidencia');
        const estadoEv = document.getElementById('estadoEvidencia');
        const uploadBox = document.getElementById('uploadBoxEvidencia');

        // Click y drag & drop
        if (btnEv && inputEv) {
            btnEv.addEventListener('click', () => inputEv.click());

            uploadBox.addEventListener('dragover', e => { e.preventDefault(); uploadBox.style.borderColor = 'var(--accent)'; });
            uploadBox.addEventListener('dragleave', () => { uploadBox.style.borderColor = ''; });
            uploadBox.addEventListener('drop', e => {
                e.preventDefault();
                uploadBox.style.borderColor = '';
                if (e.dataTransfer.files[0]) subirEvidencia(e.dataTransfer.files[0]);
            });

            inputEv.addEventListener('change', function () {
                if (this.files[0]) subirEvidencia(this.files[0]);
            });
        }

        // Botón Guardar del panel derecho
        const btnGuardarDetalle = document.getElementById('btnGuardarDetalle');
        const feedbackDetalle   = document.getElementById('feedbackDetailPanel');
        const fechas = document.querySelectorAll('#detailContent input[type="date"]');

        if (btnGuardarDetalle) {
            btnGuardarDetalle.addEventListener('click', function () {
                if (!idClienteMedidaActiva) {
                    feedbackDetalle.innerHTML = '<span class="text-danger">Sin medida seleccionada.</span>';
                    return;
                }
                const fechaInicio = fechas[0]?.value || '';
                const fechaFin    = fechas[1]?.value || '';

                btnGuardarDetalle.disabled = true;
                btnGuardarDetalle.textContent = 'Guardando…';

                fetch('../php/guardar_seguimiento_medida.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        id_cliente_medida : idClienteMedidaActiva,
                        id_empresa        : ID_EMPRESA,
                        fecha_publicacion : fechaInicio,
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        feedbackDetalle.innerHTML = '<span class="text-success fw-semibold">✓ Guardado</span>';
                        setTimeout(() => { feedbackDetalle.innerHTML = ''; }, 2500);
                    } else {
                        feedbackDetalle.innerHTML = '<span class="text-danger">✗ ' + (data.error || 'Error') + '</span>';
                    }
                })
                .catch(() => { feedbackDetalle.innerHTML = '<span class="text-danger">✗ Error de conexión.</span>'; })
                .finally(() => { btnGuardarDetalle.disabled = false; btnGuardarDetalle.textContent = 'Guardar'; });
            });
        }

        function subirEvidencia(file) {
            estadoEv.textContent = 'Subiendo…';
            estadoEv.className = 'small mt-1 text-muted';
            btnEv.disabled = true;

            const tipoArchivo = tipoArchivoPorArea(areaActiva ? areaActiva.nombre : '');
            const fd = new FormData();
            fd.append('archivo', file);
            fd.append('id_empresa', ID_EMPRESA);
            fd.append('id_cliente_medida', idClienteMedidaActiva);
            fd.append('tipo', tipoArchivo);

            fetch('../php/subir_evidencia_medida.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        estadoEv.textContent = '✓ ' + file.name + ' subido correctamente.';
                        estadoEv.className = 'small mt-1 text-success fw-semibold';
                        // Añadir el archivo a la lista
                        const lista = document.getElementById('listaEvidencias');
                        if (lista) {
                            lista.insertAdjacentHTML('beforeend',
                                '<div class="file-item mb-1">' +
                                    '<div class="file-icon">📄</div>' +
                                    '<div class="file-info"><strong>' + esc(file.name) + '</strong>' +
                                    '<span class="text-muted small"> · ' + (Math.round(file.size/1024)) + ' KB</span></div>' +
                                '</div>'
                            );
                        }
                    } else {
                        estadoEv.textContent = '✗ ' + (data.error || 'Error al subir.');
                        estadoEv.className = 'small mt-1 text-danger';
                    }
                })
                .catch(() => { estadoEv.textContent = '✗ Error de conexión.'; estadoEv.className = 'small mt-1 text-danger'; })
                .finally(() => { btnEv.disabled = false; inputEv.value = ''; });
        }
    }

    function resetDetail() {
        document.getElementById('detailContent').innerHTML = '<p class="text-muted small">Pulsa <strong>Ver</strong> en una medida para ver su detalle aquí.</p>';
        document.querySelectorAll('#medidasTbody tr').forEach(r => r.classList.remove('row-selected'));
    }

    // ── Reset formulario ──────────────────────────────────────
    function resetForm() {
        const fb = document.getElementById('formGuardarFeedback');
        if (fb) { fb.style.display = 'none'; fb.innerHTML = ''; }
    }

    // ── Subir archivo ─────────────────────────────────────────
    const btnSubir     = document.getElementById('btnSubirArchivo');
    const inputArchivo = document.getElementById('inputArchivoOferta');
    const spanNombre   = document.getElementById('nombreArchivoOferta');
    const spanEstado   = document.getElementById('estadoSubidaArchivo');

    if (btnSubir && inputArchivo) {
        btnSubir.addEventListener('click', () => inputArchivo.click());
        inputArchivo.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            spanNombre.textContent = file.name;
            spanEstado.textContent = '';
            const fd = new FormData();
            fd.append('archivo', file);
            fd.append('id_empresa', ID_EMPRESA);
            btnSubir.disabled = true;
            btnSubir.textContent = 'Subiendo...';
            fetch('../php/subir_archivo_medida.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        rutaArchivoSubido = data.ruta;
                        spanEstado.textContent = '✓ Subido';
                        spanEstado.className = 'small text-success fw-semibold';
                    } else {
                        spanEstado.textContent = '✗ ' + (data.error || 'Error');
                        spanEstado.className = 'small text-danger';
                    }
                })
                .catch(() => { spanEstado.textContent = '✗ Error de conexión'; spanEstado.className = 'small text-danger'; })
                .finally(() => { btnSubir.disabled = false; btnSubir.textContent = 'Subir archivo'; });
        });
    }

    // ── Mostrar/ocultar nuevo puesto ──────────────────────────
    const selectPuesto     = document.getElementById('selectPuesto');
    const inputNuevoPuesto = document.getElementById('inputNuevoPuesto');
    if (selectPuesto && inputNuevoPuesto) {
        selectPuesto.addEventListener('change', function () {
            const show = this.value === '__nuevo__';
            inputNuevoPuesto.style.display = show ? '' : 'none';
            if (show) inputNuevoPuesto.focus();
            else inputNuevoPuesto.value = '';
        });
    }

    // ── Guardar datos ─────────────────────────────────────────
    const btnGuardar = document.getElementById('btnGuardarMedida');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', function () {
            if (!idClienteMedidaActiva) {
                mostrarFeedback('warning', 'Selecciona una medida de la tabla antes de guardar.');
                return;
            }

            const puesto      = selectPuesto ? selectPuesto.value : '';
            const puestoNuevo = inputNuevoPuesto ? inputNuevoPuesto.value.trim() : '';
            const fecha       = document.getElementById('inputFechaPublicacion')?.value || '';
            const generoEl    = document.querySelector('input[name="genero_candidatura"]:checked');
            const genero      = generoEl ? generoEl.value : '';
            const numCand     = document.getElementById('inputCandidaturaNum')?.value || '';
            const criterioEl  = document.querySelector('input[name="criterio"]:checked');
            const criterio    = criterioEl ? criterioEl.value : '';
            const otros       = document.getElementById('inputCriterioOtros')?.value.trim() || '';

            const body = new URLSearchParams({
                id_cliente_medida : idClienteMedidaActiva,
                id_empresa        : ID_EMPRESA,
                puesto_empresa    : puesto === '__nuevo__' ? '' : puesto,
                puesto_nuevo      : puesto === '__nuevo__' ? puestoNuevo : '',
                fecha_publicacion : fecha,
                archivo_oferta    : rutaArchivoSubido,
                candidatura_genero: genero,
                candidatura_numero: numCand,
                criterio_seleccion: criterio,
                criterio_otros    : otros,
            });

            btnGuardar.disabled = true;
            btnGuardar.textContent = 'Guardando...';

            fetch('../php/guardar_seguimiento_medida.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        mostrarFeedback('success', '✓ Datos guardados correctamente.');
                        limpiarFormulario();
                    } else {
                        mostrarFeedback('danger', '✗ ' + (data.error || 'Error al guardar.'));
                    }
                })
                .catch(() => mostrarFeedback('danger', '✗ Error de conexión.'))
                .finally(() => {
                    btnGuardar.disabled = false;
                    btnGuardar.textContent = 'Guardar datos';
                });
        });
    }

    function limpiarFormulario() {
        // Puesto
        if (selectPuesto) { selectPuesto.value = ''; }
        if (inputNuevoPuesto) { inputNuevoPuesto.value = ''; inputNuevoPuesto.style.display = 'none'; }
        // Fecha
        const fecha = document.getElementById('inputFechaPublicacion');
        if (fecha) fecha.value = '';
        // Archivo
        if (inputArchivo) inputArchivo.value = '';
        if (spanNombre) spanNombre.textContent = 'Ningún archivo seleccionado';
        if (spanEstado) { spanEstado.textContent = ''; spanEstado.className = ''; }
        rutaArchivoSubido = '';
        // Candidaturas
        document.querySelectorAll('input[name="genero_candidatura"]').forEach(r => r.checked = false);
        const numCand = document.getElementById('inputCandidaturaNum');
        if (numCand) numCand.value = '';
        // Criterio
        const criterioDefecto = document.querySelector('input[name="criterio"][value="FORMACION"]');
        if (criterioDefecto) criterioDefecto.checked = true;
        const otros = document.getElementById('inputCriterioOtros');
        if (otros) otros.value = '';
    }

    function mostrarFeedback(tipo, msg) {
        const fb = document.getElementById('formGuardarFeedback');
        if (!fb) return;
        fb.innerHTML = '<div class="alert alert-' + tipo + ' py-2 mb-0">' + msg + '</div>';
        fb.style.display = '';
        if (tipo === 'success') setTimeout(() => { fb.style.display = 'none'; }, 3000);
    }

    // ── Tiles → modales ───────────────────────────────────────
    document.querySelectorAll('.tile[data-modal]').forEach(tile => {
        tile.style.cursor = 'pointer';
        tile.addEventListener('click', function () {
            const modal = new bootstrap.Modal(document.getElementById(this.dataset.modal));
            modal.show();
        });
    });

    // Subtipos dinámicos para Bajas
    const bajaTipoSelect = document.getElementById('bajaTipo');
    const bajaSubtipoSelect = document.getElementById('bajaSubtipo');
    const subtipos = {
        TEMPORALES: ['Enfermedad Común','Accidente Laboral','Riesgo embarazo','COVID'],
        DEFINITIVAS: ['Despido','Fallecimiento','Finalización contrato','Jubilación','No superación de periodo de prueba','Baja voluntaria'],
    };
    if (bajaTipoSelect) {
        bajaTipoSelect.addEventListener('change', function () {
            bajaSubtipoSelect.innerHTML = '<option value="">-- Seleccionar --</option>';
            (subtipos[this.value] || []).forEach(s => {
                bajaSubtipoSelect.innerHTML += '<option>' + s + '</option>';
            });
        });
    }

    // Helper genérico para guardar
    function guardarDatos(url, payload, feedbackId, modalId) {
        const fb  = document.getElementById(feedbackId);
        const btn = document.querySelector('#' + modalId + ' .btn-primary');
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
        fetch(url, { method: 'POST', body: new URLSearchParams(payload) })
            .then(r => r.json())
            .then(data => {
                if (fb) {
                    fb.innerHTML = data.ok
                        ? '<div class="alert alert-success py-2">✓ Guardado correctamente.</div>'
                        : '<div class="alert alert-danger py-2">✗ ' + (data.error || 'Error') + '</div>';
                }
                if (data.ok) {
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
                        if (fb) fb.innerHTML = '';
                    }, 1500);
                }
            })
            .catch(() => { if (fb) fb.innerHTML = '<div class="alert alert-danger py-2">✗ Error de conexión.</div>'; })
            .finally(() => { if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; } });
    }

    const EP = '../php/guardar_datos_area.php';

    // Guardar Bajas
    document.getElementById('btnGuardarBajas')?.addEventListener('click', () => {
        guardarDatos(EP, {
            accion: 'bajas',
            id_empresa: ID_EMPRESA,
            tipo: document.getElementById('bajaTipo').value,
            subtipo: document.getElementById('bajaSubtipo').value,
            motivo: document.getElementById('bajaMotivo').value,
            num_mujeres: document.getElementById('bajaMujeres').value,
            num_hombres: document.getElementById('bajaHombres').value,
        }, 'feedbackBajas', 'modalBajas');
    });

    // Guardar Excedencias
    document.getElementById('btnGuardarExcedencias')?.addEventListener('click', () => {
        guardarDatos(EP, {
            accion: 'excedencias',
            id_empresa: ID_EMPRESA,
            tipo: document.getElementById('excedenciaTipo').value,
            motivo: document.getElementById('excedenciaMotivo').value,
            n_mujeres: document.getElementById('excedenciaMujeres').value,
            n_hombres: document.getElementById('excedenciaHombres').value,
        }, 'feedbackExcedencias', 'modalExcedencias');
    });

    // Guardar Reducciones
    document.getElementById('btnGuardarReducciones')?.addEventListener('click', () => {
        guardarDatos(EP, {
            accion: 'reducciones',
            id_empresa: ID_EMPRESA,
            reduccion_jornada: document.getElementById('reduccionTipo').value,
            n_mujeres: document.getElementById('reduccionMujeres').value,
            n_hombres: document.getElementById('reduccionHombres').value,
        }, 'feedbackReducciones', 'modalReducciones');
    });

    // Guardar Promociones
    document.getElementById('btnGuardarPromociones')?.addEventListener('click', () => {
        guardarDatos(EP, {
            accion: 'promociones',
            id_empresa: ID_EMPRESA,
            puesto_origen: document.getElementById('promPuestoOrigen').value,
            puesto_destino: document.getElementById('promPuestoDestino').value,
            fecha_de_alta: document.getElementById('promFechaAlta').value,
            n_candidaturas: document.getElementById('promCandidaturas').value,
            n_mujeres: document.getElementById('promMujeres').value,
            n_hombres: document.getElementById('promHombres').value,
            genero_promocionado: document.getElementById('promGeneroPromocionado').value,
            interna_externa: document.getElementById('promInternaExterna').value,
            tipo_promocion: document.getElementById('promTipo').value,
            responsable: document.getElementById('promResponsable').value,
            cargo_responsable: document.getElementById('promCargoResponsable').value,
            genero_responsable: document.getElementById('promGeneroResponsable').value,
            contrato_inicial: document.getElementById('promContratoInicial').value,
            contrato_final: document.getElementById('promContratoFinal').value,
            porcentaje_jornada: document.getElementById('promPctJornada').value,
            aumento_economico: document.getElementById('promAumento').value,
            disfruta_conciliacion: document.getElementById('promConciliacion').value,
            criterio: document.getElementById('promCriterio').value,
        }, 'feedbackPromociones', 'modalPromociones');
    });

    // Guardar Formación
    document.getElementById('btnGuardarFormacion')?.addEventListener('click', () => {
        guardarDatos(EP, {
            accion: 'formacion',
            id_empresa: ID_EMPRESA,
            tipo: document.getElementById('formTipo').value,
            n_horas: document.getElementById('formHoras').value,
            n_mujeres: document.getElementById('formMujeres').value,
            n_hombres: document.getElementById('formHombres').value,
            perfil_puesto: document.getElementById('formPerfil').value,
            modalidad: document.getElementById('formModalidad').value,
            horario: document.getElementById('formHorario').value,
            caracter: document.getElementById('formCaracter').value,
        }, 'feedbackFormacion', 'modalFormacion');
    });

    // ── Init ──────────────────────────────────────────────────
    calcStats();
    renderAreaBtns();
    if (AREAS.length > 0) renderMedidas(AREAS[0], 1);

    // Abrir modal complemento desde el padre
    window.addEventListener('message', function (e) {
        if (e.data && e.data.type === 'abrirComplemento') {
            window.parent.postMessage(e.data, '*');
        }
    });
})();
</script>
</body>
</html>
