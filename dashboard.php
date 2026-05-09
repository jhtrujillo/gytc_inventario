<?php
require_once 'config.php';
require_login();

// Obtener estadísticas
try {
    // Total grúas
    $stmt = $pdo->query("SELECT COUNT(*) FROM cranes");
    $total_cranes = $stmt->fetchColumn();

    // Total reportes preoperacionales
    $stmt = $pdo->query("SELECT COUNT(*) FROM preop_logs");
    $total_logs = $stmt->fetchColumn();

    // Reportes aprobados
    $stmt = $pdo->query("SELECT COUNT(*) FROM preop_logs WHERE operating_status = 'approved'");
    $approved_logs = $stmt->fetchColumn();

    // Reportes pendientes / no aprobados
    $stmt = $pdo->query("SELECT COUNT(*) FROM preop_logs WHERE operating_status != 'approved'");
    $pending_logs = $stmt->fetchColumn();

    // Listar grúas
    $stmt = $pdo->query("SELECT * FROM cranes ORDER BY brand ASC, line ASC");
    $cranes = $stmt->fetchAll();

    // Listar reportes recientes
    $stmt = $pdo->query("SELECT l.*, c.brand, c.line, u.fullname as operator_full FROM preop_logs l 
                         JOIN cranes c ON l.crane_id = c.id 
                         JOIN users u ON l.operator_id = u.id 
                         ORDER BY l.log_date DESC, l.id DESC LIMIT 10");
    $recent_logs = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar datos en el dashboard: " . $e->getMessage());
}

$error_msg = $_GET['error'] ?? '';
$success_msg = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Control de Grúas G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            margin-bottom: 16px;
        }

        .header-section h2 {
            font-size: 24px;
            font-weight: 700;
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
                <div class="user-info">
                    <div class="user-fullname"><?= h($_SESSION['user_fullname']); ?></div>
                    <div class="user-role"><?= h($_SESSION['user_role'] === 'admin' ? 'Administrador' : 'Operador'); ?></div>
                </div>
                <a href="logout.php" class="btn btn-secondary btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <main class="container" style="margin-top: 24px;">
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= h($error_msg); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?= h($success_msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Panel de Estadísticas -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="stat-value"><?= $total_cranes; ?></div>
                <div class="stat-label">Total Grúas Registradas</div>
            </div>
            <div class="card">
                <div class="stat-value"><?= $total_logs; ?></div>
                <div class="stat-label">Reportes Preoperacionales Realizados</div>
            </div>
            <div class="card" style="border-left: 4px solid var(--success);">
                <div class="stat-value" style="color: var(--success);"><?= $approved_logs; ?></div>
                <div class="stat-label">Condición Operativa: Aprobada</div>
            </div>
            <div class="card" style="border-left: 4px solid var(--warning);">
                <div class="stat-value" style="color: var(--warning);"><?= $pending_logs; ?></div>
                <div class="stat-label">Condición Operativa: Pendiente/Rechazada</div>
            </div>
        </div>



        <!-- Sección de Inventario de Grúas -->
        <div class="header-section" style="margin-top: 48px;">
            <h2>Inventario de Grúas y Equipos</h2>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <div style="display: flex; gap: 12px;">
                    <a href="operators.php" class="btn btn-secondary">Gestionar Operadores</a>
                    <a href="crane_add.php" class="btn btn-primary">Registrar Nueva Grúa</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Máquina / Equipo</th>
                        <th>Marca</th>
                        <th>Línea</th>
                        <th>Modelo</th>
                        <th>Capacidad</th>
                        <th>Serie Chasis</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($cranes) > 0): ?>
                        <?php foreach ($cranes as $crane): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--primary);">
                                    <?= h($crane['machine_name']); ?>
                                </td>
                                <td><?= h($crane['brand']); ?></td>
                                <td><?= h($crane['line']); ?></td>
                                <td><?= h($crane['model']); ?></td>
                                <td><?= h($crane['capacity']); ?></td>
                                <td style="font-family: monospace; font-size: 13px;"><?= h($crane['chassis_series']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="preop_create.php?crane_id=<?= $crane['id']; ?>" class="btn btn-primary btn-sm">
                                            Registrar Preop
                                        </a>
                                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                            <a href="crane_edit.php?id=<?= $crane['id']; ?>" class="btn btn-secondary btn-sm">
                                                Editar Ficha
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                No hay grúas registradas en el sistema.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sección de Reportes Preoperacionales Recientes -->
        <div class="header-section" style="margin-top: 48px;">
            <h2>Historial de Reportes Preoperacionales Recientes</h2>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Grúa / Equipo</th>
                        <th>Operador</th>
                        <th>Horómetro Camión</th>
                        <th>Horómetro Grúa</th>
                        <th>Estado Operativo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_logs) > 0): ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td style="font-weight: 500;">
                                    <?= date('d/m/Y', strtotime($log['log_date'])); ?>
                                </td>
                                <td>
                                    <strong><?= h($log['brand']); ?></strong> - <?= h($log['line']); ?>
                                </td>
                                <td><?= h($log['operator_full']); ?></td>
                                <td><?= number_format($log['horometro_truck']); ?> Hrs</td>
                                <td><?= number_format($log['horometro_crane']); ?> Hrs</td>
                                <td>
                                    <?php if ($log['operating_status'] === 'approved'): ?>
                                        <span class="badge badge-success">Aprobado</span>
                                    <?php elseif ($log['operating_status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Pendiente</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">No Aprobado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="preop_view.php?id=<?= $log['id']; ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                        Ver / Imprimir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                No hay reportes preoperacionales registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>
