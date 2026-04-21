<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';
require_login();
require __DIR__ . '/../config/config.php';

function complemento_redirect(string $tab, string $msg, bool $embed): void
{
  $to = app_path('/html/complemento_formularios.php?tab=') . urlencode($tab) . '&msg=' . urlencode($msg);

  if ($embed) {
    $to .= '&embed=1';
  }

  $idEmpresaContexto = (int)($_POST['id_empresa'] ?? $_GET['id_empresa'] ?? 0);
  if ($idEmpresaContexto > 0) {
    $to .= '&id_empresa=' . urlencode((string)$idEmpresaContexto);
  }

  header('Location: ' . $to);
  exit;
}

function complemento_redirect_csrf(bool $embed): void
{
  $tab = (string)($_POST['tab'] ?? $_GET['tab'] ?? 'bajas');
  if (!in_array($tab, ['bajas', 'formacion', 'excedencias', 'permisos'], true)) {
    $tab = 'bajas';
  }

  complemento_redirect($tab, 'La sesion ha expirado. Recarga la pagina e intentalo de nuevo.', $embed);
}

function complemento_log_error(string $context, Throwable $e): void
{
  error_log(sprintf(
    '[%s] %s in %s:%d',
    $context,
    $e->getMessage(),
    $e->getFile(),
    $e->getLine()
  ));
}

function complemento_usuario_tiene_empresa(int $idEmpresa, int $idUsuario, string $rol): bool
{
  if ($idEmpresa <= 0 || $idUsuario <= 0) {
    return false;
  }

  if ($rol === 'ADMINISTRADOR') {
    return true;
  }

  $db = db();

  $stmt = $db->prepare('SELECT 1 FROM usuario_empresa WHERE id_empresa = ? AND id_usuario = ? LIMIT 1');
  $stmt->bind_param('ii', $idEmpresa, $idUsuario);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($ok) {
    return true;
  }

  $stmt = $db->prepare('SELECT 1 FROM empresa WHERE id_empresa = ? AND id_usuario = ? LIMIT 1');
  $stmt->bind_param('ii', $idEmpresa, $idUsuario);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $ok;
}

function complemento_table_has_column(string $table, string $column): bool
{
  static $cache = [];
  $key = $table . '::' . $column;
  if (array_key_exists($key, $cache)) {
    return $cache[$key];
  }

  $db = db();
  $tableEsc = $db->real_escape_string($table);
  $columnEsc = $db->real_escape_string($column);
  $res = $db->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
  $ok = ($res instanceof mysqli_result) && ($res->num_rows > 0);
  if ($res instanceof mysqli_result) {
    $res->free();
  }

  $cache[$key] = $ok;
  return $ok;
}

function complemento_primary_key(string $table): ?string
{
  static $cache = [];
  if (array_key_exists($table, $cache)) {
    return $cache[$table];
  }

  $db = db();
  $tableEsc = $db->real_escape_string($table);
  $res = $db->query("SHOW KEYS FROM `{$tableEsc}` WHERE Key_name = 'PRIMARY'");
  if (!($res instanceof mysqli_result)) {
    $cache[$table] = null;
    return null;
  }

  $bestCol = null;
  $bestSeq = PHP_INT_MAX;
  while ($row = $res->fetch_assoc()) {
    $seq = (int)($row['Seq_in_index'] ?? 0);
    $col = (string)($row['Column_name'] ?? '');
    if ($col !== '' && $seq > 0 && $seq < $bestSeq) {
      $bestSeq = $seq;
      $bestCol = $col;
    }
  }
  $res->free();

  $cache[$table] = $bestCol;
  return $cache[$table];
}

function complemento_validar_empresa(int $idEmpresa, int $idUsuario, string $rol, string $tab, bool $embed): void
{
  if ($idEmpresa <= 0) {
    complemento_redirect($tab, 'Empresa invalida.', $embed);
  }

  if (!complemento_usuario_tiene_empresa($idEmpresa, $idUsuario, $rol)) {
    complemento_redirect($tab, 'No tienes permisos para esa empresa.', $embed);
  }
}

function complemento_empresa_tiene_registro_retributivo(int $idEmpresa): bool
{
  if ($idEmpresa <= 0) {
    return false;
  }

  $sql = '
    SELECT 1
    FROM archivos a
    INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
    INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
    WHERE UPPER(TRIM(a.tipo)) = "REGISTRO_RETRIBUTIVO" AND ac.id_empresa = ?
    LIMIT 1';

  $stmt = db()->prepare($sql);
  if (!$stmt) {
    return false;
  }

  $stmt->bind_param('i', $idEmpresa);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($ok) {
    return true;
  }

  $sqlDirecto = '
    SELECT 1
    FROM archivos a
    WHERE UPPER(TRIM(a.tipo)) = "REGISTRO_RETRIBUTIVO" AND a.id_empresa = ?
    LIMIT 1';

  $stmtDirecto = db()->prepare($sqlDirecto);
  if (!$stmtDirecto) {
    return false;
  }

  $stmtDirecto->bind_param('i', $idEmpresa);
  $stmtDirecto->execute();
  $ok = (bool)$stmtDirecto->get_result()->fetch_assoc();
  $stmtDirecto->close();

  return $ok;
}

function complemento_validar_requisito_registro(int $idEmpresa, string $tab, bool $embed): void
{
  if (!complemento_empresa_tiene_registro_retributivo($idEmpresa)) {
    complemento_redirect($tab, 'Debes subir primero el Registro Retributivo para desbloquear este formulario.', $embed);
  }
}

function complemento_resolver_id_ano_datos_empresa(int $idEmpresa): int
{
  if ($idEmpresa <= 0) {
    return 0;
  }

  $db = db();
  $sql = '
    SELECT ad.id_ano_datos
    FROM ano_datos ad
    INNER JOIN contrato_empresa ce ON ce.id_contrato_empresa = ad.id_contrato_empresa
    WHERE ce.id_empresa = ?
    ORDER BY ad.id_ano_datos DESC
    LIMIT 1';

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return 0;
  }

  $stmt->bind_param('i', $idEmpresa);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return (int)($row['id_ano_datos'] ?? 0);
}

function complemento_asegurar_columna_empresa_bajas(): void
{
  if (complemento_table_has_column('bajas', 'id_empresa')) {
    return;
  }

  $db = db();
  $db->query('ALTER TABLE bajas ADD COLUMN id_empresa INT(11) NULL AFTER tipo');
  $db->query('ALTER TABLE bajas ADD INDEX idx_bajas_id_empresa (id_empresa)');
}

function complemento_bajas_pertenece_a_empresa(int $idBajas, int $idEmpresa): bool
{
  if ($idBajas <= 0 || $idEmpresa <= 0) {
    return false;
  }

  $db = db();

  $stmt = $db->prepare('SELECT 1 FROM bajas WHERE id_bajas = ? AND id_empresa = ? LIMIT 1');
  $stmt->bind_param('ii', $idBajas, $idEmpresa);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $ok;
}

function complemento_enum_values(string $table, string $column): array
{
  static $cache = [];
  $key = $table . '::' . $column;
  if (array_key_exists($key, $cache)) {
    return $cache[$key];
  }

  $db = db();
  $tableEsc = $db->real_escape_string($table);
  $columnEsc = $db->real_escape_string($column);
  $res = $db->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");

  if (!($res instanceof mysqli_result) || $res->num_rows === 0) {
    if ($res instanceof mysqli_result) {
      $res->free();
    }
    $cache[$key] = [];
    return [];
  }

  $row = $res->fetch_assoc();
  $res->free();

  $type = (string)($row['Type'] ?? '');
  if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
    $cache[$key] = [];
    return [];
  }

  $valsRaw = str_getcsv($m[1], ',', "'", '\\');
  $vals = [];
  foreach ($valsRaw as $v) {
    $vals[] = str_replace("\\'", "'", (string)$v);
  }

  $cache[$key] = $vals;
  return $vals;
}

function complemento_tipo_definitiva_ui_a_db(string $tipoUi): ?string
{
  $map = [
    'Despido' => 'Despido',
    'Fallecimiento' => 'Fallecimiento',
    'Finalización contrato' => 'Finalización contrato',
    'Jubilación' => 'Jubilación',
    'No superación de periodo de prueba' => 'No superación de periodo de prueba',
    'Baja voluntaria' => 'Baja voluntaria',
  ];
  $tipoCanonical = $map[$tipoUi] ?? null;
  if ($tipoCanonical === null) {
    return null;
  }

  $enumValues = complemento_enum_values('baja_definitivas', 'tipo');
  if ($enumValues === []) {
    return $tipoCanonical;
  }

  if (in_array($tipoCanonical, $enumValues, true)) {
    return $tipoCanonical;
  }

  $fallbacks = [
    'Finalización contrato' => ['Finalizacion contrato'],
    'Jubilación' => ['Jubilacion'],
    'No superación de periodo de prueba' => ['No supera periodo de prueba'],
  ];

  foreach ($fallbacks[$tipoCanonical] ?? [] as $alt) {
    if (in_array($alt, $enumValues, true)) {
      return $alt;
    }
  }

  return null;
}

function complemento_tipo_baja_base_valido(string $tipoBase): bool
{
  return in_array($tipoBase, ['TEMPORALES', 'DEFINITIVAS'], true);
}

function complemento_tipo_temporal_valido(string $tipoTemporal): bool
{
  return in_array($tipoTemporal, ['Enfermedad Común', 'Accidente Laboral', 'Riesgo embarazo', 'COVID'], true);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Metodo no permitido');
}

$embed = (string)($_POST['embed'] ?? $_GET['embed'] ?? '') === '1';
if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
  complemento_redirect_csrf($embed);
}

$accion = trim((string)($_POST['accion'] ?? ''));
$tabPorAccion = [
  'bajas' => 'bajas',
  'editar_baja' => 'bajas',
  'eliminar_baja' => 'bajas',
  'formacion' => 'formacion',
  'editar_formacion' => 'formacion',
  'eliminar_formacion' => 'formacion',
  'excedencias' => 'excedencias',
  'editar_excedencia' => 'excedencias',
  'eliminar_excedencia' => 'excedencias',
  'permisos_retribuidos' => 'permisos',
  'editar_permiso' => 'permisos',
  'eliminar_permiso' => 'permisos',
];
$tab = $tabPorAccion[$accion] ?? 'bajas';

if ($accion === '') {
  complemento_redirect($tab, 'Accion no valida.', $embed);
}

$rol = strtoupper((string)($_SESSION['user']['rol'] ?? 'CLIENTE'));
$idUsuario = (int)($_SESSION['user']['id_usuario'] ?? 0);

try {
  if ($accion === 'bajas') {
    $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
    complemento_validar_empresa($idEmpresa, $idUsuario, $rol, $tab, $embed);
    complemento_validar_requisito_registro($idEmpresa, $tab, $embed);
    complemento_asegurar_columna_empresa_bajas();
    $idAnoDatos = complemento_resolver_id_ano_datos_empresa($idEmpresa);
    if ($idAnoDatos <= 0) {
      complemento_redirect($tab, 'No se encontro ano_datos para la empresa seleccionada.', $embed);
    }

    $tipoBase = trim((string)($_POST['tipo_baja'] ?? ''));
    $tipoTemporal = trim((string)($_POST['tipo_temporal'] ?? ''));
    $tipoDefinitivaUi = trim((string)($_POST['tipo_definitiva'] ?? ''));
    $tipoDefinitivaDb = complemento_tipo_definitiva_ui_a_db($tipoDefinitivaUi);
    $motivo = trim((string)($_POST['motivo'] ?? ''));
    $nMujeres = (int)($_POST['num_mujeres'] ?? 0);
    $nHombres = (int)($_POST['num_hombres'] ?? 0);

    if (!complemento_tipo_baja_base_valido($tipoBase) || $nMujeres < 0 || $nHombres < 0) {
      complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
    }

    if ($tipoBase === 'TEMPORALES' && !complemento_tipo_temporal_valido($tipoTemporal)) {
      complemento_redirect($tab, 'Tipo de baja temporal invalido.', $embed);
    }

    if ($tipoBase === 'DEFINITIVAS' && $tipoDefinitivaDb === null) {
      complemento_redirect($tab, 'Tipo de baja definitiva invalido.', $embed);
    }

    $db = db();
    $db->begin_transaction();

    try {
      $stmtBajas = $db->prepare('INSERT INTO bajas (tipo,id_ano_datos, id_empresa) VALUES (?, ?, ?)');
      $stmtBajas->bind_param('sii', $tipoBase, $idAnoDatos, $idEmpresa);
      $stmtBajas->execute();
      $idBajas = (int)$db->insert_id;
      $stmtBajas->close();

      if ($tipoBase === 'TEMPORALES') {
        $stmtTemp = $db->prepare('INSERT INTO baja_temporales (motivo, tipo, num_mujeres, num_hombres, id_bajas, id_ano_datos, id_empresa) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtTemp->bind_param('ssiiiii', $motivo, $tipoTemporal, $nMujeres, $nHombres, $idBajas, $idAnoDatos, $idEmpresa);
        $stmtTemp->execute();
        $stmtTemp->close();
      } else {
        $stmtDef = $db->prepare('INSERT INTO baja_definitivas (motivo, tipo, num_mujeres, num_hombres, id_bajas, id_ano_datos, id_empresa) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtDef->bind_param('ssiiiii', $motivo, $tipoDefinitivaDb, $nMujeres, $nHombres, $idBajas, $idAnoDatos, $idEmpresa);
        $stmtDef->execute();
        $stmtDef->close();
      }

      $db->commit();
    } catch (Throwable $txe) {
      $db->rollback();
      throw $txe;
    }

    complemento_redirect($tab, 'Datos guardados correctamente.', $embed);
  }

  if ($accion === 'editar_baja') {
    $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
    $idBajas = (int)($_POST['id_bajas'] ?? 0);
    complemento_validar_empresa($idEmpresa, $idUsuario, $rol, $tab, $embed);
    complemento_validar_requisito_registro($idEmpresa, $tab, $embed);
    complemento_asegurar_columna_empresa_bajas();
    $idAnoDatos = complemento_resolver_id_ano_datos_empresa($idEmpresa);
    if ($idAnoDatos <= 0) {
      complemento_redirect($tab, 'No se encontro ano_datos para la empresa seleccionada.', $embed);
    }

    if ($idBajas <= 0 || !complemento_bajas_pertenece_a_empresa($idBajas, $idEmpresa)) {
      complemento_redirect($tab, 'No se encontro la baja para esta empresa.', $embed);
    }

    $tipoBase = trim((string)($_POST['tipo_baja'] ?? ''));
    $tipoTemporal = trim((string)($_POST['tipo_temporal'] ?? ''));
    $tipoDefinitivaUi = trim((string)($_POST['tipo_definitiva'] ?? ''));
    $tipoDefinitivaDb = complemento_tipo_definitiva_ui_a_db($tipoDefinitivaUi);
    $motivo = trim((string)($_POST['motivo'] ?? ''));
    $nMujeres = (int)($_POST['num_mujeres'] ?? 0);
    $nHombres = (int)($_POST['num_hombres'] ?? 0);

    if (!complemento_tipo_baja_base_valido($tipoBase) || $nMujeres < 0 || $nHombres < 0) {
      complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
    }

    $db = db();
    $db->begin_transaction();

    try {
      $stmtB = $db->prepare('UPDATE bajas SET tipo = ?, id_empresa = ? WHERE id_bajas = ?');
      $stmtB->bind_param('sii', $tipoBase, $idEmpresa, $idBajas);
      $stmtB->execute();
      $stmtB->close();

      $stmtDelTemp = $db->prepare('DELETE FROM baja_temporales WHERE id_bajas = ?');
      $stmtDelTemp->bind_param('i', $idBajas);
      $stmtDelTemp->execute();
      $stmtDelTemp->close();

      $stmtDelDef = $db->prepare('DELETE FROM baja_definitivas WHERE id_bajas = ?');
      $stmtDelDef->bind_param('i', $idBajas);
      $stmtDelDef->execute();
      $stmtDelDef->close();

      if ($tipoBase === 'TEMPORALES') {
        if (!complemento_tipo_temporal_valido($tipoTemporal)) {
          complemento_redirect($tab, 'Tipo de baja temporal invalido.', $embed);
        }

        $stmtTemp = $db->prepare('INSERT INTO baja_temporales (motivo, tipo, num_mujeres, num_hombres, id_bajas, id_ano_datos, id_empresa) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtTemp->bind_param('ssiiiii', $motivo, $tipoTemporal, $nMujeres, $nHombres, $idBajas, $idAnoDatos, $idEmpresa);
        $stmtTemp->execute();
        $stmtTemp->close();
      } else {
        if ($tipoDefinitivaDb === null) {
          complemento_redirect($tab, 'Tipo de baja definitiva invalido.', $embed);
        }

        $stmtDef = $db->prepare('INSERT INTO baja_definitivas (motivo, tipo, num_mujeres, num_hombres, id_bajas, id_ano_datos, id_empresa) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtDef->bind_param('ssiiiii', $motivo, $tipoDefinitivaDb, $nMujeres, $nHombres, $idBajas, $idAnoDatos, $idEmpresa);
        $stmtDef->execute();
        $stmtDef->close();
      }

      $db->commit();
    } catch (Throwable $txe) {
      $db->rollback();
      throw $txe;
    }

    complemento_redirect($tab, 'Baja actualizada correctamente.', $embed);
  }

  if ($accion === 'eliminar_baja') {
    $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
    $idBajas = (int)($_POST['id_bajas'] ?? 0);
    complemento_validar_empresa($idEmpresa, $idUsuario, $rol, $tab, $embed);
    complemento_validar_requisito_registro($idEmpresa, $tab, $embed);
    complemento_asegurar_columna_empresa_bajas();

    if ($idBajas <= 0 || !complemento_bajas_pertenece_a_empresa($idBajas, $idEmpresa)) {
      complemento_redirect($tab, 'No se encontro la baja para esta empresa.', $embed);
    }

    $db = db();
    $db->begin_transaction();

    try {
      $stmtDelTemp = $db->prepare('DELETE FROM baja_temporales WHERE id_bajas = ?');
      $stmtDelTemp->bind_param('i', $idBajas);
      $stmtDelTemp->execute();
      $stmtDelTemp->close();

      $stmtDelDef = $db->prepare('DELETE FROM baja_definitivas WHERE id_bajas = ?');
      $stmtDelDef->bind_param('i', $idBajas);
      $stmtDelDef->execute();
      $stmtDelDef->close();

      $stmtDelB = $db->prepare('DELETE FROM bajas WHERE id_bajas = ? AND id_empresa = ?');
      $stmtDelB->bind_param('ii', $idBajas, $idEmpresa);
      $stmtDelB->execute();
      $stmtDelB->close();

      $db->commit();
    } catch (Throwable $txe) {
      $db->rollback();
      throw $txe;
    }

    complemento_redirect($tab, 'Baja eliminada correctamente.', $embed);
  }

  if (in_array($accion, ['formacion', 'editar_formacion', 'eliminar_formacion'], true)) {
    $table = 'area_formaciones';
    $pk = complemento_primary_key($table);
    if ($pk === null) {
      complemento_redirect($tab, 'No se encontro clave primaria en formacion.', $embed);
    }

    $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
    complemento_validar_empresa($idEmpresa, $idUsuario, $rol, $tab, $embed);
    complemento_validar_requisito_registro($idEmpresa, $tab, $embed);
    $idAnoDatos = complemento_resolver_id_ano_datos_empresa($idEmpresa);
    if ($idAnoDatos <= 0) {
      complemento_redirect($tab, 'No se encontro ano_datos para la empresa seleccionada.', $embed);
    }

    $db = db();

    if ($accion === 'formacion') {
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
      $nHombres = (int)($_POST['n_hombres'] ?? 0);

      if ($tipo === '' || $nMujeres < 0 || $nHombres < 0) {
        complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
      }

      $stmt = $db->prepare('INSERT INTO area_formaciones (tipo, n_mujeres, n_hombres, id_ano_datos, id_empresa) VALUES (?, ?, ?, ?, ?)');
      $stmt->bind_param('siiii', $tipo, $nMujeres, $nHombres, $idAnoDatos, $idEmpresa);
      $stmt->execute();
      $stmt->close();

      complemento_redirect($tab, 'Datos guardados correctamente.', $embed);
    }

    $idRegistro = (int)($_POST['id_registro'] ?? 0);
    if ($idRegistro <= 0) {
      complemento_redirect($tab, 'Registro invalido.', $embed);
    }

    if ($accion === 'editar_formacion') {
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
      $nHombres = (int)($_POST['n_hombres'] ?? 0);

      if ($tipo === '' || $nMujeres < 0 || $nHombres < 0) {
        complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
      }

      $sql = "UPDATE `{$table}` SET tipo = ?, n_mujeres = ?, n_hombres = ? WHERE `{$pk}` = ? AND id_empresa = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param('siiii', $tipo, $nMujeres, $nHombres, $idRegistro, $idEmpresa);
      $stmt->execute();
      $stmt->close();

      complemento_redirect($tab, 'Formacion actualizada correctamente.', $embed);
    }

    $sql = "DELETE FROM `{$table}` WHERE `{$pk}` = ? AND id_empresa = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $idRegistro, $idEmpresa);
    $stmt->execute();
    $stmt->close();

    complemento_redirect($tab, 'Formacion eliminada correctamente.', $embed);
  }

  if (in_array($accion, ['excedencias', 'editar_excedencia', 'eliminar_excedencia'], true)) {
    $table = 'area_excedencias';
    $pk = complemento_primary_key($table);
    if ($pk === null) {
      complemento_redirect($tab, 'No se encontro clave primaria en excedencias.', $embed);
    }

    $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
    complemento_validar_empresa($idEmpresa, $idUsuario, $rol, $tab, $embed);
    complemento_validar_requisito_registro($idEmpresa, $tab, $embed);
    $idAnoDatos = complemento_resolver_id_ano_datos_empresa($idEmpresa);
    if ($idAnoDatos <= 0) {
      complemento_redirect($tab, 'No se encontro ano_datos para la empresa seleccionada.', $embed);
    }

    $db = db();

    if ($accion === 'excedencias') {
      $motivo = trim((string)($_POST['motivo'] ?? ''));
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
      $nHombres = (int)($_POST['n_hombres'] ?? 0);

      if (!in_array($tipo, ['Excedencias Voluntarias', 'Excedencias Cuidado Menores', 'Excedencias Cuidado de Personas Mayores'], true) || $nMujeres < 0 || $nHombres < 0) {
        complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
      }

      $stmt = $db->prepare('INSERT INTO area_excedencias (motivo, tipo, n_mujeres, n_hombres, id_ano_datos, id_empresa) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->bind_param('ssiiii', $motivo, $tipo, $nMujeres, $nHombres, $idAnoDatos, $idEmpresa);
      $stmt->execute();
      $stmt->close();

      complemento_redirect($tab, 'Datos guardados correctamente.', $embed);
    }

    $idRegistro = (int)($_POST['id_registro'] ?? 0);
    if ($idRegistro <= 0) {
      complemento_redirect($tab, 'Registro invalido.', $embed);
    }

    if ($accion === 'editar_excedencia') {
      $motivo = trim((string)($_POST['motivo'] ?? ''));
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
      $nHombres = (int)($_POST['n_hombres'] ?? 0);

      if (!in_array($tipo, ['Excedencias Voluntarias', 'Excedencias Cuidado Menores', 'Excedencias Cuidado de Personas Mayores'], true) || $nMujeres < 0 || $nHombres < 0) {
        complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
      }

      $sql = "UPDATE `{$table}` SET motivo = ?, tipo = ?, n_mujeres = ?, n_hombres = ? WHERE `{$pk}` = ? AND id_empresa = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param('ssiiii', $motivo, $tipo, $nMujeres, $nHombres, $idRegistro, $idEmpresa);
      $stmt->execute();
      $stmt->close();

      complemento_redirect($tab, 'Excedencia actualizada correctamente.', $embed);
    }

    $sql = "DELETE FROM `{$table}` WHERE `{$pk}` = ? AND id_empresa = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $idRegistro, $idEmpresa);
    $stmt->execute();
    $stmt->close();

    complemento_redirect($tab, 'Excedencia eliminada correctamente.', $embed);
  }

  if (in_array($accion, ['permisos_retribuidos', 'editar_permiso', 'eliminar_permiso'], true)) {
    $table = 'area_Permisos_retribuidos';
    $pk = complemento_primary_key($table);
    if ($pk === null) {
      complemento_redirect($tab, 'No se encontro clave primaria en permisos.', $embed);
    }

    $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
    complemento_validar_empresa($idEmpresa, $idUsuario, $rol, $tab, $embed);
    complemento_validar_requisito_registro($idEmpresa, $tab, $embed);
    $idAnoDatos = complemento_resolver_id_ano_datos_empresa($idEmpresa);
    if ($idAnoDatos <= 0) {
      complemento_redirect($tab, 'No se encontro ano_datos para la empresa seleccionada.', $embed);
    }

    $db = db();

    if ($accion === 'permisos_retribuidos') {
      $motivo = trim((string)($_POST['motivo'] ?? ''));
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
      $nHombres = (int)($_POST['n_hombres'] ?? 0);

      if ($tipo === '' || $nMujeres < 0 || $nHombres < 0) {
        complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
      }

      if (!in_array($tipo, ['Lactancia', 'Nacimiento'], true)) {
        complemento_redirect($tab, 'Tipo de permiso invalido.', $embed);
      }

      $stmt = $db->prepare('INSERT INTO area_Permisos_retribuidos (motivo, tipo, n_mujeres, n_hombres, id_ano_datos, id_empresa) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->bind_param('ssiiii', $motivo, $tipo, $nMujeres, $nHombres, $idAnoDatos, $idEmpresa);
      $stmt->execute();
      $stmt->close();

      complemento_redirect($tab, 'Datos guardados correctamente.', $embed);
    }

    $idRegistro = (int)($_POST['id_registro'] ?? 0);
    if ($idRegistro <= 0) {
      complemento_redirect($tab, 'Registro invalido.', $embed);
    }

    if ($accion === 'editar_permiso') {
      $motivo = trim((string)($_POST['motivo'] ?? ''));
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $nMujeres = (int)($_POST['n_mujeres'] ?? 0);
      $nHombres = (int)($_POST['n_hombres'] ?? 0);

      if ($tipo === '' || $nMujeres < 0 || $nHombres < 0) {
        complemento_redirect($tab, 'Completa los campos obligatorios.', $embed);
      }

      if (!in_array($tipo, ['Lactancia', 'Nacimiento'], true)) {
        complemento_redirect($tab, 'Tipo de permiso invalido.', $embed);
      }

      $sql = "UPDATE `{$table}` SET motivo = ?, tipo = ?, n_mujeres = ?, n_hombres = ? WHERE `{$pk}` = ? AND id_empresa = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param('ssiiii', $motivo, $tipo, $nMujeres, $nHombres, $idRegistro, $idEmpresa);
      $stmt->execute();
      $stmt->close();

      complemento_redirect($tab, 'Permiso actualizado correctamente.', $embed);
    }

    $sql = "DELETE FROM `{$table}` WHERE `{$pk}` = ? AND id_empresa = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $idRegistro, $idEmpresa);
    $stmt->execute();
    $stmt->close();

    complemento_redirect($tab, 'Permiso eliminado correctamente.', $embed);
  }

  complemento_redirect($tab, 'Accion no valida.', $embed);
} catch (Throwable $e) {
  complemento_log_error('complemento.guardar', $e);
  complemento_redirect($tab, 'No se pudo guardar la informacion. Intentalo de nuevo.', $embed);
}