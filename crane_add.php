<?php
require_once 'config.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_name = trim($_POST['machine_name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $line = trim($_POST['line'] ?? '');
    $capacity = trim($_POST['capacity'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $chassis_series = trim($_POST['chassis_series'] ?? '');
    $motor_series = trim($_POST['motor_series'] ?? '');
    $fuel_type = trim($_POST['fuel_type'] ?? 'DIESEL');

    $crane_code = trim($_POST['crane_code'] ?? '');

    // Estructurar fluidos como JSON
    $fluids = [
        'aceite_motor' => trim($_POST['aceite_motor'] ?? ''),
        'aceite_hidraulico' => trim($_POST['aceite_hidraulico'] ?? ''),
        'combustible' => trim($_POST['combustible_cap'] ?? '')
    ];
    $fluids_json = json_encode($fluids, JSON_UNESCAPED_UNICODE);

    if (!empty($crane_code) && !empty($brand) && !empty($line) && !empty($capacity) && !empty($model) && !empty($chassis_series) && !empty($motor_series)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO cranes (crane_code, machine_name, brand, line, capacity, model, chassis_series, motor_series, fuel_type, fluids_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $crane_code,
                $machine_name,
                $brand,
                $line,
                $capacity,
                $model,
                $chassis_series,
                $motor_series,
                $fuel_type,
                $fluids_json
            ]);
            
            $new_crane_id = $pdo->lastInsertId();
            seedDefaultActivities($pdo, $new_crane_id);

            header("Location: dashboard.php?success=" . urlencode("Grúa registrada exitosamente."));
            exit;
        } catch (PDOException $e) {
            $error = "Error al guardar la grúa: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nueva Grúa - G&TC</title>
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
            <h2 style="margin-bottom: 24px; font-weight: 700; color: var(--primary);">Registrar Nueva Grúa</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span><?= h($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="crane_add.php" method="POST">
                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Características del Equipo</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="crane_code">Código de Grúa / Equipo *</label>
                        <input type="text" id="crane_code" name="crane_code" class="form-control" placeholder="Ej: G-02" required>
                    </div>
                    <div class="form-group">
                        <label for="machine_name">Nombre de Máquina / Tipo de Equipo *</label>
                        <input type="text" id="machine_name" name="machine_name" class="form-control" value="GRÚAS SOBRE RUEDAS- PLUMATELESCOPICA" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="brand">Marca *</label>
                        <input type="text" id="brand" name="brand" class="form-control" placeholder="Ej: XCMG" required>
                    </div>
                    <div class="form-group">
                        <label for="line">Línea *</label>
                        <input type="text" id="line" name="line" class="form-control" placeholder="Ej: QY70K" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="capacity">Capacidad *</label>
                        <input type="text" id="capacity" name="capacity" class="form-control" placeholder="Ej: 70.000k" required>
                    </div>
                    <div class="form-group">
                        <label for="model">Modelo / Año *</label>
                        <input type="text" id="model" name="model" class="form-control" placeholder="Ej: 2020" required>
                    </div>
                </div>

                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Identificación de Series y Fluidos</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="chassis_series">Serie Chasis *</label>
                        <input type="text" id="chassis_series" name="chassis_series" class="form-control" placeholder="Ej: 212AAKAX23AK34839" required>
                    </div>
                    <div class="form-group">
                        <label for="motor_series">Serie Motor *</label>
                        <input type="text" id="motor_series" name="motor_series" class="form-control" placeholder="Ej: W0615338" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fuel_type">Tipo de Combustible</label>
                        <input type="text" id="fuel_type" name="fuel_type" class="form-control" value="DIESEL">
                    </div>
                    <div class="form-group">
                        <label for="combustible_cap">Capacidad Combustible</label>
                        <input type="text" id="combustible_cap" name="combustible_cap" class="form-control" placeholder="Ej: 50 GALONES">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="aceite_motor">Aceite de Motor</label>
                        <input type="text" id="aceite_motor" name="aceite_motor" class="form-control" placeholder="Ej: 6 GALONES-MOBIL DELVAC 15W40">
                    </div>
                    <div class="form-group">
                        <label for="aceite_hidraulico">Aceite Hidráulico</label>
                        <input type="text" id="aceite_hidraulico" name="aceite_hidraulico" class="form-control" placeholder="Ej: 100 GALONES MOBIL HIDRAULOAW68">
                    </div>
                </div>

                <div style="display: flex; gap: 16px; justify-content: flex-end; margin-top: 32px;">
                    <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Registrar Equipo</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
