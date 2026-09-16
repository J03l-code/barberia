/**
 * KORTZEN - Selector de Sucursales Dinámico (Activas & Próximamente)
 */

const BRANCH_STORAGE_KEY = 'kortzen_selected_branch';
let cachedBranches = [];
let currentSelectedBranch = null;

/**
 * Cargar sucursales desde la API backend
 */
async function fetchBranches() {
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3500);

        const response = await fetch('/api/get_sucursales.php?t=' + Date.now(), { signal: controller.signal });
        clearTimeout(timeoutId);
        const json = await response.json();
        if (json.success && json.data && json.data.length > 0) {
            cachedBranches = json.data;
            // Actualizar la referencia de sucursal seleccionada con los datos más recientes del servidor
            const savedId = localStorage.getItem(BRANCH_STORAGE_KEY);
            if (savedId) {
                const refreshed = cachedBranches.find(b => b.id == savedId && !b.isProximamente && b.estado === 'activo');
                if (refreshed) {
                    currentSelectedBranch = refreshed;
                }
            }
            return cachedBranches;
        }
    } catch (e) {
        console.warn('Error o timeout al cargar sucursales:', e);
    }

    // Fallback por defecto si no hay conexión
    cachedBranches = [
        {
            id: 1,
            name: "KORTZEN Llano Chico",
            address: "Calle 17 de septiembre, frente a la casa de colchon, Llano Chico, Quito",
            phone: "+593 098 842 2770",
            openTime: "10:00",
            closeTime: "20:00",
            isProximamente: false,
            estado: "activo"
        }
    ];
    return cachedBranches;
}

/**
 * Obtener la sucursal seleccionada actualmente
 */
function getSelectedBranch() {
    const savedId = localStorage.getItem(BRANCH_STORAGE_KEY);
    if (savedId) {
        if (cachedBranches.length > 0) {
            const found = cachedBranches.find(b => b.id == savedId && !b.isProximamente && b.estado === 'activo');
            if (found) {
                currentSelectedBranch = found;
                return currentSelectedBranch;
            }
        } else {
            return currentSelectedBranch || { id: parseInt(savedId) };
        }
    }

    if (currentSelectedBranch && cachedBranches.length > 0) {
        const refreshed = cachedBranches.find(b => b.id == currentSelectedBranch.id);
        if (refreshed) {
            currentSelectedBranch = refreshed;
            return currentSelectedBranch;
        }
    }

    // Seleccionar la primera activa si existe
    const firstActive = cachedBranches.find(b => !b.isProximamente && b.estado === 'activo') || cachedBranches[0];
    if (firstActive) {
        currentSelectedBranch = firstActive;
        localStorage.setItem(BRANCH_STORAGE_KEY, firstActive.id);
    }
    return currentSelectedBranch || { id: 1 };
}

/**
 * Guardar selección de sucursal
 */
function setSelectedBranch(branchId) {
    const branch = cachedBranches.find(b => b.id == branchId);
    if (!branch || branch.isProximamente || branch.estado === 'inactivo') return false;

    currentSelectedBranch = branch;
    localStorage.setItem(BRANCH_STORAGE_KEY, branch.id);
    updateBranchInfoBar();
    updateGlobalFooter(branch);

    // Emitir evento personalizado para recalcular barberos y servicios en reservar.php / nosotros.html
    window.dispatchEvent(new CustomEvent('kortzen:branchChanged', { detail: { ...branch, id: branch.id, branchId: branch.id } }));
    return true;
}

/**
 * Crear e inyectar el Modal Selector de Sucursales
 */
function createBranchSelectorModal() {
    closeBranchModal();

    const activeBranches = cachedBranches.filter(b => !b.isProximamente && b.estado === 'activo');
    const proximamenteBranches = cachedBranches.filter(b => b.isProximamente || b.estado === 'proximamente');

    const modalHTML = `
        <div id="branch-modal" class="branch-modal-overlay" onclick="if(event.target === this) closeBranchModal();" style="position: fixed; inset: 0; z-index: 100000; background: rgba(0, 0, 0, 0.88); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; padding: 12px; animation: fadeIn 0.3s ease; font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; box-sizing: border-box;">
            <div class="branch-modal-card" style="background: #0D0D0D; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; max-width: 540px; width: 100%; max-height: 90vh; overflow-y: auto; color: #FFFFFF; padding: 1.5rem 1.25rem; box-shadow: 0 30px 60px rgba(0, 0, 0, 0.95); position: relative; box-sizing: border-box;">
                
                <!-- BOTÓN X PARA CERRAR -->
                <button onclick="closeBranchModal()" aria-label="Cerrar" style="position: absolute; top: 16px; right: 16px; background: transparent; border: none; color: #888888; cursor: pointer; padding: 8px; transition: color 0.2s ease;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#888888'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

                <div style="text-align: center; margin-bottom: 1.5rem; padding-top: 6px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: #FFFFFF; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 4px; opacity: 0.9;">
                        KORTZEN BARBERÍA
                    </div>
                    <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0 0 6px 0; color: #FFFFFF; letter-spacing: 0.5px; text-transform: uppercase;">
                        SELECCIONA TU SUCURSAL
                    </h2>
                    <p style="font-size: 0.82rem; color: #888888; margin: 0; line-height: 1.4;">
                        Selecciona el local donde deseas agendar tu próximo corte o servicio.
                    </p>
                </div>

                <div class="branch-list" style="display: flex; flex-direction: column; gap: 14px; box-sizing: border-box;">
                    <!-- SUCURSALES ACTIVAS -->
                    ${activeBranches.map(branch => {
                        const isSelected = currentSelectedBranch && currentSelectedBranch.id == branch.id;
                        return `
                        <div class="branch-card ${isSelected ? 'is-selected' : ''}" 
                             onclick="selectBranchFromModal(${branch.id})"
                             style="background: #141414; border: 2px solid ${isSelected ? '#FFFFFF' : 'rgba(255, 255, 255, 0.12)'}; border-radius: 14px; padding: 1.1rem; cursor: pointer; transition: all 0.25s ease; position: relative; box-sizing: border-box;"
                             onmouseover="if(!${isSelected}) this.style.borderColor='rgba(255,255,255,0.4)'"
                             onmouseout="if(!${isSelected}) this.style.borderColor='rgba(255,255,255,0.12)'">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 180px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
                                        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0; color: #FFFFFF; letter-spacing: -0.2px; white-space: nowrap;">${branch.name}</h3>
                                        <span style="background: rgba(255, 255, 255, 0.1); color: #FFFFFF; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.6rem; font-weight: 800; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">ABIERTO</span>
                                    </div>
                                    <p style="font-size: 0.8rem; color: #CCCCCC; margin: 0 0 6px 0; line-height: 1.35; display: flex; align-items: flex-start; gap: 6px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" style="flex-shrink:0; margin-top:2px;">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span>${branch.address}</span>
                                    </p>
                                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.75rem; color: #888888; flex-wrap: wrap;">
                                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#888888" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                            ${branch.openTime} - ${branch.closeTime}
                                        </span>
                                        <span>|</span>
                                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#888888" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            ${branch.phone}
                                        </span>
                                    </div>
                                </div>
                                <button style="background: ${isSelected ? '#FFFFFF' : 'transparent'}; color: ${isSelected ? '#000000' : '#FFFFFF'}; border: 1px solid #FFFFFF; border-radius: 8px; padding: 8px 14px; font-size: 0.72rem; font-weight: 800; letter-spacing: 1px; cursor: pointer; white-space: nowrap; transition: all 0.2s ease; align-self: center; flex-shrink: 0;">
                                    ${isSelected ? 'SELECCIONADO' : 'INGRESAR'}
                                </button>
                            </div>
                        </div>
                    `;
                    }).join('')}

                    <!-- SUCURSALES PRÓXIMAMENTE (NEGRO Y BLANCO ELEGANTE) -->
                    ${proximamenteBranches.map(branch => `
                        <div class="branch-card is-disabled" 
                             style="background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.2); border-radius: 14px; padding: 1.1rem; opacity: 0.7; position: relative; cursor: not-allowed; box-sizing: border-box;">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 180px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
                                        <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: #FFFFFF; white-space: nowrap;">${branch.name}</h3>
                                        <span style="background: rgba(255, 255, 255, 0.05); color: #AAAAAA; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.6rem; font-weight: 800; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            PRÓXIMAMENTE
                                        </span>
                                    </div>
                                    <p style="font-size: 0.8rem; color: #888888; margin: 0 0 4px 0; line-height: 1.35; display: flex; align-items: flex-start; gap: 6px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#888888" stroke-width="2" style="flex-shrink:0; margin-top:2px;">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span>${branch.address}</span>
                                    </p>
                                    <p style="font-size: 0.75rem; color: #666666; margin: 0; font-style: italic;">
                                        Próximamente apertura de nueva sucursal. ¡Muy pronto cerca de ti!
                                    </p>
                                </div>
                                <button disabled style="background: rgba(255, 255, 255, 0.03); color: #555555; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 8px 12px; font-size: 0.68rem; font-weight: 700; cursor: not-allowed; white-space: nowrap; align-self: center; flex-shrink: 0;">
                                    NO DISPONIBLE
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>

                ${currentSelectedBranch ? `
                    <div style="margin-top: 1.5rem; text-align: center;">
                        <button onclick="closeBranchModal()" style="background: transparent; color: #777777; border: none; font-size: 0.8rem; cursor: pointer; text-decoration: underline; font-family: inherit; transition: color 0.2s ease;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#777777'">
                            Continuar navegación con ${currentSelectedBranch.name}
                        </button>
                    </div>
                ` : ''}
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Seleccionar sucursal desde el modal
 */
function selectBranchFromModal(branchId) {
    if (setSelectedBranch(branchId)) {
        closeBranchModal();
    }
}

/**
 * Cerrar modal de sucursal
 */
function closeBranchModal() {
    sessionStorage.setItem('kortzen_branch_dismissed', 'true');
    const modal = document.getElementById('branch-modal');
    if (modal) modal.remove();
}

// Escuchar tecla ESC para cerrar el modal de sucursales en cualquier momento
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') {
        const modal = document.getElementById('branch-modal');
        if (modal) {
            closeBranchModal();
        }
    }
});

/**
 * Actualizar la barra superior informativa de sucursal
 */
function updateBranchInfoBar() {
    const branch = getSelectedBranch();
    if (!branch) return;

    // Purga estricta de cualquier barra previa en el DOM para evitar duplicados
    document.querySelectorAll('.branch-info-bar').forEach(bar => bar.remove());

    const isMobile = window.innerWidth <= 768;

    const barHTML = `
        <div class="branch-info-bar" style="position: fixed; top: 0; left: 0; right: 0; z-index: 10001; background: #FFFFFF; color: #111111; border-bottom: 1px solid rgba(0, 0, 0, 0.08); height: 42px; min-height: 42px; max-height: 42px; display: flex; align-items: center; backdrop-filter: blur(10px); font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; overflow: hidden;">
            <div class="branch-info-bar__content" style="max-width: 1200px; width: 100%; margin: 0 auto; padding: 0 ${isMobile ? '0.6rem' : '1rem'}; display: flex; align-items: center; justify-content: space-between; font-size: ${isMobile ? '0.72rem' : '0.82rem'}; white-space: nowrap; height: 100%;">
                
                <!-- SUCURSAL SELECTOR (BOTÓN ULTRA LUXURY Y MINIMALISTA) -->
                <div onclick="window.KortzenBranches.showSelector()" 
                     style="display: inline-flex; align-items: center; gap: 0.3rem; color: #111111; cursor: pointer; user-select: none; white-space: nowrap; flex-shrink: 0;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span class="branch-info-bar__name" style="font-weight: 700; letter-spacing: -0.2px; white-space: nowrap !important; display: inline-block; max-width: ${isMobile ? '120px' : 'none'}; overflow: hidden; text-overflow: ellipsis; vertical-align: middle;">${branch.name}</span>
                    <span class="branch-info-bar__btn" style="border: 1px solid rgba(0, 0, 0, 0.22); background: transparent; color: #111111; font-size: ${isMobile ? '0.58rem' : '0.66rem'}; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; padding: 2px 6px; border-radius: 4px; margin-left: 8px; display: inline-flex; align-items: center; gap: 2px; transition: all 0.25s ease; white-space: nowrap; flex-shrink: 0;"
                          onmouseover="this.style.background='#111111'; this.style.color='#FFFFFF'; this.style.borderColor='#111111';"
                          onmouseout="this.style.background='transparent'; this.style.color='#111111'; this.style.borderColor='rgba(0, 0, 0, 0.22)';">
                        <span>CAMBIAR</span>
                        <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </span>
                </div>

                <!-- DETALLES: HORARIO, TELÉFONO Y USUARIO -->
                <div class="branch-info-bar__details" style="display: inline-flex; align-items: center; gap: ${isMobile ? '0.4rem' : '1.2rem'}; color: #555555; white-space: nowrap; flex-shrink: 0;">
                    ${!isMobile ? `
                    <span class="branch-info-bar__item branch-info-bar__item--hours" style="display: inline-flex; align-items: center; gap: 0.4rem; white-space: nowrap;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        ${branch.openTime} - ${branch.closeTime}
                    </span>
                    <span class="branch-info-bar__item branch-info-bar__item--phone" style="display: inline-flex; align-items: center; gap: 0.4rem; white-space: nowrap;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        ${branch.phone}
                    </span>
                    ` : ''}
                    <div id="header-user-profile-badge" style="display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;"></div>
                </div>
            </div>
        </div>
    `;

    const header = document.querySelector('.header');
    if (header) {
        header.insertAdjacentHTML('beforebegin', barHTML);
        document.body.classList.add('has-branch-bar');
        header.style.top = '42px';
    }

    // Auto-cargar perfil del usuario si está iniciada la sesión (O mostrar Mi Perfil si es invitado)
    fetch('/api/get_client_profile.php')
        .then(r => r.json())
        .then(res => {
            // Eliminar cualquier elemento de perfil previo o duplicado en la barra
            document.querySelectorAll('.branch-info-bar a[href*="cliente-dashboard"], .branch-info-bar a[href*="cliente-login"]').forEach(el => el.remove());
            document.querySelectorAll('#header-user-profile-badge').forEach((b, idx) => {
                if (idx > 0) b.remove();
            });

            const badge = document.getElementById('header-user-profile-badge');
            if (!badge) return;

            badge.innerHTML = ''; // Limpieza total de HTML previo

            if (res.success && res.cliente) {
                const firstName = res.cliente.nombre.split(' ')[0];
                const initial = firstName.charAt(0).toUpperCase();
                const fotoUrl = res.cliente.foto || res.cliente.foto_perfil;
                const fotoHtml = fotoUrl ? 
                    `<img src="${fotoUrl}" referrerpolicy="no-referrer" style="width:22px; height:22px; border-radius:50%; object-fit:cover; border: 1px solid #111; flex-shrink: 0;" alt="Avatar">` :
                    `<div style="width:22px; height:22px; border-radius:50%; background:#111111; color:#FFFFFF; font-size:0.65rem; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink: 0;">${initial}</div>`;

                badge.innerHTML = `
                    <span style="border-left: 1px solid rgba(0,0,0,0.12); height: 16px; margin: 0 2px; flex-shrink: 0;"></span>
                    <a href="cliente-dashboard.php" class="single-user-profile-link" onclick="event.preventDefault(); window.location.href='cliente-dashboard.php';" 
                       style="display:inline-flex; align-items:center; gap:5px; color:#111111 !important; text-decoration:none; font-weight:800; font-size:0.78rem; white-space:nowrap; cursor:pointer !important; z-index:100002; pointer-events:auto !important; position:relative;"
                       title="Ver mi cuenta">
                        ${fotoHtml}
                        <span style="color:#111111 !important; font-weight:800 !important;">${firstName}</span>
                    </a>
                    <a href="logout.php" class="single-user-logout-link" title="Cerrar sesión" 
                       style="display:inline-flex; align-items:center; gap:3px; color:#dc3545 !important; background:rgba(220,53,69,0.08); padding:2px 7px; border-radius:4px; font-size:0.68rem; font-weight:800; text-decoration:none; margin-left:4px; border:1px solid rgba(220,53,69,0.25); transition:all 0.2s; white-space:nowrap; cursor:pointer !important; z-index:100002; pointer-events:auto !important;"
                       onmouseover="this.style.background='#dc3545'; this.style.color='#FFFFFF';"
                       onmouseout="this.style.background='rgba(220,53,69,0.08)'; this.style.color='#dc3545';">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>Salir</span>
                    </a>
                `;

                // Actualizar CTA en la cabecera principal y móvil si existen
                document.querySelectorAll('.header__cta, .mobile-nav__cta').forEach(btn => {
                    btn.href = '/reservar.php';
                });

                // Agregar enlace en navegación móvil si no existe
                const mobileNavLinks = document.querySelector('.mobile-nav__links');
                if (mobileNavLinks && !mobileNavLinks.querySelector('.mobile-nav__link--logout')) {
                    const accLink = document.createElement('a');
                    accLink.href = '/cliente-dashboard.php';
                    accLink.className = 'mobile-nav__link mobile-nav__link--account';
                    accLink.textContent = 'Mi Cuenta';
                    mobileNavLinks.appendChild(accLink);

                    const logoutLink = document.createElement('a');
                    logoutLink.href = '/logout.php';
                    logoutLink.className = 'mobile-nav__link mobile-nav__link--logout';
                    logoutLink.style.color = '#dc3545';
                    logoutLink.textContent = 'Cerrar Sesión';
                    mobileNavLinks.appendChild(logoutLink);
                }
            } else {
                // NO LOGUEADO: Mostrar botón "Mi Perfil" redirigiendo a cliente-login.php
                badge.innerHTML = `
                    <span style="border-left: 1px solid rgba(0,0,0,0.12); height: 16px; margin: 0 2px; flex-shrink: 0;"></span>
                    <a href="cliente-login.php" class="single-user-profile-link" onclick="event.preventDefault(); window.location.href='cliente-login.php';" 
                       style="display:inline-flex; align-items:center; gap:5px; color:#111111 !important; text-decoration:none; font-weight:800; font-size:0.75rem; border:1.5px solid #111111; padding:3px 9px; border-radius:20px; transition:all 0.2s ease; white-space:nowrap; cursor:pointer !important; z-index:100002; pointer-events:auto !important; position:relative;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <span style="color:#111111 !important; font-weight:800 !important;">Mi Perfil</span>
                    </a>
                `;
            }
        }).catch(() => {
            document.querySelectorAll('.branch-info-bar a[href*="cliente-dashboard"], .branch-info-bar a[href*="cliente-login"]').forEach(el => el.remove());
            const badge = document.getElementById('header-user-profile-badge');
            if (badge) {
                badge.innerHTML = `
                    <span style="border-left: 1px solid rgba(0,0,0,0.12); height: 16px; margin: 0 2px; flex-shrink: 0;"></span>
                    <a href="cliente-login.php" class="single-user-profile-link" onclick="event.preventDefault(); window.location.href='cliente-login.php';" 
                       style="display:inline-flex; align-items:center; gap:5px; color:#111111 !important; text-decoration:none; font-weight:800; font-size:0.75rem; border:1.5px solid #111111; padding:3px 9px; border-radius:20px; transition:all 0.2s ease; white-space:nowrap; cursor:pointer !important; z-index:100002; pointer-events:auto !important; position:relative;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <span style="color:#111111 !important; font-weight:800 !important;">Mi Perfil</span>
                    </a>
                `;
            }
        });
}

/**
 * Actualiza el Footer global y las secciones informativas en todas las páginas según la sucursal seleccionada
 */
function updateGlobalFooter(branch) {
    if (!branch) return;

    const branchName = branch.name || branch.nombre || 'KORTZEN';
    const branchAddress = branch.address || branch.direccion || 'Llano Chico, Quito, Ecuador';
    const branchPhone = branch.phone || branch.telefono || '+593 98 842 2770';
    const open = branch.openTime || '10:00';
    const close = branch.closeTime || '20:00';
    const scheduleText = `Lunes a Domingo: ${open} - ${close}`;
    const scheduleShort = `Lun - Dom: ${open} - ${close}`;

    // 1. Nombres dinámicos
    document.querySelectorAll('[data-branch-dynamic="name"]').forEach(el => {
        el.textContent = branchName;
    });

    // 2. Dirección en Footer y Contacto
    const addressElems = document.querySelectorAll('[data-branch-dynamic="address"], .footer__branch-address');
    addressElems.forEach(el => {
        el.textContent = branchAddress;
    });

    // 3. Teléfono / WhatsApp en Footer y Contacto
    const phoneElems = document.querySelectorAll('[data-branch-dynamic="phone"], .footer__branch-phone');
    phoneElems.forEach(el => {
        el.textContent = branchPhone;
        if (el.tagName === 'A') {
            const cleanDigits = branchPhone.replace(/\D/g, '');
            el.href = cleanDigits ? `tel:+${cleanDigits}` : '#';
        }
    });

    // 4. Horarios en Footer y Contacto
    const hoursElems = document.querySelectorAll('[data-branch-dynamic="hours"], .footer__schedule-time, .footer__branch-hours');
    hoursElems.forEach(el => {
        el.textContent = el.classList.contains('contact-info__value') ? scheduleText : scheduleShort;
    });

    // 5. Mapa Google Maps en Footer y Contacto
    const mapIframes = document.querySelectorAll('#footer-map-iframe, [data-branch-dynamic="map"], .contact-map iframe');
    mapIframes.forEach(mapIframe => {
        if (branch.mapa_url && branch.mapa_url.length > 10) {
            let finalMapUrl = branch.mapa_url.trim();
            if (!finalMapUrl.includes('/embed') && (finalMapUrl.includes('google.com/maps') || finalMapUrl.includes('goo.gl/maps'))) {
                finalMapUrl = `https://maps.google.com/maps?q=${encodeURIComponent(finalMapUrl)}&output=embed`;
            }
            mapIframe.src = finalMapUrl;
            const container = mapIframe.closest('.footer__map-section, .contact-map') || mapIframe.parentElement;
            if (container) container.style.display = 'block';
        } else if (branch.id == 1) {
            mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.8071991201023!2d-78.44604192503535!3d-0.13528119986338483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d58fc52de96153%3A0x35f5708deeee0cf7!2sKORTZEN!5e0!3m2!1sen!2sec!4v1786588668585!5m2!1sen!2sec";
            const container = mapIframe.closest('.footer__map-section, .contact-map') || mapIframe.parentElement;
            if (container) container.style.display = 'block';
        } else {
            const container = mapIframe.closest('.footer__map-section, .contact-map') || mapIframe.parentElement;
            if (container && mapIframe.id === 'footer-map-iframe') container.style.display = 'none';
        }
    });
}

/**
 * Inicializar selector de sucursales
 */
async function initBranchSelector() {
    await fetchBranches();
    
    // Obtener sucursal activa
    const selected = getSelectedBranch();

    const isDashboardPage = window.location.pathname.includes('barber-dashboard') || document.body.classList.contains('pwa-app-mode');

    // Desplegar modal automáticamente al cargar la página si no se ha seleccionado en esta sesión
    if (!isDashboardPage && !sessionStorage.getItem('kortzen_branch_dismissed')) {
        setTimeout(() => {
            createBranchSelectorModal();
        }, 300);
    }

    // Actualizar la barra superior e información del footer
    if (!isDashboardPage) {
        updateBranchInfoBar();
        if (selected) updateGlobalFooter(selected);
    }

    // Emitir evento para notificar a team-loader y reservar.php con los datos completos de la sucursal activa
    if (selected && selected.id) {
        window.dispatchEvent(new CustomEvent('kortzen:branchChanged', { detail: { ...selected, id: selected.id, branchId: selected.id } }));
    }
}

// Exponer en objeto global
window.KortzenBranches = {
    init: initBranchSelector,
    showSelector: function() {
        createBranchSelectorModal();
    },
    getSelected: getSelectedBranch,
    setSelected: setSelectedBranch,
    loadAll: function() { return cachedBranches; },
    updateInfoBar: updateBranchInfoBar,
    updateFooter: updateGlobalFooter
};

// Iniciar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    initBranchSelector();
});
