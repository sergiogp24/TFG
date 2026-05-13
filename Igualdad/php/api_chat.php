<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Leer los datos de entrada
$input = json_decode(file_get_contents('php://input'), true);

// Validar que el usuario esté logueado
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if (!csrf_validate((string)($input['_csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'La sesión ha expirado. Recarga la página.']);
    exit;
}

header('Content-Type: application/json');

$userMessage = $input['message'] ?? '';
$history = $input['history'] ?? [];

// Datos del usuario y conexión a BD
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$nombreUsuario = $_SESSION['user']['nombre_usuario'] ?? 'Usuario';
$rolUsuario = $_SESSION['user']['rol'] ?? 'Usuario';

$db = db();

function responder_json(array $payload, int $status = 200): void {
    http_response_code($status);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        echo '{"error":"No se pudo generar la respuesta JSON."}';
        return;
    }
    echo $json;
}

function normalizar_texto_busqueda(string $texto): string {
    return trim(strtr(mb_strtolower($texto), [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n'
    ]));
}

function es_consulta_rr(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(rr|registro retributivo|registro|subir rr|subir el rr|cargar rr|subir registro retributivo|gestionar rr|como subir el rr|como subo el rr|como subir registro retributivo|subir el registro|donde subo el registro|donde esta registro retributivo)\b/i', $texto);
}

function es_consulta_definicion_rr(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(que es|que significa|explica|definicion|informacion)\b.*\b(registro retributivo|rr)\b|\b(registro retributivo|rr)\b.*\b(que es|que significa|definicion)\b/i', $texto);
}

function es_consulta_cuantitativos_cualitativos(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(cuantitativos?|cualitativos?|cuestionarios? cualitativos?|datos cuantitativos?)\b|\b(que son|que es|explica|informacion)\b.*\b(cuantitativos?|cualitativos?)\b/i', $texto);
}

function es_consulta_perfil(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(mis datos|editar mis datos|cambiar mis datos|modificar mis datos|editar perfil|cambiar perfil|modificar perfil|mi cuenta|area privada|perfil|como edito mis datos|como cambiar mis datos|como modificar mis datos)\b/i', $texto);
}

function es_consulta_documentacion(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(documentacion|documentos|archivo|archivos|subir documento|ver documentos|descargar documento)\b/i', $texto);
}

function es_consulta_reuniones(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(reunion|reuniones)\b.*\b(pendiente|pendientes|proxima|proximas|tengo|hay)\b|\b(tengo|hay)\b.*\b(reunion|reuniones)\b/i', $texto);
}

function es_consulta_empresas(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(que|cuales|mis|ver|listar|lista|mostrar|tengo)\b.*\b(empresas?)\b|\b(empresas?)\b.*\b(asignadas|asignadas?)\b/i', $texto);
}

function es_consulta_menu_cliente(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(que puedo hacer|que se puede hacer|como funciona|ayuda|menu|opciones|funcionalidades|nueva vista|panel cliente|donde esta cada cosa)\b/i', $texto);
}

function es_consulta_cerrar_sesion(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(cerrar sesion|salir|logout|cerrar mi sesion|como salir)\b/i', $texto);
}

function respuesta_rr(string $texto): string {
    $textoNormalizado = normalizar_texto_busqueda($texto);
    if (preg_match('/\b(ya subi|ya subido|ya esta subido|ya lo subi|lo subi|subi el rr)\b/i', $textoNormalizado)) {
        return "Si ya subiste el Registro Retributivo:\n- Entra en 'Registro Retributivo' del menú lateral.\n- Selecciona tu empresa en 'Mis Empresas'.\n- Verifica si el documento aparece en el listado de la empresa.\n- Si no aparece todavía, puede tardar un poco en procesarse.";
    }

    return "Para gestionar el Registro Retributivo:\n- Abre 'Registro Retributivo' en el menú lateral.\n- Selecciona la empresa en el bloque 'Mis Empresas'.\n- Desde ahí podrás subir y gestionar los documentos del registro.\n- Si no ves empresas, revisa primero la sección 'Mis Empresas'.";
}

function respuesta_definicion_rr(): string {
    return "El registro retributivo es una obligación legal establecida por el Ministerio de Trabajo y Economía Social en España, cuyo objetivo es garantizar la igualdad salarial entre hombres y mujeres dentro de una empresa. Todas las empresas, independientemente de su tamaño, deben disponer de este registro actualizado.\n\n"
        . "El registro retributivo recoge información sobre los salarios, complementos salariales y percepciones extrasalariales de toda la plantilla, desglosados por sexo y agrupados por puestos de trabajo o categorías profesionales. Su finalidad es detectar posibles diferencias salariales injustificadas y promover la transparencia retributiva.\n\n"
        . "Además, las empresas que tengan obligación de realizar un plan de igualdad deben incluir también una auditoría retributiva más detallada. El incumplimiento de esta obligación puede derivar en sanciones económicas por parte de la Inspección de Trabajo.\n\n"
        . "El Ministerio de Trabajo facilita herramientas y modelos oficiales para elaborar el registro retributivo conforme a la normativa vigente.";
}

    function respuesta_cuantitativos_cualitativos(): string {
        return "¿Qué son los datos cuantitativos?\n\n"
        . "Los datos cuantitativos son información numérica y medible que permite analizar situaciones de forma objetiva dentro de la empresa. Estos datos ayudan a identificar diferencias, tendencias o posibles desigualdades mediante estadísticas y métricas.\n\n"
        . "En este apartado se incluyen indicadores como:\n\n"
        . "Bajas laborales.\n"
        . "Formación recibida.\n"
        . "Excedencias.\n"
        . "Permisos retributivos.\n"
        . "Distribución de empleados por sexo, categoría o departamento.\n\n"
        . "Su finalidad es facilitar el análisis y la elaboración de informes relacionados con igualdad, recursos humanos y condiciones laborales.\n\n"
        . "¿Qué son los cuestionarios cualitativos?\n\n"
        . "Los cuestionarios cualitativos son herramientas utilizadas para recopilar opiniones, percepciones y experiencias de las personas trabajadoras sobre distintos aspectos del entorno laboral.\n\n"
        . "A diferencia de los datos cuantitativos, estos cuestionarios no se centran en números, sino en valorar situaciones relacionadas con:\n\n"
        . "Selección de personal.\n"
        . "Promoción profesional.\n"
        . "Formación.\n"
        . "Conciliación y corresponsabilidad.\n"
        . "Salud laboral.\n"
        . "Prevención del acoso sexual.\n"
        . "Violencia de género.\n"
        . "Comunicación e identidad corporativa.\n\n"
        . "Su objetivo es detectar posibles problemas, mejorar el clima laboral y promover la igualdad y el bienestar dentro de la organización.";
    }

function respuesta_perfil(string $texto): string {
    return "Para editar tus datos:\n- Abre 'Área Privada' en el menú lateral.\n- Entra en 'Mi Cuenta'.\n- Actualiza los campos y guarda los cambios.\n- Si algo no se guarda, revisa los campos obligatorios.";
}

function respuesta_documentacion(): string {
    return "Para gestionar documentación:\n- Entra en 'Documentación' desde el menú lateral.\n- Selecciona la empresa si el sistema lo solicita.\n- Desde ahí podrás ver, subir o descargar documentos disponibles.";
}

function respuesta_menu_cliente(): string {
    return "En la vista de cliente puedes usar:\n- Mis Empresas: ver empresas asignadas.\n- Registro Retributivo: subir y gestionar el registro por empresa.\n- Documentación: consultar y gestionar documentos.\n- Área Privada > Mi Cuenta: editar tus datos personales.\n- Mis Reuniones: revisar próximas reuniones.\n- Cerrar Sesión: salir de forma segura.";
}

function respuesta_cerrar_sesion(): string {
    return "Para cerrar sesión:\n- Haz clic en 'Cerrar Sesión' en el menú lateral.\n- El sistema cerrará tu sesión y volverás al acceso.\n- Si compartes equipo, cierra siempre sesión al terminar.";
}

function respuesta_reuniones_pendientes(mysqli $db, int $usuarioId): string {
    if ($usuarioId <= 0) {
        return 'No tengo acceso a tus reuniones ahora mismo.';
    }

    $texto = 'No tienes reuniones programadas próximamente.';
    $stmt = $db->prepare(
        'SELECT r.objetivo, r.fecha_reunion, r.hora_reunion, e.razon_social
     FROM reuniones r
     INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
     LEFT JOIN empresa e ON r.id_empresa = e.id_empresa
     WHERE ur.id_usuario = ? AND STR_TO_DATE(CONCAT(r.fecha_reunion, " ", r.hora_reunion), "%Y-%m-%d %H:%i") > NOW()
     ORDER BY r.fecha_reunion ASC LIMIT 5'
    );

    if (!$stmt) {
        return $texto;
    }

    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $lineas = ["Tus próximas reuniones son:"];
        while ($row = $res->fetch_assoc()) {
            $empresa = !empty($row['razon_social']) ? ' (' . $row['razon_social'] . ')' : '';
            $lineas[] = '- ' . date('d/m/Y', strtotime((string)$row['fecha_reunion'])) . ' a las ' . substr((string)$row['hora_reunion'], 0, 5) . ' | Objetivo: ' . (string)$row['objetivo'] . $empresa;
        }
        $texto = implode("\n", $lineas);
    }
    $stmt->close();

    return $texto;
}

function obtener_empresas_cliente(mysqli $db, int $usuarioId): array {
    $resultado = [];
    if ($usuarioId <= 0) {
        return $resultado;
    }

    $stmtEmpresas = $db->prepare(
        'SELECT DISTINCT e.id_empresa, e.razon_social
         FROM empresa e
         LEFT JOIN usuario_empresa ue ON e.id_empresa = ue.id_empresa
         WHERE (e.id_usuario = ? OR ue.id_usuario = ?)
         ORDER BY e.razon_social ASC'
    );

    if (!$stmtEmpresas) {
        return $resultado;
    }

    $stmtEmpresas->bind_param('ii', $usuarioId, $usuarioId);
    $stmtEmpresas->execute();
    $resEmpresas = $stmtEmpresas->get_result();
    while ($row = $resEmpresas->fetch_assoc()) {
        $resultado[] = [
            'empresaId' => (int)($row['id_empresa'] ?? 0),
            'empresaNombre' => (string)($row['razon_social'] ?? '')
        ];
    }
    $stmtEmpresas->close();

    return $resultado;
}

function formatear_lista_empresas(array $empresas): string {
    if (empty($empresas)) {
        return 'No tienes empresas asignadas ahora mismo.';
    }

    $lineas = [];
    foreach ($empresas as $item) {
        $empresaNombre = trim((string)($item['empresaNombre'] ?? ''));
        $empresaId = (int)($item['empresaId'] ?? 0);
        $lineas[] = '- ' . ($empresaNombre !== '' ? $empresaNombre : ('Empresa #' . $empresaId));
    }

    return implode("\n", $lineas);
}

function obtener_empresas_pendientes_rr_cliente(mysqli $db, int $usuarioId): array {
    $resultado = [];
    if ($usuarioId <= 0) {
        return $resultado;
    }

    $sql = "SELECT e.razon_social
            FROM empresa e
            LEFT JOIN usuario_empresa ue ON e.id_empresa = ue.id_empresa
            WHERE (e.id_usuario = ? OR ue.id_usuario = ?)
              AND NOT EXISTS (
                  SELECT 1
                  FROM archivos a
                  WHERE UPPER(TRIM(a.tipo)) = 'REGISTRO_RETRIBUTIVO'
                    AND a.id_empresa = e.id_empresa
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM archivos a
                  INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida
                  INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas
                  WHERE UPPER(TRIM(a.tipo)) = 'REGISTRO_RETRIBUTIVO'
                    AND ac.id_empresa = e.id_empresa
              )
            GROUP BY e.id_empresa
            ORDER BY e.razon_social ASC
            LIMIT 15";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return $resultado;
    }

    $stmt->bind_param('ii', $usuarioId, $usuarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $resultado[] = (string)($row['razon_social'] ?? '');
    }
    $stmt->close();

    return $resultado;
}

function construir_contexto_reuniones(mysqli $db, int $usuarioId): string {
    return respuesta_reuniones_pendientes($db, $usuarioId);
}

function construir_contexto_rr_pendiente(mysqli $db, int $usuarioId): string {
    $empresasFaltanRR = obtener_empresas_pendientes_rr_cliente($db, $usuarioId);
    if (empty($empresasFaltanRR)) {
        return 'No tienes empresas pendientes de subir el Registro Retributivo.';
    }

    return "Empresas a las que les falta subir el Registro Retributivo:\n- " . implode("\n- ", $empresasFaltanRR);
}

// Si el usuario cambia de tema y pregunta por el Registro Retributivo, salimos del flujo de reunión
if (!empty($userMessage) && es_consulta_definicion_rr($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_definicion_rr()
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_cuantitativos_cualitativos($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_cuantitativos_cualitativos()
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_rr($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_rr($userMessage)
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_menu_cliente($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_menu_cliente()
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_cerrar_sesion($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_cerrar_sesion()
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_perfil($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_perfil($userMessage)
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_documentacion($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_documentacion()
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_reuniones($userMessage)) {
    responder_json([
        'ok' => true,
        'reply' => respuesta_reuniones_pendientes($db, $usuarioId)
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_empresas($userMessage)) {
    $empresas = obtener_empresas_cliente($db, $usuarioId);
    responder_json([
        'ok' => true,
        'reply' => "Estas son tus empresas asignadas:\n" . formatear_lista_empresas($empresas),
        'companies' => $empresas
    ]);
    exit;
}

if (empty($userMessage)) {
    responder_json(['error' => 'Mensaje vacío'], 400);
    exit;
}

$reunionesText = construir_contexto_reuniones($db, $usuarioId);
$empresasFaltanText = construir_contexto_rr_pendiente($db, $usuarioId);

// Prompt de sistema orientado a cliente
$systemInstruction = "Eres un asistente virtual experto, formal y resolutivo de la plataforma 'Igualdad Consulting'.
Estás hablando con '{$nombreUsuario}' (Rol: {$rolUsuario}).

INFORMACIÓN EN TIEMPO REAL DEL USUARIO:
{$reunionesText}

ESTADO DE EMPRESAS Y REGISTRO RETRIBUTIVO:
{$empresasFaltanText}

VISTA ACTUAL DEL CLIENTE (menú lateral):
- Mis Empresas.
- Registro Retributivo.
- Documentación.
- Área Privada > Mi Cuenta.
- Mis Reuniones.
- Cerrar Sesión.

GUÍA DE RESPUESTA PARA ESTA VISTA:
1. Si preguntan qué pueden hacer, explica el menú lateral exactamente como aparece en la vista cliente.
2. Registro Retributivo: indicar que se gestiona desde esa sección seleccionando una empresa en 'Mis Empresas'.
3. Documentación: indicar que ahí se gestionan los documentos de la empresa (ver, subir, descargar según permisos).
4. Perfil: indicar la ruta 'Área Privada' > 'Mi Cuenta'.
5. Reuniones: usar exclusivamente la sección 'INFORMACIÓN EN TIEMPO REAL DEL USUARIO'; no inventar datos.
6. Si no conoces una acción concreta, responde de forma transparente y sugiere soporte técnico.
7. No hables de opciones que no estén en esta vista de cliente (por ejemplo, rutas antiguas como 'Mi Espacio' o tarjetas que no existen aquí).
8. Responde con pasos cortos, claros y en viñetas cuando sea útil.
¿Entendido?";

// Construir el array de contenidos para Gemini
$contents = [];

// Inyectar el prompt de sistema como contexto inicial de la conversación
$contents[] = [
    "role" => "user",
    "parts" => [["text" => $systemInstruction]]
];
$contents[] = [
    "role" => "model",
    "parts" => [["text" => "Entendido. Actuaré de forma formal, profesional y servicial para guiar al usuario en la plataforma de Igualdad Retributiva y Planes de Igualdad."]]
];

// Añadir el historial (últimos 5 mensajes)
$recentHistory = array_slice($history, -5);
foreach ($recentHistory as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        // Gemini usa 'model' en lugar de 'assistant'
        $role = ($msg['role'] === 'user') ? 'user' : 'model';
        $contents[] = [
            "role" => $role,
            "parts" => [
                ["text" => (string)$msg['content']]
            ]
        ];
    }
}

// Añadir el mensaje actual
$contents[] = [
    "role" => "user",
    "parts" => [
        ["text" => (string)$userMessage]
    ]
];

$postData = json_encode([
    "contents" => $contents,
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
]);

// URL del endpoint de Gemini (funciona en v1 y v1beta)
$url = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    responder_json(['error' => 'Error de conexión con el servidor de IA'], 500);
    curl_close($ch);
    exit;
}
curl_close($ch);

if ($httpCode >= 400) {
    $errorData = json_decode($response, true);
    $errorMessage = $errorData['error']['message'] ?? 'Desconocido';
    
    // Si la IA está sobrecargada, devolver un mensaje amigable como respuesta normal (código 200)
    if ($httpCode === 503 || $httpCode === 429 || strpos(strtolower($errorMessage), 'high demand') !== false || strpos(strtolower($errorMessage), 'overloaded') !== false) {
        responder_json(['reply' => 'La IA está temporalmente ocupada. Inténtalo en unos segundos.'], 200);
        exit;
    }
    
    responder_json(['error' => 'Error en la respuesta de Gemini: ' . $errorMessage], $httpCode);
    exit;
}

$responseData = json_decode($response, true);

// Extraer el texto de la respuesta de Gemini
$reply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'No pude generar una respuesta.';

responder_json(['reply' => $reply]);
