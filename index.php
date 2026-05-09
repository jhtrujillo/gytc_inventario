<?php
require_once 'config.php';

// Redireccionar inteligentemente según el estado de la sesión
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
} else {
    header("Location: login.php");
    exit;
}
