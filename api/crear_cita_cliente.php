<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth/google-config.php';

header('Content-Type: application/json');
enforceRateLimit('crear_cita_cliente', 30, 60, 'Has realizado demasiadas solicitudes de reserva en poco tiempo. Por favor espera un minuto.');

try {
    $pdo = getConnection();

    $servicioId = intval($_POST['servicio_id'] ?? 0);
    $barberoId = intval($_POST['barbero_id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $nombreCliente = trim($_POST['nombre'] ?? '');
    $emailCliente = trim($_POST['email'] ?? '');
    $sucursalId = intval($_POST['sucursal_id'] ?? 1);

    if (!$servicioId || empty($fecha) || empty($hora)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos de la reserva (servicio, fecha u hora).']);
        exit;
    }

    // Validar formato estricto de hora (solo horas enteras :00 o medias horas :30)
    if (!preg_match('/^([01]?[0-9]|2[0-3]):(00|30)$/', $hora)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Horario inválido. Solo se permite agendar en horas enteras (:00) o medias horas (:30).'
        ]);
        exit;
    }

    // 0. Validación estricta del número de celular (Ecuador 10 dígitos 09XXXXXXXX)
    $telAValidar = !empty($telefono) ? $telefono : (isClienteLoggedIn() ? (getCurrentCliente()['telefono'] ?? '') : '');
    $telCheck = validateEcuadorianMobile($telAValidar);
    if (!$telCheck['valid']) {
        echo json_encode([
            'success' => false,
            'message' => $telCheck['error'] ?? 'Por favor ingresa un número de celular de Ecuador válido (10 dígitos comenzando con 09, ej: 0991234567).'
        ]);
        exit;
    }
    // Guardar normalizado a formato estándar nacional 09XXXXXXXX
    $telefono = $telCheck['clean'];

    // Identificar o Registrar al Cliente
    if (isClienteLoggedIn()) {
        $cliente = getCurrentCliente();
        $clienteId = $cliente['id'];
        // Actualizar teléfono del cliente en su perfil para futuras citas
        $stmtUpdTel = $pdo->prepare("UPDATE clientes SET telefono = ? WHERE id = ?");
        $stmtUpdTel->execute([$telefono, $clienteId]);
        if (empty($nombreCliente)) {
            $nombreCliente = $cliente['nombre'];
        }
        if (empty($emailCliente)) {
            $emailCliente = $cliente['email'] ?? '';
        }
    } else {
        // Reserva como invitado / nuevo cliente
        if (empty($nombreCliente)) {
            echo json_encode(['success' => false, 'message' => 'Por favor ingresa tu nombre completo.']);
            exit;
        }
        if (empty($telefono)) {
            echo json_encode(['success' => false, 'message' => 'Por favor ingresa tu número de WhatsApp.']);
            exit;
        }

        $telLimpio = preg_replace('/[^0-9]/', '', $telefono);
        $stmtFind = $pdo->prepare("SELECT * FROM clientes WHERE (telefono = ? OR telefono = ?) OR (email = ? AND email != '') LIMIT 1");
        $stmtFind->execute([$telefono, $telLimpio, $emailCliente]);
        $existingClient = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($existingClient) {
            $clienteId = $existingClient['id'];
            // Actualizar nombre o email si vienen nuevos
            $stmtUpClient = $pdo->prepare("UPDATE clientes SET nombre = COALESCE(NULLIF(?, ''), nombre), email = COALESCE(NULLIF(?, ''), email), telefono = ? WHERE id = ?");
            $stmtUpClient->execute([$nombreCliente, $emailCliente, $telefono, $clienteId]);
        } else {
            $stmtNew = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email, activo, fecha_creacion) VALUES (?, ?, ?, 1, NOW())");
            $stmtNew->execute([$nombreCliente, $telefono, !empty($emailCliente) ? $emailCliente : null]);
            $clienteId = $pdo->lastInsertId();
        }

        // Iniciar sesión en backend
        $_SESSION['cliente_id'] = $clienteId;
        $_SESSION['cliente_nombre'] = $nombreCliente;
        $_SESSION['cliente_email'] = $emailCliente;
        $_SESSION['cliente_logged_in'] = true;
    }

    // 1. Obtener precio y nombre del servicio
    $stmtServicio = $pdo->prepare("SELECT * FROM servicios WHERE id = ?");
    $stmtServicio->execute([$servicioId]);
    $servicioData = $stmtServicio->fetch(PDO::FETCH_ASSOC);
    if (!$servicioData) {
        throw new Exception('El servicio seleccionado no existe.');
    }
    $nombreServicio = $servicioData['nombre'];
    $precio = floatval($servicioData['precio']);
    $rawDur = intval($servicioData['duracion_minutos']) > 0 ? intval($servicioData['duracion_minutos']) : 60;
    // Duración efectiva: <=30m -> 30m; 31-60m -> 60m (hora redonda); >60m -> múltiplos de 30m
    $duracionServicio = ($rawDur <= 30) ? 30 : (($rawDur <= 60) ? 60 : (int)(ceil($rawDur / 30) * 30));
    $exclusiveBarberId = !empty($servicioData['barbero_id']) ? intval($servicioData['barbero_id']) : null;

    if ($exclusiveBarberId) {
        $barberoId = $exclusiveBarberId;
    }

    $fechaHora = "$fecha $hora:00";
    $slotStartTs = strtotime($fechaHora);
    $slotEndTs = $slotStartTs + ($duracionServicio * 60);

    // 2. Si es "Cualquier barbero disponible" (barberoId === 0), asignar el barbero libre
    if ($barberoId === 0) {
        $stmtAllBarbers = $pdo->prepare("SELECT id, nombre, sucursal_id, almuerzo_inicio, almuerzo_fin, almuerzo_activo FROM usuarios WHERE activo = 1 AND (rol = 'barbero' OR rol = 'admin_local') AND (sucursal_id = ? OR sucursal_id IS NULL OR sucursal_id = 0) ORDER BY id ASC");
        $stmtAllBarbers->execute([$sucursalId]);
        $candidatos = $stmtAllBarbers->fetchAll(PDO::FETCH_ASSOC);

        $selectedBarber = null;

        foreach ($candidatos as $cand) {
            $candId = $cand['id'];

            // Verificar si trabaja ese día de semana
            $diaSem = date('w', $slotStartTs);
            $stmtH = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ? AND activo = 1");
            $stmtH->execute([$candId, $diaSem]);
            $hBase = $stmtH->fetch(PDO::FETCH_ASSOC);
            if (!$hBase) {
                $hBase = [
                    'hora_inicio' => '10:00:00',
                    'hora_fin' => ($diaSem == 0) ? '17:00:00' : '20:00:00',
                    'activo' => 1
                ];
            }

            $hStart = strtotime("$fecha " . $hBase['hora_inicio']);
            $hEnd = strtotime("$fecha " . $hBase['hora_fin']);
            if ($slotStartTs < $hStart || $slotEndTs > $hEnd) continue;

            // Verificar día bloqueado
            $stmtB = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ? AND todo_el_dia = 1");
            $stmtB->execute([$candId, $fecha]);
            if ($stmtB->fetch()) continue;

            // Verificar almuerzo obligatorio: Por defecto todos los barberos tienen almuerzo de 13:00 a 14:00 (bloqueado)
            $almIni = (!empty($cand['almuerzo_inicio']) && $cand['almuerzo_inicio'] !== '00:00:00') ? $cand['almuerzo_inicio'] : '13:00:00';
            $almFin = (!empty($cand['almuerzo_fin']) && $cand['almuerzo_fin'] !== '00:00:00') ? $cand['almuerzo_fin'] : '14:00:00';
            $lStart = strtotime("$fecha $almIni");
            $lEnd = strtotime("$fecha $almFin");
            if ($slotStartTs < $lEnd && $slotEndTs > $lStart) continue;

            // Verificar bloqueos por horas
            $stmtBH = $pdo->prepare("SELECT * FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ?");
            $stmtBH->execute([$candId, $fecha]);
            $bhs = $stmtBH->fetchAll(PDO::FETCH_ASSOC);
            $hasBlock = false;
            foreach ($bhs as $bh) {
                $bIni = strtotime("$fecha " . $bh['hora_inicio']);
                $bFin = strtotime("$fecha " . $bh['hora_fin']);
                if ($slotStartTs < $bFin && $slotEndTs > $bIni) { $hasBlock = true; break; }
            }
            if ($hasBlock) continue;

            // Verificar citas ocupadas con duración efectiva
            $stmtC = $pdo->prepare("SELECT c.fecha_hora, COALESCE(s.duracion_minutos, 60) as duracion_minutos 
                                    FROM citas c 
                                    LEFT JOIN servicios s ON c.servicio_id = s.id 
                                    WHERE c.barbero_id = ? AND c.estado NOT IN ('cancelada', 'no_asistio') AND DATE(c.fecha_hora) = ?");
            $stmtC->execute([$candId, $fecha]);
            $citas = $stmtC->fetchAll(PDO::FETCH_ASSOC);
            $hasCitaCol = false;
            foreach ($citas as $c) {
                $cIni = strtotime($c['fecha_hora']);
                $cRaw = intval($c['duracion_minutos']) > 0 ? intval($c['duracion_minutos']) : 60;
                $cDur = ($cRaw <= 30) ? 30 : (($cRaw <= 60) ? 60 : (int)(ceil($cRaw / 30) * 30));
                $cFin = $cIni + ($cDur * 60);
                if ($slotStartTs < $cFin && $slotEndTs > $cIni) { $hasCitaCol = true; break; }
            }
            if ($hasCitaCol) continue;

            // Barbero libre encontrado!
            $selectedBarber = $cand;
            break;
        }

        if (!$selectedBarber) {
            throw new Exception('Lo sentimos, todos los barberos se encuentran ocupados para este horario. Por favor selecciona otra hora.');
        }

        $barberoId = $selectedBarber['id'];
        $nombreBarbero = $selectedBarber['nombre'];
    } else {
        // Validar disponibilidad del barbero seleccionado
        $stmtBarbero = $pdo->prepare("SELECT id, nombre, sucursal_id, almuerzo_inicio, almuerzo_fin, almuerzo_activo FROM usuarios WHERE id = ?");
        $stmtBarbero->execute([$barberoId]);
        $barberoData = $stmtBarbero->fetch(PDO::FETCH_ASSOC);
        if (!$barberoData) {
            throw new Exception('El barbero seleccionado no existe.');
        }
        $nombreBarbero = $barberoData['nombre'];

        // Verificar si tiene citas activas en esta fecha
        $stmtCitasCount = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE barbero_id = ? AND DATE(fecha_hora) = ? AND estado NOT IN ('cancelada', 'no_asistio')");
        $stmtCitasCount->execute([$barberoId, $fecha]);
        $hasCitasOnDate = intval($stmtCitasCount->fetchColumn() ?? 0) > 0;

        // 1. Verificar horario base del barbero
        $diaSem = date('w', $slotStartTs);
        $stmtH = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ?");
        $stmtH->execute([$barberoId, $diaSem]);
        $hBase = $stmtH->fetch(PDO::FETCH_ASSOC);

        if ($hBase && intval($hBase['activo']) === 0 && !$hasCitasOnDate) {
            throw new Exception('El barbero no atiende en el día seleccionado.');
        }

        if ($hBase && intval($hBase['activo']) === 1) {
            if ($diaSem != 0 && strtotime("$fecha " . $hBase['hora_fin']) < strtotime("$fecha 20:00:00")) {
                $hBase['hora_fin'] = '20:00:00';
            }
            if ($hasCitasOnDate && strtotime("$fecha " . $hBase['hora_inicio']) > strtotime("$fecha 11:00:00")) {
                $hBase['hora_inicio'] = '11:00:00';
            }
        } else {
            $hBase = [
                'hora_inicio' => ($diaSem == 0) ? '10:00:00' : '11:00:00',
                'hora_fin' => ($diaSem == 0) ? '17:00:00' : '20:00:00',
                'activo' => 1
            ];
        }

        $hStart = strtotime("$fecha " . $hBase['hora_inicio']);
        $hEnd = strtotime("$fecha " . $hBase['hora_fin']);
        if ($slotStartTs < $hStart || $slotEndTs > $hEnd) {
            throw new Exception('El barbero no atiende en ese horario o excede la jornada laboral.');
        }

        // 2. Verificar día bloqueado
        $stmtB = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ? AND todo_el_dia = 1");
        $stmtB->execute([$barberoId, $fecha]);
        if ($stmtB->fetch() && !$hasCitasOnDate) {
            throw new Exception('El barbero no labora en la fecha seleccionada.');
        }

        // 3. Verificar citas ocupadas con duración efectiva completa
        $stmtCcheck = $pdo->prepare("
            SELECT c.fecha_hora, COALESCE(s.duracion_minutos, 60) as duracion_minutos 
            FROM citas c 
            LEFT JOIN servicios s ON c.servicio_id = s.id 
            WHERE c.barbero_id = ? AND c.estado NOT IN ('cancelada', 'no_asistio') AND DATE(c.fecha_hora) = ?
        ");
        $stmtCcheck->execute([$barberoId, $fecha]);
        $citasExistentes = $stmtCcheck->fetchAll(PDO::FETCH_ASSOC);
        foreach ($citasExistentes as $ce) {
            $ceIni = strtotime($ce['fecha_hora']);
            $ceRaw = intval($ce['duracion_minutos']) > 0 ? intval($ce['duracion_minutos']) : 60;
            $ceDur = ($ceRaw <= 30) ? 30 : (($ceRaw <= 60) ? 60 : (int)(ceil($ceRaw / 30) * 30));
            $ceFin = $ceIni + ($ceDur * 60);
            if ($slotStartTs < $ceFin && $slotEndTs > $ceIni) {
                throw new Exception('Lo sentimos, este horario acaba de ser ocupado por otra cita.');
            }
        }

        // 4. Verificar bloqueos por horas
        $stmtBH = $pdo->prepare("SELECT * FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ?");
        $stmtBH->execute([$barberoId, $fecha]);
        $bhs = $stmtBH->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bhs as $bh) {
            $bIni = strtotime("$fecha " . $bh['hora_inicio']);
            $bFin = strtotime("$fecha " . $bh['hora_fin']);
            if ($slotStartTs < $bFin && $slotEndTs > $bIni) {
                throw new Exception('El barbero no se encuentra disponible en este horario (bloqueo programado).');
            }
        }

        // 5. Verificar almuerzo obligatorio: Por defecto todos los barberos tienen almuerzo de 13:00 a 14:00 (bloqueado)
        $almIni = (!empty($barberoData['almuerzo_inicio']) && $barberoData['almuerzo_inicio'] !== '00:00:00') ? $barberoData['almuerzo_inicio'] : '13:00:00';
        $almFin = (!empty($barberoData['almuerzo_fin']) && $barberoData['almuerzo_fin'] !== '00:00:00') ? $barberoData['almuerzo_fin'] : '14:00:00';
        $cLunchStart = strtotime("$fecha $almIni");
        $cLunchEnd = strtotime("$fecha $almFin");
        if ($slotStartTs < $cLunchEnd && $slotEndTs > $cLunchStart) {
            throw new Exception('El barbero se encuentra en su horario de almuerzo (13:00 - 14:00). Por favor selecciona otro horario.');
        }
    }

    // 3. Procesar Preferencias si vienen
    $estiloBuscado = isset($_POST['estilo_buscado']) ? trim($_POST['estilo_buscado']) : null;
    $ambientePreferido = isset($_POST['ambiente_preferido']) ? trim($_POST['ambiente_preferido']) : null;
    $bebidaPreferida = isset($_POST['bebida_preferida']) ? trim($_POST['bebida_preferida']) : null;

    if ($estiloBuscado || $ambientePreferido || $bebidaPreferida) {
        $stmtUpdatePref = $pdo->prepare("
            UPDATE clientes 
            SET estilo_buscado = COALESCE(?, estilo_buscado), 
                ambiente_preferido = COALESCE(?, ambiente_preferido), 
                bebida_preferida = COALESCE(?, bebida_preferida) 
            WHERE id = ?
        ");
        $stmtUpdatePref->execute([$estiloBuscado, $ambientePreferido, $bebidaPreferida, $clienteId]);
    }

    // 4. Procesar Código de Descuento si aplica
    $codigoReferido = strtoupper(trim($_POST['codigo_referido'] ?? ''));
    $montoDescuento = 0.00;
    if (!empty($codigoReferido) && isSistemaReferidosActivo($pdo)) {
        $stmtPromo = $pdo->prepare("SELECT * FROM codigos_promocionales WHERE UPPER(codigo) = ? AND activo = 1");
        $stmtPromo->execute([$codigoReferido]);
        $promoRow = $stmtPromo->fetch(PDO::FETCH_ASSOC);
        if ($promoRow) {
            $montoDescuento = ($promoRow['tipo'] === 'porcentaje') 
                ? ($precio * floatval($promoRow['valor'])) / 100 
                : min($precio, floatval($promoRow['valor']));
        }
    }
    $precioFinal = max(0.00, $precio - $montoDescuento);

    // 5. Insertar la Cita
    $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, precio_final, notas) 
            VALUES (?, ?, ?, ?, ?, 'confirmada', ?, ?)";
    $notas = trim($_POST['notas'] ?? '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clienteId, $servicioId, $barberoId, $sucursalId, $fechaHora, $precioFinal, $notas]);
    $citaId = $pdo->lastInsertId();

    // 6. Preparar datos legibles para respuesta y confirmación
    $tsFecha = strtotime($fecha);
    $diasNombres = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $mesesNombres = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $diaSemanaStr = $diasNombres[date('w', $tsFecha)];
    $diaMesStr = date('j', $tsFecha);
    $mesStr = $mesesNombres[intval(date('n', $tsFecha))];
    $fechaLegible = "$diaSemanaStr $diaMesStr de $mesStr";

    // 7. Notificaciones (Email y WebPush)
    try {
        if (!empty($emailCliente)) {
            require_once __DIR__ . '/../includes/email_helper.php';
            enviarCorreoReserva($emailCliente, $nombreCliente, [
                'servicio' => $nombreServicio,
                'barbero' => $nombreBarbero,
                'fecha' => date('d/m/Y', $tsFecha),
                'hora' => $hora,
                'precio' => number_format($precioFinal, 2)
            ]);
        }
    } catch (Throwable $eMail) {}

    try {
        require_once __DIR__ . '/../includes/webpush_helper.php';
        notificarBarbero($pdo, $barberoId, $citaId, 'nueva_reserva', [
            'cliente' => $nombreCliente,
            'servicio' => $nombreServicio,
            'fecha' => date('d/m/Y', $tsFecha),
            'hora' => $hora,
            'sucursal' => 'KORTZEN Llano Chico'
        ]);
    } catch (Throwable $ePush) {}

    // 8. Generar Google Auth URL
    $googleAuthUrl = function_exists('getGoogleAuthUrl') ? getGoogleAuthUrl('booking_' . $citaId) : '/api/auth/google-config.php';

    echo json_encode([
        'success' => true,
        'cita_id' => $citaId,
        'servicio_nombre' => $nombreServicio,
        'barbero_nombre' => $nombreBarbero,
        'fecha' => $fecha,
        'fecha_legible' => $fechaLegible,
        'hora' => $hora,
        'duracion' => $duracionServicio,
        'precio' => number_format($precioFinal, 2),
        'cliente_nombre' => $nombreCliente,
        'cliente_telefono' => $telefono,
        'google_auth_url' => $googleAuthUrl,
        'message' => '¡Tu cita está confirmada!'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
