<?php
require_once '../config.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'services'; // services | barbers

try {
    $pdo = getConnection();

    if ($type === 'barbers') {
        $sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

        // Return user photo if available
        $sql = "SELECT u.*, s.nombre as sucursal_nombre
                FROM usuarios u 
                LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                WHERE (u.rol = 'barbero' OR u.rol = 'admin_local') AND u.activo = 1";

        if ($sucursalId > 0) {
            $sql .= " AND (u.sucursal_id = $sucursalId OR u.sucursal_id IS NULL OR u.sucursal_id = 0)";
        }
        $sql .= " ORDER BY u.id ASC";

        $raw_data = query($sql);
        $data = [];
        $seen = [];
        foreach ($raw_data as $b) {
            $nameKey = strtolower(trim($b['nombre']));
            if (!isset($seen[$nameKey])) {
                $seen[$nameKey] = true;
                $foto = !empty($b['foto_url']) ? $b['foto_url'] : (!empty($b['foto']) ? $b['foto'] : (!empty($b['foto_perfil']) ? $b['foto_perfil'] : ''));
                $b['foto_perfil'] = $foto;
                $b['foto_url'] = $foto;
                $b['biografia'] = !empty($b['biografia']) ? $b['biografia'] : (!empty($b['bio']) ? $b['bio'] : '');
                $data[] = $b;
            }
        }
        echo json_encode(['barberos' => $data]);
    } else {
        $sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

        $sql = "SELECT s.*, cs.orden as cat_orden FROM servicios s LEFT JOIN categorias_servicios cs ON s.categoria = cs.nombre";

        if ($sucursalId > 0) {
            try {
                $checkSS = $pdo->query("SELECT COUNT(*) FROM servicios_sucursales WHERE sucursal_id = $sucursalId")->fetchColumn();
                if ($checkSS > 0) {
                    $sql .= " INNER JOIN servicios_sucursales ss ON s.id = ss.servicio_id WHERE s.activo = 1 AND ss.sucursal_id = $sucursalId";
                } else {
                    $sql .= " WHERE s.activo = 1 AND (s.sucursal_id = $sucursalId OR s.sucursal_id IS NULL OR s.sucursal_id = 0)";
                }
            } catch (Throwable $e) {
                $sql .= " WHERE s.activo = 1 AND (s.sucursal_id = $sucursalId OR s.sucursal_id IS NULL OR s.sucursal_id = 0)";
            }
        } else {
            $sql .= " WHERE s.activo = 1";
        }

        $sql .= " ORDER BY COALESCE(cs.orden, 999) ASC, s.categoria ASC, COALESCE(s.orden, 999) ASC, s.id ASC";

        $raw_data = query($sql);

        // Pre-fetch all active barbers for fast lookup
        $allBarbersMap = [];
        $mateoBarber = null;
        try {
            $bList = $pdo->query("SELECT id, nombre FROM usuarios WHERE activo = 1 AND (rol = 'barbero' OR rol = 'admin_local' OR rol = 'admin')")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bList as $ub) {
                $allBarbersMap[$ub['id']] = $ub['nombre'];
                if (!$mateoBarber && (stripos($ub['nombre'], 'mateo') !== false || stripos($ub['nombre'], 'alvaro') !== false)) {
                    $mateoBarber = $ub;
                }
            }
        } catch (Throwable $eb) {}

        // Fetch assigned barbers per service from servicios_barberos
        $serviceBarbersMap = [];
        try {
            $sbRows = $pdo->query("SELECT servicio_id, barbero_id FROM servicios_barberos")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sbRows as $r) {
                $sId = intval($r['servicio_id']);
                $bId = intval($r['barbero_id']);
                if (isset($allBarbersMap[$bId])) {
                    $serviceBarbersMap[$sId][] = $bId;
                }
            }
        } catch (Throwable $e_sb) {}

        $data = [];
        foreach ($raw_data as $s) {
            $sId = intval($s['id']);
            $foto = !empty($s['foto_url']) ? $s['foto_url'] : (!empty($s['imagen_url']) ? $s['imagen_url'] : (!empty($s['foto']) ? $s['foto'] : ''));
            $s['foto_url'] = $foto;
            $s['imagen_url'] = $foto;
            $s['categoria'] = !empty($s['categoria']) ? $s['categoria'] : 'General';
            $s['que_incluye'] = !empty($s['que_incluye']) ? $s['que_incluye'] : '';
            
            $assignedBIds = $serviceBarbersMap[$sId] ?? [];
            
            // Fallback to legacy single barbero_id or Mateo smart detection
            if (empty($assignedBIds)) {
                $singleBId = !empty($s['barbero_id']) ? intval($s['barbero_id']) : null;
                if (!$singleBId && stripos($s['nombre'], 'mateo') !== false && $mateoBarber) {
                    $singleBId = intval($mateoBarber['id']);
                }
                if ($singleBId) {
                    $assignedBIds = [$singleBId];
                }
            }

            $s['barberos_ids'] = $assignedBIds;

            if (count($assignedBIds) === 1) {
                $singleId = $assignedBIds[0];
                $s['barbero_id'] = $singleId;
                $s['barbero_nombre'] = $allBarbersMap[$singleId] ?? 'Barbero Especialista';
            } else {
                $s['barbero_id'] = null;
                $s['barbero_nombre'] = null;
            }

            $data[] = $s;
        }
        echo json_encode(['servicios' => $data]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
