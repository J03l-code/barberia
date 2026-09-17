<?php
require_once '../config.php';

header('Content-Type: application/json');
enforceRateLimit('crear_cita_cliente', 20, 60, 'Has realizado demasiadas solicitudes de reserva en poco tiempo. Por favor espera un minuto.');

if (!isClienteLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

// Obtener datos
$cliente = getCurrentCliente();
$clienteId = $cliente['id'];

$servicioId = intval($_POST['servicio_id'] ?? 0);
$barberoId = intval($_POST['barbero_id'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$hora = $_POST['hora'] ?? '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';

if (!$servicioId || !$barberoId || empty($fecha) || empty($hora)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos de la reserva.']);
    exit;
}

if (empty($telefono)) {
    echo json_encode(['success' => false, 'message' => 'El teléfono es obligatorio.']);
    exit;
}

try {
    $pdo = getConnection();

    // 0. Actualizar teléfono y preferencias del cliente si vienen en la petición
    $estiloBuscado = isset($_POST['estilo_buscado']) ? trim($_POST['estilo_buscado']) : null;
    $ambientePreferido = isset($_POST['ambiente_preferido']) ? trim($_POST['ambiente_preferido']) : null;
    $bebidaPreferida = isset($_POST['bebida_preferida']) ? trim($_POST['bebida_preferida']) : null;

    if ($estiloBuscado || $ambientePreferido || $bebidaPreferida) {
        $stmtUpdatePref = $pdo->prepare("
            UPDATE clientes 
            SET telefono = ?, 
                estilo_buscado = COALESCE(?, estilo_buscado), 
                ambiente_preferido = COALESCE(?, ambiente_preferido), 
                bebida_preferida = COALESCE(?, bebida_preferida) 
            WHERE id = ?
        ");
        $stmtUpdatePref->execute([$telefono, $estiloBuscado, $ambientePreferido, $bebidaPreferida, $clienteId]);
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE clientes SET telefono = ? WHERE id = ?");
        $stmtUpdate->execute([$telefono, $clienteId]);
    }

    // 1. Validar disponibilidad (Doble check para concurrencia)
    $fechaHora = "$fecha $hora:00";

    // Check rápido si ya tiene cita a esa hora EXACTA
    $stmtCcheck = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE barbero_id = ? AND fecha_hora = ? AND estado != 'cancelada'");
    $stmtCcheck->execute([$barberoId, $fechaHora]);
    if ($stmtCcheck->fetchColumn() > 0) {
        throw new Exception('Lo sentimos, este horario acaba de ser ocupado.');
    }

    // 2. Obtener sucursal del barbero
    $stmtBarbero = $pdo->prepare("SELECT sucursal_id, nombre FROM usuarios WHERE id = ?");
    $stmtBarbero->execute([$barberoId]);
    $barberoData = $stmtBarbero->fetch();
    $sucursalId = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : ($barberoData['sucursal_id'] ?? 1);
    $nombreBarbero = $barberoData['nombre'];

    // Validar si el horario solicitado colisiona con el Horario de Almuerzo Fijo del Barbero
    $stmtAlmuerzo = $pdo->prepare("SELECT almuerzo_inicio, almuerzo_fin, almuerzo_activo FROM usuarios WHERE id = ?");
    $stmtAlmuerzo->execute([$barberoId]);
    $barberLunch = $stmtAlmuerzo->fetch(PDO::FETCH_ASSOC);

    if ($barberLunch && ($barberLunch['almuerzo_activo'] ?? 1) == 1 && !empty($barberLunch['almuerzo_inicio']) && !empty($barberLunch['almuerzo_fin'])) {
        $cSlotStart = strtotime($fechaHora);
        $cLunchStart = strtotime("$fecha " . $barberLunch['almuerzo_inicio']);
        $cLunchEnd = strtotime("$fecha " . $barberLunch['almuerzo_fin']);

        if ($cSlotStart >= $cLunchStart && $cSlotStart < $cLunchEnd) {
            throw new Exception('El barbero se encuentra en su horario de almuerzo a esa hora. Por favor selecciona otro horario.');
        }
    }

    // 3. Obtener precio y nombre servicio
    $stmtServicio = $pdo->prepare("SELECT * FROM servicios WHERE id = ?");
    $stmtServicio->execute([$servicioId]);
    $servicioData = $stmtServicio->fetch();
    if (!$servicioData) {
        throw new Exception('El servicio seleccionado no existe.');
    }
    $nombreServicio = $servicioData['nombre'];
    $precio = $servicioData['precio'];
    $assignedBarberId = !empty($servicioData['barbero_id']) ? intval($servicioData['barbero_id']) : null;

    // Validar exclusividad de barbero (Ej: Corte Con Mateo)
    if ($assignedBarberId && $assignedBarberId !== $barberoId) {
        $assignedBarberName = $pdo->query("SELECT nombre FROM usuarios WHERE id = $assignedBarberId")->fetchColumn();
        throw new Exception("El servicio '$nombreServicio' es exclusivo de " . ($assignedBarberName ?: 'su barbero titular') . ". Por favor selecciona al barbero correcto.");
    } elseif (!$assignedBarberId && stripos($nombreServicio, 'mateo') !== false) {
        if (stripos($nombreBarbero, 'mateo') === false && stripos($nombreBarbero, 'alvaro') === false) {
            throw new Exception("El servicio '$nombreServicio' es exclusivo de Mateo Álvaro. Por favor agenda tu cita con él.");
        }
    }

    // 3.5. Procesar Código de Referido y Descuentos
    $codigoReferido = strtoupper(trim($_POST['codigo_referido'] ?? ''));
    $codigoReferidoLimpio = str_replace('KORTZEN-', '', $codigoReferido);
    $montoDescuento = 0.00;
    $referenteId = null;
    $promoIdToRecord = null;
    $puntosPorReferido = 200;

    if (!empty($codigoReferidoLimpio)) {
        // A) Verificar si es un Código Promocional
        try {
            $stmtPromo = $pdo->prepare("SELECT * FROM codigos_promocionales WHERE UPPER(codigo) = ? AND activo = 1");
            $stmtPromo->execute([$codigoReferidoLimpio]);
            $promoRow = $stmtPromo->fetch(PDO::FETCH_ASSOC);

            if ($promoRow) {
                $stmtCheckUso = $pdo->prepare("SELECT COUNT(*) FROM usos_codigos_promocionales WHERE codigo_id = ? AND cliente_id = ?");
                $stmtCheckUso->execute([$promoRow['id'], $clienteId]);
                if ($stmtCheckUso->fetchColumn() == 0) {
                    $promoIdToRecord = $promoRow['id'];
                    $descuentoPct = floatval($promoRow['descuento_porcentaje']);
                    $montoDescuento = (floatval($precio) * $descuentoPct) / 100;
                }
            }
        } catch (Exception $exPromo) {}

        // B) Si no es promocional, validar si es Código de Referido de un Amigo (Solo 1ra visita)
        if (!$promoIdToRecord) {
            $stmtCheckUsed = $pdo->prepare("SELECT COUNT(*) FROM referidos WHERE referido_id = ?");
            $stmtCheckUsed->execute([$clienteId]);
            $alreadyUsedCode = ($stmtCheckUsed->fetchColumn() > 0);

            $stmtCheckCitasPrev = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE cliente_id = ? AND estado != 'cancelada'");
            $stmtCheckCitasPrev->execute([$clienteId]);
            $hasPreviousCitas = ($stmtCheckCitasPrev->fetchColumn() > 0);

            if (!$alreadyUsedCode && !$hasPreviousCitas) {
                $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
                $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
                $montoDescuento = floatval($cfgs['descuento_referido_amigo'] ?? 2.00);
                $puntosPorReferido = intval($cfgs['puntos_por_referido'] ?? 200);

                $stmtRefCheck = $pdo->prepare("SELECT id FROM clientes WHERE codigo_referido = ? OR codigo_referido = ?");
                $stmtRefCheck->execute([$codigoReferidoLimpio, $codigoReferido]);
                $refRow = $stmtRefCheck->fetch(PDO::FETCH_ASSOC);

                if ($refRow && $refRow['id'] != $clienteId) {
                    $referenteId = $refRow['id'];
                } else {
                    $montoDescuento = 0.00;
                }
            }
        }
    }

    $precioFinal = max(0.00, floatval($precio) - $montoDescuento);

    $reagendarId = isset($_POST['reagendar_id']) ? intval($_POST['reagendar_id']) : 0;

    if ($reagendarId > 0) {
        // Validar que la cita pertenece al cliente
        $stmtCheckOwner = $pdo->prepare("SELECT id FROM citas WHERE id = ? AND cliente_id = ?");
        $stmtCheckOwner->execute([$reagendarId, $clienteId]);
        if ($stmtCheckOwner->fetchColumn() === false) {
            throw new Exception('Cita original no encontrada.');
        }

        // Actualizar la cita existente
        $sql = "UPDATE citas SET servicio_id = ?, barbero_id = ?, sucursal_id = ?, fecha_hora = ?, estado = 'pendiente', precio_final = ? WHERE id = ? AND cliente_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$servicioId, $barberoId, $sucursalId, $fechaHora, $precioFinal, $reagendarId, $clienteId]);
        $citaId = $reagendarId;
    } else {
        // 4. Insertar la cita
        $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, precio_final) 
                VALUES (?, ?, ?, ?, ?, 'pendiente', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clienteId, $servicioId, $barberoId, $sucursalId, $fechaHora, $precioFinal]);
        $citaId = $pdo->lastInsertId();
    }

    // Registrar uso de Código Promocional si aplica
    if ($promoIdToRecord && $citaId > 0) {
        try {
            $stmtUpromo = $pdo->prepare("INSERT INTO usos_codigos_promocionales (codigo_id, cliente_id, cita_id, descuento_monto) VALUES (?, ?, ?, ?)");
            $stmtUpromo->execute([$promoIdToRecord, $clienteId, $citaId, $montoDescuento]);
        } catch (Exception $exUp) {}
    }

    // Registrar seguimiento de referido si aplica
    if ($referenteId && $citaId > 0) {
        try {
            $stmtInsertRef = $pdo->prepare("INSERT INTO referidos (referente_id, referido_id, codigo_usado, cita_id, descuento_aplicado, puntos_otorgados, estado) VALUES (?, ?, ?, ?, ?, ?, 'pendiente')");
            $stmtInsertRef->execute([$referenteId, $clienteId, $codigoReferido, $citaId, $montoDescuento, $puntosPorReferido]);
        } catch (Exception $exRef) {}
    }

    // 5. Sincronización Automática con Google Calendar (Opción A)
    if (!empty($_SESSION['google_access_token'])) {
        require_once __DIR__ . '/../includes/google_calendar_helper.php';
        try {
            agendarEnGoogleCalendar($_SESSION['google_access_token'], [
                'servicio' => $nombreServicio,
                'barbero' => $nombreBarbero,
                'fecha_hora' => $fechaHora,
                'duracion_minutos' => 35
            ]);
        } catch (Exception $eg) {
            // Silencioso para no romper el flujo principal
        }
    }

    // 5.5. Crear Notificación PWA e Instant WebPush para la Confirmación de Reserva
    $fechaLegible = date('d/m/Y', strtotime($fecha));

    $stmtCInfo = $pdo->prepare("SELECT nombre, email FROM clientes WHERE id = ?");
    $stmtCInfo->execute([$clienteId]);
    $cInfo = $stmtCInfo->fetch(PDO::FETCH_ASSOC);

    $finalEmail = !empty($cInfo['email']) ? trim($cInfo['email']) : trim($_SESSION['cliente_email'] ?? '');
    $finalNombre = !empty($cInfo['nombre']) ? trim($cInfo['nombre']) : trim($_SESSION['cliente_nombre'] ?? 'Cliente');

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificaciones_pwa (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                cita_id INT NULL,
                titulo VARCHAR(255) NOT NULL,
                mensaje TEXT NOT NULL,
                url VARCHAR(500) NULL,
                leido TINYINT(1) DEFAULT 0,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cli_leido (cliente_id, leido)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $tituloConf = "✂️ ¡Reserva Confirmada!";
        $msgConf = "¡Hola {$finalNombre}! Tu cita de {$nombreServicio} con {$nombreBarbero} ha sido agendada para el {$fechaLegible} a las {$hora}.";
        $urlConf = "/cliente-dashboard.php";

        $stmtNotifPwa = $pdo->prepare("
            INSERT INTO notificaciones_pwa (cliente_id, cita_id, titulo, mensaje, url) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtNotifPwa->execute([$clienteId, $citaId, $tituloConf, $msgConf, $urlConf]);

        // Intentar Web Push directo si el cliente tiene suscripción push activa
        try {
            require_once __DIR__ . '/../includes/webpush_helper.php';
            $stmtPush = $pdo->prepare("SELECT * FROM push_subscriptions WHERE cliente_id = ?");
            $stmtPush->execute([$clienteId]);
            $subs = $stmtPush->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($subs)) {
                $payloadPush = json_encode([
                    'title' => $tituloConf,
                    'body' => $msgConf,
                    'icon' => '/assets/icons/favicon.png',
                    'url' => $urlConf
                ]);
                foreach ($subs as $sub) {
                    if (!empty($sub['endpoint'])) {
                        @enviarWebPushVapid($sub, $payloadPush);
                    }
                }
            }
        } catch (Exception $exPush) {}
    } catch (Exception $exNotif) {}

    // 6. Enviar Correo Electrónico de Confirmación
    try {
        require_once __DIR__ . '/../includes/email_helper.php';
        if (!empty($finalEmail)) {
            enviarCorreoReserva($finalEmail, $finalNombre, [
                'servicio' => $nombreServicio,
                'barbero' => $nombreBarbero,
                'fecha' => $fechaLegible,
                'hora' => $hora,
                'precio' => number_format($precioFinal, 2)
            ]);
        } else {
            logEmailActivity("No se envió correo para la cita #{$citaId}: El cliente ID {$clienteId} ({$finalNombre}) no tiene correo electrónico registrado.");
        }
    } catch (Throwable $exMail) {
        logEmailActivity("Error al intentar enviar correo en crear_cita_cliente: " . $exMail->getMessage());
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
