<?php
declare(strict_types=1);

require __DIR__ . '/../php/auth.php';

require_role('CLIENTE');

require __DIR__ . '/../config/config.php';

// Redirige al panel del cliente 

function redirect_cliente(string $msg): void {
  header('Location: /Proyecto/model/cliente.php?msg=' . urlencode($msg));
  exit;
}
// VALIDACIÓN: MÉTODO HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

// VALIDACIÓN: ARCHIVOS RECIBIDOS
// Si no existe el campo fileToUpload en $_FILES, no hay subida.
if (!isset($_FILES['fileToUpload'])) {
  redirect_cliente('No se recibió archivo');
}

// DATOS DE SESIÓN 
// ID del cliente (para relacionar el archivo con el usuario en BD)
$clienteId = (int)($_SESSION['user']['id'] ?? 0);
if ($clienteId <= 0) {
  redirect_cliente('Sesión inválida');
}
// Username del cliente para nombrar la carpeta del cliente
$clienteUsername = (string)($_SESSION['user']['username'] ?? 'desconocido');
if (empty($clienteUsername)) {
  redirect_cliente('Sesión inválida');
}

// CREACIÓN DE DIRECTORIOS DE SUBIDA
// Carpeta base: /uploads  
$uploadsBase = __DIR__ . '/../uploads';
if (!is_dir($uploadsBase) && !mkdir($uploadsBase, 0775, true)) {
  redirect_cliente('No se pudo crear carpeta uploads');
}

// Carpeta del cliente: /uploads/cliente_{username}
// Con esto podemos separar los archivos por clientes.
$clientDir = $uploadsBase . '/cliente_' . $clienteUsername;
if (!is_dir($clientDir) && !mkdir($clientDir, 0775, true)) {
  redirect_cliente('No se pudo crear carpeta del cliente');
}


$db = db();                 
$uploadedCount = 0;         // contador de archivos subidos OK
$errorsList = [];           

// Cuando el input file es multiple, $_FILES['fileToUpload'] trae arrays.

$fileInput = $_FILES['fileToUpload'];

if (!is_array($fileInput['name'])) {
  $fileInput = [
    'name'     => [$fileInput['name']],
    'tmp_name' => [$fileInput['tmp_name']],
    'error'    => [$fileInput['error']],
    'size'     => [$fileInput['size']],
    'type'     => [$fileInput['type']]
  ];
}

// Número total de archivos seleccionados
$totalFiles = count($fileInput['name']);

// BUCLE PRINCIPAL: PROCESAR CADA ARCHIVO
for ($i = 0; $i < $totalFiles; $i++) {
  // Leer datos del archivo actual
  $filename  = trim((string)($fileInput['name'][$i] ?? ''));
  $tmpfile   = (string)($fileInput['tmp_name'][$i] ?? '');
  $uploaderr = (int)($fileInput['error'][$i] ?? UPLOAD_ERR_NO_FILE);
  $filesize  = (int)($fileInput['size'][$i] ?? 0);

  // Si no hay nombre, no hay archivo real y se ignora.
  if (empty($filename)) {
    continue;
  }

  // Validar error de subida del propio PHP 
  if ($uploaderr !== UPLOAD_ERR_OK) {
    $errorsList[] = "$filename: Error de subida (código $uploaderr)";
    continue;
  }

  // Validar que el archivo venga realmente de un upload HTTP
  if (empty($tmpfile) || !is_uploaded_file($tmpfile)) {
    $errorsList[] = "$filename: Archivo temporal inválido";
    continue;
  }
  // VALIDACIÓN DE EXTENSIÓN

  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  if (!in_array($ext, ['xlsx', 'xls', 'docx', 'doc'], true)) {
    $errorsList[] = "$filename: Extensión no permitida";
    continue;
  }
  // VALIDACIÓN DE TAMAÑO
  // Máximo 10MB por archivo
  if ($filesize > 10485760) {
    $errorsList[] = "$filename: Archivo demasiado grande (máx 10MB)";
    continue;
  }
  // GENERAR NOMBRE FINAL EN DISCO
  // Se genera un nombre aleatorio para:
  //  - evitar colisiones 
  //  - evitar problemas de caracteres
  $newname = bin2hex(random_bytes(16)) . '.' . $ext;

  // Ruta absoluta donde se guardará el archivo
  $newpath = $clientDir . '/' . $newname;

  // GUARDAR ARCHIVO EN DISCO
  // move_uploaded_file asegura que viene de un upload real y lo mueve al destino.
  if (!move_uploaded_file($tmpfile, $newpath)) {
    $errorsList[] = "$filename: No se pudo guardar";
    continue;
  }
  // REGISTRO EN BASE DE DATOS
  // Ruta relativa para poder generar links/descargas desde la web.
  $relpath = 'uploads/cliente_' . $clienteUsername . '/' . $newname;

  // MIME guardado
  $mime = 'application/octet-stream';

  // Hash SHA256 útil para integridad / detectar duplicados
  $sha = hash_file('sha256', $newpath) ?: '';

  // Insertar registro del archivo en tabla archivo
  $stmt = $db->prepare("
    INSERT INTO archivo
    (usuario_id, nombre_original, nombre_guardado, ruta_relativa, tamano_bytes, mime, sha256)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");

  if ($stmt) {
    $stmt->bind_param('isssiss', $clienteId, $filename, $newname, $relpath, $filesize, $mime, $sha);
    $stmt->execute();
    $stmt->close();

    // REGISTRAR AVISO PARA TÉCNICO
    // Guardamos un aviso en la tabla aviso para que el técnico lo vea.
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';

    $stmt = $db->prepare("
      INSERT INTO aviso (usuario_id, tipo, mensaje, detalle, ip)
      VALUES (?, 'upload', 'Archivo subido', ?, ?)
    ");

    if ($stmt) {
      // detalle: guardamos el nombre original del archivo subido
      $stmt->bind_param('iss', $clienteId, $filename, $ip);
      $stmt->execute();
      $stmt->close();
    }

    $uploadedCount++;
  } else {
    $errorsList[] = "$filename: Error en base de datos";
  }
}
// Si subió al menos 1: mensaje de OK + errores (si los hay)
// Si no subió ninguno: solo errores o mensaje genérico
if ($uploadedCount > 0) {
  $msg = "$uploadedCount archivo(s) subido(s)";
  if (!empty($errorsList)) {
    $msg .= " - Errores: " . implode('; ', $errorsList);
  }
  redirect_cliente($msg);
} else {
  $msg = empty($errorsList) ? 'No se subió ningún archivo' : implode('; ', $errorsList);
  redirect_cliente($msg);
}