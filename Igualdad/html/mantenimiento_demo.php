<!doctype html>
<!--
    Mantenimiento Demo (vista estática)
    ----------------------------------
    Página puramente demostrativa que reproduce la interfaz de 'Mantenimiento'
    para la sección de Plan de Igualdad. Diseñada sólo como maqueta visual:
    - No contiene lógica de backend ni envíos de formularios reales.
    - Se mantiene como recurso embebible (iframe) en otras vistas.
-->
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mi Plan de Igualdad - Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mantenimiento_demo.css">
    <style>
        /* minor inline resets for demo */
        :root {
            --accent: #6f4ae3;
            --muted: #f6f7fb
        }
        html, body {
            margin: 0;
            overflow-x: hidden;
            overflow-y: auto;
            min-height: 100%;
        }
    </style>
</head>

<body>
    <div class="demo-app">
        <main class="main">
            <!-- Topbar: título del área y acciones globales (resumen, descarga) -->
            <header class="topbar d-flex align-items-center justify-content-between">
                <div class="title">
                    <h2>Mi Plan de Igualdad</h2>
                    <p class="text-muted">Consulta, gestiona y realiza el seguimiento de las medidas de tu Plan de Igualdad.</p>
                </div>
                <div class="actions d-flex gap-2">
                    <button class="btn btn-outline-secondary">Ver resumen ejecutivo</button>
                    <button class="btn btn-primary">Descargar Plan</button>
                </div>
            </header>

            <!-- Sección principal: selector de áreas/medidas y contenido asociado -->
            <section class="measures">
                <div class="areas d-flex flex-wrap gap-3">
                    <button class="btn area-btn active">🕵️ Responsable Igualdad</button>
                    <button class="btn area-btn">🔎 Seleccion</button>
                    <button class="btn area-btn">📚 Clasificacion profesional</button>
                    <button class="btn area-btn">🎓 Formacion</button>
                    <button class="btn area-btn">🚀 Promocion</button>
                    <button class="btn area-btn">💼 Condiciones de trabajo</button>
                    <button class="btn area-btn">🏥 Salud laboral</button>
                    <button class="btn area-btn">🏠 Vida personal</button>
                    <button class="btn area-btn">🙅‍♀️ Infrarrep. femenina</button>
                    <button class="btn area-btn">📊 Auditoria salarial</button>
                    <button class="btn area-btn">🛑 Acoso sexual</button>
                    <button class="btn area-btn">💥 Violencia de genero</button>
                    <button class="btn area-btn">📢 Comunicacion y sensibiliazcion</button>
                    <button class="btn area-btn">🏳️‍🌈 LGTBI</button>
                </div>

                <!-- Content: columna izquierda con listado de medidas y formulario simulado -->
                <div class="content mt-4">
                    <div class="left">
                        <div class="measure-panel shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Selección</h5>
                                <a href="#" class="small">Ver todas las medidas →</a>
                            </div>

                            <table class="table table-borderless">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>Medida</th>
                                        <th>Estado</th>
                                        <th>Progreso</th>
                                        <th>Última actualización</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Elaborar y publicar ofertas de empleo con lenguaje inclusivo</td>
                                        <td><span class="badge bg-warning text-dark">En progreso</span></td>
                                        <td style="width:180px;">
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width:60%">60%</div>
                                            </div>
                                        </td>
                                        <td>15/04/2024</td>
                                        <td><button class="btn btn-sm btn-outline-secondary">Ver</button></td>
                                    </tr>
                                    <tr>
                                        <td>Garantizar la igualdad en los procesos de selección</td>
                                        <td><span class="badge bg-success">Completada</span></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width:100%">100%</div>
                                            </div>
                                        </td>
                                        <td>10/04/2024</td>
                                        <td><button class="btn btn-sm btn-outline-secondary">Ver</button></td>
                                    </tr>
                                    <tr>
                                        <td>Formación al equipo de selección</td>
                                        <td><span class="badge bg-secondary text-dark">Pendiente</span></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-light" style="width:0%"></div>
                                            </div>
                                        </td>
                                        <td>05/04/2024</td>
                                        <td><button class="btn btn-sm btn-outline-secondary">Ver</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                            <!-- Datos de la medida (simulado)
                                - Campos no interactivos funcionalmente en esta demo.
                                - Aquí se muestra cómo se vería la edición/visualización de una medida.
                            -->
                            <div class="measure-panel shadow-sm mt-3">
                            <h6 class="mb-3">Datos de la medida - Selección</h6>
                            <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small">Puesto de trabajo</label>
                                        <select class="form-select">
                                            <option>Técnico/a de Marketing</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Fecha de publicación</label>
                                        <input type="date" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Archivo oferta de empleo</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <button class="btn btn-outline-primary btn-sm">Subir archivo</button>
                                            <div class="small text-muted">oferta_marketing.pdf</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 d-flex gap-2">
                                        <div class="form-control text-center">Mujeres<br>
                                            <div>
                                                <input class="form-control" type="text">
                                            </div>
                                        </div>
                                        <div class="form-control text-center">Hombres<br>
                                            <div>
                                                <input class="form-control" type="text">
                                            </div>
                                        </div>
                                        <div class="form-control text-center">Seleccionadas<br>
                                            <div>
                                                <input class="form-control" type="text">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small">Criterio de selección</label>
                                        <div class="d-flex gap-3 flex-wrap">
                                            <div class="form-check"><input class="form-check-input" type="radio" checked><label class="form-check-label">Formación</label></div>
                                            <div class="form-check"><input class="form-check-input" type="radio"><label class="form-check-label">Disponibilidad</label></div>
                                            <div class="form-check"><input class="form-check-input" type="radio"><label class="form-check-label">Experiencia</label></div>
                                            <div class="form-check"><input class="form-check-input" type="radio"><label class="form-check-label">Otros</label></div>
                                            <div class="form-group">
                                                <input type="text" class="form-control" placeholder="Especificar otros criterios">
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        </div>

                        <!-- Action cards: opciones rápidas (registro retributivo, datos cuantitativos, docs) -->
                        <div class="action-cards d-flex gap-3 mt-3">
                            <div class="card flex-fill p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-box bg-success">💾</div>
                                    <div>
                                        <div class="fw-bold">Registro Retributivo</div>
                                        <div class="text-muted small">Sube el registro retributivo de la empresa para el análisis de brechas salariales.</div>
                                        <div class="mt-2"><button class="btn btn-success btn-sm">Subir registro retributivo</button></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card flex-fill p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-box bg-primary">📊</div>
                                    <div>
                                        <div class="fw-bold">Datos Cuantitativos</div>
                                        <div class="text-muted small">Introduce los datos cuantitativos del año para el seguimiento del plan.</div>
                                        <div class="mt-2"><button class="btn btn-outline-primary btn-sm">Introducir datos</button></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card flex-fill p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-box bg-secondary">📁</div>
                                    <div>
                                        <div class="fw-bold">Ver mi Documentación</div>
                                        <div class="text-muted small">Consulta y gestiona toda la documentación relacionada con el plan de igualdad.</div>
                                        <div class="mt-2"><button class="btn btn-outline-secondary btn-sm">Ver documentación</button></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data tiles: métricas rápidas y atajos visuales -->
                            <div class="data-tiles d-flex gap-3 flex-wrap mt-3">
                            <div class="tile"> 📉 Bajas</div>
                            <div class="tile"> 📈 Excedencias</div>
                            <div class="tile"> 🕒 Reducciones de jornada</div>
                            <div class="tile"> 🤝 Contrataciones</div>
                            <div class="tile"> 🚀 Promociones</div>
                            <div class="tile"> 🎓 Formación</div>
                            <div class="tile"> 📋 Ver todos</div>
                        </div>
                                <!-- fin left content -->
                                </div>

                                <!-- Right detail panel: detalle y evidencias de la medida seleccionada -->
                                <div class="right">
                                    <aside class="detail-panel shadow-sm">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="mb-0">Detalle de la medida</h5>
                                            <button class="btn btn-outline-primary btn-sm">+ Editar</button>
                                        </div>

                                        <h6 class="fw-semibold">Elaborar y publicar ofertas de empleo con lenguaje inclusivo</h6>
                                        <p class="text-muted small">Revisar y adaptar todas las ofertas de empleo utilizando un lenguaje inclusivo y no sexista.</p>

                                            <div class="mt-3">
                                            <label class="form-label small">Calendarización</label>
                                            <div class="d-flex gap-2">
                                                <input type="date" class="form-control">
                                                <input type="date" class="form-control">
                                            </div>
                                        </div>

                                            <!-- Evidencias y documentación: lista de archivos y área de subida (simulada) -->
                                            <div class="mt-3">
                                            <label class="form-label small">Evidencias / Documentación</label>
                                            <ul class="list-unstyled mt-2 files-list">
                                                <li class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                                    <div>
                                                        <strong>Oferta_Desarrollador_Web.png</strong>
                                                        <div class="small text-muted">245 KB</div>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-sm btn-outline-secondary">Descargar</button>
                                                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                                    </div>
                                                </li>
                                            </ul>
                                            <!-- Upload box: interacción visual (no sube archivos en la demo) -->
                                            <div class="upload-box mt-2 p-3 text-center border rounded">
                                                <div class="small text-muted">Arrastra aquí tu archivo o haz clic para buscar</div>
                                                <button class="btn btn-outline-primary btn-sm mt-2">Subir nueva evidencia</button>
                                            </div>
                                        </div>
                                    </aside>
                                </div>
                            </div>
            </section>
        </main>
    </div>

    <!-- Dependencias JS (bootstrap) - la demo usa componentes CSS/JS básicos de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>