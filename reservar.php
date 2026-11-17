<?php
require_once 'config.php';
require_once __DIR__ . '/api/auth/google-config.php';
enforceRateLimit('reservar_page', 40, 60);

// Identificar si el cliente ya inició sesión o está reservando como invitado
$isLoggedIn = isClienteLoggedIn();
$cliente = $isLoggedIn ? getCurrentCliente() : null;
$pageTitle = 'Reservar Cita';

// Cargar configuraciones del sistema (incluyendo política de reservas)
$systemConfigs = getSystemConfigs();
$politicaTitulo = $systemConfigs['politica_reserva_titulo'] ?? 'POLÍTICA DE RESERVAS';
$politicaTexto = $systemConfigs['politica_reserva_texto'] ?? "• Si no puede llegar a su cita, informar con al menos 1 hora de anticipación.\n• Si llega 10 minutos tarde, pierde el servicio de toalla caliente y limpieza facial.\n• Pasados los 15 minutos de retraso, la cita podrá ser reprogramada para no afectar los turnos siguientes.\n• Cuidamos tu tiempo y el de los demás caballeros.";
$politicaCheckTexto = $systemConfigs['politica_reserva_check_texto'] ?? 'He leído y acepto la política de reserva y condiciones de puntualidad.';
$politicaRequiereCheck = ($systemConfigs['politica_reserva_requiere_check'] ?? '1') === '1';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reservar Cita - KORTZEN</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="/css/pwa-native.css?v=53">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js?v=26200" defer></script>
    <script src="/js/branch-selector.js?v=26000"></script>
    <style>
        /* ANTI-ZOOM MOBILE RULE */
        input, select, textarea, .flatpickr-input {
            font-size: 16px !important;
            touch-action: manipulation;
        }

        /* FLATPICKR LUXURY CLEAN LIGHT MONOCHROME THEME */
        .flatpickr-calendar {
            background: #FFFFFF !important;
            border: 1px solid #EAEAEA !important;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.08) !important;
            border-radius: 16px !important;
            padding: 12px 10px !important;
            width: 335px !important;
            max-width: 95vw !important;
            box-sizing: border-box !important;
        }

        .flatpickr-innerContainer,
        .flatpickr-rContainer,
        .flatpickr-days,
        .dayContainer {
            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            justify-content: space-around !important;
        }

        .flatpickr-months {
            background: #FFFFFF !important;
            border-bottom: 1px solid #EAEAEA !important;
            width: 100% !important;
        }
        .flatpickr-months .flatpickr-month {
            background: #FFFFFF !important;
            color: #111111 !important;
            font-weight: 800 !important;
            fill: #111111 !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month input.cur-year {
            background: #FFFFFF !important;
            color: #111111 !important;
            font-weight: 800 !important;
            font-size: 1.1rem !important;
            border: none !important;
            padding: 2px 6px !important;
            border-radius: 6px !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month {
            background: #FFFFFF !important;
            color: #111111 !important;
        }

        .flatpickr-weekdays {
            background: #FFFFFF !important;
            width: 100% !important;
            display: flex !important;
            justify-content: space-around !important;
        }
        span.flatpickr-weekday {
            background: #FFFFFF !important;
            color: #777777 !important;
            font-weight: 800 !important;
            font-size: 0.82rem !important;
            width: 14.28% !important;
            max-width: 14.28% !important;
            flex: 1 0 14.28% !important;
            text-align: center !important;
        }

        .flatpickr-day {
            max-width: 38px !important;
            height: 38px !important;
            line-height: 36px !important;
            flex-basis: 38px !important;
            margin: 2px 1px !important;
            box-sizing: border-box !important;
        }

        /* 1. DIAS DISPONIBLES / SELECCIONABLES */
        .flatpickr-day:not(.disabled):not(.prevMonthDay):not(.nextMonthDay) {
            color: #111111 !important;
            font-weight: 700 !important;
            background: #F7F7F7 !important;
            border: 1px solid #EAEAEA !important;
            border-radius: 50% !important;
            transition: all 0.2s ease !important;
        }
        .flatpickr-day:not(.disabled):not(.prevMonthDay):not(.nextMonthDay):hover {
            background: #111111 !important;
            color: #FFFFFF !important;
            border-color: #111111 !important;
            transform: scale(1.1) !important;
        }

        /* 2. DIA SELECCIONADO */
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.selected:focus, .flatpickr-day.selected:hover {
            background: #111111 !important;
            border: 2px solid #111111 !important;
            color: #FFFFFF !important;
            font-weight: 800 !important;
            border-radius: 50% !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25) !important;
            transform: scale(1.1) !important;
        }

        /* 3. DIAS NO DISPONIBLES / PASADOS */
        .flatpickr-day.disabled, .flatpickr-day.disabled:hover, .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay, .flatpickr-day.flatpickr-disabled {
            color: #CCCCCC !important;
            opacity: 0.4 !important;
            text-decoration: line-through !important;
            background: transparent !important;
            border: 1px solid transparent !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
        }

        .flatpickr-day.today {
            border: 2px solid #111111 !important;
        }
        .flatpickr-months .flatpickr-prev-month, .flatpickr-months .flatpickr-next-month {
            color: #111111 !important;
            fill: #111111 !important;
        }

        :root {
            --gold: #111111;
            --accent-gray: #777777;
            --dark-bg: #FAF9F6;
            --card-bg: #FFFFFF;
            --text-primary: #1A1A1A;
            --text-secondary: #777777;
        }

        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        /* Container & Header */
        .booking-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 20px 16px;
            min-height: auto;
            padding-bottom: 90px;
        }

        .booking-header {
            text-align: center;
            margin-bottom: 20px;
            margin-top: 6px;
        }

        .booking-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.85rem;
            color: #111111;
            margin-bottom: 6px;
            letter-spacing: 0.04em;
            font-weight: 700;
            text-transform: none;
        }

        /* Wizard Steps Progress */
        .steps-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            position: relative;
            padding: 0 10px;
        }

        .steps-progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 20px;
            right: 20px;
            transform: translateY(-50%);
            height: 2px;
            background: #EAEAEA;
            z-index: 1;
        }

        .step-dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #FFFFFF;
            color: #999999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            position: relative;
            z-index: 5;
            border: 2px solid #EAEAEA;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-sizing: border-box;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .step-dot.active {
            background: #111111 !important;
            color: #FFFFFF !important;
            border: 3px solid #111111 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
            transform: scale(1.15);
        }

        .step-dot.completed {
            background: #111111 !important;
            color: #FFFFFF !important;
            border: 2px solid #111111 !important;
            font-weight: 800;
        }

        /* Wizard Sections */
        .wizard-step {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .wizard-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        /* Services Compact Luxury List (Setmore-Inspired Elite UX) */
        .services-list-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .services-category-block {
            width: 100%;
        }

        .services-category-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 16px 2px 8px 2px;
            font-size: 0.8rem;
            font-weight: 800;
            color: #111111;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .services-category-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #EAEAEA;
        }

        .services-card-group {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .service-list-row {
            display: flex;
            align-items: center;
            padding: 13px 15px;
            gap: 14px;
            cursor: pointer;
            border-bottom: 1px solid #F0F0F0;
            transition: background 0.18s ease, border-color 0.18s ease;
            position: relative;
            background: transparent;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        .service-list-row:last-child {
            border-bottom: none;
        }

        .service-list-row:hover {
            background: #F9FAFB;
        }

        .service-list-row:active {
            background: #F3F4F6;
        }

        .service-list-row.selected {
            background: #F8F9FA !important;
            border-left: 4px solid #111111 !important;
        }

        .service-list-row.selected .service-chevron-icon {
            color: #111111;
            transform: translateX(2px);
        }

        .service-row-thumb {
            width: 52px;
            height: 52px;
            min-width: 52px;
            border-radius: 10px;
            object-fit: cover;
            background: #F3F4F6;
            border: 1px solid #EAEAEA;
            flex-shrink: 0;
            display: block;
        }

        .service-row-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 3px;
        }

        .service-row-title-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .service-row-title {
            font-size: 0.94rem;
            font-weight: 700;
            color: #111111;
            text-transform: none;
            letter-spacing: 0;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .service-exclusive-pill {
            font-size: 0.66rem;
            font-weight: 700;
            background: #F3F4F6;
            color: #111111;
            border: 1px solid #E5E7EB;
            padding: 1px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: inline-block;
            vertical-align: middle;
        }

        .service-row-meta {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.84rem;
            color: #6B7280;
            line-height: 1.3;
        }

        .service-row-meta .duration {
            color: #6B7280;
            font-weight: 500;
        }

        .service-row-meta .meta-dot {
            color: #D1D5DB;
            font-size: 0.8rem;
        }

        .service-row-meta .details-btn {
            background: none;
            border: none;
            padding: 0;
            margin: 0;
            color: #4B5563;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
            font-size: 0.84rem;
            cursor: pointer;
            transition: color 0.15s ease;
            font-family: inherit;
        }

        .service-row-meta .details-btn:hover,
        .service-row-meta .details-btn:active {
            color: #111111;
        }

        .service-row-meta .price-val {
            color: #111111;
            font-weight: 800;
            font-size: 0.92rem;
            margin: 0;
            display: inline;
        }

        .service-chevron-icon {
            color: #9CA3AF;
            flex-shrink: 0;
            transition: transform 0.2s ease, color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .service-list-row:hover .service-chevron-icon {
            color: #111111;
            transform: translateX(2px);
        }

        /* Service Details Modal / Bottom Sheet */
        .service-details-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 10000;
            display: none;
            align-items: flex-end;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .service-details-backdrop.open {
            display: flex;
            opacity: 1;
        }

        .service-details-drawer {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 480px;
            max-height: 85vh;
            overflow-y: auto;
            padding: 22px 20px 30px 20px;
            box-sizing: border-box;
            position: relative;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.15);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            color: #111111;
        }

        .service-details-backdrop.open .service-details-drawer {
            transform: translateY(0);
        }

        @media (min-width: 600px) {
            .service-details-backdrop {
                align-items: center;
            }
            .service-details-drawer {
                border-radius: 20px;
                max-height: 85vh;
                transform: scale(0.95);
                transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .service-details-backdrop.open .service-details-drawer {
                transform: scale(1);
            }
        }

        .service-details-drawer-handle {
            width: 40px;
            height: 4px;
            border-radius: 2px;
            background: #E5E7EB;
            margin: 0 auto 16px auto;
        }

        @media (min-width: 600px) {
            .service-details-drawer-handle {
                display: none;
            }
        }

        .service-details-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #F3F4F6;
            border: 1px solid #E5E7EB;
            color: #111111;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            line-height: 1;
        }

        .service-details-close-btn:hover {
            background: #E5E7EB;
            transform: scale(1.05);
        }

        /* Cards Grid */
        .grid-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        /* Service & Barber Card */
        .option-card {
            background: var(--card-bg);
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 18px 16px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: center;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .option-card:hover {
            border-color: #111111;
            background: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
        }

        .option-card.selected {
            background: #FFFFFF !important;
            border: 2.5px solid #111111 !important;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1) !important;
            transform: translateY(-2px);
        }

        .option-card.selected h3,
        .option-card.selected p,
        .option-card.selected .price {
            color: #111111 !important;
            font-weight: 800 !important;
        }

        .option-card h3 {
            margin: 0 0 4px 0;
            font-size: 1.05rem;
            color: #111111;
            font-weight: 700;
        }

        .option-card p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .price {
            display: block;
            margin-top: 10px;
            font-weight: 800;
            color: #111111;
            font-size: 1.15rem;
        }

        /* Any Barber Card */
        .any-barber-card {
            grid-column: 1 / -1;
            background: #FFFFFF;
            border: 1.5px dashed #D1D5DB;
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-bottom: 8px;
            text-align: left;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
        }

        .any-barber-card:hover {
            border-color: #111111;
            background: #F9FAFB;
            transform: scale(1.01);
        }

        .any-barber-card.selected {
            border: 2.5px solid #111111 !important;
            background: #FFFFFF !important;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }

        /* Barber Avatar */
        .barber-avatar {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #F3F4F6;
            margin: 0 auto 12px;
            background-size: cover;
            background-position: center;
            border: 2px solid #EAEAEA;
        }

        /* Proxima Disponibilidad Banner */
        .quick-slot-banner {
            background: #FFFFFF;
            border: 1.5px solid #EAEAEA;
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        /* Date & Time */
        .datetime-wrapper {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
            gap: 10px;
            max-height: 360px;
            overflow-y: auto;
            padding: 4px 2px;
        }

        .time-slot {
            padding: 12px 8px;
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            font-size: 0.92rem;
            font-weight: 700;
            color: #111111;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .time-slot:hover:not(.disabled) {
            border-color: #111111;
            background: #F9FAFB;
        }

        .time-slot.selected {
            background: #111111 !important;
            color: #FFFFFF !important;
            border: 2px solid #111111 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            font-weight: 800 !important;
        }

        .time-slot.disabled {
            opacity: 0.35;
            cursor: not-allowed;
            background: #F3F4F6;
            color: #9CA3AF;
            border-color: #E5E7EB;
        }

        /* Navigation Buttons */
        .wizard-nav {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 24px auto 0 auto;
            max-width: 480px;
            width: 100%;
            padding-top: 16px;
            padding-bottom: 90px;
            border-top: 1px solid #EAEAEA;
            box-sizing: border-box;
        }

        .btn {
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            transition: all 0.2s ease;
            border: none;
            flex: 1;
            text-align: center;
        }

        .btn-prev {
            background: #FFFFFF;
            color: #111111;
            border: 1px solid #EAEAEA;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .btn-prev:hover, .btn-prev:active {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        .btn-next {
            background: #111111;
            color: #FFFFFF;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .btn-next:disabled {
            background: #E5E7EB;
            color: #9CA3AF;
            cursor: not-allowed;
            box-shadow: none;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>

<body>

    <div class="booking-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>
        
        <div class="booking-header">
            <h1 class="booking-title">Tu Cita</h1>
            <p style="color: var(--text-secondary); margin: 0; font-size: 0.95rem;">
                <?php if ($isLoggedIn && !empty($cliente['nombre'])): ?>
                    Hola, <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong>. Vamos a agendar tu corte de lujo.
                <?php else: ?>
                    Agenda tu servicio en menos de 1 minuto sin registro previo.
                <?php endif; ?>
            </p>
        </div>

        <!-- Selector de Sucursal Activa -->
        <div onclick="window.createBranchSelectorModal ? window.createBranchSelectorModal() : (window.KortzenBranches && window.KortzenBranches.showSelector())" style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 12px; padding: 10px 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.1rem;">📍</span>
                <div>
                    <div style="font-size: 0.68rem; font-weight: 800; color: #777777; text-transform: uppercase; letter-spacing: 0.5px;">SUCURSAL SELECCIONADA</div>
                    <div style="font-size: 0.92rem; font-weight: 800; color: #111111;" data-branch-dynamic="name">KORTZEN Llano Chico</div>
                </div>
            </div>
            <span style="background: #111111; color: #FFFFFF; font-size: 0.72rem; font-weight: 800; padding: 5px 12px; border-radius: 6px; text-transform: uppercase;">
                Cambiar
            </span>
        </div>

        <!-- Progress 5 Steps -->
        <div class="steps-progress">
            <div class="step-dot active" data-step="1">1</div>
            <div class="step-dot" data-step="2">2</div>
            <div class="step-dot" data-step="3">3</div>
            <div class="step-dot" data-step="4">4</div>
            <div class="step-dot" data-step="5">5</div>
        </div>

        <!-- Step 1: Services -->
        <div class="wizard-step active" id="step1">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #111111;">1. Elige tu Servicio</h2>
            <div class="services-list-container" id="servicesGrid">
                <p style="color:#888; text-align:center; padding: 25px 0;">Cargando servicios...</p>
            </div>
        </div>

        <!-- Step 2: Barbers -->
        <div class="wizard-step" id="step2">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #111111;">2. Elige tu Barbero</h2>
            
            <!-- Opción: Cualquier Barbero Disponible -->
            <div id="anyBarberOption" class="any-barber-card" onclick="selectAnyBarber(this)">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 900; flex-shrink: 0;">
                        ⚡
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 0.95rem; color: #111111;">Cualquier barbero disponible</div>
                        <div style="font-size: 0.8rem; color: #777777;">Quiero la hora más cercana / Mayor disponibilidad</div>
                    </div>
                </div>
                <div style="font-size: 0.8rem; font-weight: 800; color: #111111; background: #F3F4F6; padding: 6px 12px; border-radius: 8px;">
                    Elegir ➔
                </div>
            </div>

            <div class="grid-options" id="barbersGrid">
                <!-- Cargado vía JS -->
            </div>
        </div>

        <!-- Step 3: Date & Time -->
        <div class="wizard-step" id="step3">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #111111;">3. Elige Fecha y Hora</h2>

            <div class="datetime-wrapper">
                <!-- Selector de Día -->
                <div style="background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 16px; padding: 18px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);">
                    <div style="margin-bottom:12px;">
                        <label style="display:flex; align-items:center; gap:8px; color:#111111; font-weight:800; font-size:1.02rem; margin:0;">
                            <i class="fas fa-calendar-alt" style="color:#111111; font-size:1.15rem;"></i> Selecciona el día de tu cita
                        </label>
                    </div>
                    <div style="position: relative; width: 100%;">
                        <i class="fas fa-calendar-day" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #777777; font-size: 1.15rem; pointer-events: none; z-index: 2;"></i>
                        <input type="text" id="datePicker" placeholder="Haz clic aquí para abrir el calendario..." readonly
                            style="width: 100%; padding: 16px 40px 16px 48px; background: #FAF9F6; border: 1px solid #EAEAEA; color: #111111; font-weight: 700; font-size: 16px !important; border-radius: 12px; cursor: pointer; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02); transition: all 0.3s ease; box-sizing: border-box;">
                        <i class="fas fa-chevron-down" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #777777; font-size: 1rem; pointer-events: none; z-index: 2;"></i>
                    </div>
                </div>

                <!-- Banner Destacado: Próxima Hora Más Cercana Disponible -->
                <div id="quickSlotBanner" class="quick-slot-banner" style="background: #FFFFFF; border: 1.5px solid #EAEAEA; border-radius: 16px; padding: 16px 18px; display: none; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 44px; height: 44px; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 900; flex-shrink: 0;">
                            ⚡
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; font-weight: 800; color: #777777; text-transform: uppercase; letter-spacing: 0.5px;">HORA MÁS CERCANA DISPONIBLE</div>
                            <div id="quickSlotLabel" style="font-size: 1.02rem; font-weight: 900; color: #111111; margin-top: 2px;">Hoy a las 10:00</div>
                        </div>
                    </div>
                    <button type="button" id="btnQuickBook" onclick="bookQuickSlot()" style="background: #111111; color: #FFFFFF; border: none; border-radius: 10px; padding: 10px 16px; font-size: 0.85rem; font-weight: 800; cursor: pointer; text-transform: uppercase; white-space: nowrap; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);">
                        Reservar ➔
                    </button>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <label style="color:var(--text-secondary); font-size: 0.9rem; font-weight: 700; margin:0;">Horarios Disponibles</label>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="slot-filter-btn active" onclick="filtrarHorarios('all', this)" style="padding:6px 14px; border-radius:20px; border:1px solid #111111; background:#111111; color:#FFFFFF; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                TODOS
                            </button>
                            <button type="button" class="slot-filter-btn" onclick="filtrarHorarios('manana', this)" style="padding:6px 14px; border-radius:20px; border:1px solid #EAEAEA; background:#FFFFFF; color:#666; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                MAÑANA
                            </button>
                            <button type="button" class="slot-filter-btn" onclick="filtrarHorarios('tarde', this)" style="padding:6px 14px; border-radius:20px; border:1px solid #EAEAEA; background:#FFFFFF; color:#666; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                TARDE
                            </button>
                        </div>
                    </div>
                    <div id="slotsGrid" class="slots-grid">
                        <p style="color:#777; grid-column: 1/-1; text-align:center; padding: 20px 0;">Selecciona una fecha arriba para ver los turnos disponibles</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Contact & Personal Details -->
        <div class="wizard-step" id="step4">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #111111;">4. Identificación y Contacto</h2>
            
            <?php if (!$isLoggedIn): ?>
            <!-- Bloque elegante de inicio de sesión obligatorio con Google -->
            <div style="background:#FFFFFF; padding:28px 22px; border-radius:18px; border:1px solid #EAEAEA; max-width: 480px; margin: 0 auto; box-sizing: border-box; box-shadow: 0 6px 25px rgba(0,0,0,0.03); text-align: center;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #FAF9F6; border: 1px solid #EAEAEA; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                    <svg width="26" height="26" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                </div>

                <h3 style="margin: 0 0 10px 0; font-size: 1.25rem; font-weight: 800; color: #111111; letter-spacing: -0.02em;">Inicia sesión con Google</h3>
                
                <p style="color: #555555; margin: 0 auto 20px auto; font-size: 0.92rem; line-height: 1.55; max-width: 400px;">
                    Para mejorar la gestión en el sistema y brindarte un mejor servicio, inicia sesión con Gmail.
                </p>

                <div style="background: #FAF9F6; border: 1px solid #EEEEEE; border-radius: 12px; padding: 14px 16px; margin-bottom: 22px; text-align: left;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 0.84rem; color: #333333; font-weight: 600;">
                        <span style="color: #111111; font-weight: 800;">✓</span>
                        <span>Confirmación instantánea enviada a tu Gmail</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 0.84rem; color: #333333; font-weight: 600;">
                        <span style="color: #111111; font-weight: 800;">✓</span>
                        <span>Consulta y administra tus citas desde tu perfil PWA</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.84rem; color: #333333; font-weight: 600;">
                        <span style="color: #111111; font-weight: 800;">✓</span>
                        <span>Recordatorios automáticos para asegurar tu turno</span>
                    </div>
                </div>

                <button type="button" onclick="loginWithGoogleFromStep4()" id="btnGoogleLoginStep4" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 12px; background: #111111; color: #FFFFFF; border: none; padding: 15px 22px; border-radius: 12px; font-size: 0.96rem; font-weight: 800; cursor: pointer; box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12); transition: all 0.2s ease;">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Continuar con Google</span>
                </button>

                <p style="color: #888888; font-size: 0.78rem; margin: 14px 0 0 0;">
                    🔒 Rápido y seguro en 1 clic. Tu cita se mantendrá guardada para que continúes sin interrupciones.
                </p>

                <!-- Hidden inputs to preserve DOM references -->
                <input type="hidden" id="clientName" value="">
                <input type="hidden" id="clientPhone" value="">
                <input type="hidden" id="clientEmail" value="">
            </div>
            <?php else: ?>
            <!-- Cliente ya autenticado con Google -->
            <div style="background:#FFFFFF; padding:24px 20px; border-radius:16px; border:1px solid #EAEAEA; max-width: 480px; margin: 0 auto; box-sizing: border-box; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
                <!-- Card de perfil Google conectado -->
                <div style="background: #FAF9F6; border: 1px solid #EAEAEA; border-radius: 12px; padding: 12px 14px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                        <?php if (!empty($cliente['foto_perfil'])): ?>
                            <img src="<?php echo htmlspecialchars($cliente['foto_perfil']); ?>" alt="Perfil" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 1.5px solid #111111; flex-shrink: 0;">
                        <?php else: ?>
                            <div style="width: 42px; height: 42px; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0;">
                                <?php echo strtoupper(substr($cliente['nombre'] ?? 'K', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div style="min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="font-weight: 800; font-size: 0.94rem; color: #111111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                                    <?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>
                                </span>
                                <span style="background: #E8F5E9; color: #2E7D32; font-size: 0.65rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.3px; flex-shrink: 0;">
                                    ✓ Verificado
                                </span>
                            </div>
                            <div style="font-size: 0.78rem; color: #666666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 210px;">
                                <?php echo htmlspecialchars($cliente['email'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                    <span style="font-size: 0.72rem; color: #888888; font-weight: 700; text-align: right; flex-shrink: 0;">
                        Sesión PWA
                    </span>
                </div>

                <p style="color:var(--text-secondary); margin-bottom:18px; font-size:0.88rem;">
                    Verifica tus datos de contacto para enviarte la confirmación y recordatorios por WhatsApp.
                </p>

                <div style="margin-bottom: 16px;">
                    <label style="display:block; margin-bottom:6px; color:#111111; font-size: 0.88rem; font-weight:700;">Nombre completo *</label>
                    <input type="text" id="clientName" placeholder="Tu nombre" value="<?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>"
                        style="width: 100%; padding: 13px; background: #FAF9F6; border: 1px solid #EAEAEA; color: #111111; border-radius: 10px; font-weight: 600; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display:block; margin-bottom:6px; color:#111111; font-size: 0.88rem; font-weight:800;">WhatsApp / Celular (+593) *</label>
                    <input type="tel" id="clientPhone" placeholder="0991234567" value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>" autofocus
                        style="width: 100%; padding: 13px; background: #FAF9F6; border: 1.5px solid #111111; color: #111111; border-radius: 10px; font-weight: 800; font-size: 1.05rem; box-sizing: border-box;">
                    <small style="color: #777777; font-size: 0.76rem; margin-top: 4px; display: block;">* Te enviaremos el recordatorio de tu cita a este número.</small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:6px; color:#111111; font-size: 0.88rem; font-weight:700;">Correo Electrónico (Gmail vinculado)</label>
                    <input type="email" id="clientEmail" placeholder="ejemplo@gmail.com" value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>" readonly
                        style="width: 100%; padding: 13px; background: #F3F4F6; border: 1px solid #EAEAEA; color: #555555; border-radius: 10px; font-weight: 600; box-sizing: border-box; cursor: not-allowed;">
                </div>

                <!-- Código de Referido -->
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px dashed #EAEAEA;">
                    <label style="display:flex; align-items:center; gap:6px; margin-bottom:8px; color:#111111; font-weight:800; font-size: 0.88rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        <span>¿Tienes un Código Promocional o de Referido?</span>
                    </label>
                    <div style="display:flex; gap:8px; width:100%; box-sizing: border-box;">
                        <input type="text" id="referralCodeInput" placeholder="Ej: JOEL888" 
                               style="flex:1; min-width:0; padding:12px; background:#FAF9F6; border:1px solid #EAEAEA; border-radius:10px; text-transform:uppercase; font-weight:800; font-size:0.95rem; color:#111111; box-sizing: border-box;">
                        <button type="button" onclick="aplicarCodigoReferidoReserva()" 
                                style="padding:12px 18px; background:#111111; color:#FFFFFF; border:none; border-radius:10px; font-weight:800; font-size:0.85rem; cursor:pointer; text-transform:uppercase; white-space:nowrap; flex-shrink:0;">
                            Aplicar
                        </button>
                    </div>
                    <div id="referralCodeMessage" style="margin-top:8px; font-size:0.85rem;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Step 5: Confirm -->
        <div class="wizard-step" id="step5">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 900; color: #111111; text-align: center; letter-spacing: 0.5px;">5. Confirma tu Reserva</h2>
            
            <div style="max-width: 480px; width: 100%; margin: 0 auto; background:#FFFFFF; color:#111111; padding:22px 18px; border-radius:18px; border:1px solid #EAEAEA; box-shadow: 0 10px 30px rgba(0,0,0,0.4); box-sizing: border-box;">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:16px; background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 12px; padding: 14px 16px;">
                    <div style="grid-column: 1 / -1; border-bottom: 1px dashed #E0E0E0; padding-bottom: 8px; margin-bottom: 2px;">
                        <span style="color:#777777; font-size:0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">SUCURSAL</span>
                        <div id="confirmBranch" style="font-size:0.95rem; font-weight: 900; color: #111111; margin-top:2px;" data-branch-dynamic="name">KORTZEN Llano Chico</div>
                    </div>
                    <div>
                        <span style="color:#777777; font-size:0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">SERVICIO</span>
                        <div id="confirmService" style="font-size:0.92rem; font-weight: 800; color: #111111; margin-top:2px;">-</div>
                    </div>
                    <div>
                        <span style="color:#777777; font-size:0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">BARBERO</span>
                        <div id="confirmBarber" style="font-size:0.92rem; font-weight: 800; color: #111111; margin-top:2px;">-</div>
                    </div>
                    <div>
                        <span style="color:#777777; font-size:0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">FECHA Y HORA</span>
                        <div id="confirmDateTime" style="font-size:0.92rem; font-weight: 900; color: #111111; margin-top:2px;">-</div>
                    </div>
                    <div>
                        <span style="color:#777777; font-size:0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">PRECIO ESTIMADO</span>
                        <div id="confirmPrice" style="font-size:0.92rem; font-weight: 900; color: #111111; margin-top:2px;">-</div>
                    </div>
                </div>

                <!-- Política de Reserva -->
                <div class="policy-section" style="margin-bottom: 16px; padding: 14px; background: #FAFAFA; border: 1px solid #EAEAEA; border-radius: 12px;">
                    <h4 style="margin: 0 0 8px 0; color: #111111; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#111111" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span><?php echo htmlspecialchars($politicaTitulo); ?></span>
                    </h4>
                    <div style="font-size: 0.78rem; color: #4B5563; line-height: 1.45; margin-bottom: 10px;">
                        <?php 
                        $linesPol = array_filter(array_map('trim', explode("\n", $politicaTexto)));
                        if (count($linesPol) > 1):
                        ?>
                            <ul style="padding-left: 14px; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                                <?php foreach ($linesPol as $l): 
                                    $cleanL = preg_replace('/^[•\-\*✓\s]+/', '', $l);
                                    if (!empty($cleanL)):
                                ?>
                                    <li><?php echo htmlspecialchars($cleanL); ?></li>
                                <?php endif; endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="margin: 0;"><?php echo nl2br(htmlspecialchars($politicaTexto)); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($politicaRequiereCheck): ?>
                        <div style="display: flex; align-items: center; gap: 8px; border-top: 1px dashed #DDD; padding-top: 8px;">
                            <input type="checkbox" id="policyCheckbox" style="width: 18px; height: 18px; accent-color: #111111; cursor: pointer; flex-shrink:0;">
                            <label for="policyCheckbox" style="color: #111111; font-size: 0.78rem; font-weight: 700; cursor: pointer; user-select: none;">
                                <?php echo htmlspecialchars($politicaCheckTexto); ?>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>

                <button id="btnConfirmBooking" class="btn btn-next" style="width:100%; background:#111111; color:#FFFFFF; font-size:0.9rem; font-weight: 800; padding: 14px; border:none; border-radius:10px; <?php echo $politicaRequiereCheck ? 'opacity: 0.4; cursor: not-allowed;' : 'opacity: 1; cursor: pointer;'; ?> text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s;" <?php echo $politicaRequiereCheck ? 'disabled' : ''; ?>>
                    CONFIRMAR CITA ➔
                </button>
            </div>
        </div>

        <div class="wizard-nav">
            <button id="btnPrev" class="btn btn-prev" disabled>Atrás</button>
            <button id="btnNext" class="btn btn-next" disabled>Siguiente</button>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        const IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        const LOGGED_CLIENT = <?php echo json_encode($cliente ?? null); ?>;

        // State
        const bookingData = {
            serviceId: null,
            serviceName: null,
            servicePrice: null,
            serviceDuration: 40,
            barberId: null,
            barberName: null,
            date: null,
            time: null,
            name: <?php echo json_encode($cliente['nombre'] ?? ''); ?>,
            phone: <?php echo json_encode($cliente['telefono'] ?? ''); ?>,
            email: <?php echo json_encode($cliente['email'] ?? ''); ?>
        };

        let currentStep = 1;
        let allServicesList = [];
        let allBarbersList = [];
        let currentLoadedSlots = [];
        let quickSlotData = null;

        let referralCodeApplied = '';
        let appliedDiscountAmount = 0.0;
        let appliedDiscountType = 'fixed';
        let appliedDiscountPercentage = 0.0;

        function loginWithGoogleFromStep4() {
            const btn = document.getElementById('btnGoogleLoginStep4');
            if (btn) {
                btn.disabled = true;
                btn.style.opacity = '0.75';
                btn.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke="#FFFFFF" stroke-opacity="0.25"></circle>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke="#FFFFFF"></path>
                    </svg>
                    <span>Conectando con Google...</span>
                `;
            }

            const pending = {
                serviceId: bookingData.serviceId,
                serviceName: bookingData.serviceName,
                servicePrice: bookingData.servicePrice,
                serviceDuration: bookingData.serviceDuration,
                barberId: bookingData.barberId,
                barberName: bookingData.barberName,
                date: bookingData.date,
                time: bookingData.time,
                referralCode: referralCodeApplied || ''
            };

            try {
                localStorage.setItem('kortzen_pending_booking', JSON.stringify(pending));
                sessionStorage.setItem('kortzen_pending_booking', JSON.stringify(pending));
            } catch (err) {
                console.error('Error guardando borrador:', err);
            }

            window.location.href = 'api/auth/google-login.php?redirect=booking_step4';
        }

        document.addEventListener('DOMContentLoaded', async () => {
            const branchId = localStorage.getItem('kortzen_selected_branch') || 1;

            await loadBarbers(branchId);
            await loadServices(branchId);
            initDatePicker();
            updateNavButtons();

            // Auto-restaurar borrador de reserva si viene de Google OAuth o usuario ya está autenticado con borrador pendiente
            const urlParams = new URLSearchParams(window.location.search);
            const isFromGoogle = urlParams.get('from_google') === '1' || urlParams.get('restore_booking') === '1';
            let savedDraft = null;

            try {
                const rawDraft = localStorage.getItem('kortzen_pending_booking') || sessionStorage.getItem('kortzen_pending_booking');
                if (rawDraft) savedDraft = JSON.parse(rawDraft);
            } catch (err) {
                console.error('Error al parsear borrador:', err);
            }

            if (savedDraft && (isFromGoogle || IS_LOGGED_IN)) {
                // 1. Restaurar Servicio
                if (savedDraft.serviceId) {
                    bookingData.serviceId = savedDraft.serviceId;
                    bookingData.serviceName = savedDraft.serviceName;
                    bookingData.servicePrice = savedDraft.servicePrice;
                    bookingData.serviceDuration = savedDraft.serviceDuration || 40;

                    const sCard = document.querySelector(`#servicesGrid [data-service-id="${bookingData.serviceId}"]`);
                    if (sCard) sCard.classList.add('selected');
                }

                // 2. Restaurar Barbero
                if (savedDraft.barberId !== undefined && savedDraft.barberId !== null) {
                    bookingData.barberId = savedDraft.barberId;
                    bookingData.barberName = savedDraft.barberName;

                    if (bookingData.barberId === 0) {
                        const anyEl = document.getElementById('anyBarberOption');
                        if (anyEl) anyEl.classList.add('selected');
                    } else {
                        const bCard = document.querySelector(`#barbersGrid .option-card[data-barber-id="${bookingData.barberId}"]`);
                        if (bCard) bCard.classList.add('selected');
                    }
                }

                // 3. Restaurar Fecha y Hora
                if (savedDraft.date) {
                    bookingData.date = savedDraft.date;
                    const picker = document.getElementById('datePicker') ? document.getElementById('datePicker')._flatpickr : null;
                    if (picker) {
                        picker.setDate(bookingData.date, false);
                    }
                }
                if (savedDraft.time) {
                    bookingData.time = savedDraft.time;
                }

                // 4. Restaurar Código de Referido si existía
                if (savedDraft.referralCode) {
                    referralCodeApplied = savedDraft.referralCode;
                    const refInput = document.getElementById('referralCodeInput');
                    if (refInput) {
                        refInput.value = referralCodeApplied;
                        aplicarCodigoReferidoReserva();
                    }
                }

                // Limpiar borrador temporal para evitar interferir en reservas futuras
                try {
                    localStorage.removeItem('kortzen_pending_booking');
                    sessionStorage.removeItem('kortzen_pending_booking');
                } catch (e) {}

                // Sincronizar datos del cliente autenticado
                if (IS_LOGGED_IN && LOGGED_CLIENT) {
                    bookingData.name = LOGGED_CLIENT.nombre || '';
                    bookingData.email = LOGGED_CLIENT.email || '';
                    if (LOGGED_CLIENT.telefono) {
                        bookingData.phone = LOGGED_CLIENT.telefono;
                    }

                    const nameInputEl = document.getElementById('clientName');
                    const emailInputEl = document.getElementById('clientEmail');
                    const phoneInputEl = document.getElementById('clientPhone');

                    if (nameInputEl && !nameInputEl.value) nameInputEl.value = bookingData.name;
                    if (emailInputEl && !emailInputEl.value) emailInputEl.value = bookingData.email;
                    if (phoneInputEl && !phoneInputEl.value && bookingData.phone) phoneInputEl.value = bookingData.phone;
                }

                // Avanzar directamente al Paso 4 para verificar datos y continuar
                currentStep = 4;
                showStep(4);
            }

            // Policy Checkbox Logic
            const checkbox = document.getElementById('policyCheckbox');
            const btnConfirm = document.getElementById('btnConfirmBooking');
            if (checkbox && btnConfirm) {
                checkbox.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        btnConfirm.disabled = false;
                        btnConfirm.style.opacity = '1';
                        btnConfirm.style.cursor = 'pointer';
                    } else {
                        btnConfirm.disabled = true;
                        btnConfirm.style.opacity = '0.4';
                        btnConfirm.style.cursor = 'not-allowed';
                    }
                });
            }

            // Input listeners for Step 4
            const phoneInput = document.getElementById('clientPhone');
            const nameInput = document.getElementById('clientName');
            const emailInput = document.getElementById('clientEmail');

            if (phoneInput) {
                phoneInput.addEventListener('input', (e) => {
                    bookingData.phone = e.target.value.trim();
                    updateNavButtons();
                });
            }
            if (nameInput) {
                nameInput.addEventListener('input', (e) => {
                    bookingData.name = e.target.value.trim();
                    updateNavButtons();
                });
            }
            if (emailInput) {
                emailInput.addEventListener('input', (e) => {
                    bookingData.email = e.target.value.trim();
                });
            }

            // Auto-seleccionar servicio si viene en URL (?servicio_id=X)
            const urlServicioId = urlParams.get('servicio_id');
            if (urlServicioId && allServicesList.length > 0 && !savedDraft) {
                const sObj = allServicesList.find(s => s.id == urlServicioId);
                if (sObj) {
                    const cardEl = document.querySelector(`#servicesGrid [data-service-id="${sObj.id}"]`);
                    selectService(sObj.id, sObj.nombre, sObj.precio, sObj.duracion_minutos || 40, cardEl, sObj.barbero_id, sObj.barbero_nombre, sObj.barberos_ids);
                }
            }
        });

        // Branch changed event
        window.addEventListener('kortzen:branchChanged', async (e) => {
            const newBranch = e.detail;
            if (newBranch && newBranch.id) {
                bookingData.serviceId = null;
                bookingData.barberId = null;
                await loadBarbers(newBranch.id);
                await loadServices(newBranch.id);
                updateNavButtons();
            }
        });

        // Formateador de duración (ej: 60 -> 1 h, 85 -> 1 h 25 min, 30 -> 30 min)
        function formatDuration(minutes) {
            const m = parseInt(minutes) || 40;
            if (m < 60) return `${m} min`;
            const hours = Math.floor(m / 60);
            const remMin = m % 60;
            if (remMin === 0) return `${hours} h`;
            return `${hours} h ${remMin} min`;
        }

        // --- Services Loading (Sleek Compact Luxury List) ---
        async function loadServices(branchId = 1) {
            const grid = document.getElementById('servicesGrid');

            try {
                const response = await fetch(`api/get_catalog.php?sucursal_id=${branchId}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();

                if (data.error) {
                    grid.innerHTML = `<p style="color:#cc0000; text-align:center; padding:20px 0;">Error: ${data.error}</p>`;
                    return;
                }

                if (!data.servicios || data.servicios.length === 0) {
                    grid.innerHTML = '<p style="color:#888; text-align:center; padding:20px 0;">No hay servicios disponibles.</p>';
                    return;
                }

                allServicesList = data.servicios;

                // Agrupar por categoría
                const servicesByCategory = {};
                data.servicios.forEach(s => {
                    const cat = s.categoria || 'Servicios';
                    if (!servicesByCategory[cat]) {
                        servicesByCategory[cat] = [];
                    }
                    servicesByCategory[cat].push(s);
                });

                grid.innerHTML = '';

                for (const [category, services] of Object.entries(servicesByCategory)) {
                    const categoryBlock = document.createElement('div');
                    categoryBlock.className = 'services-category-block';

                    const catTitle = document.createElement('div');
                    catTitle.className = 'services-category-title';
                    catTitle.innerHTML = `<span>${category}</span>`;
                    categoryBlock.appendChild(catTitle);

                    const cardGroup = document.createElement('div');
                    cardGroup.className = 'services-card-group';

                    services.forEach(s => {
                        const el = document.createElement('div');
                        el.className = 'service-list-row';
                        el.setAttribute('data-service-id', s.id);
                        if (bookingData.serviceId && bookingData.serviceId == s.id) {
                            el.classList.add('selected');
                        }

                        const thumb = s.foto_url || s.imagen_url || '/assets/images/service-corte-mateo.png';
                        const isMateo = s.nombre.toLowerCase().includes('mateo');
                        const bName = s.barbero_nombre || (isMateo ? 'Mateo' : '');
                        const exclusiveBadgeHtml = bName ? `<span class="service-exclusive-pill">★ ${bName}</span>` : '';
                        const durFormatted = formatDuration(s.duracion_minutos || 40);

                        el.onclick = () => selectService(s.id, s.nombre, s.precio, s.duracion_minutos || 40, el, s.barbero_id, s.barbero_nombre, s.barberos_ids);

                        el.innerHTML = `
                            <img src="${thumb}" alt="${s.nombre}" class="service-row-thumb" onerror="this.onerror=null; this.src='/assets/images/service-corte-mateo.png';">
                            <div class="service-row-info">
                                <div class="service-row-title-wrap">
                                    <span class="service-row-title">${s.nombre}</span>
                                    ${exclusiveBadgeHtml}
                                </div>
                                <div class="service-row-meta">
                                    <span class="duration">${durFormatted}</span>
                                    <span class="meta-dot">·</span>
                                    <button type="button" class="details-btn" onclick="openServiceDetails(event, ${s.id})">Detalles</button>
                                    <span class="meta-dot">·</span>
                                    <span class="price-val">$${parseFloat(s.precio).toFixed(0)}</span>
                                </div>
                            </div>
                            <div class="service-chevron-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </div>
                        `;
                        cardGroup.appendChild(el);
                    });

                    categoryBlock.appendChild(cardGroup);
                    grid.appendChild(categoryBlock);
                }
            } catch (e) {
                console.error('Error cargando servicios:', e);
                grid.innerHTML = `<p style="color:#cc0000; text-align:center; padding:20px 0;">Error al cargar servicios.</p>`;
            }
        }

        // --- Detalle Modal de Servicio (Bottom-Sheet / Modal) ---
        function openServiceDetails(e, serviceId) {
            if (e) {
                e.stopPropagation();
                e.preventDefault();
            }
            const s = allServicesList.find(item => item.id == serviceId);
            if (!s) return;

            const modal = document.getElementById('serviceDetailsModal');
            const content = document.getElementById('serviceDetailsContent');
            if (!modal || !content) return;

            const thumb = s.foto_url || s.imagen_url || '/assets/images/service-corte-mateo.png';
            const isMateo = s.nombre.toLowerCase().includes('mateo');
            const bName = s.barbero_nombre || (isMateo ? 'Mateo Álvaro' : '');

            let exclusiveHtml = '';
            if (bName) {
                exclusiveHtml = `<div style="display:inline-flex; align-items:center; gap:5px; background:rgba(255, 255, 255, 0.1); border:1px solid rgba(255, 255, 255, 0.25); color:#FFFFFF; font-size:0.75rem; font-weight:800; padding:4px 10px; border-radius:6px; margin-bottom:12px; text-transform:uppercase;">
                    <span>★</span> Servicio Exclusivo con ${bName}
                </div>`;
            }

            let descHtml = s.descripcion ? `<p style="font-size:0.88rem; color:#A3A3A3; line-height:1.55; margin:0 0 16px 0;">${s.descripcion}</p>` : '';

            let incluyeHtml = '';
            if (s.que_incluye && s.que_incluye.trim() !== '') {
                const lines = s.que_incluye.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
                const renderedList = lines.map(item => {
                    const clean = item.replace(/^[•\-\*✓\s]+/, '').trim();
                    return `<div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:6px; font-size:0.84rem; color:#E5E5E5; line-height:1.35;">
                        <span style="color:#FFFFFF; font-size:0.85rem; flex-shrink:0;">✓</span>
                        <span>${clean}</span>
                    </div>`;
                }).join('');
                
                incluyeHtml = `
                    <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-left:3px solid #FFFFFF; border-radius:8px; padding:12px 14px; margin-bottom:20px; text-align:left;">
                        <div style="font-size:0.72rem; font-weight:900; text-transform:uppercase; color:#FFFFFF; margin-bottom:8px; letter-spacing:0.8px;">QUÉ INCLUYE:</div>
                        ${renderedList}
                    </div>
                `;
            }

            content.innerHTML = `
                <div style="position:relative; margin-bottom:14px;">
                    <img src="${thumb}" alt="${s.nombre}" style="width:100%; height:180px; object-fit:cover; border-radius:12px; border:1px solid rgba(255,255,255,0.1); display:block;" onerror="this.onerror=null; this.src='/assets/images/service-corte-mateo.png';">
                </div>
                ${exclusiveHtml}
                <h3 style="font-size:1.3rem; font-weight:900; color:#FFFFFF; margin:0 0 8px 0; text-transform:uppercase; letter-spacing:0.4px;">${s.nombre}</h3>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                    <span style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); padding:4px 10px; border-radius:6px; font-size:0.82rem; font-weight:700; color:#CCCCCC;">
                        ⏱️ ${formatDuration(s.duracion_minutos)}
                    </span>
                    <span style="background:rgba(255, 255, 255, 0.12); border:1px solid rgba(255, 255, 255, 0.3); padding:4px 12px; border-radius:6px; font-size:0.95rem; font-weight:900; color:#FFFFFF;">
                        $${parseFloat(s.precio).toFixed(0)}
                    </span>
                </div>
                ${descHtml}
                ${incluyeHtml}
                <button type="button" onclick="selectFromDetails(${s.id})" style="width:100%; background:#FFFFFF; color:#111111; font-weight:900; font-size:0.9rem; padding:15px; border:none; border-radius:12px; text-transform:uppercase; letter-spacing:0.8px; cursor:pointer; box-shadow:0 6px 25px rgba(255,255,255,0.25); transition:transform 0.15s ease; box-sizing:border-box;">
                    SELECCIONAR SERVICIO ($${parseFloat(s.precio).toFixed(0)}) ➔
                </button>
            `;

            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeServiceDetails(e) {
            if (e && e.target && e.target.closest('.service-details-drawer') && !e.target.classList.contains('service-details-close-btn')) {
                return;
            }
            const modal = document.getElementById('serviceDetailsModal');
            if (modal) {
                modal.classList.remove('open');
            }
            document.body.style.overflow = '';
        }

        function selectFromDetails(serviceId) {
            closeServiceDetails();
            const s = allServicesList.find(item => item.id == serviceId);
            if (!s) return;
            const rowEl = document.querySelector(`.service-list-row[data-service-id="${serviceId}"]`);
            selectService(s.id, s.nombre, s.precio, s.duracion_minutos || 40, rowEl, s.barbero_id, s.barbero_nombre, s.barberos_ids);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeServiceDetails();
        });

        // --- Barbers Loading ---
        async function loadBarbers(branchId = 1) {
            try {
                const response = await fetch(`api/get_catalog.php?type=barbers&sucursal_id=${branchId}`);
                const data = await response.json();
                allBarbersList = (data && data.barberos) ? data.barberos : [];
                renderBarbersList(allBarbersList, false);
            } catch (e) {
                console.error(e);
            }
        }

        function renderBarbersList(barbersToRender, isExclusive = false, exclusiveBarberName = '') {
            const grid = document.getElementById('barbersGrid');
            const anyBarberEl = document.getElementById('anyBarberOption');
            grid.innerHTML = '';

            if (isExclusive) {
                if (anyBarberEl) anyBarberEl.style.display = 'none';
            } else {
                if (anyBarberEl) anyBarberEl.style.display = 'flex';
            }

            if (barbersToRender && barbersToRender.length > 0) {
                barbersToRender.forEach(b => {
                    const el = document.createElement('div');
                    el.className = 'option-card';
                    el.setAttribute('data-barber-id', b.id);
                    if (bookingData.barberId && bookingData.barberId == b.id) {
                        el.classList.add('selected');
                    }
                    el.onclick = () => selectBarber(b.id, b.nombre, el);

                    const avatarUrl = b.foto_perfil || b.foto_url || '/assets/images/barber-mateo.jpg';
                    const avatarHtml = `<div class="barber-avatar" style="background-image:url('${avatarUrl}');"></div>`;

                    el.innerHTML = `
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            ${avatarHtml}
                            <h3 style="display:flex; align-items:center; gap:6px;">
                                ${b.nombre} ${isExclusive ? '<span style="color:#111111; font-size:0.8rem;">★</span>' : ''}
                            </h3>
                            <p style="font-size:0.82rem; color:#777777; margin-bottom:0;">${b.sucursal_nombre || 'Kortzen Master'}</p>
                            <div style="margin-top:6px; font-size:0.75rem; color:#111111; font-weight:800;">★★★★★ 5.0</div>
                        </div>
                    `;
                    grid.appendChild(el);
                });
            } else {
                grid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; color: #111111; padding: 2rem 1rem; background: #FFFFFF; border: 1px dashed #D1D5DB; border-radius: 16px;">
                        <p style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.3rem;">No hay barberos asignados a esta sucursal</p>
                    </div>
                `;
            }
        }

        // --- Selection Handlers ---
        function selectService(id, name, price, duration, el, barberoId = null, barberoNombre = null, barberosIds = []) {
            bookingData.serviceId = id;
            bookingData.serviceName = name;
            bookingData.servicePrice = price;
            bookingData.serviceDuration = duration;

            document.querySelectorAll('#servicesGrid [data-service-id]').forEach(c => c.classList.remove('selected'));
            if (el) el.classList.add('selected');

            // Determinar si hay barbero exclusivo
            const isMateoService = name.toLowerCase().includes('mateo');
            let targetBarber = null;

            if (barberoId) {
                targetBarber = allBarbersList.find(b => b.id == barberoId);
            }
            if (!targetBarber && isMateoService) {
                targetBarber = allBarbersList.find(b => b.nombre.toLowerCase().includes('mateo'));
            }

            if (targetBarber) {
                renderBarbersList([targetBarber], true, targetBarber.nombre);
                bookingData.barberId = targetBarber.id;
                bookingData.barberName = targetBarber.nombre;

                const bCard = document.querySelector(`#barbersGrid .option-card[data-barber-id="${targetBarber.id}"]`);
                if (bCard) bCard.classList.add('selected');

                updateNavButtons();

                setTimeout(() => {
                    currentStep = 3;
                    showStep(currentStep);
                }, 300);
            } else {
                bookingData.barberId = null;
                bookingData.barberName = null;
                renderBarbersList(allBarbersList, false);

                updateNavButtons();

                setTimeout(() => {
                    currentStep = 2;
                    showStep(currentStep);
                }, 250);
            }
        }

        function selectAnyBarber(el) {
            bookingData.barberId = 0; // 0 = Cualquier barbero
            bookingData.barberName = 'Cualquier barbero disponible';

            document.querySelectorAll('#barbersGrid .option-card').forEach(c => c.classList.remove('selected'));
            if (el) el.classList.add('selected');

            updateNavButtons();

            setTimeout(() => {
                currentStep = 3;
                showStep(currentStep);
            }, 250);
        }

        function selectBarber(id, name, el) {
            bookingData.barberId = id;
            bookingData.barberName = name;

            const anyEl = document.getElementById('anyBarberOption');
            if (anyEl) anyEl.classList.remove('selected');

            document.querySelectorAll('#barbersGrid .option-card').forEach(c => c.classList.remove('selected'));
            if (el) el.classList.add('selected');

            updateNavButtons();

            setTimeout(() => {
                currentStep = 3;
                showStep(currentStep);
            }, 250);
        }

        function selectTime(time, el) {
            bookingData.time = time;

            document.querySelectorAll('.time-slot').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            updateNavButtons();

            setTimeout(() => {
                currentStep = 4;
                showStep(currentStep);
            }, 250);
        }

        function bookQuickSlot() {
            if (!quickSlotData) return;
            bookingData.date = quickSlotData.fecha;
            bookingData.time = quickSlotData.hora;
            
            const picker = document.getElementById('datePicker')._flatpickr;
            if (picker) {
                picker.setDate(quickSlotData.fecha, false);
            }

            if (bookingData.barberId === 0 && quickSlotData.barbero_id) {
                bookingData.autoAssignedBarberName = quickSlotData.barbero_nombre;
            }

            currentStep = 4;
            showStep(currentStep);
        }

        // --- Slots & DatePicker ---
        function initDatePicker() {
            const fp = flatpickr("#datePicker", {
                locale: "es",
                defaultDate: "today",
                minDate: "today",
                maxDate: new Date().fp_incr(30),
                disableMobile: true,
                onChange: function (selectedDates, dateStr) {
                    bookingData.date = dateStr;
                    bookingData.time = null;
                    loadSlots(dateStr);
                    updateNavButtons();
                }
            });

            // Trigger today slots by default
            const todayStr = fp.formatDate(new Date(), "Y-m-d");
            bookingData.date = todayStr;
            loadSlots(todayStr);
        }

        async function loadSlots(date) {
            const grid = document.getElementById('slotsGrid');
            grid.innerHTML = '<p style="color:#888; grid-column:1/-1; text-align:center; padding:15px;">Consultando disponibilidad...</p>';

            try {
                const bId = (bookingData.barberId !== null) ? bookingData.barberId : 0;
                const sId = bookingData.serviceId || 0;
                const branchId = localStorage.getItem('kortzen_selected_branch') || 1;

                const url = `api/get_disponibilidad.php?fecha=${date}&barbero_id=${bId}&servicio_id=${sId}&sucursal_id=${branchId}`;
                const response = await fetch(url);
                const resData = await response.json();

                let slots = resData.slots || [];
                currentLoadedSlots = slots;

                // Quick slot banner update
                const quickBanner = document.getElementById('quickSlotBanner');
                if (resData.proxima_disponibilidad) {
                    quickSlotData = resData.proxima_disponibilidad;
                    document.getElementById('quickSlotLabel').textContent = `${quickSlotData.label} (${quickSlotData.barbero_nombre})`;
                    if (quickBanner) quickBanner.style.display = 'flex';
                } else {
                    if (quickBanner) quickBanner.style.display = 'none';
                }

                if (slots.length === 0) {
                    let proximaHtml = '';
                    if (resData.proxima_disponibilidad) {
                        proximaHtml = `
                            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed #EAEAEA;">
                                <div style="font-size: 0.78rem; color: #777777; font-weight: 800; margin-bottom: 6px; text-transform: uppercase;">⚡ Hora más cercana disponible:</div>
                                <div style="font-size: 1.05rem; font-weight: 900; color: #111111; margin-bottom: 12px;">${resData.proxima_disponibilidad.label} (${resData.proxima_disponibilidad.barbero_nombre})</div>
                                <button type="button" onclick="bookQuickSlot()" style="background: #111111; color: #FFFFFF; border: none; border-radius: 10px; padding: 10px 18px; font-weight: 800; font-size: 0.85rem; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);">
                                    Reservar ${resData.proxima_disponibilidad.label} ➔
                                </button>
                            </div>
                        `;
                    }
                    grid.innerHTML = `<div style="grid-column:1/-1; color:#555; text-align:center; padding:20px 16px; background:#FFFFFF; border-radius:16px; border:1px solid #EAEAEA; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
                        <div style="font-size:0.92rem; font-weight:700; color:#111111;">No hay turnos libres para esta fecha en el calendario.</div>
                        ${proximaHtml}
                    </div>`;
                    return;
                }

                renderSlotsGrid(slots);

            } catch (e) {
                console.error(e);
                grid.innerHTML = '<p style="color:#dc3545; grid-column:1/-1; text-align:center;">Error al cargar horarios.</p>';
            }
        }

        function renderSlotsGrid(slots) {
            const grid = document.getElementById('slotsGrid');
            grid.innerHTML = '';
            if (slots.length === 0) {
                grid.innerHTML = '<p style="grid-column:1/-1; color:#777; text-align:center; padding:12px;">No hay horarios para este filtro.</p>';
                return;
            }
            slots.forEach(time => {
                const el = document.createElement('div');
                el.className = 'time-slot';
                el.textContent = time;
                if (bookingData.time === time) el.classList.add('selected');
                el.onclick = () => selectTime(time, el);
                grid.appendChild(el);
            });
        }

        function filtrarHorarios(filtro, btnEl) {
            document.querySelectorAll('.slot-filter-btn').forEach(b => {
                b.style.background = '#FFFFFF';
                b.style.color = '#777777';
                b.style.borderColor = '#EAEAEA';
            });
            if (btnEl) {
                btnEl.style.background = '#111111';
                btnEl.style.color = '#FFFFFF';
                btnEl.style.borderColor = '#111111';
            }

            if (!currentLoadedSlots || currentLoadedSlots.length === 0) return;

            let filtrados = currentLoadedSlots;
            if (filtro === 'manana') {
                filtrados = currentLoadedSlots.filter(t => parseInt(t.split(':')[0]) < 13);
            } else if (filtro === 'tarde') {
                filtrados = currentLoadedSlots.filter(t => parseInt(t.split(':')[0]) >= 13);
            }
            renderSlotsGrid(filtrados);
        }

        // --- Código de Referido ---
        async function aplicarCodigoReferidoReserva() {
            const input = document.getElementById('referralCodeInput');
            const msgDiv = document.getElementById('referralCodeMessage');
            if (!input || !msgDiv) return;

            const code = input.value.trim();
            if (!code) {
                msgDiv.style.color = '#dc3545';
                msgDiv.textContent = 'Por favor ingresa un código.';
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'validar');
                formData.append('codigo', code);

                const res = await fetch('api/referidos_action.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    referralCodeApplied = data.codigo;
                    appliedDiscountType = data.tipo || 'fixed';

                    if (appliedDiscountType === 'promocional') {
                        appliedDiscountPercentage = parseFloat(data.descuento_porcentaje || 0);
                        const rawPrice = parseFloat(bookingData.servicePrice || 0);
                        appliedDiscountAmount = (rawPrice * appliedDiscountPercentage) / 100;
                    } else {
                        appliedDiscountAmount = parseFloat(data.descuento || 0);
                        appliedDiscountPercentage = 0;
                    }

                    msgDiv.style.color = '#28a745';
                    msgDiv.innerHTML = `<strong>✓ ${data.message}</strong>`;
                    input.disabled = true;
                    input.style.background = '#1b3b22';
                    if (currentStep === 5) updateSummary();
                } else {
                    msgDiv.style.color = '#dc3545';
                    msgDiv.textContent = data.message;
                }
            } catch (e) {
                msgDiv.style.color = '#dc3545';
                msgDiv.textContent = 'Error al validar el código.';
            }
        }

        // --- Navigation ---
        function showStep(step) {
            document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
            document.getElementById(`step${step}`).classList.add('active');

            // Update dots
            document.querySelectorAll('.step-dot').forEach(d => {
                const s = parseInt(d.dataset.step);
                d.classList.remove('active', 'completed');
                if (s === step) d.classList.add('active');
                if (s < step) d.classList.add('completed');
            });

            if (step === 3) {
                const picker = document.getElementById('datePicker')._flatpickr;
                let targetDate = bookingData.date;
                if (!targetDate && picker) {
                    targetDate = picker.formatDate(new Date(), "Y-m-d");
                    bookingData.date = targetDate;
                    picker.setDate(targetDate, false);
                }
                loadSlots(targetDate || new Date().toISOString().split('T')[0]);
            }

            if (step === 4) {
                if (IS_LOGGED_IN && LOGGED_CLIENT) {
                    const nameInputEl = document.getElementById('clientName');
                    const emailInputEl = document.getElementById('clientEmail');
                    const phoneInputEl = document.getElementById('clientPhone');

                    if (nameInputEl && !nameInputEl.value) nameInputEl.value = LOGGED_CLIENT.nombre || '';
                    if (emailInputEl && !emailInputEl.value) emailInputEl.value = LOGGED_CLIENT.email || '';
                    if (phoneInputEl && !phoneInputEl.value && LOGGED_CLIENT.telefono) phoneInputEl.value = LOGGED_CLIENT.telefono || '';

                    if (nameInputEl && nameInputEl.value) bookingData.name = nameInputEl.value.trim();
                    if (emailInputEl && emailInputEl.value) bookingData.email = emailInputEl.value.trim();
                    if (phoneInputEl && phoneInputEl.value) bookingData.phone = phoneInputEl.value.trim();
                }
            }

            updateNavButtons();

            if (step === 5) {
                updateSummary();
            }

            const container = document.querySelector('.booking-container');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        function updateNavButtons() {
            const prev = document.getElementById('btnPrev');
            const next = document.getElementById('btnNext');

            prev.disabled = currentStep === 1;

            let canNext = false;
            if (currentStep === 1 && bookingData.serviceId) canNext = true;
            if (currentStep === 2 && (bookingData.barberId !== null)) canNext = true;
            if (currentStep === 3 && bookingData.date && bookingData.time) canNext = true;
            if (currentStep === 4) {
                if (!IS_LOGGED_IN) {
                    next.classList.add('hidden');
                } else {
                    next.classList.remove('hidden');
                    const phone = document.getElementById('clientPhone') ? document.getElementById('clientPhone').value.trim() : '';
                    const name = document.getElementById('clientName') ? document.getElementById('clientName').value.trim() : '';
                    if (phone.length >= 7 && name.length >= 2) canNext = true;
                }
            }

            if (currentStep === 5) {
                next.classList.add('hidden');
            } else {
                next.classList.remove('hidden');
                next.disabled = !canNext;
            }
        }

        function updateSummary() {
            const branch = (window.KortzenBranches && window.KortzenBranches.getSelectedBranch) ? window.KortzenBranches.getSelectedBranch() : null;
            const branchName = branch ? branch.name : (localStorage.getItem('kortzen_selected_branch_name') || 'KORTZEN Llano Chico');
            const confBranchEl = document.getElementById('confirmBranch');
            if (confBranchEl) confBranchEl.textContent = branchName;

            document.getElementById('confirmService').textContent = bookingData.serviceName;
            document.getElementById('confirmBarber').textContent = bookingData.barberName || (bookingData.barberId === 0 ? 'Cualquier barbero disponible' : '-');
            document.getElementById('confirmDateTime').textContent = `${bookingData.date} a las ${bookingData.time}`;

            const rawPrice = parseFloat(bookingData.servicePrice || 0);
            if (appliedDiscountType === 'promocional' && appliedDiscountPercentage > 0) {
                appliedDiscountAmount = (rawPrice * appliedDiscountPercentage) / 100;
            }
            const finalPrice = Math.max(0, rawPrice - appliedDiscountAmount);

            const priceContainer = document.getElementById('confirmPrice');
            if (priceContainer) {
                if (appliedDiscountAmount > 0) {
                    const labelDesc = (appliedDiscountType === 'promocional')
                        ? `Descuento Promo (${appliedDiscountPercentage}% OFF)`
                        : `Descuento Referido`;

                    priceContainer.innerHTML = `
                        <div style="font-size: 0.85rem; color: #888888; text-decoration: line-through;">$${rawPrice.toFixed(2)}</div>
                        <div style="font-size: 1.25rem; font-weight: 900; color: #28a745; margin-bottom: 2px;">$${finalPrice.toFixed(2)}</div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #28a745;">-${labelDesc}: -$${appliedDiscountAmount.toFixed(2)}</div>
                    `;
                } else {
                    priceContainer.textContent = `$${rawPrice.toFixed(2)}`;
                }
            }
        }

        document.getElementById('btnPrev').addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        });

        document.getElementById('btnNext').addEventListener('click', () => {
            if (currentStep < 5) {
                if (currentStep === 4) {
                    if (!IS_LOGGED_IN) {
                        loginWithGoogleFromStep4();
                        return;
                    }
                    const phone = document.getElementById('clientPhone') ? document.getElementById('clientPhone').value.trim() : '';
                    const name = document.getElementById('clientName') ? document.getElementById('clientName').value.trim() : '';
                    const email = document.getElementById('clientEmail') ? document.getElementById('clientEmail').value.trim() : '';
                    if (!name || name.length < 2) {
                        alert('Por favor ingresa tu nombre completo.');
                        return;
                    }
                    if (!phone || phone.length < 7) {
                        alert('Por favor ingresa un número de teléfono/WhatsApp válido (+593).');
                        const pInput = document.getElementById('clientPhone');
                        if (pInput) pInput.focus();
                        return;
                    }
                    bookingData.name = name;
                    bookingData.phone = phone;
                    bookingData.email = email;
                }

                currentStep++;
                showStep(currentStep);
            }
        });

        // --- Final Booking Submission ---
        document.getElementById('btnConfirmBooking').addEventListener('click', async () => {
            const btn = document.getElementById('btnConfirmBooking');
            btn.disabled = true;
            btn.textContent = "Confirmando tu cita...";

            try {
                const formData = new FormData();
                const selectedBranchId = (window.KortzenBranches && window.KortzenBranches.getSelectedBranchId) ? window.KortzenBranches.getSelectedBranchId() : (localStorage.getItem('kortzen_selected_branch') || 1);
                formData.append('sucursal_id', selectedBranchId);
                formData.append('servicio_id', bookingData.serviceId);
                formData.append('barbero_id', (bookingData.barberId !== null) ? bookingData.barberId : 0);
                formData.append('fecha', bookingData.date);
                formData.append('hora', bookingData.time);
                formData.append('nombre', bookingData.name || document.getElementById('clientName').value.trim());
                formData.append('telefono', bookingData.phone || document.getElementById('clientPhone').value.trim());
                formData.append('email', bookingData.email || document.getElementById('clientEmail').value.trim());
                
                if (referralCodeApplied && referralCodeApplied.trim() !== '') {
                    formData.append('codigo_referido', referralCodeApplied.trim());
                }

                const req = await fetch('api/crear_cita_cliente.php', {
                    method: 'POST',
                    body: formData
                });

                const res = await req.json();

                if (res.success) {
                    const branch = (window.KortzenBranches && window.KortzenBranches.getSelectedBranch) ? window.KortzenBranches.getSelectedBranch() : null;
                    const branchName = branch ? branch.name : (localStorage.getItem('kortzen_selected_branch_name') || 'KORTZEN Llano Chico');
                    const bName = res.barbero_nombre || bookingData.barberName;
                    
                    const gCalTitle = encodeURIComponent(`Cita en ${branchName} - ${bookingData.serviceName}`);
                    const gCalLoc = encodeURIComponent(`${branchName}, Quito`);
                    const gCalDetails = encodeURIComponent(`Cita con ${bName} el ${bookingData.date} a las ${bookingData.time}`);
                    const dateClean = bookingData.date.replace(/-/g, '');
                    const timeClean = bookingData.time.replace(':', '');
                    const gCalUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${gCalTitle}&dates=${dateClean}T${timeClean}00/${dateClean}T${timeClean}00&details=${gCalDetails}&location=${gCalLoc}`;

                    const googleAuthUrl = res.google_auth_url || 'api/auth/google-login.php';

                    document.querySelector('.booking-container').innerHTML = `
                    <div style="text-align:center; padding-top:20px; padding-bottom:60px;">
                        <div style="width:72px; height:72px; border-radius:50%; background:#111111; color:#FFFFFF; font-size:2.2rem; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto; font-weight:900; box-shadow:0 4px 20px rgba(0,0,0,0.15);">✓</div>
                        <h1 style="color:#111111; font-size:1.6rem; font-weight:900; margin-bottom:6px; font-family:'Playfair Display', Georgia, serif;">¡Cita Confirmada!</h1>
                        <p style="color:#777777; font-size:0.92rem; margin-bottom:22px;">Hemos reservado tu turno con éxito.</p>
                        
                        <div style="max-width:480px; margin:0 auto 20px auto; background:#FFFFFF; color:#111111; padding:20px 18px; border-radius:16px; border:1px solid #EAEAEA; text-align:left; box-shadow:0 4px 20px rgba(0,0,0,0.04);">
                            <div style="margin-bottom:10px;">
                                <span style="font-size:0.7rem; color:#777; font-weight:800; text-transform:uppercase;">SUCURSAL</span>
                                <div style="font-size:1rem; font-weight:900; color:#111111;">${branchName}</div>
                            </div>
                            <div style="margin-bottom:10px;">
                                <span style="font-size:0.7rem; color:#777; font-weight:800; text-transform:uppercase;">SERVICIO</span>
                                <div style="font-size:1rem; font-weight:800;">${bookingData.serviceName} (${bookingData.serviceDuration} min)</div>
                            </div>
                            <div style="margin-bottom:10px;">
                                <span style="font-size:0.7rem; color:#777; font-weight:800; text-transform:uppercase;">BARBERO ASIGNADO</span>
                                <div style="font-size:1rem; font-weight:800; color:#111111;">${bName}</div>
                            </div>
                            <div>
                                <span style="font-size:0.7rem; color:#777; font-weight:800; text-transform:uppercase;">FECHA Y HORA</span>
                                <div style="font-size:1.05rem; font-weight:900; color:#111111;">${res.fecha_legible || bookingData.date} a las ${bookingData.time}</div>
                            </div>
                        </div>

                        <!-- Botón Exclusivo de Google Sign-in -->
                        <div style="max-width:480px; margin:0 auto 20px auto; background:#FFFFFF; border:1px solid #EAEAEA; padding:18px 16px; border-radius:16px; text-align:center; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
                            <div style="font-size:0.92rem; font-weight:800; color:#111111; margin-bottom:6px;">¿Deseas guardar tus datos para próximas reservas?</div>
                            <p style="font-size:0.78rem; color:#777777; margin-bottom:14px;">Vincula tu cuenta con Google en un solo clic para ver tu historial y acumular puntos.</p>
                            <a href="${googleAuthUrl}" style="display:inline-flex; align-items:center; justify-content:center; gap:10px; background:#111111; color:#FFFFFF; font-weight:800; font-size:0.88rem; padding:12px 18px; border-radius:10px; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; box-shadow:0 4px 15px rgba(0,0,0,0.15);">
                                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/><path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/></svg>
                                <span>Guardar mis datos con Google</span>
                            </a>
                        </div>

                        <!-- Botón Añadir a Google Calendar -->
                        <div style="max-width:480px; margin:0 auto 12px auto;">
                            <a href="${gCalUrl}" target="_blank" style="display:flex; align-items:center; justify-content:center; gap:10px; background:#4285F4; color:#FFFFFF; font-weight:800; font-size:0.88rem; padding:13px; border-radius:12px; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; box-sizing:border-box;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <span>Añadir a mi Google Calendar</span>
                            </a>
                        </div>

                        <!-- Botón Agendar Otra Cita -->
                        <div style="max-width:480px; margin:0 auto 16px auto;">
                            <a href="reservar.php" style="display:flex; align-items:center; justify-content:center; gap:10px; background:#FFFFFF; border:1px solid #EAEAEA; color:#111111; font-weight:800; font-size:0.88rem; padding:13px 18px; border-radius:12px; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; box-sizing:border-box; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                                <span>+ Agendar Otra Cita / Acompañante</span>
                            </a>
                        </div>

                        <a href="mis-citas.php" class="btn btn-next" style="display:inline-block; max-width:480px; width:100%; text-decoration:none; background:#111111; color:#FFFFFF; font-weight:800; padding:14px; border-radius:12px; text-transform:uppercase; letter-spacing:1px; box-sizing:border-box;">Ver Mis Citas</a>
                    </div>
                    `;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    alert('Error: ' + res.message);
                    btn.disabled = false;
                    btn.textContent = "CONFIRMAR CITA ➔";
                }

            } catch (e) {
                console.error(e);
                alert('No se pudo procesar la reserva. Por favor intenta de nuevo.');
                btn.disabled = false;
                btn.textContent = "CONFIRMAR CITA ➔";
            }
        });
    </script>
    
    <!-- Native Bottom Navigation Bar -->
    <nav class="pwa-bottom-nav-bar">
        <a href="cliente-dashboard.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="pwa-servicios.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="6" cy="6" r="3"></circle>
                <circle cx="6" cy="18" r="3"></circle>
                <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
            </svg>
            <span>Servicios</span>
        </a>
        <a href="reservar.php" class="pwa-nav-tab pwa-nav-tab--active">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Reservar</span>
        </a>
        <a href="mis-citas.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>Citas</span>
        </a>
        <a href="mi-perfil.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Perfil</span>
        </a>
    </nav>
    <!-- Modal / Drawer de Detalles del Servicio (Luxury Dark) -->
    <div id="serviceDetailsModal" class="service-details-backdrop" onclick="closeServiceDetails(event)">
        <div class="service-details-drawer" onclick="event.stopPropagation()">
            <div class="service-details-drawer-handle"></div>
            <button type="button" class="service-details-close-btn" onclick="closeServiceDetails()" aria-label="Cerrar">&times;</button>
            <div id="serviceDetailsContent">
                <!-- Dinámico -->
            </div>
        </div>
    </div>
</body>
</html>
