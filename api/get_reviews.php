<?php
require_once '../config.php';

// Prevent HTML error output from corrupting JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    $reviews = [];
    try {
        $reviews = query("SELECT id, cliente_nombre, comentario, calificacion, fecha FROM resenas WHERE visible = 1 ORDER BY COALESCE(fecha, id) DESC, id DESC LIMIT 10");
    } catch (Exception $e) {
        $reviews = query("SELECT * FROM resenas WHERE visible = 1 ORDER BY id DESC LIMIT 10");
    }
    echo json_encode(['success' => true, 'reviews' => $reviews]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'reviews' => []]);
}

