<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/helpers.php';
require __DIR__ . '/../config/config.php';

function redirect_documentos(string $msg): void
{
    $baseUrl = '/html/subir_documento_tipo.html.php';
    header('Location: ' . app_path($baseUrl . '?msg=') . urlencode($msg));
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
$maxTamanoBytes = 100 * 1024 * 1024; // 100MB
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);

$tiposPermitidos = ['IGUALDAD', 'SELECCION', 'SALUD', 'COMUNICACION', 'LGTBI', 'TOMA DE DATOS', 'DOCUMENTACION'];

if (!in_array($tipo, $tiposPermitidos, true)) {
    redirect_documentos('Tipo de archivo no valido.');
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No se recibio archivo.';
    if (isset($_FILES['archivo'])) {
        $error = (int)$_FILES['archivo']['error'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            $errorMsg = 'El archivo supera el tamano permitido por el servidor/formulario.';
        } elseif ($error !== UPLOAD_ERR_OK) {
            $errorMsg = 'Error en la subida del archivo (Cod: ' . $error . ').';
        }
    }
    redirect_documentos($errorMsg);
}

$archivo = $_FILES['archivo'];

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
    redirect_documentos('Tamano de archivo no valido (max 100MB).');
}

$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    redirect_documentos('No se pudo crear la carpeta de subida.');
}

$db = db();

$empresaNombre = '';
$idEmpresaContexto = (int)($_POST['id_empresa'] ?? 0);

if ($idEmpresaContexto > 0) {
    // Verificar si la empresa existe
    $stmtCheck = $db->prepare('SELECT razon_social FROM empresa WHERE id_empresa = ? LIMIT 1');
    if ($stmtCheck) {
        $stmtCheck->bind_param('i', $idEmpresaContexto);
        $stmtCheck->execute();
        $rowCheck = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();
        if ($rowCheck) {
            $empresaNombre = trim((string)($rowCheck['razon_social'] ?? ''));
        }
    }
}

// Fallback logic if id_empresa not provided (legacy or client view)
if ($idEmpresaContexto <= 0 && $usuarioId > 0) {
    $stmtEmpresa = $db->prepare(
        'SELECT e.id_empresa, e.razon_social
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
        if ($rowEmpresa) {
            $idEmpresaContexto = (int)($rowEmpresa['id_empresa'] ?? 0);
            $empresaNombre = trim((string)($rowEmpresa['razon_social'] ?? ''));
        }
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

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeReal = finfo_file($finfo, $tmpName);
finfo_close($finfo);

$allowedMimes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-office',
    'application/octet-stream'
];

if (!in_array($mimeReal, $allowedMimes, true)) {
    redirect_documentos('El contenido del archivo no es válido o no está permitido.');
}

if (!move_uploaded_file($tmpName, $rutaDestino)) {
    redirect_documentos('No se pudo guardar el archivo.');
}

$rutaRelativa = 'uploads/' . $nombreGuardado;
$mime = $mimeReal;
$sha256 = hash_file('sha256', $rutaDestino) ?: null;

$nombreOriginalMostrar = $empresaToken . '_' . $tipoToken;
if ($tipo === 'TOMA DE DATOS' && $referenciaEmpresa !== '') {
    $nombreOriginalMostrar = $referenciaEmpresa;
}
if ($ext !== '') {
    $nombreOriginalMostrar .= '.' . $ext;
}

try {
    // LIMPIEZA DE ARCHIVOS ANTIGUOS: Borrar archivos del mismo tipo para esta empresa si ya existen
    if ($idEmpresaContexto > 0) {
        $stmtOld = $db->prepare("SELECT id_archivo, ruta_relativa FROM archivos WHERE id_empresa = ? AND tipo = ?");
        if ($stmtOld) {
            $stmtOld->bind_param('is', $idEmpresaContexto, $tipo);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result();
            while ($rowOld = $resOld->fetch_assoc()) {
                $idOld = (int)$rowOld['id_archivo'];
                $rutaOld = (string)$rowOld['ruta_relativa'];
                if ($rutaOld !== '') {
                    $absPathOld = __DIR__ . '/../' . $rutaOld;
                    if (is_file($absPathOld)) {
                        @unlink($absPathOld);
                    }
                }
                $stmtDel = $db->prepare('DELETE FROM archivos WHERE id_archivo = ?');
                if ($stmtDel) {
                    $stmtDel->bind_param('i', $idOld);
                    $stmtDel->execute();
                    $stmtDel->close();
                }
            }
            $stmtOld->close();
        }
    }

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
