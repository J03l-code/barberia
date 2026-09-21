/**
 * KORTZEN - Servicios Compartidos
 * Este módulo maneja los datos de servicios que se comparten entre
 * el dashboard de administración y las páginas públicas.
 */

const SERVICES_STORAGE_KEY = 'kortzen_services';

// Servicios por defecto
const DEFAULT_SERVICES = [
    {
        id: 1,
        name: "Corte Clásico",
        description: "Corte tradicional con técnica de tijera y máquina. Incluye lavado con productos premium y styling final con producto seleccionado.",
        price: 35,
        duration: 45,
        category: "corte",
        icon: "scissors"
    },
    {
        id: 2,
        name: "Corte Premium",
        description: "Experiencia completa que incluye consulta de estilo, corte personalizado, tratamiento capilar y masaje de cuero cabelludo.",
        price: 55,
        duration: 60,
        category: "corte",
        icon: "star"
    },
    {
        id: 3,
        name: "Corte Junior",
        description: "Corte especializado para jóvenes de 10 a 16 años. Incluye lavado y styling adaptado a las tendencias juveniles.",
        price: 25,
        duration: 30,
        category: "corte",
        icon: "user"
    },
    {
        id: 4,
        name: "Afeitado Real",
        description: "Ritual completo de afeitado con navaja barbera. Toallas calientes, pre-shave oil, espuma artesanal y aftershave premium.",
        price: 40,
        duration: 45,
        category: "afeitado",
        icon: "box"
    },
    {
        id: 5,
        name: "Afeitado Express",
        description: "Afeitado rápido y preciso con navaja. Ideal para el caballero con agenda ajustada que no renuncia a la calidad.",
        price: 25,
        duration: 20,
        category: "afeitado",
        icon: "shield"
    },
    {
        id: 6,
        name: "Líneas & Contorno",
        description: "Definición perfecta de patillas, nuca y contorno facial con navaja. El acabado impecable entre cortes.",
        price: 15,
        duration: 15,
        category: "afeitado",
        icon: "grid"
    },
    {
        id: 7,
        name: "Perfilado de Barba",
        description: "Diseño y perfilado personalizado de tu barba. Definición de líneas con navaja y recorte uniforme para un look impecable.",
        price: 20,
        duration: 30,
        category: "barba",
        icon: "book"
    },
    {
        id: 8,
        name: "Tratamiento Hidratante",
        description: "Tratamiento profundo con aceites esenciales y bálsamos nutritivos. Hidrata, suaviza y da brillo a tu barba.",
        price: 25,
        duration: 25,
        category: "barba",
        icon: "info"
    },
    {
        id: 9,
        name: "Pack Barba Completo",
        description: "Experiencia completa: lavado, exfoliación, perfilado con navaja, tratamiento hidratante y styling final.",
        price: 45,
        duration: 45,
        category: "barba",
        icon: "polygon"
    },
    {
        id: 10,
        name: "Facial Revitalizante",
        description: "Limpieza profunda, exfoliación, mascarilla nutritiva y masaje facial. Rejuvenece y revitaliza tu piel.",
        price: 50,
        duration: 45,
        category: "spa",
        icon: "droplet"
    },
    {
        id: 11,
        name: "Ritual Black Tie",
        description: "El tratamiento completo para ocasiones especiales: corte, afeitado real, facial y manicura. El caballero perfecto.",
        price: 120,
        duration: 120,
        category: "spa",
        icon: "users"
    },
    {
        id: 12,
        name: "Masaje Craneal",
        description: "Masaje relajante de cuero cabelludo con aceites esenciales. Alivia el estrés y estimula el crecimiento capilar.",
        price: 25,
        duration: 20,
        category: "spa",
        icon: "coffee"
    }
];

// SVG Icons para los servicios
const SERVICE_ICONS = {
    scissors: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="6" cy="6" r="3"></circle>
        <circle cx="6" cy="18" r="3"></circle>
        <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
        <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
        <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
    </svg>`,
    star: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
    </svg>`,
    user: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
    </svg>`,
    box: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
        <line x1="3" y1="6" x2="21" y2="6"></line>
    </svg>`,
    shield: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
    </svg>`,
    grid: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="3" y1="9" x2="21" y2="9"></line>
        <line x1="9" y1="21" x2="9" y2="9"></line>
    </svg>`,
    book: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
    </svg>`,
    info: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 16v-4"></path>
        <path d="M12 8h.01"></path>
    </svg>`,
    polygon: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
    </svg>`,
    droplet: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
    </svg>`,
    users: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>`,
    coffee: `<svg class="service-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
        <line x1="6" y1="1" x2="6" y2="4"></line>
        <line x1="10" y1="1" x2="10" y2="4"></line>
        <line x1="14" y1="1" x2="14" y2="4"></line>
    </svg>`,
    // Default icon for highlights
    bag: `<svg class="highlight-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <path d="M16 10a4 4 0 0 1-8 0"></path>
    </svg>`,
    clock: `<svg class="highlight-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 6v6l4 2"></path>
    </svg>`,
    starHighlight: `<svg class="highlight-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
    </svg>`
};

// Etiquetas de categorías
const CATEGORY_LABELS = {
    corte: 'Corte & Estilo',
    afeitado: 'Afeitado Tradicional',
    barba: 'Cuidado de Barba',
    spa: 'Tratamientos Spa'
};

/**
 * Carga servicios desde localStorage o usa los por defecto
 */
function loadServices() {
    try {
        // Primero intentar cargar del localStorage principal del dashboard
        const dashboardData = localStorage.getItem('kortzen_db');
        console.log('🔍 KORTZEN Services: Buscando datos en localStorage...');

        if (dashboardData) {
            const parsed = JSON.parse(dashboardData);
            if (parsed.services && parsed.services.length > 0) {
                console.log('✅ KORTZEN Services: Datos encontrados en kortzen_db, ' + parsed.services.length + ' servicios');
                // Mergear con descripciones por defecto si faltan
                return parsed.services.map(s => {
                    const defaultService = DEFAULT_SERVICES.find(ds => ds.id === s.id);
                    return {
                        ...defaultService,
                        ...s,
                        description: s.description || defaultService?.description || '',
                        icon: s.icon || defaultService?.icon || 'star'
                    };
                });
            }
        }

        // Si no hay datos en dashboard, intentar cargar servicios independientes
        const stored = localStorage.getItem(SERVICES_STORAGE_KEY);
        if (stored) {
            console.log('✅ KORTZEN Services: Datos encontrados en kortzen_services');
            return JSON.parse(stored);
        }
    } catch (e) {
        console.warn('⚠️ Error loading services:', e);
    }

    console.log('📦 KORTZEN Services: Usando datos por defecto');
    return [...DEFAULT_SERVICES];
}

/**
 * Guarda servicios en localStorage
 */
function saveServices(services) {
    try {
        localStorage.setItem(SERVICES_STORAGE_KEY, JSON.stringify(services));
        return true;
    } catch (e) {
        console.error('Error saving services:', e);
        return false;
    }
}

/**
 * Obtiene servicios por categoría
 */
function getServicesByCategory(category) {
    const services = loadServices();
    return services.filter(s => s.category === category);
}

/**
 * Obtiene servicios destacados (uno de cada categoría principal)
 */
function getHighlightedServices() {
    const services = loadServices();
    const highlighted = [];

    // Obtener "Corte Clásico" o el primer corte
    const corte = services.find(s => s.name === "Corte Clásico") ||
        services.find(s => s.category === "corte");
    if (corte) highlighted.push({ ...corte, highlightIcon: 'bag' });

    // Obtener "Afeitado Real" o el primer afeitado  
    const afeitado = services.find(s => s.name === "Afeitado Real") ||
        services.find(s => s.category === "afeitado");
    if (afeitado) highlighted.push({ ...afeitado, highlightIcon: 'clock' });

    // Obtener "Facial Revitalizante" o el primer spa
    const spa = services.find(s => s.name === "Facial Revitalizante") ||
        services.find(s => s.category === "spa");
    if (spa) highlighted.push({ ...spa, highlightIcon: 'starHighlight' });

    return highlighted;
}

/**
 * Renderiza un card de servicio para servicios.html
 */
function renderServiceCard(service) {
    const icon = SERVICE_ICONS[service.icon] || SERVICE_ICONS.star;
    return `
        <article class="service-card" data-reveal>
            ${icon}
            <h3 class="service-card__title">${service.name}</h3>
            <p class="service-card__description">${service.description}</p>
            <div class="service-card__meta">
                <span class="service-card__duration">⏱ ${service.duration} min</span>
                <span class="service-card__price">$${service.price}</span>
            </div>
            <a href="/reservar.php" class="btn btn--secondary btn--sm service-card__btn" style="width: 100%;">Reservar</a>
        </article>
    `;
}

/**
 * Renderiza un card destacado para index.html
 */
function renderHighlightCard(service) {
    const icon = SERVICE_ICONS[service.highlightIcon] || SERVICE_ICONS.starHighlight;

    // Descripción corta para highlights
    const shortDesc = service.description.length > 100
        ? service.description.substring(0, 100) + '...'
        : service.description;

    return `
        <article class="highlight-card" data-reveal>
            ${icon}
            <h3 class="highlight-card__title">${service.name}</h3>
            <p class="highlight-card__description">${shortDesc}</p>
            <a href="/servicios.html" class="btn btn--ghost btn--sm">Desde $${service.price}</a>
        </article>
    `;
}

/**
 * Renderiza todos los servicios en servicios.html
 */
function renderAllServicesPage() {
    const services = loadServices();
    const categories = ['corte', 'afeitado', 'barba', 'spa'];

    categories.forEach(category => {
        const container = document.querySelector(`#services-${category}`);
        if (container) {
            const categoryServices = services.filter(s => s.category === category);
            container.innerHTML = categoryServices.map(s => renderServiceCard(s)).join('');
        }
    });
}

/**
 * Renderiza servicios destacados en index.html
 */
function renderHighlightedServicesHome() {
    const container = document.querySelector('#highlighted-services');
    if (!container) return;

    const highlighted = getHighlightedServices();
    container.innerHTML = highlighted.map(s => renderHighlightCard(s)).join('');
}

// Exponer funciones globalmente para uso en las páginas
window.KortzenServices = {
    load: loadServices,
    save: saveServices,
    getByCategory: getServicesByCategory,
    getHighlighted: getHighlightedServices,
    renderServiceCard,
    renderHighlightCard,
    renderAllServicesPage,
    renderHighlightedServicesHome,
    CATEGORY_LABELS,
    SERVICE_ICONS
};
