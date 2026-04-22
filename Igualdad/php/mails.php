<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/helpers.php';

function correo_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/mail.php';
    }

    return $config;
}

function correo_url_login(): string
{
    return 'https://igualdad.consultingsigloxxi.es/Igualdad/model/admin.php?view=menu';
}

function correo_url_base(): string
{
    if (function_exists('app_base_url')) {
        $detectedBaseUrl = trim((string)app_base_url());
        $detectedHost = (string)parse_url($detectedBaseUrl, PHP_URL_HOST);

        if ($detectedBaseUrl !== '' && $detectedHost !== '') {
            return rtrim($detectedBaseUrl, '/');
        }
    }

    return 'https://igualdad.consultingsigloxxi.es/Igualdad';
}

function correo_crear_mailer(): PHPMailer
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $mailConfig = correo_config();
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Host = (string)$mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$mailConfig['username'];
    $mail->Password = (string)$mailConfig['password'];

    if (($mailConfig['secure'] ?? 'none') !== 'none') {
        $mail->SMTPSecure = (string)$mailConfig['secure'];
    }

    $mail->Port = (int)$mailConfig['port'];
    $mail->setFrom((string)$mailConfig['from_email'], (string)$mailConfig['from_name']);
    $mail->isHTML(true);

    return $mail;
}

function correo_enviar_html(
    string $emailDestino,
    string $nombreDestino,
    string $asunto,
    string $html,
    string $textoPlano = '',
    ?callable $configurar = null
): void {
    $mail = correo_crear_mailer();

    if ($configurar !== null) {
        $configurar($mail);
    }

    $mail->addAddress($emailDestino, $nombreDestino);
    $mail->Subject = $asunto;
    $mail->Body = $html;

    if ($textoPlano !== '') {
        $mail->AltBody = $textoPlano;
    }

    $mail->send();
}

function correo_formatear_lista(array $items): string
{
    $items = array_values(array_filter(array_map('trim', $items), static fn(string $item): bool => $item !== ''));
    $count = count($items);

    if ($count === 0) {
        return '';
    }

    if ($count === 1) {
        return $items[0];
    }

    if ($count === 2) {
        return $items[0] . ' y ' . $items[1];
    }

    $lastItem = array_pop($items);
    return implode(', ', $items) . ' y ' . $lastItem;
}

function correo_normalizar_servicio(string $tipoContrato): string
{
    $servicio = strtoupper(trim($tipoContrato));

    if ($servicio === 'PLAN IGUALDAD') {
        return 'Plan de Igualdad';
    }

    if ($servicio === 'MANTENIMIENTO') {
        return 'Mantenimiento';
    }

    if ($servicio === '' || $servicio === 'SIN CONTRATO') {
        return '';
    }

    return $servicio;
}

function correo_obtener_empresas_asignadas(mysqli $db, int $userId): array
{
    $empresas = [];

    $stmt = $db->prepare(
        "SELECT e.razon_social, COALESCE(( SELECT ce.tipo_contrato FROM contrato_empresa ce WHERE ce.id_empresa = e.id_empresa ORDER BY ce.id_contrato_empresa DESC
                LIMIT 1), 'SIN CONTRATO') AS tipo_contrato FROM usuario_empresa ue INNER JOIN empresa e ON e.id_empresa = ue.id_empresa
     WHERE ue.id_usuario = ? ORDER BY e.razon_social ASC"
    );

    if (!$stmt) {
        return $empresas;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $empresas[] = [
            'razon_social' => trim((string)($row['razon_social'] ?? '')),
            'tipo_contrato' => trim((string)($row['tipo_contrato'] ?? 'SIN CONTRATO')),
        ];
    }

    $stmt->close();

    return $empresas;
}

function correo_tiene_reunion_subir_rr(mysqli $db, int $userId): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM reuniones r
         INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
         WHERE ur.id_usuario = ?
             AND r.objetivo = 'Subir R.R'
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $hasMeeting = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $hasMeeting;
}

function correo_tiene_registro_retributivo(mysqli $db, int $userId): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM archivos a
         INNER JOIN usuario_empresa ue ON ue.id_empresa = a.id_empresa
         WHERE ue.id_usuario = ?
             AND a.tipo = 'REGISTRO_RETRIBUTIVO'
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $hasRegistro = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $hasRegistro;
}

function correo_secciones_empresa_servicio(array $empresasAsignadas): array
{
    $companyNames = [];
    $serviceNames = [];

    foreach ($empresasAsignadas as $companyData) {
        $companyName = trim((string)($companyData['razon_social'] ?? ''));
        if ($companyName !== '') {
            $companyNames[] = $companyName;
        }

        $serviceName = correo_normalizar_servicio((string)($companyData['tipo_contrato'] ?? 'SIN CONTRATO'));
        if ($serviceName !== '') {
            $serviceNames[] = $serviceName;
        }
    }

    $companyNames = array_values(array_unique($companyNames));
    $serviceNames = array_values(array_unique($serviceNames));

    $companyLabel = correo_formatear_lista($companyNames);
    $serviceLabel = correo_formatear_lista($serviceNames);

    return [
        'company' => $companyLabel !== '' ? $companyLabel : 'su empresa asignada',
        'service' => $serviceLabel !== '' ? $serviceLabel : 'el servicio correspondiente',
    ];
}

function correo_enviar_alta_usuario(string $email, string $username, string $resetLink, array $assignedCompanies): void
{
    $sections = correo_secciones_empresa_servicio($assignedCompanies);
    $usernameHtml = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $companySection = htmlspecialchars($sections['company'], ENT_QUOTES, 'UTF-8');
    $serviceSection = htmlspecialchars($sections['service'], ENT_QUOTES, 'UTF-8');
    $resetLinkHtml = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    $body = '
      <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
          <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <p>Estimado/a ' . $usernameHtml . ',</p>

            <p>Le informamos de que ha sido dado/a de alta en la plataforma <strong>My Equality by Cefora Business Consulting</strong> para la gestión del servicio de ' . $serviceSection . ' correspondiente a ' . $companySection . '.</p>

            <p>Para comenzar, debe acceder a la plataforma a través del siguiente enlace y establecer su contraseña de acceso:</p>

            <p><a href="' . $resetLinkHtml . '">' . $resetLinkHtml . '</a></p>

            <p>Por motivos de seguridad, este enlace es personal y tiene una validez limitada.</p>

            <p>Una vez haya creado su contraseña y accedido a la plataforma, deberá:</p>

            <ul>
              <li>Revisar la documentación disponible</li>
              <li>En caso de disponer de registro retributivo, subir el archivo correspondiente</li>
              <li>Si no dispone de él, podrá descargar la plantilla, completarla y volver a subirla</li>
              <li>Completar los datos solicitados en la aplicación (información cuantitativa y otros formularios necesarios)</li>
            </ul>

            <p>El equipo técnico realizará el seguimiento del proceso y se pondrá en contacto con usted en caso de ser necesario</p>
            <p>Para cualquier duda o incidencia, puede contactar con nosotros respondiendo a este correo.</p>

            <p>Un saludo,</p>
            <p><strong>Equipo My Equality</strong></p>
          </div>
        </body>
      </html>
    ';

    correo_enviar_html(
        $email,
        $username,
        'Alta en My Equality – Acceso a la plataforma',
        $body
    );
}

function correo_enviar_alta_usuario_tecnico(string $email, string $username, string $resetLink): void
{
    $usernameHtml = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $resetLinkHtml = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    $body = '
      <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
          <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <p>Estimado/a ' . $usernameHtml . ',</p>

            <p>Le informamos de que ha sido dado/a de alta en la plataforma <strong>My Equality by Cefora Business Consulting</strong>.</p>

            <p>Para comenzar, debe acceder a la plataforma a través del siguiente enlace y establecer su contraseña de acceso:</p>

            <p><a href="' . $resetLinkHtml . '">' . $resetLinkHtml . '</a></p>

            <p>Por motivos de seguridad, este enlace es personal y tiene una validez limitada.</p>

            <p>Un saludo,</p>
            <p><strong>Equipo My Equality</strong></p>
          </div>
        </body>
      </html>
    ';

    correo_enviar_html(
        $email,
        $username,
        'Alta en My Equality – Acceso a la plataforma',
        $body
    );
}

function correo_enviar_restablecimiento_contrasena(string $email, string $username, string $resetLink): void
{
    $body = '
      <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
          <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #667eea;">Hola ' . h($username) . ',</h2>
            <p>Hemos recibido una solicitud para restablecer tu contrasena en <strong>Consultoria Igualdad</strong>.</p>
            <p>Haz clic en el siguiente enlace para crear una nueva contrasena:</p>
            <div style="text-align: center; margin: 30px 0;">
              <a href="' . h($resetLink) . '"
                 style="display: inline-block; padding: 12px 30px; background-color: #667eea; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Restablecer Contrasena
              </a>
            </div>
            <p style="color: #666;">Si no funciona el boton, copia y pega este enlace en tu navegador:</p>
            <p style="background-color: #f5f5f5; padding: 10px; border-left: 4px solid #667eea; word-break: break-all;">
              ' . h($resetLink) . '
            </p>
            <p style="color: #999; font-size: 12px;"><strong>Nota:</strong> Este enlace es valido durante 24 horas.</p>
            <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
            <p style="color: #999; font-size: 12px;">Si no solicitaste este cambio, puedes ignorar este email.</p>
          </div>
        </body>
      </html>
    ';

    correo_enviar_html(
        $email,
        $username,
        'Restablece tu contrasena - Consultoria Igualdad',
        $body
    );
}

function correo_enviar_contacto_tecnico_empresa(
    string $emailDestino,
    string $empresaNombre,
    string $tecnicoNombre,
    string $tecnicoEmail,
    string $asunto,
    string $mensaje
): void {
    $mensajeSeguroHtml = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));
    $tecnicoHtml = htmlspecialchars($tecnicoNombre, ENT_QUOTES, 'UTF-8');
    $empresaHtml = htmlspecialchars($empresaNombre, ENT_QUOTES, 'UTF-8');
    $asuntoHtml = htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8');
    $tecnicoEmailHtml = $tecnicoEmail !== '' ? ' (' . htmlspecialchars($tecnicoEmail, ENT_QUOTES, 'UTF-8') . ')' : '';

    $body = '
      <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
          <div style="max-width: 680px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #1f4aa2;">Nuevo mensaje de tu tecnico asignado</h2>
            <p><strong>Empresa:</strong> ' . $empresaHtml . '</p>
            <p><strong>Tecnico:</strong> ' . $tecnicoHtml . $tecnicoEmailHtml . '</p>
            <p><strong>Asunto:</strong> ' . $asuntoHtml . '</p>
            <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
            <div>' . $mensajeSeguroHtml . '</div>
          </div>
        </body>
      </html>
    ';

    $altBody = "Tecnico: {$tecnicoNombre}" . ($tecnicoEmail !== '' ? " ({$tecnicoEmail})" : '') . "\nEmpresa: {$empresaNombre}\nAsunto: {$asunto}\n\n{$mensaje}";

    correo_enviar_html(
        $emailDestino,
        $empresaNombre,
        '[Contacto tecnico] ' . $asunto,
        $body,
        $altBody,
        static function (PHPMailer $mail) use ($tecnicoEmail, $tecnicoNombre): void {
            if ($tecnicoEmail !== '' && filter_var($tecnicoEmail, FILTER_VALIDATE_EMAIL) !== false) {
                $mail->addReplyTo($tecnicoEmail, $tecnicoNombre);
            }
        }
    );
}

function correo_enviar_recordatorio_registro_retributivo(string $email, string $nombre, string $companySection, string $serviceSection): void
{
    $body = '
        <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                    <p>Estimado/a ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ',</p>

                    <p>Nos ponemos en contacto con usted para recordarle que dispone de acceso a la plataforma <strong>My Equality by Cefora Business Consulting</strong> correspondiente al servicio de <strong>' . htmlspecialchars($serviceSection, ENT_QUOTES, 'UTF-8') . '</strong> de la empresa <strong>' . htmlspecialchars($companySection, ENT_QUOTES, 'UTF-8') . '</strong>.</p>

                    <p>Hemos detectado que, hasta la fecha, <strong>no se ha accedido a la plataforma y/o no se ha subido el registro retributivo</strong>, necesario para continuar con el desarrollo del servicio.</p>

                    <p>Le recomendamos acceder a la mayor brevedad posibles a través del siguiente enlace:</p>

                    <p style="text-align: center; margin: 30px 0;">
                        <a href="' . htmlspecialchars(correo_url_login(), ENT_QUOTES, 'UTF-8') . '" style="display: inline-block; padding: 12px 30px; background-color: #667eea; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                            Acceder a la plataforma
                        </a>
                    </p>

                    <p><strong>Próximos pasos a realizar:</strong></p>

                    <ul>
                        <li>Acceder a la plataforma y completar el registro inicial</li>
                        <li>Subir el <strong>registro retributivo</strong>, si ya dispone de él</li>
                        <li>En caso contrario, descargar la plantilla, completarla y volver a subirla</li>
                    </ul>

                    <p>Este paso es imprescindible para poder avanzar en el análisis y continuar con el desarrollo del Plan de Igualdad.</p>

                    <p>Si necesita ayuda o tiene cualquier duda durante el proceso, puede contactar con nosotros respondiendo a este correo y le asistiremos lo antes posible.</p>

                    <p>Quedamos a la espera de su acceso y de la documentación solicitada.</p>

                    <p>Un saludo,</p>
                    <p><strong>Equipo My Equality</strong></p>
                </div>
            </body>
        </html>
    ';

    correo_enviar_html(
        $email,
        $nombre,
        'Recordatorio – Acceso a My Equality y registro retributivo pendiente',
        $body
    );
}

function correo_enviar_recordatorio_rr_reuniones_vencidas(mysqli $db): void
{
    $stmt = $db->prepare(
        "SELECT DISTINCT
          u.id_usuario,
          u.email,
          u.nombre_usuario
        FROM reuniones r
        INNER JOIN usuario_reunion ur ON ur.id_reunion = r.id_reunion
        INNER JOIN usuario u ON u.id_usuario = ur.id_usuario
        INNER JOIN rol ro ON ro.id = u.rol_id
        WHERE UPPER(TRIM(ro.nombre)) = 'CLIENTE'
          AND r.objetivo = 'Subir R.R'
          AND STR_TO_DATE(CONCAT(r.fecha_reunion, ' ', r.hora_reunion), '%Y-%m-%d %H:%i') <= NOW()
          AND NOT EXISTS (
            SELECT 1
            FROM usuario_empresa ue
            INNER JOIN archivos a ON a.id_empresa = ue.id_empresa
            WHERE ue.id_usuario = u.id_usuario
              AND UPPER(TRIM(a.tipo)) = 'REGISTRO_RETRIBUTIVO'
          )"
    );

    if (!$stmt) {
        return;
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $email = trim((string)($row['email'] ?? ''));
        if ($email === '') {
            continue;
        }

        $nombre = trim((string)($row['nombre_usuario'] ?? ''));

        try {
            $empresasAsignadas = correo_obtener_empresas_asignadas($db, (int)($row['id_usuario'] ?? 0));
            $secciones = correo_secciones_empresa_servicio($empresasAsignadas);

            correo_enviar_recordatorio_registro_retributivo(
                $email,
                $nombre,
                $secciones['company'],
                $secciones['service']
            );
        } catch (Throwable $e) {
            error_log('Error al enviar recordatorio de RR (admin): ' . $e->getMessage());
        }
    }

    $stmt->close();
}

function correo_enviar_recordatorio_rr_pendiente_por_token_expirado(mysqli $db, string $emailToken): void
{
    if ($emailToken === '') {
        return;
    }

    try {
        $stmtUserExpired = $db->prepare('SELECT id_usuario, nombre_usuario, email FROM usuario WHERE email = ? LIMIT 1');
        if (!$stmtUserExpired) {
            return;
        }

        $stmtUserExpired->bind_param('s', $emailToken);
        $stmtUserExpired->execute();
        $userExpired = $stmtUserExpired->get_result()->fetch_assoc() ?: null;
        $stmtUserExpired->close();

        if (!$userExpired) {
            return;
        }

        $userIdExpired = (int)($userExpired['id_usuario'] ?? 0);
        $userNameExpired = (string)($userExpired['nombre_usuario'] ?? '');
        $userEmailExpired = (string)($userExpired['email'] ?? '');

        if (
            $userIdExpired <= 0
            || $userEmailExpired === ''
            || !correo_tiene_reunion_subir_rr($db, $userIdExpired)
            || correo_tiene_registro_retributivo($db, $userIdExpired)
        ) {
            return;
        }

        $empresasAsignadas = correo_obtener_empresas_asignadas($db, $userIdExpired);
        $secciones = correo_secciones_empresa_servicio($empresasAsignadas);

        correo_enviar_recordatorio_registro_retributivo(
            $userEmailExpired,
            $userNameExpired,
            $secciones['company'],
            $secciones['service']
        );
    } catch (Throwable $e) {
        error_log('Error enviando recordatorio por token expirado: ' . $e->getMessage());
    }
}

function correo_enviar_confirmacion_registro_retributivo(string $email, string $nombre): void
{
    $body = '
      <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
          <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #667eea;">Hola ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ',</h2>
            <p>¡Excelente! Hemos recibido tu <strong>Registro Retributivo</strong> correctamente.</p>
            <p style="background-color: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; border-radius: 4px;">
              ✓ Tu archivo ha sido procesado exitosamente y se encuentra en nuestro sistema.
            </p>
            <p>Gracias por mantener tu documentación actualizada. Si tienes alguna duda o necesitas asistencia, no dudes en contactarnos.</p>

            <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

            <p style="color: #999; font-size: 12px;">
              Este es un mensaje automático de <strong>Consultoría Igualdad</strong>. Por favor, no respondas directamente a este correo.
            </p>
          </div>
        </body>
      </html>
    ';

    correo_enviar_html(
        $email,
        $nombre,
        'Registro Retributivo Recibido - Consultoría Igualdad',
        $body
    );
}

function correo_obtener_empresa_y_servicio(mysqli $db, int $idEmpresa): ?array
{
    if ($idEmpresa <= 0) {
        return null;
    }

    $stmt = $db->prepare(
        "SELECT
            e.razon_social,
            COALESCE((
                SELECT ce.tipo_contrato
                FROM contrato_empresa ce
                WHERE ce.id_empresa = e.id_empresa
                ORDER BY ce.id_contrato_empresa DESC
                LIMIT 1
            ), 'SIN CONTRATO') AS tipo_contrato
         FROM empresa e
         WHERE e.id_empresa = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if ($row === null) {
        return null;
    }

    $empresa = trim((string)($row['razon_social'] ?? ''));
    $servicio = correo_normalizar_servicio((string)($row['tipo_contrato'] ?? 'SIN CONTRATO'));

    if ($servicio === '') {
        $servicio = 'Pendiente de asignar';
    }

    return [
        'empresa' => $empresa,
        'servicio' => $servicio,
    ];
}

function correo_enviar_nueva_empresa_asignada(
    string $emailDestino,
    string $nombreDestino,
    string $empresaNombre,
    string $servicioNombre,
    string $urlVerEmpresa
): void {
    $nombreHtml = htmlspecialchars($nombreDestino, ENT_QUOTES, 'UTF-8');
    $empresaHtml = htmlspecialchars($empresaNombre, ENT_QUOTES, 'UTF-8');
    $servicioHtml = htmlspecialchars($servicioNombre, ENT_QUOTES, 'UTF-8');
    $urlLogin = correo_url_login();
    $urlHtml = htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8');

    $body = '
      <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
          <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #1f4aa2; margin-top: 0;">Nueva empresa asignada</h2>
            <p>Hola ' . $nombreHtml . ',</p>
            <p>Se te ha asignado una nueva empresa:</p>
            <p><strong>Empresa:</strong> ' . $empresaHtml . '<br><strong>Servicio:</strong> ' . $servicioHtml . '</p>
            <p>Ya puedes acceder para revisar la información y comenzar la gestión.</p>
            <p style="text-align: center; margin: 28px 0;">
              <a href="' . $urlHtml . '"
                 style="display: inline-block; padding: 12px 28px; background-color: #1f4aa2; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">
                                 Ir al login
              </a>
            </p>
          </div>
        </body>
      </html>
    ';

    $altBody = "Nueva empresa asignada\n\nEmpresa: {$empresaNombre}\nServicio: {$servicioNombre}\n\nAccede desde login: {$urlLogin}";

    correo_enviar_html(
        $emailDestino,
        $nombreDestino,
        'Nueva empresa asignada',
        $body,
        $altBody
    );
}
