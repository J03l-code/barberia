<?php
require_once 'config.php';
$pageTitle = 'Próximamente | Nueva Plataforma KORTZEN';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Próximamente Nueva Plataforma | KORTZEN Barbería Quito</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">

    <style>
        :root {
            --bg-dark: #0A0A0B;
            --bg-card: #141416;
            --bg-card-hover: #1A1A1E;
            --gold-primary: #C0A062;
            --gold-hover: #D4AF37;
            --gold-light: #F3E5AB;
            --text-light: #FFFFFF;
            --text-muted: #9CA3AF;
            --border-gold: rgba(192, 160, 98, 0.35);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-display: 'Cinzel', serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(192, 160, 98, 0.15) 0%, transparent 60%),
                radial-gradient(circle at 100% 100%, rgba(192, 160, 98, 0.05) 0%, transparent 40%);
            overflow-x: hidden;
        }

        .header-simple {
            padding: 24px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .header-logo {
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: 0.15em;
            color: #FFFFFF;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .header-logo span {
            color: var(--gold-primary);
        }

        .header-back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s ease;
        }

        .header-back-link:hover {
            color: var(--gold-primary);
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .coming-card {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 24px;
            padding: 48px 36px;
            max-width: 680px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7), 0 0 40px rgba(192, 160, 98, 0.08);
            position: relative;
            backdrop-filter: blur(10px);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(192, 160, 98, 0.12);
            border: 1px solid var(--border-gold);
            color: var(--gold-primary);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .badge-dot {
            width: 8px;
            height: 8px;
            background: var(--gold-primary);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--gold-primary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        .title-hero {
            font-size: 2.2rem;
            font-weight: 900;
            line-height: 1.2;
            color: #FFFFFF;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .title-hero span {
            background: linear-gradient(135deg, #FFFFFF 30%, var(--gold-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .desc-text {
            color: #D1D5DB;
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 32px;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Action Box */
        .action-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-subtle);
            border-radius: 18px;
            padding: 24px 20px;
            margin-bottom: 32px;
        }

        .action-box-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--gold-primary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .action-box-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .btn-setmore {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: linear-gradient(135deg, var(--gold-primary) 0%, #A88647 100%);
            color: #0A0A0B;
            font-size: 1.05rem;
            font-weight: 900;
            text-decoration: none;
            padding: 16px 28px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(192, 160, 98, 0.35);
            transition: all 0.3s ease;
            letter-spacing: 0.02em;
        }

        .btn-setmore:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(192, 160, 98, 0.5);
            background: linear-gradient(135deg, #D4AF37 0%, var(--gold-primary) 100%);
            color: #000;
        }

        .btn-whatsapp-alt {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(37, 211, 102, 0.1);
            border: 1px solid rgba(37, 211, 102, 0.3);
            color: #25D366;
            font-size: 0.95rem;
            font-weight: 800;
            text-decoration: none;
            padding: 13px 22px;
            border-radius: 12px;
            margin-top: 12px;
            width: 100%;
            transition: all 0.2s ease;
        }

        .btn-whatsapp-alt:hover {
            background: rgba(37, 211, 102, 0.2);
            border-color: #25D366;
            color: #4ADE80;
        }

        /* Teaser Feature Cards */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            text-align: left;
            margin-top: 24px;
        }

        .feature-mini {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 16px 14px;
        }

        .feature-mini-icon {
            font-size: 1.3rem;
            color: var(--gold-primary);
            margin-bottom: 8px;
        }

        .feature-mini-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .feature-mini-text {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .footer-simple {
            padding: 24px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-simple a {
            color: var(--text-light);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-simple a:hover {
            color: var(--gold-primary);
        }

        @media (max-width: 640px) {
            .coming-card {
                padding: 32px 20px;
                border-radius: 20px;
            }

            .title-hero {
                font-size: 1.7rem;
            }

            .desc-text {
                font-size: 0.95rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .btn-setmore {
                font-size: 0.95rem;
                padding: 14px 20px;
            }
        }
    </style>
</head>

<body>

    <!-- Header -->
    <header class="header-simple">
        <a href="/" class="header-logo">
            KORT<span>ZEN</span>
        </a>
        <a href="/" class="header-back-link">
            <i class="fas fa-arrow-left"></i> Inicio
        </a>
    </header>

    <!-- Main Section -->
    <main class="main-content">
        <div class="coming-card">
            
            <div class="badge-pill">
                <span class="badge-dot"></span>
                <span>Muy Pronto • Nueva Experiencia</span>
            </div>

            <h1 class="title-hero">
                Estamos actualizando nuestra <span>Plataforma Oficial</span>
            </h1>

            <p class="desc-text">
                Muy pronto podrás agendar tus citas en tiempo real, elegir a tu barbero preferido, acumular puntos exclusivos de cliente y acceder a beneficios únicos desde nuestra nueva plataforma web y app.
            </p>

            <!-- Call to Action Card -->
            <div class="action-box">
                <div class="action-box-title">
                    <i class="far fa-calendar-check"></i> ¿Deseas agendar tu cita hoy?
                </div>
                <p class="action-box-desc">
                    Mientras completamos el lanzamiento oficial, puedes seguir reservando tu turno con total normalidad en nuestra plataforma habitual:
                </p>

                <a href="https://barberestudiokortzen.setmore.com/" target="_blank" rel="noopener noreferrer" class="btn-setmore">
                    <span>Continuar Reservando en Setmore</span>
                    <i class="fas fa-external-link-alt"></i>
                </a>

                <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20deseo%20reservar%20una%20cita" target="_blank" rel="noopener noreferrer" class="btn-whatsapp-alt">
                    <i class="fab fa-whatsapp" style="font-size: 1.15rem;"></i>
                    <span>O Reserva Directamente por WhatsApp</span>
                </a>
            </div>

            <!-- Features Teaser -->
            <div class="features-grid">
                <div class="feature-mini">
                    <div class="feature-mini-icon">💈</div>
                    <div class="feature-mini-title">Agenda Inteligente</div>
                    <div class="feature-mini-text">Turnos en vivo con tus barberos de confianza sin esperas.</div>
                </div>
                <div class="feature-mini">
                    <div class="feature-mini-icon">⭐</div>
                    <div class="feature-mini-title">Club de Puntos</div>
                    <div class="feature-mini-text">Acumula puntos en cada visita y canjéalos por cortes gratis.</div>
                </div>
                <div class="feature-mini">
                    <div class="feature-mini-icon">📍</div>
                    <div class="feature-mini-title">Estilo a Medida</div>
                    <div class="feature-mini-text">Historial personalizado de tus rituales y preferencias.</div>
                </div>
            </div>

            <div style="margin-top: 28px;">
                <a href="/" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; font-weight: 700; transition: color 0.2s;" onmouseover="this.style.color='#C0A062'" onmouseout="this.style.color='#9CA3AF'">
                    ← Volver a la página principal
                </a>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-simple">
        <p>© 2026 <strong>KORTZEN</strong>. Barbería Clásica & Estilismo Masculino. Llano Chico, Quito.</p>
    </footer>

</body>
</html>
