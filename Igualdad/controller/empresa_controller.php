<?php

declare(strict_types=1);


require __DIR__ . '/../php/auth.php';

require_once __DIR__ . '/../php/helpers.php';

require_login();

require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../php/mails.php';

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdministrador = ($rol === 'ADMINISTRADOR');
$esTecnico = ($rol === 'TECNICO');
$accion = (string)($_POST['accion'] ?? '');

if (!$esAdministrador && !$esTecnico) {
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

  if ($ok) {
    return true;
  }

  $stmt = $db->prepare("SELECT 1 FROM contrato_empresa WHERE id_empresa = ? AND id_usuario = ? LIMIT 1");
  $stmt->bind_param('ii', $idEmpresa, $idUsuario);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $ok;
}

if ($esTecnico && $accion !== 'editar_empresas' && $accion !== 'add_contratos' && $accion !== 'edit_contratos' && $accion !== 'delete_contratos' && $accion !== 'edit_plan' && $accion !== 'delete_plan_empresa' && $accion !== 'enviar_correo_empresa') {
  http_response_code(403);
  exit('Acceso denegado');
}

function redirect_menu_empresas(string $msg = ''): void
{
  $to = app_path('/model/empresa.php?view=ver_empresas');
  if ($msg !== '') $to .= '&msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

function redirect_view_empresas(string $view, string $msg = '', int $idEmpresa = 0): void
{
  $to = app_path('/model/empresa.php?view=') . urlencode($view);
  if ($idEmpresa <= 0) {
    $idEmpresa = (int)($_POST['id_empresa'] ?? $_GET['id_empresa'] ?? 0);
  }
  if ($idEmpresa > 0) {
    $to .= '&id_empresa=' . $idEmpresa;
  }
  $tipoContrato = trim((string)($_POST['tipo_contrato_context'] ?? $_POST['tipo_contrato'] ?? $_GET['tipo_contrato'] ?? ''));
  if ($tipoContrato !== '') {
    $to .= '&tipo_contrato=' . urlencode($tipoContrato);
  }
  $from = trim((string)($_POST['from'] ?? $_GET['from'] ?? ''));
  if ($from !== '') {
    $to .= '&from=' . urlencode($from);
  }
  if ($msg !== '') $to .= '&msg=' . urlencode($msg);
  header("Location: $to");
  exit;
}

function log_internal_error_empresa(string $context, Throwable $e): void
{
  error_log(sprintf(
    '[%s] %s in %s:%d',
    $context,
    $e->getMessage(),
    $e->getFile(),
    $e->getLine()
  ));
}

function asegurar_fk_archivos_cliente_medida_no_cascade(): void
{
  static $aplicado = false;
  if ($aplicado) {
    return;
  }

  $aplicado = true;
  $db = db();

  $stmt = $db->prepare(
    "SELECT DELETE_RULE
     FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'archivos'
       AND CONSTRAINT_NAME = 'fk_archivo_cliente_medida'
     LIMIT 1"
  );

  if (!$stmt) {
    return;
  }

  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $deleteRule = strtoupper(trim((string)($row['DELETE_RULE'] ?? '')));
  if ($deleteRule === 'SET NULL') {
    return;
  }

  @$db->query('ALTER TABLE archivos DROP FOREIGN KEY fk_archivo_cliente_medida');
  @$db->query(
    'ALTER TABLE archivos
     ADD CONSTRAINT fk_archivo_cliente_medida
     FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida)
     ON DELETE SET NULL'
  );
}

function usuario_es_tecnico(int $idUsuario): bool
{
  if ($idUsuario <= 0) {
    return false;
  }

  $stmt = db()->prepare("\n    SELECT COALESCE(r.nombre, '') AS rol_nombre\n    FROM usuario u\n    LEFT JOIN rol r ON r.id = u.rol_id\n    WHERE u.id_usuario = ?\n    LIMIT 1\n  ");
  $stmt->bind_param('i', $idUsuario);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $rolNombre = strtoupper(trim((string)($row['rol_nombre'] ?? '')));
  return in_array($rolNombre, ['TECNICO', 'TÉCNICO'], true);
}

function limpiar_ids_unicos(array $ids): array
{
  $result = [];
  foreach ($ids as $idRaw) {
    $id = (int)$idRaw;
    if ($id > 0) {
      $result[$id] = $id;
    }
  }
  return array_values($result);
}

function obtener_tecnicos_validos(array $idsTecnicos): array
{
  $ids = limpiar_ids_unicos($idsTecnicos);
  $validos = [];

  foreach ($ids as $idUsuario) {
    if (usuario_es_tecnico($idUsuario)) {
      $validos[] = $idUsuario;
    }
  }

  return $validos;
}

function tabla_cnae_disponible(): bool
{
  static $checked = false;
  static $exists = false;

  if ($checked) {
    return $exists;
  }

  $checked = true;
  $res = db()->query("SHOW TABLES LIKE 'cnae'");
  $exists = ($res instanceof mysqli_result && $res->num_rows > 0);

  return $exists;
}

function parsear_cnaes(string $raw): array
{
  $parts = preg_split('/[\r\n,;]+/u', $raw);
  if (!is_array($parts)) {
    return [];
  }

  $result = [];
  foreach ($parts as $item) {
    $value = trim((string)$item);
    if ($value === '') {
      continue;
    }

    $key = function_exists('mb_strtolower')
      ? mb_strtolower($value, 'UTF-8')
      : strtolower($value);

    if (!isset($result[$key])) {
      $result[$key] = $value;
    }
  }

  return array_values($result);
}

function guardar_cnaes_empresa(int $idEmpresa, array $cnaes): void
{
  if ($idEmpresa <= 0 || !tabla_cnae_disponible()) {
    return;
  }

  $db = db();

  $stmtDelete = $db->prepare('DELETE FROM cnae WHERE id_empresa = ?');
  if ($stmtDelete) {
    $stmtDelete->bind_param('i', $idEmpresa);
    $stmtDelete->execute();
    $stmtDelete->close();
  }

  if (empty($cnaes)) {
    return;
  }

  $stmtInsert = $db->prepare('INSERT INTO cnae (nombre, id_empresa) VALUES (?, ?)');
  if (!$stmtInsert) {
    return;
  }

  foreach ($cnaes as $cnaeNombre) {
    $nombre = trim((string)$cnaeNombre);
    if ($nombre === '') {
      continue;
    }
    $stmtInsert->bind_param('si', $nombre, $idEmpresa);
    $stmtInsert->execute();
  }

  $stmtInsert->close();
}

function sincronizar_tecnico_empresa(int $idEmpresa, array $idsTecnicos): void
{
  if ($idEmpresa <= 0) {
    return;
  }

  $db = db();

  $idsTecnicosPrevios = [];
  $stmtPrev = $db->prepare("\n    SELECT ue.id_usuario\n    FROM usuario_empresa ue\n    INNER JOIN usuario u ON u.id_usuario = ue.id_usuario\n    LEFT JOIN rol r ON r.id = u.rol_id\n    WHERE ue.id_empresa = ? AND UPPER(COALESCE(r.nombre, '')) IN ('TECNICO', 'TÉCNICO')\n  ");
  if ($stmtPrev) {
    $stmtPrev->bind_param('i', $idEmpresa);
    $stmtPrev->execute();
    $resPrev = $stmtPrev->get_result();
    while ($rowPrev = $resPrev->fetch_assoc()) {
      $idPrev = (int)($rowPrev['id_usuario'] ?? 0);
      if ($idPrev > 0) {
        $idsTecnicosPrevios[$idPrev] = $idPrev;
      }
    }
    $stmtPrev->close();
  }

  $stmtDelete = $db->prepare("\n    DELETE ue\n    FROM usuario_empresa ue\n    INNER JOIN usuario u ON u.id_usuario = ue.id_usuario\n    LEFT JOIN rol r ON r.id = u.rol_id\n    WHERE ue.id_empresa = ? AND UPPER(COALESCE(r.nombre, '')) IN ('TECNICO', 'TÉCNICO')\n  ");
  $stmtDelete->bind_param('i', $idEmpresa);
  $stmtDelete->execute();
  $stmtDelete->close();

  $idsTecnicos = limpiar_ids_unicos($idsTecnicos);
  if (!empty($idsTecnicos)) {
    $stmtInsert = $db->prepare("INSERT IGNORE INTO usuario_empresa (id_usuario, id_empresa) VALUES (?, ?)");
    foreach ($idsTecnicos as $idTecnico) {
      $stmtInsert->bind_param('ii', $idTecnico, $idEmpresa);
      $stmtInsert->execute();
    }
    $stmtInsert->close();
  }

  $idsNuevos = array_values(array_diff($idsTecnicos, array_values($idsTecnicosPrevios)));
  if (empty($idsNuevos)) {
    return;
  }

  $empresaServicio = correo_obtener_empresa_y_servicio($db, $idEmpresa);
  if ($empresaServicio === null) {
    return;
  }

  $empresaNombre = (string)($empresaServicio['empresa'] ?? '');
  $servicioNombre = (string)($empresaServicio['servicio'] ?? 'Pendiente de asignar');
  $urlEmpresa = app_path('/model/empresa.php?view=ver_empresa&id_empresa=' . $idEmpresa . '&from=tecnico');

  $stmtTecnico = $db->prepare("SELECT id_usuario, nombre_usuario, email FROM usuario WHERE id_usuario = ? LIMIT 1");
  if (!$stmtTecnico) {
    return;
  }

  foreach ($idsNuevos as $idTecnicoNuevo) {
    $idTecnicoNuevo = (int)$idTecnicoNuevo;
    if ($idTecnicoNuevo <= 0) {
      continue;
    }

    $stmtTecnico->bind_param('i', $idTecnicoNuevo);
    $stmtTecnico->execute();
    $rowTecnico = $stmtTecnico->get_result()->fetch_assoc() ?: null;

    if (!$rowTecnico) {
      continue;
    }

    $emailTecnico = trim((string)($rowTecnico['email'] ?? ''));
    $nombreTecnico = trim((string)($rowTecnico['nombre_usuario'] ?? ''));
    if ($emailTecnico === '' || filter_var($emailTecnico, FILTER_VALIDATE_EMAIL) === false) {
      continue;
    }

    try {
      correo_enviar_nueva_empresa_asignada(
        $emailTecnico,
        $nombreTecnico,
        $empresaNombre,
        $servicioNombre,
        $urlEmpresa
      );
    } catch (Throwable $e) {
      error_log('Error enviando correo de nueva empresa asignada: ' . $e->getMessage());
    }
  }

  $stmtTecnico->close();
}

function guardar_archivos_empresa(int $idEmpresa, $filesInput): array
{
  $result = ['ok' => 0, 'errores' => []];

  if ($idEmpresa <= 0 || !is_array($filesInput) || !isset($filesInput['name'])) {
    return $result;
  }

  if (!is_array($filesInput['name'])) {
    $filesInput = [
      'name' => [$filesInput['name']],
      'tmp_name' => [$filesInput['tmp_name']],
      'error' => [$filesInput['error']],
      'size' => [$filesInput['size']],
      'type' => [$filesInput['type'] ?? ''],
    ];
  }

  $baseUploads = __DIR__ . '/../uploads';
  if (!is_dir($baseUploads) && !mkdir($baseUploads, 0775, true)) {
    $result['errores'][] = 'No se pudo crear la carpeta uploads.';
    return $result;
  }

  $allowedExtensions = ['xlsx', 'xls', 'docx', 'doc', 'pdf'];
  $db = db();
  $totalFiles = count($filesInput['name']);

  for ($i = 0; $i < $totalFiles; $i++) {
    $filename = trim((string)($filesInput['name'][$i] ?? ''));
    $tmpFile = (string)($filesInput['tmp_name'][$i] ?? '');
    $uploadErr = (int)($filesInput['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    $fileSize = (int)($filesInput['size'][$i] ?? 0);

    if ($filename === '' || $uploadErr === UPLOAD_ERR_NO_FILE) {
      continue;
    }

    if ($uploadErr !== UPLOAD_ERR_OK) {
      $result['errores'][] = $filename . ': error de subida (' . $uploadErr . ').';
      continue;
    }

    if ($tmpFile === '' || !is_uploaded_file($tmpFile)) {
      $result['errores'][] = $filename . ': archivo temporal inválido.';
      continue;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpFile);
    finfo_close($finfo);

    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    if (!in_array($ext, $allowedExtensions, true) || !in_array($mimeType, $allowedMimes, true)) {
      $result['errores'][] = $filename . ': extensión o contenido no permitido.';
      continue;
    }

    if ($fileSize > 52428800) {
      $result['errores'][] = $filename . ': supera el límite de 50MB.';
      continue;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpFile);
    finfo_close($finfo);

    $allowedMimes = [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-office',
      'application/octet-stream' // A veces detectado para archivos de office antiguos
    ];

    if (!in_array($mime, $allowedMimes, true)) {
      $result['errores'][] = $filename . ': el contenido del archivo no es válido o no está permitido.';
      continue;
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $newPath = $baseUploads . '/' . $newName;

    if (!move_uploaded_file($tmpFile, $newPath)) {
      $result['errores'][] = $filename . ': no se pudo guardar en disco.';
      continue;
    }

    $sha = hash_file('sha256', $newPath) ?: '';
    $rutaRelativa = 'uploads/' . $newName;

    $stmt = $db->prepare("\n      INSERT INTO archivos\n      (tipo, asunto, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_cliente_medida, id_empresa)\n      VALUES ('DOCUMENTO EMPRESA', NULL, ?, ?, ?, ?, ?, ?, NULL, ?)\n    ");

    if (!$stmt) {
      $result['errores'][] = $filename . ': error al registrar en base de datos.';
      @unlink($newPath);
      continue;
    }

    $stmt->bind_param('sssissi', $filename, $newName, $rutaRelativa, $fileSize, $mime, $sha, $idEmpresa);
    $stmt->execute();
    $stmt->close();
    $result['ok']++;
  }

  return $result;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
  redirect_menu_empresas('La sesion ha expirado. Recarga la pagina e intentalo de nuevo.');
}

if ($accion === 'enviar_correo_empresa') {
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $asunto = trim((string)($_POST['asunto_correo'] ?? ''));
  $cuerpo = trim((string)($_POST['cuerpo_correo'] ?? ''));

  if ($idEmpresa <= 0) {
    redirect_view_empresas('ver_empresa', 'Empresa invalida.');
  }

  if ($esTecnico && !tecnico_tiene_empresa($idEmpresa, $currentUserId)) {
    redirect_view_empresas('ver_empresas', 'No tienes permiso para esta empresa.');
  }

  if ($asunto === '' || $cuerpo === '') {
    redirect_view_empresas('ver_empresa', 'Debes indicar asunto y cuerpo del correo.', $idEmpresa);
  }

  $asuntoLength = function_exists('mb_strlen') ? mb_strlen($asunto, 'UTF-8') : strlen($asunto);
  if ($asuntoLength > 180) {
    redirect_view_empresas('ver_empresa', 'El asunto es demasiado largo (maximo 180 caracteres).', $idEmpresa);
  }

  $stmtEmpresaCorreo = db()->prepare('SELECT razon_social, email FROM empresa WHERE id_empresa = ? LIMIT 1');
  if (!$stmtEmpresaCorreo) {
    redirect_view_empresas('ver_empresa', 'No se pudo preparar la consulta de empresa.', $idEmpresa);
  }

  $stmtEmpresaCorreo->bind_param('i', $idEmpresa);
  $stmtEmpresaCorreo->execute();
  $empresaCorreo = $stmtEmpresaCorreo->get_result()->fetch_assoc();
  $stmtEmpresaCorreo->close();

  if (!$empresaCorreo) {
    redirect_view_empresas('ver_empresa', 'No se encontro la empresa seleccionada.', $idEmpresa);
  }

  $emailDestino = trim((string)($empresaCorreo['email'] ?? ''));
  $nombreEmpresa = trim((string)($empresaCorreo['razon_social'] ?? 'Empresa'));

  if ($emailDestino === '' || filter_var($emailDestino, FILTER_VALIDATE_EMAIL) === false) {
    redirect_view_empresas('ver_empresa', 'La empresa no tiene un email valido para envio.', $idEmpresa);
  }

  try {
    $tecnicoNombre = trim((string)($_SESSION['user']['nombre_usuario'] ?? 'Tecnico'));
    $tecnicoEmail = trim((string)($_SESSION['user']['email'] ?? ''));

    if ($tecnicoEmail === '' && $currentUserId > 0) {
      $stmtT = db()->prepare('SELECT email FROM usuario WHERE id_usuario = ? LIMIT 1');
      if ($stmtT) {
        $stmtT->bind_param('i', $currentUserId);
        $stmtT->execute();
        $rowT = $stmtT->get_result()->fetch_assoc();
        $tecnicoEmail = trim((string)($rowT['email'] ?? ''));
        $stmtT->close();
      }
    }

    // Obtener la firma del técnico desde la BD (campo firmacorreo)
    $tecnicoFirma = '';
    if ($currentUserId > 0) {
      $stmtF = db()->prepare('SELECT firmacorreo FROM usuario WHERE id_usuario = ? LIMIT 1');
      if ($stmtF) {
        $stmtF->bind_param('i', $currentUserId);
        $stmtF->execute();
        $rowF = $stmtF->get_result()->fetch_assoc() ?: null;
        $stmtF->close();
        if ($rowF !== null) {
          $tecnicoFirma = trim((string)($rowF['firmacorreo'] ?? ''));
        }
      }
    }

    correo_enviar_contacto_tecnico_empresa(
      $emailDestino,
      $nombreEmpresa,
      $tecnicoNombre,
      $tecnicoEmail,
      $asunto,
      $cuerpo,
      $tecnicoFirma
    );

    redirect_view_empresas('ver_empresa', 'Correo enviado correctamente.', $idEmpresa);
  } catch (Throwable $e) {
    log_internal_error_empresa('empresa.enviar_correo', $e);
    redirect_view_empresas('ver_empresa', 'No se pudo enviar el correo. Intentalo de nuevo.', $idEmpresa);
  }
}

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
  $cnaeRaw = trim((string)($_POST['cnae'] ?? ''));
  $cnaes = parsear_cnaes($cnaeRaw);
  $convenio = trim((string)($_POST['convenio'] ?? ''));
  $personas_mujeres = trim((string)($_POST['personas_mujeres'] ?? ''));
  $personas_hombres = trim((string)($_POST['personas_hombres'] ?? ''));
  $personas_totales = trim((string)($_POST['personas_totales'] ?? ''));
  $centros_trabajo = trim((string)($_POST['centros_trabajo'] ?? ''));
  $recogida_informacion = trim((string)($_POST['recogida_informacion'] ?? ''));
  $vigencia_plan = trim((string)($_POST['vigencia_plan'] ?? ''));
  $tecnicosRaw = $_POST['tecnicos'] ?? [];
  if (!is_array($tecnicosRaw)) {
    $tecnicosRaw = [];
  }

  $tecnicosSeleccionados = obtener_tecnicos_validos($tecnicosRaw);
  if (!empty($tecnicosRaw) && empty($tecnicosSeleccionados)) {
    redirect_view_empresas('add_empresas', 'Los tecnicos seleccionados no son válidos.');
  }

  $id_usuario = $tecnicosSeleccionados[0] ?? null;

  if ($razon_social === '' || $nif === '' || $telefono === '' || $email === '' || $sector === '') {
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
  $cargo = ($cargo === '') ? null : $cargo;
  $contacto = ($contacto === '') ? null : $contacto;
  $convenio = ($convenio === '') ? null : $convenio;
  $personas_mujeres = ($personas_mujeres === '') ? null : (int)$personas_mujeres;
  $personas_hombres = ($personas_hombres === '') ? null : (int)$personas_hombres;
  $personas_totales = ($personas_totales === '') ? null : (int)$personas_totales;
  $centros_trabajo = ($centros_trabajo === '') ? null : (int)$centros_trabajo;
  $recogida_informacion = ($recogida_informacion === '') ? null : $recogida_informacion;
  $vigencia_plan = ($vigencia_plan === '') ? null : $vigencia_plan;

  try {
    $stmt = db()->prepare("INSERT INTO empresa(
        razon_social, nif, domicilio_social, forma_juridica, ano_constitucional,
        responsable, cargo, contacto, email, telefono,
        sector, convenio,
        personas_mujeres, personas_hombres, personas_total, centros_trabajo,
        recogida_informacion, vigencia_plan, id_usuario
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
      'ssssssssssssiiiissi',
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
    $idEmpresaNueva = (int)$stmt->insert_id;
    $stmt->close();

    guardar_cnaes_empresa($idEmpresaNueva, $cnaes);

    sincronizar_tecnico_empresa($idEmpresaNueva, $tecnicosSeleccionados);

    $resultadoSubida = guardar_archivos_empresa($idEmpresaNueva, $_FILES['archivos_empresa'] ?? null);
    $mensajeFinal = 'Empresa creada';
    if ($resultadoSubida['ok'] > 0) {
      $mensajeFinal .= ' y ' . (int)$resultadoSubida['ok'] . ' archivo(s) guardado(s)';
    }
    if (!empty($resultadoSubida['errores'])) {
      $mensajeFinal .= '. Errores de archivos: ' . implode('; ', $resultadoSubida['errores']);
    }

    $to = app_path('/model/empresa.php?view=add_contratos&id_empresa=') . $idEmpresaNueva;
    header('Location: ' . $to);
    exit;
  } catch (Throwable $e) {
    log_internal_error_empresa('empresa.crear', $e);
    redirect_view_empresas('add_empresas', 'No se pudo crear la empresa. Intentalo de nuevo.');
  }
}

// EDITAR EMPRESAS
if ($accion === 'editar_empresas') {
  $id_empresa       = (int)($_POST['id_empresa'] ?? 0);
  $currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);

  if ($esTecnico && !tecnico_tiene_empresa($id_empresa, $currentUserId)) {
    redirect_view_empresas('ver_empresas', 'No tienes permiso para editar esta empresa');
  }

  $id_usuario_actual_empresa = null;
  if ($id_empresa > 0) {
    $stmtEmpresaActual = db()->prepare("SELECT id_usuario FROM empresa WHERE id_empresa = ? LIMIT 1");
    $stmtEmpresaActual->bind_param('i', $id_empresa);
    $stmtEmpresaActual->execute();
    $rowEmpresaActual = $stmtEmpresaActual->get_result()->fetch_assoc();
    $stmtEmpresaActual->close();
    $id_usuario_actual_empresa = isset($rowEmpresaActual['id_usuario']) ? (int)$rowEmpresaActual['id_usuario'] : null;
  }

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
  $cnaeRaw = trim((string)($_POST['cnae'] ?? ''));
  $cnaes = parsear_cnaes($cnaeRaw);
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
  $tecnicosSeleccionados = [];

  if ($esTecnico) {
    $id_usuario = $id_usuario_actual_empresa;
  } else {
    $tecnicosRaw = $_POST['tecnicos'] ?? [];
    if (!is_array($tecnicosRaw)) {
      $tecnicosRaw = [];
    }

    $tecnicosSeleccionados = obtener_tecnicos_validos($tecnicosRaw);
    if (!empty($tecnicosRaw) && empty($tecnicosSeleccionados)) {
      redirect_view_empresas('edit_empresas', 'Los tecnicos seleccionados no son válidos.');
    }

    $id_usuario = $tecnicosSeleccionados[0] ?? null;
  }

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
  $cargo = ($cargo === '') ? null : $cargo;
  $contacto = ($contacto === '') ? null : $contacto;
  $email = ($email === '') ? null : $email;
  $telefono = ($telefono === '') ? null : $telefono;
  $convenio = ($convenio === '') ? null : $convenio;

  $personas_mujeres = ($personas_mujeres === '') ? null : (int)$personas_mujeres;
  $personas_hombres = ($personas_hombres === '') ? null : (int)$personas_hombres;
  $personas_totales = ($personas_totales === '') ? null : (int)$personas_totales;
  $centros_trabajo  = ($centros_trabajo === '') ? null : (int)$centros_trabajo;

  $recogida_informacion = ($recogida_informacion === '') ? null : $recogida_informacion;
  $vigencia_plan = ($vigencia_plan === '') ? null : $vigencia_plan;

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
      'ssssssssssssiiiissii',
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

    guardar_cnaes_empresa($id_empresa, $cnaes);

    if (!$esTecnico) {
      sincronizar_tecnico_empresa($id_empresa, $tecnicosSeleccionados);
    }

    $resultadoSubida = guardar_archivos_empresa($id_empresa, $_FILES['archivos_empresa'] ?? null);
    $mensajeFinal = 'Empresa actualizada';
    if ($resultadoSubida['ok'] > 0) {
      $mensajeFinal .= ' y ' . (int)$resultadoSubida['ok'] . ' archivo(s) guardado(s)';
    }
    if (!empty($resultadoSubida['errores'])) {
      $mensajeFinal .= '. Errores de archivos: ' . implode('; ', $resultadoSubida['errores']);
    }

    redirect_menu_empresas($mensajeFinal);
  } catch (Throwable $e) {
    log_internal_error_empresa('empresa.editar', $e);
    redirect_view_empresas('edit_empresas', 'No se pudo actualizar la empresa. Intentalo de nuevo.');
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
    log_internal_error_empresa('empresa.eliminar', $e);
    redirect_view_empresas('delete_empresas', 'No se pudo eliminar la empresa. Intentalo de nuevo.');
  }
}
// CREAR CONTRATO
if ($accion === 'add_contratos') {
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idUsuarioServicio = $esTecnico
    ? $currentUserId
    : (int)($_POST['id_usuario'] ?? 0);

  if ($esTecnico && !tecnico_tiene_empresa($idEmpresa, $currentUserId)) {
    redirect_view_empresas('ver_contratos', 'No tienes permiso para usar esta empresa');
  }

  $tipoContrato = strtoupper(trim((string)($_POST['tipo_contrato'] ?? '')));
  $inicioPlan = trim((string)($_POST['inicio_plan'] ?? ''));
  $finPlan = trim((string)($_POST['fin_plan'] ?? ''));
  $inicioContratacion = trim((string)($_POST['inicio_contratacion'] ?? ''));
  $finContratacion = trim((string)($_POST['fin_contratacion'] ?? ''));
  $areasRaw = $_POST['areas'] ?? [];
  $medidasRaw = $_POST['medidas'] ?? [];
  $medidasPersonalizadasRaw = $_POST['medidas_personalizadas'] ?? [];

  if (!is_array($areasRaw)) $areasRaw = [];
  if (!is_array($medidasRaw)) $medidasRaw = [];
  if (!is_array($medidasPersonalizadasRaw)) $medidasPersonalizadasRaw = [];

  $areas = array_values(array_unique(array_filter(array_map('intval', $areasRaw), static fn($n) => $n > 0)));

  $_SESSION['add_contrato_old'] = [
    'id_empresa' => $idEmpresa,
    'id_usuario' => $idUsuarioServicio,
    'tipo_contrato' => $tipoContrato,
    'inicio_plan' => $inicioPlan,
    'fin_plan' => $finPlan,
    'inicio_contratacion' => $inicioContratacion,
    'fin_contratacion' => $finContratacion,
    'areas' => $areas,
    'medidas' => $medidasRaw,
    'medidas_personalizadas' => $medidasPersonalizadasRaw,
  ];

  $errorContrato = static function (string $msg): void {
    $_SESSION['add_contrato_error'] = $msg;
    redirect_view_empresas('add_contratos');
  };

  if ($idEmpresa <= 0) $errorContrato('Selecciona una empresa.');
  if ($idUsuarioServicio <= 0) $errorContrato('Selecciona un tecnico para el servicio.');
  if ($tipoContrato === '' || $inicioContratacion === '' || $finContratacion === '') {
    $errorContrato('Completa todos los campos del contrato.');
  }

  $tiposValidos = ['PLAN IGUALDAD', 'MANTENIMIENTO'];
  if (!in_array($tipoContrato, $tiposValidos, true)) {
    $errorContrato('Tipo de contrato inválido.');
  }

  $usaMantenimiento = ($tipoContrato === 'MANTENIMIENTO');
  $usaPlanYMedidas = $usaMantenimiento || ($tipoContrato === 'PLAN IGUALDAD');

  if (strtotime($inicioContratacion) === false || strtotime($finContratacion) === false) {
    $errorContrato('Formato de fecha inválido.');
  }
  if (strtotime($inicioContratacion) > strtotime($finContratacion)) {
    $errorContrato('La fecha de inicio no puede ser mayor que la fecha de fin.');
  }

  if ($usaMantenimiento) {
    if ($inicioPlan === '' || $finPlan === '') {
      $errorContrato('Completa la fecha de inicio y fin de vigencia para mantenimiento.');
    }
    if (strtotime($inicioPlan) === false || strtotime($finPlan) === false) {
      $errorContrato('Formato de fecha de vigencia inválido.');
    }
    if (strtotime($inicioPlan) > strtotime($finPlan)) {
      $errorContrato('La fecha de inicio de vigencia no puede ser mayor que la de fin.');
    }
    if (empty($areas)) {
      $errorContrato('Selecciona al menos un área para mantenimiento.');
    }
  } elseif ($usaPlanYMedidas && ($inicioPlan !== '' || $finPlan !== '')) {
    // Plan Igualdad: fechas opcionales, pero si se rellenan hay que validarlas
    if ($inicioPlan === '' || $finPlan === '') {
      $errorContrato('Si indicas una fecha de vigencia, debes completar tanto el inicio como el fin.');
    }
    if (strtotime($inicioPlan) === false || strtotime($finPlan) === false) {
      $errorContrato('Formato de fecha de vigencia inválido.');
    }
    if (strtotime($inicioPlan) > strtotime($finPlan)) {
      $errorContrato('La fecha de inicio de vigencia no puede ser mayor que la de fin.');
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

    if (!$esTecnico) {
      $stmtTecnico = $db->prepare("\n        SELECT 1\n        FROM usuario u\n        INNER JOIN rol r ON r.id = u.rol_id\n        WHERE u.id_usuario = ?\n          AND UPPER(TRIM(COALESCE(r.nombre, ''))) IN ('TECNICO', 'TÉCNICO')\n        LIMIT 1\n      ");
      $stmtTecnico->bind_param('i', $idUsuarioServicio);
      $stmtTecnico->execute();
      $tecnicoValido = (bool)$stmtTecnico->get_result()->fetch_assoc();
      $stmtTecnico->close();
      if (!$tecnicoValido) {
        $errorContrato('El tecnico seleccionado no es válido.');
      }
    }

    if ($usaPlanYMedidas && !empty($areas)) {
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

    $stmt = $db->prepare("INSERT INTO contrato_empresa (tipo_contrato, inicio_contratacion, fin_contratacion, id_empresa, id_usuario) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssii', $tipoContrato, $inicioContratacion, $finContratacion, $idEmpresa, $idUsuarioServicio);
    $stmt->execute();
    $stmt->close();

    if ($usaPlanYMedidas && !empty($areas)) {
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

        // Guardar medidas personalizadas si existen
        $medidasPersonalizadasArea = trim((string)($medidasPersonalizadasRaw[(string)$idPlanArea] ?? $medidasPersonalizadasRaw[$idPlanArea] ?? ''));
        if (!empty($medidasPersonalizadasArea)) {
          $stmtInsertMedidaPersonalizada = $db->prepare("INSERT INTO medida (descripcion, id_plan) VALUES (?, ?)");
          $stmtInsertMedidaPersonalizada->bind_param('si', $medidasPersonalizadasArea, $idPlanArea);
          $stmtInsertMedidaPersonalizada->execute();
          $idMedidaPersonalizada = (int)$stmtInsertMedidaPersonalizada->insert_id;
          $stmtInsertMedidaPersonalizada->close();

          if ($idMedidaPersonalizada > 0) {
            $stmtInsertMedidaEnCliente = $db->prepare("INSERT INTO cliente_medida (id_areas_contratadas, id_medida) VALUES (?, ?)");
            $stmtInsertMedidaEnCliente->bind_param('ii', $idAreaContratada, $idMedidaPersonalizada);
            $stmtInsertMedidaEnCliente->execute();
            $stmtInsertMedidaEnCliente->close();
          }
        }
      }

      $stmtAreaInsert->close();
    }

    // --- Asignar técnico a la empresa en usuario_empresa si no existe ---
    $stmtCheck = $db->prepare("SELECT 1 FROM usuario_empresa WHERE id_usuario = ? AND id_empresa = ?");
    $stmtCheck->bind_param('ii', $idUsuarioServicio, $idEmpresa);
    $stmtCheck->execute();
    $existe = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();
    if (!$existe) {
      $stmtInsert = $db->prepare("INSERT INTO usuario_empresa (id_usuario, id_empresa) VALUES (?, ?)");
      $stmtInsert->bind_param('ii', $idUsuarioServicio, $idEmpresa);
      $stmtInsert->execute();
      $stmtInsert->close();
    }

    $db->commit();

    unset($_SESSION['add_contrato_old'], $_SESSION['add_contrato_error']);
    redirect_view_empresas('ver_contratos', 'Contrato guardado correctamente.', $idEmpresa);
  } catch (Throwable $e) {
    $db->rollback();
    log_internal_error_empresa('empresa.add_contrato', $e);
    $errorContrato('No se pudo guardar el contrato. Intentalo de nuevo.');
  }
}

// EDITAR CONTRATO
if ($accion === 'edit_contratos') {
  $idContrato = (int)($_POST['id_contrato_empresa'] ?? 0);
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);
  $idUsuarioServicio = $esTecnico
    ? $currentUserId
    : (int)($_POST['id_usuario'] ?? 0);
  $tipoContrato = strtoupper(trim((string)($_POST['tipo_contrato'] ?? '')));
  $inicioPlan = trim((string)($_POST['inicio_plan'] ?? ''));
  $finPlan = trim((string)($_POST['fin_plan'] ?? ''));
  $inicioContratacion = trim((string)($_POST['inicio_contratacion'] ?? ''));
  $finContratacion = trim((string)($_POST['fin_contratacion'] ?? ''));
  $areasRaw = $_POST['areas'] ?? [];
  $medidasRaw = $_POST['medidas'] ?? [];
  $medidasPersonalizadasRaw = $_POST['medidas_personalizadas'] ?? [];

  if (!is_array($areasRaw)) $areasRaw = [];
  if (!is_array($medidasRaw)) $medidasRaw = [];
  if (!is_array($medidasPersonalizadasRaw)) $medidasPersonalizadasRaw = [];

  $areas = array_values(array_unique(array_filter(array_map('intval', $areasRaw), static fn($n) => $n > 0)));

  $_SESSION['edit_contrato_old'] = [
    'id_contrato_empresa' => $idContrato,
    'id_empresa' => $idEmpresa,
    'id_usuario' => $idUsuarioServicio,
    'tipo_contrato' => $tipoContrato,
    'inicio_plan' => $inicioPlan,
    'fin_plan' => $finPlan,
    'inicio_contratacion' => $inicioContratacion,
    'fin_contratacion' => $finContratacion,
    'areas' => $areas,
    'medidas' => $medidasRaw,
    'medidas_personalizadas' => $medidasPersonalizadasRaw,
  ];

  $errorEditContrato = static function (string $msg) use ($idContrato): void {
    $_SESSION['edit_contrato_error'] = $msg;
    $to = app_path('/model/empresa.php?view=edit_contratos&id_contrato=') . (int)$idContrato;
    $idEmpresaCtx = (int)($_POST['id_empresa'] ?? $_GET['id_empresa'] ?? 0);
    if ($idEmpresaCtx > 0) {
      $to .= '&id_empresa=' . $idEmpresaCtx;
    }
    $tipoContratoCtx = trim((string)($_POST['tipo_contrato_context'] ?? $_POST['tipo_contrato'] ?? $_GET['tipo_contrato'] ?? ''));
    if ($tipoContratoCtx !== '') {
      $to .= '&tipo_contrato=' . urlencode($tipoContratoCtx);
    }
    $fromCtx = trim((string)($_POST['from'] ?? $_GET['from'] ?? ''));
    if ($fromCtx !== '') {
      $to .= '&from=' . urlencode($fromCtx);
    }
    $soloMedidasEmbedCtx = trim((string)($_POST['solo_medidas_embed'] ?? $_GET['solo_medidas_embed'] ?? ''));
    if ($soloMedidasEmbedCtx === '1') {
      $to .= '&solo_medidas_embed=1';
    }
    header('Location: ' . $to);
    exit;
  };

  if ($idContrato <= 0) $errorEditContrato('Contrato inválido.');
  if ($idEmpresa <= 0) $errorEditContrato('Selecciona una empresa.');
  if ($idUsuarioServicio <= 0) $errorEditContrato('Selecciona un tecnico para el servicio.');
  if ($tipoContrato === '' || $inicioContratacion === '' || $finContratacion === '') {
    $errorEditContrato('Completa todos los campos del contrato.');
  }

  $tiposValidos = ['PLAN IGUALDAD', 'MANTENIMIENTO'];
  if (!in_array($tipoContrato, $tiposValidos, true)) {
    $errorEditContrato('Tipo de contrato inválido.');
  }

  $usaMantenimiento = ($tipoContrato === 'MANTENIMIENTO');
  $usaPlanYMedidas = $usaMantenimiento || ($tipoContrato === 'PLAN IGUALDAD');

  if (strtotime($inicioContratacion) === false || strtotime($finContratacion) === false) {
    $errorEditContrato('Formato de fecha inválido.');
  }
  if (strtotime($inicioContratacion) > strtotime($finContratacion)) {
    $errorEditContrato('La fecha de inicio no puede ser mayor que la fecha de fin.');
  }

  if ($usaMantenimiento) {
    if ($inicioPlan === '' || $finPlan === '') {
      $errorEditContrato('Completa la fecha de inicio y fin de vigencia para mantenimiento.');
    }
    if (strtotime($inicioPlan) === false || strtotime($finPlan) === false) {
      $errorEditContrato('Formato de fecha de vigencia inválido.');
    }
    if (strtotime($inicioPlan) > strtotime($finPlan)) {
      $errorEditContrato('La fecha de inicio de vigencia no puede ser mayor que la de fin.');
    }
    if (empty($areas)) {
      $errorEditContrato('Selecciona al menos un área para mantenimiento.');
    }
  } elseif ($usaPlanYMedidas && ($inicioPlan !== '' || $finPlan !== '')) {
    // Plan Igualdad: fechas opcionales, pero si se rellenan hay que validarlas
    if ($inicioPlan === '' || $finPlan === '') {
      $errorEditContrato('Si indicas una fecha de vigencia, debes completar tanto el inicio como el fin.');
    }
    if (strtotime($inicioPlan) === false || strtotime($finPlan) === false) {
      $errorEditContrato('Formato de fecha de vigencia inválido.');
    }
    if (strtotime($inicioPlan) > strtotime($finPlan)) {
      $errorEditContrato('La fecha de inicio de vigencia no puede ser mayor que la de fin.');
    }
  }

  $db = db();
  try {
    $stmtC = $db->prepare("SELECT id_contrato_empresa, id_empresa FROM contrato_empresa WHERE id_contrato_empresa = ? LIMIT 1");
    $stmtC->bind_param('i', $idContrato);
    $stmtC->execute();
    $contratoActual = $stmtC->get_result()->fetch_assoc();
    if (!$contratoActual) {
      $stmtC->close();
      $errorEditContrato('El contrato no existe.');
    }
    $stmtC->close();

    if ($esTecnico) {
      $idEmpresaContratoActual = (int)($contratoActual['id_empresa'] ?? 0);
      if (!tecnico_tiene_empresa($idEmpresaContratoActual, $currentUserId)) {
        $errorEditContrato('No tienes permiso para editar este contrato.');
      }
      if (!tecnico_tiene_empresa($idEmpresa, $currentUserId)) {
        $errorEditContrato('No puedes asignar el contrato a una empresa no asignada.');
      }
    }

    $stmtE = $db->prepare("SELECT id_empresa FROM empresa WHERE id_empresa = ? LIMIT 1");
    $stmtE->bind_param('i', $idEmpresa);
    $stmtE->execute();
    if (!$stmtE->get_result()->fetch_assoc()) {
      $stmtE->close();
      $errorEditContrato('La empresa seleccionada no existe.');
    }
    $stmtE->close();

    if (!$esTecnico) {
      $stmtTecnico = $db->prepare("\n        SELECT 1\n        FROM usuario u\n        INNER JOIN rol r ON r.id = u.rol_id\n        WHERE u.id_usuario = ?\n          AND UPPER(TRIM(COALESCE(r.nombre, ''))) IN ('TECNICO', 'TÉCNICO')\n        LIMIT 1\n      ");
      $stmtTecnico->bind_param('i', $idUsuarioServicio);
      $stmtTecnico->execute();
      $tecnicoValido = (bool)$stmtTecnico->get_result()->fetch_assoc();
      $stmtTecnico->close();
      if (!$tecnicoValido) {
        $errorEditContrato('El tecnico seleccionado no es válido.');
      }
    }

    if ($usaPlanYMedidas && !empty($areas)) {
      $stmtArea = $db->prepare("SELECT id_plan FROM area_plan WHERE id_plan = ? LIMIT 1");
      foreach ($areas as $idPlanArea) {
        $stmtArea->bind_param('i', $idPlanArea);
        $stmtArea->execute();
        if (!$stmtArea->get_result()->fetch_assoc()) {
          $stmtArea->close();
          $errorEditContrato('Una de las áreas no existe.');
        }
      }
      $stmtArea->close();
    }

    $db->begin_transaction();

    $stmtU = $db->prepare("UPDATE contrato_empresa SET tipo_contrato = ?, inicio_contratacion = ?, fin_contratacion = ?, id_empresa = ?, id_usuario = ? WHERE id_contrato_empresa = ?");
    $stmtU->bind_param('sssiii', $tipoContrato, $inicioContratacion, $finContratacion, $idEmpresa, $idUsuarioServicio, $idContrato);
    $stmtU->execute();
    $stmtU->close();

    if ($usaPlanYMedidas && !empty($areas)) {
      asegurar_fk_archivos_cliente_medida_no_cascade();

      $stmtDelAf = $db->prepare("DELETE af FROM area_formacion af INNER JOIN cliente_medida cm ON cm.id_cliente_medida = af.id_cliente_medida INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
      $stmtDelAf->bind_param('i', $idEmpresa);
      $stmtDelAf->execute();
      $stmtDelAf->close();

      $stmtDelAe = $db->prepare("DELETE ae FROM area_ejercicio ae INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ae.id_cliente_medida INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
      $stmtDelAe->bind_param('i', $idEmpresa);
      $stmtDelAe->execute();
      $stmtDelAe->close();

      $stmtDelCm = $db->prepare("DELETE cm FROM cliente_medida cm INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
      $stmtDelCm->bind_param('i', $idEmpresa);
      $stmtDelCm->execute();
      $stmtDelCm->close();

      $stmtDelPc = $db->prepare("DELETE FROM areas_contratadas WHERE id_empresa = ?");
      $stmtDelPc->bind_param('i', $idEmpresa);
      $stmtDelPc->execute();
      $stmtDelPc->close();

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

        $medidasPersonalizadasArea = trim((string)($medidasPersonalizadasRaw[(string)$idPlanArea] ?? $medidasPersonalizadasRaw[$idPlanArea] ?? ''));
        if ($medidasPersonalizadasArea !== '') {
          $stmtInsertMedidaPersonalizada = $db->prepare("INSERT INTO medida (descripcion, id_plan) VALUES (?, ?)");
          $stmtInsertMedidaPersonalizada->bind_param('si', $medidasPersonalizadasArea, $idPlanArea);
          $stmtInsertMedidaPersonalizada->execute();
          $idMedidaPersonalizada = (int)$stmtInsertMedidaPersonalizada->insert_id;
          $stmtInsertMedidaPersonalizada->close();

          if ($idMedidaPersonalizada > 0) {
            $stmtInsertMedidaEnCliente = $db->prepare("INSERT INTO cliente_medida (id_areas_contratadas, id_medida) VALUES (?, ?)");
            $stmtInsertMedidaEnCliente->bind_param('ii', $idAreaContratada, $idMedidaPersonalizada);
            $stmtInsertMedidaEnCliente->execute();
            $stmtInsertMedidaEnCliente->close();
          }
        }
      }

      $stmtAreaInsert->close();
    }

    // --- Asignar técnico a la empresa en usuario_empresa si no existe ---
    $stmtCheck = $db->prepare("SELECT 1 FROM usuario_empresa WHERE id_usuario = ? AND id_empresa = ?");
    $stmtCheck->bind_param('ii', $idUsuarioServicio, $idEmpresa);
    $stmtCheck->execute();
    $existe = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();
    if (!$existe) {
      $stmtInsert = $db->prepare("INSERT INTO usuario_empresa (id_usuario, id_empresa) VALUES (?, ?)");
      $stmtInsert->bind_param('ii', $idUsuarioServicio, $idEmpresa);
      $stmtInsert->execute();
      $stmtInsert->close();
    }

    $db->commit();

    unset($_SESSION['edit_contrato_old'], $_SESSION['edit_contrato_error']);
    $soloMedidasEmbedCtx = trim((string)($_POST['solo_medidas_embed'] ?? $_GET['solo_medidas_embed'] ?? ''));
    if ($soloMedidasEmbedCtx === '1') {
      $to = app_path('/model/empresa.php?view=edit_contratos&id_contrato=') . (int)$idContrato;
      if ($idEmpresa > 0) {
        $to .= '&id_empresa=' . $idEmpresa;
      }
      $tipoContratoCtx = trim((string)($_POST['tipo_contrato_context'] ?? $_POST['tipo_contrato'] ?? $_GET['tipo_contrato'] ?? ''));
      if ($tipoContratoCtx !== '') {
        $to .= '&tipo_contrato=' . urlencode($tipoContratoCtx);
      }
      $fromCtx = trim((string)($_POST['from'] ?? $_GET['from'] ?? ''));
      if ($fromCtx !== '') {
        $to .= '&from=' . urlencode($fromCtx);
      }
      $to .= '&solo_medidas_embed=1&msg=' . urlencode('Medidas guardadas correctamente.');
      header('Location: ' . $to);
      exit;
    }

    redirect_view_empresas('ver_contratos', 'Contrato actualizado correctamente.', $idEmpresa);
  } catch (Throwable $e) {
    @$db->rollback();
    log_internal_error_empresa('empresa.edit_contrato', $e);
    $errorEditContrato('No se pudo actualizar el contrato. Intentalo de nuevo.');
  }
}

// ELIMINAR CONTRATO
if ($accion === 'delete_contratos') {
  $idContrato = (int)($_POST['id_contrato_empresa'] ?? 0);
  $currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);

  if ($idContrato <= 0) {
    redirect_view_empresas('ver_contratos', 'Contrato inválido.');
  }

  try {
    $db = db();
    $stmtCheck = $db->prepare("SELECT id_empresa, tipo_contrato FROM contrato_empresa WHERE id_contrato_empresa = ? LIMIT 1");
    $stmtCheck->bind_param('i', $idContrato);
    $stmtCheck->execute();
    $contratoRow = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$contratoRow) {
      redirect_view_empresas('ver_contratos', 'Contrato no encontrado.');
    }

    $idEmpresaContrato = (int)($contratoRow['id_empresa'] ?? 0);
    $tipoContratoElim   = strtoupper(trim((string)($contratoRow['tipo_contrato'] ?? '')));

    // Verificación de seguridad para técnico
    if ($esTecnico) {
      if (!tecnico_tiene_empresa($idEmpresaContrato, $currentUserId)) {
        redirect_view_empresas('ver_contratos', 'No tienes permiso para eliminar este contrato.');
      }
    }

    $db->begin_transaction();

    // Limpiar áreas y medidas asociadas (aplica a PLAN IGUALDAD y MANTENIMIENTO)
    if (in_array($tipoContratoElim, ['PLAN IGUALDAD', 'MANTENIMIENTO'], true)) {
      $stmtDelAf = $db->prepare("DELETE af FROM area_formacion af INNER JOIN cliente_medida cm ON cm.id_cliente_medida = af.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas WHERE ac.id_empresa = ?");
      $stmtDelAf->bind_param('i', $idEmpresaContrato);
      $stmtDelAf->execute();
      $stmtDelAf->close();

      $stmtDelAe = $db->prepare("DELETE ae FROM area_ejercicio ae INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ae.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas WHERE ac.id_empresa = ?");
      $stmtDelAe->bind_param('i', $idEmpresaContrato);
      $stmtDelAe->execute();
      $stmtDelAe->close();

      $stmtDelCm = $db->prepare("DELETE cm FROM cliente_medida cm INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas WHERE ac.id_empresa = ?");
      $stmtDelCm->bind_param('i', $idEmpresaContrato);
      $stmtDelCm->execute();
      $stmtDelCm->close();

      $stmtDelAc = $db->prepare("DELETE FROM areas_contratadas WHERE id_empresa = ?");
      $stmtDelAc->bind_param('i', $idEmpresaContrato);
      $stmtDelAc->execute();
      $stmtDelAc->close();
    }

    $stmt = $db->prepare("DELETE FROM contrato_empresa WHERE id_contrato_empresa = ?");
    $stmt->bind_param('i', $idContrato);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    redirect_view_empresas('ver_contratos', 'Contrato eliminado correctamente.', $idEmpresaContrato);
  } catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
      $db->rollback();
    }
    log_internal_error_empresa('empresa.delete_contrato', $e);
    redirect_view_empresas('ver_contratos', 'No se pudo eliminar el contrato. Intentalo de nuevo.');
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
    header('Location: ' . app_path('/model/empresa.php?view=edit_plan&id_empresa=') . (int)$idEmpresa);
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
    asegurar_fk_archivos_cliente_medida_no_cascade();

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

    // PRESERVAR ARCHIVOS: Antes de borrar medidas, desligamos los archivos de cliente_medida 
    // para evitar que el ON DELETE CASCADE los borre si la FK no se pudo cambiar a SET NULL.
    $stmtPreserve = $db->prepare("
        UPDATE archivos 
        SET id_cliente_medida = NULL 
        WHERE id_empresa = ? 
          AND id_cliente_medida IS NOT NULL
    ");
    if ($stmtPreserve) {
        $stmtPreserve->bind_param('i', $idEmpresa);
        $stmtPreserve->execute();
        $stmtPreserve->close();
    }

    $stmtDelAf = $db->prepare("DELETE af FROM area_formacion af INNER JOIN cliente_medida cm ON cm.id_cliente_medida = af.id_cliente_medida INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
    $stmtDelAf->bind_param('i', $idEmpresa);
    $stmtDelAf->execute();
    $stmtDelAf->close();

    $stmtDelAe = $db->prepare("DELETE ae FROM area_ejercicio ae INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ae.id_cliente_medida INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
    $stmtDelAe->bind_param('i', $idEmpresa);
    $stmtDelAe->execute();
    $stmtDelAe->close();

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
    redirect_view_empresas('ver_planes', 'Plan actualizado correctamente.', $idEmpresa);
  } catch (Throwable $e) {
    $db->rollback();
    log_internal_error_empresa('empresa.edit_plan', $e);
    $errorEdit('No se pudo actualizar el plan. Intentalo de nuevo.');
  }
}

// ELIMINAR PLAN DE EMPRESA
if ($accion === 'delete_plan_empresa') {
  $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
  $currentUserId = (int)($_SESSION['user']['id_usuario'] ?? 0);

  if ($idEmpresa <= 0) {
    redirect_view_empresas('ver_planes', 'Empresa inválida.', $idEmpresa);
  }

  // Validar que el técnico tiene permiso sobre esta empresa
  if ($esTecnico && !tecnico_tiene_empresa($idEmpresa, $currentUserId)) {
    redirect_view_empresas('ver_planes', 'No tienes permiso para eliminar el plan de esta empresa.', $idEmpresa);
  }

  $db = db();
  try {
    $db->begin_transaction();

    // PRESERVAR ARCHIVOS: Antes de borrar medidas, desligamos los archivos de cliente_medida
    $stmtPreserve = $db->prepare("
        UPDATE archivos 
        SET id_cliente_medida = NULL 
        WHERE id_empresa = ? 
          AND id_cliente_medida IS NOT NULL
    ");
    if ($stmtPreserve) {
        $stmtPreserve->bind_param('i', $idEmpresa);
        $stmtPreserve->execute();
        $stmtPreserve->close();
    }

    $stmtDelAf = $db->prepare("DELETE af FROM area_formacion af INNER JOIN cliente_medida cm ON cm.id_cliente_medida = af.id_cliente_medida INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
    $stmtDelAf->bind_param('i', $idEmpresa);
    $stmtDelAf->execute();
    $stmtDelAf->close();

    $stmtDelAe = $db->prepare("DELETE ae FROM area_ejercicio ae INNER JOIN cliente_medida cm ON cm.id_cliente_medida = ae.id_cliente_medida INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
    $stmtDelAe->bind_param('i', $idEmpresa);
    $stmtDelAe->execute();
    $stmtDelAe->close();

    $stmtDelCm = $db->prepare("DELETE cm FROM cliente_medida cm INNER JOIN areas_contratadas pc ON pc.id_areas_contratadas = cm.id_areas_contratadas WHERE pc.id_empresa = ?");
    $stmtDelCm->bind_param('i', $idEmpresa);
    $stmtDelCm->execute();
    $stmtDelCm->close();

    $stmtDelPc = $db->prepare("DELETE FROM areas_contratadas WHERE id_empresa = ?");
    $stmtDelPc->bind_param('i', $idEmpresa);
    $stmtDelPc->execute();
    $stmtDelPc->close();

    $db->commit();
    redirect_view_empresas('ver_planes', 'Plan eliminado correctamente.', $idEmpresa);
  } catch (Throwable $e) {
    $db->rollback();
    log_internal_error_empresa('empresa.delete_plan', $e);
    redirect_view_empresas('ver_planes', 'No se pudo eliminar el plan. Intentalo de nuevo.', $idEmpresa);
  }
}
// Si llega aquí, acción no soportada
redirect_menu_empresas('Acción no válida');
