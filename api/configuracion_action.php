<?php
/**
 * KORTZEN - Procesador de Guardado de Configuración (Admin)
 */
require_once '../config.php';
requireLogin();

if (!isAdminTecnico() && !canManageSettings()) {
    header('Location: ../dashboard.php?error=' . urlencode('Acceso no autorizado'));
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save_configs') {
    try {
        $pdo = getConnection();
        
        $puntos_por_corte = max(0, intval($_POST['puntos_por_corte'] ?? 100));
        $puntos_por_referido = max(0, intval($_POST['puntos_por_referido'] ?? 200));
        $descuento_referido_amigo = max(0, floatval($_POST['descuento_referido_amigo'] ?? 2.00));
        $descuento_referente = max(0, floatval($_POST['descuento_referente'] ?? 2.00));
        $puntos_nivel_plata = max(1, intval($_POST['puntos_nivel_plata'] ?? 500));
        $puntos_nivel_oro = max($puntos_nivel_plata + 1, intval($_POST['puntos_nivel_oro'] ?? 1500));
        $puntos_nivel_vip = max($puntos_nivel_oro + 1, intval($_POST['puntos_nivel_vip'] ?? 3000));

        $smtp_host = trim($_POST['smtp_host'] ?? 'smtp.hostinger.com');
        $smtp_port = intval($_POST['smtp_port'] ?? 465);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');

        $politica_reserva_titulo = trim($_POST['politica_reserva_titulo'] ?? 'POLÍTICA DE RESERVAS');
        $politica_reserva_texto = trim($_POST['politica_reserva_texto'] ?? '');
        $politica_reserva_check_texto = trim($_POST['politica_reserva_check_texto'] ?? 'He leído y acepto la política de reserva y condiciones de puntualidad.');
        $politica_reserva_requiere_check = (isset($_POST['politica_reserva_requiere_check']) && $_POST['politica_reserva_requiere_check'] === '0') ? '0' : '1';

        $configs = [
            'puntos_por_corte' => (string)$puntos_por_corte,
            'puntos_por_referido' => (string)$puntos_por_referido,
            'descuento_referido_amigo' => number_format($descuento_referido_amigo, 2, '.', ''),
            'descuento_referente' => number_format($descuento_referente, 2, '.', ''),
            'puntos_nivel_plata' => (string)$puntos_nivel_plata,
            'puntos_nivel_oro' => (string)$puntos_nivel_oro,
            'puntos_nivel_vip' => (string)$puntos_nivel_vip,
            'politica_reserva_titulo' => $politica_reserva_titulo,
            'politica_reserva_texto' => $politica_reserva_texto,
            'politica_reserva_check_texto' => $politica_reserva_check_texto,
            'politica_reserva_requiere_check' => $politica_reserva_requiere_check,
            'smtp_host' => $smtp_host,
            'smtp_port' => (string)$smtp_port,
            'smtp_user' => $smtp_user,
            'smtp_pass' => $smtp_pass
        ];

        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

        foreach ($configs as $clave => $valor) {
            $stmt->execute([$clave, $valor]);
        }

        header('Location: ../configuracion.php?success=' . urlencode('Configuración del sistema guardada exitosamente.'));
        exit;

    } catch (Exception $e) {
        header('Location: ../configuracion.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Crear Código Promocional
if ($action === 'crear_codigo_promocional') {
    try {
        $pdo = getConnection();
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['codigo'] ?? '')));
        $porcentaje = floatval($_POST['descuento_porcentaje'] ?? 10);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($codigo)) {
            header('Location: ../configuracion.php?error=' . urlencode('El código no puede estar vacío.'));
            exit;
        }

        if ($porcentaje <= 0 || $porcentaje > 100) {
            header('Location: ../configuracion.php?error=' . urlencode('El porcentaje de descuento debe estar entre 1% y 100%.'));
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO codigos_promocionales (codigo, descuento_porcentaje, uso_maximo_por_usuario, activo, descripcion) VALUES (?, ?, 1, ?, ?)");
        $stmt->execute([$codigo, $porcentaje, $activo, $descripcion]);

        header('Location: ../configuracion.php?success=' . urlencode("Código promocional '$codigo' creado exitosamente ($porcentaje% de descuento)."));
        exit;
    } catch (Exception $e) {
        header('Location: ../configuracion.php?error=' . urlencode('Error: El código ya existe o es inválido.'));
        exit;
    }
}

// Toggle Estado (Activo / Inactivo)
if ($action === 'toggle_codigo_promocional') {
    try {
        $pdo = getConnection();
        $id = intval($_POST['id'] ?? 0);
        $activo = intval($_POST['activo'] ?? 0);
        $stmt = $pdo->prepare("UPDATE codigos_promocionales SET activo = ? WHERE id = ?");
        $stmt->execute([$activo, $id]);

        $estadoTexto = $activo ? 'activado' : 'desactivado';
        header('Location: ../configuracion.php?success=' . urlencode("Código promocional $estadoTexto correctamente."));
        exit;
    } catch (Exception $e) {
        header('Location: ../configuracion.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Eliminar Código Promocional
if ($action === 'eliminar_codigo_promocional') {
    try {
        $pdo = getConnection();
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM codigos_promocionales WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: ../configuracion.php?success=' . urlencode("Código promocional eliminado exitosamente."));
        exit;
    } catch (Exception $e) {
        header('Location: ../configuracion.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ../configuracion.php');
exit;
