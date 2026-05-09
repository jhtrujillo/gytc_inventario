<?php
require_once 'config.php';
require_admin();

$id = intval($_GET['id'] ?? 0);
$error = '';

// Asegurar de forma autoinstalable y tolerante a fallos que la columna image_path exista
try {
    $pdo->query("SELECT image_path FROM cranes LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE cranes ADD COLUMN image_path VARCHAR(255) DEFAULT 'images/crane_xcmg.png'");
    } catch (PDOException $ex) {}
}

try {
    // Buscar la grúa
    $stmt = $pdo->prepare("SELECT * FROM cranes WHERE id = ?");
    $stmt->execute([$id]);
    $crane = $stmt->fetch();

    if (!$crane) {
        header("Location: dashboard.php?error=" . urlencode("El equipo especificado no existe."));
        exit;
    }

    // Decodificar fluidos
    $fluids = json_decode($crane['fluids_info'] ?? '', true) ?? [];

} catch (PDOException $e) {
    die("Error al cargar el equipo: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_name = trim($_POST['machine_name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $line = trim($_POST['line'] ?? '');
    $capacity = trim($_POST['capacity'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $chassis_series = trim($_POST['chassis_series'] ?? '');
    $motor_series = trim($_POST['motor_series'] ?? '');
    $fuel_type = trim($_POST['fuel_type'] ?? 'DIESEL');

    // Procesar carga de archivo para la foto de la grúa
    $image_path = $crane['image_path'] ?? 'images/crane_xcmg.png';
    if (isset($_FILES['crane_image']) && $_FILES['crane_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['crane_image']['tmp_name'];
        $fileName = $_FILES['crane_image']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            if (!is_dir('images')) {
                mkdir('images', 0755, true);
            }
            $newFileName = 'crane_' . $id . '_' . time() . '.' . $fileExtension;
            $dest_path = 'images/' . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $image_path = $dest_path;
            }
        } else {
            $error = "La extensión de la imagen no está permitida. Usa JPG, PNG o WEBP.";
        }
    }

    // Estructurar fluidos como JSON
    $fluids_new = [
        'aceite_motor' => trim($_POST['aceite_motor'] ?? ''),
        'aceite_hidraulico' => trim($_POST['aceite_hidraulico'] ?? ''),
        'combustible' => trim($_POST['combustible_cap'] ?? '')
    ];
    $fluids_json = json_encode($fluids_new, JSON_UNESCAPED_UNICODE);

    if (empty($error)) {
        if (!empty($brand) && !empty($line) && !empty($capacity) && !empty($model) && !empty($chassis_series) && !empty($motor_series)) {
            try {
                $stmt = $pdo->prepare("UPDATE cranes SET machine_name = ?, brand = ?, line = ?, capacity = ?, model = ?, chassis_series = ?, motor_series = ?, fuel_type = ?, fluids_info = ?, image_path = ? WHERE id = ?");
                $stmt->execute([
                    $machine_name,
                    $brand,
                    $line,
                    $capacity,
                    $model,
                    $chassis_series,
                    $motor_series,
                    $fuel_type,
                    $fluids_json,
                    $image_path,
                    $id
                ]);

                header("Location: dashboard.php?success=" . urlencode("Especificaciones e imagen del equipo actualizadas correctamente."));
                exit;
            } catch (PDOException $e) {
                $error = "Error al actualizar la grúa: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, completa todos los campos obligatorios.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Ficha Técnica de Grúa - G&TC</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="container nav-container">
            <a href="dashboard.php" class="brand-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
                <span>G&TC Control de Grúas</span>
            </a>
            
            <div class="nav-user">
                <a href="dashboard.php" class="btn btn-secondary btn-sm">Volver al Dashboard</a>
            </div>
        </div>
    </header>

    <main class="container" style="margin-top: 32px; max-width: 800px;">
        <div class="card">
            <h2 style="margin-bottom: 24px; font-weight: 700; color: var(--primary);">Editar Ficha Técnica</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span><?= h($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="crane_edit.php?id=<?= $id; ?>" method="POST" enctype="multipart/form-data">
                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Características del Equipo</h3>

                <div class="form-group">
                    <label for="machine_name">Nombre de Máquina / Tipo de Equipo *</label>
                    <input type="text" id="machine_name" name="machine_name" class="form-control" value="<?= h($crane['machine_name']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="brand">Marca *</label>
                        <input type="text" id="brand" name="brand" class="form-control" value="<?= h($crane['brand']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="line">Línea *</label>
                        <input type="text" id="line" name="line" class="form-control" value="<?= h($crane['line']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="capacity">Capacidad *</label>
                        <input type="text" id="capacity" name="capacity" class="form-control" value="<?= h($crane['capacity']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="model">Modelo / Año *</label>
                        <input type="text" id="model" name="model" class="form-control" value="<?= h($crane['model']); ?>" required>
                    </div>
                </div>

                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Identificación de Series y Fluidos</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="chassis_series">Serie Chasis *</label>
                        <input type="text" id="chassis_series" name="chassis_series" class="form-control" value="<?= h($crane['chassis_series']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="motor_series">Serie Motor *</label>
                        <input type="text" id="motor_series" name="motor_series" class="form-control" value="<?= h($crane['motor_series']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fuel_type">Tipo de Combustible</label>
                        <input type="text" id="fuel_type" name="fuel_type" class="form-control" value="<?= h($crane['fuel_type']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="combustible_cap">Capacidad Combustible</label>
                        <input type="text" id="combustible_cap" name="combustible_cap" class="form-control" value="<?= h($fluids['combustible'] ?? ''); ?>" placeholder="Ej: 50 GALONES-DIESEL">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="aceite_motor">Aceite de Motor</label>
                        <input type="text" id="aceite_motor" name="aceite_motor" class="form-control" value="<?= h($fluids['aceite_motor'] ?? ''); ?>" placeholder="Ej: 6 GALONES-MOBIL DELVAC 15W40">
                    </div>
                    <div class="form-group">
                        <label for="aceite_hidraulico">Aceite Hidráulico</label>
                        <input type="text" id="aceite_hidraulico" name="aceite_hidraulico" class="form-control" value="<?= h($fluids['aceite_hidraulico'] ?? ''); ?>" placeholder="Ej: 100 GALONES MOBIL HIDRAULOAW68">
                    </div>
                </div>

                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Foto de la Máquina</h3>
                <div style="display: flex; gap: 24px; align-items: center; margin-bottom: 24px;">
                    <div style="width: 150px; height: 110px; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: #f8fafc; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);">
                        <img id="image-preview" src="<?= h($crane['image_path'] ?? 'images/crane_xcmg.png'); ?>" alt="Previsualización" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    </div>
                    <div style="flex: 1;">
                        <label for="crane_image" style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 8px;">Subir Nueva Foto (JPG, PNG, WEBP)</label>
                        <input type="file" id="crane_image" name="crane_image" accept="image/*" class="form-control" onchange="previewFile()" style="padding: 6px;">
                    </div>
                </div>

                <div style="display: flex; gap: 16px; justify-content: flex-end; margin-top: 32px;">
                    <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function previewFile() {
            const preview = document.getElementById('image-preview');
            const file = document.getElementById('crane_image').files[0];
            const reader = new FileReader();

            reader.addEventListener("load", function () {
                preview.src = reader.result;
            }, false);

            if (file) {
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
