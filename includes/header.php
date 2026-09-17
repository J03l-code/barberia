<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <!-- Mobile Header (Visible only on mobile) -->
        <div class="mobile-header">
            <div class="mobile-logo">KORTZEN</div>
            <button class="mobile-menu-toggle" id="sidebarToggle">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">KORTZEN</div>

            <nav class="sidebar-nav">
                <a href="dashboard.php"
                    class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Overview</span>
                </a>

                <?php if (canViewUsers()): ?>
                    <a href="usuarios.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Usuarios</span>
                    </a>
                <?php endif; ?>

                <?php if (canManageBranches()): ?>
                    <a href="sucursales.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sucursales.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span>Sucursales</span>
                    </a>
                <?php endif; ?>

                <?php if (canManageInventory()): ?>
                    <a href="inventario.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'inventario.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                        <span>Inventario</span>
                    </a>
                <?php endif; ?>

                <?php if (canManageServices()): ?>
                    <a href="servicios.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'servicios.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <path
                                d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z">
                            </path>
                        </svg>
                        <span>Servicios</span>
                    </a>
                <?php endif; ?>

                <?php if (canManageGallery()): ?>
                    <a href="galeria_admin.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'galeria_admin.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <span>Galería Web</span>
                    </a>
                <?php endif; ?>

                <?php if (canManageReviews()): 
                    $pendingCountBadge = 0;
                    try {
                        $pendingCountBadge = intval(query("SELECT COUNT(*) as total FROM resenas WHERE visible = 0")[0]['total'] ?? 0);
                    } catch (Exception $e) {}
                ?>
                    <a href="resenas.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'resenas.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <path
                                d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z">
                            </path>
                        </svg>
                        <span>Reseñas</span>
                        <?php if ($pendingCountBadge > 0): ?>
                            <span style="background: #F59E0B; color: #FFFFFF; font-size: 0.68rem; font-weight: 800; padding: 2px 6px; border-radius: 10px; margin-left: 4px;" title="<?php echo $pendingCountBadge; ?> reseña(s) pendiente(s) por moderar">
                                <?php echo $pendingCountBadge; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <a href="citas.php"
                    class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'citas.php' ? 'active' : ''; ?>">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Citas</span>
                </a>

                <?php if (canManageSchedules()): ?>
                    <a href="horarios.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'horarios.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span>Horarios</span>
                    </a>
                <?php endif; ?>

                <?php if (canViewUsers()): ?>
                    <a href="clientes.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Clientes</span>
                    </a>
                <?php endif; ?>
                
                <?php if (canManageSettings()): ?>
                    <a href="configuracion.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                        <span>Configuración</span>
                    </a>
                <?php endif; ?>

                <?php if (canViewLogs()): ?>
                    <a href="logs.php"
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>Logs</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php
                        $currentUser = getCurrentUser();
                        echo strtoupper(substr($currentUser['nombre'], 0, 2));
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['nombre']); ?></div>
                        <div class="user-role"><?php echo getRolDisplayName($currentUser['rol']); ?></div>
                    </div>
                </div>
                <form method="GET" action="logout.php" style="margin: 0;">
                    <button type="submit" class="btn-logout">Cerrar Sesión</button>
                </form>
            </div>
        </aside>

        <!-- Bottom Nav Bar for Mobile PWA -->
        <nav class="pwa-bottom-nav" style="display: none;">
            <a href="dashboard.php" class="pwa-bottom-nav__item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Overview</span>
            </a>
            <a href="citas.php" class="pwa-bottom-nav__item <?php echo basename($_SERVER['PHP_SELF']) == 'citas.php' ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span>Citas</span>
            </a>
            <a href="horarios.php" class="pwa-bottom-nav__item <?php echo basename($_SERVER['PHP_SELF']) == 'horarios.php' ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Horarios</span>
            </a>
            <a href="logout.php" class="pwa-bottom-nav__item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Salir</span>
            </a>
        </nav>

        <!-- Main Content -->
        <main class="main-content">

            <?php if (!empty($_GET['success'])): ?>
                <div class="alert-banner alert-success" style="background: rgba(46, 204, 113, 0.12); border: 1px solid #2ECC71; color: #27ae60; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 18px; font-weight: 900;">✓</span> 
                        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; color: #27ae60; cursor: pointer; line-height: 1; padding: 0 4px;">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['error'])): ?>
                <div class="alert-banner alert-danger" style="background: rgba(231, 76, 60, 0.12); border: 1px solid #E74C3C; color: #c0392b; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.15);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 18px; font-weight: 900;">⚠</span> 
                        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; color: #c0392b; cursor: pointer; line-height: 1; padding: 0 4px;">&times;</button>
                </div>
            <?php endif; ?>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const sidebar = document.getElementById('sidebar');
                    const sidebarToggle = document.getElementById('sidebarToggle');
                    const sidebarOverlay = document.getElementById('sidebarOverlay');

                    function toggleSidebar() {
                        sidebar.classList.toggle('active');
                        sidebarOverlay.classList.toggle('active');
                    }

                    if (sidebarToggle) {
                        sidebarToggle.addEventListener('click', toggleSidebar);
                    }

                    if (sidebarOverlay) {
                        sidebarOverlay.addEventListener('click', toggleSidebar);
                    }
                });
            </script>

