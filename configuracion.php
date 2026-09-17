<?php
/**
 * KORTZEN - Configuración del Sistema (Panel de Administración)
 * Control de Puntos KORTZEN, Descuentos y Sistema de Referidos (Diseño Blanco y Negro Minimalista)
 */
require_once 'config.php';
requireLogin();
requirePermission(canManageSettings());

$pdo = getConnection();

// Asegurar tablas de configuración
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS configuracion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(50) NOT NULL UNIQUE,
            valor TEXT NOT NULL,
            descripcion VARCHAR(255) NULL,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $defaultConfigs = [
        ['puntos_por_corte', '100', 'Puntos otorgados al cliente por cada cita/corte completado'],
        ['puntos_por_referido', '200', 'Puntos otorgados al cliente referente por cada referido exitoso'],
        ['descuento_referido_amigo', '2.00', 'Descuento ($) en la primera reserva del amigo que aplica el código'],
        ['descuento_referente', '2.00', 'Descuento ($) otorgado al cliente referente para su próxima cita'],
        ['puntos_nivel_plata', '500', 'Puntos requeridos para alcanzar Nivel Plata'],
        ['puntos_nivel_oro', '1500', 'Puntos requeridos para alcanzar Nivel Oro'],
        ['puntos_nivel_vip', '3000', 'Puntos requeridos para alcanzar Nivel VIP / Máximo'],
        ['politica_reserva_titulo', 'POLÍTICA DE RESERVAS', 'Título del cuadro de política en el proceso de reserva'],
        ['politica_reserva_texto', "• Si no puede llegar a su cita, informar con al menos 1 hora de anticipación.\n• Si llega 10 minutos tarde, pierde el servicio de toalla caliente y limpieza facial.\n• Pasados los 15 minutos de retraso, la cita podrá ser reprogramada para no afectar los turnos siguientes.\n• Cuidamos tu tiempo y el de los demás caballeros.", 'Cláusulas y términos de la política de reserva'],
        ['politica_reserva_check_texto', 'He leído y acepto la política de reserva y condiciones de puntualidad.', 'Texto de aceptación que el cliente debe marcar'],
        ['politica_reserva_requiere_check', '1', 'Si es obligatorio aceptar el checkbox (1 = Sí, 0 = No)']
    ];

    $stmtCfg = $pdo->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    foreach ($defaultConfigs as $cfg) {
        $stmtCfg->execute($cfg);
    }
    // Asegurar tablas de códigos promocionales
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS codigos_promocionales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            uso_maximo_por_usuario INT DEFAULT 1,
            activo TINYINT(1) DEFAULT 1,
            descripcion VARCHAR(255) NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS usos_codigos_promocionales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_id INT NOT NULL,
            cliente_id INT NOT NULL,
            cita_id INT NULL,
            descuento_monto DECIMAL(10,2) NOT NULL,
            fecha_uso DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_codigo (codigo_id),
            KEY idx_cliente (cliente_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

} catch (Exception $e) {}

// Obtener todas las configuraciones actuales
$stmt = $pdo->query("SELECT * FROM configuracion");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$configs = [];
foreach ($rows as $r) {
    $configs[$r['clave']] = $r['valor'];
}

// Obtener códigos promocionales
$codigosPromocionales = [];
try {
    $stmtPromo = $pdo->query("
        SELECT cp.*, (SELECT COUNT(*) FROM usos_codigos_promocionales u WHERE u.codigo_id = cp.id) as usos_totales 
        FROM codigos_promocionales cp 
        ORDER BY cp.id DESC
    ");
    $codigosPromocionales = $stmtPromo->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $exP) {}

$pageTitle = 'Configuración del Sistema y Referidos';
include 'includes/header.php';
?>

<style>
    .config-card {
        background: #FFFFFF;
        border: 1px solid #EAEAEA;
        border-radius: 16px;
        padding: 28px;
        color: #111111;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        margin-bottom: 24px;
        transition: all 0.2s ease;
    }
    .config-card-header {
        font-size: 1.15rem;
        font-weight: 800;
        color: #111111;
        margin-bottom: 22px;
        border-bottom: 1px solid #F0F0F0;
        padding-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.01em;
    }
    .config-icon {
        width: 22px;
        height: 22px;
        stroke: #111111;
        stroke-width: 2;
        fill: none;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .config-label {
        font-weight: 700;
        color: #222222;
        display: block;
        margin-bottom: 8px;
        font-size: 0.92rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .config-input {
        width: 100%;
        padding: 13px 16px;
        background: #FAFAFA;
        border: 1px solid #D1D1D1;
        color: #111111;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.05rem;
        transition: border-color 0.2s;
    }
    .config-input:focus {
        border-color: #000000;
        outline: none;
        background: #FFFFFF;
    }
    .config-help {
        color: #777777;
        font-size: 0.82rem;
        display: block;
        margin-top: 6px;
        line-height: 1.4;
    }
    .btn-save-main {
        background: #000000;
        color: #FFFFFF;
        border: none;
        padding: 16px 36px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 0.95rem;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: transform 0.15s, background 0.15s;
    }
    .btn-save-main:hover {
        background: #222222;
        transform: translateY(-2px);
    }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
    <div>
        <h1 class="page-title" style="margin: 0; color: #111111; font-weight: 900; font-size: 1.8rem; letter-spacing: -0.02em;">Configuración de Puntos & Referidos</h1>
        <p style="color: #666666; margin-top: 6px; font-size: 0.95rem;">Gestiona las reglas del programa de fidelización y parámetros del sistema.</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="padding: 14px 20px; background: #111111; color: #FFFFFF; border-radius: 12px; margin-bottom: 28px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
        <svg class="config-icon" style="stroke: #FFFFFF;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
    </div>
<?php endif; ?>

<form method="POST" action="api/configuracion_action.php">
    <input type="hidden" name="action" value="save_configs">

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        
        <!-- Tarjeta de Puntos KORTZEN -->
        <div class="config-card">
            <h3 class="config-card-header">
                <svg class="config-icon" viewBox="0 0 24 24"><path d="M12 15l-2 5l9-11h-7l2-5l-9 11h7z"></path></svg>
                Sistema de Puntos de Fidelidad
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label class="config-label">Puntos por Cita / Corte Completado</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="puntos_por_corte" value="<?php echo htmlspecialchars($configs['puntos_por_corte'] ?? '100'); ?>" required min="0" class="config-input">
                        <span style="color: #666666; font-weight: 700; white-space: nowrap; font-size: 0.85rem;">PTS / CITA</span>
                    </div>
                    <small class="config-help">Puntos asignados al cliente automáticamente cuando el barbero completa un servicio.</small>
                </div>

                <div>
                    <label class="config-label">Puntos de Bonificación por Referido Exitoso</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="puntos_por_referido" value="<?php echo htmlspecialchars($configs['puntos_por_referido'] ?? '200'); ?>" required min="0" class="config-input">
                        <span style="color: #666666; font-weight: 700; white-space: nowrap; font-size: 0.85rem;">PTS / AMIGO</span>
                    </div>
                    <small class="config-help">Puntos entregados al cliente que invitó cuando su amigo finaliza su primera cita.</small>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Descuentos por Referidos -->
        <div class="config-card">
            <h3 class="config-card-header">
                <svg class="config-icon" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                Descuentos y Promociones
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label class="config-label">Descuento para el Nuevo Cliente (Amigo)</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #111111; font-weight: 800; font-size: 1.2rem;">$</span>
                        <input type="number" name="descuento_referido_amigo" value="<?php echo htmlspecialchars($configs['descuento_referido_amigo'] ?? '2.00'); ?>" step="0.50" required min="0" class="config-input">
                    </div>
                    <small class="config-help">Monto de descuento directo restado en la reserva al aplicar el código de referido.</small>
                </div>

                <div>
                    <label class="config-label">Descuento para el Cliente Referente</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #111111; font-weight: 800; font-size: 1.2rem;">$</span>
                        <input type="number" name="descuento_referente" value="<?php echo htmlspecialchars($configs['descuento_referente'] ?? '2.00'); ?>" step="0.50" required min="0" class="config-input">
                    </div>
                    <small class="config-help">Descuento otorgado al cliente que refirió para usar en su próxima reserva.</small>
                </div>
            </div>
        </div>

    </div>

    <!-- Tarjeta de Niveles y Rangos KORTZEN -->
    <div class="config-card">
        <h3 class="config-card-header">
            <svg class="config-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            Niveles de Fidelización & Metas de Puntos
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <label class="config-label">Nivel Plata (Requerido)</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_plata" value="<?php echo htmlspecialchars($configs['puntos_nivel_plata'] ?? '500'); ?>" required min="0" class="config-input">
                    <span style="color: #666666; font-weight: 700; font-size: 0.82rem;">PTS</span>
                </div>
                <small class="config-help">Puntos necesarios para pasar de Bronce a Plata.</small>
            </div>

            <div>
                <label class="config-label">Nivel Oro (Requerido)</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_oro" value="<?php echo htmlspecialchars($configs['puntos_nivel_oro'] ?? '1500'); ?>" required min="0" class="config-input">
                    <span style="color: #666666; font-weight: 700; font-size: 0.82rem;">PTS</span>
                </div>
                <small class="config-help">Puntos necesarios para pasar de Plata a Oro.</small>
            </div>

            <div>
                <label class="config-label">Nivel VIP / Máximo (Requerido)</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_vip" value="<?php echo htmlspecialchars($configs['puntos_nivel_vip'] ?? '3000'); ?>" required min="0" class="config-input">
                    <span style="color: #666666; font-weight: 700; font-size: 0.82rem;">PTS</span>
                </div>
                <small class="config-help">Meta máxima de puntos para el nivel VIP.</small>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Configuración de Servidor de Correos (SMTP Hostinger) -->
    <div class="config-card">
        <h3 class="config-card-header">
            <svg class="config-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            Configuración del Servidor de Correos (SMTP)
        </h3>
        <p style="color: #666666; font-size: 0.88rem; margin-bottom: 18px; line-height: 1.5;">Configura tu correo corporativo para garantizar la entrega de confirmaciones y recordatorios a la bandeja de entrada del cliente.</p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label class="config-label">Servidor SMTP (Host)</label>
                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($configs['smtp_host'] ?? 'smtp.hostinger.com'); ?>" class="config-input">
            </div>

            <div>
                <label class="config-label">Puerto SMTP</label>
                <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($configs['smtp_port'] ?? '465'); ?>" class="config-input">
            </div>

            <div>
                <label class="config-label">Correo Remitente / Usuario SMTP</label>
                <input type="email" name="smtp_user" placeholder="info@kortzen.com" value="<?php echo htmlspecialchars($configs['smtp_user'] ?? 'info@kortzen.com'); ?>" class="config-input">
            </div>

            <div>
                <label class="config-label">Contraseña de Correo SMTP</label>
                <input type="password" name="smtp_pass" placeholder="••••••••••••" value="<?php echo htmlspecialchars($configs['smtp_pass'] ?? 'Kortzen2026!'); ?>" class="config-input">
            </div>
        </div>
    </div>

    <!-- Tarjeta de Política de Reservas & Términos de Cita -->
    <div class="config-card">
        <h3 class="config-card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg class="config-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                <span>Política de Reservas & Condiciones de Cita</span>
            </div>
            <span style="font-size: 0.75rem; background: #ECFDF5; color: #047857; padding: 4px 12px; border-radius: 20px; font-weight: 800; border: 1px solid #A7F3D0;">
                ✓ Se muestra al cliente antes de agendar
            </span>
        </h3>
        <p style="color: #666666; font-size: 0.88rem; margin-bottom: 20px; line-height: 1.5;">
            Personaliza las políticas de puntualidad, tolerancia y aviso de cancelación que los clientes leen y aceptan en el paso final del asistente de reserva.
        </p>

        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div>
                <label class="config-label">Título del Cuadro de Políticas</label>
                <input type="text" name="politica_reserva_titulo" value="<?php echo htmlspecialchars($configs['politica_reserva_titulo'] ?? 'POLÍTICA DE RESERVAS'); ?>" class="config-input" placeholder="POLÍTICA DE RESERVAS" required>
            </div>

            <div>
                <label class="config-label">Cláusulas y Reglas de la Cita (Un ítem por renglón)</label>
                <textarea name="politica_reserva_texto" class="config-input" style="min-height: 140px; font-family: inherit; font-size: 0.92rem; font-weight: 500; resize: vertical; line-height: 1.5;" placeholder="• Si no puede llegar a su cita, informar con al menos 1 hora de anticipación.&#10;• Si llega 10 minutos tarde, pierde el servicio de toalla caliente y limpieza facial.&#10;• Pasados los 15 minutos de retraso, la cita podrá ser reprogramada.&#10;• Cuidamos tu tiempo y el de los demás caballeros."><?php echo htmlspecialchars($configs['politica_reserva_texto'] ?? "• Si no puede llegar a su cita, informar con al menos 1 hora de anticipación.\n• Si llega 10 minutos tarde, pierde el servicio de toalla caliente y limpieza facial.\n• Pasados los 15 minutos de retraso, la cita podrá ser reprogramada para no afectar los turnos siguientes.\n• Cuidamos tu tiempo y el de los demás caballeros."); ?></textarea>
                <small class="config-help">Puedes ingresar viñetas (•) o escribir línea a línea. El sistema las formateará automáticamente para el cliente.</small>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                <div>
                    <label class="config-label">Texto de la Casilla de Aceptación (Checkbox)</label>
                    <input type="text" name="politica_reserva_check_texto" value="<?php echo htmlspecialchars($configs['politica_reserva_check_texto'] ?? 'He leído y acepto la política de reserva y condiciones de puntualidad.'); ?>" class="config-input" required>
                    <small class="config-help">Frase de consentimiento que el cliente debe leer junto a la casilla de verificación.</small>
                </div>

                <div>
                    <label class="config-label">¿Exigir Checkbox para Reservar?</label>
                    <select name="politica_reserva_requiere_check" class="config-input" style="cursor: pointer;">
                        <option value="1" <?php echo (($configs['politica_reserva_requiere_check'] ?? '1') === '1') ? 'selected' : ''; ?>>Sí, Obligatorio para agendar</option>
                        <option value="0" <?php echo (($configs['politica_reserva_requiere_check'] ?? '1') === '0') ? 'selected' : ''; ?>>No, Solo Informativo</option>
                    </select>
                    <small class="config-help">Si es obligatorio, el botón no se habilitará hasta que se marque la casilla.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón Guardar Cambios Principales -->
    <div style="display: flex; justify-content: flex-end; margin-top: 10px; margin-bottom: 30px;">
        <button type="submit" class="btn-save-main">
            <svg class="config-icon" style="stroke: #FFFFFF; width: 18px; height: 18px;" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>Guardar Configuración General</span>
        </button>
    </div>
</form>

<!-- Seccion Códigos Promocionales & Cupones -->
<div class="config-card" style="margin-top: 20px;">
    <h3 class="config-card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <svg class="config-icon" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
            <span>Códigos Promocionales & Cupones (Uso Único por Cliente)</span>
        </div>
        <span style="font-size: 0.78rem; background: #111111; color: #FFFFFF; padding: 5px 14px; border-radius: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">1 Uso Por Usuario</span>
    </h3>
    <p style="color: #666666; font-size: 0.88rem; margin-bottom: 22px; line-height: 1.5;">
        Crea códigos de descuento basados en porcentaje (%). Cada cliente solo podrá utilizar el código 1 sola vez en sus reservas. Puedes activar o desactivar cualquier código en tiempo real.
    </p>

    <!-- Formulario Crear Código -->
    <form method="POST" action="api/configuracion_action.php" style="background: #FAFAFA; border: 1px solid #EAEAEA; border-radius: 14px; padding: 22px; margin-bottom: 28px;">
        <input type="hidden" name="action" value="crear_codigo_promocional">
        
        <div style="display: grid; grid-template-columns: 2fr 1.2fr 2fr 1fr; gap: 16px; align-items: flex-end;">
            <div>
                <label class="config-label" style="font-size: 0.78rem;">Código Promocional *</label>
                <input type="text" name="codigo" placeholder="Ej: PROMO20" required class="config-input" style="text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">
            </div>
            <div>
                <label class="config-label" style="font-size: 0.78rem;">Descuento % *</label>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <input type="number" name="descuento_porcentaje" placeholder="15" min="1" max="100" step="0.5" required class="config-input">
                    <span style="font-weight: 900; font-size: 1.1rem; color: #111111;">%</span>
                </div>
            </div>
            <div>
                <label class="config-label" style="font-size: 0.78rem;">Descripción (Opcional)</label>
                <input type="text" name="descripcion" placeholder="Ej: 15% desc. por inauguración" class="config-input" style="font-size: 0.9rem; font-weight: 500;">
            </div>
            <div>
                <button type="submit" style="width: 100%; background: #111111; color: #FFFFFF; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 0.82rem; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px;">
                    + Crear Código
                </button>
            </div>
        </div>
    </form>

    <!-- Tabla de Códigos Existentes -->
    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid #EAEAEA; font-size: 0.78rem; text-transform: uppercase; color: #777777;">
                    <th style="padding: 12px;">Código</th>
                    <th style="padding: 12px;">Descuento</th>
                    <th style="padding: 12px;">Límite por Cliente</th>
                    <th style="padding: 12px;">Usos Totales</th>
                    <th style="padding: 12px;">Estado</th>
                    <th style="padding: 12px; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($codigosPromocionales)): ?>
                    <?php foreach ($codigosPromocionales as $cp): ?>
                        <tr style="border-bottom: 1px solid #F0F0F0; font-size: 0.9rem;">
                            <td style="padding: 14px 12px; font-weight: 900; color: #111111; letter-spacing: 1px;">
                                <?php echo htmlspecialchars($cp['codigo']); ?>
                                <?php if (!empty($cp['descripcion'])): ?>
                                    <div style="font-size: 0.78rem; color: #777777; font-weight: 500; text-transform: none; margin-top: 2px;"><?php echo htmlspecialchars($cp['descripcion']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 12px; font-weight: 900; color: #28a745; font-size: 1.05rem;">
                                <?php echo number_format($cp['descuento_porcentaje'], 1); ?>% OFF
                            </td>
                            <td style="padding: 14px 12px; color: #555555; font-size: 0.85rem; font-weight: 600;">
                                1 uso por usuario
                            </td>
                            <td style="padding: 14px 12px; font-weight: 800; color: #111111;">
                                <?php echo $cp['usos_totales']; ?> uso<?php echo $cp['usos_totales'] == 1 ? '' : 's'; ?>
                            </td>
                            <td style="padding: 14px 12px;">
                                <?php if ($cp['activo']): ?>
                                    <span style="background: #e8f5e9; color: #2e7d32; font-weight: 800; font-size: 0.75rem; padding: 5px 12px; border-radius: 20px; border: 1px solid #a5d6a7;">ACTIVO</span>
                                <?php else: ?>
                                    <span style="background: #fafafa; color: #888888; font-weight: 800; font-size: 0.75rem; padding: 5px 12px; border-radius: 20px; border: 1px solid #e0e0e0;">INACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 12px; text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <form method="POST" action="api/configuracion_action.php" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_codigo_promocional">
                                        <input type="hidden" name="id" value="<?php echo $cp['id']; ?>">
                                        <input type="hidden" name="activo" value="<?php echo $cp['activo'] ? 0 : 1; ?>">
                                        <button type="submit" style="background: #FFFFFF; border: 1px solid #D1D1D1; padding: 6px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 800; cursor: pointer; color: #111111;">
                                            <?php echo $cp['activo'] ? 'Desactivar' : 'Activar'; ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="api/configuracion_action.php" style="display: inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este código promocional?');">
                                        <input type="hidden" name="action" value="eliminar_codigo_promocional">
                                        <input type="hidden" name="id" value="<?php echo $cp['id']; ?>">
                                        <button type="submit" style="background: #fff0f0; border: 1px solid #ffcdd2; padding: 6px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 800; cursor: pointer; color: #d32f2f;">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding: 24px; text-align: center; color: #888888; font-size: 0.9rem;">
                            No hay códigos promocionales creados. ¡Crea el primero utilizando el formulario superior!
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tarjeta de Gestión de Sucursales y Próxima Apertura -->
<div class="config-card" style="margin-top: 32px; background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 16px; padding: 32px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
        <h3 class="config-card-header" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.25rem; font-weight: 800;">
            <svg class="config-icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            Gestión de Sucursales & Próximas Aperturas
        </h3>
        <a href="sucursales_crear.php" style="background: #111111; color: #FFFFFF; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
            + AÑADIR SUCURSAL
        </a>
    </div>
    <p style="color: #666666; font-size: 0.9rem; margin-bottom: 24px; line-height: 1.5;">
        Configura los locales activos de KORTZEN donde los clientes pueden agendar sus citas, o crea sucursales en estado <strong>"PRÓXIMAMENTE"</strong> para mostrar futuras aperturas de forma visible pero deshabilitada en el selector inicial.
    </p>

    <?php
    $sucursalesList = query("SELECT * FROM sucursales ORDER BY id ASC");
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
        <?php foreach ($sucursalesList as $suc): ?>
            <div style="background: #FAFAFA; border: 1px solid #EAEAEA; border-radius: 12px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <h4 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #111111;"><?php echo htmlspecialchars($suc['nombre']); ?></h4>
                        <?php 
                        $st = $suc['estado'] ?? 'activo';
                        if ($st === 'activo'): ?>
                            <span style="background: #e8f8f0; color: #2ecc71; font-size: 0.68rem; font-weight: 800; padding: 3px 8px; border-radius: 12px; border: 1px solid #a3e9c4;">🟢 ACTIVA</span>
                        <?php elseif ($st === 'proximamente'): ?>
                            <span style="background: #fffde7; color: #d4ac0d; font-size: 0.68rem; font-weight: 800; padding: 3px 8px; border-radius: 12px; border: 1px solid #f9e79f;">⏳ PRÓXIMAMENTE</span>
                        <?php else: ?>
                            <span style="background: #f2f4f4; color: #7f8c8d; font-size: 0.68rem; font-weight: 800; padding: 3px 8px; border-radius: 12px; border: 1px solid #bdc3c7;">🔴 INACTIVA</span>
                        <?php endif; ?>
                    </div>
                    <p style="margin: 0 0 6px 0; font-size: 0.82rem; color: #666666;">📍 <?php echo htmlspecialchars($suc['direccion']); ?></p>
                    <p style="margin: 0; font-size: 0.78rem; color: #888888;">🕒 Horario: <?php echo date('H:i', strtotime($suc['horario_apertura'] ?? '09:00')); ?> - <?php echo date('H:i', strtotime($suc['horario_cierre'] ?? '20:00')); ?></p>
                </div>
                <div style="margin-top: 14px; pt: 12px; border-top: 1px solid #EEEEEE; display: flex; justify-content: flex-end;">
                    <a href="sucursales_editar.php?id=<?php echo $suc['id']; ?>" style="color: #111111; font-size: 0.78rem; font-weight: 800; text-decoration: underline;">
                        Editar Sucursal & Estado →
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
