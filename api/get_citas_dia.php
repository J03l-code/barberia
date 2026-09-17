<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$reqSucursal = isset($_GET['sucursal_id']) && $_GET['sucursal_id'] !== '' ? intval($_GET['sucursal_id']) : 0;
$userSucursalesIds = getUsuarioSucursalesIds($currentUser['id']);

try {
    $pdo = getConnection();

    $sql = "SELECT c.id, c.fecha_hora, c.estado, c.precio_final, 
                   u.nombre as barbero, 
                   s.nombre as servicio, 
                   cli.nombre as cliente,
                   suc.nombre as sucursal_nombre,
                   ref.codigo_usado as referido_codigo,
                   ref.descuento_aplicado as referido_descuento
            FROM citas c
            JOIN usuarios u ON c.barbero_id = u.id
            JOIN servicios s ON c.servicio_id = s.id
            JOIN clientes cli ON c.cliente_id = cli.id
            JOIN sucursales suc ON c.sucursal_id = suc.id
            LEFT JOIN referidos ref ON c.id = ref.cita_id
            WHERE DATE(c.fecha_hora) = ?";

    $params = [$fecha];

    if (isAdminTecnico()) {
        if ($reqSucursal > 0) {
            $sql .= " AND c.sucursal_id = ?";
            $params[] = $reqSucursal;
        }
    } elseif ($currentUser['rol'] === 'admin_local') {
        if ($reqSucursal > 0 && in_array($reqSucursal, $userSucursalesIds)) {
            $sql .= " AND c.sucursal_id = ?";
            $params[] = $reqSucursal;
        } else {
            if (!empty($userSucursalesIds)) {
                $inList = implode(',', array_map('intval', $userSucursalesIds));
                $sql .= " AND c.sucursal_id IN ($inList)";
            } else {
                $sql .= " AND 1=0";
            }
        }
    } elseif ($currentUser['rol'] === 'barbero') {
        $sql .= " AND c.barbero_id = ?";
        $params[] = $currentUser['id'];
    }

    $sql .= " ORDER BY c.fecha_hora ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalRecaudado = 0;
    $totalCitas = count($citas);

    foreach ($citas as &$cita) {
        if ($cita['estado'] === 'completada') {
            $totalRecaudado += floatval($cita['precio_final']);
        }
    }

    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'citas' => $citas,
        'total_recaudado' => $totalRecaudado,
        'total_citas' => $totalCitas
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
