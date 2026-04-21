<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../php/auth.php';

require_login();
require __DIR__ . '/../config/config.php';

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdministrador = ($rol === 'ADMINISTRADOR');
$esTecnico = ($rol === 'TECNICO');

if (!$esAdministrador && !$esTecnico) {
  http_response_code(403);
  exit('Acceso denegado');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
  redirect_mantenimiento('ver_formacion', 0, 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.');
}

$accion = (string)($_POST['accion'] ?? '');

if ($esTecnico && $accion !== 'add_formacion' && $accion !== 'add_ejercicio' && $accion !== 'add_infra' && $accion !== 'add_acoso' && $accion !== 'add_violencia' && $accion !== 'add_retribuciones' && $accion !== 'add_condiciones' && $accion !== 'add_salud' && $accion !== 'add_responsable_igualdad' && $accion !== 'add_seleccion' && $accion !== 'add_promocion') {
  http_response_code(403);
  exit('Acceso denegado');
}

function tecnico_tiene_empresa(int $idEmpresa, int $idUsuario): bool
{
  if ($idEmpresa <= 0 || $idUsuario <= 0) {
    return false;
  }

  $db = db();
  $stmt = $db->prepare("SELECT 1 FROM usuario_empresa WHERE id_empresa = ? AND id_usuario = ? LIMIT 1");
  $stmt->bind_param('ii', $idEmpresa, $idUsuario);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($ok) {
    return true;
  }

  $stmt = $db->prepare("SELECT 1 FROM empresa WHERE id_empresa = ? AND id_usuario = ? LIMIT 1");
  $stmt->bind_param('ii', $idEmpresa, $idUsuario);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $ok;
}

function redirect_mantenimiento(string $view, int $idEmpresa, string $msg = ''): void
{
  $to = app_path('/model/mantenimiento.php?view=') . urlencode($view) . '&id_empresa=' . $idEmpresa;
  if ($msg !== '') {
    $to .= '&msg=' . urlencode($msg);
  }
  header("Location: $to");
  exit;
}

function log_internal_error_mantenimiento(string $context, Throwable $e): void
{
  error_log(sprintf(
    '[%s] %s in %s:%d',
    $context,
    $e->getMessage(),
    $e->getFile(),
    $e->getLine()
  ));
}

$idEmpresa = (int)($_POST['id_empresa'] ?? 0);
$currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);

if ($accion === 'add_formacion') {
  $nombre = trim((string)($_POST['nombre'] ?? ''));
  $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
  $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
  $laboral = trim((string)($_POST['laboral'] ?? ''));
  $modalidad = trim((string)($_POST['modalidad'] ?? ''));
  $voluntariaObligatoria = trim((string)($_POST['voluntaria_obligatoria'] ?? ''));
  $nHoras = (int)($_POST['n_horas'] ?? 0);
  $nHombres = (int)($_POST['n_hombres'] ?? 0);
  $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
  $informadoPlantilla = trim((string)($_POST['informado_plantilla'] ?? ''));
  $criterioSeleccion = trim((string)($_POST['criterio_seleccion'] ?? ''));

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_formacion', 0, 'Empresa inválida para registrar formación.');
  }

  if (
    $nombre === '' || $fechaInicio === '' || $fechaFin === '' ||
    $laboral === '' || $modalidad === '' || $voluntariaObligatoria === '' ||
    $nHoras < 0 || $nHombres < 0 || $nMujeres < 0 ||
    $informadoPlantilla === '' || $criterioSeleccion === ''
  ) {
    redirect_mantenimiento('ver_formacion', $idEmpresa, 'Completa todos los campos obligatorios de formación.');
  }

  if (!in_array($laboral, ['Dentro', 'Fuera'], true)) {
    redirect_mantenimiento('ver_formacion', $idEmpresa, 'El campo Dentro/Fuera Laboral no es válido.');
  }

  if (!in_array($voluntariaObligatoria, ['Voluntaria', 'Obligatoria'], true)) {
    redirect_mantenimiento('ver_formacion', $idEmpresa, 'El campo Voluntaria/Obligatoria no es válido.');
  }

  if (strtotime($fechaInicio) === false || strtotime($fechaFin) === false) {
    redirect_mantenimiento('ver_formacion', $idEmpresa, 'Formato de fechas inválido.');
  }

  if (strtotime($fechaInicio) > strtotime($fechaFin)) {
    redirect_mantenimiento('ver_formacion', $idEmpresa, 'La fecha de inicio no puede ser mayor que la fecha de fin.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%formacion%' OR LOWER(ap.nombre) LIKE '%formación%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_formacion', $idEmpresa, 'No existe una medida de Formación asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_formacion', $idEmpresa, 'No se pudo resolver la medida de Formación para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_formacion (
      nombre, fecha_inicio, fecha_fin, laboral, modalidad, voluntaria_obligatoria,
      n_horas, n_hombres, n_mujeres, informado_plantilla, criterio_seleccion, id_cliente_medida
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtInsert->bind_param(
      'ssssssiiissi',
      $nombre,
      $fechaInicio,
      $fechaFin,
      $laboral,
      $modalidad,
      $voluntariaObligatoria,
      $nHoras,
      $nHombres,
      $nMujeres,
      $informadoPlantilla,
      $criterioSeleccion,
      $idClienteMedida
    );
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_formacion', $idEmpresa, 'Formación registrada correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_formacion', $e);
    redirect_mantenimiento('ver_formacion', $idEmpresa, 'No se pudo guardar la formación. Intentalo de nuevo.');
  }
}

if ($accion === 'add_ejercicio') {
  $medida = trim((string)($_POST['medida'] ?? ''));
  $solicitaMujeres = (int)($_POST['solicita_mujeres'] ?? 0);
  $solicitaHombres = (int)($_POST['solicita_hombres'] ?? 0);
  $concedeMujeres = (int)($_POST['concede_mujeres'] ?? 0);
  $concedeHombres = (int)($_POST['concede_hombres'] ?? 0);

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_ejercicio', 0, 'Empresa inválida para registrar ejercicio.');
  }

  if ($medida === '' || $solicitaMujeres < 0 || $solicitaHombres < 0 || $concedeMujeres < 0 || $concedeHombres < 0) {
    redirect_mantenimiento('ver_ejercicio', $idEmpresa, 'Completa todos los campos obligatorios de ejercicio.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND LOWER(ap.nombre) LIKE '%ejercicio%'
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_ejercicio', $idEmpresa, 'No existe una medida de Ejercicio asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_ejercicio', $idEmpresa, 'No se pudo resolver la medida de Ejercicio para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_ejercicio (
      medida, solicita_mujeres, solicita_hombres, concede_mujeres, concede_hombres, id_cliente_medida
    ) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtInsert->bind_param('siiiii', $medida, $solicitaMujeres, $solicitaHombres, $concedeMujeres, $concedeHombres, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_ejercicio', $idEmpresa, 'Ejercicio registrado correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_ejercicio', $e);
    redirect_mantenimiento('ver_ejercicio', $idEmpresa, 'No se pudo guardar el ejercicio. Intentalo de nuevo.');
  }
}

if ($accion === 'add_infra') {
  $plantillaMujeres = (int)($_POST['plantilla_mujeres'] ?? 0);
  $plantillaHombres = (int)($_POST['plantilla_hombres'] ?? 0);

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_infra', 0, 'Empresa inválida para registrar infrarrepresentación femenina.');
  }

  if ($plantillaMujeres < 0 || $plantillaHombres < 0) {
    redirect_mantenimiento('ver_infra', $idEmpresa, 'Las cantidades no pueden ser negativas.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%infrarrepresent%' OR LOWER(ap.nombre) LIKE '%infra%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_infra', $idEmpresa, 'No existe una medida de Infrarrepresentación femenina asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_infra', $idEmpresa, 'No se pudo resolver la medida de Infrarrepresentación femenina para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_infra (plantilla_mujeres, plantilla_hombres, id_cliente_medida) VALUES (?, ?, ?)");
    $stmtInsert->bind_param('iii', $plantillaMujeres, $plantillaHombres, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_infra', $idEmpresa, 'Infrarrepresentación femenina registrada correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_infra', $e);
    redirect_mantenimiento('ver_infra', $idEmpresa, 'No se pudo guardar la infrarrepresentación femenina. Intentalo de nuevo.');
  }
}

if ($accion === 'add_acoso') {
  $incidente = trim((string)($_POST['incidente'] ?? ''));
  $fechaAlta = trim((string)($_POST['fecha_alta'] ?? ''));
  $procedimiento = trim((string)($_POST['procedimiento'] ?? ''));
  $gradoIncidencia = trim((string)($_POST['grado_incidencia'] ?? ''));
  $acciones = trim((string)($_POST['acciones'] ?? ''));

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_acoso', 0, 'Empresa inválida para registrar acoso.');
  }

  if ($incidente === '' || $fechaAlta === '' || $procedimiento === '' || $gradoIncidencia === '') {
    redirect_mantenimiento('ver_acoso', $idEmpresa, 'Completa los campos obligatorios de acoso.');
  }

  if (strtotime($fechaAlta) === false) {
    redirect_mantenimiento('ver_acoso', $idEmpresa, 'La fecha de alta no es válida.');
  }

  $fechaAltaSql = date('Y-m-d 00:00:00', strtotime($fechaAlta));

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%acoso%' OR LOWER(ap.nombre) LIKE '%acoso sexual%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_acoso', $idEmpresa, 'No existe una medida de Acoso asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_acoso', $idEmpresa, 'No se pudo resolver la medida de Acoso para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_acoso (incidente, procedimiento, grado_incidencia, fecha_alta, acciones, id_cliente_medida) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtInsert->bind_param('sssssi', $incidente, $procedimiento, $gradoIncidencia, $fechaAltaSql, $acciones, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_acoso', $idEmpresa, 'Acoso registrado correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_acoso', $e);
    redirect_mantenimiento('ver_acoso', $idEmpresa, 'No se pudo guardar el acoso. Intentalo de nuevo.');
  }
}

if ($accion === 'add_violencia') {
  $acciones = trim((string)($_POST['acciones'] ?? ''));
  $observaciones = trim((string)($_POST['observaciones'] ?? ''));
  $fechaAlta = trim((string)($_POST['fecha_alta'] ?? ''));
  $solicitaMujeres = (int)($_POST['solicita_mujeres'] ?? 0);

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_violencia', 0, 'Empresa inválida para registrar violencia de género.');
  }

  if ($acciones === '' || $observaciones === '' || $fechaAlta === '') {
    redirect_mantenimiento('ver_violencia', $idEmpresa, 'Completa los campos obligatorios de violencia de género.');
  }

  if (strtotime($fechaAlta) === false) {
    redirect_mantenimiento('ver_violencia', $idEmpresa, 'La fecha de alta no es válida.');
  }

  if ($solicitaMujeres < 0) {
    redirect_mantenimiento('ver_violencia', $idEmpresa, 'El valor de solicita mujeres no puede ser negativo.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%violencia%' OR LOWER(ap.nombre) LIKE '%violencia de genero%' OR LOWER(ap.nombre) LIKE '%violencia género%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_violencia', $idEmpresa, 'No existe una medida de Violencia de género asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_violencia', $idEmpresa, 'No se pudo resolver la medida de Violencia de género para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_violencia (acciones, observaciones, fecha_alta, solicita_mujeres, id_cliente_medida) VALUES (?, ?, ?, ?, ?)");
    $fechaAltaSql = date('Y-m-d', strtotime($fechaAlta));
    $stmtInsert->bind_param('sssii', $acciones, $observaciones, $fechaAltaSql, $solicitaMujeres, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_violencia', $idEmpresa, 'Violencia de género registrada correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_violencia', $e);
    redirect_mantenimiento('ver_violencia', $idEmpresa, 'No se pudo guardar la violencia de genero. Intentalo de nuevo.');
  }
}

if ($accion === 'add_retribuciones') {
  $permisos = trim((string)($_POST['permisos'] ?? ''));
  $numMujeres = (int)($_POST['num_mujeres'] ?? 0);
  $numHombres = (int)($_POST['num_hombres'] ?? 0);

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_retribuciones', 0, 'Empresa inválida para registrar retribuciones.');
  }

  if ($permisos === '') {
    redirect_mantenimiento('ver_retribuciones', $idEmpresa, 'Completa los campos obligatorios de retribuciones.');
  }

  if ($numMujeres < 0 || $numHombres < 0) {
    redirect_mantenimiento('ver_retribuciones', $idEmpresa, 'Los valores numéricos no pueden ser negativos.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%retribu%' OR LOWER(ap.nombre) LIKE '%retribucion%' OR LOWER(ap.nombre) LIKE '%retribución%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_retribuciones', $idEmpresa, 'No existe una medida de Retribuciones asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_retribuciones', $idEmpresa, 'No se pudo resolver la medida de Retribuciones para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_retribuciones (permisos, num_mujeres, num_hombres, id_cliente_medida) VALUES (?, ?, ?, ?)");
    $stmtInsert->bind_param('siii', $permisos, $numMujeres, $numHombres, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_retribuciones', $idEmpresa, 'Retribuciones registradas correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_retribuciones', $e);
    redirect_mantenimiento('ver_retribuciones', $idEmpresa, 'No se pudieron guardar las retribuciones. Intentalo de nuevo.');
  }
}

if ($accion === 'add_condiciones') {
  $nConversionesContrato = trim((string)($_POST['n_conversiones_contrato'] ?? ''));
  $nJornadasAmpliadas = trim((string)($_POST['n_jornadas_ampliadas'] ?? ''));
  $evaluacionCondicionesTrabajo = trim((string)($_POST['evaluacion_condiciones_trabajo'] ?? ''));
  $muestreo = trim((string)($_POST['muestreo'] ?? ''));
  $contratacionesRealizadas = (int)($_POST['contrataciones_realizadas'] ?? 0);

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_condiciones', 0, 'Empresa inválida para registrar condiciones de trabajo.');
  }

  if (
    $nConversionesContrato === '' || $nJornadasAmpliadas === '' || $evaluacionCondicionesTrabajo === '' ||
    $muestreo === '' || $contratacionesRealizadas < 0
  ) {
    redirect_mantenimiento('ver_condiciones', $idEmpresa, 'Completa los campos obligatorios de condiciones de trabajo.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%condicion%' OR LOWER(ap.nombre) LIKE '%trabajo%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_condiciones', $idEmpresa, 'No existe una medida de Condiciones de trabajo asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_condiciones', $idEmpresa, 'No se pudo resolver la medida de Condiciones de trabajo para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_condiciones_trabajo (n_conversiones_contrato, n_jornadas_ampliadas, evaluacion_condiciones_trabajo, muestreo, contrataciones_realizadas, id_cliente_medida) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtInsert->bind_param('ssssii', $nConversionesContrato, $nJornadasAmpliadas, $evaluacionCondicionesTrabajo, $muestreo, $contratacionesRealizadas, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_condiciones', $idEmpresa, 'Condiciones de trabajo registradas correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_condiciones', $e);
    redirect_mantenimiento('ver_condiciones', $idEmpresa, 'No se pudieron guardar las condiciones de trabajo. Intentalo de nuevo.');
  }
}

if ($accion === 'add_salud') {
  $nombre = trim((string)($_POST['nombre'] ?? ''));
  $procedencia = trim((string)($_POST['procedencia'] ?? ''));
  $observaciones = trim((string)($_POST['observaciones'] ?? ''));

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_salud', 0, 'Empresa inválida para registrar salud laboral.');
  }

  if ($nombre === '' || $procedencia === '') {
    redirect_mantenimiento('ver_salud', $idEmpresa, 'Completa los campos obligatorios de salud laboral.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%salud%' OR LOWER(ap.nombre) LIKE '%laboral%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_salud', $idEmpresa, 'No existe una medida de Salud laboral asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_salud', $idEmpresa, 'No se pudo resolver la medida de Salud laboral para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_salud (nombre, procedencia, observaciones, id_cliente_medida) VALUES (?, ?, ?, ?)");
    $stmtInsert->bind_param('sssi', $nombre, $procedencia, $observaciones, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_salud', $idEmpresa, 'Salud laboral registrada correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_salud', $e);
    redirect_mantenimiento('ver_salud', $idEmpresa, 'No se pudo guardar salud laboral. Intentalo de nuevo.');
  }
}

if ($accion === 'add_responsable_igualdad') {
  $nombre = trim((string)($_POST['nombre'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_responsable_igualdad', 0, 'Empresa inválida para registrar responsable de igualdad.');
  }

  if ($nombre === '' || $email === '') {
    redirect_mantenimiento('ver_responsable_igualdad', $idEmpresa, 'Completa los campos obligatorios de responsable de igualdad.');
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_mantenimiento('ver_responsable_igualdad', $idEmpresa, 'El email no tiene un formato válido.');
  }

  try {
    $db = db();
    $sqlBuscarArea = "
      SELECT ac.id_areas_contratadas
      FROM areas_contratadas ac
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%responsable de igualdad%' OR (LOWER(ap.nombre) LIKE '%responsable%' AND LOWER(ap.nombre) LIKE '%igualdad%'))
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY ac.id_areas_contratadas ASC
      LIMIT 1
    ";

    $stmtArea = $db->prepare($sqlBuscarArea);
    if ($esTecnico) {
      $stmtArea->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtArea->bind_param('i', $idEmpresa);
    }
    $stmtArea->execute();
    $area = $stmtArea->get_result()->fetch_assoc();
    $stmtArea->close();

    if (!$area) {
      redirect_mantenimiento('ver_responsable_igualdad', $idEmpresa, 'No existe un área de Responsable de igualdad asociada para esta empresa.');
    }

    $idAreasContratadas = (int)($area['id_areas_contratadas'] ?? 0);
    if ($idAreasContratadas <= 0) {
      redirect_mantenimiento('ver_responsable_igualdad', $idEmpresa, 'No se pudo resolver el área de Responsable de igualdad para guardar.');
    }

    $stmtInsert = $db->prepare("INSERT INTO area_responsable_igualdad (nombre, email, id_areas_contratadas) VALUES (?, ?, ?)");
    $stmtInsert->bind_param('ssi', $nombre, $email, $idAreasContratadas);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_responsable_igualdad', $idEmpresa, 'Responsable de igualdad registrado correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_responsable_igualdad', $e);
    redirect_mantenimiento('ver_responsable_igualdad', $idEmpresa, 'No se pudo guardar responsable de igualdad. Intentalo de nuevo.');
  }
}

if ($accion === 'add_seleccion') {
  $puestoActual = trim((string)($_POST['puesto_actual'] ?? ''));
  $fechaAlta = trim((string)($_POST['fecha_alta'] ?? ''));
  $responsable = trim((string)($_POST['responsable'] ?? ''));
  $responsableIntExt = trim((string)($_POST['responsable_Int_Ext'] ?? ''));
  $crgoResponsable = trim((string)($_POST['crgo_responsable'] ?? ''));
  $gnroSeleccionado = trim((string)($_POST['gnro_seleccionado'] ?? ''));
  $cMujeres = (int)($_POST['c_mujeres'] ?? 0);
  $cHombres = (int)($_POST['c_hombres'] ?? 0);
  $criterioSeleccion = trim((string)($_POST['criterio_seleccion'] ?? ''));

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_seleccion', 0, 'Empresa inválida para registrar proceso de selección y contratación.');
  }

  if (
    $puestoActual === '' || $fechaAlta === '' || $responsable === '' || $responsableIntExt === '' ||
    $crgoResponsable === '' || $gnroSeleccionado === '' || $criterioSeleccion === ''
  ) {
    redirect_mantenimiento('ver_seleccion', $idEmpresa, 'Completa los campos obligatorios de proceso de selección y contratación.');
  }

  if (strtotime($fechaAlta) === false) {
    redirect_mantenimiento('ver_seleccion', $idEmpresa, 'La fecha de alta no es válida.');
  }

  if (!in_array($crgoResponsable, ['Masculino', 'Femenino'], true)) {
    redirect_mantenimiento('ver_seleccion', $idEmpresa, 'El valor de género del cargo responsable no es válido.');
  }

  if (!in_array($gnroSeleccionado, ['Masculino', 'Femenino'], true)) {
    redirect_mantenimiento('ver_seleccion', $idEmpresa, 'El valor de género seleccionado no es válido.');
  }

  if ($cMujeres < 0 || $cHombres < 0) {
    redirect_mantenimiento('ver_seleccion', $idEmpresa, 'Los valores de candidaturas no pueden ser negativos.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%seleccion%' OR LOWER(ap.nombre) LIKE '%selección%' OR LOWER(ap.nombre) LIKE '%contratacion%' OR LOWER(ap.nombre) LIKE '%contratación%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_seleccion', $idEmpresa, 'No existe una medida de Proceso de selección y contratación asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_seleccion', $idEmpresa, 'No se pudo resolver la medida de Proceso de selección y contratación para guardar.');
    }

    $fechaAltaSql = date('Y-m-d', strtotime($fechaAlta));
    $stmtInsert = $db->prepare("INSERT INTO area_seleccion (puesto_actual, fecha_alta, responsable, responsable_Int_Ext, crgo_responsable, gnro_seleccionado, c_mujeres, c_hombres, criterio_seleccion, id_cliente_medida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtInsert->bind_param('ssssssiisi', $puestoActual, $fechaAltaSql, $responsable, $responsableIntExt, $crgoResponsable, $gnroSeleccionado, $cMujeres, $cHombres, $criterioSeleccion, $idClienteMedida);
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_seleccion', $idEmpresa, 'Proceso de selección y contratación registrado correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_seleccion', $e);
    redirect_mantenimiento('ver_seleccion', $idEmpresa, 'No se pudo guardar el proceso de seleccion y contratacion. Intentalo de nuevo.');
  }
}

if ($accion === 'add_promocion') {
  $puestoOrigen = trim((string)($_POST['puesto_origen'] ?? ''));
  $puestoDestino = trim((string)($_POST['puesto_destino'] ?? ''));
  $aumentoEconomico = (int)($_POST['aumento_economico'] ?? 0);
  $nCandidaturas = (int)($_POST['n_candidaturas'] ?? 0);
  $nHombres = (int)($_POST['n_hombres'] ?? 0);
  $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
  $responsable = trim((string)($_POST['responsable'] ?? ''));
  $cargoResponsable = trim((string)($_POST['cargo_responsable'] ?? ''));
  $generoResponsable = trim((string)($_POST['genero_responsable'] ?? ''));
  $generoPromocionado = trim((string)($_POST['genero_promocionado'] ?? ''));
  $internaExterna = trim((string)($_POST['interna_externa'] ?? ''));
  $contratoInicial = trim((string)($_POST['contrato_inicial'] ?? ''));
  $contratoFinal = trim((string)($_POST['contrato_final'] ?? ''));
  $tipoPromocion = trim((string)($_POST['tipo_promocion'] ?? ''));
  $fechaAlta = trim((string)($_POST['fecha_de_alta'] ?? ''));
  $porcentajeJornada = (int)($_POST['porcentaje_jornada'] ?? 0);
  $disfrutaConciliacion = (string)($_POST['disfruta_conciliacion'] ?? '');
  $criterio = trim((string)($_POST['criterio'] ?? ''));

  if ($idEmpresa <= 0) {
    redirect_mantenimiento('ver_promocion', 0, 'Empresa inválida para registrar promoción y ascenso profesional.');
  }

  if (
    $puestoOrigen === '' || $puestoDestino === '' || $responsable === '' || $cargoResponsable === '' ||
    $generoResponsable === '' || $generoPromocionado === '' || $tipoPromocion === '' || $fechaAlta === '' || $criterio === '' ||
    $contratoInicial === '' || $contratoFinal === ''
  ) {
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'Completa los campos obligatorios de promoción y ascenso profesional.');
  }

  if ($aumentoEconomico < 0 || $nCandidaturas < 0 || $nHombres < 0 || $nMujeres < 0 || $porcentajeJornada < 0) {
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'Los valores numéricos no pueden ser negativos.');
  }

  if (!in_array($generoResponsable, ['Masculino', 'Femenino'], true)) {
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'El género del responsable no es válido.');
  }

  if (!in_array($generoPromocionado, ['Masculino', 'Femenino'], true)) {
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'El género promocionado no es válido.');
  }

  if ($internaExterna !== '' && !in_array($internaExterna, ['Interna', 'Externa'], true)) {
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'El valor Interna/Externa no es válido.');
  }

  if ($disfrutaConciliacion !== '' && !in_array($disfrutaConciliacion, ['0', '1'], true)) {
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'El valor de conciliación no es válido.');
  }

  if (strtotime($fechaAlta) === false) {
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'La fecha de alta no es válida.');
  }

  try {
    $db = db();
    $sqlBuscarMedida = "
      SELECT cm.id_cliente_medida
      FROM cliente_medida cm
      INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
      INNER JOIN area_plan ap ON ap.id_plan = ac.id_plan
      WHERE ac.id_empresa = ?
        AND (LOWER(ap.nombre) LIKE '%promocion%' OR LOWER(ap.nombre) LIKE '%promoción%' OR LOWER(ap.nombre) LIKE '%ascenso%')
      " . ($esTecnico ? "AND EXISTS (SELECT 1 FROM usuario_empresa ue WHERE ue.id_empresa = ac.id_empresa AND ue.id_usuario = ?)" : "") . "
      ORDER BY cm.id_cliente_medida ASC
      LIMIT 1
    ";

    $stmtMedida = $db->prepare($sqlBuscarMedida);
    if ($esTecnico) {
      $stmtMedida->bind_param('ii', $idEmpresa, $currentUserId);
    } else {
      $stmtMedida->bind_param('i', $idEmpresa);
    }
    $stmtMedida->execute();
    $medida = $stmtMedida->get_result()->fetch_assoc();
    $stmtMedida->close();

    if (!$medida) {
      redirect_mantenimiento('ver_promocion', $idEmpresa, 'No existe una medida de Promoción y ascenso profesional asociada para esta empresa.');
    }

    $idClienteMedida = (int)($medida['id_cliente_medida'] ?? 0);
    if ($idClienteMedida <= 0) {
      redirect_mantenimiento('ver_promocion', $idEmpresa, 'No se pudo resolver la medida de Promoción y ascenso profesional para guardar.');
    }

    $fechaAltaSql = date('Y-m-d', strtotime($fechaAlta));
    $stmtInsert = $db->prepare("INSERT INTO area_promocion_ascenso_personal (puesto_origen, puesto_destino, aumento_economico, n_candidaturas, n_hombres, n_mujeres, responsable, cargo_responsable, genero_responsable, genero_promocionado, interna_externa, contrato_inicial, contrato_final, tipo_promocion, fecha_de_alta, porcentaje_jornada, disfruta_conciliacion, criterio, id_cliente_medida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtInsert->bind_param(
      'ssiiiissssssssssissi',
      $puestoOrigen,
      $puestoDestino,
      $aumentoEconomico,
      $nCandidaturas,
      $nHombres,
      $nMujeres,
      $responsable,
      $cargoResponsable,
      $generoResponsable,
      $generoPromocionado,
      $internaExterna,
      $contratoInicial,
      $contratoFinal,
      $tipoPromocion,
      $fechaAltaSql,
      $porcentajeJornada,
      $disfrutaConciliacion,
      $criterio,
      $idClienteMedida
    );
    $stmtInsert->execute();
    $stmtInsert->close();

    redirect_mantenimiento('ver_promocion', $idEmpresa, 'Promoción y ascenso profesional registrado correctamente.');
  } catch (Throwable $e) {
    log_internal_error_mantenimiento('mantenimiento.add_promocion', $e);
    redirect_mantenimiento('ver_promocion', $idEmpresa, 'No se pudo guardar la promocion y ascenso profesional. Intentalo de nuevo.');
  }
}

redirect_mantenimiento('ver_formacion', $idEmpresa, 'Acción no válida');
