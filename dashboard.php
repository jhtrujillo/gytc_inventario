<?php
require_once 'config.php';
require_login();

// Obtener estadísticas y Alertas Inteligentes
$critical_tasks = [];
$warning_tasks = [];
$rejected_cranes = [];

try {
    // 1. Alertas de Mantenimiento (Basado en último horómetro reportado)
    $sql_alerts = "SELECT 
                        t.task_name, t.task_code, t.current_value, t.next_change_value, t.current_value_truck, t.next_change_value_truck,
                        IF(t.next_change_value_truck > 0, t.next_change_value_truck - t.current_value_truck, t.next_change_value - t.current_value) as remaining,
                        c.id as crane_id, c.brand, c.line, c.crane_code
                   FROM preop_tasks t
                   JOIN preop_logs l ON t.log_id = l.id
                   JOIN cranes c ON l.crane_id = c.id
                   WHERE l.id IN (SELECT MAX(sub_l.id) FROM preop_logs sub_l GROUP BY sub_l.crane_id)
                   HAVING remaining <= 50
                   ORDER BY remaining ASC";
    $stmt_alerts = $pdo->query($sql_alerts);
    $alerts_raw = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alerts_raw as $row) {
        if ($row['remaining'] <= 0) {
            $critical_tasks[] = $row;
        } else {
            $warning_tasks[] = $row;
        }
    }

    // 2. Alertas de Estatus Operativo (Máquinas Inaptas en último reporte)
    $sql_rejected = "SELECT l.id, l.log_date, c.id as crane_id, c.brand, c.line, c.crane_code, l.operator_name
                     FROM preop_logs l
                     JOIN cranes c ON l.crane_id = c.id
                     WHERE l.id IN (SELECT MAX(sub_l.id) FROM preop_logs sub_l GROUP BY sub_l.crane_id)
                     AND l.operating_status = 'rejected'";
    $rejected_cranes = $pdo->query($sql_rejected)->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas Estándar
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
    $stmt = $pdo->query("SELECT * FROM cranes ORDER BY crane_code ASC, brand ASC, line ASC");
    $cranes = $stmt->fetchAll();

    // Listar reportes recientes
    $stmt = $pdo->query("SELECT l.*, c.brand, c.line, c.crane_code, u.fullname as operator_full FROM preop_logs l 
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

        /* SMART ALERT WIDGETS */
        .alert-center {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            padding: 24px;
            margin-bottom: 40px;
            border: 1px solid #fee2e2;
        }
        .alert-center h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            color: #0f172a;
            margin-bottom: 20px;
            font-weight: 800;
        }
        .alert-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        .alert-card {
            display: flex;
            flex-direction: column;
            padding: 16px;
            border-radius: 12px;
            text-decoration: none !important;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .alert-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }
        .alert-card-critical {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-card-critical::before { background: #dc2626; }
        .alert-card-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .alert-card-warning::before { background: #d97706; }
        .alert-card-rejected {
            background: #450a0a;
            color: #fef2f2;
            border: 1px solid #7f1d1d;
        }
        .alert-card-rejected::before { background: #ef4444; }
        
        .alert-meta {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            opacity: 0.8;
            margin-bottom: 4px;
        }
        .alert-card-title {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .alert-value {
            margin-top: 8px;
            font-size: 13px;
            font-weight: 600;
        }
    </style>

    <!-- CONFIGURACIÓN PWA (Aplicación Instalable) -->
    <meta name="theme-color" content="#1e3a8a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="images/icon-192.png">
    <link rel="manifest" href="manifest.json">
    
    <script>
        // Registro del Service Worker para habilitar la instalación
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('PWA Service Worker Registrado exitosamente.'))
                    .catch(err => console.log('Error registrando PWA SW:', err));
            });
        }
    </script>
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

        <!-- CENTRO INTELIGENTE DE ALERTAS (Solo se muestra si hay acciones requeridas) -->
        <?php if (!empty($critical_tasks) || !empty($warning_tasks) || !empty($rejected_cranes)): ?>
            <div class="alert-center">
                <h3>
                    <svg width="22" height="22" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    Centro de Alertas Críticas y Mantenimiento
                </h3>
                
                <div class="alert-grid">
                    <!-- 1. Máquinas Inaptas (Máxima Prioridad) -->
                    <?php foreach ($rejected_cranes as $r): ?>
                        <a href="crane_view.php?crane_id=<?= $r['crane_id']; ?>" class="alert-card alert-card-rejected">
                            <div class="alert-meta">🛑 PARO OPERATIVO</div>
                            <div class="alert-card-title">[<?= h($r['crane_code'] ?: 'S/C'); ?>] <?= h($r['brand']); ?> - <?= h($r['line']); ?></div>
                            <div style="font-size: 12px; margin-top: 4px;">Reportada como NO APTA en último preoperacional.</div>
                            <div class="alert-value">Reportado por: <?= h($r['operator_name']); ?></div>
                        </a>
                    <?php endforeach; ?>

                    <!-- 2. Mantenimientos Vencidos (Rojo) -->
                    <?php foreach ($critical_tasks as $t): ?>
                        <a href="crane_view.php?crane_id=<?= $t['crane_id']; ?>" class="alert-card alert-card-critical">
                            <div class="alert-meta">🚨 Mantenimiento Vencido</div>
                            <div class="alert-card-title"><?= h($t['task_name']); ?> (<?= h($t['task_code']); ?>)</div>
                            <div style="font-size: 12px; margin-top: 4px;">[<?= h($t['crane_code'] ?: 'S/C'); ?>] <?= h($t['brand']); ?> - <?= h($t['line']); ?></div>
                            <div class="alert-value">Excedido por: <?= abs($t['remaining']); ?> Horas ⚠️</div>
                        </a>
                    <?php endforeach; ?>

                    <!-- 3. Mantenimientos Próximos (Amarillo) -->
                    <?php foreach ($warning_tasks as $w): ?>
                        <a href="crane_view.php?crane_id=<?= $w['crane_id']; ?>" class="alert-card alert-card-warning">
                            <div class="alert-meta">⚠️ Próxima Revisión</div>
                            <div class="alert-card-title"><?= h($w['task_name']); ?> (<?= h($w['task_code']); ?>)</div>
                            <div style="font-size: 12px; margin-top: 4px;">[<?= h($w['crane_code'] ?: 'S/C'); ?>] <?= h($w['brand']); ?> - <?= h($w['line']); ?></div>
                            <div class="alert-value">Restan solo: <?= $w['remaining']; ?> Horas</div>
                        </a>
                    <?php endforeach; ?>
                </div>
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
                        <th>Código</th>
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
                                <td data-label="Código" style="font-weight: 700; color: var(--primary);"><?= h($crane['crane_code'] ?: 'S/C'); ?></td>
                                <td data-label="Máquina / Equipo" style="font-weight: 600;">
                                    <a href="crane_view.php?crane_id=<?= $crane['id']; ?>" style="color: var(--primary); text-decoration: none;">
                                        <?= h($crane['machine_name']); ?>
                                    </a>
                                </td>
                                <td data-label="Marca"><?= h($crane['brand']); ?></td>
                                <td data-label="Línea"><?= h($crane['line']); ?></td>
                                <td data-label="Modelo"><?= h($crane['model']); ?></td>
                                <td data-label="Capacidad"><?= h($crane['capacity']); ?></td>
                                <td data-label="Serie Chasis" style="font-family: monospace; font-size: 13px;"><?= h($crane['chassis_series']); ?></td>
                                <td data-label="">
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
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                No hay grúas registradas en el sistema.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>
