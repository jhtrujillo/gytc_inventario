-- Esquema de Base de Datos para Control Preoperacional de Grúas
-- Compatible con MAMP y DreamHost

CREATE DATABASE IF NOT EXISTS gytc_inventario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gytc_inventario;

-- 1. Tabla de Usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabla de Grúas (Ficha Técnica Básica)
CREATE TABLE IF NOT EXISTS cranes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(150) NOT NULL DEFAULT 'GRÚA SOBRE RUEDAS- PLUMATELESCOPICA',
    brand VARCHAR(100) NOT NULL,
    line VARCHAR(100) NOT NULL,
    capacity VARCHAR(50) NOT NULL,
    model VARCHAR(10) NOT NULL,
    chassis_series VARCHAR(100) NOT NULL,
    motor_series VARCHAR(100) NOT NULL,
    fuel_type VARCHAR(50) DEFAULT 'DIESEL',
    fluids_info TEXT, -- Almacena información sobre aceites y combustible
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2b. Tabla de Definición de Actividades de Mantenimiento por Máquina
CREATE TABLE IF NOT EXISTS crane_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crane_id INT NOT NULL,
    task_code VARCHAR(10) NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    task_name VARCHAR(150) NOT NULL,
    frequency INT NOT NULL,
    FOREIGN KEY (crane_id) REFERENCES cranes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Tabla de Cabecera de Reportes Preoperacionales
CREATE TABLE IF NOT EXISTS preop_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crane_id INT NOT NULL,
    operator_id INT NOT NULL,
    log_date DATE NOT NULL,
    horometro_truck INT NOT NULL,
    horometro_crane INT NOT NULL,
    operator_name VARCHAR(100) NOT NULL,
    -- Checklist de documentación (1 = Sí, 0 = No)
    doc_security TINYINT(1) DEFAULT 0,
    doc_medical TINYINT(1) DEFAULT 0,
    doc_card TINYINT(1) DEFAULT 0,
    doc_ppe TINYINT(1) DEFAULT 0,
    doc_extinguisher TINYINT(1) DEFAULT 0,
    -- Información de eslingas (en formato JSON)
    sling_info TEXT,
    -- Estado de operación de la grúa
    operating_status ENUM('approved', 'rejected', 'pending') NOT NULL DEFAULT 'pending',
    signature_data LONGTEXT, -- Contiene la firma electrónica en base64
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (crane_id) REFERENCES cranes(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Tabla de Detalles de Actividades de Mantenimiento por Reporte
CREATE TABLE IF NOT EXISTS preop_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    task_code VARCHAR(10) NOT NULL,
    task_type VARCHAR(50) NOT NULL, -- LUBRICACION, ENGRASE, FILTROS, etc.
    task_name VARCHAR(150) NOT NULL,
    frequency INT NOT NULL,
    current_value INT NOT NULL,
    last_change_date DATE,
    next_change_value INT NOT NULL,
    status VARCHAR(50) DEFAULT 'Normal',
    FOREIGN KEY (log_id) REFERENCES preop_logs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insertar Datos de Prueba Iniciales (Seeders)
-- Usuario Admin por defecto: admin / admin123
INSERT INTO users (id, username, password, fullname, role)
VALUES (1, 'admin', '$2y$10$n4qGz9E9S2p2Y/U0C8XQvODtOQ64h2tC7V7fbyyCqBHeSg6L0UunO', 'Administrador G&TC', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Usuario Operador por defecto: operador / operador123
INSERT INTO users (id, username, password, fullname, role)
VALUES (2, 'operador', '$2y$10$wEAtG8496lB.18T4T7B5Ke.p61rTHeO/YpX13L8E0fX09q0p.e2M6', 'Germán Villarraga', 'user')
ON DUPLICATE KEY UPDATE id=id;

-- Grúa por defecto
INSERT INTO cranes (id, machine_name, brand, line, capacity, model, chassis_series, motor_series, fuel_type, fluids_info)
VALUES (1, 'GRUAS SOBRE RUEDAS- PLUMATELESCOPICA', 'XCMG', 'QY70K', '70.000k', '2020', '212AAKAX23AK34839', 'W0615338', 'DIESEL', '{"aceite_motor":"6 CALONES-MOBIL DELVAC 15W40","aceite_hidraulico":"100 GALONES MOBIL HIDRAULOAW68","combustible":"50 GALONES-DIESEL"}')
ON DUPLICATE KEY UPDATE id=id;
