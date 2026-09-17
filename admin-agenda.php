<?php
/**
 * KORTZEN - Redirección a la PWA de Administración
 */
require_once 'config.php';
requireLogin();

$sucursal_id = intval($_GET['sucursal_id'] ?? 0);
$url = 'pwa-admin.php?tab=agenda' . ($sucursal_id > 0 ? '&sucursal_id=' . $sucursal_id : '');
header('Location: ' . $url);
exit;
