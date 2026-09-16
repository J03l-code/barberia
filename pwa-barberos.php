<?php
/**
 * Barberos - Native App UI
 * Sección para conocer a los barberos de KORTZEN
 */

session_start();
require_once 'config.php';

// Obtener barberos reales de la BD
$barberos = [];
try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM usuarios WHERE activo = 1 AND (rol = 'barbero' OR rol = 'admin_local') ORDER BY id ASC");
    $raw_barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $seen = [];
    foreach ($raw_barberos as $b) {
        $nameKey = strtolower(trim($b['nombre']));
        if (!isset($seen[$nameKey])) {
            $seen[$nameKey] = true;
            $foto = !empty($b['foto_url']) ? $b['foto_url'] : (!empty($b['foto']) ? $b['foto'] : (!empty($b['foto_perfil']) ? $b['foto_perfil'] : ''));
            $b['foto_url'] = $foto;
            $b['foto'] = $foto;
            $b['biografia'] = !empty($b['biografia']) ? $b['biografia'] : (!empty($b['bio']) ? $b['bio'] : '');
            $barberos[] = $b;
        }
    }
} catch (Exception $e) {
}


// Fallback por si no hay barberos creados aún en BD
if (empty($barberos)) {
    $barberos = [
        [
            'id' => 1,
            'nombre' => 'Mateo',
            'rol' => 'Master Barber',
            'especialidades' => 'Cortes de autor, degradados de precisión y estilismo.',
            'biografia' => 'Más de 8 años de experiencia en barbería tradicional y tendencias contemporáneas.',
            'foto_url' => ''
        ],
        [
            'id' => 2,
            'nombre' => 'Andrés',
            'rol' => 'Especialista en Barba',
            'especialidades' => 'Perfilado de barba, toalla caliente y afeitado tradicional.',
            'biografia' => 'Apasionado por la navaja libre y los rituales de afeitado de lujo.',
            'foto_url' => ''
        ],
        [
            'id' => 3,
            'nombre' => 'Santiago',
            'rol' => 'Barbero & Estilista',
            'especialidades' => 'Diseño de cejas, cuidado facial y estilo vintage.',
            'biografia' => 'Especialista en transformar tu imagen resaltando tus rasgos naturales.',
            'foto_url' => ''
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Nuestros Barberos - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=50">

    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
    <script src="/js/branch-selector.js?v=26000"></script>

    <style>
        .pwa-barber-profile-card {
            background: var(--pwa-card-bg);
            border: 1px solid var(--pwa-border);
            border-radius: var(--pwa-radius-card);
            padding: 1.25rem;
            margin-bottom: 1.1rem;
            box-shadow: var(--pwa-shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .pwa-barber-profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .pwa-barber-photo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--pwa-border);
            background: #EAEAEA;
            flex-shrink: 0;
        }

        .pwa-barber-photo-fallback {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #111111;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .pwa-barber-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--pwa-text-main);
            margin-bottom: 0.15rem;
        }

        .pwa-barber-role {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--pwa-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pwa-barber-bio {
            font-size: 0.82rem;
            color: var(--pwa-text-muted);
            line-height: 1.45;
            flex-grow: 1;
            margin-bottom: 0.85rem;
        }

        .pwa-barber-bottom-wrapper {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .pwa-barber-specs {
            display: inline-block;
            background: var(--pwa-bg);
            border: 1px solid var(--pwa-border);
            border-radius: 8px;
            padding: 0.4rem 0.75rem;
            font-size: 0.75rem;
            color: var(--pwa-text-main);
            font-weight: 500;
        }
    </style>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>

        <!-- Native Top Bar (Mobile Only) -->
        <header class="pwa-header">
            <button class="pwa-header__btn" onclick="history.back()" title="Atrás">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <div class="pwa-header__title">Nuestros Barberos</div>
            <div style="width: 32px;"></div>
        </header>

        <p class="pwa-subtitle">Conoce a nuestros maestros barberos expertos en técnicas de lujo y estilismo masculino.</p>

        <!-- Barber Cards -->
        <div class="pwa-barber-grid" style="margin-top: 1rem;">
            <?php foreach ($barberos as $b): 
                $nombre_b = htmlspecialchars($b['nombre']);
                $inicial_b = strtoupper(substr($nombre_b, 0, 1));
                $foto_b = $b['foto_url'] ?? '';
                $rol_b = !empty($b['rol']) ? ($b['rol'] === 'admin' ? 'Master Barber & Founder' : 'Barbero Profesional') : 'Maestro Barbero';
                $bio_b = !empty($b['biografia']) ? $b['biografia'] : 'Especialista en cortes de precisión, diseño de barba y cuidado masculino de lujo.';
                $specs_b = !empty($b['especialidades']) ? $b['especialidades'] : 'Corte de Autor • Perfilado de Barba';
            ?>
            <div class="pwa-barber-profile-card">
                <div class="pwa-barber-profile-header">
                    <?php if (!empty($foto_b)): ?>
                        <img src="<?php echo htmlspecialchars($foto_b); ?>" alt="<?php echo $nombre_b; ?>" class="pwa-barber-photo">
                    <?php else: ?>
                        <div class="pwa-barber-photo-fallback"><?php echo $inicial_b; ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="pwa-barber-name"><?php echo $nombre_b; ?></div>
                        <div class="pwa-barber-role"><?php echo $rol_b; ?></div>
                    </div>
                </div>

                <div class="pwa-barber-bio">
                    <?php echo htmlspecialchars($bio_b); ?>
                </div>

                <div class="pwa-barber-bottom-wrapper" style="width: 100%;">
                    <div class="pwa-barber-specs">
                        ✦ <?php echo htmlspecialchars($specs_b); ?>
                    </div>

                    <a href="reservar.php?barbero_id=<?php echo $b['id']; ?>" class="pwa-btn-black" style="padding: 0.85rem; font-size: 0.8rem; width: 100%;">
                        <span>RESERVAR CON <?php echo strtoupper($nombre_b); ?></span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

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
        <a href="reservar.php" class="pwa-nav-tab">
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
