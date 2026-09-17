<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageGallery());

$error = '';
$success = '';

// Asegurar tabla galeria_imagenes
try {
    $pdo = getConnection();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `galeria_imagenes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `titulo` VARCHAR(150) NOT NULL,
            `descripcion` TEXT DEFAULT NULL,
            `imagen_url` VARCHAR(500) NOT NULL,
            `categoria` VARCHAR(50) NOT NULL DEFAULT 'corte',
            `sucursal_id` INT UNSIGNED DEFAULT 1,
            `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {}

// Procesar Subida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagen'])) {
    try {
        $titulo = sanitize($_POST['titulo'] ?? '');
        $descripcion = sanitize($_POST['descripcion'] ?? '');
        $categoria = sanitize($_POST['categoria'] ?? 'corte');

        if (empty($titulo)) {
            throw new Exception("El título de la fotografía es obligatorio.");
        }

        $targetDir = __DIR__ . "/assets/uploads/galeria/";
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $fileName = uniqid('gal_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES["imagen"]["name"]));
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $allowTypes = ['jpg', 'png', 'jpeg', 'gif', 'webp'];

        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $targetFilePath)) {
                // Insertar en DB
                $url = "/assets/uploads/galeria/" . $fileName; // Ruta web relativa
                $sql = "INSERT INTO galeria_imagenes (titulo, descripcion, categoria, imagen_url) VALUES (?, ?, ?, ?)";
                $stmt = getConnection()->prepare($sql);
                $stmt->execute([$titulo, $descripcion, $categoria, $url]);

                $newId = getConnection()->lastInsertId();
                $origName = $_FILES["imagen"]["name"] ?? $fileName;
                registrarLog('SUBIR_FOTO', 'galeria', $newId, "Nueva foto '$titulo' subida a la Galería Web (Categoría: '$categoria', Archivo: '$origName')");

                // Redirect to prevent re-submission
                header('Location: galeria_admin.php?success=' . urlencode('Imagen subida correctamente a la galería'));
                exit;
            } else {
                $error = "Error al mover el archivo subido al servidor.";
            }
        } else {
            $error = "Solo se permiten archivos JPG, JPEG, PNG, GIF, y WEBP.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Mensajes via URL
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Procesar Eliminación
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        // Obtener ruta para borrar archivo
        $img = query("SELECT titulo, categoria, imagen_url FROM galeria_imagenes WHERE id = ?", [$id]);
        if (!empty($img)) {
            $gTitle = !empty($img[0]['titulo']) ? $img[0]['titulo'] : "ID #$id";
            $gCat = !empty($img[0]['categoria']) ? $img[0]['categoria'] : "corte";

            $relPath = ltrim($img[0]['imagen_url'], '/');
            $fullPath = __DIR__ . '/' . $relPath;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            // Borrar de DB
            $stmt = getConnection()->prepare("DELETE FROM galeria_imagenes WHERE id = ?");
            $stmt->execute([$id]);

            registrarLog('ELIMINAR_FOTO', 'galeria', $id, "Foto '$gTitle' (Categoría: '$gCat') fue eliminada de la Galería Web");
            header('Location: galeria_admin.php?success=' . urlencode('Imagen eliminada correctamente'));
            exit;
        }
    } catch (Exception $e) {
        $error = "Error al eliminar la imagen: " . $e->getMessage();
    }
}

// Obtener Imágenes
try {
    $imagenes = query("SELECT * FROM galeria_imagenes ORDER BY id DESC");
} catch (PDOException $e) {
    $imagenes = [];
}

$pageTitle = 'Gestión de Galería';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Galería Web</h1>
    <button onclick="document.getElementById('modalUpload').style.display='flex'" class="btn btn-primary">+ NUEVA
        FOTO</button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"
        style="background:#e74c3c22; color:#e74c3c; padding:15px; border-radius:6px; margin-bottom:20px; border:1px solid #e74c3c;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"
        style="background:#2ecc7122; color:#2ecc71; padding:15px; border-radius:6px; margin-bottom:20px; border:1px solid #2ecc71;">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<style>
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }

    .gallery-card {
        background: #1A1A1A;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #333;
        transition: transform 0.2s;
        position: relative;
    }

    .gallery-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary-gold);
    }

    .img-wrapper {
        height: 200px;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .card-body {
        padding: 15px;
        text-align: center;
    }

    .card-title-text {
        font-weight: 800 !important;
        color: #FFFFFF !important;
        margin-bottom: 5px;
        font-size: 1.05rem;
        text-align: center;
    }

    .card-desc {
        font-size: 13px;
        font-weight: 700 !important;
        color: #FFFFFF !important;
        text-align: center;
        opacity: 0.95;
    }

    .btn-delete-float {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(231, 76, 60, 0.9);
        color: white;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-delete-float:hover {
        background: #c0392b;
        transform: scale(1.1);
    }

    /* MODAL */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .modal-box {
        background: #1A1A1A;
        width: 500px;
        max-width: 90%;
        padding: 30px;
        border-radius: 12px;
        border: 1px solid #333;
    }
</style>

<div class="gallery-grid">
    <?php foreach ($imagenes as $img): ?>
        <div class="gallery-card">
            <div class="img-wrapper">
                <img src="<?php echo htmlspecialchars($img['imagen_url']); ?>"
                    alt="<?php echo htmlspecialchars($img['titulo']); ?>">
            </div>
            <button class="btn-delete-float"
                onclick="if(confirm('¿Borrar esta imagen?')) window.location.href='?delete=<?php echo $img['id']; ?>'">
                &times;
            </button>
            <div class="card-body">
                <span
                    style="font-size:10px;text-transform:uppercase;background:#333;color:#aaa;padding:2px 6px;border-radius:4px;"><?php echo htmlspecialchars($img['categoria'] ?? 'corte'); ?></span>
                <div class="card-title-text" style="margin-top:5px;">
                    <?php echo htmlspecialchars($img['titulo']); ?>
                </div>
                <div class="card-desc">
                    <?php echo htmlspecialchars($img['descripcion']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Empty State -->
    <?php if (count($imagenes) === 0): ?>
        <div
            style="grid-column: 1/-1; text-align: center; padding: 50px; color: #666; border: 2px dashed #333; border-radius: 12px;">
            <p>No hay imágenes en la galería aún.</p>
            <button onclick="document.getElementById('modalUpload').style.display='flex'" class="btn btn-secondary"
                style="margin-top:10px;">Subir Primera Foto</button>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div id="modalUpload" class="modal-overlay">
    <div class="modal-box">
        <h2 style="color:var(--primary-gold); margin-bottom: 20px;">Subir Nueva Foto</h2>
        <form method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 15px;">
                <label style="display:block; color:#888; margin-bottom:5px;">Título</label>
                <input type="text" name="titulo" required
                    style="width:100%; padding:10px; background:#111; border:1px solid #333; color:white; border-radius:4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display:block; color:#888; margin-bottom:5px;">Categoría</label>
                <select name="categoria" required
                    style="width:100%; padding:10px; background:#111; border:1px solid #333; color:white; border-radius:4px;">
                    <option value="corte">Cortes de Pelo</option>
                    <option value="barba">Barba y Afeitado</option>
                    <option value="spa">Spa & Tratamientos Faciales</option>
                    <option value="espacio">Espacio / Local</option>
                    <option value="productos">Productos</option>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display:block; color:#888; margin-bottom:5px;">Descripción (Opcional)</label>
                <textarea name="descripcion" rows="3"
                    style="width:100%; padding:10px; background:#111; border:1px solid #333; color:white; border-radius:4px;"></textarea>
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display:block; color:#888; margin-bottom:5px;">Archivo de Imagen</label>
                <input type="file" name="imagen" required accept="image/*" style="width:100%; color:white;">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modalUpload').style.display='none'"
                    class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Subir Imagen</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
