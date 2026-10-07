<?php
require_once 'config.php';
require_once __DIR__ . '/api/auth/google-config.php';

$pageTitle = 'Reservar Cita | KORTZEN Barbería';
$googleAuthUrl = function_exists('getGoogleAuthUrl') ? getGoogleAuthUrl('booking_direct') : '/api/auth/google-config.php';

// Obtener cliente si ya tiene sesión activa
$clienteSesion = isClienteLoggedIn() ? getCurrentCliente() : null;
$clienteNombre = $clienteSesion['nombre'] ?? '';
$clienteTelefono = $clienteSesion['telefono'] ?? '';
$clienteEmail = $clienteSesion['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="canonical" href="https://kortzen.com/reservar">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="48x48" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/favicon.ico?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png?v=10">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-main: #0B0B0D;
            --bg-card: #141417;
            --bg-card-hover: #1A1A1E;
            --border-card: #242429;
            --border-focus: #FFFFFF;
            --color-gold: #C0A062;
            --color-gold-light: #D4AF37;
            --color-white: #FFFFFF;
            --color-gray: #9CA3AF;
            --color-gray-dark: #4B5563;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-main);
            color: var(--color-white);
            font-family: var(--font-family);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Contenedor Principal Estilo App */
        .app-container {
            width: 100%;
            max-width: 520px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg-main);
            padding: 0 16px 40px 16px;
            position: relative;
        }

        /* Header Superior */
        .app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 4px 14px 4px;
            position: sticky;
            top: 0;
            background: var(--bg-main);
            z-index: 50;
        }

        .header-btn {
            background: transparent;
            border: none;
            color: var(--color-white);
            font-size: 1.1rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
            text-decoration: none;
        }

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .brand-logo {
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            color: var(--color-white);
            text-decoration: none;
            text-transform: uppercase;
        }

        .brand-logo span {
            color: var(--color-gold);
        }

        /* Indicador de Pasos (1 2 3 4) */
        .steps-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 8px 0 20px 0;
        }

        .step-pill {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 800;
            background: #1C1C20;
            color: #6B7280;
            transition: all 0.25s ease;
        }

        .step-pill.active {
            background: var(--color-white);
            color: #000000;
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.35);
        }

        .step-pill.completed {
            background: #27272E;
            color: var(--color-white);
        }

        /* Encabezado del Paso */
        .step-header {
            margin-bottom: 20px;
        }

        .step-header__title {
            font-size: 1.55rem;
            font-weight: 900;
            color: var(--color-white);
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }

        .step-header__subtitle {
            font-size: 0.88rem;
            color: var(--color-gray);
            line-height: 1.45;
        }

        /* Secciones de Pasos (Step Views) */
        .step-view {
            display: none;
            flex-direction: column;
            gap: 14px;
            animation: fadeIn 0.25s ease;
        }

        .step-view.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* -------------------------------------------------------------
           PASO 1: TARJETAS DE SERVICIOS
        ------------------------------------------------------------- */
        .service-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-lg);
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .service-card:hover {
            border-color: #383840;
            background: var(--bg-card-hover);
        }

        .service-card.selected {
            border-color: var(--color-white);
            background: #18181D;
        }

        .service-thumb {
            width: 76px;
            height: 76px;
            border-radius: var(--radius-md);
            object-fit: cover;
            background: #1C1C20;
            flex-shrink: 0;
        }

        .service-info {
            flex: 1;
            min-width: 0;
        }

        .service-name {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--color-white);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .service-meta {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--color-gold);
            margin-bottom: 4px;
        }

        .service-desc {
            font-size: 0.76rem;
            color: var(--color-gray);
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .service-select-btn {
            background: var(--color-white);
            color: #000000;
            border: none;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 800;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform 0.15s, background 0.2s;
        }

        .service-card.selected .service-select-btn {
            background: var(--color-gold);
            color: #000000;
        }

        /* -------------------------------------------------------------
           PASO 2: SELECCIÓN DE BARBERO
        ------------------------------------------------------------- */
        /* Tarjeta Destacada "Cualquier barbero disponible" */
        .any-barber-card {
            background: #16161A;
            border: 1.5px solid var(--color-gold);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 20px rgba(192, 160, 98, 0.12);
        }

        .any-barber-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(192, 160, 98, 0.22);
            background: #1C1C22;
        }

        .any-barber-card.selected {
            border-color: var(--color-white);
            background: #202028;
        }

        .any-barber-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .any-barber-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(192, 160, 98, 0.15);
            color: var(--color-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .any-barber-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--color-white);
            margin-bottom: 2px;
        }

        .any-barber-subtitle {
            font-size: 0.78rem;
            color: var(--color-gray);
        }

        .any-barber-arrow {
            color: var(--color-gold);
            font-size: 1rem;
        }

        /* Tarjeta de Barbero Individual */
        .barber-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .barber-card:hover {
            border-color: #383840;
            background: var(--bg-card-hover);
        }

        .barber-card.selected {
            border-color: var(--color-white);
            background: #18181D;
        }

        .barber-avatar {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #28282E;
            background: #1A1A1E;
            flex-shrink: 0;
        }

        .barber-info {
            flex: 1;
            min-width: 0;
        }

        .barber-name {
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--color-white);
        }

        .barber-role {
            font-size: 0.75rem;
            color: var(--color-gray);
            margin-bottom: 2px;
        }

        .barber-rating {
            font-size: 0.72rem;
            color: var(--color-gold);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 2px;
        }

        .barber-specialty {
            font-size: 0.74rem;
            color: #8E8E93;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .barber-choose-btn {
            background: var(--color-white);
            color: #000000;
            border: none;
            padding: 7px 14px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 800;
            cursor: pointer;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .barber-card.selected .barber-choose-btn {
            background: var(--color-gold);
        }

        /* -------------------------------------------------------------
           PASO 3: FECHA Y HORA
        ------------------------------------------------------------- */
        .selected-barber-badge {
            background: #16161A;
            border: 1px solid var(--border-card);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
        }

        .selected-barber-badge .edit-link {
            color: var(--color-gold);
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            font-size: 0.8rem;
        }

        /* Caja de Próxima Disponibilidad */
        .hero-availability-box {
            background: #141417;
            border: 1.5px solid #28282E;
            border-radius: var(--radius-lg);
            padding: 18px 16px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .hero-availability-tag {
            font-size: 0.76rem;
            color: var(--color-gray);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hero-availability-time {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--color-white);
            letter-spacing: -0.02em;
        }

        .hero-availability-sub {
            font-size: 0.78rem;
            color: var(--color-gray);
            margin-bottom: 8px;
        }

        .hero-availability-btn {
            width: 100%;
            max-width: 320px;
            background: var(--color-white);
            color: #000000;
            border: none;
            padding: 12px 20px;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.15s, background 0.2s;
        }

        .hero-availability-btn:hover {
            transform: scale(1.02);
            background: #EDEDED;
        }

        /* Selector de Días Horizontal */
        .days-strip-container {
            width: 100%;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 4px 0;
        }

        .days-strip-container::-webkit-scrollbar {
            display: none;
        }

        .days-strip {
            display: flex;
            gap: 8px;
            min-width: max-content;
        }

        .day-tab {
            width: 62px;
            padding: 10px 4px;
            border-radius: var(--radius-md);
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .day-tab:hover {
            border-color: #383840;
        }

        .day-tab.active {
            background: var(--color-white);
            border-color: var(--color-white);
            color: #000000;
        }

        .day-tab-name {
            font-size: 0.74rem;
            font-weight: 600;
            color: inherit;
        }

        .day-tab-num {
            font-size: 1.05rem;
            font-weight: 800;
            color: inherit;
        }

        /* Bloques de Horas (Mañana / Tarde) */
        .slots-section-title {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--color-white);
            margin: 10px 0 8px 0;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .slot-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-md);
            padding: 12px 6px;
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--color-white);
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .slot-btn:hover {
            border-color: #44444C;
            background: var(--bg-card-hover);
        }

        .slot-btn.selected {
            background: var(--color-white);
            color: #000000;
            border-color: var(--color-white);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.3);
        }

        /* -------------------------------------------------------------
           PASO 4: DATOS DEL CLIENTE Y CONFIRMACIÓN
        ------------------------------------------------------------- */
        .summary-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-lg);
            padding: 14px;
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .summary-thumb {
            width: 72px;
            height: 72px;
            border-radius: var(--radius-md);
            object-fit: cover;
            background: #1A1A1E;
            flex-shrink: 0;
        }

        .summary-details {
            flex: 1;
            font-size: 0.85rem;
            line-height: 1.45;
        }

        .summary-title {
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--color-white);
        }

        .summary-barber {
            color: var(--color-gray);
            font-size: 0.8rem;
            margin-bottom: 4px;
        }

        .summary-datetime {
            color: var(--color-white);
            font-weight: 700;
            font-size: 0.82rem;
        }

        .summary-price {
            color: var(--color-gold);
            font-weight: 800;
            font-size: 0.95rem;
            margin-top: 2px;
        }

        /* Formulario */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #D1D5DB;
        }

        .input-wrapper {
            background: #141417;
            border: 1.5px solid var(--border-card);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            padding: 0 14px;
            transition: border-color 0.2s;
        }

        .input-wrapper:focus-within {
            border-color: var(--color-white);
        }

        .input-icon {
            color: var(--color-gray);
            font-size: 0.95rem;
            margin-right: 10px;
        }

        .input-field {
            width: 100%;
            background: transparent;
            border: none;
            outline: none;
            padding: 13px 0;
            color: var(--color-white);
            font-size: 0.95rem;
            font-family: inherit;
        }

        .input-field::placeholder {
            color: #55555C;
        }

        .phone-prefix {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--color-white);
            margin-right: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-confirm-main {
            width: 100%;
            background: var(--color-white);
            color: #000000;
            border: none;
            padding: 15px;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 900;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 8px;
            transition: all 0.2s ease;
        }

        .btn-confirm-main:hover {
            background: #EAEAEA;
            transform: translateY(-1px);
        }

        .btn-confirm-main:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Trust Badges */
        .trust-badges {
            display: flex;
            justify-content: space-around;
            padding: 16px 0 0 0;
            text-align: center;
            gap: 8px;
        }

        .trust-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            color: #8E8E93;
            max-width: 110px;
        }

        .trust-item i {
            font-size: 1rem;
            color: var(--color-white);
            margin-bottom: 2px;
        }

        /* -------------------------------------------------------------
           PASO 5: CITA CONFIRMADA
        ------------------------------------------------------------- */
        .confirmed-view {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding-top: 10px;
        }

        .success-circle-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(46, 204, 113, 0.12);
            border: 2px solid #2ECC71;
            color: #2ECC71;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 4px;
            box-shadow: 0 0 25px rgba(46, 204, 113, 0.25);
        }

        .confirmed-title {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--color-white);
            letter-spacing: -0.01em;
        }

        .confirmed-sub {
            font-size: 0.92rem;
            color: var(--color-gray);
            max-width: 380px;
        }

        .receipt-card {
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 4px;
        }

        .receipt-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.88rem;
            color: #D1D5DB;
        }

        .receipt-row i {
            color: var(--color-gold);
            width: 18px;
            text-align: center;
            font-size: 0.95rem;
        }

        /* Personalización de Experiencia */
        .experience-box {
            width: 100%;
            background: #141417;
            border: 1px solid var(--border-card);
            border-radius: var(--radius-lg);
            padding: 18px;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .experience-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--color-white);
        }

        .experience-desc {
            font-size: 0.78rem;
            color: var(--color-gray);
            margin-bottom: 4px;
        }

        .experience-item {
            background: #1A1A1F;
            border: 1px solid #282830;
            border-radius: var(--radius-md);
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.84rem;
            color: var(--color-white);
            cursor: pointer;
            transition: background 0.2s;
        }

        .experience-item:hover {
            background: #22222A;
        }

        .experience-item select {
            background: transparent;
            border: none;
            color: var(--color-white);
            font-family: inherit;
            font-size: 0.84rem;
            outline: none;
            width: 100%;
            cursor: pointer;
        }

        .experience-item select option {
            background: #141417;
            color: #FFFFFF;
        }

        /* Botón de Google OAuth (Exclusivo Google) */
        .btn-google-save {
            width: 100%;
            background: #FFFFFF;
            color: #111111;
            border: none;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            font-size: 0.88rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            transition: all 0.2s ease;
        }

        .btn-google-save:hover {
            background: #F1F1F1;
            transform: translateY(-1px);
        }

        .btn-google-save img {
            width: 18px;
            height: 18px;
        }

        .link-skip {
            color: var(--color-gray);
            font-size: 0.82rem;
            text-decoration: underline;
            cursor: pointer;
            margin-top: 4px;
        }

        .link-skip:hover {
            color: var(--color-white);
        }

        /* Toast / Error Banner */
        .toast-error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid #E74C3C;
            color: #FF7675;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            display: none;
            margin-bottom: 12px;
        }
    </style>
</head>

<body>
    <div class="app-container">
        
        <!-- Header Superior con Back y Logo -->
        <header class="app-header">
            <button type="button" class="header-btn" id="btnBack" aria-label="Volver">
                <i class="fas fa-arrow-left"></i>
            </button>
            <a href="/" class="brand-logo">KORT<span>ZEN</span></a>
            <a href="/cliente-login.php" class="header-btn" aria-label="Mi Cuenta">
                <i class="far fa-user-circle"></i>
            </a>
        </header>

        <!-- Indicador de Pasos 1 2 3 4 -->
        <div class="steps-bar" id="stepsBar">
            <div class="step-pill active" data-step="1">1</div>
            <div class="step-pill" data-step="2">2</div>
            <div class="step-pill" data-step="3">3</div>
            <div class="step-pill" data-step="4">4</div>
        </div>

        <div class="toast-error" id="toastError"></div>

        <!-- =========================================================
             PASO 1: ELIGE TU SERVICIO
        ========================================================= -->
        <section class="step-view active" id="step1">
            <div class="step-header">
                <h1 class="step-header__title">1. Elige tu servicio</h1>
                <p class="step-header__subtitle">Todos nuestros servicios incluyen asesoría personalizada.</p>
            </div>

            <div id="servicesList" style="display: flex; flex-direction: column; gap: 12px;">
                <div style="text-align: center; color: var(--color-gray); padding: 40px 0;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px; font-size: 0.85rem;">Cargando catálogo de servicios...</p>
                </div>
            </div>
        </section>

        <!-- =========================================================
             PASO 2: ELIGE TU BARBERO
        ========================================================= -->
        <section class="step-view" id="step2">
            <div class="step-header">
                <h1 class="step-header__title">2. Elige tu barbero</h1>
                <p class="step-header__subtitle">Selecciona con quién quieres atenderte o elige la opción más rápida.</p>
            </div>

            <!-- Botón Destacado: Cualquier barbero disponible -->
            <div class="any-barber-card" id="btnAnyBarber">
                <div class="any-barber-left">
                    <div class="any-barber-icon">⚡</div>
                    <div>
                        <div class="any-barber-title">Cualquier barbero disponible</div>
                        <div class="any-barber-subtitle">Quiero la hora más cercana</div>
                    </div>
                </div>
                <div class="any-barber-arrow"><i class="fas fa-chevron-right"></i></div>
            </div>

            <div id="barbersList" style="display: flex; flex-direction: column; gap: 12px;">
                <div style="text-align: center; color: var(--color-gray); padding: 30px 0;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px; font-size: 0.85rem;">Cargando equipo de barberos...</p>
                </div>
            </div>
        </section>

        <!-- =========================================================
             PASO 3: ELIGE FECHA Y HORA
        ========================================================= -->
        <section class="step-view" id="step3">
            <div class="step-header">
                <h1 class="step-header__title">3. Elige fecha y hora</h1>
                <p class="step-header__subtitle">Selecciona el día y la hora que más te convenga.</p>
            </div>

            <!-- Resumen del Barbero Seleccionado -->
            <div class="selected-barber-badge" id="selectedBarberBadge">
                <div id="selectedBarberText">⚡ Cualquier barbero disponible</div>
                <span class="edit-link" onclick="goToStep(2)">Editar</span>
            </div>

            <!-- Caja de Próxima Disponibilidad (1-Clic) -->
            <div class="hero-availability-box" id="heroAvailabilityBox" style="display: none;">
                <div class="hero-availability-tag"><i class="far fa-clock"></i> Próxima disponibilidad</div>
                <div class="hero-availability-time" id="heroAvailabilityTime">Hoy 10:40</div>
                <div class="hero-availability-sub">La hora más cercana</div>
                <button type="button" class="hero-availability-btn" id="btnQuickBook">
                    <span id="btnQuickBookText">Reservar 10:40</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <!-- Selector de Días Horizontal -->
            <div class="days-strip-container">
                <div class="days-strip" id="daysStrip"></div>
            </div>

            <!-- Slots Mañana -->
            <div id="sectionManana">
                <div class="slots-section-title">Mañana</div>
                <div class="slots-grid" id="slotsGridManana"></div>
            </div>

            <!-- Slots Tarde -->
            <div id="sectionTarde" style="margin-top: 8px;">
                <div class="slots-section-title">Tarde</div>
                <div class="slots-grid" id="slotsGridTarde"></div>
            </div>

            <div id="noSlotsMsg" style="display: none; text-align: center; color: var(--color-gray); padding: 25px 10px; background: var(--bg-card); border-radius: var(--radius-md); font-size: 0.85rem;">
                No hay turnos disponibles para esta fecha. Por favor selecciona otro día.
            </div>
        </section>

        <!-- =========================================================
             PASO 4: CONFIRMA TU CITA (DATOS)
        ========================================================= -->
        <section class="step-view" id="step4">
            <div class="step-header">
                <h1 class="step-header__title">4. Confirma tu cita</h1>
                <p class="step-header__subtitle">Completa tus datos para reservar.</p>
            </div>

            <!-- Mini Resumen Superior -->
            <div class="summary-card">
                <img src="/assets/images/service-corte-mateo.png" alt="Servicio" class="summary-thumb" id="summaryThumb">
                <div class="summary-details">
                    <div class="summary-title" id="summaryServiceName">Corte KORTZEN</div>
                    <div class="summary-barber" id="summaryBarberName">con cualquier barbero</div>
                    <div class="summary-datetime" id="summaryDateTime"><i class="far fa-calendar-alt"></i> Mié 30 de septiembre • 10:40</div>
                    <div class="summary-price" id="summaryPrice">$10.00</div>
                </div>
            </div>

            <!-- Formulario de Datos -->
            <form id="bookingForm" onsubmit="submitBooking(event)" style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" for="clientName">Nombre completo *</label>
                    <div class="input-wrapper">
                        <i class="far fa-user input-icon"></i>
                        <input type="text" id="clientName" class="input-field" placeholder="Ej. Juan Pérez" value="<?php echo htmlspecialchars($clienteNombre); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="clientPhone">WhatsApp *</label>
                    <div class="input-wrapper">
                        <span class="phone-prefix">🇪🇨 +593</span>
                        <input type="tel" id="clientPhone" class="input-field" placeholder="99 123 4567" value="<?php echo htmlspecialchars(preg_replace('/^\+?593/', '', $clienteTelefono)); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="clientEmail">Correo (opcional)</label>
                    <div class="input-wrapper">
                        <i class="far fa-envelope input-icon"></i>
                        <input type="email" id="clientEmail" class="input-field" placeholder="Ej. juan@correo.com" value="<?php echo htmlspecialchars($clienteEmail); ?>">
                    </div>
                </div>

                <button type="submit" class="btn-confirm-main" id="btnSubmit">
                    <span>CONFIRMAR CITA</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <!-- Trust Badges -->
            <div class="trust-badges">
                <div class="trust-item">
                    <i class="fas fa-lock"></i>
                    <span>Sin crear cuenta</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-bolt"></i>
                    <span>Solo toma unos segundos</span>
                </div>
                <div class="trust-item">
                    <i class="fab fa-whatsapp"></i>
                    <span>Recibirás la confirmación por WhatsApp</span>
                </div>
            </div>
        </section>

        <!-- =========================================================
             PASO 5: CITA CONFIRMADA
        ========================================================= -->
        <section class="step-view" id="step5">
            <div class="confirmed-view">
                <div class="success-circle-icon"><i class="fas fa-check"></i></div>
                <h1 class="confirmed-title">¡Tu cita está confirmada!</h1>
                <p class="confirmed-sub">Te hemos enviado los detalles por WhatsApp.</p>

                <!-- Recibo de la Cita -->
                <div class="receipt-card">
                    <div class="receipt-row">
                        <i class="fas fa-cut"></i>
                        <span id="receiptService">Corte KORTZEN • con cualquier barbero</span>
                    </div>
                    <div class="receipt-row">
                        <i class="far fa-calendar-alt"></i>
                        <span id="receiptDate">Miércoles 30 de septiembre</span>
                    </div>
                    <div class="receipt-row">
                        <i class="far fa-clock"></i>
                        <span id="receiptTime">10:40 (40 min)</span>
                    </div>
                    <div class="receipt-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>KORTZEN Barbería • Llano Chico</span>
                    </div>
                </div>

                <!-- Personalización de la Experiencia -->
                <div class="experience-box">
                    <div class="experience-title">¿Quieres personalizar tu experiencia?</div>
                    <div class="experience-desc">Ayúdanos a conocerte mejor (opcional)</div>

                    <!-- Bebida -->
                    <div class="experience-item">
                        <select id="prefBebida" onchange="saveClientPreferences()">
                            <option value="">☕ ¿Qué bebida prefieres?</option>
                            <option value="Agua mineral con gas">Agua mineral con gas</option>
                            <option value="Café espresso premium">Café espresso premium</option>
                            <option value="Cerveza artesanal bien fría">Cerveza artesanal bien fría</option>
                            <option value="Whiskey en las rocas">Whiskey en las rocas</option>
                            <option value="Bebida sin azúcar">Bebida sin azúcar</option>
                            <option value="Ninguna">Ninguna</option>
                        </select>
                    </div>

                    <!-- Conversación -->
                    <div class="experience-item">
                        <select id="prefAmbiente" onchange="saveClientPreferences()">
                            <option value="">💬 ¿Prefieres conversar o un servicio tranquilo?</option>
                            <option value="Conversar y conocer tendencias">Conversar y conocer tendencias</option>
                            <option value="Silencio y relax total">Silencio y relax total</option>
                            <option value="Música suave y desconexión">Música suave y desconexión</option>
                        </select>
                    </div>

                    <!-- Estilo -->
                    <div class="experience-item">
                        <select id="prefEstilo" onchange="saveClientPreferences()">
                            <option value="">✂️ ¿Qué estilo buscas?</option>
                            <option value="Corte clásico formal">Corte clásico formal</option>
                            <option value="Degradado moderno / Skin Fade">Degradado moderno / Skin Fade</option>
                            <option value="Perfilado e hidratación de barba">Perfilado e hidratación de barba</option>
                            <option value="Cambio radical de look y visagismo">Cambio radical de look y visagismo</option>
                        </select>
                    </div>
                </div>

                <!-- Botón de Google OAuth (Exclusivo Google) -->
                <a href="<?php echo htmlspecialchars($googleAuthUrl); ?>" class="btn-google-save" id="btnGoogleSave">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google">
                    <span>Guardar mis datos con Google para reservar más rápido</span>
                </a>

                <a href="/" class="link-skip">Ahora no, continuar</a>
            </div>
        </section>

    </div>

    <!-- =========================================================
         JAVASCRIPT WIZARD LOGIC
    ========================================================= -->
    <script>
        // Estado Global del Wizard
        const bookingState = {
            step: 1,
            servicioId: null,
            servicioNombre: '',
            servicioPrecio: '0.00',
            servicioDuracion: 40,
            servicioThumb: '/assets/images/service-corte-mateo.png',
            barberoId: 0,
            barberoNombre: 'Cualquier barbero disponible',
            barberoAvatar: '',
            fecha: '<?php echo date('Y-m-d'); ?>',
            hora: '',
            proximaDisp: null,
            sucursalId: 1
        };

        let servicesData = [];
        let barbersData = [];

        document.addEventListener('DOMContentLoaded', () => {
            initSteps();
            loadServices();
            loadBarbers();
            buildDaysStrip();

            document.getElementById('btnBack').addEventListener('click', handleBackNavigation);
            document.getElementById('btnAnyBarber').addEventListener('click', selectAnyBarber);
            document.getElementById('btnQuickBook').addEventListener('click', selectQuickSlot);
        });

        // Manejo de Pasos
        function goToStep(stepNumber) {
            bookingState.step = stepNumber;

            // Actualizar vista
            document.querySelectorAll('.step-view').forEach(el => el.classList.remove('active'));
            const targetView = document.getElementById(`step${stepNumber}`);
            if (targetView) targetView.classList.add('active');

            // Actualizar pills
            const stepsBar = document.getElementById('stepsBar');
            if (stepNumber === 5) {
                stepsBar.style.display = 'none';
            } else {
                stepsBar.style.display = 'flex';
                document.querySelectorAll('.step-pill').forEach(pill => {
                    const pStep = parseInt(pill.getAttribute('data-step'));
                    pill.classList.remove('active', 'completed');
                    if (pStep === stepNumber) pill.classList.add('active');
                    else if (pStep < stepNumber) pill.classList.add('completed');
                });
            }

            // Scroll arriba suave
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Cargar slots si entra a paso 3
            if (stepNumber === 3) {
                loadAvailability();
            } else if (stepNumber === 4) {
                updateSummaryCard();
            }
        }

        function handleBackNavigation() {
            if (bookingState.step === 1) {
                window.location.href = '/';
            } else if (bookingState.step <= 4) {
                goToStep(bookingState.step - 1);
            } else {
                window.location.href = '/';
            }
        }

        function initSteps() {
            goToStep(1);
        }

        // -------------------------------------------------------------
        // PASO 1: CARGAR SERVICIOS
        // -------------------------------------------------------------
        async function loadServices() {
            const container = document.getElementById('servicesList');
            try {
                const res = await fetch('/api/get_servicios_public.php');
                const json = await res.json();

                if (json.success && json.data && json.data.length > 0) {
                    servicesData = json.data;
                    renderServices(servicesData);
                } else {
                    container.innerHTML = '<p style="text-align:center;color:#999;padding:20px;">No hay servicios disponibles.</p>';
                }
            } catch (err) {
                console.error(err);
                container.innerHTML = '<p style="text-align:center;color:#ff6b6b;padding:20px;">Error al cargar servicios.</p>';
            }
        }

        function renderServices(services) {
            const container = document.getElementById('servicesList');
            container.innerHTML = '';

            services.forEach(s => {
                const card = document.createElement('div');
                card.className = `service-card ${bookingState.servicioId == s.id ? 'selected' : ''}`;
                
                const thumb = s.foto_url || s.imagen_url || '/assets/images/service-corte-mateo.png';
                const desc = s.que_incluye ? s.que_incluye.replace(/\r?\n/g, ' • ') : (s.descripcion || 'Asesoría y corte personalizado');

                card.innerHTML = `
                    <img src="${thumb}" alt="${s.nombre}" class="service-thumb" onerror="this.src='/assets/images/service-corte-mateo.png'">
                    <div class="service-info">
                        <div class="service-name">${s.nombre}</div>
                        <div class="service-meta">$${parseFloat(s.precio).toFixed(0)} • ${s.duracion_minutos} min</div>
                        <div class="service-desc">${desc}</div>
                    </div>
                    <button type="button" class="service-select-btn">Seleccionar</button>
                `;

                card.addEventListener('click', () => {
                    selectService(s);
                });

                container.appendChild(card);
            });
        }

        function selectService(s) {
            bookingState.servicioId = s.id;
            bookingState.servicioNombre = s.nombre;
            bookingState.servicioPrecio = parseFloat(s.precio).toFixed(2);
            bookingState.servicioDuracion = parseInt(s.duracion_minutos) || 40;
            bookingState.servicioThumb = s.foto_url || s.imagen_url || '/assets/images/service-corte-mateo.png';

            // Si es exclusivo (ej. Corte con Mateo)
            if (s.barbero_id && parseInt(s.barbero_id) > 0) {
                bookingState.barberoId = parseInt(s.barbero_id);
            }

            // Marcar en UI
            document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');

            // Avanzar a paso 2
            setTimeout(() => goToStep(2), 150);
        }

        // -------------------------------------------------------------
        // PASO 2: CARGAR BARBEROS
        // -------------------------------------------------------------
        async function loadBarbers() {
            const container = document.getElementById('barbersList');
            try {
                const res = await fetch('/api/get_barberos.php');
                const json = await res.json();

                if (json.success && json.data && json.data.length > 0) {
                    barbersData = json.data;
                    renderBarbers(barbersData);
                } else {
                    container.innerHTML = '<p style="text-align:center;color:#999;padding:20px;">No hay barberos registrados.</p>';
                }
            } catch (err) {
                console.error(err);
                container.innerHTML = '<p style="text-align:center;color:#ff6b6b;padding:20px;">Error al cargar barberos.</p>';
            }
        }

        function renderBarbers(barbers) {
            const container = document.getElementById('barbersList');
            container.innerHTML = '';

            barbers.forEach(b => {
                const card = document.createElement('div');
                card.className = `barber-card ${bookingState.barberoId == b.id ? 'selected' : ''}`;

                const primerNombre = b.nombre.split(' ')[0];
                const avatar = b.foto || b.foto_url || '/assets/images/barber-mateo.jpg';
                const rating = '★★★★★ 5.0';
                const spec = b.especialidades || 'Especialidad en degradados, visagismo y barba.';

                card.innerHTML = `
                    <img src="${avatar}" alt="${b.nombre}" class="barber-avatar" onerror="this.src='/assets/images/barber-mateo.jpg'">
                    <div class="barber-info">
                        <div class="barber-name">${b.nombre}</div>
                        <div class="barber-role">${b.cargo || 'Barbero Profesional'}</div>
                        <div class="barber-rating">${rating}</div>
                        <div class="barber-specialty">${spec}</div>
                    </div>
                    <button type="button" class="barber-choose-btn">Elegir a ${primerNombre}</button>
                `;

                card.addEventListener('click', () => {
                    selectBarber(b);
                });

                container.appendChild(card);
            });
        }

        function selectAnyBarber() {
            bookingState.barberoId = 0;
            bookingState.barberoNombre = 'Cualquier barbero disponible';
            bookingState.barberoAvatar = '';

            document.querySelectorAll('.barber-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('btnAnyBarber').classList.add('selected');

            setTimeout(() => goToStep(3), 150);
        }

        function selectBarber(b) {
            bookingState.barberoId = b.id;
            bookingState.barberoNombre = b.nombre;
            bookingState.barberoAvatar = b.foto || b.foto_url || '';

            document.getElementById('btnAnyBarber').classList.remove('selected');
            document.querySelectorAll('.barber-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');

            setTimeout(() => goToStep(3), 150);
        }

        // -------------------------------------------------------------
        // PASO 3: CALENDARIO Y SLOTS DE HORA
        // -------------------------------------------------------------
        function buildDaysStrip() {
            const container = document.getElementById('daysStrip');
            container.innerHTML = '';

            const diasNombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            const today = new Date();

            for (let i = 0; i < 14; i++) {
                const d = new Date();
                d.setDate(today.getDate() + i);

                const yyyy = d.getFullYear();
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                const isoDate = `${yyyy}-${mm}-${dd}`;

                const diaSem = (i === 0) ? 'Hoy' : diasNombres[d.getDay()];
                const diaNum = d.getDate();

                const tab = document.createElement('div');
                tab.className = `day-tab ${isoDate === bookingState.fecha ? 'active' : ''}`;
                tab.setAttribute('data-date', isoDate);

                tab.innerHTML = `
                    <div class="day-tab-name">${diaSem}</div>
                    <div class="day-tab-num">${diaNum}</div>
                `;

                tab.addEventListener('click', () => {
                    document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    bookingState.fecha = isoDate;
                    loadAvailability();
                });

                container.appendChild(tab);
            }
        }

        async function loadAvailability() {
            // Actualizar badge barbero
            const bBadgeText = document.getElementById('selectedBarberText');
            if (bookingState.barberoId === 0) {
                bBadgeText.innerHTML = '⚡ <strong>Cualquier barbero disponible</strong>';
            } else {
                bBadgeText.innerHTML = `👤 <strong>${bookingState.barberoNombre}</strong>`;
            }

            const gridM = document.getElementById('slotsGridManana');
            const gridT = document.getElementById('slotsGridTarde');
            const noMsg = document.getElementById('noSlotsMsg');
            const heroBox = document.getElementById('heroAvailabilityBox');

            gridM.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#777;padding:15px;"><i class="fas fa-spinner fa-spin"></i> Cargando horarios...</div>';
            gridT.innerHTML = '';
            noMsg.style.display = 'none';

            try {
                const url = `/api/get_disponibilidad.php?fecha=${bookingState.fecha}&barbero_id=${bookingState.barberoId}&servicio_id=${bookingState.servicioId || 1}`;
                const res = await fetch(url);
                const json = await res.json();

                gridM.innerHTML = '';
                gridT.innerHTML = '';

                if (json.success) {
                    // Mostrar hero próxima disponibilidad si existe
                    if (json.proxima_disponibilidad) {
                        bookingState.proximaDisp = json.proxima_disponibilidad;
                        document.getElementById('heroAvailabilityTime').innerText = json.proxima_disponibilidad.label;
                        document.getElementById('btnQuickBookText').innerText = `Reservar ${json.proxima_disponibilidad.hora}`;
                        heroBox.style.display = 'flex';
                    } else {
                        heroBox.style.display = 'none';
                    }

                    // Renderizar Mañana
                    if (json.slots_manana && json.slots_manana.length > 0) {
                        document.getElementById('sectionManana').style.display = 'block';
                        json.slots_manana.forEach(slot => {
                            gridM.appendChild(createSlotButton(slot));
                        });
                    } else {
                        document.getElementById('sectionManana').style.display = 'none';
                    }

                    // Renderizar Tarde
                    if (json.slots_tarde && json.slots_tarde.length > 0) {
                        document.getElementById('sectionTarde').style.display = 'block';
                        json.slots_tarde.forEach(slot => {
                            gridT.appendChild(createSlotButton(slot));
                        });
                    } else {
                        document.getElementById('sectionTarde').style.display = 'none';
                    }

                    if ((!json.slots_manana || json.slots_manana.length === 0) && (!json.slots_tarde || json.slots_tarde.length === 0)) {
                        noMsg.style.display = 'block';
                    }
                } else {
                    noMsg.style.display = 'block';
                    noMsg.innerText = json.error || 'No hay horarios disponibles.';
                }
            } catch (err) {
                console.error(err);
                gridM.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#ff6b6b;padding:15px;">Error al consultar horarios.</div>';
            }
        }

        function createSlotButton(slotTime) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `slot-btn ${bookingState.hora === slotTime ? 'selected' : ''}`;
            btn.innerText = slotTime;

            btn.addEventListener('click', () => {
                document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                bookingState.hora = slotTime;
                setTimeout(() => goToStep(4), 150);
            });

            return btn;
        }

        function selectQuickSlot() {
            if (bookingState.proximaDisp) {
                bookingState.fecha = bookingState.proximaDisp.fecha;
                bookingState.hora = bookingState.proximaDisp.hora;
                if (bookingState.barberoId === 0 && bookingState.proximaDisp.barbero_id) {
                    bookingState.barberoNombre = bookingState.proximaDisp.barbero_nombre;
                }
                goToStep(4);
            }
        }

        // -------------------------------------------------------------
        // PASO 4: RESUMEN Y ENVÍO DE RESERVA
        // -------------------------------------------------------------
        function updateSummaryCard() {
            document.getElementById('summaryThumb').src = bookingState.servicioThumb;
            document.getElementById('summaryServiceName').innerText = bookingState.servicioNombre || 'Corte KORTZEN';
            document.getElementById('summaryBarberName').innerText = (bookingState.barberoId === 0) ? 'con cualquier barbero' : `con ${bookingState.barberoNombre}`;
            document.getElementById('summaryPrice').innerText = `$${bookingState.servicioPrecio}`;

            // Formatear fecha legible
            const [y, m, d] = bookingState.fecha.split('-');
            const dateObj = new Date(parseInt(y), parseInt(m) - 1, parseInt(d));
            const diasNombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            const mesesNombres = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            const fStr = `${diasNombres[dateObj.getDay()]} ${dateObj.getDate()} de ${mesesNombres[dateObj.getMonth()]}`;

            document.getElementById('summaryDateTime').innerHTML = `<i class="far fa-calendar-alt"></i> ${fStr} • ${bookingState.hora} (${bookingState.servicioDuracion} min)`;
        }

        async function submitBooking(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btnSubmit');
            const toast = document.getElementById('toastError');
            toast.style.display = 'none';

            const name = document.getElementById('clientName').value.trim();
            let phone = document.getElementById('clientPhone').value.trim().replace(/\s+/g, '');
            const email = document.getElementById('clientEmail').value.trim();

            if (!name) {
                showToast('Por favor ingresa tu nombre completo.');
                return;
            }
            if (!phone || phone.length < 8) {
                showToast('Por favor ingresa un número de WhatsApp válido.');
                return;
            }

            // Normalizar teléfono con formato +593
            if (!phone.startsWith('+593') && !phone.startsWith('593')) {
                phone = '+593' + phone.replace(/^0+/, '');
            }

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirmando tu cita...';

            try {
                const formData = new FormData();
                formData.append('servicio_id', bookingState.servicioId);
                formData.append('barbero_id', bookingState.barberoId);
                formData.append('fecha', bookingState.fecha);
                formData.append('hora', bookingState.hora);
                formData.append('nombre', name);
                formData.append('telefono', phone);
                formData.append('email', email);
                formData.append('sucursal_id', bookingState.sucursalId);

                const res = await fetch('/api/crear_cita_cliente.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();

                if (json.success) {
                    // Actualizar pantalla 5 (Recibo de confirmación)
                    document.getElementById('receiptService').innerText = `${json.servicio_nombre} • con ${json.barbero_nombre}`;
                    document.getElementById('receiptDate').innerText = json.fecha_legible;
                    document.getElementById('receiptTime').innerText = `${json.hora} (${json.duracion} min)`;

                    if (json.google_auth_url) {
                        document.getElementById('btnGoogleSave').href = json.google_auth_url;
                    }

                    goToStep(5);
                } else {
                    showToast(json.message || 'No se pudo completar la reserva.');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<span>CONFIRMAR CITA</span> <i class="fas fa-arrow-right"></i>';
                }
            } catch (err) {
                console.error(err);
                showToast('Error de conexión al procesar la cita.');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<span>CONFIRMAR CITA</span> <i class="fas fa-arrow-right"></i>';
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('toastError');
            toast.innerText = msg;
            toast.style.display = 'block';
            toast.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // -------------------------------------------------------------
        // PASO 5: GUARDAR PREFERENCIAS DE EXPERIENCIA
        // -------------------------------------------------------------
        async function saveClientPreferences() {
            const bebida = document.getElementById('prefBebida').value;
            const ambiente = document.getElementById('prefAmbiente').value;
            const estilo = document.getElementById('prefEstilo').value;

            try {
                const formData = new FormData();
                formData.append('bebida_preferida', bebida);
                formData.append('ambiente_preferido', ambiente);
                formData.append('estilo_buscado', estilo);

                await fetch('/api/guardar_preferencias_cliente.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (err) {
                console.error('Error guardando preferencias:', err);
            }
        }
    </script>
</body>

</html>
