<?php
/**
 * Solicitar Registro de Cliente - KORTZEN
 * Permite a clientes sin cuenta de Google enviar sus datos por WhatsApp
 * para que el administrador cree su perfil en el sistema.
 */

require_once 'config.php';

$sucursales = [];

try {
    $pdo = getConnection();
    
    // Sucursales activas
    $stmtS = $pdo->query("SELECT id, nombre, direccion FROM sucursales WHERE estado = 'activo' ORDER BY id ASC");
    $sucursales = $stmtS->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fallback sucursales si vacío
if (empty($sucursales)) {
    $sucursales = [
        ['id' => 1, 'nombre' => 'KORTZEN Llano Chico', 'direccion' => 'Calle 17 de septiembre, Llano Chico, Quito']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Solicitar Creación de Perfil - KORTZEN</title>
    
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/base.css">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js" defer></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body, 'Outfit', sans-serif);
            background-color: var(--color-charcoal, #F7F7F7);
            color: #111111;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }

        .solicitud-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            padding: 2.25rem 1.75rem;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.07);
            box-sizing: border-box;
        }

        .solicitud-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .solicitud-logo {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 0.15em;
            color: #000000;
            margin-bottom: 0.4rem;
        }

        .solicitud-logo span {
            color: #000000;
        }

        .solicitud-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #111111;
            margin-bottom: 0.35rem;
        }

        .solicitud-desc {
            font-size: 0.85rem;
            color: #555555;
            line-height: 1.45;
        }

        .form-group {
            margin-bottom: 1.15rem;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #222222;
            margin-bottom: 0.35rem;
        }

        .form-label span.req {
            color: #EF4444;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            background: #F9FAFB;
            border: 1.5px solid #D1D5DB;
            border-radius: 10px;
            color: #111111;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #16A34A;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
            background: #FFFFFF;
        }

        select.form-control {
            cursor: pointer;
        }

        select.form-control option {
            background: #FFFFFF;
            color: #111111;
        }

        .btn-whatsapp-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px 20px;
            background: #16A34A;
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
            transition: all 0.25s ease;
            margin-top: 1.5rem;
            text-decoration: none;
            box-sizing: border-box;
        }

        .btn-whatsapp-submit:hover, .btn-whatsapp-submit:active {
            background: #15803D;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(22, 163, 74, 0.4);
        }

        .back-links {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid #E5E7EB;
            font-size: 0.85rem;
        }

        .back-links a {
            color: #6B7280;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-links a:hover {
            color: #111111;
        }

        .badge-step {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F0FDF4;
            color: #16A34A;
            border: 1px solid #86EFAC;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
        }
    </style>
</head>

<body>

    <div class="solicitud-card" id="formContainer">
        <div class="solicitud-header">
            <div class="badge-step">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.075-1.847-.426-1.547-.64-2.527-2.222-2.604-2.325-.077-.103-.623-.829-.623-1.581 0-.752.393-1.122.533-1.272.14-.15.305-.188.407-.188.102 0 .204.002.294.006.096.004.225-.036.35.267.13.313.442 1.079.48 1.157.039.078.065.17.013.273-.051.103-.077.167-.154.256-.077.09-.161.2-.23.269-.077.077-.157.161-.067.316.09.154.401.662.861 1.072.593.528 1.093.692 1.248.769.155.077.246.064.337-.039.091-.103.391-.455.495-.61.104-.155.207-.129.349-.077.142.052.898.423 1.053.5.155.078.258.117.297.181.039.065.039.378-.105.783z"/></svg>
                <span>Sin cuenta Google</span>
            </div>
            <h1 class="solicitud-logo">KORT<span>ZEN</span></h1>
            <div class="solicitud-title">Crear Perfil de Cliente</div>
            <p class="solicitud-desc">Llena tus datos y pulsa el botón para enviar tu solicitud directamente al WhatsApp del Administrador.</p>
        </div>

        <form id="solicitudForm" onsubmit="enviarSolicitudWhatsApp(event)">
            <div class="form-group">
                <label class="form-label" for="txtNombre">Nombre y Apellido <span class="req">*</span></label>
                <input type="text" id="txtNombre" class="form-control" placeholder="Ej: Carlos Mendoza" required autocomplete="name">
            </div>

            <div class="form-group">
                <label class="form-label" for="txtTelefono">WhatsApp / Teléfono <span class="req">*</span></label>
                <input type="tel" id="txtTelefono" class="form-control" placeholder="Ej: 0991234567" required autocomplete="tel">
            </div>

            <div class="form-group">
                <label class="form-label" for="txtEmail">Correo Electrónico (Opcional)</label>
                <input type="email" id="txtEmail" class="form-control" placeholder="Ej: carlos@gmail.com" autocomplete="email">
            </div>

            <div class="form-group">
                <label class="form-label" for="selSucursal">Sucursal de Preferencia</label>
                <select id="selSucursal" class="form-control">
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['nombre']); ?>">
                            📍 <?php echo htmlspecialchars($s['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" id="btnEnviarWA" class="btn-whatsapp-submit">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.075-1.847-.426-1.547-.64-2.527-2.222-2.604-2.325-.077-.103-.623-.829-.623-1.581 0-.752.393-1.122.533-1.272.14-.15.305-.188.407-.188.102 0 .204.002.294.006.096.004.225-.036.35.267.13.313.442 1.079.48 1.157.039.078.065.17.013.273-.051.103-.077.167-.154.256-.077.09-.161.2-.23.269-.077.077-.157.161-.067.316.09.154.401.662.861 1.072.593.528 1.093.692 1.248.769.155.077.246.064.337-.039.091-.103.391-.455.495-.61.104-.155.207-.129.349-.077.142.052.898.423 1.053.5.155.078.258.117.297.181.039.065.039.378-.105.783z"/>
                    <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.98-1.397C8.42 21.498 10.15 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18.25c-1.63 0-3.15-.48-4.43-1.3l-.32-.2-3.28.92.93-3.2-.21-.34C3.82 14.8 3.75 13.43 3.75 12c0-4.55 3.7-8.25 8.25-8.25s8.25 3.7 8.25 8.25-3.7 8.25-8.25 8.25z"/>
                </svg>
                <span>Enviar Solicitud a WhatsApp</span>
            </button>
        </form>

        <div class="back-links">
            <a href="cliente-login.php">← Iniciar con Google</a>
            <a href="/">Volver a Inicio →</a>
        </div>
    </div>

    <!-- Success Screen (Replaces form after click) -->
    <div class="solicitud-card" id="successContainer" style="display: none; text-align: center;">
        <div style="width: 65px; height: 65px; border-radius: 50%; background: #25D366; color: #FFFFFF; font-size: 2.2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; font-weight: 900;">
            ✓
        </div>
        <h2 style="font-size: 1.4rem; font-weight: 900; margin-bottom: 0.5rem; color: #111111;">¡Solicitud Lista!</h2>
        <p style="color: #374151; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
            Se ha abierto WhatsApp para enviar tus datos al administrador de KORTZEN. Si no se abrió automáticamente, pulsa el botón a continuación:
        </p>
        <a id="btnAbrirWA" href="#" target="_blank" class="btn-whatsapp-submit" style="margin-top: 0;">
            Abrir WhatsApp de KORTZEN
        </a>
        <div style="margin-top: 1.5rem;">
            <a href="/" style="color: #111111; font-weight: 700; text-decoration: none; font-size: 0.9rem;">← Volver a la página principal</a>
        </div>
    </div>

    <script>
        const WA_ADMIN_NUMBER = '593988422770';

        async function enviarSolicitudWhatsApp(e) {
            e.preventDefault();

            const nombre = document.getElementById('txtNombre').value.trim();
            const telefono = document.getElementById('txtTelefono').value.trim();
            const email = document.getElementById('txtEmail').value.trim();
            const sucursal = document.getElementById('selSucursal').value;

            if (!nombre || !telefono) {
                alert('Por favor completa tu nombre y número de teléfono.');
                return;
            }

            // Construir mensaje estructurado para WhatsApp
            let mensaje = `💈 *SOLICITUD DE CREACIÓN DE PERFIL - KORTZEN* 💈\n\n`;
            mensaje += `👤 *Cliente:* ${nombre}\n`;
            mensaje += `📱 *WhatsApp:* ${telefono}\n`;
            if (email) mensaje += `📧 *Email:* ${email}\n`;
            mensaje += `📍 *Sucursal:* ${sucursal}\n\n`;
            mensaje += `_Hola KORTZEN, no dispongo de cuenta Google. Por favor creen mi perfil de cliente con estos datos para poder ingresar y agendar mis citas. ¡Muchas gracias!_`;

            const waUrl = `https://wa.me/${WA_ADMIN_NUMBER}?text=${encodeURIComponent(mensaje)}`;

            // Guardar en la base de datos en segundo plano
            try {
                const fd = new FormData();
                fd.append('nombre', nombre);
                fd.append('telefono', telefono);
                fd.append('email', email);
                fd.append('sucursal', sucursal);

                fetch('/api/registrar_solicitud_cliente.php', {
                    method: 'POST',
                    body: fd
                }).catch(() => {});
            } catch(err) {}

            // Configurar pantalla de éxito y enlace
            document.getElementById('btnAbrirWA').href = waUrl;
            document.getElementById('formContainer').style.display = 'none';
            document.getElementById('successContainer').style.display = 'block';

            // Abrir WhatsApp en nueva pestaña
            window.open(waUrl, '_blank');
        }
    </script>
</body>
</html>
