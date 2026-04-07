<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../php/auth.php';
require_role('ADMINISTRADOR');

require __DIR__ . '/../config/config.php';

function redirect_menu_empresas(string $msg = ''): void
{
  $to = '/Igualdad/model/empresa.php?view=ver_empresas';
  if ($msg !== '') $to .= '&msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

function redirect_view_empresas(string $view, string $msg = ''): void
{
  $to = '/Igualdad/model/empresa.php?view=' . urlencode($view);
  if ($msg !== '') $to .= '&msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

$accion = (string)($_POST['accion'] ?? '');


//  CREAR EMPRESA

if ($accion === 'add_empresas') {
  $razon_social = trim((string)($_POST['razon_social'] ?? ''));
  $nif = trim((string)($_POST['nif'] ?? ''));
  $sector    = trim((string)($_POST['sector'] ?? ''));
  $domicilio_social = trim((string)($_POST['domicilio_social'] ?? ''));
  $forma_juridica = trim((string)($_POST['forma_juridica'] ?? ''));
  $ano_constitucional = trim((string)($_POST['ano_constitucional'] ?? ''));
  $responsable = trim((string)($_POST['responsable'] ?? ''));
  $cargo = trim((string)($_POST['cargo'] ?? ''));
  $contacto = trim((string)($_POST['contacto'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $cnae = trim((string)($_POST['cnae'] ?? ''));
  $convenio = trim((string)($_POST['convenio'] ?? ''));
  $personas_mujeres = trim((string)($_POST['personas_mujeres'] ?? ''));
  $personas_hombres = trim((string)($_POST['personas_hombres'] ?? ''));
  $personas_totales = trim((string)($_POST['personas_totales'] ?? ''));
  $centros_trabajo = trim((string)($_POST['centros_trabajo'] ?? ''));
  $recogida_informacion = trim((string)($_POST['recogida_informacion'] ?? ''));
  $vigencia_plan = trim((string)($_POST['vigencia_plan'] ?? ''));
  $id_usuario_raw = trim((string)($_POST['id_usuario'] ?? ''));
  $id_usuario = ($id_usuario_raw === '' || (int)$id_usuario_raw <= 0) ? null : (int)$id_usuario_raw;

  if ($razon_social === ''  || $nif === '' || $telefono === '' || $email === '' || $sector === '') {
    redirect_view_empresas('add_empresas', 'Obligatorio rellenar nombre, nif, email, telefono, sector');
  }

  if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_view_empresas('add_empresas', 'Email inválido. Debe tener formato ejemplo@dominio.com');
  }

  if ($telefono !== '' && !preg_match('/^\d{6,15}$/', $telefono)) {
    redirect_view_empresas('add_empresas', 'Teléfono inválido: solo números (6 a 15 dígitos).');
  }

  // Vacíos a NULL (igual que tú)
  $sector = ($sector === '') ? null : $sector;
  $domicilio_social = ($domicilio_social === '') ? null : $domicilio_social;
  $forma_juridica = ($forma_juridica === '') ? null : $forma_juridica;
  $ano_constitucional = ($ano_constitucional === '') ? null : $ano_constitucional;
  $cargo = ($cargo == '') ? null : $cargo;
  $contacto = ($contacto == '') ? null : $contacto;
  $cnae = ($cnae == '') ? null : $cnae;
  $convenio = ($convenio == '') ? null : $convenio;
  $personas_mujeres = ($personas_mujeres == '') ? null : (int)$personas_mujeres;
  $personas_hombres = ($personas_hombres == '') ? null : (int)$personas_hombres;
  $personas_totales = ($personas_totales == '') ? null : (int)$personas_totales;
  $centros_trabajo = ($centros_trabajo == '') ? null : (int)$centros_trabajo;
  $recogida_informacion = ($recogida_informacion == '') ? null : $recogida_informacion;
  $vigencia_plan = ($vigencia_plan == '') ? null : $vigencia_plan;

  try {
    $stmt = db()->prepare("INSERT INTO empresa(
        razon_social, nif, domicilio_social, forma_juridica, ano_constitucional,
        responsable, cargo, contacto, email, telefono,
        sector, cnae, convenio,
        personas_mujeres, personas_hombres, personas_total, centros_trabajo,
        recogida_informacion, vigencia_plan, id_usuario
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
      'ssssssssssssiiiisssi',
      $razon_social,
      $nif,
      $domicilio_social,
      $forma_juridica,
      $ano_constitucional,
      $responsable,
      $cargo,
      $contacto,
      $email,
      $telefono,
      $sector,
      $cnae,
      $convenio,
      $personas_mujeres,
      $personas_hombres,
      $personas_totales,
      $centros_trabajo,
      $recogida_informacion,
      $vigencia_plan,
      $id_usuario
    );

    $stmt->execute();
    $stmt->close();

    redirect_menu_empresas('Empresa creada');
  } catch (Throwable $e) {
    redirect_view_empresas('add_empresas', 'Error al crear empresa: ' . $e->getMessage());
  }
}

// EDITAR EMPRESAS
if ($accion === 'editar_empresas') {
  $id_empresa       = (int)($_POST['id_empresa'] ?? 0);
  $razon_social = trim((string)($_POST['razon_social'] ?? ''));
  $nif = trim((string)($_POST['nif'] ?? ''));
  $sector    = trim((string)($_POST['sector'] ?? ''));
  $domicilio_social = trim((string)($_POST['domicilio_social'] ?? ''));
  $forma_juridica = trim((string)($_POST['forma_juridica'] ?? ''));
  $ano_constitucional = trim((string)($_POST['ano_constitucional'] ?? ''));
  $responsable = trim((string)($_POST['responsable'] ?? ''));
  $cargo = trim((string)($_POST['cargo'] ?? ''));
  $contacto = trim((string)($_POST['contacto'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $sector = trim((string)($_POST['sector'] ?? ''));
  $cnae = trim((string)($_POST['cnae'] ?? ''));
  $convenio = trim((string)($_POST['convenio'] ?? ''));
  $personas_mujeres = trim((string)($_POST['personas_mujeres'] ?? ''));
  $personas_hombres = trim((string)($_POST['personas_hombres'] ?? ''));
  $personas_totales = trim((string)($_POST['personas_totales'] ?? ''));
  $centros_trabajo = trim((string)($_POST['centros_trabajo'] ?? ''));
  $recogida_informacion = trim((string)($_POST['recogida_informacion'] ?? ''));
  $vigencia_plan = trim((string)($_POST['vigencia_plan'] ?? ''));

  // id_usuario puede ser NULL (igual que en add)
  $id_usuario_raw = trim((string)($_POST['id_usuario'] ?? ''));
  $id_usuario = ($id_usuario_raw === '' || (int)$id_usuario_raw <= 0) ? null : (int)$id_usuario_raw;

  // Validaciones básicas
  if ($id_empresa <= 0 || $razon_social === '' || $nif === '') {
    redirect_view_empresas('edit_empresas', 'Faltan datos');
  }

  // email opcional: si viene relleno, validar formato
  if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirect_view_empresas('edit_empresas', 'Email inválido. Debe tener formato ejemplo@dominio.com');
  }

  if ($telefono !== '' && !preg_match('/^\d{6,15}$/', $telefono)) {
    redirect_view_empresas('edit_empresas', 'Teléfono inválido: solo números (6 a 15 dígitos).');
  }

  // Convertir vacíos a NULL (campos opcionales) -> mismo estilo que tú
  $sector = ($sector === '') ? null : $sector;
  $domicilio_social = ($domicilio_social === '') ? null : $domicilio_social;
  $forma_juridica = ($forma_juridica === '') ? null : $forma_juridica;
  $ano_constitucional = ($ano_constitucional === '') ? null : $ano_constitucional;
  $responsable = ($responsable === '') ? null : $responsable;
  $cargo = ($cargo == '') ? null : $cargo;
  $contacto = ($contacto == '') ? null : $contacto;
  $email = ($email === '') ? null : $email;
  $telefono = ($telefono === '') ? null : $telefono;
  $cnae = ($cnae == '') ? null : $cnae;
  $convenio = ($convenio == '') ? null : $convenio;

  $personas_mujeres = ($personas_mujeres == '') ? null : (int)$personas_mujeres;
  $personas_hombres = ($personas_hombres == '') ? null : (int)$personas_hombres;
  $personas_totales = ($personas_totales == '') ? null : (int)$personas_totales;
  $centros_trabajo  = ($centros_trabajo == '') ? null : (int)$centros_trabajo;

  $recogida_informacion = ($recogida_informacion == '') ? null : $recogida_informacion;
  $vigencia_plan = ($vigencia_plan == '') ? null : $vigencia_plan;

try {
  $stmt = db()->prepare("
    UPDATE empresa SET
      razon_social = ?,
      nif = ?,
      sector = ?,
      domicilio_social = ?,
      forma_juridica = ?,
      ano_constitucional = ?,
      responsable = ?,
      cargo = ?,
      contacto = ?,
      email = ?,
      telefono = ?,
      cnae = ?,
      convenio = ?,
      personas_mujeres = ?,
      personas_hombres = ?,
      personas_total = ?,
      centros_trabajo = ?,
      recogida_informacion = ?,
      vigencia_plan = ?,
      id_usuario = ?
    WHERE id_empresa = ?
  ");

  if ($stmt === false) {
    redirect_view_empresas('edit_empresas', 'Error: no se pudo preparar la consulta (db()->prepare falló)');
  }

  $stmt->bind_param(
    'sssssssssssssiiiissii',
    $razon_social,
    $nif,
    $sector,
    $domicilio_social,
    $forma_juridica,
    $ano_constitucional,
    $responsable,
    $cargo,
    $contacto,
    $email,
    $telefono,
    $cnae,
    $convenio,
    $personas_mujeres,
    $personas_hombres,
    $personas_totales,
    $centros_trabajo,
    $recogida_informacion,
    $vigencia_plan,
    $id_usuario,
    $id_empresa
  );

  $stmt->execute();
  $stmt->close();

  redirect_menu_empresas('Empresa actualizada');
} catch (Throwable $e) {
  redirect_view_empresas('edit_empresas', 'Error al actualizar empresa: ' . $e->getMessage());
}

}



// ELIMINAR EMPRESA

if ($accion === 'eliminar_empresas') {
  $id = (int)($_POST['id_empresa'] ?? 0);
  if ($id <= 0) redirect_view_empresas('delete_empresas', 'ID inválido');

  try {
    // Si tienes la tabla usuario_empresa:
    $stmt = db()->prepare("DELETE FROM usuario_empresa WHERE id_empresa = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = db()->prepare("DELETE FROM empresa WHERE id_empresa = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    redirect_menu_empresas('Empresa eliminada');
  } catch (Throwable $e) {
    redirect_view_empresas('delete_empresas', 'No se pudo eliminar: ' . $e->getMessage());
  }
}
// CREAR CONTRATO
if ($accion === 'add_contratos') {
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $tipoContrato = strtoupper(trim((string)($_POST['tipo_contrato'] ?? '')));
  $inicioPlan = trim((string)($_POST['inicio_plan'] ?? ''));
  $finPlan = trim((string)($_POST['fin_plan'] ?? ''));
  $inicioContratacion = trim((string)($_POST['inicio_contratacion'] ?? ''));
  $finContratacion = trim((string)($_POST['fin_contratacion'] ?? ''));
  $areasRaw = $_POST['areas'] ?? [];
  $medidasRaw = $_POST['medidas'] ?? [];

  if (!is_array($areasRaw)) $areasRaw = [];
  if (!is_array($medidasRaw)) $medidasRaw = [];

  $areas = array_values(array_unique(array_filter(array_map('intval', $areasRaw), static fn($n) => $n > 0)));

  $_SESSION['add_contrato_old'] = [
    'id_empresa' => $idEmpresa,
    'tipo_contrato' => $tipoContrato,
    'inicio_plan' => $inicioPlan,
    'fin_plan' => $finPlan,
    'inicio_contratacion' => $inicioContratacion,
    'fin_contratacion' => $finContratacion,
    'areas' => $areas,
    'medidas' => $medidasRaw,
  ];

  $errorContrato = static function (string $msg): void {
    $_SESSION['add_contrato_error'] = $msg;
    redirect_view_empresas('add_contratos');
  };

  if ($idEmpresa <= 0) $errorContrato('Selecciona una empresa.');
  if ($tipoContrato === '' || $inicioContratacion === '' || $finContratacion === '') {
    $errorContrato('Completa todos los campos del contrato.');
  }

  $tiposValidos = ['COMPLETO', 'MANTENIMIENTO'];
  if (!in_array($tipoContrato, $tiposValidos, true)) {
    $errorContrato('Tipo de contrato inválido.');
  }

  $usaPlanYMedidas = in_array($tipoContrato, ['COMPLETO', 'MANTENIMIENTO'], true);

  if ($usaPlanYMedidas && ($inicioPlan === '' || $finPlan === '')) {
    $errorContrato('Completa el inicio y fin del plan.');
  }

  if (strtotime($inicioContratacion) === false || strtotime($finContratacion) === false) {
    $errorContrato('Formato de fecha inválido.');
  }
  if (strtotime($inicioContratacion) > strtotime($finContratacion)) {
    $errorContrato('La fecha de inicio no puede ser mayor que la fecha de fin.');
  }

  if ($usaPlanYMedidas && $inicioPlan !== '' && $finPlan !== '') {
    if (strtotime($inicioPlan) === false || strtotime($finPlan) === false) {
      $errorContrato('Formato de fecha del plan inválido.');
    }
    if (strtotime($inicioPlan) > strtotime($finPlan)) {
      $errorContrato('La fecha de inicio del plan no puede ser mayor que la de fin.');
    }
    if (empty($areas)) {
      $errorContrato('Selecciona al menos un área del plan.');
    }
  }

  $db = db();
  try {
    $resTabla = $db->query("SHOW TABLES LIKE 'contrato_empresa'");
    if (!($resTabla instanceof mysqli_result) || $resTabla->num_rows === 0) {
      $errorContrato('La tabla contrato_empresa no existe en la base de datos.');
    }

    $stmtE = $db->prepare("SELECT id_empresa FROM empresa WHERE id_empresa = ? LIMIT 1");
    $stmtE->bind_param('i', $idEmpresa);
    $stmtE->execute();
    $empresaExiste = $stmtE->get_result()->fetch_assoc();
    $stmtE->close();
    if (!$empresaExiste) {
      $errorContrato('La empresa seleccionada no existe.');
    }

    $stmtArea = null;
    $stmtMedida = null;

    if ($usaPlanYMedidas) {
      $stmtArea = $db->prepare("SELECT id_plan FROM area_plan WHERE id_plan = ? LIMIT 1");
      foreach ($areas as $idPlanArea) {
        $stmtArea->bind_param('i', $idPlanArea);
        $stmtArea->execute();
        if (!$stmtArea->get_result()->fetch_assoc()) {
          $stmtArea->close();
          $errorContrato('Una de las áreas no existe.');
        }
      }
      $stmtArea->close();
    }

    $db->begin_transaction();

    $stmt = $db->prepare("INSERT INTO contrato_empresa (tipo_contrato, inicio_contratacion, fin_contratacion, id_empresa) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $tipoContrato, $inicioContratacion, $finContratacion, $idEmpresa);
    $stmt->execute();
    $stmt->close();

    if ($usaPlanYMedidas) {
      $stmtAreaInsert = $db->prepare("INSERT INTO areas_contratadas (inicio_plan, fin_plan, id_empresa, id_plan) VALUES (?, ?, ?, ?)");

      foreach ($areas as $idPlanArea) {
        $stmtAreaInsert->bind_param('ssii', $inicioPlan, $finPlan, $idEmpresa, $idPlanArea);
        $stmtAreaInsert->execute();
        $idAreaContratada = (int)$stmtAreaInsert->insert_id;

        $rawAreaMedidas = $medidasRaw[(string)$idPlanArea] ?? $medidasRaw[$idPlanArea] ?? [];
        if (!is_array($rawAreaMedidas)) $rawAreaMedidas = [];
        $idsMedidas = array_values(array_unique(array_filter(array_map('intval', $rawAreaMedidas), static fn($n) => $n > 0)));

        if (!empty($idsMedidas)) {
          $stmtMedida = $db->prepare("SELECT id_medida FROM medida WHERE id_medida = ? AND id_plan = ? LIMIT 1");
          $stmtInsertMedida = $db->prepare("INSERT INTO cliente_medida (id_areas_contratadas, id_medida) VALUES (?, ?)");

          foreach ($idsMedidas as $idMedida) {
            $stmtMedida->bind_param('ii', $idMedida, $idPlanArea);
            $stmtMedida->execute();
            if ($stmtMedida->get_result()->fetch_assoc()) {
              $stmtInsertMedida->bind_param('ii', $idAreaContratada, $idMedida);
              $stmtInsertMedida->execute();
            }
          }

          $stmtMedida->close();
          $stmtInsertMedida->close();
        }
      }

      $stmtAreaInsert->close();
    }

    $db->commit();

    unset($_SESSION['add_contrato_old'], $_SESSION['add_contrato_error']);
    redirect_view_empresas('ver_planes', 'Contrato guardado correctamente.');
  } catch (Throwable $e) {
    $db->rollback();
    $errorContrato('Error al guardar el contrato: ' . $e->getMessage());
  }
}

// EDITAR CONTRATO
if ($accion === 'edit_contratos') {
  $idContrato = (int)($_POST['id_contrato_empresa'] ?? 0);
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $tipoContrato = strtoupper(trim((string)($_POST['tipo_contrato'] ?? '')));
  $inicioContratacion = trim((string)($_POST['inicio_contratacion'] ?? ''));
  $finContratacion = trim((string)($_POST['fin_contratacion'] ?? ''));

  $_SESSION['edit_contrato_old'] = [
    'id_contrato_empresa' => $idContrato,
    'id_empresa' => $idEmpresa,
    'tipo_contrato' => $tipoContrato,
    'inicio_contratacion' => $inicioContratacion,
    'fin_contratacion' => $finContratacion,
  ];

  $errorEditContrato = static function (string $msg) use ($idContrato): void {
    $_SESSION['edit_contrato_error'] = $msg;
    header('Location: /Igualdad/model/empresa.php?view=edit_contratos&id_contrato=' . (int)$idContrato);
    exit;
  };

  if ($idContrato <= 0) $errorEditContrato('Contrato inválido.');
  if ($idEmpresa <= 0) $errorEditContrato('Selecciona una empresa.');
  if ($tipoContrato === '' || $inicioContratacion === '' || $finContratacion === '') {
    $errorEditContrato('Completa todos los campos del contrato.');
  }

  $tiposValidos = ['COMPLETO', 'MANTENIMIENTO'];
  if (!in_array($tipoContrato, $tiposValidos, true)) {
    $errorEditContrato('Tipo de contrato inválido.');
  }

  if (strtotime($inicioContratacion) === false || strtotime($finContratacion) === false) {
    $errorEditContrato('Formato de fecha inválido.');
  }
  if (strtotime($inicioContratacion) > strtotime($finContratacion)) {
    $errorEditContrato('La fecha de inicio no puede ser mayor que la fecha de fin.');
  }

  $db = db();
  try {
    $stmtC = $db->prepare("SELECT id_contrato_empresa FROM contrato_empresa WHERE id_contrato_empresa = ? LIMIT 1");
    $stmtC->bind_param('i', $idContrato);
    $stmtC->execute();
    if (!$stmtC->get_result()->fetch_assoc()) {
      $stmtC->close();
      $errorEditContrato('El contrato no existe.');
    }
    $stmtC->close();

    $stmtE = $db->prepare("SELECT id_empresa FROM empresa WHERE id_empresa = ? LIMIT 1");
    $stmtE->bind_param('i', $idEmpresa);
    $stmtE->execute();
    if (!$stmtE->get_result()->fetch_assoc()) {
      $stmtE->close();
      $errorEditContrato('La empresa seleccionada no existe.');
    }
    $stmtE->close();

    $stmtU = $db->prepare("UPDATE contrato_empresa SET tipo_contrato = ?, inicio_contratacion = ?, fin_contratacion = ?, id_empresa = ? WHERE id_contrato_empresa = ?");
    $stmtU->bind_param('sssii', $tipoContrato, $inicioContratacion, $finContratacion, $idEmpresa, $idContrato);
    $stmtU->execute();
    $stmtU->close();

    unset($_SESSION['edit_contrato_old'], $_SESSION['edit_contrato_error']);
    redirect_view_empresas('ver_contratos', 'Contrato actualizado correctamente.');
  } catch (Throwable $e) {
    $errorEditContrato('Error al actualizar el contrato: ' . $e->getMessage());
  }
}

// ELIMINAR CONTRATO
if ($accion === 'delete_contratos') {
  $idContrato = (int)($_POST['id_contrato_empresa'] ?? 0);
  if ($idContrato <= 0) {
    redirect_view_empresas('ver_contratos', 'Contrato inválido.');
  }

  try {
    $stmt = db()->prepare("DELETE FROM contrato_empresa WHERE id_contrato_empresa = ?");
    $stmt->bind_param('i', $idContrato);
    $stmt->execute();
    $stmt->close();

    redirect_view_empresas('ver_contratos', 'Contrato eliminado correctamente.');
  } catch (Throwable $e) {
    redirect_view_empresas('ver_contratos', 'No se pudo eliminar el contrato: ' . $e->getMessage());
  }
}

// EDITAR PLAN Y MEDIDAS DE EMPRESA
if ($accion === 'edit_plan') {
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $inicioPlan = trim((string)($_POST['inicio_plan'] ?? ''));
  $finPlan = trim((string)($_POST['fin_plan'] ?? ''));
  $areasRaw = $_POST['areas'] ?? [];
  $medidasRaw = $_POST['medidas'] ?? [];

  if (!is_array($areasRaw)) $areasRaw = [];
  if (!is_array($medidasRaw)) $medidasRaw = [];

  $areas = array_values(array_unique(array_filter(array_map('intval', $areasRaw), static fn($n) => $n > 0)));

  $_SESSION['edit_plan_old'] = [
    'id_empresa' => $idEmpresa,
    'inicio_plan' => $inicioPlan,
    'fin_plan' => $finPlan,
    'areas' => $areas,
    'medidas' => $medidasRaw,
  ];

  $errorEdit = static function (string $msg) use ($idEmpresa): void {
    $_SESSION['edit_plan_error'] = $msg;
    header('Location: /Igualdad/model/empresa.php?view=edit_plan&id_empresa=' . (int)$idEmpresa);
    exit;
  };

  if ($idEmpresa <= 0) $errorEdit('Empresa inválida.');
  if ($inicioPlan === '' || $finPlan === '') {
    $errorEdit('Completa todas las fechas.');
  }
  if (strtotime($inicioPlan) === false || strtotime($finPlan) === false) {
    $errorEdit('Formato de fecha inválido.');
  }
  if (strtotime($inicioPlan) > strtotime($finPlan)) $errorEdit('La fecha de inicio del plan no puede ser mayor que la de fin.');
  if (empty($areas)) $errorEdit('Selecciona al menos un área.');

  $db = db();

  try {
    $stmtE = $db->prepare("SELECT id_empresa FROM empresa WHERE id_empresa = ? LIMIT 1");
    $stmtE->bind_param('i', $idEmpresa);
    $stmtE->execute();
    if (!$stmtE->get_result()->fetch_assoc()) {
      $stmtE->close();
      $errorEdit('La empresa no existe.');
    }
    $stmtE->close();

    $stmtArea = $db->prepare("SELECT id_plan FROM area_plan WHERE id_plan = ? LIMIT 1");
    foreach ($areas as $idPlanArea) {
      $stmtArea->bind_param('i', $idPlanArea);
      $stmtArea->execute();
      if (!$stmtArea->get_result()->fetch_assoc()) {
        $stmtArea->close();
        $errorEdit('Una de las áreas no existe.');
      }
    }
    $stmtArea->close();

    $db->begin_transaction();

    $stmtDelCm = $db->prepare("DELETE cm FROM cliente_medida cm INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
    $stmtDelCm->bind_param('i', $idEmpresa);
    $stmtDelCm->execute();
    $stmtDelCm->close();

    $stmtDelPc = $db->prepare("DELETE FROM areas_contratadas WHERE id_empresa = ?");
    $stmtDelPc->bind_param('i', $idEmpresa);
    $stmtDelPc->execute();
    $stmtDelPc->close();

    foreach ($areas as $idPlanArea) {
      $stmtP = $db->prepare("INSERT INTO areas_contratadas (inicio_plan, fin_plan, id_empresa, id_plan) VALUES (?, ?, ?, ?)");
      $stmtP->bind_param('ssii', $inicioPlan, $finPlan, $idEmpresa, $idPlanArea);
      $stmtP->execute();
      $idPlanCliente = (int)$stmtP->insert_id;
      $stmtP->close();

      $rawAreaMedidas = $medidasRaw[(string)$idPlanArea] ?? $medidasRaw[$idPlanArea] ?? [];
      if (!is_array($rawAreaMedidas)) $rawAreaMedidas = [];
      $idsMedidas = array_values(array_unique(array_filter(array_map('intval', $rawAreaMedidas), static fn($n) => $n > 0)));

      if (!empty($idsMedidas)) {
        $stmtV = $db->prepare("SELECT id_medida FROM medida WHERE id_medida = ? AND id_plan = ? LIMIT 1");
        $stmtI = $db->prepare("INSERT INTO cliente_medida (id_areas_contratadas, id_medida) VALUES (?, ?)");
        foreach ($idsMedidas as $idMedida) {
          $stmtV->bind_param('ii', $idMedida, $idPlanArea);
          $stmtV->execute();
          if ($stmtV->get_result()->fetch_assoc()) {
            $stmtI->bind_param('ii', $idPlanCliente, $idMedida);
            $stmtI->execute();
          }
        }
        $stmtV->close();
        $stmtI->close();
      }
    }

    $db->commit();
    unset($_SESSION['edit_plan_old'], $_SESSION['edit_plan_error']);
    redirect_view_empresas('ver_planes', 'Plan actualizado correctamente.');
  } catch (Throwable $e) {
    $db->rollback();
    $errorEdit('Error al actualizar el plan: ' . $e->getMessage());
  }
}

// ELIMINAR PLAN DE EMPRESA
if ($accion === 'delete_plan_empresa') {
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  if ($idEmpresa <= 0) {
    redirect_view_empresas('ver_planes', 'Empresa inválida.');
  }

  $db = db();
  try {
    $db->begin_transaction();

    $stmtDelCm = $db->prepare("DELETE cm FROM cliente_medida cm INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
    $stmtDelCm->bind_param('i', $idEmpresa);
    $stmtDelCm->execute();
    $stmtDelCm->close();

    $stmtDelPc = $db->prepare("DELETE FROM areas_contratadas WHERE id_empresa = ?");
    $stmtDelPc->bind_param('i', $idEmpresa);
    $stmtDelPc->execute();
    $stmtDelPc->close();

    $db->commit();
    redirect_view_empresas('ver_planes', 'Plan eliminado correctamente.');
  } catch (Throwable $e) {
    $db->rollback();
    redirect_view_empresas('ver_planes', 'No se pudo eliminar el plan: ' . $e->getMessage());
  }
}
// Si llega aquí, acción no soportada
redirect_menu_empresas('Acción no válida');