<?php
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=UTF-8');

function jsonResp(bool $ok, ?string $error = null): never
{
    ob_end_clean();
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResp(false, 'Método no permitido.');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user']))              jsonResp(false, 'No autenticado.');

require_once __DIR__ . '/../config/config.php';

$idEmpresa       = (int)($_POST['id_empresa']        ?? 0);
$idClienteMedida = (int)($_POST['id_cliente_medida'] ?? 0);
$tipoArchivo     = trim((string)($_POST['tipo']       ?? 'DOCUMENTACION'));

if ($idEmpresa <= 0) jsonResp(false, 'Empresa no válida.');

// Validar que el archivo llegó correctamente
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    jsonResp(false, 'Error en la subida (código ' . ($_FILES['archivo']['error'] ?? -1) . ').');
}

$file     = $_FILES['archivo'];
$tmpPath  = (string)$file['tmp_name'];
$origName = basename((string)$file['name']);
$size     = (int)$file['size'];

// Límite 20 MB
if ($size > 20 * 1024 * 1024) jsonResp(false, 'El archivo supera el tamaño máximo de 20 MB.');

// Extensiones permitidas
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$extPermitidas = ['pdf','doc','docx','png','jpg','jpeg','xls','xlsx'];
if (!in_array($ext, $extPermitidas, true)) {
    jsonResp(false, 'Extensión no permitida. Usa: ' . implode(', ', $extPermitidas));
}

// Validar MIME real
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmpPath);
$mimesPermitidos = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/png',
    'image/jpeg',
];
if (!in_array($mime, $mimesPermitidos, true)) {
    jsonResp(false, 'Tipo de archivo no permitido.');
}

// Hash SHA-256 para detectar duplicados
$sha256 = hash_file('sha256', $tmpPath);

// Directorio de destino
$dirDestino = __DIR__ . '/../uploads/evidencias/' . $idEmpresa;
if (!is_dir($dirDestino)) {
    mkdir($dirDestino, 0755, true);
}

$nombreGuardado = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$rutaFisica     = $dirDestino . '/' . $nombreGuardado;
$rutaRelativa   = 'uploads/evidencias/' . $idEmpresa . '/' . $nombreGuardado;

if (!move_uploaded_file($tmpPath, $rutaFisica)) {
    jsonResp(false, 'No se pudo guardar el archivo en el servidor.');
}

try {
    $db = db();

    $idClienteMedidaParam = $idClienteMedida > 0 ? $idClienteMedida : null;

    // Buscar archivo existente con el mismo nombre original para esta empresa/medida
    $stmtBuscar = $db->prepare(
        "SELECT id_archivo, ruta_relativa FROM archivos
         WHERE nombre_original = ? AND id_empresa = ?
           AND (id_cliente_medida = ? OR (id_cliente_medida IS NULL AND ? IS NULL))
         LIMIT 1"
    );
    $stmtBuscar->bind_param('siii', $origName, $idEmpresa, $idClienteMedidaParam, $idClienteMedidaParam);
    $stmtBuscar->execute();
    $existente = $stmtBuscar->get_result()->fetch_assoc();
    $stmtBuscar->close();

    if ($existente) {
        // Borrar el archivo físico antiguo
        $rutaFisicaAntigua = __DIR__ . '/../' . $existente['ruta_relativa'];
        if (file_exists($rutaFisicaAntigua)) {
            @unlink($rutaFisicaAntigua);
        }

        // Actualizar el registro existente
        $stmt = $db->prepare(
            "UPDATE archivos
             SET nombre_guardado = ?, ruta_relativa = ?, tamano_bytes = ?, mime = ?, sha256 = ?, subido_en = NOW()
             WHERE id_archivo = ?"
        );
        $stmt->bind_param('ssissi', $nombreGuardado, $rutaRelativa, $size, $mime, $sha256, $existente['id_archivo']);
        if (!$stmt->execute()) {
            @unlink($rutaFisica);
            jsonResp(false, 'Error al actualizar el archivo en la base de datos.');
        }
        $stmt->close();
    } else {
        // Insertar nuevo registro
        $stmt = $db->prepare(
            "INSERT INTO archivos
                (tipo, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256, id_cliente_medida, id_empresa)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'ssssissii',
            $tipoArchivo, $origName, $nombreGuardado, $rutaRelativa,
            $size, $mime, $sha256, $idClienteMedidaParam, $idEmpresa
        );
        if (!$stmt->execute()) {
            @unlink($rutaFisica);
            jsonResp(false, 'Error al registrar el archivo en la base de datos.');
        }
        $stmt->close();
    }

    jsonResp(true);
} catch (\Throwable $e) {
    @unlink($rutaFisica);
    jsonResp(false, 'Error interno: ' . $e->getMessage());
}
