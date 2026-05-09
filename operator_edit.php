<?php
require_once 'config.php';
require_admin();

$id = intval($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$user = null;

if ($is_edit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            header("Location: operators.php?error=" . urlencode("El operador especificado no existe."));
            exit;
        }
    } catch (PDOException $e) {
        die("Error al cargar operador: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $document_id = trim($_POST['document_id'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $license_expiry = !empty($_POST['license_expiry']) ? $_POST['license_expiry'] : null;
    $job_title = trim($_POST['job_title'] ?? 'Operador de Grúa');
    $is_active = intval($_POST['is_active'] ?? 1);

    if (!empty($fullname) && !empty($username)) {
        try {
            // Verificar unicidad del username (excluyendo el actual si estamos editando)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt_check->execute([$username, $id]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = "El nombre de usuario '$username' ya se encuentra registrado por otro operador.";
            } else {
                if ($is_edit) {
                    // Si se ingresó una nueva contraseña, la actualizamos
                    if (!empty($password)) {
                        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_upd = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, password = ?, role = ?, document_id = ?, license_number = ?, license_expiry = ?, job_title = ?, is_active = ? WHERE id = ?");
                        $stmt_upd->execute([$fullname, $username, $hashed_pass, $role, $document_id, $license_number, $license_expiry, $job_title, $is_active, $id]);
                    } else {
                        $stmt_upd = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, role = ?, document_id = ?, license_number = ?, license_expiry = ?, job_title = ?, is_active = ? WHERE id = ?");
                        $stmt_upd->execute([$fullname, $username, $role, $document_id, $license_number, $license_expiry, $job_title, $is_active, $id]);
                    }
                    header("Location: operators.php?success=" . urlencode("Perfil del operador actualizado correctamente."));
                    exit;
                } else {
                    // Al agregar uno nuevo, la contraseña es obligatoria
                    if (empty($password)) {
                        $error = "La contraseña es obligatoria para un nuevo operador.";
                    } else {
                        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_ins = $pdo->prepare("INSERT INTO users (fullname, username, password, role, document_id, license_number, license_expiry, job_title, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_ins->execute([$fullname, $username, $hashed_pass, $role, $document_id, $license_number, $license_expiry, $job_title, $is_active]);
                        header("Location: operators.php?success=" . urlencode("Nuevo operador creado exitosamente."));
                        exit;
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error al guardar el operador: " . $e->getMessage();
        }
    } else {
        $error = "El Nombre Completo y Nombre de Usuario son obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Editar' : 'Agregar'; ?> Operador - G&TC</title>
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
                <a href="operators.php" class="btn btn-secondary btn-sm">Volver a Operadores</a>
            </div>
        </div>
    </header>

    <main class="container" style="margin-top: 32px; max-width: 800px;">
        <div class="card">
            <h2 style="margin-bottom: 24px; font-weight: 700; color: var(--primary);"><?= $is_edit ? 'Editar Perfil de Operador' : 'Agregar Nuevo Operador'; ?></h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span><?= h($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="operator_edit.php<?= $is_edit ? '?id=' . $id : ''; ?>" method="POST">
                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Información Personal</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fullname">Nombre Completo *</label>
                        <input type="text" id="fullname" name="fullname" class="form-control" value="<?= h($user['fullname'] ?? ''); ?>" required placeholder="Ej: Juan Pérez">
                    </div>
                    <div class="form-group">
                        <label for="document_id">Cédula / Documento Identidad</label>
                        <input type="text" id="document_id" name="document_id" class="form-control" value="<?= h($user['document_id'] ?? ''); ?>" placeholder="Ej: 1.098.765.432">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="job_title">Cargo / Especialidad</label>
                        <input type="text" id="job_title" name="job_title" class="form-control" value="<?= h($user['job_title'] ?? 'Operador de Grúa'); ?>" placeholder="Ej: Operador de Grúa Telescópica">
                    </div>
                    <div class="form-group">
                        <label for="is_active">Estado en el Sistema</label>
                        <select id="is_active" name="is_active" class="form-control">
                            <option value="1" <?= isset($user['is_active']) && $user['is_active'] == 1 ? 'selected' : ''; ?>>Activo (Acceso Permitido)</option>
                            <option value="0" <?= isset($user['is_active']) && $user['is_active'] == 0 ? 'selected' : ''; ?>>Inactivo (Acceso Denegado)</option>
                        </select>
                    </div>
                </div>

                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Credenciales de Acceso</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Nombre de Usuario *</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?= h($user['username'] ?? ''); ?>" required placeholder="Ej: jperez">
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña <?= $is_edit ? '(Opcional)' : '*'; ?></label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="<?= $is_edit ? 'Dejar en blanco para mantener la actual' : 'Asigna una contraseña'; ?>" <?= $is_edit ? '' : 'required'; ?>>
                    </div>
                </div>

                <h3 style="font-size: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px; font-weight: 600; color: var(--text-muted);">Licencia y Certificaciones</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="license_number">N° de Licencia / Certificación</label>
                        <input type="text" id="license_number" name="license_number" class="form-control" value="<?= h($user['license_number'] ?? ''); ?>" placeholder="Ej: LIC-88746-A">
                    </div>
                    <div class="form-group">
                        <label for="license_expiry">Fecha de Vencimiento</label>
                        <input type="date" id="license_expiry" name="license_expiry" class="form-control" value="<?= h($user['license_expiry'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <label for="role">Rol en el Sistema</label>
                    <select id="role" name="role" class="form-control" style="max-width: 300px;">
                        <option value="user" <?= isset($user['role']) && $user['role'] === 'user' ? 'selected' : ''; ?>>Operador / Usuario Estándar</option>
                        <option value="admin" <?= isset($user['role']) && $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrador G&TC</option>
                    </select>
                </div>

                <div style="display: flex; gap: 16px; justify-content: flex-end; margin-top: 32px;">
                    <a href="operators.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Guardar Cambios' : 'Registrar Operador'; ?></button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
