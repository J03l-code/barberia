<?php
require_once 'config.php';
enforceRateLimit('reservar_page', 40, 60);

// Si no hay cliente logueado, redirigir a login de cliente (o google auth)
// Nota: Asumimos que existe un index.html con botón de login o similar.
// Por ahora, si no está logueado, mostramos advertencia o forzamos login.
if (!isClienteLoggedIn()) {
    // Redirigir a la página de login de clientes
    header('Location: cliente-login.php');
    exit;
}

$cliente = getCurrentCliente();
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
    <link rel="stylesheet" href="css/pwa-native.css?v=50">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js" defer></script>
    <script src="/js/branch-selector.js?v=26000"></script>
    <style>
        /* ANTI-ZOOM MOBILE RULE */
        input, select, textarea, .flatpickr-input {
            font-size: 16px !important;
            touch-action: manipulation;
        }

        /* FLATPICKR LUXURY CLEAN DARK THEME OVERRIDES WITH PERFECT 7-COLUMN RESPONSIVE LAYOUT */
        .flatpickr-calendar {
            background: #141416 !important;
            border: 2px solid #C0A062 !important;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.95) !important;
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
            background: #141416 !important;
            border-bottom: 1px solid #28282C !important;
            width: 100% !important;
        }
        .flatpickr-months .flatpickr-month {
            background: #141416 !important;
            color: #FFFFFF !important;
            font-weight: 800 !important;
            fill: #FFFFFF !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month input.cur-year {
            background: #141416 !important;
            color: #FFFFFF !important;
            font-weight: 800 !important;
            font-size: 1.1rem !important;
            border: none !important;
            padding: 2px 6px !important;
            border-radius: 6px !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month {
            background: #141416 !important;
            color: #FFFFFF !important;
        }

        .flatpickr-weekdays {
            background: #141416 !important;
            width: 100% !important;
            display: flex !important;
            justify-content: space-around !important;
        }
        span.flatpickr-weekday {
            background: #141416 !important;
            color: #C0A062 !important;
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

        /* 1. DIAS DISPONIBLES / SELECCIONABLES (DESTACADOS CON TEXTO BLANCO BRILLANTE Y ANILLO DORADO) */
        .flatpickr-day:not(.disabled):not(.prevMonthDay):not(.nextMonthDay) {
            color: #FFFFFF !important;
            font-weight: 800 !important;
            background: #1E1E22 !important;
            border: 1px solid rgba(192, 160, 98, 0.45) !important;
            border-radius: 50% !important;
            transition: all 0.2s ease !important;
        }
        .flatpickr-day:not(.disabled):not(.prevMonthDay):not(.nextMonthDay):hover {
            background: #C0A062 !important;
            color: #000000 !important;
            border-color: #C0A062 !important;
            transform: scale(1.1) !important;
        }

        /* 2. DIA SELECCIONADO (CIRCULO ORO INTENSO) */
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.selected:focus, .flatpickr-day.selected:hover {
            background: #C0A062 !important;
            border: 2px solid #FFFFFF !important;
            color: #000000 !important;
            font-weight: 800 !important;
            border-radius: 50% !important;
            box-shadow: 0 0 15px rgba(192, 160, 98, 0.8) !important;
            transform: scale(1.1) !important;
        }

        /* 3. DIAS NO DISPONIBLES / PASADOS / DESHABILITADOS (OPACIDAD BAJA Y TACHADOS) */
        .flatpickr-day.disabled, .flatpickr-day.disabled:hover, .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay, .flatpickr-day.flatpickr-disabled {
            color: #333333 !important;
            opacity: 0.25 !important;
            text-decoration: line-through !important;
            background: transparent !important;
            border: 1px solid transparent !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
        }

        .flatpickr-day.today {
            border: 2px solid #C0A062 !important;
        }
        .flatpickr-months .flatpickr-prev-month, .flatpickr-months .flatpickr-next-month {
            color: #C0A062 !important;
            fill: #C0A062 !important;
        }

        :root {
            --gold: #C0A062;
            --dark-bg: #050505;
            --card-bg: #111111;
            --text-primary: #F5F5F5;
            --text-secondary: #A3A3A3;
        }

        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Container & Header */
        .booking-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 20px 16px;
            min-height: auto;
            padding-bottom: 40px;
        }

        .booking-header {
            text-align: center;
            margin-bottom: 20px;
            margin-top: 10px;
        }

        .booking-title {
            font-size: 2rem;
            color: var(--gold);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
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
            height: 3px;
            background: #2A2A2D;
            z-index: 1;
        }

        .step-dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1C1C1E;
            color: #888888;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            position: relative;
            z-index: 5;
            border: 2px solid #333333;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-sizing: border-box;
        }

        .step-dot.active {
            background: #000000 !important;
            color: #FFFFFF !important;
            border: 3px solid #C0A062 !important;
            box-shadow: 0 0 15px rgba(192, 160, 98, 0.7), inset 0 0 5px rgba(192, 160, 98, 0.4);
            transform: scale(1.15);
        }

        .step-dot.completed {
            background: #C0A062 !important;
            color: #000000 !important;
            border: 2px solid #C0A062 !important;
            font-weight: 800;
        }

        /* Wizard Sections */
        .wizard-step {
            display: none;
            animation: fadeIn 0.5s ease;
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

        /* Cards Grid */
        .grid-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        /* Service Card */
        .option-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .option-card:hover {
            border-color: #FFFFFF;
            background: rgba(255, 255, 255, 0.05);
        }

        .option-card.selected {
            background: #181818 !important;
            border: 2.5px solid #FFFFFF !important;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.35), inset 0 0 10px rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
        }

        .option-card.selected h3,
        .option-card.selected p,
        .option-card.selected .price {
            color: #FFFFFF !important;
            font-weight: 800 !important;
        }

        .option-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }

        .option-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .price {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: var(--gold);
            font-size: 1.2rem;
        }

        /* Barber Card */
        .barber-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #333;
            margin: 0 auto 15px;
            background-size: cover;
            background-position: center;
            border: 2px solid #FFFFFF;
        }

        /* Date & Time */
        .datetime-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 700px) {
            .datetime-wrapper {
                grid-template-columns: 1fr;
            }
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
        }

        .time-slot {
            padding: 10px;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .time-slot:hover:not(.disabled) {
            border-color: #FFFFFF;
        }

        .time-slot.selected {
            background: #181818 !important;
            color: #FFFFFF !important;
            border: 2.5px solid #FFFFFF !important;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.4);
            font-weight: 800 !important;
        }

        .time-slot.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            background: #111;
        }

        /* Navigation Buttons */
        .wizard-nav {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 20px auto 0 auto;
            max-width: 375px;
            width: calc(100% - 20px);
            padding-top: 16px;
            padding-bottom: 90px; /* Space above bottom nav bar */
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            box-sizing: border-box;
        }

        .btn {
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s ease;
            border: none;
            flex: 1;
            text-align: center;
        }

        .btn-prev {
            background: #FFFFFF;
            color: #111111;
            border: 1px solid #DDDDDD;
        }

        .btn-prev:hover, .btn-prev:active {
            background: #F0F0F0;
        }

        .btn-next {
            background: #FFFFFF;
            color: #111111;
            font-weight: 700;
        }

        .btn-next:disabled {
            background: #333333;
            color: #777777;
            cursor: not-allowed;
        }

        .hidden {
            display: none;
        }
    </style>
</head>

<body>

    <div class="booking-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>
        <div class="booking-header">
            <h1 class="booking-title">Tu Cita</h1>
            <p>Hola,
                <?php echo htmlspecialchars($cliente['nombre']); ?>. Vamos a agendar tu próximo corte.
            </p>
        </div>

        <!-- Selector de Sucursal Activa -->
        <div onclick="window.createBranchSelectorModal ? window.createBranchSelectorModal() : (window.KortzenBranches && window.KortzenBranches.showSelector())" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 10px 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s ease;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.1rem;">📍</span>
                <div>
                    <div style="font-size: 0.68rem; font-weight: 800; color: var(--color-gold, #C0A062); text-transform: uppercase; letter-spacing: 0.5px;">SUCURSAL SELECCIONADA</div>
                    <div style="font-size: 0.92rem; font-weight: 800; color: #FFFFFF;" data-branch-dynamic="name">KORTZEN Llano Chico</div>
                </div>
            </div>
            <span style="background: #FFFFFF; color: #111111; font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 6px; text-transform: uppercase;">
                Cambiar
            </span>
        </div>

        <!-- Progress -->
        <div class="steps-progress">
            <div class="step-dot active" data-step="1">1</div>
            <div class="step-dot" data-step="2">2</div>
            <div class="step-dot" data-step="3">3</div>
            <div class="step-dot" data-step="4">4</div>
            <div class="step-dot" data-step="5">5</div>
        </div>

        <!-- Step 1: Services -->
        <div class="wizard-step active" id="step1">
            <h2 style="margin-bottom: 20px;">Elige tu Servicio</h2>
            <div class="grid-options" id="servicesGrid">
                <!-- Cargado vía JS -->
                <p>Cargando servicios...</p>
            </div>
        </div>

        <!-- Step 2: Barbers -->
        <div class="wizard-step" id="step2">
            <h2 style="margin-bottom: 20px;">Elige tu Barbero</h2>
            <div class="grid-options" id="barbersGrid">
                <!-- Cargado vía JS -->
            </div>
        </div>

        <!-- Step 3: Date & Time -->
        <div class="wizard-step" id="step3">
            <h2 style="margin-bottom: 20px; font-weight: 800;">Elige Fecha y Hora</h2>
            <div class="datetime-wrapper">
                <div style="background: #161618; border: 1.5px solid #C0A062; border-radius: 14px; padding: 18px; margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);">
                    <div style="margin-bottom:12px;">
                        <label style="display:flex; align-items:center; gap:8px; color:#FFFFFF; font-weight:800; font-size:1.05rem; margin:0;">
                            <i class="fas fa-calendar-alt" style="color:#C0A062; font-size:1.2rem;"></i> Selecciona el día de tu cita
                        </label>
                    </div>
                    <div style="position: relative; width: 100%;">
                        <i class="fas fa-calendar-day" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #C0A062; font-size: 1.2rem; pointer-events: none; z-index: 2;"></i>
                        <input type="text" id="datePicker" placeholder="Haz clic aquí para abrir el calendario..." readonly
                            style="width: 100%; padding: 16px 40px 16px 48px; background: #08080A; border: 1px solid #C0A062; color: #FFFFFF; font-weight: 800; font-size: 16px !important; border-radius: 10px; cursor: pointer; box-shadow: 0 0 12px rgba(192, 160, 98, 0.2); transition: all 0.3s ease; box-sizing: border-box;">
                        <i class="fas fa-chevron-down" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #C0A062; font-size: 1rem; pointer-events: none; z-index: 2;"></i>
                    </div>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <label style="color:var(--text-secondary); margin:0;">Horarios Disponibles</label>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="slot-filter-btn active" onclick="filtrarHorarios('all', this)" style="padding:5px 12px; border-radius:20px; border:1px solid #111; background:#111; color:#fff; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                TODOS
                            </button>
                            <button type="button" class="slot-filter-btn" onclick="filtrarHorarios('manana', this)" style="padding:5px 12px; border-radius:20px; border:1px solid #ddd; background:#fff; color:#111; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                MAÑANA (9-13h)
                            </button>
                            <button type="button" class="slot-filter-btn" onclick="filtrarHorarios('tarde', this)" style="padding:5px 12px; border-radius:20px; border:1px solid #ddd; background:#fff; color:#111; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                TARDE (14-20h)
                            </button>
                        </div>
                    </div>
                    <div id="slotsGrid" class="slots-grid">
                        <p style="color:#666; grid-column: 1/-1;">Selecciona una fecha arriba para ver los turnos disponibles</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Personal Details -->
        <div class="wizard-step" id="step4">
            <h2 style="margin-bottom: 20px;">Datos de Contacto</h2>
            <div
                style="background:var(--card-bg); padding:30px; border-radius:12px; border:1px solid #E0E0E0; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <p style="color:var(--text-secondary); margin-bottom:20px;">Necesitamos un número de contacto para
                    confirmar tu cita.</p>

                <div style="margin-bottom: 20px;">
                    <label
                        style="display:block; margin-bottom:8px; color:var(--text-secondary); font-size: 0.9rem;">Nombre</label>
                    <input type="text" id="clientName" readonly
                        style="width: 100%; padding: 12px; background: #F5F5F5; border: 1px solid #DDD; color: #555; border-radius: 6px; cursor: not-allowed;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label
                        style="display:block; margin-bottom:8px; color:var(--text-secondary); font-size: 0.9rem;">Email</label>
                    <input type="text" id="clientEmail" readonly
                        style="width: 100%; padding: 12px; background: #F5F5F5; border: 1px solid #DDD; color: #555; border-radius: 6px; cursor: not-allowed;">
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display:block; margin-bottom:8px; color:var(--gold); font-size: 0.9rem;">Teléfono /
                        WhatsApp *</label>
                    <input type="tel" id="clientPhone" placeholder="Ej: 0991234567"
                        style="width: 100%; padding: 12px; background: #FFF; border: 2px solid var(--gold); color: #333; border-radius: 6px; font-weight: bold; font-size: 1.1rem;">
                </div>
                <p style="font-size: 0.8rem; color: #999;">* Obligatorio para notificaciones de la cita.</p>

                    <label style="display:flex; align-items:center; gap:6px; margin-bottom:8px; color:#111111; font-weight:800; font-size: 0.9rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        <span>¿Tienes un Código de Referido?</span>
                    </label>
                    <div style="display:flex; gap:8px; width:100%; box-sizing: border-box;">
                        <input type="text" id="referralCodeInput" placeholder="Ej: JOEL888" 
                               style="flex:1; min-width:0; padding:12px; background:#FFFFFF; border:1px solid #CCCCCC; border-radius:8px; text-transform:uppercase; font-weight:800; font-size:0.95rem; color:#111111; box-sizing: border-box;">
                        <button type="button" onclick="aplicarCodigoReferidoReserva()" 
                                style="padding:12px 18px; background:var(--color-gold, #C0A062); color:#111111; border:none; border-radius:8px; font-weight:900; font-size:0.85rem; cursor:pointer; text-transform:uppercase; white-space:nowrap; flex-shrink:0;">
                            Aplicar
                        </button>
                    </div>
                    <div id="referralCodeMessage" style="margin-top:8px; font-size:0.85rem;"></div>
                </div>
            </div>
        </div>

        <!-- Step 5: Confirm -->
        <div class="wizard-step" id="step5">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 900; color: #FFFFFF; text-align: center; letter-spacing: 1px;">Confirma tu Reserva</h2>
            <div style="max-width: 375px; width: calc(100% - 20px); margin: 0 auto; background:#FFFFFF; color:#111111; padding:18px 16px; border-radius:18px; border:1px solid #EAEAEA; box-shadow: 0 10px 30px rgba(0,0,0,0.25); box-sizing: border-box;">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:14px; background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 12px; padding: 12px 14px;">
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

                <div class="policy-section" style="margin-bottom: 14px; padding: 14px; background: #FAFAFA; border: 1px solid #EAEAEA; border-radius: 12px;">
                    <h4 style="margin: 0 0 8px 0; color: #111111; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C0A062" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span><?php echo htmlspecialchars($politicaTitulo); ?></span>
                    </h4>
                    <div style="font-size: 0.78rem; color: #4B5563; line-height: 1.45; margin-bottom: 10px;">
                        <?php 
                        $linesPol = array_filter(array_map('trim', explode("\n", $politicaTexto)));
                        if (count($linesPol) > 1):
                        ?>
                            <ul style="padding-left: 14px; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                                <?php foreach ($linesPol as $l): 
                                    $cleanL = preg_replace('/^[•\-\*\✓\s]+/', '', $l);
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

                <button id="btnConfirmBooking" class="btn btn-next" style="width:100%; background:#111111; color:#FFFFFF; font-size:0.85rem; font-weight: 800; padding: 12px; border:none; border-radius:10px; <?php echo $politicaRequiereCheck ? 'opacity: 0.4; cursor: not-allowed;' : 'opacity: 1; cursor: pointer;'; ?> text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s;" <?php echo $politicaRequiereCheck ? 'disabled' : ''; ?>>
                    CONFIRMAR RESERVA
                </button>
            </div>
        </div>

        <script>
            // Policy Checkbox Logic
            document.addEventListener('DOMContentLoaded', () => {
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
            });
        </script>

        <div class="wizard-nav">
            <button id="btnPrev" class="btn btn-prev" disabled>Atrás</button>
            <button id="btnNext" class="btn btn-next" disabled>Siguiente</button>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        // State
        const bookingData = {
            serviceId: null,
            serviceName: null,
            servicePrice: null,
            barberId: null,
            barberName: null,
            date: null,
            time: null,
            phone: null
        };

        let currentStep = 1;
        let hasExistingPhone = false; // Track if client already has phone
        let allServicesList = [];
        let allBarbersList = [];

        // Load Data
        document.addEventListener('DOMContentLoaded', async () => {
            // Get selected branch from localStorage
            const branchId = localStorage.getItem('kortzen_selected_branch') || 1;

            await loadBarbers(branchId);
            await loadServices(branchId);
            await loadClientProfile();
            initDatePicker();
            updateNavButtons();

            // Auto-seleccionar servicio si viene en URL (?servicio_id=X)
            const urlParams = new URLSearchParams(window.location.search);
            const urlServicioId = urlParams.get('servicio_id');
            if (urlServicioId && allServicesList.length > 0) {
                const sObj = allServicesList.find(s => s.id == urlServicioId);
                if (sObj) {
                    const cardEl = document.querySelector(`#servicesGrid .option-card[data-service-id="${sObj.id}"]`);
                    selectService(sObj.id, sObj.nombre, sObj.precio, cardEl, sObj.barbero_id, sObj.barbero_nombre);
                }
            }
        });

        // Escuchar cambios de sucursal en tiempo real
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

        // --- API Calls ---

        async function loadServices(branchId = 1) {
            const grid = document.getElementById('servicesGrid');

            try {
                const response = await fetch(`api/get_catalog.php?sucursal_id=${branchId}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.error) {
                    grid.innerHTML = `<p style="color:#cc0000; grid-column:1/-1;">Error: ${data.error}</p>`;
                    return;
                }

                if (!data.servicios || data.servicios.length === 0) {
                    grid.innerHTML = '<p style="color:#888; grid-column:1/-1;">No hay servicios disponibles.</p>';
                    return;
                }

                allServicesList = data.servicios;

                // Group by category
                const servicesByCategory = {};
                data.servicios.forEach(s => {
                    const cat = s.categoria || 'General';
                    if (!servicesByCategory[cat]) {
                        servicesByCategory[cat] = [];
                    }
                    servicesByCategory[cat].push(s);
                });

                grid.innerHTML = '';

                // Iterate categories
                for (const [category, services] of Object.entries(servicesByCategory)) {
                    // Create Category Header
                    const catHeader = document.createElement('h3');
                    catHeader.style.cssText = 'grid-column: 1/-1; margin: 20px 0 10px 0; color: var(--color-gold); border-bottom: 1px solid #ddd; padding-bottom: 5px; text-transform: uppercase; font-size: 1.1rem;';
                    catHeader.textContent = category;
                    grid.appendChild(catHeader);

                    // Create Service Cards
                    services.forEach(s => {
                        const el = document.createElement('div');
                        el.className = 'option-card';
                        el.setAttribute('data-service-id', s.id);
                        if (bookingData.serviceId && bookingData.serviceId == s.id) {
                            el.classList.add('selected');
                        }
                        el.onclick = () => selectService(s.id, s.nombre, s.precio, el, s.barbero_id, s.barbero_nombre);

                        let imageHtml = '';
                        if (s.foto_url && s.foto_url.trim() !== '') {
                            imageHtml = `<div class="service-image" style="width:100%; height:140px; background-image:url('${s.foto_url}'); background-size:cover; background-position:center; border-radius:8px 8px 0 0; margin-bottom:10px;"></div>`;
                        } else {
                            imageHtml = `<div class="service-image" style="width:100%; height:140px; background: linear-gradient(to bottom right, #333, #555); display:flex; align-items:center; justify-content:center; border-radius:8px 8px 0 0; margin-bottom:10px;"><span style="color:rgba(255,255,255,0.2); font-size:2rem;">✂️</span></div>`;
                        }

                        let exclusiveBadgeHtml = '';
                        const isMateo = s.nombre.toLowerCase().includes('mateo');
                        if (s.barbero_nombre || isMateo) {
                            const bName = s.barbero_nombre || 'Mateo Álvaro';
                            exclusiveBadgeHtml = `<div style="font-size:0.75rem; color:var(--color-gold, #C0A062); font-weight:800; margin-top:2px;">⭐ Solo con ${bName}</div>`;
                        }

                        let descHtml = s.descripcion ? `<p style="font-size:0.8rem; color:#888888; margin:4px 0 6px 0; line-height:1.35;">${s.descripcion}</p>` : '';

                        let incluyeHtml = '';
                        if (s.que_incluye && s.que_incluye.trim() !== '') {
                            const lines = s.que_incluye.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
                            const renderedList = lines.slice(0, 4).map(item => {
                                const clean = item.replace(/^[•\-\*\✓\s]+/, '').trim();
                                return `<div style="display:flex; align-items:flex-start; gap:4px; margin-bottom:2px;"><span style="color:var(--color-gold, #C0A062); font-size:0.72rem; flex-shrink:0;">✓</span><span>${clean}</span></div>`;
                            }).join('');
                            
                            incluyeHtml = `
                                <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-left:2.5px solid var(--color-gold, #C0A062); border-radius:4px; padding:6px 8px; margin:6px 0 8px 0; text-align:left; font-size:0.74rem; color:#CCCCCC; line-height:1.3;">
                                    <div style="font-size:0.68rem; font-weight:800; text-transform:uppercase; color:var(--color-gold, #C0A062); margin-bottom:3px; letter-spacing:0.4px;">Incluye:</div>
                                    ${renderedList}
                                </div>
                            `;
                        }

                        el.innerHTML = `
                            ${imageHtml}
                            <h3 style="margin:5px 0 2px 0;">${s.nombre}</h3>
                            <p style="font-size:0.85rem; color:#777; margin-bottom:2px;">${s.duracion_minutos} min</p>
                            ${exclusiveBadgeHtml}
                            ${descHtml}
                            ${incluyeHtml}
                            <span class="price" style="font-size:1.1rem; margin-top:4px; display:block; font-weight:800; color:#FFFFFF;">$${s.precio}</span>
                        `;
                        grid.appendChild(el);
                    });
                }
            } catch (e) {
                console.error('Error cargando servicios:', e);
                grid.innerHTML = `<p style="color:#cc0000; grid-column:1/-1;">Error al cargar servicios. Revisa la consola (F12).</p>`;
            }
        }

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
            grid.innerHTML = '';

            let noticeEl = document.getElementById('exclusiveBarberNotice');
            if (!noticeEl) {
                noticeEl = document.createElement('div');
                noticeEl.id = 'exclusiveBarberNotice';
                grid.parentNode.insertBefore(noticeEl, grid);
            }

            if (isExclusive) {
                noticeEl.innerHTML = `
                    <div style="background: rgba(192, 160, 98, 0.12); border: 1.5px solid var(--color-gold, #C0A062); border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; color: #FFFFFF; font-size: 0.92rem; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.3rem;">⭐</span>
                        <div>
                            <span style="color: var(--color-gold, #C0A062); font-weight: 900; text-transform: uppercase;">Servicio Exclusivo</span>: Este corte está asignado únicamente a <strong>${exclusiveBarberName || 'Mateo Álvaro'}</strong>.
                        </div>
                    </div>
                `;
                noticeEl.style.display = 'block';
            } else {
                noticeEl.innerHTML = '';
                noticeEl.style.display = 'none';
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
                    let avatarHtml = '';
                    if (b.foto_perfil && b.foto_perfil.length > 5) {
                        avatarHtml = `
                        <div class="barber-avatar" style="width:60px; height:60px; border-radius:50%; background-image:url('${b.foto_perfil}'); background-size:cover; background-position:center; margin-bottom:10px; border:2px solid var(--color-gold);"></div>`;
                    } else {
                        avatarHtml = `
                        <div class="barber-avatar" style="width:60px; height:60px; border-radius:50%; background:#333; color:white; display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:10px; border:2px solid var(--color-gold);">
                            ${b.nombre.charAt(0)}
                        </div>`;
                    }

                    el.innerHTML = `
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            ${avatarHtml}
                            <h3 style="display:flex; align-items:center; gap:6px;">
                                ${b.nombre} ${isExclusive ? '<span style="color:var(--color-gold, #C0A062); font-size:0.8rem;">★</span>' : ''}
                            </h3>
                            <p style="font-size:0.9rem; color:#888; margin-bottom:0;">${b.sucursal_nombre || 'Kortzen'}</p>
                            ${isExclusive ? '<span style="display:inline-block; margin-top:6px; font-size:0.75rem; background:var(--color-gold, #C0A062); color:#000; font-weight:800; padding:2px 8px; border-radius:10px;">EXCLUSIVO</span>' : ''}
                        </div>
                    `;
                    grid.appendChild(el);
                });
            } else {
                grid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; color: #111111; padding: 2.5rem 1rem; background: #F9F9F9; border: 1px dashed #CCCCCC; border-radius: 12px; box-sizing: border-box;">
                        <p style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.3rem;">No hay barberos disponibles en esta sucursal</p>
                        <p style="font-size: 0.82rem; color: #666666; margin: 0;">Selecciona otra sucursal en el menú superior para agendar con barberos disponibles.</p>
                    </div>
                `;
            }
        }

        let currentLoadedSlots = [];

        async function loadSlots(date) {
            if (!bookingData.barberId) return;

            const grid = document.getElementById('slotsGrid');
            grid.innerHTML = '<p style="color:#888; grid-column:1/-1; text-align:center;">Cargando horarios...</p>';

            try {
                const url = `api/get_disponibilidad.php?fecha=${date}&barbero_id=${bookingData.barberId}&servicio_id=${bookingData.serviceId}`;
                const response = await fetch(url);
                const resData = await response.json();

                let slots = Array.isArray(resData) ? resData : (resData.slots || []);
                currentLoadedSlots = slots;

                grid.innerHTML = '';

                if (slots.length === 0) {
                    let sugerenciaHtml = '';
                    if (resData.sugerencia_proxima_fecha && resData.sugerencia_legible) {
                        sugerenciaHtml = `
                            <div style="margin-top:10px; font-size:0.85rem; color:#111; font-weight:700;">
                                💡 Próxima fecha disponible recomendada: <strong>${resData.sugerencia_legible}</strong>
                                <br>
                                <button type="button" onclick="seleccionarFechaSugerida('${resData.sugerencia_proxima_fecha}')" style="margin-top:8px; padding:6px 14px; background:#111; color:#fff; border:none; border-radius:8px; font-size:0.8rem; font-weight:800; cursor:pointer;">
                                    Ver ${resData.sugerencia_legible}
                                </button>
                            </div>
                        `;
                    }
                    grid.innerHTML = `<div style="grid-column:1/-1; color:#777; text-align:center; padding:15px; background:#fafafa; border-radius:10px; border:1px solid #eee;">
                        No hay horarios disponibles para este día.${sugerenciaHtml}
                    </div>`;
                    return;
                }

                renderSlotsGrid(slots);

            } catch (e) {
                grid.innerHTML = '<p style="color:red; grid-column:1/-1; text-align:center;">Error al cargar horarios</p>';
            }
        }

        function renderSlotsGrid(slots) {
            const grid = document.getElementById('slotsGrid');
            grid.innerHTML = '';
            if (slots.length === 0) {
                grid.innerHTML = '<p style="grid-column:1/-1; color:#777; text-align:center;">No hay horarios en este filtro.</p>';
                return;
            }
            slots.forEach(time => {
                const el = document.createElement('div');
                el.className = 'time-slot';
                el.textContent = time;
                el.onclick = () => selectTime(time, el);
                grid.appendChild(el);
            });
        }

        function filtrarHorarios(filtro, btnEl) {
            document.querySelectorAll('.slot-filter-btn').forEach(b => {
                b.style.background = '#fff';
                b.style.color = '#111';
                b.style.borderColor = '#ddd';
            });
            if (btnEl) {
                btnEl.style.background = '#111';
                btnEl.style.color = '#fff';
                btnEl.style.borderColor = '#111';
            }

            if (!currentLoadedSlots || currentLoadedSlots.length === 0) return;

            let filtrados = currentLoadedSlots;
            if (filtro === 'manana') {
                filtrados = currentLoadedSlots.filter(t => {
                    const hour = parseInt(t.split(':')[0]);
                    return hour < 14;
                });
            } else if (filtro === 'tarde') {
                filtrados = currentLoadedSlots.filter(t => {
                    const hour = parseInt(t.split(':')[0]);
                    return hour >= 14;
                });
            }
            renderSlotsGrid(filtrados);
        }

        function seleccionarFechaSugerida(fechaStr) {
            const picker = document.getElementById('datePicker')._flatpickr;
            if (picker) {
                picker.setDate(fechaStr, true);
            }
        }

        async function loadClientProfile() {
            try {
                const response = await fetch('api/get_client_profile.php');
                const res = await response.json();

                if (res.success && res.cliente) {
                    document.getElementById('clientName').value = res.cliente.nombre;
                    document.getElementById('clientEmail').value = res.cliente.email;
                    if (res.cliente.telefono && res.cliente.telefono.length > 6) {
                        document.getElementById('clientPhone').value = res.cliente.telefono;
                        bookingData.phone = res.cliente.telefono;
                        hasExistingPhone = true; // Will skip step 4
                    }
                }
            } catch (e) {
                console.error("Error loading profile", e);
            }
        }

        // --- Actions ---

        function selectService(id, name, price, el, barberoId = null, barberoNombre = null) {
            bookingData.serviceId = id;
            bookingData.serviceName = name;
            bookingData.servicePrice = price;
            bookingData.serviceBarberId = barberoId;
            bookingData.serviceBarberNombre = barberoNombre;

            document.querySelectorAll('#servicesGrid .option-card').forEach(c => c.classList.remove('selected'));
            if (el) el.classList.add('selected');

            // Determinar si el corte es exclusivo de un barbero (ej: Mateo Álvaro)
            const isMateoService = name.toLowerCase().includes('mateo');
            let targetBarber = null;

            if (barberoId) {
                targetBarber = allBarbersList.find(b => b.id == barberoId);
            }
            if (!targetBarber && isMateoService) {
                targetBarber = allBarbersList.find(b => 
                    b.nombre.toLowerCase().includes('mateo') || 
                    b.nombre.toLowerCase().includes('alvaro') ||
                    (b.email && b.email.toLowerCase().includes('mateo'))
                );
            }

            if (targetBarber) {
                // Servicio Exclusivo: Filtrar la lista de barberos para mostrar ÚNICAMENTE a ese barbero
                renderBarbersList([targetBarber], true, targetBarber.nombre);
                
                // Preseleccionar al barbero automáticamente
                bookingData.barberId = targetBarber.id;
                bookingData.barberName = targetBarber.nombre;

                // Marcar tarjeta seleccionada si está en DOM
                const bCard = document.querySelector(`#barbersGrid .option-card[data-barber-id="${targetBarber.id}"]`);
                if (bCard) bCard.classList.add('selected');

                updateNavButtons();

                // Auto-avanzar directamente al Paso 3 (Fecha y Hora)
                setTimeout(() => {
                    currentStep = 3;
                    showStep(currentStep);
                }, 350);
            } else {
                // Servicio General: Mostrar todos los barberos del local
                bookingData.barberId = null;
                bookingData.barberName = null;
                renderBarbersList(allBarbersList, false);

                updateNavButtons();

                // Auto-avanzar al Paso 2 (Elegir Barbero)
                setTimeout(() => {
                    currentStep = 2;
                    showStep(currentStep);
                }, 300);
            }
        }

        function selectBarber(id, name, el) {
            bookingData.barberId = id;
            bookingData.barberName = name;

            document.querySelectorAll('#barbersGrid .option-card').forEach(c => c.classList.remove('selected'));
            if (el) el.classList.add('selected');
            updateNavButtons();

            // Auto-advance to Step 3 (Date)
            setTimeout(() => {
                currentStep = 3;
                showStep(currentStep);
            }, 300);
        }

        function selectTime(time, el) {
            bookingData.time = time;

            document.querySelectorAll('.time-slot').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            updateNavButtons();

            // Auto-advance to Step 4 (Datos de Contacto & Código de Referido)
            setTimeout(() => {
                currentStep = 4;
                showStep(currentStep);
            }, 300);
        }

        function initDatePicker() {
            flatpickr("#datePicker", {
                locale: "es",
                minDate: "today",
                maxDate: new Date().fp_incr(30), // 30 días adelante
                disableMobile: true, // 100% PREVIENE ZOOM Y PICKER NATIVO EN MÓVILES
                disable: [
                    function (date) {
                        return (date.getDay() === 0); // Deshabilitar domingos si cerrados
                    }
                ],
                onChange: function (selectedDates, dateStr, instance) {
                    bookingData.date = dateStr;
                    bookingData.time = null; // Reset time
                    loadSlots(dateStr);
                    updateNavButtons();
                }
            });
        }

        // Input changed listener
        document.getElementById('clientPhone').addEventListener('input', (e) => {
            bookingData.phone = e.target.value.trim();
            updateNavButtons();
        });

        // --- Navigation & Confirmation ---

        document.getElementById('btnPrev').addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        });

        document.getElementById('btnNext').addEventListener('click', () => {
            if (currentStep < 5) {
                // Validation Step 4
                if (currentStep === 4) {
                    const phone = document.getElementById('clientPhone').value.trim();
                    if (!phone || phone.length < 7) {
                        alert('Por favor ingresa un número de teléfono válido.');
                        return;
                    }
                    bookingData.phone = phone;
                }

                currentStep++;
                showStep(currentStep);
            }
        });

        document.getElementById('btnConfirmBooking').addEventListener('click', async () => {
            const btn = document.getElementById('btnConfirmBooking');
            btn.disabled = true;
            btn.textContent = "Procesando...";

            try {
                const formData = new FormData();
                const selectedBranchId = (window.KortzenBranches && window.KortzenBranches.getSelectedBranchId) ? window.KortzenBranches.getSelectedBranchId() : (localStorage.getItem('kortzen_selected_branch') || 1);
                formData.append('sucursal_id', selectedBranchId);
                formData.append('servicio_id', bookingData.serviceId);
                formData.append('barbero_id', bookingData.barberId);
                formData.append('fecha', bookingData.date);
                formData.append('hora', bookingData.time);
                formData.append('telefono', bookingData.phone);
                
                // Incluir preferencias guardadas del cuestionario de estilo si existen
                const prefsRaw = localStorage.getItem('kortzen_client_preferences');
                if (prefsRaw) {
                    try {
                        const prefs = JSON.parse(prefsRaw);
                        if (prefs.estilo) formData.append('estilo_buscado', prefs.estilo);
                        if (prefs.ambiente) formData.append('ambiente_preferido', prefs.ambiente);
                        if (prefs.bebida) formData.append('bebida_preferida', prefs.bebida);
                    } catch(e) {}
                }
                
                if (referralCodeApplied && referralCodeApplied.trim() !== '') {
                    formData.append('codigo_referido', referralCodeApplied.trim());
                }

                const urlParams = new URLSearchParams(window.location.search);
                const reagendarId = urlParams.get('reagendar_id');
                if (reagendarId) {
                    formData.append('reagendar_id', reagendarId);
                }

                const req = await fetch('api/crear_cita_cliente.php', {
                    method: 'POST',
                    body: formData
                });

                const responseText = await req.text();
                let res;
                try {
                    res = JSON.parse(responseText);
                } catch (jsonErr) {
                    console.error("Server output error:", responseText);
                    throw new Error("Respuesta inesperada del servidor.");
                }

                if (res.success) {
                    // Success UI
                    const branch = (window.KortzenBranches && window.KortzenBranches.getSelectedBranch) ? window.KortzenBranches.getSelectedBranch() : null;
                    const branchName = branch ? branch.name : (localStorage.getItem('kortzen_selected_branch_name') || 'KORTZEN Llano Chico');
                    const gCalTitle = encodeURIComponent(`Cita en ${branchName} - ${bookingData.serviceName}`);
                    const gCalLoc = encodeURIComponent(`${branchName}, Quito`);
                    const gCalDetails = encodeURIComponent(`Cita con ${bookingData.barberName} el ${bookingData.date} a las ${bookingData.time}`);
                    const dateClean = bookingData.date.replace(/-/g, '');
                    const timeClean = bookingData.time.replace(':', '');
                    const gCalUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${gCalTitle}&dates=${dateClean}T${timeClean}00/${dateClean}T${timeClean}00&details=${gCalDetails}&location=${gCalLoc}`;

                    document.querySelector('.booking-container').innerHTML = `
                    <div style="text-align:center; padding-top:40px; padding-bottom:60px;">
                        <div style="width:70px; height:70px; border-radius:50%; background:#111111; color:#FFFFFF; font-size:2.5rem; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto; font-weight:900;">✓</div>
                        <h1 style="color:#FFFFFF; font-size:1.6rem; font-weight:900; margin-bottom:10px;">¡Reserva Exitosa!</h1>
                        <p style="color:#AAAAAA; font-size:0.95rem; margin-bottom:25px;">Tu cita ha sido ${reagendarId ? 'reagendada' : 'agendada'} correctamente.</p>
                        
                        <div style="max-width:380px; margin:0 auto 25px auto; background:#FFFFFF; color:#111111; padding:20px; border-radius:16px; border:1px solid #EAEAEA; text-align:left;">
                            <div style="margin-bottom:12px;">
                                <span style="font-size:0.72rem; color:#777; font-weight:800; text-transform:uppercase;">SUCURSAL</span>
                                <div style="font-size:1.05rem; font-weight:800; color:#111111;">${branchName}</div>
                            </div>
                            <div style="margin-bottom:12px;">
                                <span style="font-size:0.72rem; color:#777; font-weight:800; text-transform:uppercase;">SERVICIO</span>
                                <div style="font-size:1.05rem; font-weight:800;">${bookingData.serviceName}</div>
                            </div>
                            <div style="margin-bottom:12px;">
                                <span style="font-size:0.72rem; color:#777; font-weight:800; text-transform:uppercase;">BARBERO</span>
                                <div style="font-size:1.05rem; font-weight:800;">${bookingData.barberName}</div>
                            </div>
                            <div>
                                <span style="font-size:0.72rem; color:#777; font-weight:800; text-transform:uppercase;">FECHA Y HORA</span>
                                <div style="font-size:1.05rem; font-weight:900; color:#111111;">${bookingData.date} a las ${bookingData.time}</div>
                            </div>
                        </div>

                        <!-- Botón Añadir a Google Calendar -->
                        <div style="max-width:380px; margin:0 auto 20px auto;">
                            <a href="${gCalUrl}" target="_blank" style="display:flex; align-items:center; justify-content:center; gap:10px; background:#4285F4; color:#FFFFFF; font-weight:800; font-size:0.88rem; padding:14px; border-radius:12px; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; box-sizing:border-box;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <span>Añadir a mi Google Calendar</span>
                            </a>
                        </div>

                        <a href="cliente-dashboard.php" class="btn btn-next" style="display:inline-block; max-width:380px; width:100%; text-decoration:none; background:#FFFFFF; color:#111111; font-weight:800; padding:15px; border-radius:12px; text-transform:uppercase; letter-spacing:1px; box-sizing:border-box;">Volver a mi Cuenta</a>
                        <br><br>
                        <a href="https://www.google.com/maps/place/KORTZEN/@-0.1352812,-78.4460419,17z/data=!3m1!4b1!4m6!3m5!1s0x91d58fc52de96153:0x35f5708deeee0cf7!8m2!3d-0.1352812!4d-78.443467!16s%2Fg%2F11yck29m8p?entry=ttu" target="_blank" style="color:#FFFFFF; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-size:0.9rem; font-weight:600;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <span>Califícanos en Google</span>
                        </a>
                    </div>
                `;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    alert('Error: ' + res.message);
                    btn.disabled = false;
                    btn.textContent = "CONFIRMAR RESERVA";
                }

            } catch (e) {
                console.error(e);
                alert('No se pudo procesar la reserva. Por favor intenta de nuevo.');
                btn.disabled = false;
                btn.textContent = "CONFIRMAR RESERVA";
            }
        });

        let referralCodeApplied = '';
        let appliedDiscountAmount = 0.0;
        let appliedDiscountType = 'fixed';
        let appliedDiscountPercentage = 0.0;

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
                    msgDiv.innerHTML = `<strong>✅ ${data.message}</strong>`;
                    input.disabled = true;
                    input.style.background = '#e8f5e9';
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

            updateNavButtons();

            if (step === 5) {
                updateSummary();
            }

            // Scroll suavemente al inicio del contenedor
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

            // Logic next button
            let canNext = false;
            if (currentStep === 1 && bookingData.serviceId) canNext = true;
            if (currentStep === 2 && bookingData.barberId) canNext = true;
            if (currentStep === 3 && bookingData.date && bookingData.time) canNext = true;
            if (currentStep === 4 && bookingData.phone && bookingData.phone.length > 6) canNext = true;

            if (currentStep === 5) {
                next.classList.add('hidden'); // Hide next on last step
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
            document.getElementById('confirmBarber').textContent = bookingData.barberName;
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
                        <div style="font-size: 1.3rem; font-weight: 900; color: #28a745; margin-bottom: 4px;">$${finalPrice.toFixed(2)}</div>
                        <div style="font-size: 0.8rem; font-weight: 800; color: #28a745; line-height: 1.3;">
                            <div>-$${appliedDiscountAmount.toFixed(2)}</div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #28a745;">${labelDesc}</div>
                        </div>
                    `;
                } else {
                    priceContainer.textContent = `$${rawPrice.toFixed(2)}`;
                }
            }
        }
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
</body>
</html>
