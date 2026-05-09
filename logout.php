<?php
require_once 'config.php';

// Limpiar todas las variables de sesión
$_SESSION = [];

// Destruir la sesión física
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redireccionar al login
header("Location: login.php");
exit;
