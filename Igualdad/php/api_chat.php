<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// Validar que el usuario esté logueado
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

// Leer los datos de entrada
$input = json_decode(file_get_contents('php://input'), true);

$userMessage = $input['message'] ?? '';
$history = $input['history'] ?? [];

// Datos del usuario y conexión a BD necesarios para acciones estructuradas
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$nombreUsuario = $_SESSION['user']['nombre_usuario'] ?? 'Usuario';
$rolUsuario = $_SESSION['user']['rol'] ?? 'Usuario';

$db = db();

$pendingMeeting = $_SESSION['pending_reunion_ai'] ?? null;

function es_respuesta_afirmativa(string $texto): bool {
    return (bool)preg_match('/^(si|sí|s|vale|ok|de acuerdo|claro|correcto)\b/i', trim($texto));
}

function es_respuesta_negativa(string $texto): bool {
    return (bool)preg_match('/^(no|n|sin tecnico|sin técnico|sin asignar|no quiero)\b/i', trim($texto));
}

function filtro_sql_rol_tecnico(string $aliasRol): string {
    return "REPLACE(REPLACE(UPPER(TRIM($aliasRol.nombre)), 'É', 'E'), 'Í', 'I') LIKE 'TECNICO%'";
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
    return (bool)preg_match('/\b(rr|registro retributivo|registro|subir rr|subir el rr|cargar rr|subir registro retributivo|gestionar rr|como subir el rr|como subo el rr|como subir registro retributivo|subir el registro)\b/i', $texto);
}

function es_consulta_word_final(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(word final|descargar word final|bajar word final|descargar el word final|word generado|documento final|descargar documento final|bajar documento final|como descargar el word final|como descargo el word final)\b/i', $texto);
}

function es_consulta_perfil(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(mis datos|editar mis datos|cambiar mis datos|modificar mis datos|editar perfil|cambiar perfil|modificar perfil|mi cuenta|area privada|perfil|como edito mis datos|como cambiar mis datos|como modificar mis datos)\b/i', $texto);
}

function es_consulta_reuniones(string $texto): bool {
    $texto = normalizar_texto_busqueda($texto);
    return (bool)preg_match('/\b(reunion|reuniones)\b.*\b(pendiente|pendientes|proxima|proximas|tengo|hay)\b|\b(tengo|hay)\b.*\b(reunion|reuniones)\b/i', $texto);
}

function respuesta_rr(string $texto): string {
    $textoNormalizado = normalizar_texto_busqueda($texto);
    if (preg_match('/\b(ya subi|ya subido|ya esta subido|ya lo subi|lo subi|subi el rr)\b/i', $textoNormalizado)) {
        return "Si ya has subido el Registro Retributivo, revisa en 'Mi Espacio' la tarjeta de 'Plan de Igualdad'.\n- Ahí debe aparecer la opción de continuar con los formularios.\n- Si no aparece, puede tardar un tiempo en procesarse.";
    }

    return "Para subir el Registro Retributivo:\n- Entra en 'Mi Espacio' o en el panel de la empresa.\n- Busca la tarjeta de 'Plan de Igualdad'.\n- Dentro de esa sección encontrarás la opción para subir el RR.\n- Cuando lo subas, se desbloquearán los formularios cuantitativos y cualitativos.";
}

function respuesta_word_final(string $texto): string {
    $textoNormalizado = normalizar_texto_busqueda($texto);
    if (preg_match('/\b(ya subi|ya subido|ya esta subido|ya lo subi|lo subi)\b/i', $textoNormalizado)) {
        return "Si ya subiste el Registro Retributivo y todavía no ves el Word final:\n- Espera unos días para que termine el proceso.\n- Revisa en 'Mi Espacio' la tarjeta de 'Descargar Word Final'.\n- Si sigue sin salir, dime la empresa y lo revisamos.";
    }

    return "Para descargar el Word final:\n- Entra en 'Mi Espacio'.\n- Busca la tarjeta de 'Descargar Word Final'.\n- Selecciona la empresa y descarga el documento.\n- Si no te deja descargarlo, revisa primero que el Registro Retributivo esté subido.";
}

function respuesta_perfil(string $texto): string {
    return "Para editar tus datos de perfil:\n- Entra en 'Área Privada' o 'Mi cuenta'.\n- Ahí podrás actualizar tus datos personales.\n- Si algo no se guarda, revisa que los campos obligatorios estén completos.";
}

function respuesta_reuniones_pendientes($db, int $usuarioId): string {
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

function responder_json(array $payload, int $status = 200): void {
    http_response_code($status);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        echo '{"error":"No se pudo generar la respuesta JSON."}';
        return;
    }
    echo $json;
}

// --- IA: Detección de intención de crear reunión ---
function extraer_datos_reunion($mensaje, $db, $usuarioId) {
    // Buscar empresas del usuario
    $empresas = [];
    $stmt = $db->prepare('SELECT e.id_empresa, e.razon_social FROM empresa e LEFT JOIN usuario_empresa ue ON e.id_empresa = ue.id_empresa WHERE (e.id_usuario = ? OR ue.id_usuario = ?)');
    $stmt->bind_param('ii', $usuarioId, $usuarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $empresas[$row['id_empresa']] = $row['razon_social'];
    }
    $stmt->close();
    // Buscar empresa mencionada
    $empresaId = null;
    $empresaNombre = null;

    // Buscar patrón 'Empresa: nombre' en el mensaje y usar búsqueda parcial en BD
    if (preg_match('/empresa\s*[:\-]?\s*([^\n\r]+)/i', $mensaje, $mEmpresa)) {
        $nombreBuscado = trim($mEmpresa[1]);
        if ($nombreBuscado !== '') {
            $like = '%' . $db->real_escape_string($nombreBuscado) . '%';
            $stmtE = $db->prepare('SELECT e.id_empresa, e.razon_social FROM empresa e LEFT JOIN usuario_empresa ue ON e.id_empresa = ue.id_empresa WHERE (e.id_usuario = ? OR ue.id_usuario = ?) AND UPPER(e.razon_social) LIKE UPPER(?) LIMIT 1');
            if ($stmtE) {
                $stmtE->bind_param('iis', $usuarioId, $usuarioId, $like);
                $stmtE->execute();
                $resE = $stmtE->get_result();
                if ($rowE = $resE->fetch_assoc()) {
                    $empresaId = (int)$rowE['id_empresa'];
                    $empresaNombre = $rowE['razon_social'];
                }
                $stmtE->close();
            }
        }
    }

    // Si no se encontró por el patrón, intentar match exacto con las empresas del usuario
    if ($empresaId === null) {
        foreach ($empresas as $id => $nombre) {
            if (stripos($mensaje, $nombre) !== false) {
                $empresaId = $id;
                $empresaNombre = $nombre;
                break;
            }
        }
    }

    // Si el usuario escribió un id de empresa explícito, usarlo cuando pertenezca a sus empresas
    if ($empresaId === null && preg_match('/\b(\d{1,6})\b/', $mensaje, $mEmpresaId)) {
        $idEmpresaPosible = (int)$mEmpresaId[1];
        if ($idEmpresaPosible > 0 && isset($empresas[$idEmpresaPosible])) {
            $empresaId = $idEmpresaPosible;
            $empresaNombre = $empresas[$idEmpresaPosible];
        }
    }

    // Último recurso: usar el primer bloque del mensaje antes de una coma o salto como posible empresa
    if ($empresaId === null) {
        $segmentoEmpresa = trim(preg_split('/[;,\n\r]+/', $mensaje, 2)[0] ?? '');
        if ($segmentoEmpresa !== '') {
            if (ctype_digit($segmentoEmpresa) && isset($empresas[(int)$segmentoEmpresa])) {
                $empresaId = (int)$segmentoEmpresa;
                $empresaNombre = $empresas[$empresaId];
            } else {
                foreach ($empresas as $id => $nombre) {
                    if ($segmentoEmpresa !== '' && (stripos($segmentoEmpresa, $nombre) !== false || stripos($nombre, $segmentoEmpresa) !== false)) {
                        $empresaId = $id;
                        $empresaNombre = $nombre;
                        break;
                    }
                }
            }
        }
    }

    // Búsqueda parcial en BD como último intento (mensajes tipo "ejemplo 3 ...")
    if ($empresaId === null) {
        $candidatosEmpresa = [];
        if (preg_match('/(?:empresa|ejemplo)\s*[:\-]?\s*([^\n\r,;]+)/i', $mensaje, $mEmpresaLibre)) {
            $candidatosEmpresa[] = trim($mEmpresaLibre[1]);
        }
        $primerBloque = trim(preg_split('/[;,\n\r]+/', $mensaje, 2)[0] ?? '');
        if ($primerBloque !== '') {
            $candidatosEmpresa[] = $primerBloque;
        }

        $candidatosEmpresa = array_values(array_unique(array_filter($candidatosEmpresa, static function ($v) {
            return is_string($v) && trim($v) !== '';
        })));

        foreach ($candidatosEmpresa as $textoEmpresa) {
            $likeEmpresa = '%' . $textoEmpresa . '%';
            $stmtE2 = $db->prepare('SELECT DISTINCT e.id_empresa, e.razon_social FROM empresa e LEFT JOIN usuario_empresa ue ON e.id_empresa = ue.id_empresa WHERE (e.id_usuario = ? OR ue.id_usuario = ?) AND UPPER(e.razon_social) LIKE UPPER(?) LIMIT 1');
            if (!$stmtE2) {
                continue;
            }
            $stmtE2->bind_param('iis', $usuarioId, $usuarioId, $likeEmpresa);
            $stmtE2->execute();
            $resE2 = $stmtE2->get_result();
            if ($rowE2 = $resE2->fetch_assoc()) {
                $empresaId = (int)$rowE2['id_empresa'];
                $empresaNombre = (string)$rowE2['razon_social'];
                $stmtE2->close();
                break;
            }
            $stmtE2->close();
        }
    }
    // Buscar fecha (formato dd/mm/yyyy o yyyy-mm-dd)
    $fecha = null;
    if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $mensaje, $m)) {
        $f = str_replace(['/', '-'], '-', $m[1]);
        $partes = explode('-', $f);
        if (strlen($partes[2]) === 4) $fecha = $partes[2] . '-' . str_pad($partes[1],2,'0',STR_PAD_LEFT) . '-' . str_pad($partes[0],2,'0',STR_PAD_LEFT);
        else $fecha = $partes[0] . '-' . str_pad($partes[1],2,'0',STR_PAD_LEFT) . '-' . str_pad($partes[2],2,'0',STR_PAD_LEFT);
    }
    // Buscar hora (formato HH:MM)
    $hora = null;
    if (preg_match('/(\d{1,2}:\d{2})/', $mensaje, $m)) {
        $hora = str_pad(explode(':', $m[1])[0],2,'0',STR_PAD_LEFT) . ':' . explode(':', $m[1])[1];
    }
    // Buscar asunto/objetivo
    $asunto = null;
    if (preg_match('/asunto:? ([^\n\r]+)/i', $mensaje, $m)) {
        $asunto = trim($m[1]);
    } else {
        // Si no hay "asunto:" explícito, usar la frase después de "reunión" o "programar"
        if (preg_match('/reuni[oó]n.*? (?:sobre|para|con) ([^\.,\n]+)/i', $mensaje, $m)) {
            $asunto = trim($m[1]);
        }
    }
    // Buscar técnico indicado explícitamente: 'Técnico: Nombre' o 'tecnico: Nombre'
    $tecnicoNombre = null;
    if (preg_match('/tecnic[oó]\s*[:\-]?\s*([^\n\r]+)/i', $mensaje, $mt)) {
        $tecnicoNombre = trim($mt[1]);
    }
    return [
        'empresaId' => $empresaId,
        'empresaNombre' => $empresaNombre,
        'fecha' => $fecha,
        'hora' => $hora,
        'asunto' => $asunto,
        'tecnicoNombre' => $tecnicoNombre
    ];
}

function crear_reunion_ia($db, $usuarioId, $datos) {
    if (empty($datos['empresaId']) || empty($datos['fecha']) || empty($datos['hora']) || empty($datos['asunto'])) {
        return [false, 'Faltan datos para crear la reunión. Indica empresa, fecha, hora y asunto.'];
    }
    // Posible override del técnico
    $idTecnicoOverride = isset($datos['idTecnico']) ? (int)$datos['idTecnico'] : (isset($datos['id_tecnico']) ? (int)$datos['id_tecnico'] : null);
    $tecnicoNombreOverride = isset($datos['tecnicoNombre']) ? trim($datos['tecnicoNombre']) : (isset($datos['tecnico_nombre']) ? trim($datos['tecnico_nombre']) : null);
    // Buscar técnico asignado a la empresa o usar override
        $idTecnico = null;
        if ($idTecnicoOverride > 0) {
        // validar que el id existe y es técnico
            $stmtChk = $db->prepare('SELECT u.id_usuario FROM usuario u INNER JOIN rol r ON r.id = u.rol_id WHERE u.id_usuario = ? AND ' . filtro_sql_rol_tecnico('r') . ' LIMIT 1');
        if ($stmtChk) {
            $stmtChk->bind_param('i', $idTecnicoOverride);
            $stmtChk->execute();
            if ($stmtChk->get_result()->fetch_assoc()) $idTecnico = $idTecnicoOverride;
            $stmtChk->close();
        }
    } elseif (!empty($tecnicoNombreOverride)) {
        $likeTech = '%' . $db->real_escape_string($tecnicoNombreOverride) . '%';
            $stmtTech = $db->prepare('SELECT u.id_usuario FROM usuario u INNER JOIN rol r ON r.id = u.rol_id WHERE UPPER(CONCAT(u.nombre_usuario, " ", COALESCE(u.nombre,"") ," ", COALESCE(u.apellidos,"") )) LIKE UPPER(?) AND ' . filtro_sql_rol_tecnico('r') . ' LIMIT 1');
        if ($stmtTech) {
            $stmtTech->bind_param('s', $likeTech);
            $stmtTech->execute();
            $resT = $stmtTech->get_result();
            if ($rowT = $resT->fetch_assoc()) $idTecnico = (int)$rowT['id_usuario'];
            $stmtTech->close();
        }
    }
    // Si no se proporcionó override, intentar buscar técnico asignado a la empresa
    if ($idTecnico === null) {
        $stmt = $db->prepare('SELECT ue.id_usuario FROM usuario_empresa ue INNER JOIN usuario u ON ue.id_usuario = u.id_usuario INNER JOIN rol r ON r.id = u.rol_id WHERE ue.id_empresa = ? AND ' . filtro_sql_rol_tecnico('r') . ' LIMIT 1');
        $stmt->bind_param('i', $datos['empresaId']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $idTecnico = (int)$row['id_usuario'];
        }
        $stmt->close();
    }
    if (!$idTecnico) {
        // Buscar si la empresa tiene un usuario principal técnico
        $stmt = $db->prepare('SELECT e.id_usuario FROM empresa e INNER JOIN usuario u ON e.id_usuario = u.id_usuario INNER JOIN rol r ON r.id = u.rol_id WHERE e.id_empresa = ? AND ' . filtro_sql_rol_tecnico('r') . ' LIMIT 1');
        $stmt->bind_param('i', $datos['empresaId']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $idTecnico = (int)$row['id_usuario'];
        }
        $stmt->close();
    }
    // No bloquear la creación si no existe técnico: permitimos crear la reunión y asociar al usuario.
    $sinTecnico = false;
    if (!$idTecnico) {
        $sinTecnico = true;
    }
    // Crear la reunión
    $stmt = $db->prepare('INSERT INTO reuniones (objetivo, hora_reunion, fecha_reunion, id_empresa) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sssi', $datos['asunto'], $datos['hora'], $datos['fecha'], $datos['empresaId']);
    $stmt->execute();
    $idReunion = (int)$stmt->insert_id;
    $stmt->close();
    // Asociar cliente
    $stmt = $db->prepare('INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)');
    $stmt->bind_param('ii', $usuarioId, $idReunion);
    $stmt->execute();
    $stmt->close();
    // Asociar técnico si existe y no se solicitó explícitamente que no se asigne
    if (!$sinTecnico && $idTecnico && $idTecnico !== $usuarioId) {
        $stmt = $db->prepare('INSERT INTO usuario_reunion (id_usuario, id_reunion) VALUES (?, ?)');
        $stmt->bind_param('ii', $idTecnico, $idReunion);
        $stmt->execute();
        $stmt->close();
    }

    $msgBase = 'Reunión creada correctamente para la empresa ' . ($datos['empresaNombre'] ?? $datos['empresaId']) . ' el ' . $datos['fecha'] . ' a las ' . $datos['hora'] . '.';
    if ($sinTecnico) {
        $msgBase .= ' No se encontró técnico asignado automáticamente; se notificará al equipo para que asigne o confirme un técnico.';
    }
    return [true, $msgBase];
}

function preparar_respuesta_tecnico(array $datos): array {
    $empresa = $datos['empresaNombre'] ?? $datos['empresaId'] ?? 'la empresa';
    return [
        'ok' => true,
        'reply' => 'He detectado los datos de la reunión para ' . $empresa . '. ¿Quieres añadir un técnico asignado? Responde Sí o No.',
        'options' => [
            ['label' => 'Sí, añadir técnico', 'value' => 'si_tecnico'],
            ['label' => 'No, sin técnico', 'value' => 'no_tecnico']
        ],
        'draft' => $datos
    ];
}

function formatear_lista_tecnicos(array $tecnicos): string {
    $lineas = [];
    foreach ($tecnicos as $tipo => $items) {
        if (empty($items)) {
            continue;
        }
        $lineas[] = strtoupper($tipo) . ':';
        foreach ($items as $item) {
            $nombre = trim((string)($item['nombre'] ?? ''));
            $username = trim((string)($item['username'] ?? ''));
            $lineas[] = '- ' . $item['id'] . ' | ' . ($nombre !== '' ? $nombre : $username) . ($username !== '' ? ' (' . $username . ')' : '');
        }
    }
    return implode("\n", $lineas);
}

// Obtener técnicos relacionados con una empresa: asignados y disponibles
function obtener_tecnicos_empresa($db, $empresaId) {
    $result = [
        'asignados' => [],
        'disponibles' => []
    ];
    if (empty($empresaId)) return $result;

    // Técnicos asignados a la empresa vía usuario_empresa o como usuario principal de la empresa
    $stmt = $db->prepare('SELECT DISTINCT u.id_usuario, u.nombre_usuario, COALESCE(u.nombre, "") AS nombre, COALESCE(u.apellidos, "") AS apellidos FROM usuario_empresa ue INNER JOIN usuario u ON ue.id_usuario = u.id_usuario INNER JOIN rol r ON r.id = u.rol_id WHERE ue.id_empresa = ? AND ' . filtro_sql_rol_tecnico('r') . ' UNION SELECT DISTINCT u2.id_usuario, u2.nombre_usuario, COALESCE(u2.nombre, "") AS nombre, COALESCE(u2.apellidos, "") AS apellidos FROM empresa e INNER JOIN usuario u2 ON e.id_usuario = u2.id_usuario INNER JOIN rol r2 ON r2.id = u2.rol_id WHERE e.id_empresa = ? AND ' . filtro_sql_rol_tecnico('r2') . '');
    if ($stmt) {
        $stmt->bind_param('ii', $empresaId, $empresaId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $result['asignados'][] = ['id' => (int)$row['id_usuario'], 'username' => $row['nombre_usuario'], 'nombre' => trim(($row['nombre'] . ' ' . $row['apellidos']))];
        }
        $stmt->close();
    }

    // Técnicos disponibles (todos con rol TECNICO) que no estén en asignados
    $stmtAll = $db->prepare('SELECT u.id_usuario, u.nombre_usuario, COALESCE(u.nombre, "") AS nombre, COALESCE(u.apellidos, "") AS apellidos FROM usuario u INNER JOIN rol r ON r.id = u.rol_id WHERE ' . filtro_sql_rol_tecnico('r'));
    $asignadosIds = array_column($result['asignados'], 'id');
    if ($stmtAll) {
        $stmtAll->execute();
        $resAll = $stmtAll->get_result();
        while ($row = $resAll->fetch_assoc()) {
            $id = (int)$row['id_usuario'];
            if (!in_array($id, $asignadosIds, true)) {
                $result['disponibles'][] = ['id' => $id, 'username' => $row['nombre_usuario'], 'nombre' => trim(($row['nombre'] . ' ' . $row['apellidos']))];
            }
        }
        $stmtAll->close();
    }

    return $result;
}

function obtener_empresas_tecnicos_usuario($db, int $usuarioId): array {
    $resultado = [];
    if ($usuarioId <= 0) {
        return $resultado;
    }

    $empresas = [];
    $stmtEmpresas = $db->prepare('SELECT DISTINCT e.id_empresa, e.razon_social FROM empresa e LEFT JOIN usuario_empresa ue ON e.id_empresa = ue.id_empresa WHERE (e.id_usuario = ? OR ue.id_usuario = ?) ORDER BY e.razon_social ASC');
    if ($stmtEmpresas) {
        $stmtEmpresas->bind_param('ii', $usuarioId, $usuarioId);
        $stmtEmpresas->execute();
        $resEmpresas = $stmtEmpresas->get_result();
        while ($row = $resEmpresas->fetch_assoc()) {
            $empresas[] = [
                'id_empresa' => (int)($row['id_empresa'] ?? 0),
                'razon_social' => (string)($row['razon_social'] ?? '')
            ];
        }
        $stmtEmpresas->close();
    }

    foreach ($empresas as $empresa) {
        $empresaId = (int)($empresa['id_empresa'] ?? 0);
        if ($empresaId <= 0) {
            continue;
        }

        $tecnicosEmpresa = obtener_tecnicos_empresa($db, $empresaId);
        $asignados = $tecnicosEmpresa['asignados'] ?? [];

        if (empty($asignados)) {
            $resultado[] = [
                'empresaId' => $empresaId,
                'empresaNombre' => (string)($empresa['razon_social'] ?? ''),
                'tecnicos' => []
            ];
            continue;
        }

        $resultado[] = [
            'empresaId' => $empresaId,
            'empresaNombre' => (string)($empresa['razon_social'] ?? ''),
            'tecnicos' => $asignados
        ];
    }

    return $resultado;
}

function formatear_lista_empresas_tecnicos(array $empresasTecnicos): string {
    if (empty($empresasTecnicos)) {
        return 'No se encontraron empresas con técnico asignado.';
    }

    $lineas = [];
    foreach ($empresasTecnicos as $item) {
        $empresaNombre = trim((string)($item['empresaNombre'] ?? ''));
        $empresaId = (int)($item['empresaId'] ?? 0);
        $lineas[] = 'EMPRESA: ' . ($empresaNombre !== '' ? $empresaNombre : ('#' . $empresaId));

        $tecnicos = $item['tecnicos'] ?? [];
        if (empty($tecnicos)) {
            $lineas[] = '- Sin técnico asignado';
            continue;
        }

        foreach ($tecnicos as $tecnico) {
            $nombre = trim((string)($tecnico['nombre'] ?? ''));
            $username = trim((string)($tecnico['username'] ?? ''));
            $lineas[] = '- Técnico: ' . $tecnico['id'] . ' | ' . ($nombre !== '' ? $nombre : $username) . ($username !== '' ? ' (' . $username . ')' : '');
        }
    }

    return implode("\n", $lineas);
}


// Detectar intención de crear reunión
$intencionCrearReunion = false;
// Si el usuario usa verbos explícitos
if (preg_match('/(programar|crear|agendar).*reuni[oó]n/i', $userMessage)) {
    $intencionCrearReunion = true;
} else {
    // Si el mensaje contiene fecha + hora + asunto (o objetivo) asumimos que quiere crear la reunión
    $tieneFecha = preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})|\d{4}-\d{2}-\d{2}/', $userMessage);
    $tieneHora = preg_match('/(\d{1,2}:\d{2})/', $userMessage);
    $tieneAsunto = preg_match('/\b(asunto|objetivo)[:\s]/i', $userMessage) || preg_match('/\breuni[oó]n\b.*?(?:sobre|para|con)\s+[\w\s\-\,\.]+/i', $userMessage);
    if ($tieneFecha && $tieneHora && $tieneAsunto) {
        $intencionCrearReunion = true;
    }
}

// Si el usuario quiere crear una NUEVA reunión, siempre tiene prioridad sobre cualquier estado pendiente
if ($intencionCrearReunion) {
    $datos = extraer_datos_reunion($userMessage, $db, $usuarioId);
    if (is_array($pendingMeeting) && !empty($pendingMeeting['sin_tecnico_confirmado'])) {
        $datos = array_merge($pendingMeeting, $datos);
        $datos['id_tecnico'] = null;
        $datos['tecnicoNombre'] = null;
        list($ok, $msg) = crear_reunion_ia($db, $usuarioId, $datos);
        if ($ok) {
            unset($_SESSION['pending_reunion_ai']);
        } else {
            $_SESSION['pending_reunion_ai'] = array_merge($datos, ['sin_tecnico_confirmado' => true]);
        }
        echo json_encode(['ok' => $ok, 'reply' => $msg]);
        exit;
    }

    unset($_SESSION['pending_reunion_ai']); // Resetear estado previo para empezar limpio
    $tieneTecnico = !empty($datos['idTecnico']) || !empty($datos['tecnicoNombre']);
    if (!$tieneTecnico) {
        $_SESSION['pending_reunion_ai'] = $datos;
        echo json_encode(preparar_respuesta_tecnico($datos));
        exit;
    }
    list($ok, $msg) = crear_reunion_ia($db, $usuarioId, $datos);
    unset($_SESSION['pending_reunion_ai']);
    echo json_encode(['ok' => $ok, 'reply' => $msg]);
    exit;
}

// Si el usuario cambia de tema y pregunta por el Registro Retributivo, salimos del flujo de reunión
if (!empty($userMessage) && es_consulta_rr($userMessage)) {
    unset($_SESSION['pending_reunion_ai']);
    echo json_encode([
        'ok' => true,
        'reply' => respuesta_rr($userMessage)
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_word_final($userMessage)) {
    unset($_SESSION['pending_reunion_ai']);
    echo json_encode([
        'ok' => true,
        'reply' => respuesta_word_final($userMessage)
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_perfil($userMessage)) {
    unset($_SESSION['pending_reunion_ai']);
    echo json_encode([
        'ok' => true,
        'reply' => respuesta_perfil($userMessage)
    ]);
    exit;
}

if (!empty($userMessage) && es_consulta_reuniones($userMessage)) {
    unset($_SESSION['pending_reunion_ai']);
    responder_json([
        'ok' => true,
        'reply' => respuesta_reuniones_pendientes($db, $usuarioId)
    ]);
    exit;
}

// Resolver respuesta pendiente sobre si asignar técnico o crear la reunión sin técnico
if (is_array($pendingMeeting) && !empty($userMessage)) {
    if (es_respuesta_afirmativa($userMessage)) {
        $empresaPendiente = (int)($pendingMeeting['empresaId'] ?? 0);
        if ($empresaPendiente <= 0) {
            $empresasTecnicos = obtener_empresas_tecnicos_usuario($db, $usuarioId);
            $listaEmpresasTecnicos = formatear_lista_empresas_tecnicos($empresasTecnicos);
            responder_json([
                'ok' => true,
                'reply' => "Necesito que me indiques primero la empresa para listar sus técnicos asignados.\n\n" . $listaEmpresasTecnicos . "\n\nEscribe el nombre o id de la empresa y te enseño sus técnicos.",
                'companies_technicians' => $empresasTecnicos
            ]);
            exit;
        }

        $tecnicos = obtener_tecnicos_empresa($db, $empresaPendiente);
        $tecnicoText = formatear_lista_tecnicos($tecnicos);
        $_SESSION['pending_reunion_ai'] = array_merge($pendingMeeting, ['stage' => 'awaiting_technician_choice']);

        $replyMsg = 'Perfecto. Aquí tienes los técnicos disponibles para esa empresa:';
        if ($tecnicoText !== '') {
            $replyMsg .= "\n\n" . $tecnicoText;
        } else {
            $replyMsg .= "\n\n(No se encontraron técnicos asignados.)";
        }
        $replyMsg .= "\n\nResponde con el **id** del técnico que quieres asignar, o escribe \"sin técnico\" para programarla sin asignación.";

        responder_json([
            'ok' => true,
            'reply' => $replyMsg,
            'technicians' => $tecnicos,
            'technicians_text' => $tecnicoText
        ]);
        exit;
    }

    if (es_respuesta_negativa($userMessage)) {
        $datosPendientes = $pendingMeeting;
        $datosPendientes['id_tecnico'] = null;
        $datosPendientes['tecnicoNombre'] = null;
        $datosPendientes['sin_tecnico_confirmado'] = true;
        list($ok, $msg) = crear_reunion_ia($db, $usuarioId, $datosPendientes);
        if ($ok) {
            unset($_SESSION['pending_reunion_ai']);
        } else {
            $_SESSION['pending_reunion_ai'] = $datosPendientes;
        }
        echo json_encode(['ok' => $ok, 'reply' => $msg]);
        exit;
    }

    if (($pendingMeeting['stage'] ?? '') === 'awaiting_technician_choice') {
        $tecnicos = obtener_tecnicos_empresa($db, (int)($pendingMeeting['empresaId'] ?? 0));
        $texto = trim($userMessage);
        $idElegido = null;
        if (preg_match('/\b(\d+)\b/', $texto, $mId)) {
            $idElegido = (int)$mId[1];
        }

        $tecnicosTodos = array_merge($tecnicos['asignados'] ?? [], $tecnicos['disponibles'] ?? []);
        if ($idElegido === null) {
            foreach ($tecnicosTodos as $tec) {
                if ($tec['nombre'] !== '' && stripos($texto, $tec['nombre']) !== false) {
                    $idElegido = (int)$tec['id'];
                    break;
                }
                if (!empty($tec['username']) && stripos($texto, $tec['username']) !== false) {
                    $idElegido = (int)$tec['id'];
                    break;
                }
            }
        }

        if ($idElegido === null) {
            $tecListText = formatear_lista_tecnicos($tecnicos);
            $retryMsg = 'No he entendido el técnico seleccionado.';
            if ($tecListText !== '') {
                $retryMsg .= "\n\n" . $tecListText;
            }
            $retryMsg .= "\n\nResponde con el id del técnico o escribe \"sin técnico\".";
            responder_json([
                'ok' => true,
                'reply' => $retryMsg,
                'technicians' => $tecnicos,
                'technicians_text' => $tecListText
            ]);
            exit;
        }

        $datosPendientes = $pendingMeeting;
        $datosPendientes['id_tecnico'] = $idElegido;
        $datosPendientes['tecnicoNombre'] = null;
        unset($_SESSION['pending_reunion_ai']);

        list($ok, $msg) = crear_reunion_ia($db, $usuarioId, $datosPendientes);
        echo json_encode(['ok' => $ok, 'reply' => $msg]);
        exit;
    }

    // Si sigue respondiendo con más datos, actualizamos el borrador sin perder contexto
    $_SESSION['pending_reunion_ai'] = array_merge($pendingMeeting, extraer_datos_reunion($userMessage, $db, $usuarioId));
    echo json_encode([
        'ok' => true,
        'reply' => 'Aún me falta saber si quieres asignar técnico. Responde Sí para ver técnicos disponibles o No para programarla sin técnico.',
        'options' => [
            ['label' => 'Sí, añadir técnico', 'value' => 'si_tecnico'],
            ['label' => 'No, sin técnico', 'value' => 'no_tecnico']
        ]
    ]);
    exit;
}

// Soporte para creación estructurada de reunión vía payload JSON (por ejemplo desde UI/chatbot)
if ((!empty($input['action']) && $input['action'] === 'create_meeting') || !empty($input['form'])) {
    $form = $input['form'] ?? [];
    $datos = [
        'empresaId' => isset($form['id_empresa']) ? (int)$form['id_empresa'] : (int)($form['empresa_id'] ?? 0),
        'empresaNombre' => $form['empresa_nombre'] ?? $form['empresa'] ?? null,
        'fecha' => $form['fecha'] ?? null,
        'hora' => $form['hora'] ?? null,
        'asunto' => $form['asunto'] ?? $form['objetivo'] ?? null
    ];
    $tieneTecnico = !empty($datos['id_tecnico']) || !empty($datos['tecnicoNombre']) || !empty($datos['tecnico_nombre']);
    if (!$tieneTecnico) {
        $_SESSION['pending_reunion_ai'] = $datos;
        echo json_encode(preparar_respuesta_tecnico($datos));
        exit;
    }

    list($ok, $msg) = crear_reunion_ia($db, $usuarioId, $datos);
    unset($_SESSION['pending_reunion_ai']);
    echo json_encode(['ok' => $ok, 'reply' => $msg]);
    exit;
}

// Petición para listar técnicos disponibles/asignados para una empresa
if (!empty($input['action']) && $input['action'] === 'list_technicians') {
    $empresaId = isset($input['id_empresa']) ? (int)$input['id_empresa'] : (isset($input['form']['id_empresa']) ? (int)$input['form']['id_empresa'] : 0);
    if ($empresaId > 0) {
        $techs = obtener_tecnicos_empresa($db, $empresaId);
        echo json_encode(['ok' => true, 'technicians' => $techs]);
        exit;
    }

    $empresasTecnicos = obtener_empresas_tecnicos_usuario($db, $usuarioId);
    echo json_encode([
        'ok' => true,
        'companies_technicians' => $empresasTecnicos,
        'reply' => formatear_lista_empresas_tecnicos($empresasTecnicos)
    ]);
    exit;
}

// Petición textual para listar empresas y técnicos asignados
if (!empty($userMessage) && preg_match('/\b(lista|listar|mostrar|ver)\b.*\b(empresas?|empresa)\b.*\b(tecnic[oa]s?|t[eé]cnic[oa])\b|\b(tecnic[oa]s?|t[eé]cnic[oa])\b.*\b(empresas?|empresa)\b/i', $userMessage)) {
    $empresasTecnicos = obtener_empresas_tecnicos_usuario($db, $usuarioId);
    echo json_encode([
        'ok' => true,
        'reply' => formatear_lista_empresas_tecnicos($empresasTecnicos),
        'companies_technicians' => $empresasTecnicos
    ]);
    exit;
}

// (La intención de crear reunión nueva ya se resuelve antes del bloque pendingMeeting)

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

// Obtener datos del usuario actual
$usuarioId = (int)($_SESSION['user']['id_usuario'] ?? 0);
$nombreUsuario = $_SESSION['user']['nombre_usuario'] ?? 'Usuario';
$rolUsuario = $_SESSION['user']['rol'] ?? 'Usuario';

// Conectar a la base de datos para obtener contexto real
$db = db();
$reunionesText = "No tienes reuniones programadas próximamente.";

if ($usuarioId > 0) {
    $stmtReuniones = $db->prepare(
        'SELECT r.objetivo, r.fecha_reunion, r.hora_reunion, e.razon_social
         FROM reuniones r
         INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
         LEFT JOIN empresa e ON r.id_empresa = e.id_empresa
         WHERE ur.id_usuario = ? AND STR_TO_DATE(CONCAT(r.fecha_reunion, " ", r.hora_reunion), "%Y-%m-%d %H:%i") > NOW()
         ORDER BY r.fecha_reunion ASC LIMIT 5'
    );
    if ($stmtReuniones) {
        $stmtReuniones->bind_param('i', $usuarioId);
        $stmtReuniones->execute();
        $resReuniones = $stmtReuniones->get_result();
        if ($resReuniones->num_rows > 0) {
            $reunionesText = "Tus próximas reuniones programadas son:\n";
            while ($row = $resReuniones->fetch_assoc()) {
                $empresa = $row['razon_social'] ? " ({$row['razon_social']})" : "";
                $reunionesText .= "- " . date('d/m/Y', strtotime($row['fecha_reunion'])) . " a las " . substr($row['hora_reunion'], 0, 5) . " | Objetivo: " . $row['objetivo'] . $empresa . "\n";
            }
        }
        $stmtReuniones->close();
    }
}

// Consultar empresas que faltan por subir el Registro Retributivo
$empresasFaltanRR = [];
if ($usuarioId > 0) {
    if (in_array(strtoupper($rolUsuario), ['ADMINISTRADOR', 'STAFF'])) {
        $sqlEmpresas = "SELECT e.razon_social FROM empresa e WHERE NOT EXISTS (SELECT 1 FROM archivos a WHERE UPPER(TRIM(a.tipo)) = 'REGISTRO_RETRIBUTIVO' AND a.id_empresa = e.id_empresa) AND NOT EXISTS (SELECT 1 FROM archivos a INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas WHERE UPPER(TRIM(a.tipo)) = 'REGISTRO_RETRIBUTIVO' AND ac.id_empresa = e.id_empresa) LIMIT 10";
        $stmtEmp = $db->prepare($sqlEmpresas);
        if ($stmtEmp) {
            $stmtEmp->execute();
            $res = $stmtEmp->get_result();
            while ($row = $res->fetch_assoc()) {
                $empresasFaltanRR[] = $row['razon_social'];
            }
            $stmtEmp->close();
        }
    } else {
        $sqlEmpresas = "SELECT e.razon_social FROM empresa e LEFT JOIN usuario_empresa ue ON e.id_empresa = ue.id_empresa WHERE (e.id_usuario = ? OR ue.id_usuario = ?) AND NOT EXISTS (SELECT 1 FROM archivos a WHERE UPPER(TRIM(a.tipo)) = 'REGISTRO_RETRIBUTIVO' AND a.id_empresa = e.id_empresa) AND NOT EXISTS (SELECT 1 FROM archivos a INNER JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida INNER JOIN areas_contratadas ac ON ac.id_areas_contratadas = cm.id_areas_contratadas WHERE UPPER(TRIM(a.tipo)) = 'REGISTRO_RETRIBUTIVO' AND ac.id_empresa = e.id_empresa) GROUP BY e.id_empresa LIMIT 15";
        $stmtEmp = $db->prepare($sqlEmpresas);
        if ($stmtEmp) {
            $stmtEmp->bind_param('ii', $usuarioId, $usuarioId);
            $stmtEmp->execute();
            $res = $stmtEmp->get_result();
            while ($row = $res->fetch_assoc()) {
                $empresasFaltanRR[] = $row['razon_social'];
            }
            $stmtEmp->close();
        }
    }
}

$empresasFaltanText = "No tienes empresas pendientes de subir el Registro Retributivo.";
if (count($empresasFaltanRR) > 0) {
    $empresasFaltanText = "Empresas a las que les falta subir el Registro Retributivo:\n- " . implode("\n- ", $empresasFaltanRR);
    if (count($empresasFaltanRR) >= 10 && in_array(strtoupper($rolUsuario), ['ADMINISTRADOR', 'STAFF'])) {
        $empresasFaltanText .= "\n(Mostrando solo 10 empresas debido a tu rol, pero pueden haber más).";
    }
}

// Prompt hiper-específico de la aplicación
$systemInstruction = "Eres un asistente virtual experto, formal y resolutivo de la plataforma 'Igualdad Consulting'.
Estás hablando con '{$nombreUsuario}' (Rol: {$rolUsuario}).

INFORMACIÓN EN TIEMPO REAL DEL USUARIO:
{$reunionesText}

ESTADO DE EMPRESAS Y REGISTRO RETRIBUTIVO:
{$empresasFaltanText}

GUÍA DE USO DE LA PLATAFORMA (Usa esto para responder):
1. **Registro Retributivo (R.R.)**: Para subir o gestionar el Registro Retributivo, el usuario debe acceder al panel de la empresa o a 'Mi Espacio'. Allí vera una tarjeta que pone plan de igualdad, dentro de este podra subir el registro retributivo, ademas una vez subido el registro retributivo, se desbloquearan unos formularios cuantitativos y cualitativos. Una vez subido, el sistema procesará la información para generar el cuadro de porcentajes o el documento WORD.
2. **Reuniones**: Si el usuario te pregunta por sus reuniones, lee la sección 'INFORMACIÓN EN TIEMPO REAL DEL USUARIO' que te he proporcionado arriba. No inventes datos. Si dice que no hay, dile que no tiene reuniones pendientes.
3. **Modificar Información**: Los datos de tu perfil se pueden actualizar desde el botón 'Area Privada', 'Mi cuenta' en el panel de control.
4. **Plan de Igualdad**: Es el documento final que se genera tras completar el diagnóstico y las auditorías retributivas.
5. **Comportamiento**: Sé directo, usa viñetas para explicar pasos y mantén siempre un tono educado. Si no tienes la respuesta, sugiere contactar con soporte técnico o con su técnico asignado.
6. **Descargar Word Final**: Una vez completado todo el diagnostico y las auditorias retributivas, se podrá descargar el word final, esta situado en la seccion de 'Mi Espacio', en una tarjeta que pone descargar Word Final, deberas seleccionar la empresa y descargar el docuemnto, es posible que si no has subido el registro retributivo  no puedas descargarlo, si el registro esta subido y todavia no puedes descargarlo deberas esperar unos dias.
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
foreach ($recentHistory as $msg) 
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
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión con el servidor de IA']);
    curl_close($ch);
    exit;
}
curl_close($ch);

if ($httpCode >= 400) {
    $errorData = json_decode($response, true);
    $errorMessage = $errorData['error']['message'] ?? 'Desconocido';
    
    // Si la IA está sobrecargada, devolver un mensaje amigable como respuesta normal (código 200)
    if ($httpCode === 503 || $httpCode === 429 || strpos(strtolower($errorMessage), 'high demand') !== false || strpos(strtolower($errorMessage), 'overloaded') !== false) {
        http_response_code(200);
        echo json_encode(['reply' => 'La IA está temporalmente ocupada. Inténtalo en unos segundos.']);
        exit;
    }
    
    http_response_code($httpCode);
    echo json_encode(['error' => 'Error en la respuesta de Gemini: ' . $errorMessage]);
    exit;
}

$responseData = json_decode($response, true);

// Extraer el texto de la respuesta de Gemini
$reply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'No pude generar una respuesta.';

echo json_encode(['reply' => $reply]);




