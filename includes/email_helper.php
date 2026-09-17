<?php
/**
 * KORTZEN - Helper para Envío de Correos Electrónicos (Diseño Blanco y Negro Minimalista)
 */

require_once __DIR__ . '/../config.php';

function logEmailActivity($texto) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/email_log.txt', date('[Y-m-d H:i:s] ') . $texto . "\n", FILE_APPEND);
}

/**
 * Enviar correo a través de SMTP Sockets con soporte SSL/TLS robusto
 */
function _trySMTPSocketConnect($toEmail, $subject, $htmlMessage, $host, $port, $username, $password) {
    $fromName = "KORTZEN Barbería";
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $remoteTarget = ($port == 465) ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
    $socket = @stream_socket_client($remoteTarget, $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        $socketHost = ($port == 465) ? "ssl://{$host}" : $host;
        $socket = @fsockopen($socketHost, $port, $errno, $errstr, 6);
    }

    if (!$socket) {
        logEmailActivity("Fallo de conexión socket a {$host}:{$port} - Error: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($socket, 6);

    $read = function($socket) {
        $response = '';
        while ($str = @fgets($socket, 515)) {
            $response .= $str;
            if (strlen($str) >= 4 && $str[3] === ' ') break;
            if (strlen($str) < 4) break;
        }
        return trim($response);
    };

    $send = function($socket, $cmd) use ($read) {
        @fputs($socket, $cmd . "\r\n");
        return $read($socket);
    };

    $banner = $read($socket);
    $heloHost = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'kortzen.com';
    $send($socket, "EHLO " . $heloHost);

    if ($port == 587) {
        $tlsRes = $send($socket, "STARTTLS");
        if (substr($tlsRes, 0, 3) == '220') {
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            @stream_socket_enable_crypto($socket, true, $cryptoMethod);
            $send($socket, "EHLO " . $heloHost);
        }
    }

    $authRes = $send($socket, "AUTH LOGIN");
    if (substr($authRes, 0, 3) != '334') { 
        logEmailActivity("Fallo AUTH LOGIN con {$host}: {$authRes}");
        @fclose($socket); 
        return false; 
    }

    $send($socket, base64_encode($username));
    $passRes = $send($socket, base64_encode($password));
    if (substr($passRes, 0, 3) != '235') { 
        logEmailActivity("Fallo autenticación usuario {$username}: {$passRes}");
        @fclose($socket); 
        return false; 
    }

    $mailFromRes = $send($socket, "MAIL FROM:<{$username}>");
    if (substr($mailFromRes, 0, 3) != '250') {
        logEmailActivity("Fallo MAIL FROM: {$mailFromRes}");
        @fclose($socket);
        return false;
    }

    $rcptRes = $send($socket, "RCPT TO:<{$toEmail}>");
    if (substr($rcptRes, 0, 3) != '250' && substr($rcptRes, 0, 3) != '251') {
        logEmailActivity("Fallo RCPT TO <{$toEmail}>: {$rcptRes}");
        @fclose($socket);
        return false;
    }

    $dataPrompt = $send($socket, "DATA");
    if (substr($dataPrompt, 0, 3) != '354') {
        logEmailActivity("Fallo DATA prompt: {$dataPrompt}");
        @fclose($socket);
        return false;
    }

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode($fromName) . "?=";
    $messageId = "<" . time() . "." . bin2hex(random_bytes(6)) . "@kortzen.com>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "From: {$encodedFromName} <{$username}>\r\n";
    $headers .= "Reply-To: {$encodedFromName} <{$username}>\r\n";
    $headers .= "To: <{$toEmail}>\r\n";
    $headers .= "Subject: {$encodedSubject}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: {$messageId}\r\n";
    $headers .= "X-Mailer: KORTZEN-Mailer/2.0\r\n";

    $messageData = $headers . "\r\n" . $htmlMessage . "\r\n.";
    $dataRes = $send($socket, $messageData);

    $send($socket, "QUIT");
    @fclose($socket);

    $isOk = (substr($dataRes, 0, 3) == '250');
    if (!$isOk) {
        logEmailActivity("Fallo envío de cuerpo DATA a {$toEmail}: {$dataRes}");
    }
    return $isOk;
}

/**
 * Enviar correo a través de SMTP Sockets (Con Fallback Automático 465 SSL / 587 TLS)
 */
function enviarCorreoSMTPDirecto($toEmail, $subject, $htmlMessage, $smtpConfig) {
    $host = $smtpConfig['smtp_host'] ?? 'smtp.hostinger.com';
    $port = intval($smtpConfig['smtp_port'] ?? 465);
    $username = $smtpConfig['smtp_user'] ?? 'info@kortzen.com';
    $password = $smtpConfig['smtp_pass'] ?? 'Kortzen2026!';

    $ok = _trySMTPSocketConnect($toEmail, $subject, $htmlMessage, $host, $port, $username, $password);
    if ($ok) return true;

    $fallbackPort = ($port == 465) ? 587 : 465;
    return _trySMTPSocketConnect($toEmail, $subject, $htmlMessage, $host, $fallbackPort, $username, $password);
}

/**
 * Enviar correo de confirmación de reserva
 */
function enviarCorreoReserva($toEmail, $clienteNombre, $datosCita)
{
    if (empty($toEmail)) return false;

    $subject = "Confirmación de Cita - KORTZEN Barbería";

    $servicio = htmlspecialchars($datosCita['servicio'] ?? 'Servicio de Barbería');
    $barbero = htmlspecialchars($datosCita['barbero'] ?? 'Barbero Profesional');
    $fecha = htmlspecialchars($datosCita['fecha'] ?? '');
    $hora = htmlspecialchars($datosCita['hora'] ?? '');
    $precio = htmlspecialchars($datosCita['precio'] ?? '0.00');
    $nombreCliente = htmlspecialchars($clienteNombre ?? 'Cliente');

    $message = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Confirmación de Cita - KORTZEN</title>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8F9FA; color: #111111; margin: 0; padding: 30px 15px; }
            .container { max-width: 540px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
            .header { background-color: #000000; padding: 28px 20px; text-align: center; }
            .logo { color: #FFFFFF; font-size: 22px; font-weight: 900; letter-spacing: 4px; text-transform: uppercase; text-decoration: none; }
            .content { padding: 36px 30px; line-height: 1.6; }
            .title { color: #111111; margin: 0 0 10px 0; font-size: 22px; font-weight: 800; text-align: center; letter-spacing: -0.02em; }
            .subtitle { color: #666666; font-size: 14px; text-align: center; margin-bottom: 26px; font-weight: 400; }
            .details-box { background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 12px; padding: 18px 22px; margin: 24px 0; }
            .detail-row { border-bottom: 1px solid #EEEEEE; padding: 12px 0; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { color: #888888; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
            .detail-value { color: #111111; font-weight: 700; font-size: 14px; text-align: right; }
            .btn { display: block; width: 210px; margin: 30px auto 10px auto; background-color: #000000; color: #FFFFFF; padding: 14px 24px; text-decoration: none; border-radius: 50px; font-weight: 700; text-align: center; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; }
            .location-note { font-size: 12px; color: #777777; text-align: center; margin-top: 24px; border-top: 1px solid #F0F0F0; padding-top: 18px; }
            .footer { text-align: center; padding: 22px; font-size: 11px; color: #999999; background: #FAFAFA; border-top: 1px solid #EEEEEE; text-transform: uppercase; letter-spacing: 1px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>KORTZEN</span>
            </div>
            <div class='content'>
                <h2 class='title'>Cita Confirmada</h2>
                <p class='subtitle'>Hola <strong>$nombreCliente</strong>, tu reserva ha sido registrada correctamente.</p>
                
                <div class='details-box'>
                    <div class='detail-row'><span class='detail-label'>Servicio</span><span class='detail-value'>$servicio</span></div>
                    <div class='detail-row'><span class='detail-label'>Barbero</span><span class='detail-value'>$barbero</span></div>
                    <div class='detail-row'><span class='detail-label'>Fecha</span><span class='detail-value'>$fecha</span></div>
                    <div class='detail-row'><span class='detail-label'>Hora</span><span class='detail-value'>$hora</span></div>
                    <div class='detail-row'><span class='detail-label'>Total</span><span class='detail-value'>$$precio</span></div>
                </div>
                
                <div class='location-note'>
                    Ubicación: <strong>KORTZEN Llano Chico</strong><br>Por favor llega 5 minutos antes de la hora agendada.
                </div>
                
                <a href='https://kortzen.com/mis-citas.php' class='btn'>Ver mis Citas</a>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " KORTZEN Barbería • Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        $cfgs = [];
        try {
            $pdo = getConnection();
            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'smtp_%'");
            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $eDb) {}

        if (empty($cfgs['smtp_user']) || empty($cfgs['smtp_pass'])) {
            $cfgs['smtp_host'] = 'smtp.hostinger.com';
            $cfgs['smtp_port'] = 465;
            $cfgs['smtp_user'] = 'info@kortzen.com';
            $cfgs['smtp_pass'] = 'Kortzen2026!';
        }

        $smtpOk = enviarCorreoSMTPDirecto($toEmail, $subject, $message, $cfgs);
        logEmailActivity("SMTP RESERVA: Para: $toEmail | Resultado: " . ($smtpOk ? 'EXITO' : 'FALLO'));
        if ($smtpOk) return true;
    } catch (Exception $exSmtp) {
        logEmailActivity("Excepción SMTP Reserva: " . $exSmtp->getMessage());
    }

    $fromEmail = "info@kortzen.com";
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode("KORTZEN Barbería") . "?=";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "From: {$encodedFromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$encodedFromName} <{$fromEmail}>\r\n";
    $headers .= "Subject: {$encodedSubject}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $res = @mail($toEmail, $subject, $message, $headers, "-f $fromEmail");
    logEmailActivity("MAIL NATIVO RESERVA: Para: $toEmail | Resultado: " . ($res ? 'EXITO' : 'FALLO'));

    return $res;
}

/**
 * Enviar correo de recordatorio el día del corte
 */
function enviarCorreoRecordatorio($toEmail, $clienteNombre, $datosCita)
{
    if (empty($toEmail)) return false;

    $subject = "Recordatorio de Cita - KORTZEN Barbería";

    $servicio = htmlspecialchars($datosCita['servicio'] ?? 'Corte / Servicio');
    $barbero = htmlspecialchars($datosCita['barbero'] ?? 'Barbero Profesional');
    $hora = htmlspecialchars($datosCita['hora'] ?? '');
    $nombreCliente = htmlspecialchars($clienteNombre ?? 'Cliente');

    $message = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Recordatorio de Cita - KORTZEN</title>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8F9FA; color: #111111; margin: 0; padding: 30px 15px; }
            .container { max-width: 540px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
            .header { background-color: #000000; padding: 28px 20px; text-align: center; }
            .logo { color: #FFFFFF; font-size: 22px; font-weight: 900; letter-spacing: 4px; text-transform: uppercase; text-decoration: none; }
            .content { padding: 36px 30px; line-height: 1.6; }
            .title { color: #111111; margin: 0 0 10px 0; font-size: 22px; font-weight: 800; text-align: center; letter-spacing: -0.02em; }
            .subtitle { color: #666666; font-size: 14px; text-align: center; margin-bottom: 26px; font-weight: 400; }
            .details-box { background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 12px; padding: 18px 22px; margin: 24px 0; }
            .detail-row { border-bottom: 1px solid #EEEEEE; padding: 12px 0; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { color: #888888; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
            .detail-value { color: #111111; font-weight: 700; font-size: 14px; text-align: right; }
            .btn { display: block; width: 210px; margin: 30px auto 10px auto; background-color: #000000; color: #FFFFFF; padding: 14px 24px; text-decoration: none; border-radius: 50px; font-weight: 700; text-align: center; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; }
            .location-note { font-size: 12px; color: #777777; text-align: center; margin-top: 24px; border-top: 1px solid #F0F0F0; padding-top: 18px; }
            .footer { text-align: center; padding: 22px; font-size: 11px; color: #999999; background: #FAFAFA; border-top: 1px solid #EEEEEE; text-transform: uppercase; letter-spacing: 1px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>KORTZEN</span>
            </div>
            <div class='content'>
                <h2 class='title'>Recordatorio de Cita</h2>
                <p class='subtitle'>Hola <strong>$nombreCliente</strong>, te recordamos que tu cita está agendada para el día de <strong>HOY</strong>.</p>
                
                <div class='details-box'>
                    <div class='detail-row'><span class='detail-label'>Hora de atención</span><span class='detail-value'>$hora</span></div>
                    <div class='detail-row'><span class='detail-label'>Servicio</span><span class='detail-value'>$servicio</span></div>
                    <div class='detail-row'><span class='detail-label'>Barbero</span><span class='detail-value'>$barbero</span></div>
                    <div class='detail-row'><span class='detail-label'>Sucursal</span><span class='detail-value'>KORTZEN Llano Chico</span></div>
                </div>
                
                <div class='location-note'>
                    Recuerda llegar 5 minutos antes para brindarte la mejor atención.
                </div>
                
                <a href='https://kortzen.com/mis-citas.php' class='btn'>Ver Detalles</a>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " KORTZEN Barbería • Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        $cfgs = [];
        try {
            $pdo = getConnection();
            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'smtp_%'");
            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $eDb) {}

        if (empty($cfgs['smtp_user']) || empty($cfgs['smtp_pass'])) {
            $cfgs['smtp_host'] = 'smtp.hostinger.com';
            $cfgs['smtp_port'] = 465;
            $cfgs['smtp_user'] = 'info@kortzen.com';
            $cfgs['smtp_pass'] = 'Kortzen2026!';
        }

        $smtpOk = enviarCorreoSMTPDirecto($toEmail, $subject, $message, $cfgs);
        logEmailActivity("SMTP RECORDATORIO: Para: $toEmail | Resultado: " . ($smtpOk ? 'EXITO' : 'FALLO'));
        if ($smtpOk) return true;
    } catch (Exception $exSmtp) {
        logEmailActivity("Excepción SMTP Recordatorio: " . $exSmtp->getMessage());
    }

    $fromEmail = "info@kortzen.com";
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode("KORTZEN Barbería") . "?=";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "From: {$encodedFromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$encodedFromName} <{$fromEmail}>\r\n";
    $headers .= "Subject: {$encodedSubject}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $res = @mail($toEmail, $subject, $message, $headers, "-f $fromEmail");
    logEmailActivity("MAIL NATIVO RECORDATORIO: Para: $toEmail | Resultado: " . ($res ? 'EXITO' : 'FALLO'));

    return $res;
}
