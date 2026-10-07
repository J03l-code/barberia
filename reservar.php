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

        /* 1. DIAS DISPONIBLES / SELECCIONABLES */
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

        /* 2. DIA SELECCIONADO */
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.selected:focus, .flatpickr-day.selected:hover {
            background: #C0A062 !important;
            border: 2px solid #FFFFFF !important;
            color: #000000 !important;
            font-weight: 800 !important;
            border-radius: 50% !important;
            box-shadow: 0 0 15px rgba(192, 160, 98, 0.8) !important;
            transform: scale(1.1) !important;
        }

        /* 3. DIAS NO DISPONIBLES / PASADOS */
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
            margin-bottom: 8px;
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

        /* Cards Grid */
        .grid-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        /* Service & Barber Card */
        .option-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: center;
            position: relative;
            box-sizing: border-box;
        }

        .option-card:hover {
            border-color: #FFFFFF;
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
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
            margin: 0 0 8px 0;
            font-size: 1.05rem;
        }

        .option-card p {
            font-size: 0.88rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .price {
            display: block;
            margin-top: 12px;
            font-weight: bold;
            color: var(--gold);
            font-size: 1.15rem;
        }

        /* Any Barber Card */
        .any-barber-card {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, rgba(192, 160, 98, 0.15) 0%, rgba(20, 20, 22, 0.9) 100%);
            border: 1.5px dashed var(--gold);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-bottom: 8px;
            text-align: left;
        }

        .any-barber-card:hover {
            border-color: #FFFFFF;
            background: linear-gradient(135deg, rgba(192, 160, 98, 0.25) 0%, rgba(30, 30, 35, 1) 100%);
            transform: scale(1.01);
        }

        .any-barber-card.selected {
            border: 2.5px solid #FFFFFF !important;
            background: #181818 !important;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.35);
        }

        /* Barber Avatar */
        .barber-avatar {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #333;
            margin: 0 auto 12px;
            background-size: cover;
            background-position: center;
            border: 2px solid var(--gold);
        }

        /* Proxima Disponibilidad Banner */
        .quick-slot-banner {
            background: linear-gradient(135deg, #18181b 0%, #111113 100%);
            border: 1.5px solid var(--gold);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(192, 160, 98, 0.15);
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
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            font-size: 0.92rem;
            font-weight: 700;
            color: #FFFFFF;
            transition: all 0.2s;
        }

        .time-slot:hover:not(.disabled) {
            border-color: #FFFFFF;
            background: rgba(255, 255, 255, 0.08);
        }

        .time-slot.selected {
            background: #FFFFFF !important;
            color: #111111 !important;
            border: 2px solid #FFFFFF !important;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
            font-weight: 900 !important;
        }

        .time-slot.disabled {
            opacity: 0.25;
            cursor: not-allowed;
            background: #111;
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
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            box-sizing: border-box;
        }

        .btn {
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.88rem;
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
            font-weight: 800;
        }

        .btn-next:disabled {
            background: #333333;
            color: #777777;
            cursor: not-allowed;
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
        <div onclick="window.createBranchSelectorModal ? window.createBranchSelectorModal() : (window.KortzenBranches && window.KortzenBranches.showSelector())" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 10px 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s ease;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.1rem;">📍</span>
                <div>
                    <div style="font-size: 0.68rem; font-weight: 800; color: var(--gold); text-transform: uppercase; letter-spacing: 0.5px;">SUCURSAL SELECCIONADA</div>
                    <div style="font-size: 0.92rem; font-weight: 800; color: #FFFFFF;" data-branch-dynamic="name">KORTZEN Llano Chico</div>
                </div>
            </div>
            <span style="background: #FFFFFF; color: #111111; font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 6px; text-transform: uppercase;">
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
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #FFFFFF;">1. Elige tu Servicio</h2>
            <div class="grid-options" id="servicesGrid">
                <p style="color:#888; grid-column:1/-1; text-align:center;">Cargando servicios...</p>
            </div>
        </div>

        <!-- Step 2: Barbers -->
        <div class="wizard-step" id="step2">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #FFFFFF;">2. Elige tu Barbero</h2>
            
            <!-- Opción: Cualquier Barbero Disponible -->
            <div id="anyBarberOption" class="any-barber-card" onclick="selectAnyBarber(this)">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--gold); color: #000; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 900; flex-shrink: 0;">
                        ⚡
                    </div>
                    <div>
                        <div style="font-weight: 900; font-size: 0.95rem; color: #FFFFFF;">Cualquier barbero disponible</div>
                        <div style="font-size: 0.8rem; color: var(--gold);">Quiero la hora más cercana / Mayor disponibilidad</div>
                    </div>
                </div>
                <div style="font-size: 0.8rem; font-weight: 800; color: #FFFFFF; background: rgba(255,255,255,0.1); padding: 6px 12px; border-radius: 8px;">
                    Elegir ➔
                </div>
            </div>

            <div class="grid-options" id="barbersGrid">
                <!-- Cargado vía JS -->
            </div>
        </div>

        <!-- Step 3: Date & Time -->
        <div class="wizard-step" id="step3">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #FFFFFF;">3. Elige Fecha y Hora</h2>
            
            <!-- Quick Earliest Slot Banner -->
            <div id="quickSlotBanner" class="quick-slot-banner" style="display: none;">
                <div>
                    <div style="font-size: 0.72rem; font-weight: 800; color: var(--gold); text-transform: uppercase; letter-spacing: 0.5px;">⚡ PRÓXIMA DISPONIBILIDAD</div>
                    <div id="quickSlotLabel" style="font-size: 0.95rem; font-weight: 900; color: #FFFFFF; margin-top: 2px;">Hoy a las 10:00</div>
                </div>
                <button type="button" id="btnQuickBook" onclick="bookQuickSlot()" style="background: var(--gold); color: #000; border: none; border-radius: 8px; padding: 8px 14px; font-size: 0.8rem; font-weight: 900; cursor: pointer; text-transform: uppercase;">
                    Reservar ➔
                </button>
            </div>

            <div class="datetime-wrapper">
                <div style="background: #161618; border: 1.5px solid var(--gold); border-radius: 14px; padding: 18px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);">
                    <div style="margin-bottom:12px;">
                        <label style="display:flex; align-items:center; gap:8px; color:#FFFFFF; font-weight:800; font-size:1.02rem; margin:0;">
                            <i class="fas fa-calendar-alt" style="color:var(--gold); font-size:1.15rem;"></i> Selecciona el día de tu cita
                        </label>
                    </div>
                    <div style="position: relative; width: 100%;">
                        <i class="fas fa-calendar-day" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--gold); font-size: 1.15rem; pointer-events: none; z-index: 2;"></i>
                        <input type="text" id="datePicker" placeholder="Haz clic aquí para abrir el calendario..." readonly
                            style="width: 100%; padding: 16px 40px 16px 48px; background: #08080A; border: 1px solid var(--gold); color: #FFFFFF; font-weight: 800; font-size: 16px !important; border-radius: 10px; cursor: pointer; box-shadow: 0 0 12px rgba(192, 160, 98, 0.2); transition: all 0.3s ease; box-sizing: border-box;">
                        <i class="fas fa-chevron-down" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--gold); font-size: 1rem; pointer-events: none; z-index: 2;"></i>
                    </div>
                </div>

                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <label style="color:var(--text-secondary); font-size: 0.9rem; font-weight: 700; margin:0;">Horarios Disponibles</label>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="slot-filter-btn active" onclick="filtrarHorarios('all', this)" style="padding:6px 12px; border-radius:20px; border:1px solid #111; background:#FFFFFF; color:#111; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                TODOS
                            </button>
                            <button type="button" class="slot-filter-btn" onclick="filtrarHorarios('manana', this)" style="padding:6px 12px; border-radius:20px; border:1px solid #333; background:#1C1C1E; color:#FFF; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                MAÑANA
                            </button>
                            <button type="button" class="slot-filter-btn" onclick="filtrarHorarios('tarde', this)" style="padding:6px 12px; border-radius:20px; border:1px solid #333; background:#1C1C1E; color:#FFF; font-size:0.72rem; font-weight:800; cursor:pointer;">
                                TARDE
                            </button>
                        </div>
                    </div>
                    <div id="slotsGrid" class="slots-grid">
                        <p style="color:#666; grid-column: 1/-1; text-align:center; padding: 20px 0;">Selecciona una fecha arriba para ver los turnos disponibles</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Contact & Personal Details -->
        <div class="wizard-step" id="step4">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 800; color: #FFFFFF;">4. Datos de Contacto</h2>
            <div style="background:var(--card-bg); padding:24px 20px; border-radius:14px; border:1px solid rgba(255,255,255,0.1); max-width: 480px; margin: 0 auto; box-sizing: border-box;">
                <p style="color:var(--text-secondary); margin-bottom:18px; font-size:0.88rem;">
                    Ingresa tus datos para recibir la confirmación inmediata y recordatorios de tu cita.
                </p>

                <div style="margin-bottom: 16px;">
                    <label style="display:block; margin-bottom:6px; color:#FFFFFF; font-size: 0.88rem; font-weight:700;">Nombre completo *</label>
                    <input type="text" id="clientName" placeholder="Ej: Carlos Andrade" value="<?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>"
                        style="width: 100%; padding: 13px; background: #1C1C1E; border: 1px solid rgba(255,255,255,0.15); color: #FFFFFF; border-radius: 8px; font-weight: 700; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display:block; margin-bottom:6px; color:var(--gold); font-size: 0.88rem; font-weight:800;">WhatsApp / Celular (+593) *</label>
                    <input type="tel" id="clientPhone" placeholder="0991234567" value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>"
                        style="width: 100%; padding: 13px; background: #1C1C1E; border: 1.5px solid var(--gold); color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.05rem; box-sizing: border-box;">
                    <small style="color: #888888; font-size: 0.76rem; margin-top: 4px; display: block;">* Te enviaremos el recordatorio a este número.</small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:6px; color:#FFFFFF; font-size: 0.88rem; font-weight:700;">Correo Electrónico (opcional)</label>
                    <input type="email" id="clientEmail" placeholder="ejemplo@gmail.com" value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                        style="width: 100%; padding: 13px; background: #1C1C1E; border: 1px solid rgba(255,255,255,0.15); color: #FFFFFF; border-radius: 8px; font-weight: 700; box-sizing: border-box;">
                </div>

                <!-- Código de Referido -->
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px dashed rgba(255,255,255,0.15);">
                    <label style="display:flex; align-items:center; gap:6px; margin-bottom:8px; color:var(--gold); font-weight:800; font-size: 0.88rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        <span>¿Tienes un Código Promocional o de Referido?</span>
                    </label>
                    <div style="display:flex; gap:8px; width:100%; box-sizing: border-box;">
                        <input type="text" id="referralCodeInput" placeholder="Ej: JOEL888" 
                               style="flex:1; min-width:0; padding:12px; background:#1C1C1E; border:1px solid rgba(255,255,255,0.15); border-radius:8px; text-transform:uppercase; font-weight:800; font-size:0.95rem; color:#FFFFFF; box-sizing: border-box;">
                        <button type="button" onclick="aplicarCodigoReferidoReserva()" 
                                style="padding:12px 18px; background:var(--gold); color:#111111; border:none; border-radius:8px; font-weight:900; font-size:0.85rem; cursor:pointer; text-transform:uppercase; white-space:nowrap; flex-shrink:0;">
                            Aplicar
                        </button>
                    </div>
                    <div id="referralCodeMessage" style="margin-top:8px; font-size:0.85rem;"></div>
                </div>
            </div>
        </div>

        <!-- Step 5: Confirm -->
        <div class="wizard-step" id="step5">
            <h2 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 900; color: #FFFFFF; text-align: center; letter-spacing: 1px;">5. Confirma tu Reserva</h2>
            
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
            name: null,
            phone: null,
            email: null
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

        document.addEventListener('DOMContentLoaded', async () => {
            const branchId = localStorage.getItem('kortzen_selected_branch') || 1;

            await loadBarbers(branchId);
            await loadServices(branchId);
            initDatePicker();
            updateNavButtons();

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
            const urlParams = new URLSearchParams(window.location.search);
            const urlServicioId = urlParams.get('servicio_id');
            if (urlServicioId && allServicesList.length > 0) {
                const sObj = allServicesList.find(s => s.id == urlServicioId);
                if (sObj) {
                    const cardEl = document.querySelector(`#servicesGrid .option-card[data-service-id="${sObj.id}"]`);
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

        // --- Services Loading ---
        async function loadServices(branchId = 1) {
            const grid = document.getElementById('servicesGrid');

            try {
                const response = await fetch(`api/get_catalog.php?sucursal_id=${branchId}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
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
                    const cat = s.categoria || 'Servicios';
                    if (!servicesByCategory[cat]) {
                        servicesByCategory[cat] = [];
                    }
                    servicesByCategory[cat].push(s);
                });

                grid.innerHTML = '';

                for (const [category, services] of Object.entries(servicesByCategory)) {
                    const catHeader = document.createElement('h3');
                    catHeader.style.cssText = 'grid-column: 1/-1; margin: 15px 0 6px 0; color: var(--gold); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; text-transform: uppercase; font-size: 0.95rem; letter-spacing: 0.5px;';
                    catHeader.textContent = category;
                    grid.appendChild(catHeader);

                    services.forEach(s => {
                        const el = document.createElement('div');
                        el.className = 'option-card';
                        el.setAttribute('data-service-id', s.id);
                        if (bookingData.serviceId && bookingData.serviceId == s.id) {
                            el.classList.add('selected');
                        }

                        const thumb = s.foto_url || s.imagen_url || '/assets/images/service-corte-mateo.png';
                        let imageHtml = `<div class="service-image" style="width:100%; height:130px; background-image:url('${thumb}'); background-size:cover; background-position:center; border-radius:8px; margin-bottom:10px;"></div>`;

                        let exclusiveBadgeHtml = '';
                        const isMateo = s.nombre.toLowerCase().includes('mateo');
                        if (s.barbero_nombre || isMateo) {
                            const bName = s.barbero_nombre || 'Mateo Álvaro';
                            exclusiveBadgeHtml = `<div style="font-size:0.75rem; color:var(--gold); font-weight:800; margin-top:2px;">★ Solo con ${bName}</div>`;
                        }

                        let descHtml = s.descripcion ? `<p style="font-size:0.8rem; color:#888888; margin:4px 0 6px 0; line-height:1.3;">${s.descripcion}</p>` : '';

                        let incluyeHtml = '';
                        if (s.que_incluye && s.que_incluye.trim() !== '') {
                            const lines = s.que_incluye.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
                            const renderedList = lines.map(item => {
                                const clean = item.replace(/^[•\-\*✓\s]+/, '').trim();
                                return `<div style="display:flex; align-items:flex-start; gap:4px; margin-bottom:2px;"><span style="color:var(--gold); font-size:0.72rem; flex-shrink:0;">✓</span><span>${clean}</span></div>`;
                            }).join('');
                            
                            incluyeHtml = `
                                <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-left:2.5px solid var(--gold); border-radius:4px; padding:6px 8px; margin:6px 0 8px 0; text-align:left; font-size:0.74rem; color:#CCCCCC; line-height:1.3;">
                                    <div style="font-size:0.68rem; font-weight:800; text-transform:uppercase; color:var(--gold); margin-bottom:3px; letter-spacing:0.4px;">Incluye:</div>
                                    ${renderedList}
                                </div>
                            `;
                        }

                        el.onclick = () => selectService(s.id, s.nombre, s.precio, s.duracion_minutos || 40, el, s.barbero_id, s.barbero_nombre, s.barberos_ids);

                        el.innerHTML = `
                            ${imageHtml}
                            <h3 style="margin:5px 0 2px 0;">${s.nombre}</h3>
                            <p style="font-size:0.82rem; color:#777; margin-bottom:2px;">⏱️ ${s.duracion_minutos || 40} min</p>
                            ${exclusiveBadgeHtml}
                            ${descHtml}
                            ${incluyeHtml}
                            <span class="price" style="font-size:1.1rem; margin-top:4px; display:block; font-weight:800; color:#FFFFFF;">$${parseFloat(s.precio).toFixed(0)}</span>
                        `;
                        grid.appendChild(el);
                    });
                }
            } catch (e) {
                console.error('Error cargando servicios:', e);
                grid.innerHTML = `<p style="color:#cc0000; grid-column:1/-1;">Error al cargar servicios.</p>`;
            }
        }

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
                                ${b.nombre} ${isExclusive ? '<span style="color:var(--gold); font-size:0.8rem;">★</span>' : ''}
                            </h3>
                            <p style="font-size:0.82rem; color:#888; margin-bottom:0;">${b.sucursal_nombre || 'Kortzen Master'}</p>
                            <div style="margin-top:6px; font-size:0.75rem; color:var(--gold); font-weight:800;">★★★★★ 5.0</div>
                        </div>
                    `;
                    grid.appendChild(el);
                });
            } else {
                grid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; color: #FFFFFF; padding: 2rem 1rem; background: #18181A; border: 1px dashed rgba(255,255,255,0.2); border-radius: 12px;">
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

            document.querySelectorAll('#servicesGrid .option-card').forEach(c => c.classList.remove('selected'));
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
                    grid.innerHTML = `<div style="grid-column:1/-1; color:#AAA; text-align:center; padding:16px; background:#1C1C1E; border-radius:10px; border:1px solid rgba(255,255,255,0.08);">
                        No hay turnos libres para esta fecha. Por favor selecciona otro día en el calendario.
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
                b.style.background = '#1C1C1E';
                b.style.color = '#FFF';
                b.style.borderColor = '#333';
            });
            if (btnEl) {
                btnEl.style.background = '#FFFFFF';
                btnEl.style.color = '#111111';
                btnEl.style.borderColor = '#FFFFFF';
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
                const phone = document.getElementById('clientPhone').value.trim();
                const name = document.getElementById('clientName').value.trim();
                if (phone.length >= 7 && name.length >= 2) canNext = true;
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
                    const phone = document.getElementById('clientPhone').value.trim();
                    const name = document.getElementById('clientName').value.trim();
                    const email = document.getElementById('clientEmail').value.trim();
                    if (!name || name.length < 2) {
                        alert('Por favor ingresa tu nombre completo.');
                        return;
                    }
                    if (!phone || phone.length < 7) {
                        alert('Por favor ingresa un número de teléfono/WhatsApp válido.');
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
                        <div style="width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg, #10B981, #059669); color:#FFFFFF; font-size:2.2rem; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto; font-weight:900; box-shadow:0 0 25px rgba(16,185,129,0.5);">✓</div>
                        <h1 style="color:#FFFFFF; font-size:1.6rem; font-weight:900; margin-bottom:6px;">¡Cita Confirmada!</h1>
                        <p style="color:#AAAAAA; font-size:0.92rem; margin-bottom:22px;">Hemos reservado tu turno con éxito.</p>
                        
                        <div style="max-width:480px; margin:0 auto 20px auto; background:#FFFFFF; color:#111111; padding:20px 18px; border-radius:16px; border:1px solid #EAEAEA; text-align:left; box-shadow:0 8px 25px rgba(0,0,0,0.3);">
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
                        <div style="max-width:480px; margin:0 auto 20px auto; background:#18181B; border:1.5px solid var(--gold); padding:18px 16px; border-radius:14px; text-align:center;">
                            <div style="font-size:0.92rem; font-weight:800; color:#FFFFFF; margin-bottom:6px;">¿Deseas guardar tus datos para próximas reservas?</div>
                            <p style="font-size:0.78rem; color:#A3A3A3; margin-bottom:14px;">Vincula tu cuenta con Google en un solo clic para ver tu historial y acumular puntos.</p>
                            <a href="${googleAuthUrl}" style="display:inline-flex; align-items:center; justify-content:center; gap:10px; background:#FFFFFF; color:#111111; font-weight:800; font-size:0.88rem; padding:12px 18px; border-radius:10px; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; box-shadow:0 4px 15px rgba(255,255,255,0.2);">
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
                            <a href="reservar.php" style="display:flex; align-items:center; justify-content:center; gap:10px; background:linear-gradient(135deg, var(--gold) 0%, #D4AF37 50%, #B38F4D 100%); color:#000000; font-weight:900; font-size:0.88rem; padding:13px 18px; border-radius:12px; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; box-sizing:border-box;">
                                <span>+ Agendar Otra Cita / Acompañante</span>
                            </a>
                        </div>

                        <a href="mis-citas.php" class="btn btn-next" style="display:inline-block; max-width:480px; width:100%; text-decoration:none; background:#FFFFFF; color:#111111; font-weight:800; padding:14px; border-radius:12px; text-transform:uppercase; letter-spacing:1px; box-sizing:border-box;">Ver Mis Citas</a>
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
</body>
</html>
