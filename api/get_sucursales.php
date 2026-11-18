<?php
/**
 * KORTZEN - API Pública de Sucursales (Optimizada sin DDL)
 * Retorna las sucursales activas y próximas aperturas
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $pdo = getConnection();

    // Obtener columnas existentes de forma segura
    $existingCols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM sucursales");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingCols[] = $r['Field'];
        }
    } catch (Exception $e) {}

    $hasEstado = in_array('estado', $existingCols);
    $hasApertura = in_array('horario_apertura', $existingCols);
    $hasCierre = in_array('horario_cierre', $existingCols);
    $hasMapa = in_array('mapa_url', $existingCols);

    $sql = "SELECT id, nombre, direccion, telefono";
    if ($hasApertura) $sql .= ", horario_apertura";
    if ($hasCierre) $sql .= ", horario_cierre";
    if ($hasEstado) $sql .= ", COALESCE(estado, 'activo') as estado";
    if ($hasMapa) $sql .= ", mapa_url";
    $sql .= " FROM sucursales";

    if ($hasEstado) {
        $sql .= " WHERE COALESCE(estado, 'activo') IN ('activo', 'proximamente')";
        $sql .= " ORDER BY CASE WHEN COALESCE(estado, 'activo') = 'activo' THEN 1 ELSE 2 END, id ASC";
    } else {
        $sql .= " ORDER BY id ASC";
    }

    $stmt = $pdo->query($sql);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear datos para cliente
    $formatted = [];
    foreach ($sucursales as $s) {
        $estado = $s['estado'] ?? 'activo';
        if ($estado === 'inactivo') continue;

        $rawOpen = trim((string)($s['horario_apertura'] ?? ''));
        $rawClose = trim((string)($s['horario_cierre'] ?? ''));

        $openTime = '10:00';
        if (!empty($rawOpen) && $rawOpen !== '00:00:00' && $rawOpen !== '00:00') {
            $parsed = date('H:i', strtotime($rawOpen));
            if ($parsed && $parsed !== '00:00') {
                $openTime = $parsed;
            }
        }

        $closeTime = '20:00';
        if (!empty($rawClose) && $rawClose !== '00:00:00' && $rawClose !== '00:00') {
            $parsed = date('H:i', strtotime($rawClose));
            if ($parsed && $parsed !== '00:00') {
                $closeTime = $parsed;
            }
        }

        $formatted[] = [
            'id' => intval($s['id']),
            'name' => $s['nombre'],
            'address' => $s['direccion'] ?: 'Quito',
            'phone' => $s['telefono'] ?: '',
            'openTime' => $openTime,
            'closeTime' => $closeTime,
            'estado' => $estado,
            'isProximamente' => ($estado === 'proximamente'),
            'mapa_url' => $s['mapa_url'] ?? null
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $formatted
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
