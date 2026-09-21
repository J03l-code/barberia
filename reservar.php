<?php
require_once 'config.php';
$pageTitle = 'Reservar Cita | KORTZEN Barbería Quito';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Cita | KORTZEN Barbería Quito</title>
    <link rel="canonical" href="https://kortzen.com/reservar">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="48x48" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/favicon.ico?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png?v=10">

    <!-- Kortzen Core Stylesheets -->
    <link rel="stylesheet" href="/css/variables.css?v=22">
    <link rel="stylesheet" href="/css/reset.css?v=22">
    <link rel="stylesheet" href="/css/base.css?v=35">
    <link rel="stylesheet" href="/css/components.css?v=35">
    <link rel="stylesheet" href="/css/layout.css?v=35000">
    <link rel="stylesheet" href="/css/pages.css?v=22">
    <link rel="stylesheet" href="/css/animations.css?v=24">
    <link rel="stylesheet" href="/css/whatsapp.css?v=22">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        /* Restablecer cursor visible estándar */
        html, body, a, button {
            cursor: auto !important;
        }

        .cursor-dot {
            display: none !important;
        }

        body {
            background-color: #FFFFFF;
            color: #111111;
            font-family: 'Plus Jakarta Sans', var(--font-body), sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* Encabezado de página perfectamente centrado */
        .page-header-centered {
            text-align: center;
            padding: 4rem 1.5rem 2.5rem 1.5rem;
            max-width: 820px;
            margin: 0 auto;
        }

        .page-header-centered__subtitle {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #777777;
            margin-bottom: 0.75rem;
        }

        .page-header-centered__title {
            font-size: clamp(2.2rem, 5vw, 3.2rem);
            font-weight: 900;
            color: #111111;
            line-height: 1.15;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .page-header-centered__desc {
            font-size: 1.1rem;
            color: #555555;
            line-height: 1.6;
            max-width: 620px;
            margin: 0 auto;
        }

        /* Tarjeta Principal de Alto Contraste */
        .expectation-card {
            background: #111111;
            border: 1.5px solid #242424;
            border-radius: 20px;
            padding: 3rem 2.25rem;
            max-width: 820px;
            margin: 0 auto 4rem auto;
            text-align: center;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.12);
            color: #FFFFFF;
        }

        .expectation-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #FFFFFF;
            background: #222222;
            border: 1px solid #383838;
            padding: 6px 16px;
            border-radius: 9999px;
            margin-bottom: 1.75rem;
        }

        .expectation-title {
            font-size: clamp(1.6rem, 3.5vw, 2.2rem);
            font-weight: 900;
            color: #FFFFFF;
            margin-bottom: 1rem;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }

        .expectation-desc {
            color: #D1D5DB;
            font-size: 1rem;
            line-height: 1.65;
            max-width: 620px;
            margin: 0 auto 2.25rem auto;
        }

        /* Panel de Acción Destacada */
        .action-highlight-box {
            background: #18181A;
            border: 1.5px solid #2D2D30;
            border-radius: 16px;
            padding: 2rem 1.75rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .action-highlight-box__tag {
            color: #C0A062;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.5rem;
        }

        .action-highlight-box__title {
            color: #FFFFFF;
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .action-highlight-box__desc {
            color: #9CA3AF;
            font-size: 0.92rem;
            line-height: 1.55;
            max-width: 520px;
            margin: 0 auto 1.5rem auto;
        }

        /* Botón Setmore Ultra Visible y Llamativo */
        .btn-setmore-main {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #C0A062;
            color: #0A0A0B;
            font-size: 1.05rem;
            font-weight: 900;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 16px 28px;
            border-radius: 12px;
            border: 2px solid #D4AF37;
            box-shadow: 0 8px 25px rgba(192, 160, 98, 0.45);
            transition: all 0.25s ease;
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
        }

        .btn-setmore-main:hover {
            background: #FFFFFF;
            color: #000000;
            border-color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255, 255, 255, 0.25);
        }

        /* Botón WhatsApp de Apoyo */
        .btn-whatsapp-sub {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #25D366;
            color: #FFFFFF;
            font-size: 0.92rem;
            font-weight: 800;
            text-decoration: none;
            padding: 13px 22px;
            border-radius: 10px;
            width: 100%;
            max-width: 460px;
            margin: 12px auto 0 auto;
            transition: all 0.2s ease;
        }

        .btn-whatsapp-sub:hover {
            background: #1EBE5D;
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        /* Features Grid */
        .features-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            text-align: left;
            margin-top: 2rem;
        }

        .feature-box {
            background: #18181A;
            border: 1px solid #262628;
            border-left: 3px solid #C0A062;
            padding: 1.1rem 1rem;
            border-radius: 8px;
        }

        .feature-box__title {
            color: #FFFFFF;
            font-size: 0.9rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .feature-box__desc {
            color: #9CA3AF;
            font-size: 0.78rem;
            line-height: 1.45;
        }

        /* Botón Volver */
        .btn-back-clean {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            color: #9CA3AF;
            border: 1.5px solid #333333;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            margin-top: 2rem;
            transition: all 0.2s ease;
        }

        .btn-back-clean:hover {
            border-color: #FFFFFF;
            color: #FFFFFF;
        }

        @media (max-width: 768px) {
            .expectation-card {
                padding: 2.25rem 1.25rem;
                margin-bottom: 2.5rem;
            }

            .action-highlight-box {
                padding: 1.5rem 1rem;
            }

            .features-grid-3 {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .btn-setmore-main {
                font-size: 0.95rem;
                padding: 14px 18px;
            }
        }
    </style>
</head>

<body>
    <!-- Header Oficial -->
    <header class="header" role="banner">
        <div class="container header__container">
            <a href="/" class="header__logo">
                <span class="header__logo-text">KORT<span>ZEN</span></span>
            </a>
            <nav class="nav" role="navigation" aria-label="Navegación principal">
                <a href="/" class="nav__link">Inicio</a>
                <a href="/servicios.html" class="nav__link">Servicios</a>
                <a href="/nosotros.html" class="nav__link">Equipo</a>
                <a href="/galeria.html" class="nav__link">Galería</a>
                <a href="/contacto.html" class="nav__link">Contacto</a>
            </nav>
            <a href="/reservar.php" class="btn btn--primary header__cta">Reservar Cita</a>
            <button class="menu-toggle" aria-label="Abrir menú">
                <span class="menu-toggle__line"></span>
                <span class="menu-toggle__line"></span>
                <span class="menu-toggle__line"></span>
            </button>
        </div>
    </header>

    <!-- Mobile Navigation Oficial -->
    <nav class="mobile-nav" id="mobile-nav" role="navigation" aria-label="Navegación móvil">
        <div class="mobile-nav__links">
            <a href="/" class="mobile-nav__link">Inicio</a>
            <a href="/servicios.html" class="mobile-nav__link">Servicios</a>
            <a href="/nosotros.html" class="mobile-nav__link">Equipo</a>
            <a href="/galeria.html" class="mobile-nav__link">Galería</a>
            <a href="/contacto.html" class="mobile-nav__link">Contacto</a>
        </div>
        <div class="mobile-nav__footer">
            <a href="/reservar.php" class="btn btn--primary mobile-nav__cta">Reservar Cita</a>
            <div class="mobile-nav__meta">
                <div class="mobile-nav__meta-item">
                    <span class="mobile-nav__meta-label">Ubicación</span>
                    <span class="mobile-nav__meta-value">Llano Chico, Quito, Ecuador</span>
                </div>
                <div class="mobile-nav__meta-item">
                    <span class="mobile-nav__meta-label">Horarios</span>
                    <span class="mobile-nav__meta-value">Lun - Dom: 10:00 AM - 08:00 PM</span>
                </div>
                <div class="mobile-nav__meta-item">
                    <span class="mobile-nav__meta-label">Contacto</span>
                    <span class="mobile-nav__meta-value">
                        <a href="https://www.instagram.com/mateo.josue_" target="_blank">Instagram</a> / 
                        <a href="https://www.facebook.com/saludyvidamasfacil" target="_blank">Facebook</a> / 
                        <a href="https://wa.me/593988422770" target="_blank">WhatsApp</a>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <!-- Page Header Centrado -->
        <section class="page-header-centered">
            <span class="page-header-centered__subtitle">Agenda & Experiencia</span>
            <h1 class="page-header-centered__title">Reservas</h1>
            <p class="page-header-centered__desc">Estamos perfeccionando nuestra nueva plataforma digital para brindarte un servicio superior.</p>
        </section>

        <!-- Contenedor Principal de Expectativa -->
        <section class="container" style="padding-bottom: 2rem;">
            <div class="expectation-card">
                
                <div class="expectation-badge">
                    <span>Próximamente • Nueva Experiencia</span>
                </div>

                <h2 class="expectation-title">
                    Estamos actualizando nuestra Plataforma Oficial
                </h2>

                <p class="expectation-desc">
                    Muy pronto podrás agendar tus citas en tiempo real con tu barbero de confianza, acumular puntos de cliente y acceder a beneficios exclusivos desde nuestra nueva plataforma web y app.
                </p>

                <!-- Panel de Acción Destacada -->
                <div class="action-highlight-box">
                    <div class="action-highlight-box__tag">Atención Continua</div>
                    <h3 class="action-highlight-box__title">¿Deseas reservar tu cita hoy?</h3>
                    <p class="action-highlight-box__desc">
                        Mientras completamos el lanzamiento oficial, puedes seguir agendando tu turno con total normalidad en nuestra plataforma habitual:
                    </p>

                    <div>
                        <a href="https://barberestudiokortzen.setmore.com/" target="_blank" rel="noopener noreferrer" class="btn-setmore-main">
                            <span>Continuar Reservando en Setmore</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>

                        <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20deseo%20agendar%20una%20cita" target="_blank" rel="noopener noreferrer" class="btn-whatsapp-sub">
                            <i class="fab fa-whatsapp" style="font-size: 1.1rem;"></i>
                            <span>O Reserva Directamente por WhatsApp</span>
                        </a>
                    </div>
                </div>

                <!-- Tarjetas de Adelanto -->
                <div class="features-grid-3">
                    <div class="feature-box">
                        <div class="feature-box__title">Agenda en Tiempo Real</div>
                        <div class="feature-box__desc">Disponibilidad exacta con tu barbero favorito y sin esperas.</div>
                    </div>
                    <div class="feature-box">
                        <div class="feature-box__title">Club de Puntos</div>
                        <div class="feature-box__desc">Acumula beneficios y descuentos en cada uno de tus cortes.</div>
                    </div>
                    <div class="feature-box">
                        <div class="feature-box__title">Historial de Estilo</div>
                        <div class="feature-box__desc">Registro personalizado de tus preferencias y rituales de autor.</div>
                    </div>
                </div>

                <div>
                    <a href="/" class="btn-back-clean">
                        <i class="fas fa-arrow-left"></i> Volver al inicio
                    </a>
                </div>

            </div>
        </section>
    </main>

    <!-- Footer Oficial -->
    <footer class="footer" role="contentinfo">
        <div class="container">
            <div class="footer__grid">
                <!-- Brand -->
                <div class="footer__brand">
                    <h3 class="footer__logo">KORT<span>ZEN</span></h3>
                    <p class="footer__desc">
                        Barbería clásica y estilismo masculino contemporáneo en Llano Chico, Quito. Una experiencia diseñada para el hombre de distinción.
                    </p>
                </div>

                <!-- Navigation -->
                <div>
                    <h4 class="footer__title">Navegación</h4>
                    <ul class="footer__links">
                        <li><a href="/" class="footer__link">Inicio</a></li>
                        <li><a href="/servicios.html" class="footer__link">Servicios</a></li>
                        <li><a href="/nosotros.html" class="footer__link">Equipo</a></li>
                        <li><a href="/galeria.html" class="footer__link">Galería</a></li>
                        <li><a href="/contacto.html" class="footer__link">Contacto</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div>
                    <h4 class="footer__title">Servicios</h4>
                    <ul class="footer__links">
                        <li><a href="/servicios.html" class="footer__link">Corte Clásico</a></li>
                        <li><a href="/servicios.html" class="footer__link">Afeitado Tradicional</a></li>
                        <li><a href="/servicios.html" class="footer__link">Cuidado de Barba</a></li>
                        <li><a href="/servicios.html" class="footer__link">Tratamientos Spa</a></li>
                    </ul>
                </div>

                <!-- Schedule & Contact -->
                <div>
                    <h4 class="footer__title">Ubicación & Contacto</h4>
                    <ul class="footer__links" style="margin-bottom: 1rem;">
                        <li style="display: flex; gap: 8px; color: var(--color-gray-light); font-size: 0.85rem; margin-bottom: 8px; line-height: 1.4;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold, #D4AF37)" stroke-width="2" style="flex-shrink:0; margin-top: 2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <span data-branch-dynamic="address" class="footer__branch-address">Llano Chico, Quito, Ecuador</span>
                        </li>
                        <li style="display: flex; gap: 8px; color: var(--color-gray-light); font-size: 0.85rem; margin-bottom: 8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold, #D4AF37)" stroke-width="2" style="flex-shrink:0; margin-top: 2px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            <a data-branch-dynamic="phone" class="footer__branch-phone" href="tel:+593988422770" style="color: var(--color-gray-light); text-decoration: none;">+593 098 842 2770</a>
                        </li>
                    </ul>
                    <h4 class="footer__title" style="margin-top: 1rem;">Horario</h4>
                    <div class="footer__schedule">
                        <div class="footer__schedule-item">
                            <span data-branch-dynamic="hours" class="footer__schedule-time" style="color: var(--color-gray-light); font-size: 0.85rem;">Lun - Dom: 10:00 AM - 08:00 PM</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer__bottom">
            <p class="footer__copyright">
                <a href="login.php" style="color: inherit; text-decoration: none; cursor: default;">©</a> 2026 KORTZEN. Todos los derechos reservados.
            </p>
            <div class="footer__dev">
                <span class="footer__dev-label">Desarrollado Por</span>
                <a href="https://jiyanedesign.com" target="_blank" class="footer__dev-link">JiyaneDesign</a>
            </div>
            <div class="footer__legal">
                <a href="/politica-de-privacidad.html" class="footer__legal-link">Política de Privacidad</a>
                <a href="/terminos-de-uso.html" class="footer__legal-link">Términos de Uso</a>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Flotante -->
    <a href="https://wa.me/593988422770" class="floating-whatsapp-btn" target="_blank" title="Contáctanos por WhatsApp" aria-label="WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.075-1.847-.426-1.547-.64-2.527-2.222-2.604-2.325-.077-.103-.623-.829-.623-1.581 0-.752.393-1.122.533-1.272.14-.15.305-.188.407-.188.102 0 .204.002.294.006.096.004.225-.036.35.267.13.313.442 1.079.48 1.157.039.078.065.17.013.273-.051.103-.077.167-.154.256-.077.09-.161.2-.23.269-.077.077-.157.161-.067.316.09.154.401.662.861 1.072.593.528 1.093.692 1.248.769.155.077.246.064.337-.039.091-.103.391-.455.495-.61.104-.155.207-.129.349-.077.142.052.898.423 1.053.5.155.078.258.117.297.181.039.065.039.378-.105.783z"/>
            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.98-1.397C8.42 21.498 10.15 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18.25c-1.63 0-3.15-.48-4.43-1.3l-.32-.2-3.28.92.93-3.2-.21-.34C3.82 14.8 3.75 13.43 3.75 12c0-4.55 3.7-8.25 8.25-8.25s8.25 3.7 8.25 8.25-3.7 8.25-8.25 8.25z"/>
        </svg>
    </a>

    <!-- Scripts -->
    <script src="/js/client-auth.js?v=100"></script>
    <script src="/js/branch-selector.js?v=25000"></script>
    <script type="module" src="/js/main.js"></script>
</body>
</html>
