<?php
require_once '../config.php';
require_once __DIR__ . '/../includes/webpush_helper.php';

$action = $_POST['action'] ?? '';

if ($action !== 'cancelar_cita_cliente' && !isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = getConnection();

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

    $redirect_url = '../citas.php'; // Default
    if (!empty($_POST['return_url'])) {
        $redirect_url = $_POST['return_url'];
    } elseif (!empty($_GET['return_url'])) {
        $redirect_url = $_GET['return_url'];
    } elseif (isset($_POST['redirect_source']) && $_POST['redirect_source'] === 'dashboard') {
        $redirect_url = '../dashboard.php';
    } elseif (!empty($_SERVER['HTTP_REFERER']) && (strpos($_SERVER['HTTP_REFERER'], 'citas.php') !== false || strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false)) {
        $redirect_url = $_SERVER['HTTP_REFERER'];
    }

    if (!function_exists('responderAccionCita')) {
        function responderAccionCita($isAjax, $redirectUrl, $msgSuccess, $extraData = []) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(array_merge([
                    'success' => true,
                    'message' => $msgSuccess
                ], $extraData));
                exit;
            }

            $parts = explode('#', $redirectUrl, 2);
            $urlWithoutHash = $parts[0];
            $hash = isset($parts[1]) ? '#' . $parts[1] : '';

            $qParts = explode('?', $urlWithoutHash, 2);
            $path = $qParts[0];
            $query = [];
            if (isset($qParts[1])) {
                parse_str($qParts[1], $query);
            }
            $query['success'] = $msgSuccess;
            header('Location: ' . $path . '?' . http_build_query($query) . $hash);
            exit;
        }
    }

    switch ($action) {
        case 'cancelar_cita_cliente':
            if (!isClienteLoggedIn()) {
                throw new Exception('Debes iniciar sesión.');
            }
            $clienteId = $_SESSION['cliente_id'];
            $citaId = intval($_POST['id'] ?? 0);

            if ($citaId <= 0) {
                throw new Exception('Cita inválida.');
            }

            // Verificar propiedad de la cita y tiempo límite (> 2 horas)
            $stmtCita = $pdo->prepare("SELECT id, barbero_id, fecha_hora, estado FROM citas WHERE id = ? AND cliente_id = ?");
            $stmtCita->execute([$citaId, $clienteId]);
            $cita = $stmtCita->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                throw new Exception('La cita no fue encontrada.');
            }

            if ($cita['estado'] === 'cancelada') {
                throw new Exception('Esta cita ya se encuentra cancelada.');
            }

            $timestampCita = strtotime($cita['fecha_hora']);
            $diferenciaHoras = ($timestampCita - time()) / 3600;

            if ($diferenciaHoras < 2) {
                throw new Exception('Las citas solo se pueden cancelar con al menos 2 horas de anticipación. Por favor contacta directamente a la barbería.');
            }

            $stmtCancel = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?");
            $stmtCancel->execute([$citaId]);

            // Notificar al barbero en tiempo real
            try {
                notificarBarbero($pdo, $cita['barbero_id'] ?? 0, $citaId, 'cita_cancelada');
            } catch (Exception $eNotif) {}

            header('Location: ../cliente-dashboard.php?success=' . urlencode('Tu cita ha sido cancelada exitosamente.'));
            exit;

        case 'crear_manual':
            if (!in_array($_SESSION['user_rol'] ?? '', ['admin', 'admin_local', 'administrador', 'superadmin'])) {
                throw new Exception('Permisos insuficientes.');
            }

            $cliNombre = trim($_POST['cliente_nombre'] ?? '');
            $cliTel = trim($_POST['cliente_telefono'] ?? '');
            $barbero_id = intval($_POST['barbero_id'] ?? 0);
            $servicio_id = intval($_POST['servicio_id'] ?? 0);
            $fecha = trim($_POST['fecha'] ?? '');
            $hora = trim($_POST['hora'] ?? '');

            if (empty($cliNombre) || !$barbero_id || !$servicio_id || empty($fecha) || empty($hora)) {
                throw new Exception('Faltan campos obligatorios para agendar la cita.');
            }

            // Obtener sucursal del barbero
            $stmtBInfo = $pdo->prepare("SELECT sucursal_id, nombre FROM usuarios WHERE id = ?");
            $stmtBInfo->execute([$barbero_id]);
            $bRow = $stmtBInfo->fetch(PDO::FETCH_ASSOC);
            $sucursal_id = $bRow['sucursal_id'] ?? 1;

            // Obtener o registrar cliente
            $cliente_id = 0;
            if (!empty($cliTel)) {
                $stmtClFind = $pdo->prepare("SELECT id FROM clientes WHERE telefono = ? LIMIT 1");
                $stmtClFind->execute([$cliTel]);
                $cliente_id = intval($stmtClFind->fetchColumn() ?? 0);
            }

            if ($cliente_id <= 0) {
                // Crear nuevo cliente rápido
                $fakeEmail = 'cli_' . time() . '_' . rand(100, 999) . '@kortzen.com';
                $stmtNewCl = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email, activo) VALUES (?, ?, ?, 1)");
                $stmtNewCl->execute([$cliNombre, $cliTel, $fakeEmail]);
                $cliente_id = intval($pdo->lastInsertId());
            }

            // Obtener duración del servicio
            $stmtServ = $pdo->prepare("SELECT nombre, duracion_minutos, precio FROM servicios WHERE id = ?");
            $stmtServ->execute([$servicio_id]);
            $servRow = $stmtServ->fetch(PDO::FETCH_ASSOC);
            $duracionMin = intval($servRow['duracion_minutos'] ?? 45);
            $precioServicio = floatval($servRow['precio'] ?? 0.00);

            $fecha_hora = $fecha . ' ' . $hora . ':00';
            $horaFinStr = date('H:i:s', strtotime($fecha_hora) + ($duracionMin * 60));

            $stmtInsert = $pdo->prepare("
                INSERT INTO citas (cliente_id, cliente_nombre, cliente_telefono, servicio_id, barbero_id, sucursal_id, fecha_hora, hora_fin, estado, precio_final, notas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', ?, 'Cita manual agendada por administración')
            ");
            $stmtInsert->execute([$cliente_id, $cliNombre, $cliTel, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $horaFinStr, $precioServicio]);
            $newCitaId = intval($pdo->lastInsertId());

            registrarLog('CREAR', 'citas', $newCitaId, "Cita manual rápida #$newCitaId creada para '$cliNombre' con barbero #$barbero_id");

            try {
                notificarBarbero($pdo, $barbero_id, $newCitaId, 'nueva_reserva');
            } catch (Exception $eN) {}

            responderAccionCita($isAjax, $redirect_url, 'Cita agendada exitosamente.', ['cita_id' => $newCitaId]);

        case 'create':
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $servicio_id = intval($_POST['servicio_id'] ?? 0);
            $barbero_id = intval($_POST['barbero_id'] ?? 0);
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
            $fecha = $_POST['fecha'] ?? '';
            $hora = $_POST['hora'] ?? '';
            $estado = $_POST['estado'] ?? 'pendiente';
            $notas = trim($_POST['notas'] ?? '');

            if (!$cliente_id || !$servicio_id || !$barbero_id || !$sucursal_id || !$fecha || !$hora) {
                throw new Exception('Todos los campos son obligatorios.');
            }

            // Validar si el servicio es exclusivo de un barbero
            $sData = $pdo->query("SELECT nombre, barbero_id FROM servicios WHERE id = $servicio_id")->fetch(PDO::FETCH_ASSOC);
            if ($sData && !empty($sData['barbero_id']) && intval($sData['barbero_id']) !== $barbero_id) {
                $bName = $pdo->query("SELECT nombre FROM usuarios WHERE id = " . intval($sData['barbero_id']))->fetchColumn();
                throw new Exception("El servicio '{$sData['nombre']}' es exclusivo de " . ($bName ?: 'otro barbero') . ".");
            }

            $fecha_hora = $fecha . ' ' . $hora . ':00';

            $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cliente_id, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $estado, $notas]);

            $newCitaId = $pdo->lastInsertId();

            $stmtCName = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
            $stmtCName->execute([$cliente_id]);
            $clienteNombre = $stmtCName->fetchColumn() ?: "Cliente #$cliente_id";

            registrarLog('CREAR', 'citas', $newCitaId, "Cita agendada para el cliente '$clienteNombre' ($fecha_hora)");

            // Notificar al Barbero Asignado (PWA + WebPush)
            try {
                notificarBarbero($pdo, $barbero_id, $newCitaId, 'nueva_reserva');
            } catch (Exception $eNotif) {}

            // Enviar correo de confirmación al cliente si tiene correo registrado
            try {
                $stmtClient = $pdo->prepare("SELECT nombre, email FROM clientes WHERE id = ?");
                $stmtClient->execute([$cliente_id]);
                $cData = $stmtClient->fetch(PDO::FETCH_ASSOC);

                if ($cData && !empty($cData['email'])) {
                    require_once __DIR__ . '/../includes/email_helper.php';
                    $stmtSrv = $pdo->prepare("SELECT nombre, precio FROM servicios WHERE id = ?");
                    $stmtSrv->execute([$servicio_id]);
                    $srvData = $stmtSrv->fetch(PDO::FETCH_ASSOC);

                    $stmtBarb = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
                    $stmtBarb->execute([$barbero_id]);
                    $barbName = $stmtBarb->fetchColumn() ?: 'Barbero Profesional';

                    enviarCorreoReserva($cData['email'], $cData['nombre'], [
                        'servicio' => $srvData['nombre'] ?? 'Servicio de Barbería',
                        'barbero' => $barbName,
                        'fecha' => date('d/m/Y', strtotime($fecha)),
                        'hora' => $hora,
                        'precio' => number_format(floatval($srvData['precio'] ?? 0), 2)
                    ]);
                }
            } catch (Throwable $eMail) {}

            header('Location: ../citas.php?success=Cita creada exitosamente');
            exit;

        case 'cancelar_barbero':
            // Permitir a barbero, admin y admin_local
            if (!in_array($_SESSION['user_rol'], ['barbero', 'admin', 'admin_local'])) {
                throw new Exception('No permitido. Rol actual: ' . ($_SESSION['user_rol'] ?? 'ninguno'));
            }

            $id = intval($_POST['id'] ?? 0);
            $barberoId = $_SESSION['user_id'];

            // Si es un barbero normal, verificar que es SU cita
            if ($_SESSION['user_rol'] === 'barbero') {
                $stmtCheck = $pdo->prepare("SELECT id FROM citas WHERE id = ? AND barbero_id = ?");
                $stmtCheck->execute([$id, $barberoId]);
                if (!$stmtCheck->fetch()) {
                    throw new Exception('Esta cita no te pertenece.');
                }
            }
            // Si es admin, no verificamos ownership, confiamos en su poder.

            $stmtCInfo = $pdo->prepare("SELECT cita.cliente_id, c.nombre FROM citas cita JOIN clientes c ON cita.cliente_id = c.id WHERE cita.id = ?");
            $stmtCInfo->execute([$id]);
            $citaCli = $stmtCInfo->fetch(PDO::FETCH_ASSOC);
            $clienteNombre = $citaCli ? $citaCli['nombre'] : "Cita #$id";
            $clienteIdNotif = $citaCli ? $citaCli['cliente_id'] : 0;

            $sql = "UPDATE citas SET estado = 'cancelada' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            // Notificar al cliente si existe
            if ($clienteIdNotif > 0) {
                try {
                    notificarCliente($pdo, $clienteIdNotif, $id, 'Cita Cancelada', 'Tu cita de barbería ha sido cancelada por la administración o barbero.', '/cliente-dashboard.php');
                } catch (Exception $eNotif) {}
            }

            registrarLog('CANCELAR', 'citas', $id, "Cita #$id cancelada para el cliente '$clienteNombre'");
            responderAccionCita($isAjax, $redirect_url, 'Cita cancelada correctamente.', ['cita_id' => $id]);

        case 'completar':
            if (!in_array($_SESSION['user_rol'], ['admin', 'admin_local'])) {
                throw new Exception('Acceso denegado. Solo la administración puede finalizar citas y registrar propinas.');
            }

            $id = intval($_POST['id'] ?? 0);
            $propina = floatval($_POST['propina'] ?? 0.00);
            if ($propina < 0) $propina = 0.00;

            // Array de materiales y cantidades
            $materiales = $_POST['materiales'] ?? [];
            $cantidades = $_POST['cantidades'] ?? [];

            if ($id <= 0) {
                throw new Exception('ID inválido');
            }

            // 1. Marcar completada con propina
            try {
                $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_final");
            } catch (Exception $exP) {}

            $stmtComp = $pdo->prepare("UPDATE citas SET estado = 'completada', propina = ? WHERE id = ?");
            $stmtComp->execute([$propina, $id]);

            // Cargar puntos configurados
            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
            $puntosPorCorte = intval($cfgs['puntos_por_corte'] ?? 100);

            // Sumar Puntos KORTZEN al cliente por completar cita
            $stmtCitaClient = $pdo->prepare("SELECT cliente_id FROM citas WHERE id = ?");
            $stmtCitaClient->execute([$id]);
            $clientRow = $stmtCitaClient->fetch();
            if ($clientRow && !empty($clientRow['cliente_id'])) {
                try {
                    $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
                } catch (Exception $ex) {}
                $stmtAddPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                $stmtAddPts->execute([$puntosPorCorte, $clientRow['cliente_id']]);
            }

            // Procesar recompensa de referido pendiente
            try {
                $stmtPendingRef = $pdo->prepare("SELECT * FROM referidos WHERE cita_id = ? AND estado = 'pendiente'");
                $stmtPendingRef->execute([$id]);
                $refPending = $stmtPendingRef->fetch(PDO::FETCH_ASSOC);

                if ($refPending) {
                    $referenteId = $refPending['referente_id'];
                    $puntosBonus = intval($refPending['puntos_otorgados'] ?? 200);

                    // 1. Marcar referido como completado
                    $stmtMarkRef = $pdo->prepare("UPDATE referidos SET estado = 'completado' WHERE id = ?");
                    $stmtMarkRef->execute([$refPending['id']]);

                    // 2. Sumar puntos bonus al referente
                    if ($referenteId > 0 && $puntosBonus > 0) {
                        $stmtAddRefPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                        $stmtAddRefPts->execute([$puntosBonus, $referenteId]);
                    }
                }
            } catch (Exception $exRef) {}

            // Obtener sucursal de la cita
            $stmtCitaInfo = $pdo->prepare("SELECT sucursal_id FROM citas WHERE id = ?");
            $stmtCitaInfo->execute([$id]);
            $citaInfo = $stmtCitaInfo->fetch();
            $sucursal_id = $citaInfo['sucursal_id'] ?? 0;
            $usuario_id = $_SESSION['user_id'];

            // 2. Procesar inventario y Registrar Venta
            if (!empty($materiales)) {
                $stmtStock = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?");
                $stmtPrice = $pdo->prepare("SELECT precio FROM inventario WHERE id = ?");
                $stmtVenta = $pdo->prepare("INSERT INTO ventas_productos (cita_id, producto_id, cantidad, precio_unitario, sucursal_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");

                for ($i = 0; $i < count($materiales); $i++) {
                    $prodId = intval($materiales[$i]);
                    $cant = floatval($cantidades[$i]);

                    if ($prodId > 0 && $cant > 0) {
                        $stmtStock->execute([$cant, $prodId]);

                        $stmtPrice->execute([$prodId]);
                        $prodInfo = $stmtPrice->fetch();
                        $precioUnitario = $prodInfo['precio'] ?? 0;

                        if ($sucursal_id > 0) {
                            $stmtVenta->execute([$id, $prodId, $cant, $precioUnitario, $sucursal_id, $usuario_id]);
                        }
                    }
                }
            }

            $stmtCDetails = $pdo->prepare("
                SELECT c.nombre as cliente_nombre, s.nombre as servicio_nombre, b.nombre as barbero_nombre, cita.precio_final
                FROM citas cita
                LEFT JOIN clientes c ON cita.cliente_id = c.id
                LEFT JOIN servicios s ON cita.servicio_id = s.id
                LEFT JOIN usuarios b ON cita.barbero_id = b.id
                WHERE cita.id = ?
            ");
            $stmtCDetails->execute([$id]);
            $cD = $stmtCDetails->fetch(PDO::FETCH_ASSOC);

            $cNombre = $cD ? $cD['cliente_nombre'] : "Cita #$id";
            $sNombre = $cD ? $cD['servicio_nombre'] : "Servicio";
            $bNombre = $cD ? $cD['barbero_nombre'] : "Barbero";
            $precioVal = number_format(floatval($cD['precio_final'] ?? 0), 2);

            registrarLog('COMPLETAR', 'citas', $id, "Cita #$id finalizada para el cliente '$cNombre' (Servicio: '$sNombre', Barbero: '$bNombre', Valor: $$precioVal, Propina: $$propina, +$puntosPorCorte pts ganados)");

            $msgOk = 'Cita completada con éxito.';
            if ($propina > 0) {
                $msgOk .= ' Propina registrada para el barbero: $' . number_format($propina, 2);
            }
            responderAccionCita($isAjax, $redirect_url, $msgOk, ['cita_id' => $id, 'propina' => $propina]);

        case 'cambiar_estado':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            $nuevoEstado = trim($_POST['estado'] ?? $_GET['estado'] ?? '');
            $propina = floatval($_POST['propina'] ?? 0.00);
            if ($propina < 0) $propina = 0.00;

            $validEstados = ['pendiente', 'confirmada', 'en_atencion', 'completada', 'cancelada'];

            if ($id <= 0 || !in_array($nuevoEstado, $validEstados)) {
                throw new Exception('Parámetros de cita o estado inválidos.');
            }

            // Obtener estado anterior y detalles de la cita
            $stmtCitaInfo = $pdo->prepare("
                SELECT c.*, cli.id as cliente_id, cli.nombre as cliente_nombre, s.nombre as servicio_nombre, u.nombre as barbero_nombre
                FROM citas c
                LEFT JOIN clientes cli ON c.cliente_id = cli.id
                LEFT JOIN servicios s ON c.servicio_id = s.id
                LEFT JOIN usuarios u ON c.barbero_id = u.id
                WHERE c.id = ?
            ");
            $stmtCitaInfo->execute([$id]);
            $citaRow = $stmtCitaInfo->fetch(PDO::FETCH_ASSOC);

            if (!$citaRow) {
                throw new Exception('La cita no existe.');
            }

            // Validar Permisos de Acceso y Propiedad de Cita (RBAC & IDOR Protection)
            $userRol = $_SESSION['user_rol'] ?? '';
            $userId = intval($_SESSION['user_id'] ?? 0);
            $userSucursal = intval($_SESSION['user_sucursal_id'] ?? 0);

            if ($userRol === 'barbero' && intval($citaRow['barbero_id']) !== $userId) {
                throw new Exception('Acceso denegado: Solo puedes modificar tus propias citas.');
            }

            if ($userRol === 'admin_local' && $userSucursal > 0 && intval($citaRow['sucursal_id']) !== $userSucursal) {
                throw new Exception('Acceso denegado: Esta cita pertenece a otra sucursal.');
            }

            $estadoAnterior = $citaRow['estado'];

            // Actualizar estado (y propina si se envió)
            if ($nuevoEstado === 'completada' && $propina >= 0) {
                try {
                    $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_final");
                } catch (Exception $exP) {}

                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = ?, propina = ? WHERE id = ?");
                $stmtUpdate->execute([$nuevoEstado, $propina, $id]);
            } elseif ($nuevoEstado === 'pendiente') {
                // Si el admin resetea a Pendiente, resetear asistencia_confirmada = 0 en toda la plataforma
                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'pendiente', asistencia_confirmada = 0 WHERE id = ?");
                $stmtUpdate->execute([$id]);
            } elseif ($nuevoEstado === 'confirmada') {
                // Si el admin marca como Confirmada, marcar asistencia_confirmada = 1
                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'confirmada', asistencia_confirmada = 1 WHERE id = ?");
                $stmtUpdate->execute([$id]);
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = ? WHERE id = ?");
                $stmtUpdate->execute([$nuevoEstado, $id]);
            }

            // Si cambió a COMPLETADA y antes NO estaba completada: Otorgar puntos de fidelidad y bonus referido
            if ($nuevoEstado === 'completada' && $estadoAnterior !== 'completada') {
                if (!empty($citaRow['cliente_id'])) {
                    // Cargar puntos configurados
                    $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
                    $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
                    $puntosPorCorte = intval($cfgs['puntos_por_corte'] ?? 100);

                    try {
                        $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
                    } catch (Exception $ex) {}
                    $stmtAddPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                    $stmtAddPts->execute([$puntosPorCorte, $citaRow['cliente_id']]);
                }

                try {
                    $stmtPendingRef = $pdo->prepare("SELECT * FROM referidos WHERE cita_id = ? AND estado = 'pendiente'");
                    $stmtPendingRef->execute([$id]);
                    $refPending = $stmtPendingRef->fetch(PDO::FETCH_ASSOC);

                    if ($refPending) {
                        $referenteId = $refPending['referente_id'];
                        $puntosBonus = intval($refPending['puntos_otorgados'] ?? 200);

                        $stmtMarkRef = $pdo->prepare("UPDATE referidos SET estado = 'completado' WHERE id = ?");
                        $stmtMarkRef->execute([$refPending['id']]);

                        if ($referenteId > 0 && $puntosBonus > 0) {
                            $stmtAddRefPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                            $stmtAddRefPts->execute([$puntosBonus, $referenteId]);
                        }
                    }
                } catch (Exception $exRef) {}
            }

            // Notificaciones en tiempo real al Barbero Asignado (PWA + WebPush)
            $barberoIdNotif = intval($citaRow['barbero_id'] ?? 0);
            if ($barberoIdNotif > 0) {
                try {
                    $tipoNotif = 'cambio_estado';
                    if ($nuevoEstado === 'confirmada') $tipoNotif = 'cita_confirmada';
                    elseif ($nuevoEstado === 'cancelada') $tipoNotif = 'cita_cancelada';
                    
                    notificarBarbero($pdo, $barberoIdNotif, $id, $tipoNotif, [
                        'cliente' => $citaRow['cliente_nombre'] ?? 'Cliente',
                        'servicio' => $citaRow['servicio_nombre'] ?? 'Servicio',
                        'fecha' => date('d/m/Y', strtotime($citaRow['fecha_hora'])),
                        'hora' => date('H:i', strtotime($citaRow['fecha_hora']))
                    ]);
                } catch (Exception $eNotif) {}
            }

            // Notificar al Cliente si se cancela o confirma
            $clienteIdNotif = intval($citaRow['cliente_id'] ?? 0);
            if ($clienteIdNotif > 0) {
                try {
                    if ($nuevoEstado === 'confirmada') {
                        notificarCliente($pdo, $clienteIdNotif, $id, 'Cita Confirmada', 'Tu cita en Kortzen ha sido confirmada por el equipo.', '/cliente-dashboard.php');
                    } elseif ($nuevoEstado === 'cancelada') {
                        notificarCliente($pdo, $clienteIdNotif, $id, 'Cita Cancelada', 'Tu cita de barbería ha sido cancelada.', '/cliente-dashboard.php');
                    }
                } catch (Exception $eNotif) {}
            }

            $cNombre = $citaRow['cliente_nombre'] ?? "Cita #$id";
            $sNombre = $citaRow['servicio_nombre'] ?? "Servicio";
            registrarLog('EDITAR', 'citas', $id, "Estado de cita #$id ($sNombre) cambiado a '$nuevoEstado' para '$cNombre'");

            responderAccionCita($isAjax, $redirect_url, "Estado de cita actualizado exitosamente a " . ucfirst($nuevoEstado), ['cita_id' => $id, 'estado' => $nuevoEstado]);

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de cita inválido.');
            }

            $userRol = $_SESSION['user_rol'] ?? '';
            $userSucursal = intval($_SESSION['user_sucursal_id'] ?? 0);

            if ($userRol === 'barbero') {
                throw new Exception('Acceso denegado: Los barberos no tienen permisos para eliminar citas.');
            }

            $stmtCDetails = $pdo->prepare("
                SELECT cita.sucursal_id, c.nombre as cliente_nombre, s.nombre as servicio_nombre
                FROM citas cita
                LEFT JOIN clientes c ON cita.cliente_id = c.id
                LEFT JOIN servicios s ON cita.servicio_id = s.id
                WHERE cita.id = ?
            ");
            $stmtCDetails->execute([$id]);
            $cD = $stmtCDetails->fetch(PDO::FETCH_ASSOC);

            if (!$cD) {
                throw new Exception('La cita a eliminar no existe.');
            }

            if ($userRol === 'admin_local' && $userSucursal > 0 && intval($cD['sucursal_id']) !== $userSucursal) {
                throw new Exception('Acceso denegado: No puedes eliminar citas de otra sucursal.');
            }

            $cNombre = $cD['cliente_nombre'] ?? "Cliente";
            $sNombre = $cD['servicio_nombre'] ?? "Servicio";

            $sql = "DELETE FROM citas WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'citas', $id, "Cita #$id ('$sNombre') del cliente '$cNombre' fue eliminada del sistema");

            responderAccionCita($isAjax, $redirect_url, 'Cita eliminada exitosamente.', ['cita_id' => $id]);

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en citas_action.php: " . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
        exit;
    }
    $parts = explode('?', $redirect_url, 2);
    $path = $parts[0];
    $query = [];
    if (isset($parts[1])) parse_str($parts[1], $query);
    $query['error'] = 'Error de base de datos';
    header('Location: ' . $path . '?' . http_build_query($query));
    exit;

} catch (Exception $e) {
    error_log("Error en citas_action.php: " . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    $parts = explode('?', $redirect_url, 2);
    $path = $parts[0];
    $query = [];
    if (isset($parts[1])) parse_str($parts[1], $query);
    $query['error'] = $e->getMessage();
    header('Location: ' . $path . '?' . http_build_query($query));
    exit;
}
