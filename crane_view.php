<?php
require_once 'config.php';
require_login();

$crane_id = intval($_GET['crane_id'] ?? 0);

try {
    // Cargar los datos de la grúa
    $stmt = $pdo->prepare("SELECT * FROM cranes WHERE id = ?");
    $stmt->execute([$crane_id]);
    $crane = $stmt->fetch();

    if (!$crane) {
        header("Location: dashboard.php?error=" . urlencode("La grúa especificada no existe."));
        exit;
    }

    $fluids = json_decode($crane['fluids_info'] ?? '', true) ?? [];

    // Cargar historial de reportes preoperacionales de esta grúa
    $stmt_logs = $pdo->prepare("SELECT l.*, u.fullname as operator_full FROM preop_logs l 
                                JOIN users u ON l.operator_id = u.id 
                                WHERE l.crane_id = ? 
                                ORDER BY l.log_date DESC, l.id DESC");
    $stmt_logs->execute([$crane_id]);
    $crane_logs = $stmt_logs->fetchAll();

    // Cargar las actividades de mantenimiento registradas
    $stmt_tasks = $pdo->prepare("SELECT * FROM crane_activities WHERE crane_id = ? ORDER BY task_code ASC");
    $stmt_tasks->execute([$crane_id]);
    $activities = $stmt_tasks->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar los datos de la grúa: " . $e->getMessage());
}

$error_msg = $_GET['error'] ?? '';
$success_msg = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista de Equipo - <?= h($crane['brand']) . ' ' . h($crane['line']); ?> - G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .crane-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 32px;
            margin-bottom: 24px;
        }
        .crane-header-title h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary);
        }
        .crane-header-title p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }
        .tabs-container {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 24px;
        }
        .tab-button {
            padding: 12px 24px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
        }
        .tab-button:hover {
            color: var(--primary);
        }
        .tab-button.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .badge {
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-success { background-color: #ecfdf5; color: #065f46; }
        .badge-warning { background-color: #fffbeb; color: #92400e; }
        .badge-danger { background-color: #fef2f2; color: #991b1b; }

        /* Estilo para Ficha Técnica en Web */
        .ficha-web-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
        }
        .spec-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .spec-item:last-child {
            border-bottom: none;
        }
        .spec-item strong {
            color: var(--text-muted);
            font-weight: 500;
        }
        .spec-item span {
            color: var(--text-main);
            font-weight: 600;
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

    <main class="container">
        
        <div class="crane-header">
            <div class="crane-header-title">
                <h2><?= h($crane['machine_name']); ?></h2>
                <p>Identificación Técnica y Control Operativo: <strong><?= h($crane['brand']); ?> - <?= h($crane['line']); ?></strong></p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="preop_create.php?crane_id=<?= $crane['id']; ?>" class="btn btn-primary">Registrar Preoperacional</a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="crane_edit.php?id=<?= $crane['id']; ?>" class="btn btn-secondary">✏️ Editar Ficha</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error"><span><?= h($error_msg); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><span><?= h($success_msg); ?></span></div>
        <?php endif; ?>

        <!-- TABS SWITCHER -->
        <div class="tabs-container">
            <button class="tab-button active" onclick="switchTab(event, 'tab-ficha')">📋 Ficha Técnica</button>
            <button class="tab-button" onclick="switchTab(event, 'tab-historial')">🕒 Historial de Preoperacionales (<?= count($crane_logs); ?>)</button>
            <button class="tab-button" onclick="switchTab(event, 'tab-actividades')">🔧 Actividades de Mantenimiento (<?= count($activities); ?>)</button>
        </div>

        <!-- CONTENIDO TAB 1: FICHA TÉCNICA -->
        <div id="tab-ficha" class="tab-content active">
            <div class="ficha-web-grid">
                <div class="card" style="padding: 24px;">
                    <h3 style="font-size: 16px; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-bottom: 16px; font-weight: 700; color: var(--primary);">Especificaciones Técnicas</h3>
                    
                    <div class="spec-item">
                        <strong>Marca del Equipo</strong>
                        <span><?= h($crane['brand']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Línea / Referencia</strong>
                        <span><?= h($crane['line']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Modelo (Año)</strong>
                        <span><?= h($crane['model']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Capacidad Nominal</strong>
                        <span><?= h($crane['capacity']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Serie Chasis</strong>
                        <span><?= h($crane['chassis_series']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Serie Motor</strong>
                        <span><?= h($crane['motor_series']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Tipo de Combustible</strong>
                        <span><?= h($crane['fuel_type']); ?></span>
                    </div>

                    <h3 style="font-size: 16px; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px; font-weight: 700; color: var(--primary);">Fluidos y Capacidades</h3>
                    <div class="spec-item">
                        <strong>Aceite Motor</strong>
                        <span><?= h($fluids['aceite_motor'] ?: 'No registrado'); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Aceite Hidráulico</strong>
                        <span><?= h($fluids['aceite_hidraulico'] ?: 'No registrado'); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Combustible (Capacidad)</strong>
                        <span><?= h($fluids['combustible'] ?: 'No registrado'); ?></span>
                    </div>
                </div>

                <!-- Foto del Equipo -->
                <div class="card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px;">
                    <h3 style="font-size: 16px; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-bottom: 16px; font-weight: 700; color: var(--primary); width: 100%; text-align: left;">Registro Fotográfico</h3>
                    <div style="width: 100%; max-height: 350px; overflow: hidden; border-radius: 8px; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; background: #f8fafc; padding: 12px;">
                        <img src="<?= h($crane['image_path'] ?: 'images/crane_xcmg.png'); ?>" alt="Foto de la Grúa" style="max-width: 100%; max-height: 320px; border-radius: 6px; object-fit: contain;">
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTENIDO TAB 2: HISTORIAL DE PREOPERACIONALES -->
        <div id="tab-historial" class="tab-content">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Operador</th>
                            <th>Horómetro Camión</th>
                            <th>Horómetro Grúa</th>
                            <th>Estado Operativo</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($crane_logs) > 0): ?>
                            <?php foreach ($crane_logs as $log): ?>
                                <tr>
                                    <td><strong><?= date('d/m/Y', strtotime($log['log_date'])); ?></strong></td>
                                    <td><?= h($log['operator_full']); ?></td>
                                    <td><?= h($log['horometro_truck']); ?> Hrs</td>
                                    <td><?= h($log['horometro_crane']); ?> Hrs</td>
                                    <td>
                                        <span class="badge <?= $log['operating_status'] === 'approved' ? 'badge-success' : ($log['operating_status'] === 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?= $log['operating_status'] === 'approved' ? 'Aprobado' : ($log['operating_status'] === 'pending' ? 'Pendiente' : 'Rechazado'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="preop_view.php?id=<?= $log['id']; ?>" class="btn btn-secondary btn-sm">📄 Ver / Imprimir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">No se registran preoperacionales anteriores para esta grúa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONTENIDO TAB 3: ACTIVIDADES DE MANTENIMIENTO -->
        <div id="tab-actividades" class="tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 16px; font-weight: 700; color: var(--primary);">Actividades de Mantenimiento Configuradas</h3>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="crane_activities.php?crane_id=<?= $crane['id']; ?>" class="btn btn-secondary btn-sm">⚙️ Configurar Actividades</a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Código</th>
                            <th style="width: 150px;">Tipo</th>
                            <th>Actividad de Mantenimiento</th>
                            <th style="width: 180px; text-align: center;">Frecuencia (Hrs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $act): ?>
                                <tr>
                                    <td><strong><?= h($act['task_code']); ?></strong></td>
                                    <td><span class="badge" style="background:#e2e8f0; color:#475569;"><?= h($act['task_type']); ?></span></td>
                                    <td><?= h($act['task_name']); ?></td>
                                    <td style="text-align: center; font-weight: 600;"><?= number_format($act['frequency']); ?> Hrs</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 32px;">No hay actividades configuradas para esta grúa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        function switchTab(event, tabId) {
            // Desactivar todos los botones de tab
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            // Desactivar todos los contenidos de tab
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Activar tab actual
            if (event) {
                event.currentTarget.classList.add('active');
            }
            document.getElementById(tabId).classList.add('active');
        }
    </script>
</body>
</html>
