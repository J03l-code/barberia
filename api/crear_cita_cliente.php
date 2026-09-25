<?php
require_once '../config.php';

header('Content-Type: application/json');
enforceRateLimit('crear_cita_cliente', 20, 60, 'Has realizado demasiadas solicitudes de reserva en poco tiempo. Por favor espera un minuto.');

$cliente = null;
$clienteId = null;

if (isClienteLoggedIn()) {
    $cliente = getCurrentCliente();
    $clienteId = $cliente['id'] ?? null;
}

// Obtener datos
$servicioId = intval($_POST['servicio_id'] ?? 0);
$barberoId = intval($_POST['barbero_id'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$hora = $_POST['hora'] ?? '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$clienteNombre = trim($_POST['cliente_nombre'] ?? $_POST['nombre'] ?? '');
$clienteEmail = trim($_POST['cliente_email'] ?? $_POST['email'] ?? '');

if (!$servicioId || !$barberoId || empty($fecha) || empty($hora)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos de la reserva (servicio, barbero, fecha u hora).']);
    exit;
}

if (empty($telefono)) {
    echo json_encode(['success' => false, 'message' => 'El teléfono / WhatsApp es obligatorio para confirmar la cita.']);
    exit;
}

try {
    $pdo = getConnection();

    if (!$clienteId) {
        if (empty($clienteNombre)) {
            echo json_encode(['success' => false, 'message' => 'Por favor ingresa tu nombre completo para la reserva.']);
            exit;
        }

        $telClean = preg_replace('/[^0-9]/', '', $telefono);
        $stmtFind = $pdo->prepare("SELECT * FROM clientes WHERE (telefono = ? OR (telefono != '' AND telefono = ?) OR (email != '' AND email = ?)) ORDER BY id DESC LIMIT 1");
        $stmtFind->execute([$telefono, $telClean, $clienteEmail]);
        $existente = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $clienteId = $existente['id'];
            $cliente = $existente;
            if (empty($existente['nombre']) && !empty($clienteNombre)) {
                $pdo->prepare("UPDATE clientes SET nombre = ? WHERE id = ?")->execute([$clienteNombre, $clienteId]);
            }
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO clientes (nombre, email, telefono, created_at) VALUES (?, ?, ?, NOW())");
            $stmtInsert->execute([$clienteNombre, !empty($clienteEmail) ? $clienteEmail : null, $telefono]);
            $clienteId = $pdo->lastInsertId();

            $stmtNew = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmtNew->execute([$clienteId]);
            $cliente = $stmtNew->fetch(PDO::FETCH_ASSOC);
        }

        $_SESSION['cliente_logged_in'] = true;
        $_SESSION['cliente_id'] = $clienteId;
        $_SESSION['cliente_nombre'] = $cliente['nombre'];
        $_SESSION['cliente_email'] = $cliente['email'] ?? '';
    }

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
            try {
                $colsRef = $pdo->query("SHOW COLUMNS FROM `referidos`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('referido_id', $colsRef)) {
                    if (in_array('cliente_referido_id', $colsRef)) {
                        $pdo->exec("ALTER TABLE `referidos` CHANGE COLUMN `cliente_referido_id` `referido_id` INT UNSIGNED NULL");
                    } else {
                        $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `referido_id` INT UNSIGNED NULL");
                    }
                }
                if (!in_array('referente_id', $colsRef)) {
                    if (in_array('cliente_origen_id', $colsRef)) {
                        $pdo->exec("ALTER TABLE `referidos` CHANGE COLUMN `cliente_origen_id` `referente_id` INT UNSIGNED NULL");
                    } else {
                        $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `referente_id` INT UNSIGNED NULL");
                    }
                }
                if (!in_array('codigo_usado', $colsRef)) {
                    $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `codigo_usado` VARCHAR(50) NULL");
                }
                if (!in_array('cita_id', $colsRef)) {
                    $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `cita_id` INT UNSIGNED NULL");
                }
                if (!in_array('descuento_aplicado', $colsRef)) {
                    $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `descuento_aplicado` DECIMAL(10,2) DEFAULT 0.00");
                }
                if (!in_array('puntos_otorgados', $colsRef)) {
                    $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `puntos_otorgados` INT DEFAULT 0");
                }
                if (!in_array('estado', $colsRef)) {
                    $pdo->exec("ALTER TABLE `referidos` ADD COLUMN `estado` ENUM('pendiente', 'completado', 'cancelado') DEFAULT 'pendiente'");
                }
            } catch (Exception $exRefCheck) {}

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

    // Registrar seguimiento de referido si aplica y acreditar puntos
    if ($referenteId && $citaId > 0) {
        try {
            $stmtInsertRef = $pdo->prepare("INSERT INTO referidos (referente_id, referido_id, codigo_usado, cita_id, descuento_aplicado, puntos_otorgados, estado) VALUES (?, ?, ?, ?, ?, ?, 'completado')");
            $stmtInsertRef->execute([$referenteId, $clienteId, $codigoReferido, $citaId, $montoDescuento, $puntosPorReferido]);

            // Acreditar puntos al referente
            $stmtAddRefPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
            $stmtAddRefPts->execute([$puntosPorReferido, $referenteId]);
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

    // 5.5. Enviar Notificaciones PWA / WebPush al Cliente, al Barbero Asignado y al Referente (si aplica)
    $fechaLegible = date('d/m/Y', strtotime($fecha));

    $stmtCInfo = $pdo->prepare("SELECT nombre, email FROM clientes WHERE id = ?");
    $stmtCInfo->execute([$clienteId]);
    $cInfo = $stmtCInfo->fetch(PDO::FETCH_ASSOC);

    $finalEmail = !empty($cInfo['email']) ? trim($cInfo['email']) : trim($_SESSION['cliente_email'] ?? '');
    $finalNombre = !empty($cInfo['nombre']) ? trim($cInfo['nombre']) : trim($_SESSION['cliente_nombre'] ?? 'Cliente');

    try {
        require_once __DIR__ . '/../includes/webpush_helper.php';

        // 1. Notificar al Cliente (PWA + WebPush)
        $tituloConf = "Reserva Confirmada";
        $msgConf = "Hola {$finalNombre}, tu cita de {$nombreServicio} con {$nombreBarbero} ha sido agendada para el {$fechaLegible} a las {$hora}.";
        notificarCliente($pdo, $clienteId, $citaId, $tituloConf, $msgConf, '/cliente-dashboard.php');

        // 2. Notificar explícitamente al Barbero Asignado (PWA + WebPush)
        notificarBarbero($pdo, $barberoId, $citaId, 'nueva_reserva', [
            'cliente' => $finalNombre,
            'servicio' => $nombreServicio,
            'fecha' => $fechaLegible,
            'hora' => $hora,
            'sucursal' => $barberoData['nombre'] ?? ''
        ]);

        // 3. Notificar al Referente si se usó su código de referido
        if ($referenteId && $referenteId > 0) {
            $tituloRef = "Nuevo Referido en KORTZEN";
            $msgRef = "{$finalNombre} ha reservado con tu código de referido. Se han acreditado +{$puntosPorReferido} Puntos KORTZEN a tu cuenta.";
            notificarCliente($pdo, $referenteId, $citaId, $tituloRef, $msgRef, '/cliente-dashboard.php');
        }
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
