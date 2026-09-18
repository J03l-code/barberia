<?php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar permisos
if (!canManageSchedules()) {
    header('Location: ../dashboard.php?error=' . urlencode('No tienes permiso para gestionar horarios.'));
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$barberoId = intval($_POST['barbero_id'] ?? ($_GET['barbero_id'] ?? 0));
$isAjax = (!empty($_POST['ajax']) || !empty($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false));

if ($barberoId <= 0 && $action !== 'get_barbero_horarios') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Barbero no válido.']);
        exit;
    }
    header('Location: ../horarios.php?error=' . urlencode('Barbero no válido.'));
    exit;
}

try {
    $pdo = getConnection();

    switch ($action) {
        case 'get_barbero_horarios':
            $h = query("SELECT * FROM horarios_barberos WHERE barbero_id = ? ORDER BY dia_semana ASC", [$barberoId]);
            $bDias = query("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha >= CURDATE() ORDER BY fecha ASC", [$barberoId]);
            $bHoras = [];
            try {
                $bHoras = query("SELECT * FROM bloqueos_horas WHERE barbero_id = ? AND fecha >= CURDATE() ORDER BY fecha ASC, hora_inicio ASC", [$barberoId]);
            } catch (Exception $eBh) {}
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'barbero_id' => $barberoId,
                'horarios' => $h,
                'dias_bloqueados' => $bDias,
                'bloqueos_horas' => $bHoras
            ]);
            exit;

        case 'guardar_horarios':
            // Guardar horarios semanales
            $horaInicio = $_POST['hora_inicio'] ?? [];
            $horaFin = $_POST['hora_fin'] ?? [];
            $activo = $_POST['activo'] ?? [];

            // Días de la semana (0-6)
            for ($dia = 0; $dia <= 6; $dia++) {
                $esActivo = isset($activo[$dia]) ? 1 : 0;
                $inicio = $horaInicio[$dia] ?? '10:00';
                $fin = $horaFin[$dia] ?? '20:00';

                // Insertar o actualizar (UPSERT)
                $sql = "INSERT INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) 
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            hora_inicio = VALUES(hora_inicio),
                            hora_fin = VALUES(hora_fin),
                            activo = VALUES(activo)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$barberoId, $dia, $inicio, $fin, $esActivo]);
            }

            registrarLog('UPDATE', 'horarios_barberos', $barberoId, 'Horarios actualizados');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Horarios del barbero guardados correctamente']);
                exit;
            }

            $redirectTarget = (isset($_POST['from_profile']) && $_POST['from_profile'] == '1')
                ? '../barbero_detalle.php?id=' . $barberoId . '&success=' . urlencode('Horarios del barbero guardados correctamente')
                : '../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Horarios guardados correctamente');
            header('Location: ' . $redirectTarget);
            exit;

        case 'agregar_bloqueo':
            $fecha = $_POST['fecha'] ?? '';
            $motivo = trim($_POST['motivo'] ?? 'Día de descanso');

            if (empty($fecha)) {
                throw new Exception('La fecha es obligatoria.');
            }

            // Verificar que la fecha no sea pasada
            if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
                throw new Exception('No se pueden bloquear fechas pasadas.');
            }

            // Insertar bloqueo
            $sql = "INSERT INTO dias_bloqueados (barbero_id, fecha, motivo, creado_por) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$barberoId, $fecha, $motivo, $_SESSION['user_id']]);

            $newId = $pdo->lastInsertId();
            registrarLog('INSERT', 'dias_bloqueados', $newId, "Día bloqueado: $fecha - $motivo");

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Día bloqueado agregado exitosamente', 'id' => $newId]);
                exit;
            }

            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Día bloqueado agregado'));
            exit;

        case 'eliminar_bloqueo':
            $bloqueoId = intval($_POST['bloqueo_id'] ?? 0);

            if ($bloqueoId <= 0) {
                throw new Exception('Bloqueo no válido.');
            }

            $sql = "DELETE FROM dias_bloqueados WHERE id = ? AND barbero_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$bloqueoId, $barberoId]);

            registrarLog('DELETE', 'dias_bloqueados', $bloqueoId, 'Día bloqueado eliminado');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Día bloqueado eliminado exitosamente']);
                exit;
            }

            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Día bloqueado eliminado'));
            exit;

        case 'agregar_bloqueo_hora':
            $fecha = $_POST['fecha'] ?? '';
            $horaInicio = $_POST['hora_inicio'] ?? '';
            $horaFin = $_POST['hora_fin'] ?? '';
            $motivo = trim($_POST['motivo'] ?? 'Bloqueo parcial');

            if (empty($fecha) || empty($horaInicio) || empty($horaFin)) {
                throw new Exception('Fecha y horas son obligatorias.');
            }

            if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
                throw new Exception('No se pueden bloquear fechas pasadas.');
            }

            if (strtotime($horaFin) <= strtotime($horaInicio)) {
                throw new Exception('La hora de fin debe ser posterior a la de inicio.');
            }

            $sql = "INSERT INTO bloqueos_horas (barbero_id, fecha, hora_inicio, hora_fin, motivo, creado_por) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$barberoId, $fecha, $horaInicio, $horaFin, $motivo, $_SESSION['user_id']]);

            $newBhId = $pdo->lastInsertId();
            registrarLog('INSERT', 'bloqueos_horas', $newBhId, "Bloqueo hora: $fecha ($horaInicio-$horaFin) - $motivo");

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Bloqueo de horario agregado exitosamente', 'id' => $newBhId]);
                exit;
            }

            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Bloqueo por horas agregado'));
            exit;

        case 'eliminar_bloqueo_hora':
            $bloqueoId = intval($_POST['bloqueo_id'] ?? 0);

            if ($bloqueoId <= 0) {
                throw new Exception('Bloqueo de hora no válido.');
            }

            $sql = "DELETE FROM bloqueos_horas WHERE id = ? AND barbero_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$bloqueoId, $barberoId]);

            registrarLog('DELETE', 'bloqueos_horas', $bloqueoId, 'Bloqueo de hora eliminado');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Bloqueo de horario eliminado exitosamente']);
                exit;
            }

            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Bloqueo de horas eliminado'));
            exit;

        default:
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                exit;
            }
            header('Location: ../horarios.php?barbero=' . $barberoId);
            exit;
    }

} catch (PDOException $e) {
    error_log("Error en horarios_action.php: " . $e->getMessage());

    $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) 
        ? 'Esta fecha u horario ya está bloqueado.' 
        : 'Error en la base de datos al procesar el horario.';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    header('Location: ../horarios.php?barbero=' . $barberoId . '&error=' . urlencode($msg));
    exit;

} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header('Location: ../horarios.php?barbero=' . $barberoId . '&error=' . urlencode($e->getMessage()));
    exit;
}
