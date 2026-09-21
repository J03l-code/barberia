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

    <!-- Kortzen Design System Stylesheets -->
    <link rel="stylesheet" href="/css/variables.css?v=22">
    <link rel="stylesheet" href="/css/reset.css?v=22">
    <link rel="stylesheet" href="/css/base.css?v=35">
    <link rel="stylesheet" href="/css/components.css?v=35">
    <link rel="stylesheet" href="/css/layout.css?v=35000">
    <link rel="stylesheet" href="/css/pages.css?v=22">
    <link rel="stylesheet" href="/css/cursor.css?v=22">
    <link rel="stylesheet" href="/css/animations.css?v=24">
    <link rel="stylesheet" href="/css/whatsapp.css?v=22">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <style>
        .coming-soon-wrapper {
            background-color: var(--color-charcoal, #141416);
            border: 1px solid var(--color-border, rgba(255, 255, 255, 0.08));
            border-radius: var(--radius-lg, 16px);
            padding: var(--space-12, 3rem) var(--space-8, 2rem);
            max-width: 860px;
            margin: 0 auto;
            text-align: center;
        }

        .coming-soon-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: var(--text-xs, 0.75rem);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--color-gold, #C0A062);
            background: rgba(192, 160, 98, 0.1);
            border: 1px solid rgba(192, 160, 98, 0.25);
            padding: 6px 16px;
            border-radius: var(--radius-full, 9999px);
            margin-bottom: var(--space-6, 1.5rem);
        }

        .coming-soon-title {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            color: var(--color-white, #FFFFFF);
            margin-bottom: var(--space-4, 1rem);
            line-height: 1.2;
            letter-spacing: -0.01em;
        }

        .coming-soon-desc {
            color: var(--color-gray-light, #9CA3AF);
            font-size: var(--text-lg, 1.125rem);
            line-height: 1.7;
            max-width: 640px;
            margin: 0 auto var(--space-10, 2.5rem);
        }

        .booking-action-panel {
            background: var(--color-black, #0A0A0B);
            border: 1px solid var(--color-border, rgba(255, 255, 255, 0.08));
            border-radius: var(--radius-md, 12px);
            padding: var(--space-8, 2rem) var(--space-6, 1.5rem);
            margin-bottom: var(--space-10, 2.5rem);
        }

        .booking-action-panel__tag {
            color: var(--color-gold, #C0A062);
            font-size: var(--text-xs, 0.75rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: var(--space-2, 0.5rem);
        }

        .booking-action-panel__heading {
            color: var(--color-white, #FFFFFF);
            font-size: var(--text-xl, 1.25rem);
            font-weight: 700;
            margin-bottom: var(--space-2, 0.5rem);
        }

        .booking-action-panel__text {
            color: var(--color-gray, #6B7280);
            font-size: var(--text-sm, 0.875rem);
            margin-bottom: var(--space-6, 1.5rem);
            line-height: 1.5;
        }

        .booking-action-buttons {
            display: flex;
            flex-direction: column;
            gap: var(--space-4, 1rem);
            max-width: 460px;
            margin: 0 auto;
        }

        .booking-features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-4, 1rem);
            text-align: left;
            margin-top: var(--space-6, 1.5rem);
        }

        .booking-feature-item {
            background: rgba(255, 255, 255, 0.02);
            border-left: 2px solid var(--color-gold, #C0A062);
            padding: var(--space-4, 1rem);
            border-radius: 0 var(--radius-sm, 6px) var(--radius-sm, 6px) 0;
        }

        .booking-feature-item__title {
            color: var(--color-white, #FFFFFF);
            font-size: var(--text-sm, 0.875rem);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .booking-feature-item__desc {
            color: var(--color-gray, #6B7280);
            font-size: var(--text-xs, 0.75rem);
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .coming-soon-wrapper {
                padding: var(--space-8, 2rem) var(--space-4, 1rem);
            }

            .booking-features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
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

    <!-- Mobile Navigation -->
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
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <span class="page-header__subtitle">Agenda & Experiencia</span>
                <h1 class="page-header__title">Reservas</h1>
                <p class="page-header__description">Estamos perfeccionando nuestra nueva plataforma digital para brindarte un servicio superior.</p>
            </div>
        </section>

        <!-- Expectation Section -->
        <section class="section" style="padding-top: 0;">
            <div class="container">
                <div class="coming-soon-wrapper">
                    
                    <div class="coming-soon-badge">
                        <span>Próximamente • Nueva Experiencia</span>
                    </div>

                    <h2 class="coming-soon-title">
                        Estamos actualizando nuestra Plataforma Oficial
                    </h2>

                    <p class="coming-soon-desc">
                        Muy pronto podrás agendar tus citas en tiempo real con tu barbero de confianza, acumular puntos de cliente y acceder a beneficios exclusivos desde nuestra nueva plataforma web y app.
                    </p>

                    <!-- Main Action Panel -->
                    <div class="booking-action-panel">
                        <div class="booking-action-panel__tag">Atención Continua</div>
                        <h3 class="booking-action-panel__heading">¿Deseas reservar tu cita hoy?</h3>
                        <p class="booking-action-panel__text">
                            Mientras completamos el lanzamiento oficial, puedes seguir agendando tu turno con total normalidad en nuestra plataforma habitual:
                        </p>

                        <div class="booking-action-buttons">
                            <a href="https://barberestudiokortzen.setmore.com/" target="_blank" rel="noopener noreferrer" class="btn btn--primary btn--lg" style="width: 100%; justify-content: center; text-align: center;">
                                Continuar Reservando en Setmore →
                            </a>

                            <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20deseo%20agendar%20una%20cita" target="_blank" rel="noopener noreferrer" class="btn btn--secondary" style="width: 100%; justify-content: center; text-align: center; border-color: rgba(37, 211, 102, 0.4); color: #25D366;">
                                Reservar por WhatsApp
                            </a>
                        </div>
                    </div>

                    <!-- Upcoming Features Grid -->
                    <div class="booking-features-grid">
                        <div class="booking-feature-item">
                            <div class="booking-feature-item__title">Agenda en Tiempo Real</div>
                            <div class="booking-feature-item__desc">Disponibilidad exacta con tu barbero favorito y sin esperas.</div>
                        </div>
                        <div class="booking-feature-item">
                            <div class="booking-feature-item__title">Club de Puntos</div>
                            <div class="booking-feature-item__desc">Acumula beneficios y descuentos en cada uno de tus cortes.</div>
                        </div>
                        <div class="booking-feature-item">
                            <div class="booking-feature-item__title">Historial de Estilo</div>
                            <div class="booking-feature-item__desc">Registro personalizado de tus preferencias y rituales de autor.</div>
                        </div>
                    </div>

                    <div style="margin-top: var(--space-8, 2rem);">
                        <a href="/" class="btn btn--ghost" style="color: var(--color-gray-light); font-size: var(--text-sm);">
                            ← Volver al inicio
                        </a>
                    </div>

                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
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
