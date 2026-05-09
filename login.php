<?php
require_once 'config.php';

// Redireccionar al dashboard si ya está logueado
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (isset($user['is_active']) && $user['is_active'] == 0) {
                    $error = "Tu cuenta se encuentra inactiva. Contacta al administrador para habilitar tu acceso.";
                } else {
                    // Registrar variables de sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_fullname'] = $user['fullname'];
                    $_SESSION['user_role'] = $user['role'];

                    header("Location: dashboard.php");
                    exit;
                }
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "Error al intentar iniciar sesión: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Control de Grúas G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-body);
            padding: 20px;
        }

        .login-card {
            background-color: var(--bg-card);
            padding: 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .login-logo {
            color: var(--primary);
            margin-bottom: 24px;
        }

        .login-logo svg {
            width: 48px;
            height: 48px;
        }

        .login-card h2 {
            margin-bottom: 8px;
            font-size: 24px;
            font-weight: 700;
        }

        .login-card p.subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .login-card form {
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <!-- SVG de una grúa / maquinaria industrial estilizada -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            
            <h2>¡Bienvenido!</h2>
            <p class="subtitle">Ingresa para gestionar el control de grúas</p>

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

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Ej: admin" required autofocus>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                    Ingresar al Sistema
                </button>
            </form>

            <div style="margin-top: 24px; font-size: 12px; color: var(--text-muted);">
                <p>Credenciales demo por defecto:</p>
                <p style="font-family: monospace;">Administrador: admin / admin123</p>
                <p style="font-family: monospace;">Operador: operador / operador123</p>
            </div>
        </div>
    </div>
</body>
</html>
