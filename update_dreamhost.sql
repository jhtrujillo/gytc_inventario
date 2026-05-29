-- =====================================================
-- SCRIPT DE ACTUALIZACIÓN DE ESTRUCTURA Y DATOS (DREAMHOST)
-- =====================================================

-- 1. ACTUALIZACIONES DE ESTRUCTURA:
ALTER TABLE preop_tasks ADD COLUMN current_value_truck INT DEFAULT 0 AFTER current_value;
ALTER TABLE preop_tasks ADD COLUMN next_change_value_truck INT DEFAULT 0 AFTER next_change_value;
ALTER TABLE preop_logs ADD COLUMN signature_data LONGTEXT NULL AFTER operating_status;

CREATE TABLE IF NOT EXISTS crane_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crane_id INT NOT NULL,
    document_name VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (crane_id) REFERENCES cranes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. ELIMINACIÓN DE EQUIPO G-07 (Y CASCADA DE TAREAS/REPORTES CONECTADOS):
DELETE FROM cranes WHERE crane_code = 'G-07';

-- 3. REGISTRO DE OPERADORES (SI NO EXISTEN YA EN PRODUCCIÓN):
INSERT INTO users (username, password, fullname, role) SELECT 'operador', '$2y$12$ziTMoYRg7UiQLkN7cN7nNuQJqi847FlvnPsmtQZZR2q55EfbXZrXm', 'Germán Villarraga', 'user' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE fullname = 'Germán Villarraga');
INSERT INTO users (username, password, fullname, role) SELECT 'jcprado', '$2y$12$c4XQaiYp3jDupsz8j5w/fOPoV/CI.WyFzrzzWDZhj4D.FeL8bLe2G', 'JUAN CARLOS PRADO', 'user' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE fullname = 'JUAN CARLOS PRADO');

-- 4. DATOS DE LOS NUEVOS EQUIPOS (JRM229 y ZNN913):
-- Insertar Equipo JRM229
INSERT INTO cranes (crane_code, machine_name, brand, line, capacity, model, chassis_series, motor_series, fuel_type, fluids_info) 
VALUES ('JRM229', 'CAMIONETA CHEVROLET D-MAX', 'CHEVROLET', 'D-MAX', '700k', '2021', '8LBETF3W9M0001637', 'UZ2074', 'DIESEL', '{\"aceite_motor\":\"6 LITROS MOBIL DELVAC 15W40\",\"aceite_hidraulico\":\"N\\/A\",\"combustible\":\"15 GALONES -DIESEL\"}');
SET @crane_id_jrm229 = LAST_INSERT_ID();

INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '100', 'LUBRICACION', 'Aceite motor', 5000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '201', 'ENGRASE', 'Crucetas', 5000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '202', 'ENGRASE', 'Cardanes', 5000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '203', 'ENGRASE', 'revision terminales', 5000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '300', 'FILTROS', 'Aceite motor Camión', 5000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '301', 'FILTROS', 'Combustible Motor', 5000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '302', 'FILTROS', 'Aire motor', 5000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_jrm229, '302b', 'FILTROS', 'Trampa de Agua', 5000);

-- Insertar Equipo ZNN913
INSERT INTO cranes (crane_code, machine_name, brand, line, capacity, model, chassis_series, motor_series, fuel_type, fluids_info) 
VALUES ('ZNN913', 'CAMION ESTACAS', 'FOTON', 'BJ1045V9JD4-F1', '2,400k', '2023', 'LVBV3JBB3PY001661', '77084182', 'DIESEL', '{\"aceite_motor\":\"6 LITROS CASTROL CRB MULTI 15W40\",\"aceite_hidraulico\":\"N\\/A\",\"combustible\":\"15 GALONES -DIESEL\"}');
SET @crane_id_znn913 = LAST_INSERT_ID();

INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '99', 'LUBRICACION', 'Aceite motor Camión', 6000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '100', 'ENGRASE', 'Crucetas', 6000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '201', 'ENGRASE', 'Cardanes', 6000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '202', 'ENGRASE', 'Terminales de Direccion', 6000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '203', 'FILTROS', 'Aceite motor Camión', 6000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '300', 'FILTROS', 'Combustible Motor Camión', 6000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '301', 'FILTROS', 'Aire motor Camión', 6000);
INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (@crane_id_znn913, '302', 'FILTROS', 'Trampa de Agua Camión', 6000);

-- 5. REPORTES PREOPERACIONALES HISTÓRICOS:
-- Log del 2025-04-15 para JRM229
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_jrm229, (SELECT id FROM users WHERE fullname = 'JUAN CARLOS PRADO' LIMIT 1), '2025-04-15', 85300, 0, 'JUAN CARLOS PRADO', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'LUBRICACION', 'Aceite motor', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Crucetas', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Cardanes', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'ENGRASE', 'revision terminales', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Aceite motor Camión', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Combustible Motor', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Aire motor', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302b', 'FILTROS', 'Trampa de Agua', 5000, 0, 85300, '2025-04-15', 0, 90300, 'Normal');

-- Log del 2025-08-15 para JRM229
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_jrm229, (SELECT id FROM users WHERE fullname = 'JUAN CARLOS PRADO' LIMIT 1), '2025-08-15', 89800, 0, 'JUAN CARLOS PRADO', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'LUBRICACION', 'Aceite motor', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Crucetas', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Cardanes', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'ENGRASE', 'revision terminales', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Aceite motor Camión', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Combustible Motor', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Aire motor', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302b', 'FILTROS', 'Trampa de Agua', 5000, 0, 89800, '2025-08-15', 0, 94800, 'Normal');

-- Log del 2025-12-18 para JRM229
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_jrm229, (SELECT id FROM users WHERE fullname = 'JUAN CARLOS PRADO' LIMIT 1), '2025-12-18', 95100, 0, 'JUAN CARLOS PRADO', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'LUBRICACION', 'Aceite motor', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Crucetas', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Cardanes', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'ENGRASE', 'revision terminales', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Aceite motor Camión', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Combustible Motor', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Aire motor', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302b', 'FILTROS', 'Trampa de Agua', 5000, 0, 95100, '2025-12-18', 0, 100100, 'Normal');

-- Log del 2026-04-07 para JRM229
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_jrm229, (SELECT id FROM users WHERE fullname = 'JUAN CARLOS PRADO' LIMIT 1), '2026-04-07', 100200, 0, 'JUAN CARLOS PRADO', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'LUBRICACION', 'Aceite motor', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Crucetas', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Cardanes', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'ENGRASE', 'revision terminales', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Aceite motor Camión', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Combustible Motor', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Aire motor', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302b', 'FILTROS', 'Trampa de Agua', 5000, 0, 100200, '2026-04-07', 0, 105200, 'Normal');

-- Log del 2024-10-15 para ZNN913
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_znn913, (SELECT id FROM users WHERE fullname = 'GERMAN VILLARRAGA' LIMIT 1), '2024-10-15', 79000, 0, 'GERMAN VILLARRAGA', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '99', 'LUBRICACION', 'Aceite motor Camión', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'ENGRASE', 'Crucetas', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Cardanes', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Terminales de Direccion', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'FILTROS', 'Aceite motor Camión', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Combustible Motor Camión', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Aire motor Camión', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Trampa de Agua Camión', 6000, 0, 79000, '2024-10-15', 0, 85000, 'Normal');

-- Log del 2025-04-12 para ZNN913
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_znn913, (SELECT id FROM users WHERE fullname = 'GERMAN VILLARRAGA' LIMIT 1), '2025-04-12', 85400, 0, 'GERMAN VILLARRAGA', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '99', 'LUBRICACION', 'Aceite motor Camión', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'ENGRASE', 'Crucetas', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Cardanes', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Terminales de Direccion', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'FILTROS', 'Aceite motor Camión', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Combustible Motor Camión', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Aire motor Camión', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Trampa de Agua Camión', 6000, 0, 85400, '2025-04-12', 0, 91400, 'Normal');

-- Log del 2025-10-15 para ZNN913
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_znn913, (SELECT id FROM users WHERE fullname = 'GERMAN VILLARRAGA' LIMIT 1), '2025-10-15', 91700, 0, 'GERMAN VILLARRAGA', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '99', 'LUBRICACION', 'Aceite motor Camión', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'ENGRASE', 'Crucetas', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Cardanes', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Terminales de Direccion', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'FILTROS', 'Aceite motor Camión', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Combustible Motor Camión', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Aire motor Camión', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Trampa de Agua Camión', 6000, 0, 91700, '2025-10-15', 0, 97700, 'Normal');

-- Log del 2026-03-30 para ZNN913
INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, operating_status) 
VALUES (@crane_id_znn913, (SELECT id FROM users WHERE fullname = 'GERMAN VILLARRAGA' LIMIT 1), '2026-03-30', 98400, 0, 'GERMAN VILLARRAGA', 1, 1, 1, 1, 1, 'approved');
SET @log_id = LAST_INSERT_ID();
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '99', 'LUBRICACION', 'Aceite motor Camión', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '100', 'ENGRASE', 'Crucetas', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '201', 'ENGRASE', 'Cardanes', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '202', 'ENGRASE', 'Terminales de Direccion', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '203', 'FILTROS', 'Aceite motor Camión', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '300', 'FILTROS', 'Combustible Motor Camión', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '301', 'FILTROS', 'Aire motor Camión', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');
INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status)
VALUES (@log_id, '302', 'FILTROS', 'Trampa de Agua Camión', 6000, 0, 98400, '2026-03-30', 0, 104400, 'Normal');

