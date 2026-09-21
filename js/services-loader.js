/**
 * KORTZEN - Services Loader (100% Dinámico desde el Dashboard)
 * Carga dinámicamente todas las categorías y servicios configurados en el Dashboard
 */

const ServicesLoader = {
    init: function () {
        document.addEventListener('DOMContentLoaded', () => {
            this.loadServices();
        });
    },

    loadServices: async function () {
        // 1. Contenedor de servicios destacados (página de Inicio)
        const indexContainer = document.querySelector('.services-wrapper') || document.getElementById('highlighted-services');

        // 2. Contenedor dinámico principal en servicios.html
        const dynamicContainer = document.getElementById('dynamic-services-container');

        // 3. Contenedores específicos legacy
        const legacyContainers = {
            'Corte': document.getElementById('services-corte'),
            'Afeitado': document.getElementById('services-afeitado'),
            'Barba': document.getElementById('services-barba'),
            'Spa': document.getElementById('services-spa'),
            'Otros': document.getElementById('services-otros')
        };

        try {
            const response = await fetch('/api/get_servicios_public.php');
            const result = await response.json();

            if (result.success) {
                // Si estamos en la página de inicio (Index)
                if (indexContainer && result.data && result.data.length > 0) {
                    let featuredServices = result.data.filter(s => s.destacado == 1);
                    if (featuredServices.length === 0) {
                        featuredServices = result.data.slice(0, 3);
                    } else if (featuredServices.length > 3) {
                        featuredServices = featuredServices.slice(0, 3);
                    }
                    this.renderServices(featuredServices, indexContainer);
                }

                // Si estamos en la página de servicios (servicios.html) con contenedor dinámico
                if (dynamicContainer) {
                    this.renderDynamicCategories(result.categories || [], result.data || [], dynamicContainer);
                } else if (result.data && result.data.length > 0) {
                    // Fallback a contenedores legacy
                    this.renderCategorizedServices(result.data, legacyContainers);
                }
            }
        } catch (error) {
            console.error('Error cargando servicios:', error);
        }
    },

    // Renderiza todas las categorías configuradas dinámicamente desde el dashboard
    renderDynamicCategories: function (categoriesList, allServices, mainContainer) {
        mainContainer.innerHTML = '';

        // Agrupar servicios por categoría
        const grouped = {};
        const catMeta = {};

        // 1. Inicializar con todas las categorías activas del dashboard en su orden exacto
        categoriesList.forEach(c => {
            grouped[c.nombre] = [];
            catMeta[c.nombre] = c;
        });

        // 2. Clasificar cada servicio en su categoría correspondiente
        allServices.forEach(service => {
            const cat = service.categoria || 'General';
            if (!grouped[cat]) {
                grouped[cat] = [];
                catMeta[cat] = {
                    nombre: cat,
                    descripcion: service.cat_descripcion || '',
                    orden: service.cat_orden || 999
                };
            }
            grouped[cat].push(service);
        });

        // 3. Renderizar cada categoría del dashboard (incluso si tiene 0 servicios)
        const allCategoryKeys = Object.keys(grouped);
        let sectionIndex = 0;

        allCategoryKeys.forEach(catName => {
            const services = grouped[catName] || [];
            const meta = catMeta[catName] || {};
            const slug = this.slugify(catName);
            const isCharcoalBg = (sectionIndex % 2 === 1);

            const section = document.createElement('section');
            section.className = 'section service-category';
            section.id = slug;
            section.setAttribute('aria-labelledby', `${slug}-title`);
            if (isCharcoalBg) {
                section.style.backgroundColor = 'var(--color-charcoal, #1A1A1A)';
            }

            const containerDiv = document.createElement('div');
            containerDiv.className = 'container';

            let descHtml = '';
            if (meta.descripcion && meta.descripcion.trim() !== '') {
                descHtml = `<p style="color: var(--color-gray, #999999); font-size: 0.95rem; line-height: 1.5; margin-top: -1rem; margin-bottom: 2rem;">${meta.descripcion}</p>`;
            }

            let contentHtml = '';
            if (services.length > 0) {
                contentHtml = `<div class="services-grid" id="services-${slug}"></div>`;
            } else {
                contentHtml = `
                    <div style="text-align: center; color: var(--color-gray, #888888); padding: 3rem 1.5rem; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.12); border-radius: 12px; margin: 1rem 0;">
                        <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--color-white, #FFFFFF); margin: 0 0 0.5rem 0; font-family: var(--font-display, 'Playfair Display', serif);">Próximamente nuevos tratamientos</h4>
                        <p style="font-size: 0.9rem; color: var(--color-gray, #999999); max-width: 440px; margin: 0 auto; line-height: 1.5;">Estamos configurando los servicios para esta categoría.</p>
                    </div>
                `;
            }

            containerDiv.innerHTML = `
                <h2 id="${slug}-title" class="service-category__title">
                    <span>${meta.nombre || catName}</span>
                </h2>
                ${descHtml}
                ${contentHtml}
            `;

            if (services.length > 0) {
                const grid = containerDiv.querySelector(`#services-${slug}`);
                services.forEach(service => {
                    this.renderServiceCard(service, grid);
                });
            }

            section.appendChild(containerDiv);
            mainContainer.appendChild(section);
            sectionIndex++;
        });

        // Trigger reveal animation
        if (window.initScrollReveal) {
            setTimeout(() => {
                const elements = mainContainer.querySelectorAll('[data-reveal]');
                elements.forEach(el => el.classList.add('revealed'));
            }, 100);
        }
    },

    slugify: function (text) {
        return (text || '').toString().toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    },

    // Render logic para contenedores legacy
    renderCategorizedServices: function (services, containers) {
        Object.values(containers).forEach(c => { if (c) c.innerHTML = ''; });

        services.forEach(service => {
            const catRaw = service.categoria || 'Otros';
            const cat = catRaw.toLowerCase();

            let targetContainer = null;
            if (cat.includes('corte')) targetContainer = containers['Corte'];
            else if (cat.includes('afeitado')) targetContainer = containers['Afeitado'];
            else if (cat.includes('barba')) targetContainer = containers['Barba'];
            else if (cat.includes('spa') || cat.includes('facial')) targetContainer = containers['Spa'];
            else targetContainer = containers['Otros'];

            if (targetContainer) {
                this.renderServiceCard(service, targetContainer);
            }
        });
    },

    renderServiceCard: function (service, container) {
        const card = document.createElement('div');
        card.className = 'service-card revealed';
        card.setAttribute('data-reveal', '');
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';

        const price = parseFloat(service.precio).toFixed(2);
        const cat = service.categoria || 'General';
        const tagHtml = service.tag ? `<span class="service-card__tag-badge">${service.tag}</span>` : '';
        const beneficioHtml = service.beneficio ? `<span class="service-card__benefit-text">${service.beneficio}</span>` : '';

        // Formatear ¿Qué incluye?
        let incluyeHtml = '';
        if (service.que_incluye && service.que_incluye.trim() !== '') {
            const rawItems = service.que_incluye.split(/\r?\n/).map(i => i.trim()).filter(i => i.length > 0);
            if (rawItems.length > 0) {
                const itemsList = rawItems.map(item => {
                    const cleanItem = item.replace(/^[•\-\*\✓\s]+/, '').trim();
                    return `<li class="service-card__includes-item"><span class="service-card__includes-icon">✓</span><span>${cleanItem}</span></li>`;
                }).join('');

                incluyeHtml = `
                    <div class="service-card__includes">
                        <div class="service-card__includes-title">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>¿Qué incluye?</span>
                        </div>
                        <ul class="service-card__includes-list">
                            ${itemsList}
                        </ul>
                    </div>
                `;
            }
        }

        card.innerHTML = `
            <div class="service-card__image-wrapper">
                 <img src="${service.foto_url || '/assets/images/service-placeholder.jpg'}" alt="${service.nombre}" class="service-card__image" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(service.nombre)}&background=333333&color=fff&size=128'">
                 ${tagHtml}
            </div>
            
            <div class="service-card__content">
                <span class="service-card__category">${cat}</span>
                <h3 class="service-card__title">${service.nombre}</h3>
                ${beneficioHtml}
                <p class="service-card__desc">${service.descripcion || ''}</p>
                ${incluyeHtml}
                <div class="service-card__footer">
                    <span class="service-card__price">$${price}</span>
                    <span class="service-card__duration">${service.duracion_minutos} min</span>
                </div>
                <a href="/reservar.php" class="btn btn--secondary btn--sm service-card__btn">Reservar</a>
            </div>
        `;
        container.appendChild(card);
    },

    renderServices: function (services, container) {
        container.innerHTML = '';
        services.forEach(service => this.renderServiceCard(service, container));

        if (window.initScrollReveal) {
            setTimeout(() => {
                const elements = container.querySelectorAll('[data-reveal]');
                elements.forEach(el => el.classList.add('revealed'));
            }, 100);
        }
    }
};

ServicesLoader.init();
