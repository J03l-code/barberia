<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $sql = "SELECT * FROM galeria_imagenes ORDER BY id DESC"; // LIFO (Last In First Out)
    $stmt = $pdo->query($sql);
    $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($imagenes as &$img) {
        $tLower = strtolower($img['titulo'] ?? '');
        if (stripos($tLower, 'fade') !== false || stripos($tLower, 'corte') !== false) {
            $img['imagen_url'] = '/assets/images/service-corte-mateo.png';
        } elseif (stripos($tLower, 'barba') !== false) {
            $img['imagen_url'] = '/assets/images/service-barba-premium.png';
        } elseif (stripos($tLower, 'spa') !== false || stripos($tLower, 'facial') !== false) {
            $img['imagen_url'] = '/assets/images/service-limpieza-facial.png';
        } elseif (stripos($tLower, 'instalacion') !== false || stripos($tLower, 'espacio') !== false) {
            $img['imagen_url'] = '/assets/images/service-ritual-kortzen.png';
        }
        if (empty($img['imagen_url'])) {
            $img['imagen_url'] = '/assets/images/service-classic-cut.jpg';
        }
    }

    echo json_encode(['success' => true, 'data' => $imagenes]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
