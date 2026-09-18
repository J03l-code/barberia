<?php
/**
 * KORTZEN - Desktop Web Navbar & Collapsible Menu Header
 * Iconos vectoriales minimalistas en negro y soporte de alineación perfecta
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* Estilos Autocontenidos para Vista de Escritorio */
    @media (min-width: 769px) {
        .pwa-bottom-nav-bar, 
        .pwa-header {
            display: none !important;
        }

        .pwa-container, .booking-container {
            max-width: 1050px !important;
            margin: 0 auto !important;
            padding-top: 1.5rem !important;
            padding-bottom: 4rem !important;
        }

        .pwa-desktop-navbar {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 1.5rem;
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .pwa-desktop-logo {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #111111;
            text-decoration: none;
        }

        .pwa-desktop-menu-links {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .pwa-desktop-nav-link {
            color: #444444;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.55rem 0.95rem;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .pwa-desktop-nav-link:hover,
        .pwa-desktop-nav-link--active {
            background: #111111;
            color: #FFFFFF !important;
        }

        .pwa-desktop-toggle-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #F8F9FA;
            border: 1px solid #EAEAEA;
            padding: 0.55rem 1.1rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.82rem;
            color: #111111;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pwa-desktop-toggle-btn:hover {
            background: #111111;
            color: #FFFFFF;
        }

        /* Menú Colapsable */
        .pwa-collapsible-menu {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        }

        .pwa-collapsible-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.85rem;
        }

        .pwa-collapsible-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.85rem 1rem;
            background: #F9F9F9;
            border: 1px solid #EAEAEA;
            border-radius: 12px;
            color: #111111;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.2s ease;
        }

        .pwa-collapsible-item svg {
            color: #111111;
            transition: color 0.2s ease;
        }

        .pwa-collapsible-item:hover {
            background: #111111;
            color: #FFFFFF !important;
        }

        .pwa-collapsible-item:hover svg {
            color: #FFFFFF !important;
        }

        /* Grillas multicolumna en Escritorio */
        .pwa-quick-actions {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 1rem !important;
        }

        .pwa-services-list,
        .pwa-barber-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 1.25rem !important;
        }
    }

    @media (max-width: 768px) {
        .pwa-desktop-navbar,
        .pwa-collapsible-menu {
            display: none !important;
        }
    }
</style>

<!-- Desktop Web Navigation Header -->
<header class="pwa-desktop-navbar">
    <div style="display: flex; align-items: center; gap: 14px;">
        <a href="cliente-dashboard.php" class="pwa-desktop-logo" style="margin: 0; display: inline-flex; align-items: center;">KORTZEN</a>
        <div class="pwa-desktop-branch-selector" onclick="window.createBranchSelectorModal ? window.createBranchSelectorModal() : (window.KortzenBranches && window.KortzenBranches.showSelector())" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #F4F4F5; border: 1px solid #E4E4E7; padding: 4px 10px; border-radius: 20px; font-size: 0.76rem; font-weight: 700; color: #18181B; transition: all 0.2s ease;" onmouseover="this.style.background='#18181B'; this.style.color='#FFFFFF'; this.style.borderColor='#18181B';" onmouseout="this.style.background='#F4F4F5'; this.style.color='#18181B'; this.style.borderColor='#E4E4E7';" title="Cambiar Sucursal">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
            <span data-branch-dynamic="name" class="pwa-branch-name">KORTZEN Llano Chico</span>
            <span style="background: rgba(0,0,0,0.08); font-size: 0.65rem; padding: 1px 6px; border-radius: 4px; text-transform: uppercase;">Cambiar</span>
        </div>
    </div>

    <div class="pwa-desktop-menu-links">
        <a href="cliente-dashboard.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'cliente-dashboard.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Inicio</a>
        <a href="pwa-servicios.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'pwa-servicios.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Servicios</a>
        <a href="pwa-barberos.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'pwa-barberos.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Barberos</a>
        <a href="reservar.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'reservar.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Reservar</a>
        <a href="mis-citas.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'mis-citas.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Mis Citas</a>
        <a href="mi-perfil.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'mi-perfil.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Perfil</a>
    </div>

    <button type="button" class="pwa-desktop-toggle-btn" onclick="toggleDesktopMenu()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
        <span>Menú</span>
    </button>
</header>

<!-- Collapsible Menu Drawer on Desktop -->
<div id="pwaCollapsibleMenu" class="pwa-collapsible-menu" style="display: none;">
    <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #777; margin-bottom: 0.85rem;">
        ACCESOS RÁPIDOS Y NAVEGACIÓN KORTZEN
    </div>
    <div class="pwa-collapsible-grid">
        <a href="cliente-dashboard.php" class="pwa-collapsible-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Inicio</span>
        </a>
        <a href="pwa-servicios.php" class="pwa-collapsible-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg>
            <span>Catálogo de Servicios</span>
        </a>
        <a href="pwa-barberos.php" class="pwa-collapsible-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span>Nuestros Barberos</span>
        </a>
        <a href="reservar.php" class="pwa-collapsible-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Agendar Cita</span>
        </a>
        <a href="mis-citas.php" class="pwa-collapsible-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span>Mis Citas e Historial</span>
        </a>
        <a href="mi-perfil.php" class="pwa-collapsible-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>Mi Perfil & Puntos</span>
        </a>
        <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20tengo%20una%20consulta" target="_blank" class="pwa-collapsible-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
            <span>Soporte WhatsApp</span>
        </a>
        <a href="logout.php" onclick="if(window.cerrarSesionPwa){ event.preventDefault(); window.cerrarSesionPwa('/cliente-login.php'); }" class="pwa-collapsible-item" style="color: #dc3545;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #dc3545;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</div>

<script src="/js/branch-selector.js?v=26000"></script>
<script>
    function toggleDesktopMenu() {
        const menu = document.getElementById('pwaCollapsibleMenu');
        if (!menu) return;
        if (menu.style.display === 'none' || menu.style.display === '') {
            menu.style.display = 'block';
        } else {
            menu.style.display = 'none';
        }
    }
</script>
