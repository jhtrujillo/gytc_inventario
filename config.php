<?php
/**
 * Archivo de Configuración y Conexión de Base de Datos
 * Optimizado para MAMP (Local) y DreamHost (Producción)
 */

// Desactivar advertencias Deprecated y Notices para una interfaz limpia en PHP 8.5+
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Iniciar sesión de forma segura si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------
// CONFIGURACIÓN DE BASE DE DATOS (Ajustar según entorno)
// -------------------------------------------------------------

// Credenciales por defecto para MAMP (Mac)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '8889'); // Cambiar a '8889' si tu MAMP usa ese puerto para MySQL
define('DB_NAME', 'gytc_inventario');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // MAMP por defecto usa 'root' como contraseña, en DreamHost cámbialo por la tuya

// -------------------------------------------------------------
// CONEXIÓN PDO Y CREACIÓN AUTOMÁTICA (Para instalación fácil)
// -------------------------------------------------------------
try {
    // Conexión inicial sin base de datos para intentar crearla si no existe
    $dsn_no_db = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $pdo_init = new PDO($dsn_no_db, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Crear base de datos si no existe
    $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Conectar a la base de datos específica
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Crear tablas de forma automática si no existen
    createTablesIfNotExist($pdo);

} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:30px;background:#fff5f5;color:#c53030;border-radius:8px;margin:50px auto;max-width:600px;box-shadow:0 4px 12px rgba(0,0,0,0.1);'>
            <h2 style='margin-top:0;'>Error de Conexión a la Base de Datos</h2>
            <p>No se pudo conectar al servidor MySQL. Por favor verifica que <strong>MAMP</strong> esté activo y que las credenciales en <code>config.php</code> sean correctas.</p>
            <p style='font-size:13px;background:#ffebeb;padding:10px;border-radius:4px;'><strong>Detalle técnico:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
         </div>");
}

// -------------------------------------------------------------
// FUNCIONES DE AYUDA Y SEGURIDAD
// -------------------------------------------------------------

/**
 * Escapar cadenas HTML para evitar ataques XSS
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar si el usuario está autenticado
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Requerir autenticación para ver una página
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Requerir rol de administrador
 */
function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: dashboard.php?error=" . urlencode("No tienes permisos para acceder a esta sección."));
        exit;
    }
}

/**
 * Estructura de tablas y autoinstalador
 */
function createTablesIfNotExist($pdo) {
    // Tabla Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Tabla Cranes
    $pdo->exec("CREATE TABLE IF NOT EXISTS cranes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_name VARCHAR(150) NOT NULL DEFAULT 'GRÚA SOBRE RUEDAS- PLUMATELESCOPICA',
        brand VARCHAR(100) NOT NULL,
        line VARCHAR(100) NOT NULL,
        capacity VARCHAR(50) NOT NULL,
        model VARCHAR(10) NOT NULL,
        chassis_series VARCHAR(100) NOT NULL,
        motor_series VARCHAR(100) NOT NULL,
        fuel_type VARCHAR(50) DEFAULT 'DIESEL',
        fluids_info TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Tabla Crane Activities (Definición de actividades de mantenimiento por máquina)
    $pdo->exec("CREATE TABLE IF NOT EXISTS crane_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        crane_id INT NOT NULL,
        task_code VARCHAR(10) NOT NULL,
        task_type VARCHAR(50) NOT NULL,
        task_name VARCHAR(150) NOT NULL,
        frequency INT NOT NULL,
        FOREIGN KEY (crane_id) REFERENCES cranes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Tabla Preop Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS preop_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        crane_id INT NOT NULL,
        operator_id INT NOT NULL,
        log_date DATE NOT NULL,
        horometro_truck INT NOT NULL,
        horometro_crane INT NOT NULL,
        operator_name VARCHAR(100) NOT NULL,
        doc_security TINYINT(1) DEFAULT 0,
        doc_medical TINYINT(1) DEFAULT 0,
        doc_card TINYINT(1) DEFAULT 0,
        doc_ppe TINYINT(1) DEFAULT 0,
        doc_extinguisher TINYINT(1) DEFAULT 0,
        sling_info TEXT,
        operating_status ENUM('approved', 'not_approved', 'pending') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (crane_id) REFERENCES cranes(id) ON DELETE CASCADE,
        FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Tabla Preop Tasks
    $pdo->exec("CREATE TABLE IF NOT EXISTS preop_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_id INT NOT NULL,
        task_code VARCHAR(10) NOT NULL,
        task_type VARCHAR(50) NOT NULL,
        task_name VARCHAR(150) NOT NULL,
        frequency INT NOT NULL,
        current_value INT NOT NULL,
        last_change_date DATE,
        next_change_value INT NOT NULL,
        status VARCHAR(50) DEFAULT 'Normal',
        FOREIGN KEY (log_id) REFERENCES preop_logs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Crear usuarios por defecto si la tabla está vacía
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // admin / admin123
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, fullname, role) VALUES ('admin', '$admin_pass', 'Administrador G&TC', 'admin')");
        
        // operador / operador123
        $user_pass = password_hash('operador123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, fullname, role) VALUES ('operador', '$user_pass', 'Germán Villarraga', 'user')");
    }

    // Crear grúa de prueba por defecto si la tabla está vacía
    $stmt = $pdo->query("SELECT COUNT(*) FROM cranes");
    if ($stmt->fetchColumn() == 0) {
        $fluids = json_encode([
            'aceite_motor' => '6 CALONES-MOBIL DELVAC 15W40',
            'aceite_hidraulico' => '100 GALONES MOBIL HIDRAULOAW68',
            'combustible' => '50 GALONES-DIESEL'
        ]);
        $stmt_insert = $pdo->prepare("INSERT INTO cranes (machine_name, brand, line, capacity, model, chassis_series, motor_series, fuel_type, fluids_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->execute([
            'GRUAS SOBRE RUEDAS- PLUMATELESCOPICA',
            'XCMG',
            'QY70K',
            '70.000k',
            '2020',
            '212AAKAX23AK34839',
            'W0615338',
            'DIESEL',
            $fluids
        ]);
    }

    // Sembrar actividades por defecto para grúas que no tengan ninguna definida
    $stmt_cranes = $pdo->query("SELECT id FROM cranes");
    $all_cranes = $stmt_cranes->fetchAll();
    foreach ($all_cranes as $cr) {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM crane_activities WHERE crane_id = ?");
        $stmt_count->execute([$cr['id']]);
        if ($stmt_count->fetchColumn() == 0) {
            seedDefaultActivities($pdo, $cr['id']);
        }
    }
}

/**
 * Registra la lista de las 35 actividades de mantenimiento estándar por defecto para una grúa
 */
function seedDefaultActivities($pdo, $crane_id) {
    $default_tasks = [
        ['code' => '100', 'type' => 'LUBRICACION', 'name' => 'Aceite motor Camión', 'freq' => 250],
        ['code' => '102', 'type' => 'LUBRICACION', 'name' => 'Caja auxiliar', 'freq' => 2000],
        ['code' => '103', 'type' => 'LUBRICACION', 'name' => 'Diferenciales', 'freq' => 2000],
        ['code' => '104', 'type' => 'LUBRICACION', 'name' => 'Reductores Diferenciales', 'freq' => 1500],
        ['code' => '105', 'type' => 'LUBRICACION', 'name' => 'Sistema Hidráulico Camión', 'freq' => 2000],
        ['code' => '108', 'type' => 'LUBRICACION', 'name' => 'Transmisión', 'freq' => 2000],
        ['code' => '201', 'type' => 'ENGRASE', 'name' => 'Crucetas', 'freq' => 125],
        ['code' => '202', 'type' => 'ENGRASE', 'name' => 'Cardanes', 'freq' => 125],
        ['code' => '203', 'type' => 'ENGRASE', 'name' => 'Terminales de Dirección', 'freq' => 250],
        ['code' => '207', 'type' => 'ENGRASE', 'name' => 'Rodamientos Rodillo Diferencial', 'freq' => 2000],
        ['code' => '300', 'type' => 'FILTROS', 'name' => 'Aceite motor Camión', 'freq' => 250],
        ['code' => '301', 'type' => 'FILTROS', 'name' => 'Combustible Motor Camión', 'freq' => 250],
        ['code' => '302', 'type' => 'FILTROS', 'name' => 'Aire motor Camión', 'freq' => 250],
        ['code' => '303', 'type' => 'FILTROS', 'name' => 'Transmisión Camión', 'freq' => 1000],
        ['code' => '305', 'type' => 'FILTROS', 'name' => 'Aire Acondicionado Camión', 'freq' => 1000],
        ['code' => '307', 'type' => 'FILTROS', 'name' => 'Tanque Hidráulico Camión', 'freq' => 2000],
        ['code' => '308', 'type' => 'FILTROS', 'name' => 'Filtros de Agua Camión', 'freq' => 250],
        ['code' => '402', 'type' => 'ELECTRICO', 'name' => 'Alternador y Arranque Camión', 'freq' => 2000],
        ['code' => '501', 'type' => 'FRENOS', 'name' => 'Cámaras de Freno', 'freq' => 2000],
        ['code' => '900', 'type' => 'LUBRICACION', 'name' => 'Reductor de Giro Grúa', 'freq' => 2000],
        ['code' => '901', 'type' => 'LUBRICACION', 'name' => 'Reductor de Winche Grúa', 'freq' => 2000],
        ['code' => '902', 'type' => 'LUBRICACION', 'name' => 'Cable Winche Grúa', 'freq' => 125],
        ['code' => '903', 'type' => 'LUBRICACION', 'name' => 'Aceite motor Grúa', 'freq' => 250],
        ['code' => '904', 'type' => 'LUBRICACION', 'name' => 'Sistema Hidráulico Grúa', 'freq' => 2000],
        ['code' => '905', 'type' => 'ENGRASE', 'name' => 'Rodamiento Tornamesa Grúa', 'freq' => 125],
        ['code' => '906', 'type' => 'ENGRASE', 'name' => 'Corona Tornamesa Grúa', 'freq' => 125],
        ['code' => '907', 'type' => 'ENGRASE', 'name' => 'Extensiones Boom Grúa', 'freq' => 200],
        ['code' => '908', 'type' => 'FILTROS', 'name' => 'Combustible Motor Grúa', 'freq' => 250],
        ['code' => '909', 'type' => 'FILTROS', 'name' => 'Aceite motor Grúa', 'freq' => 250],
        ['code' => '910', 'type' => 'FILTROS', 'name' => 'Aire motor Grúa', 'freq' => 500],
        ['code' => '911', 'type' => 'FILTROS', 'name' => 'Aire Acondicionado Grúa', 'freq' => 1000],
        ['code' => '912', 'type' => 'FILTROS', 'name' => 'Filtro Tanque Hidráulico Grúa', 'freq' => 2000],
        ['code' => '913', 'type' => 'FILTROS', 'name' => 'Trampa de Agua Grúa', 'freq' => 250],
        ['code' => '914', 'type' => 'WINCHE', 'name' => 'Motor Hidráulico Grúa', 'freq' => 3000],
        ['code' => '916', 'type' => 'ELECTRICO', 'name' => 'Alternador Grúa', 'freq' => 2000]
    ];
    $stmt = $pdo->prepare("INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (?, ?, ?, ?, ?)");
    foreach ($default_tasks as $t) {
        $stmt->execute([$crane_id, $t['code'], $t['type'], $t['name'], $t['freq']]);
    }
}

