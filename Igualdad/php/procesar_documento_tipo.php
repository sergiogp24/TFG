<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../config/config.php';

function redirect_documentos(string $msg): void
{
    header('Location: ' . app_path('/html/index_documentos_tipo.php?msg=') . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo no permitido');
}

if (!csrf_validate((string)($_POST['_csrf_token'] ?? ''))) {
    redirect_documentos('La sesion ha expirado. Recarga la pagina e intentalo de nuevo.');
}

$asunto = trim((string)($_POST['asunto'] ?? ''));
$referenciaEmpresa = trim((string)($_POST['referencia_empresa'] ?? ''));
$tipo = strtoupper(trim((string)($_POST['tipo'] ?? '')));
$maxTamanoBytes = 50 * 1024 * 1024; // 50MB
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);

$tiposPermitidos = ['IGUALDAD', 'SELECCION', 'SALUD', 'COMUNICACION', 'LGTBI', 'TOMA DE DATOS'];

if (!in_array($tipo, $tiposPermitidos, true)) {
    redirect_documentos('Tipo de archivo no valido.');
}

if (!isset($_FILES['archivo'])) {
    redirect_documentos('No se recibio archivo.');
}

$archivo = $_FILES['archivo'];
$error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

if ($error !== UPLOAD_ERR_OK) {
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        redirect_documentos('El archivo supera el tamano permitido por el servidor/formulario.');
    }
    redirect_documentos('Error en la subida del archivo.');
}

$nombreOriginal = trim((string)($archivo['name'] ?? ''));
$tmpName = (string)($archivo['tmp_name'] ?? '');
$tamanoBytes = (int)($archivo['size'] ?? 0);

if ($nombreOriginal === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
    redirect_documentos('Archivo temporal invalido.');
}

$ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
$extPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'];

if (!in_array($ext, $extPermitidas, true)) {
    redirect_documentos('Extension no permitida.');
}

if ($tamanoBytes <= 0) {
    redirect_documentos('El archivo esta vacio (0KB).');
}

if ($tamanoBytes > $maxTamanoBytes) {
    redirect_documentos('Tamano de archivo no valido (max 50MB).');
}

$uploadDir = __DIR__ . '/../uploads/documentos_tipo';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    redirect_documentos('No se pudo crear la carpeta de subida.');
}

$db = db();

$empresaNombre = '';
$idEmpresaContexto = 0;
if ($usuarioId > 0) {
    $stmtEmpresa = $db->prepare(
        'SELECT e.razon_social
         FROM usuario_empresa ue
         INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
         WHERE ue.id_usuario = ?
         ORDER BY e.razon_social ASC
         LIMIT 1'
    );
    if ($stmtEmpresa) {
        $stmtEmpresa->bind_param('i', $usuarioId);
        $stmtEmpresa->execute();
        $rowEmpresa = $stmtEmpresa->get_result()->fetch_assoc();
        $stmtEmpresa->close();
        $empresaNombre = trim((string)($rowEmpresa['razon_social'] ?? ''));
    }

    $stmtEmpresaId = $db->prepare(
        'SELECT e.id_empresa
         FROM usuario_empresa ue
         INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
         WHERE ue.id_usuario = ?
         ORDER BY e.razon_social ASC
         LIMIT 1'
    );
    if ($stmtEmpresaId) {
        $stmtEmpresaId->bind_param('i', $usuarioId);
        $stmtEmpresaId->execute();
        $rowEmpresaId = $stmtEmpresaId->get_result()->fetch_assoc();
        $stmtEmpresaId->close();
        $idEmpresaContexto = (int)($rowEmpresaId['id_empresa'] ?? 0);
    }
}

if ($tipo === 'TOMA DE DATOS') {
    if ($empresaNombre !== '') {
        $referenciaEmpresa = $empresaNombre . ' - TOMA DE DATOS';
    }

    if ($referenciaEmpresa === '') {
        redirect_documentos('No se pudo determinar la empresa para TOMA DE DATOS.');
    }
}

if ($asunto === '') {
    redirect_documentos('El asunto es obligatorio.');
}

$empresaToken = $empresaNombre !== '' ? $empresaNombre : 'SIN_EMPRESA';
$empresaToken = preg_replace('/[\\\/\:\"\*\?<>\|]+/', '-', $empresaToken);
$empresaToken = preg_replace('/\s+/', '_', (string)$empresaToken);
$empresaToken = trim((string)$empresaToken, '._-');
$empresaToken = mb_strtoupper($empresaToken !== '' ? $empresaToken : 'SIN_EMPRESA', 'UTF-8');

$tipoToken = preg_replace('/\s+/', '_', $tipo);
$tipoToken = mb_strtoupper((string)$tipoToken, 'UTF-8');

$uniqueSuffix = substr(md5(uniqid('', true)), 0, 8);
$nombreGuardadoBase = $empresaToken . '_' . $tipoToken . '_' . $uniqueSuffix;
$nombreGuardado = $nombreGuardadoBase . '.' . $ext;
$rutaDestino = $uploadDir . '/' . $nombreGuardado;

if (!move_uploaded_file($tmpName, $rutaDestino)) {
    redirect_documentos('No se pudo guardar el archivo.');
}

$rutaRelativa = 'uploads/documentos_tipo/' . $nombreGuardado;
$mime = mime_content_type($rutaDestino) ?: null;
$sha256 = hash_file('sha256', $rutaDestino) ?: null;

$nombreOriginalMostrar = $empresaToken . '_' . $tipoToken;
if ($tipo === 'TOMA DE DATOS' && $referenciaEmpresa !== '') {
    $nombreOriginalMostrar = $referenciaEmpresa;
}
if ($ext !== '') {
    $nombreOriginalMostrar .= '.' . $ext;
}

try {
    $stmt = $db->prepare(
           'INSERT INTO archivos (tipo, asunto, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_empresa)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        redirect_documentos('Error preparando insercion en base de datos.');
    }

    $stmt->bind_param(
        'sssssissi',
        $tipo,
        $asunto,
        $nombreOriginalMostrar,
        $nombreGuardado,
        $rutaRelativa,
        $tamanoBytes,
        $mime,
        $sha256,
        $idEmpresaContexto
    );

    if (!$stmt->execute()) {
        $stmt->close();
        redirect_documentos('No se pudo registrar el archivo en base de datos.');
    }

    $stmt->close();
} catch (Throwable $e) {
    redirect_documentos('Error guardando en base de datos.');
}

redirect_documentos('Archivo subido correctamente.');