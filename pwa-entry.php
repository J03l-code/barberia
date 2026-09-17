<?php
/**
 * KORTZEN - PWA Router Entry Point
 * Redirects the user to their appropriate dashboard depending on session state
 */
require_once 'config.php';

if (isClienteLoggedIn()) {
    header('Location: cliente-dashboard.php');
    exit;
} elseif (isLoggedIn()) {
    $u = getCurrentUser();
    if ($u['rol'] === 'barbero') {
        header('Location: barber-dashboard.php');
    } else {
        header('Location: admin-agenda.php');
    }
    exit;
} else {
    // If not authenticated, redirect to client login screen by default
    header('Location: cliente-login.php');
    exit;
}
