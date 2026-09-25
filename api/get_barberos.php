<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    $sucursal_id = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

    // Base query: Active users with role 'barbero' or 'admin_local'
    // If sucursal_id is provided, filter by it.
    // If not, we might return empty or all. Let's return empty if no branch ID to be safe, or allow 0 for all.

    $params = [];
    $whereAuto = "activo = 1 AND (rol = 'barbero' OR rol = 'admin_local')";

    if ($sucursal_id > 0) {
        $sql = "SELECT * FROM usuarios WHERE $whereAuto AND (sucursal_id = ? OR sucursal_id IS NULL OR sucursal_id = 0) ORDER BY id ASC";
        $params[] = $sucursal_id;
    } else {
        $sql = "SELECT * FROM usuarios WHERE $whereAuto ORDER BY id ASC";
    }


    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $barberos = [];
    $seen = [];
    foreach ($raw_barberos as $barbero) {
        $nameKey = strtolower(trim($barbero['nombre']));
        if (!isset($seen[$nameKey])) {
            $seen[$nameKey] = true;
            $barbero['cargo'] = ($barbero['rol'] === 'admin_local') ? 'Gerente / Master Barber' : 'Barbero Profesional';
            
            $foto = !empty($barbero['foto_url']) ? $barbero['foto_url'] : (!empty($barbero['foto']) ? $barbero['foto'] : '');
            
            // Mapeo robusto a las imágenes existentes
            if (stripos($nameKey, 'mateo') !== false) {
                $foto = '/assets/images/barber-mateo.jpg';
            } elseif (stripos($nameKey, 'jeffrey') !== false || stripos($nameKey, 'jefrey') !== false) {
                $foto = '/assets/images/barber-jeffrey.jpg';
            } elseif (stripos($nameKey, 'joel') !== false) {
                $foto = '/assets/images/barber-joel.jpg';
            } elseif (stripos($nameKey, 'jonathan') !== false) {
                $foto = '/assets/images/barber-jonathan.jpg';
            } elseif (stripos($nameKey, 'josh') !== false) {
                $foto = '/assets/images/barber-josh.jpg';
            }

            $barbero['foto'] = !empty($foto) ? $foto : '/assets/images/barber-mateo.jpg';
            $barbero['foto_url'] = $barbero['foto'];
            $barbero['foto_perfil'] = $barbero['foto'];
            $barbero['biografia'] = !empty($barbero['biografia']) ? $barbero['biografia'] : (!empty($barbero['bio']) ? $barbero['bio'] : '');
            $barbero['bio'] = $barbero['biografia'];
            $barberos[] = $barbero;
        }
    }

    echo json_encode(['success' => true, 'data' => $barberos]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
