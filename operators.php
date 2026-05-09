<?php
require_once 'config.php';
require_admin();

// Asegurar autoinstalación de columnas requeridas en la tabla de usuarios
try {
    $pdo->query("SELECT document_id FROM users LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN document_id VARCHAR(50) DEFAULT ''");
        $pdo->exec("ALTER TABLE users ADD COLUMN license_number VARCHAR(100) DEFAULT ''");
        $pdo->exec("ALTER TABLE users ADD COLUMN license_expiry DATE DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN job_title VARCHAR(100) DEFAULT 'Operador de Grúa'");
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    } catch (PDOException $ex) {}
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Eliminar un operador/usuario
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    if ($delete_id === intval($_SESSION['user_id'])) {
        header("Location: operators.php?error=" . urlencode("No puedes eliminar tu propio usuario activo."));
        exit;
    }
    
    try {
        $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del->execute([$delete_id]);
        header("Location: operators.php?success=" . urlencode("Operador eliminado correctamente."));
        exit;
    } catch (PDOException $e) {
        $error = "No se puede eliminar este operador porque posee reportes preoperacionales históricos registrados. Te sugerimos desactivarlo en su lugar.";
    }
}

// Obtener la lista de usuarios/operadores
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY fullname ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar los operadores: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Operadores y Personal - G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            margin-bottom: 24px;
        }
        .header-section h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        .badge-active { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-inactive { background-color: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-role-admin { background-color: #f0f2ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge-role-user { background-color: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
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
        <div class="header-section">
            <h2>Gestionar Operadores y Personal</h2>
            <a href="operator_edit.php" class="btn btn-primary">➕ Agregar Nuevo Operador</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= h($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?= h($success); ?></span>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre Completo</th>
                        <th>Cédula / ID</th>
                        <th>Cargo / Especialidad</th>
                        <th>Licencia / Certificación</th>
                        <th>Vencimiento</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?= h($u['fullname']); ?></strong></td>
                                <td><?= h($u['document_id'] ?: 'No registrado'); ?></td>
                                <td><?= h($u['job_title'] ?: 'Operador de Grúa'); ?></td>
                                <td><?= h($u['license_number'] ?: 'No registrada'); ?></td>
                                <td>
                                    <?php if ($u['license_expiry']): ?>
                                        <?php 
                                        $expiry = strtotime($u['license_expiry']);
                                        $is_expired = $expiry < time();
                                        ?>
                                        <span style="<?= $is_expired ? 'color: var(--danger); font-weight: 600;' : 'color: var(--success);'; ?>">
                                            <?= date('d/m/Y', $expiry); ?> <?= $is_expired ? '⚠️ (Vencida)' : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= h($u['username']); ?></code></td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-role-admin' : 'badge-role-user'; ?>">
                                        <?= $u['role'] === 'admin' ? 'Administrador' : 'Operador'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?= $u['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="operator_edit.php?id=<?= $u['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px;">✏️ Editar</a>
                                        <?php if ($u['id'] !== intval($_SESSION['user_id'])): ?>
                                            <a href="operators.php?delete_id=<?= $u['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; color: var(--danger); border-color: rgba(239,68,68,0.2);" onclick="return confirm('¿Estás seguro de que deseas eliminar este operador? Si tiene reportes preoperacionales históricos, te sugerimos desactivarlo en su lugar editando su perfil.');">🗑️ Eliminar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 32px;">No se encontraron operadores registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
