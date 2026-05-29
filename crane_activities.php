<?php
require_once 'config.php';
require_admin();

$crane_id = intval($_GET['crane_id'] ?? 0);

// Buscar la grúa seleccionada
try {
    $stmt = $pdo->prepare("SELECT * FROM cranes WHERE id = ?");
    $stmt->execute([$crane_id]);
    $crane = $stmt->fetch();

    if (!$crane) {
        header("Location: dashboard.php?error=" . urlencode("Selecciona una grúa válida para configurar actividades."));
        exit;
    }
} catch (PDOException $e) {
    die("Error al cargar la grúa: " . $e->getMessage());
}

$error = '';
$success = '';

// Procesar acciones POST (Agregar, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $task_code = trim($_POST['task_code'] ?? '');
        $task_type = trim($_POST['task_type'] ?? 'LUBRICACION');
        $task_name = trim($_POST['task_name'] ?? '');
        $frequency = intval($_POST['frequency'] ?? 0);

        if (!empty($task_code) && !empty($task_name) && $frequency > 0) {
            try {
                // Verificar si el código ya existe para esta grúa
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM crane_activities WHERE crane_id = ? AND task_code = ?");
                $stmt_check->execute([$crane_id, $task_code]);
                if ($stmt_check->fetchColumn() > 0) {
                    $error = "El código de actividad '$task_code' ya existe registrado en esta máquina.";
                } else {
                    $stmt_add = $pdo->prepare("INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (?, ?, ?, ?, ?)");
                    $stmt_add->execute([$crane_id, $task_code, $task_type, $task_name, $frequency]);
                    $success = "Actividad de mantenimiento agregada exitosamente.";
                }
            } catch (PDOException $e) {
                $error = "Error al agregar actividad: " . $e->getMessage();
            }
        } else {
            $error = "Todos los campos son obligatorios y la frecuencia debe ser mayor a 0.";
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $task_code = trim($_POST['task_code'] ?? '');
        $task_type = trim($_POST['task_type'] ?? 'LUBRICACION');
        $task_name = trim($_POST['task_name'] ?? '');
        $frequency = intval($_POST['frequency'] ?? 0);

        if ($id > 0 && !empty($task_code) && !empty($task_name) && $frequency > 0) {
            try {
                // Verificar duplicados de código
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM crane_activities WHERE crane_id = ? AND task_code = ? AND id != ?");
                $stmt_check->execute([$crane_id, $task_code, $id]);
                if ($stmt_check->fetchColumn() > 0) {
                    $error = "El código de actividad '$task_code' ya está en uso por otra actividad de esta máquina.";
                } else {
                    $stmt_edit = $pdo->prepare("UPDATE crane_activities SET task_code = ?, task_type = ?, task_name = ?, frequency = ? WHERE id = ?");
                    $stmt_edit->execute([$task_code, $task_type, $task_name, $frequency, $id]);
                    $success = "Actividad de mantenimiento actualizada correctamente.";
                }
            } catch (PDOException $e) {
                $error = "Error al actualizar actividad: " . $e->getMessage();
            }
        } else {
            $error = "Completa todos los campos obligatorios.";
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt_del = $pdo->prepare("DELETE FROM crane_activities WHERE id = ? AND crane_id = ?");
                $stmt_del->execute([$id, $crane_id]);
                $success = "Actividad de mantenimiento eliminada de la máquina.";
            } catch (PDOException $e) {
                $error = "Error al eliminar actividad: " . $e->getMessage();
            }
        }
    }
}

// Cargar las actividades registradas de esta grúa
try {
    $stmt_tasks = $pdo->prepare("SELECT * FROM crane_activities WHERE crane_id = ? ORDER BY task_code ASC");
    $stmt_tasks->execute([$crane_id]);
    $activities = $stmt_tasks->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar actividades de la grúa: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Actividades de Mantenimiento - G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .section-header {
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 8px;
            margin-bottom: 16px;
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .inline-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border: 1px dashed var(--border-color);
            margin-bottom: 24px;
            align-items: flex-end;
        }
        .inline-form .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 120px;
        }
    </style>
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

    <main class="container" style="margin-top: 32px; padding-bottom: 100px;">
        <div class="card" style="max-width: 1000px; margin: 0 auto;">
            
            <div style="margin-bottom: 24px;">
                <h2 style="font-weight: 700; color: var(--primary); margin-bottom: 4px;">Configurar Ficha de Mantenimiento</h2>
                <p style="color: var(--text-muted); font-size: 14px;">Define los códigos, nombres de actividades y frecuencias de mantenimiento para: <strong>[<?= h($crane['crane_code'] ?: 'S/C'); ?>] <?= h($crane['brand']); ?> - <?= h($crane['line']); ?></strong></p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><span><?= h($error); ?></span></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><span><?= h($success); ?></span></div>
            <?php endif; ?>

            <!-- REGISTRAR NUEVA ACTIVIDAD -->
            <div class="section-header">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
                <span>Registrar Nueva Actividad de Mantenimiento</span>
            </div>

            <form action="crane_activities.php?crane_id=<?= $crane_id; ?>" method="POST" class="inline-form">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group" style="max-width: 120px;">
                    <label for="task_code" style="font-size:12px;">Código *</label>
                    <input type="text" id="task_code" name="task_code" class="form-control" placeholder="Ej: 100" required style="padding:8px;">
                </div>

                <div class="form-group" style="max-width: 180px;">
                    <label for="task_type" style="font-size:12px;">Tipo *</label>
                    <select id="task_type" name="task_type" class="form-control" style="padding:8px; height:auto;">
                        <option value="LUBRICACION">LUBRICACION</option>
                        <option value="ENGRASE">ENGRASE</option>
                        <option value="FILTROS">FILTROS</option>
                        <option value="ELECTRICO">ELECTRICO</option>
                        <option value="FRENOS">FRENOS</option>
                        <option value="WINCHE">WINCHE</option>
                        <option value="OTROS">OTROS</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="task_name" style="font-size:12px;">Nombre de Actividad *</label>
                    <input type="text" id="task_name" name="task_name" class="form-control" placeholder="Ej: Aceite motor Camión" required style="padding:8px;">
                </div>

                <div class="form-group" style="max-width: 130px;">
                    <label for="frequency" style="font-size:12px;">Frecuencia (Hrs) *</label>
                    <input type="number" id="frequency" name="frequency" class="form-control" placeholder="Ej: 250" required min="1" style="padding:8px;">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-weight:600; font-size:13px; height:38px;">➕ Agregar</button>
            </form>

            <!-- TABLA DE ACTIVIDADES EXISTENTES -->
            <div class="section-header" style="margin-top: 36px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                <span>Actividades y Frecuencias Registradas (<?= count($activities); ?>)</span>
            </div>

            <div class="table-responsive">
                <table class="data-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Código</th>
                            <th style="width: 150px;">Tipo</th>
                            <th>Actividad de Mantenimiento</th>
                            <th style="width: 150px; text-align: center;">Frecuencia (Hrs)</th>
                            <th style="width: 180px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $act): ?>
                                <tr id="row_<?= $act['id']; ?>">
                                    <!-- Modo Vista -->
                                    <form id="form_<?= $act['id']; ?>" action="crane_activities.php?crane_id=<?= $crane_id; ?>" method="POST">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= $act['id']; ?>">
                                        
                                        <td data-label="Código" style="font-weight: 700; color: var(--text-muted);">
                                            <span class="view-mode_<?= $act['id']; ?>"><?= h($act['task_code']); ?></span>
                                            <input type="text" name="task_code" value="<?= h($act['task_code']); ?>" class="form-control edit-mode_<?= $act['id']; ?>" style="display:none; padding:4px 8px; font-size:13px;" required>
                                        </td>
                                        <td data-label="Tipo">
                                            <span class="view-mode_<?= $act['id']; ?> badge" style="background:#e2e8f0; color:#475569;"><?= h($act['task_type']); ?></span>
                                            <select name="task_type" class="form-control edit-mode_<?= $act['id']; ?>" style="display:none; padding:4px 8px; font-size:13px; height:auto;" required>
                                                <option value="LUBRICACION" <?= $act['task_type'] === 'LUBRICACION' ? 'selected' : ''; ?>>LUBRICACION</option>
                                                <option value="ENGRASE" <?= $act['task_type'] === 'ENGRASE' ? 'selected' : ''; ?>>ENGRASE</option>
                                                <option value="FILTROS" <?= $act['task_type'] === 'FILTROS' ? 'selected' : ''; ?>>FILTROS</option>
                                                <option value="ELECTRICO" <?= $act['task_type'] === 'ELECTRICO' ? 'selected' : ''; ?>>ELECTRICO</option>
                                                <option value="FRENOS" <?= $act['task_type'] === 'FRENOS' ? 'selected' : ''; ?>>FRENOS</option>
                                                <option value="WINCHE" <?= $act['task_type'] === 'WINCHE' ? 'selected' : ''; ?>>WINCHE</option>
                                                <option value="OTROS" <?= $act['task_type'] === 'OTROS' ? 'selected' : ''; ?>>OTROS</option>
                                            </select>
                                        </td>
                                        <td data-label="Actividad">
                                            <span class="view-mode_<?= $act['id']; ?>" style="font-weight:600;"><?= h($act['task_name']); ?></span>
                                            <input type="text" name="task_name" value="<?= h($act['task_name']); ?>" class="form-control edit-mode_<?= $act['id']; ?>" style="display:none; padding:4px 8px; font-size:13px;" required>
                                        </td>
                                        <td data-label="Frecuencia (Hrs)" style="text-align: center;">
                                            <span class="view-mode_<?= $act['id']; ?>"><?= number_format($act['frequency']); ?> Hrs</span>
                                            <input type="number" name="frequency" value="<?= $act['frequency']; ?>" class="form-control edit-mode_<?= $act['id']; ?>" style="display:none; padding:4px 8px; font-size:13px; text-align:center;" required min="1">
                                        </td>
                                        <td data-label="" style="text-align: center;">
                                            <!-- Botones Modo Vista -->
                                            <div class="view-mode_<?= $act['id']; ?>" style="display: flex; gap: 8px; justify-content: center;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="enableEdit(<?= $act['id']; ?>)">✏️ Editar</button>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $act['id']; ?>)">🗑️</button>
                                            </div>
                                            <!-- Botones Modo Edicion -->
                                            <div class="edit-mode_<?= $act['id']; ?>" style="display: none; gap: 8px; justify-content: center;">
                                                <button type="submit" class="btn btn-primary btn-sm">💾 Guardar</button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="disableEdit(<?= $act['id']; ?>)">Cancelar</button>
                                            </div>
                                        </td>
                                    </form>

                                    <!-- Formulario oculto de eliminación -->
                                    <form id="del_form_<?= $act['id']; ?>" action="crane_activities.php?crane_id=<?= $crane_id; ?>" method="POST" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $act['id']; ?>">
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                    No hay actividades registradas para esta grúa.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <script>
        function enableEdit(id) {
            document.querySelectorAll('.view-mode_' + id).forEach(el => el.style.display = 'none');
            document.querySelectorAll('.edit-mode_' + id).forEach(el => el.style.display = 'inline-flex');
        }

        function disableEdit(id) {
            document.querySelectorAll('.edit-mode_' + id).forEach(el => el.style.display = 'none');
            document.querySelectorAll('.view-mode_' + id).forEach(el => el.style.display = 'inline-flex');
        }

        function confirmDelete(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta actividad de mantenimiento? Esto afectará los futuros reportes de esta máquina.')) {
                document.getElementById('del_form_' + id).submit();
            }
        }
    </script>
</body>
</html>
