<?php
require_once 'config.php';
require_login();

$crane_id = intval($_GET['crane_id'] ?? 0);
if ($crane_id === 0) {
    try {
        $stmt_first = $pdo->query("SELECT id FROM cranes LIMIT 1");
        $crane_id = intval($stmt_first->fetchColumn() ?? 0);
    } catch (PDOException $e) {}
}
$error = '';
$success_msg = $_GET['success'] ?? '';

// 0. Procesar sub-acción AJAX / POST para agregar nueva actividad de mantenimiento al vuelo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_activity'])) {
    $task_code = trim($_POST['new_task_code'] ?? '');
    $task_type = trim($_POST['new_task_type'] ?? 'OTROS');
    $task_name = trim($_POST['new_task_name'] ?? '');
    $frequency = intval($_POST['new_frequency'] ?? 0);

    if (!empty($task_code) && !empty($task_name) && $frequency > 0) {
        try {
            // Verificar si el código ya existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM crane_activities WHERE crane_id = ? AND task_code = ?");
            $stmt_check->execute([$crane_id, $task_code]);
            if ($stmt_check->fetchColumn() > 0) {
                header("Location: preop_create.php?crane_id=$crane_id&error=" . urlencode("El código de actividad '$task_code' ya existe registrado en esta máquina."));
                exit;
            } else {
                $stmt_add = $pdo->prepare("INSERT INTO crane_activities (crane_id, task_code, task_type, task_name, frequency) VALUES (?, ?, ?, ?, ?)");
                $stmt_add->execute([$crane_id, $task_code, $task_type, $task_name, $frequency]);
                header("Location: preop_create.php?crane_id=$crane_id&success=" . urlencode("Actividad '$task_name' agregada exitosamente."));
                exit;
            }
        } catch (PDOException $e) {
            header("Location: preop_create.php?crane_id=$crane_id&error=" . urlencode("Error al agregar actividad: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: preop_create.php?crane_id=$crane_id&error=" . urlencode("Completa todos los campos requeridos para agregar la actividad."));
        exit;
    }
}

// 0.1 Procesar sub-acción POST para eliminar una actividad de la base de datos para esta grúa específica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_activity'])) {
    $task_code = trim($_POST['delete_task_code'] ?? '');
    if (!empty($task_code)) {
        try {
            $stmt_del = $pdo->prepare("DELETE FROM crane_activities WHERE crane_id = ? AND task_code = ?");
            $stmt_del->execute([$crane_id, $task_code]);
            header("Location: preop_create.php?crane_id=$crane_id&success=" . urlencode("Actividad eliminada exitosamente de esta máquina."));
            exit;
        } catch (PDOException $e) {
            header("Location: preop_create.php?crane_id=$crane_id&error=" . urlencode("Error al eliminar la actividad: " . $e->getMessage()));
            exit;
        }
    }
}

try {
    // Buscar la grúa seleccionada
    $stmt = $pdo->prepare("SELECT * FROM cranes WHERE id = ?");
    $stmt->execute([$crane_id]);
    $crane = $stmt->fetch();

    if (!$crane) {
        header("Location: dashboard.php?error=" . urlencode("Selecciona una grúa válida para iniciar el reporte."));
        exit;
    }
} catch (PDOException $e) {
    die("Error al cargar la grúa: " . $e->getMessage());
}

// Cargar las actividades y frecuencias de la base de datos de manera dinámica para esta grúa específica
$default_tasks = [];
try {
    $stmt_tasks = $pdo->prepare("SELECT task_code as code, task_type as type, task_name as name, frequency as freq FROM crane_activities WHERE crane_id = ? ORDER BY task_code ASC");
    $stmt_tasks->execute([$crane_id]);
    $default_tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar actividades de mantenimiento: " . $e->getMessage());
}

// Obtener el último reporte guardado para esta grúa, para precargar los valores de mantenimiento anteriores (Carry Forward)
$prev_tasks = [];
$prev_log = null;
try {
    $stmt_last_log = $pdo->prepare("SELECT * FROM preop_logs WHERE crane_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_last_log->execute([$crane_id]);
    $prev_log = $stmt_last_log->fetch(PDO::FETCH_ASSOC);
    
    // Obtener los valores de tareas más recientes de manera de Carry Forward por código individual para esta grúa
    $stmt_prev_tasks = $pdo->prepare("
        SELECT t.task_code, t.current_value, t.current_value_truck, t.last_change_date, t.next_change_value, t.next_change_value_truck, t.status
        FROM preop_tasks t
        JOIN preop_logs l ON t.log_id = l.id
        WHERE l.crane_id = ? AND l.id = (
            SELECT MAX(sub_l.id)
            FROM preop_logs sub_l
            JOIN preop_tasks sub_t ON sub_t.log_id = sub_l.id
            WHERE sub_l.crane_id = l.crane_id AND sub_t.task_code = t.task_code
        )
    ");
    $stmt_prev_tasks->execute([$crane_id]);
    $prev_tasks = $stmt_prev_tasks->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorar si no hay anteriores
}

// Cargar lista de operadores activos para el datalist buscador
$operators_list = [];
try {
    $stmt_operators = $pdo->query("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname ASC");
    $operators_list = $stmt_operators->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silencioso si falla
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_add_activity']) && !isset($_POST['ajax_delete_activity'])) {
    // 1. Datos básicos (horómetros opcionales, por defecto 0 si están vacíos)
    $log_date = $_POST['log_date'] ?? date('Y-m-d');
    $horometro_truck = !empty($_POST['horometro_truck']) ? intval($_POST['horometro_truck']) : 0;
    $horometro_crane = !empty($_POST['horometro_crane']) ? intval($_POST['horometro_crane']) : 0;
    $operator_name = trim($_POST['operator_name'] ?? '');

    // Buscar id del operador según el nombre seleccionado, o usar la sesión si no coincide
    $operator_id = $_SESSION['user_id'];
    if (!empty($operator_name)) {
        try {
            $stmt_op_id = $pdo->prepare("SELECT id FROM users WHERE fullname = ? LIMIT 1");
            $stmt_op_id->execute([$operator_name]);
            $found_id = $stmt_op_id->fetchColumn();
            if ($found_id) {
                $operator_id = intval($found_id);
            }
        } catch (PDOException $e) {}
    }

    // 2. Documentación
    $doc_security = isset($_POST['doc_security']) ? 1 : 0;
    $doc_medical = isset($_POST['doc_medical']) ? 1 : 0;
    $doc_card = isset($_POST['doc_card']) ? 1 : 0;
    $doc_ppe = isset($_POST['doc_ppe']) ? 1 : 0;
    $doc_extinguisher = isset($_POST['doc_extinguisher']) ? 1 : 0;

    // 3. Estructura de eslingas dinámica desde los inputs de arreglos
    $slings = [];
    $sling_items = $_POST['sling_item'] ?? [];
    $sling_precintos = $_POST['sling_precinto'] ?? [];
    $sling_codes = $_POST['sling_cod'] ?? [];
    $sling_dates = $_POST['sling_date'] ?? [];

    foreach ($sling_items as $idx => $item_val) {
        if (!empty($item_val)) {
            $slings[] = [
                'item' => trim($item_val),
                'no_precinto' => trim($sling_precintos[$idx] ?? ''),
                'cod_fabrica' => trim($sling_codes[$idx] ?? ''),
                'ultima_inspeccion' => trim($sling_dates[$idx] ?? '')
            ];
        }
    }
    $sling_info_json = json_encode($slings, JSON_UNESCAPED_UNICODE);

    // 5. Captura de Firma Digital
    $signature_data = $_POST['signature_data'] ?? null;

    try {
        $pdo->beginTransaction();

        // Guardar reporte de cabecera
        $stmt = $pdo->prepare("INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, sling_info, operating_status, signature_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $crane_id,
            $operator_id,
            $log_date,
            $horometro_truck,
            $horometro_crane,
            $operator_name,
            $doc_security,
            $doc_medical,
            $doc_card,
            $doc_ppe,
            $doc_extinguisher,
            $sling_info_json,
            $operating_status,
            $signature_data
        ]);
        
        $log_id = $pdo->lastInsertId();

        // Guardar reporte de tareas individuales
        $stmt_task = $pdo->prepare("INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, current_value_truck, last_change_date, next_change_value, next_change_value_truck, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($default_tasks as $index => $task) {
            $current_val = intval($_POST["task_curr_crane_" . $task['code']] ?? 0);
            $current_val_truck = intval($_POST["task_curr_truck_" . $task['code']] ?? 0);
            $last_date = $_POST["task_date_" . $task['code']] ?? null;
            if (empty($last_date)) $last_date = null;
            $next_val = intval($_POST["task_next_crane_" . $task['code']] ?? 0);
            $next_val_truck = intval($_POST["task_next_truck_" . $task['code']] ?? 0);
            $status = $_POST["task_status_" . $task['code']] ?? 'Normal';

            $stmt_task->execute([
                $log_id,
                $task['code'],
                $task['type'],
                $task['name'],
                $task['freq'],
                $current_val,
                $current_val_truck,
                $last_date,
                $next_val,
                $next_val_truck,
                $status
            ]);
        }

        $pdo->commit();
        header("Location: dashboard.php?success=" . urlencode("Reporte Preoperacional guardado de manera exitosa."));
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error al intentar guardar el preoperacional: " . $e->getMessage();
    }
}

// Obtener datos de fluidos de la grúa seleccionada
$fluids = json_decode($crane['fluids_info'] ?? '', true) ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Control Preoperacional - G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilos de calco perfecto con inputs estilizados de manera transparente y premium */
        .hv-table th, .hv-table td {
            border: 1px solid #000;
        }

        /* Campos de entrada optimizados para integrarse perfectamente dentro de las celdas */
        .form-control-hv {
            width: 100%;
            height: 100%;
            border: 1px dashed #3b82f6;
            background-color: #eff6ff;
            font-size: 11px;
            font-weight: 600;
            padding: 4px;
            box-sizing: border-box;
            border-radius: 4px;
            text-align: center;
            color: #1e3a8a;
            transition: all 0.2s ease;
        }

        .form-control-hv:focus {
            border: 1.5px solid #2563eb;
            background-color: #fff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
        }

        .hv-doc-checkbox-editable {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }

        .hv-doc-checkbox-editable input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .floating-action-bar {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(8px);
            padding: 12px 24px;
            border-radius: 50px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
            display: flex;
            gap: 16px;
            align-items: center;
            z-index: 1000;
        }

        @media print {
            .floating-action-bar, .no-print-bar {
                display: none !important;
            }
        }

        /* NUEVOS ESTILOS RESPONSIVE EXCLUSIVOS PARA LA CREACIÓN EN MÓVIL */
        @media screen and (max-width: 768px) {
            .hoja-vida-container {
                min-width: 100% !important;
                border: none;
                box-shadow: none;
                margin: 0;
            }
            .hv-header, .hv-grid-2, .hv-doc-checklist, .hv-footer {
                display: flex;
                flex-direction: column;
            }
            .hv-header-right, .hv-tech-cell, .hv-doc-row, .hv-footer-section {
                border: none;
                border-bottom: 1px solid #e2e8f0;
            }
            .hv-photo-container {
                border-left: none;
                border-top: 1px solid #e2e8f0;
                padding: 20px 0;
            }
            .hv-doc-row {
                justify-content: space-between;
                padding: 12px 16px;
                border-right: none !important;
            }
            
            /* TABLAS TRANSFORMADAS EN TARJETAS DE ENTRADA DE DATOS */
            .hv-table thead tr:first-child { display: none; } /* Ocultar cabeceras de tabla planas */
            .hv-table, .hv-table tbody, .hv-table tr, .hv-table td {
                display: block;
                width: 100%;
            }
            .hv-table tr {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                margin-bottom: 16px;
                padding: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            }
            .hv-table td {
                border: none;
                border-bottom: 1px solid #f1f5f9;
                padding: 12px 6px;
                text-align: right;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 14px;
            }
            .hv-table td:last-child { 
                border-bottom: none;
                background: #f8fafc;
                margin: 8px -12px -12px -12px;
                padding: 12px;
                border-radius: 0 0 12px 12px;
                justify-content: center;
            }
            
            /* Insertar labels contextuales */
            .hv-table td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-muted);
                font-size: 11px;
                text-transform: uppercase;
                text-align: left;
                flex: 1;
                padding-right: 8px;
            }
            
            /* Estilo del control dentro de la tarjeta */
            .hv-table td .form-control-hv, .hv-table td input, .hv-table td span {
                width: 60% !important;
                text-align: right !important;
                font-size: 13px;
                height: 38px;
                background: #f0f4f8;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                padding: 0 10px;
            }

            .hv-table td[data-label=""]::before, .hv-table td:empty::before { display: none; }
            
            .floating-action-bar {
                left: 16px;
                right: 16px;
                bottom: 16px;
                border-radius: 16px;
                flex-direction: row;
                justify-content: space-between;
                padding: 12px;
                width: calc(100% - 32px);
            }
            
            .hoja-vida-container div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body style="background-color: #f1f5f9; padding-top: 24px; padding-bottom: 120px;">

    <div class="container" style="max-width: 1300px;">
        
        <!-- BARRA DE ACCIONES SUPERIOR -->
        <div class="card" style="margin-bottom: 20px; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <a href="dashboard.php" style="text-decoration:none; font-weight:600; color:var(--primary);">Volver al Dashboard</a>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted);">Registrando reporte para: <strong>[<?= h($crane['crane_code'] ?: 'S/C'); ?>] <?= h($crane['brand']); ?> - <?= h($crane['line']); ?></strong></span>
            </div>
        </div>

        <?php if (!empty($error) || !empty($_GET['error'])): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;"><span><?= h($error ?: ($_GET['error'] ?? '')); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><span><?= h($success_msg); ?></span></div>
        <?php endif; ?>

        <!-- FORMULARIO DE REPORTE ÚNICO -->
        <form action="preop_create.php?crane_id=<?= $crane_id; ?>" method="POST" id="main-preop-form">

            <!-- CONTENEDOR DE LA HOJA DE VIDA (VISTA EXACTA DEL PAPEL FÍSICO) -->
            <div class="hoja-vida-scroll-wrapper">
                <div class="hoja-vida-container">
                
                <!-- ENCABEZADO -->
                <div class="hv-header">
                    <div class="hv-header-left">
                        <h2>HOJA DE VIDA DE EQUIPO</h2>
                        <h1>GRÚAS Y TRANSPORTES DE COLOMBIA SAS</h1>
                    </div>
                    <div class="hv-header-right">
                        <div class="hv-header-cell"><strong>FORMATO:</strong> MT-F-07</div>
                        <div class="hv-header-cell"><strong>VERSIÓN:</strong> 1</div>
                        <div class="hv-header-cell"><strong>FECHA:</strong> 01-08-2023</div>
                    </div>
                </div>

                <!-- SECCIÓN 1: CARACTERÍSTICAS DEL EQUIPO Y REGISTRO FOTOGRÁFICO -->
                <div class="hv-section-title">Características del Equipo e Identificación Técnica</div>
                
                <div class="hv-grid-2" style="border-bottom: 2px solid #334155;">
                    <!-- Detalles de Especificación -->
                    <div style="display:flex; flex-direction:column;">
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1; background:#f8fafc;">
                            <strong>MÁQUINA:</strong> <?= h($crane['machine_name']); ?>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #cbd5e1;">
                            <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>MARCA:</strong> <?= h($crane['brand']); ?></div>
                            <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>NRO REGISTRO:</strong> MT<?= str_pad($crane['id'], 5, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #cbd5e1;">
                            <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>LÍNEA:</strong> <?= h($crane['line']); ?></div>
                            <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>MODELO:</strong> <?= h($crane['model']); ?></div>
                        </div>
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1; border-bottom:1px solid #cbd5e1;">
                            <strong>CAPACIDAD:</strong> <?= h($crane['capacity']); ?>
                        </div>
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1; border-bottom:1px solid #cbd5e1;">
                            <strong>SERIE CHASIS:</strong> <?= h($crane['chassis_series']); ?>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #cbd5e1;">
                            <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>SERIE MOTOR:</strong> <?= h($crane['motor_series']); ?></div>
                            <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>COMBUSTIBLE:</strong> <?= h($crane['fuel_type']); ?></div>
                        </div>
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1; line-height:1.5;">
                            <strong>FLUIDOS Y CAPACIDADES:</strong><br>
                            • Aceite Motor: <?= h($fluids['aceite_motor'] ?? 'N/A'); ?><br>
                            • Aceite Hidráulico: <?= h($fluids['aceite_hidraulico'] ?? 'N/A'); ?><br>
                            • Combustible: <?= h($fluids['combustible'] ?? 'N/A'); ?>
                        </div>
                    </div>

                    <!-- Foto de la Grúa -->
                    <div class="hv-photo-container">
                        <img src="<?= h($crane['image_path'] ?? 'images/crane_xcmg.png'); ?>" alt="Registro Fotográfico Grúa">
                    </div>
                </div>

                <!-- SECCIÓN 2: DOCUMENTACIÓN (CAMPOS EDITABLES DIRECTOS) -->
                <div class="hv-section-title" style="border-top: 1px solid #334155;">Validación de Documentación Operativa</div>
                <div class="hv-doc-checklist">
                    <div class="hv-doc-row">
                        <span>Planilla de Seguridad del Operador</span>
                        <span class="hv-doc-checkbox-editable">
                            <input type="checkbox" name="doc_security" value="1" checked>
                        </span>
                    </div>
                    <div class="hv-doc-row">
                        <span>Examen Médico Operador</span>
                        <span class="hv-doc-checkbox-editable">
                            <input type="checkbox" name="doc_medical" value="1" checked>
                        </span>
                    </div>
                    <div class="hv-doc-row">
                        <span>Carnet de Operador</span>
                        <span class="hv-doc-checkbox-editable">
                            <input type="checkbox" name="doc_card" value="1" checked>
                        </span>
                    </div>
                    <div class="hv-doc-row">
                        <span>Elementos de EPP</span>
                        <span class="hv-doc-checkbox-editable">
                            <input type="checkbox" name="doc_ppe" value="1" checked>
                        </span>
                    </div>
                    <div class="hv-doc-row" style="border-right:none;">
                        <span>Extintor PQS</span>
                        <span class="hv-doc-checkbox-editable">
                            <input type="checkbox" name="doc_extinguisher" value="1" checked>
                        </span>
                    </div>
                </div>

                <!-- SECCIÓN 3: CABECERA DE OPERACIÓN E HORÓMETROS (CAMPOS EDITABLES DIRECTOS) -->
                <div class="hv-section-title">Ficha de Control de Actividades de Mantenimiento</div>
                
                <table class="hv-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Código</th>
                            <th style="width: 100px;">Marca Equipo</th>
                            <th style="width: 80px;">Modelo Equipo</th>
                            <th style="width: 90px;">Horómetro Camión</th>
                            <th style="width: 90px;">Horómetro Grúa</th>
                            <th style="width: 100px;">Fecha Act. *</th>
                            <th style="width: 160px;">Operador Equipo *</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background-color: #f1f5f9;">
                            <td data-label="Código" style="font-weight:bold; text-align: center; font-size:13px; color:#1e3a8a;"><?= h($crane['crane_code'] ?: 'G-' . str_pad($crane['id'], 2, '0', STR_PAD_LEFT)); ?></td>
                            <td data-label="Marca Equipo" style="text-align:center; font-size:12px; font-weight:700;"><?= h($crane['brand']) . ' ' . h($crane['line']); ?></td>
                            <td data-label="Modelo Equipo" style="text-align:center; font-size:12px; font-weight:700;"><?= h($crane['model']); ?></td>
                            <td data-label="Horómetro Camión">
                                <input type="number" name="horometro_truck" value="<?= isset($prev_log['horometro_truck']) ? $prev_log['horometro_truck'] : ''; ?>" class="form-control-hv" placeholder="Opcional" min="0" style="font-size:13px; font-weight:700;">
                            </td>
                            <td data-label="Horómetro Grúa">
                                <input type="number" name="horometro_crane" value="<?= isset($prev_log['horometro_crane']) ? $prev_log['horometro_crane'] : ''; ?>" class="form-control-hv" placeholder="Opcional" min="0" style="font-size:13px; font-weight:700;">
                            </td>
                            <td data-label="Fecha Act.">
                                <input type="date" name="log_date" value="<?= date('Y-m-d'); ?>" class="form-control-hv" required style="font-size:12px;">
                            </td>
                            <td data-label="Operador">
                                <input type="text" name="operator_name" id="operator_name" list="operators" value="<?= h($_SESSION['fullname'] ?? ''); ?>" class="form-control-hv" required style="text-transform:uppercase; font-size:12px; font-weight:700;" autocomplete="off">
                                <datalist id="operators">
                                    <?php foreach ($operators_list as $op): ?>
                                        <option value="<?= h($op['fullname']); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- AGREGAR ACTIVIDAD AL VUELO (INTEGRADO EN EL FORMULARIO) -->
                <details style="background: #f8fafc; border: 1px dashed var(--border-color); border-radius: 8px; padding: 14px 18px; margin: 20px 0;" class="no-print">
                    <summary style="font-weight: 700; color: var(--primary); cursor: pointer; font-size: 14px;">
                        ➕ ¿Deseas agregar una nueva actividad de mantenimiento a esta máquina sobre la marcha? (Haz clic aquí)
                    </summary>
                    <div style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 1; min-width: 100px;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Código *</label>
                            <input type="text" id="new_task_code" class="form-control" placeholder="Ej: 920" style="padding: 6px; font-size: 12px;">
                        </div>
                        <div style="flex: 1.5; min-width: 130px;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Tipo *</label>
                            <select id="new_task_type" class="form-control" style="padding: 6px; font-size: 12px; height: auto;">
                                <option value="LUBRICACION">LUBRICACION</option>
                                <option value="ENGRASE">ENGRASE</option>
                                <option value="FILTROS">FILTROS</option>
                                <option value="ELECTRICO">ELECTRICO</option>
                                <option value="FRENOS">FRENOS</option>
                                <option value="WINCHE">WINCHE</option>
                                <option value="OTROS" selected>OTROS</option>
                            </select>
                        </div>
                        <div style="flex: 3; min-width: 200px;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Nombre de Actividad *</label>
                            <input type="text" id="new_task_name" class="form-control" placeholder="Ej: Cambio de poleas" style="padding: 6px; font-size: 12px;">
                        </div>
                        <div style="flex: 1.5; min-width: 100px;">
                            <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Frecuencia (Hrs) *</label>
                            <input type="number" id="new_frequency" class="form-control" placeholder="Ej: 500" style="padding: 6px; font-size: 12px;">
                        </div>
                        <button type="button" class="btn btn-primary" onclick="submitNewActivity()" style="padding: 8px 16px; font-size: 12px; font-weight: 600; height: 35px; background: #2563eb;">➕ Agregar Actividad</button>
                    </div>
                </details>

                <table class="hv-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Código</th>
                            <th style="width: 100px;">Tipo</th>
                            <th class="left-align" style="text-align:left; padding-left:12px;">Actividad de Mantenimiento</th>
                            <th style="width: 80px;">Frecuencia</th>
                            <th style="width: 90px;">Horómetro Grúa (Actual)</th>
                            <th style="width: 90px;">Horómetro Camión (Actual)</th>
                            <th style="width: 110px;">Fecha de Cambio</th>
                            <th style="width: 90px;">Horómetro Grúa (Próximo)</th>
                            <th style="width: 90px;">Horómetro Camión (Próximo)</th>
                            <th style="width: 50px;" class="no-print">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($default_tasks as $task): 
                            $code = $task['code'];
                            $is_truck = (stripos($task['name'], 'camion') !== false || stripos($task['name'], 'camión') !== false || in_array(substr($code, 0, 1), ['1', '2', '3', '4', '5']));
                            
                            if ($is_truck) {
                                $val_curr_crane = 0;
                                $val_curr_truck = isset($prev_tasks[$code]) ? $prev_tasks[$code]['current_value_truck'] : 0;
                                $val_next_crane = 0;
                                $val_next_truck = isset($prev_tasks[$code]) ? $prev_tasks[$code]['next_change_value_truck'] : ($val_curr_truck + $task['freq']);
                            } else {
                                $val_curr_crane = isset($prev_tasks[$code]) ? $prev_tasks[$code]['current_value'] : 0;
                                $val_curr_truck = 0;
                                $val_next_crane = isset($prev_tasks[$code]) ? $prev_tasks[$code]['next_change_value'] : ($val_curr_crane + $task['freq']);
                                $val_next_truck = 0;
                            }
                            
                            $val_date = isset($prev_tasks[$code]) ? $prev_tasks[$code]['last_change_date'] : date('Y-m-d');
                        ?>
                            <tr>
                                <td data-label="Código" style="font-weight:700; text-align: center; color:#1e3a8a;"><?= h($code); ?></td>
                                <td data-label="Tipo" style="font-weight:700; text-transform:uppercase; text-align: center; font-size: 10px; color: #64748b;"><?= h($task['type']); ?></td>
                                <td data-label="Actividad" class="left-align" style="text-align:left; padding-left:12px; font-weight:600;"><?= h($task['name']); ?></td>
                                <td data-label="Frecuencia" style="text-align: center; font-weight:700; color:#1e3a8a;"><?= number_format($task['freq']); ?></td>
                                
                                <td data-label="Horómetro Grúa (Actual)">
                                    <?php if ($is_truck): ?>
                                        <input type="number" name="task_curr_crane_<?= $code; ?>" value="0" class="form-control-hv" readonly style="background: #f1f5f9; color: #94a3b8;">
                                    <?php else: ?>
                                        <input type="number" 
                                               id="curr_crane_<?= $code; ?>" 
                                               name="task_curr_crane_<?= $code; ?>" 
                                               value="<?= $val_curr_crane; ?>" 
                                               class="form-control-hv" 
                                               oninput="calcNext('<?= $code; ?>', <?= $task['freq']; ?>, 'crane')"
                                               min="0">
                                    <?php endif; ?>
                                </td>
                                
                                <td data-label="Horómetro Camión (Actual)">
                                    <?php if ($is_truck): ?>
                                        <input type="number" 
                                               id="curr_truck_<?= $code; ?>" 
                                               name="task_curr_truck_<?= $code; ?>" 
                                               value="<?= $val_curr_truck; ?>" 
                                               class="form-control-hv" 
                                               oninput="calcNext('<?= $code; ?>', <?= $task['freq']; ?>, 'truck')"
                                               min="0">
                                    <?php else: ?>
                                        <input type="number" name="task_curr_truck_<?= $code; ?>" value="0" class="form-control-hv" readonly style="background: #f1f5f9; color: #94a3b8;">
                                    <?php endif; ?>
                                </td>
                                
                                <td data-label="Fecha Cambio">
                                    <input type="date" name="task_date_<?= $code; ?>" value="<?= h($val_date); ?>" class="form-control-hv">
                                </td>
                                
                                <td data-label="Horómetro Grúa (Próximo)">
                                    <?php if ($is_truck): ?>
                                        <input type="number" name="task_next_crane_<?= $code; ?>" value="0" class="form-control-hv" readonly style="background: #f1f5f9; color: #94a3b8;">
                                    <?php else: ?>
                                        <input type="number" 
                                               id="next_crane_<?= $code; ?>" 
                                               name="task_next_crane_<?= $code; ?>" 
                                               value="<?= $val_next_crane; ?>" 
                                               class="form-control-hv" 
                                               min="0"
                                               style="font-weight: 700; color: #10b981;">
                                    <?php endif; ?>
                                </td>
                                
                                <td data-label="Horómetro Camión (Próximo)">
                                    <?php if ($is_truck): ?>
                                        <input type="number" 
                                               id="next_truck_<?= $code; ?>" 
                                               name="task_next_truck_<?= $code; ?>" 
                                               value="<?= $val_next_truck; ?>" 
                                               class="form-control-hv" 
                                               min="0"
                                               style="font-weight: 700; color: #10b981;">
                                    <?php else: ?>
                                        <input type="number" name="task_next_truck_<?= $code; ?>" value="0" class="form-control-hv" readonly style="background: #f1f5f9; color: #94a3b8;">
                                    <?php endif; ?>
                                </td>
                                
                                <td data-label="" style="text-align: center;" class="no-print">
                                    <button type="button" onclick="deleteActivity('<?= $code; ?>', '<?= h($task['name']); ?>')" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 16px;" title="Eliminar actividad">🗑️</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- SECCIÓN 4: DETALLE DE ESLINGAS (CAMPOS EDITABLES DINÁMICOS) -->
                <div class="hv-section-title" style="border-top:1px solid #334155;">Detalle de Eslingas (Certificado DEC 1930 OIN 2941)</div>
                <div style="display:grid; grid-template-columns: 1.8fr 2.2fr;">
                    <div style="border-right: 2px solid #334155; padding-bottom: 12px;">
                        <table class="hv-table" style="width:100%; border:none;">
                            <thead>
                                <tr>
                                    <th style="border-top:none; border-left:none; width: 60px;">Item</th>
                                    <th style="border-top:none;">Nro Precinto *</th>
                                    <th style="border-top:none;">Código Fábrica *</th>
                                    <th style="border-top:none; width: 100px;">Última Insp. *</th>
                                    <th style="border-top:none; border-right:none; width: 40px;" class="no-print">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="slings-tbody">
                                <?php 
                                $prev_slings = json_decode($prev_log['sling_info'] ?? '', true) ?? [];
                                if (empty($prev_slings)) {
                                    $prev_slings = [
                                        ['item' => '21', 'no_precinto' => '', 'cod_fabrica' => '', 'ultima_inspeccion' => date('Y-m-d')],
                                        ['item' => '22', 'no_precinto' => '', 'cod_fabrica' => '', 'ultima_inspeccion' => date('Y-m-d')],
                                        ['item' => '23', 'no_precinto' => '', 'cod_fabrica' => '', 'ultima_inspeccion' => date('Y-m-d')],
                                        ['item' => '24', 'no_precinto' => '', 'cod_fabrica' => '', 'ultima_inspeccion' => date('Y-m-d')]
                                    ];
                                }
                                foreach ($prev_slings as $sling): 
                                ?>
                                    <tr>
                                        <td data-label="Item" style="border-left:none; font-weight:bold; text-align: center; font-size: 12px;">
                                            <input type="text" name="sling_item[]" value="<?= h($sling['item']); ?>" class="form-control-hv" style="font-weight:700; background:transparent; border:none; text-align:center;" required readonly>
                                        </td>
                                        <td data-label="Precinto">
                                            <input type="text" name="sling_precinto[]" value="<?= h($sling['no_precinto']); ?>" class="form-control-hv" placeholder="Nro Precinto" required>
                                        </td>
                                        <td data-label="Cód Fábrica">
                                            <input type="text" name="sling_cod[]" value="<?= h($sling['cod_fabrica']); ?>" class="form-control-hv" placeholder="Cód. Fábrica" required style="font-family: monospace;">
                                        </td>
                                        <td data-label="Última Insp.">
                                            <input type="date" name="sling_date[]" value="<?= h($sling['ultima_inspeccion']); ?>" class="form-control-hv" required>
                                        </td>
                                        <td data-label="" style="border-right:none; text-align: center;" class="no-print">
                                            <button type="button" onclick="this.closest('tr').remove()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 16px;" title="Eliminar eslinga">❌</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addSlingRow()" style="margin-top: 12px; margin-left: 12px; background-color: #f1f5f9; border-color: #cbd5e1; color: #334155; font-size: 12px; font-weight: 700; padding: 8px 12px;">
                            ➕ Agregar Nueva Eslinga
                        </button>
                    </div>
                    <div style="padding:20px; display:flex; flex-direction:column; justify-content:center; line-height:1.6; background:#fffdf5; font-size: 12px;">
                        <p style="font-weight:800; color:#854d0e; margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.5px;">Eslingas del Equipo <?= h($crane['crane_code'] ?: 'G-' . str_pad($crane['id'], 2, '0', STR_PAD_LEFT)); ?></p>
                        <p>Las labores de eslingas nuevas iniciaron a partir del **08 de Febrero de 2024** con vigencia de un (1) año según su uso y condiciones generales. Se debe realizar obligatoriamente la inspección mensual correspondiente por el Departamento de Seguridad Industrial.</p>
                        <p style="margin-top:10px; font-weight:800; color: #1e293b;">Última Inspección General Registrada: 06/02/2024</p>
                    </div>
                </div>

                <!-- SECCIÓN 5: PIE DE FIRMAS Y APROBACIÓN (SELECCIÓN DIRECTA) -->
                <div class="hv-footer">
                    <div class="hv-footer-section">
                        <strong style="font-size:11px; text-transform:uppercase; color:var(--text-muted); display:block;">Nombre del Operador</strong>
                        <input type="text" name="operator_name_signature" id="operator_name_signature" list="operators" value="<?= h($_SESSION['fullname'] ?? ''); ?>" required style="border:none; border-bottom:2px solid #334155; height:40px; width:100%; font-weight:800; font-size:14px; text-transform:uppercase; color: var(--primary); padding:4px 0; background:transparent; outline:none; margin-top:8px;" autocomplete="off" placeholder="ESCRIBE O SELECCIONA OPERADOR">
                        
                        <!-- NUEVA PIZARRA DE FIRMA DIGITAL INTERACTIVA -->
                        <div style="margin-top: 20px; position: relative;" class="no-print">
                            <strong style="font-size:11px; color:#64748b; display:block; margin-bottom:6px; text-transform:uppercase; font-weight:700;">✍️ FIRMAR AQUÍ ABAJO (CON EL DEDO O MOUSE)</strong>
                            <div style="border: 2px dashed #94a3b8; border-radius: 8px; background:#fdfdfd; width:100%; height:140px; position:relative; overflow:hidden; box-shadow: inset 0 2px 6px rgba(0,0,0,0.04);">
                                <canvas id="signature-pad" style="position:absolute; left:0; top:0; width:100%; height:100%; cursor:crosshair; touch-action: none;"></canvas>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                <button type="button" onclick="clearSignature()" style="background:#fee2e2; border:1.5px solid #fca5a5; color:#b91c1c; font-size:11px; cursor:pointer; padding:6px 10px; border-radius:6px; font-weight:700;">🧹 Borrar Trazo</button>
                                <span style="font-size:11px; color:#94a3b8; font-style:italic; font-weight:500;">Trazo digital registrado</span>
                            </div>
                            <!-- Input oculto que enviará el Base64 -->
                            <input type="hidden" name="signature_data" id="hidden-signature-input">
                        </div>
                    </div>
                    <div class="hv-footer-section" style="background:#f8fafc; display:flex; flex-direction:column; justify-content:center; align-items:center;">
                        <strong style="font-size:11px; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:12px; font-weight:700;">Condición Operativa del Equipo *</strong>
                        <div style="display:flex; justify-content:center; align-items:center; width: 100%;">
                            <select name="operating_status" class="form-control-hv" style="font-size:13px; font-weight:800; height: 42px; color:#1e3a8a; border: 2px solid #2563eb; background:#fff; padding: 6px 16px; border-radius: 8px; width: 85%; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.05);">
                                <option value="approved" selected>🟢 APROBADO PARA OPERAR</option>
                                <option value="pending">🟡 OPERACIÓN PENDIENTE</option>
                                <option value="rejected">🔴 NO APROBADO (INAPTO)</option>
                            </select>
                        </div>
                    </div>
                </div>

                </div>

            </div> <!-- Fin .hoja-vida-scroll-wrapper -->

            <!-- BARRA FLOTANTE DE GUARDADO (PERMANENTE EN PANTALLA) -->
            <div class="floating-action-bar no-print">
                <a href="dashboard.php" class="btn btn-secondary" style="background: transparent; border: 1px solid #cbd5e1; color: #fff; padding: 10px 20px;">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="background: #10b981; border: none; padding: 10px 24px; font-weight: 700; font-size: 13px;">💾 Guardar Reporte Preoperacional</button>
            </div>

        </form>
        
        <div style="text-align:center; margin-top:24px; font-size:11px; color:var(--text-muted);" class="no-print">
            <p>Generado digitalmente por G&TC Control de Grúas. Listo para imprimir en formato A4 / Carta.</p>
        </div>

    </div>

    <script>
        // Cálculo automático interactivo del próximo cambio basado en horómetro actual y frecuencia de cada tarea
        function calcNext(code, freq, type) {
            const currInput = document.getElementById('curr_' + type + '_' + code);
            const nextInput = document.getElementById('next_' + type + '_' + code);
            
            if (currInput && nextInput) {
                const val = parseInt(currInput.value) || 0;
                nextInput.value = val + freq;
            }
        }

        // Agregar dinámicamente nueva actividad de mantenimiento sin interferir con el envío del formulario principal
        function submitNewActivity() {
            const code = document.getElementById('new_task_code').value.trim();
            const type = document.getElementById('new_task_type').value;
            const name = document.getElementById('new_task_name').value.trim();
            const freq = document.getElementById('new_frequency').value.trim();

            if (!code || !name || !freq) {
                alert('Por favor, completa todos los campos obligatorios para registrar la actividad.');
                return;
            }

            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'preop_create.php?crane_id=<?= $crane_id; ?>';

            const fields = {
                ajax_add_activity: '1',
                new_task_code: code,
                new_task_type: type,
                new_task_name: name,
                new_frequency: freq
            };

            for (const [k, v] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = k;
                input.value = v;
                tempForm.appendChild(input);
            }

            document.body.appendChild(tempForm);
            tempForm.submit();
        }

        // Eliminar permanentemente una actividad de mantenimiento de la base de datos de esta grúa al vuelo
        function deleteActivity(code, name) {
            if (confirm(`¿Estás seguro de que deseas eliminar permanentemente la actividad "${name}" de esta máquina?`)) {
                const tempForm = document.createElement('form');
                tempForm.method = 'POST';
                tempForm.action = 'preop_create.php?crane_id=<?= $crane_id; ?>';

                const fields = {
                    ajax_delete_activity: '1',
                    delete_task_code: code
                };

                for (const [k, v] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = k;
                    input.value = v;
                    tempForm.appendChild(input);
                }

                document.body.appendChild(tempForm);
                tempForm.submit();
            }
        }

        // Agregar fila dinámica de eslingas
        function addSlingRow() {
            const tbody = document.getElementById('slings-tbody');
            const rowCount = tbody.rows.length + 21; // Generación automática de ítem (21, 22, 23, 24, 25...)
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Item" style="border-left:none; font-weight:bold; text-align: center; font-size: 11px;">
                    <input type="text" name="sling_item[]" value="${rowCount}" class="form-control-hv" style="font-weight:700; background:transparent; border:none;" required readonly>
                </td>
                <td data-label="Precinto">
                    <input type="text" name="sling_precinto[]" value="" class="form-control-hv" placeholder="Nro Precinto" required>
                </td>
                <td data-label="Cód Fábrica">
                    <input type="text" name="sling_cod[]" value="" class="form-control-hv" placeholder="Cód. Fábrica" required style="font-family: monospace;">
                </td>
                <td data-label="Última Insp.">
                    <input type="date" name="sling_date[]" value="${new Date().toISOString().split('T')[0]}" class="form-control-hv" required>
                </td>
                <td data-label="" style="border-right:none; text-align: center;" class="no-print">
                    <button type="button" onclick="this.closest('tr').remove()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 14px;" title="Eliminar eslinga">❌</button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        // Sincronizar el campo de texto superior de Operador con la firma inferior en tiempo real bidireccional
        const operatorNameInput = document.getElementById('operator_name');
        const operatorSignatureInput = document.getElementById('operator_name_signature');
        if (operatorNameInput && operatorSignatureInput) {
            operatorNameInput.addEventListener('input', function() {
                operatorSignatureInput.value = this.value.toUpperCase();
            });
            operatorSignatureInput.addEventListener('input', function() {
                operatorNameInput.value = this.value.toUpperCase();
            });
            
            // Sincronizar inicialmente
            operatorSignatureInput.value = operatorNameInput.value.toUpperCase();
        }

        // --- Lógica de Firma Digital en Canvas ---
        const canvas = document.getElementById('signature-pad');
        const hiddenSigInput = document.getElementById('hidden-signature-input');
        const mainForm = document.getElementById('main-preop-form');
        let isBlank = true; // Para validar si realmente firmó

        if (canvas) {
            const ctx = canvas.getContext('2d');
            
            // Ajustar el tamaño interno del canvas al tamaño visual real en el DOM
            function resizeCanvas() {
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width;
                canvas.height = rect.height;
                // Restaurar configuración de trazo
                ctx.strokeStyle = "#000000";
                ctx.lineWidth = 2;
                ctx.lineCap = "round";
                ctx.lineJoin = "round";
            }
            window.addEventListener('resize', resizeCanvas);
            setTimeout(resizeCanvas, 100); // Esperar a renderizado inicial

            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            function getPosition(e) {
                const rect = canvas.getBoundingClientRect();
                let clientX, clientY;
                if (e.touches && e.touches.length > 0) {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDrawing(e) {
                isDrawing = true;
                isBlank = false;
                const pos = getPosition(e);
                lastX = pos.x;
                lastY = pos.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                if (e.cancelable) e.preventDefault();
            }

            function draw(e) {
                if (!isDrawing) return;
                const pos = getPosition(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                if (e.cancelable) e.preventDefault();
            }

            function stopDrawing() {
                if (isDrawing) ctx.closePath();
                isDrawing = false;
            }

            // Eventos Mouse
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDrawing);

            // Eventos Táctiles (Móvil)
            canvas.addEventListener('touchstart', startDrawing, {passive: false});
            canvas.addEventListener('touchmove', draw, {passive: false});
            canvas.addEventListener('touchend', stopDrawing);

            // Función pública para borrar
            window.clearSignature = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                isBlank = true;
                hiddenSigInput.value = '';
            }

            // Interceptar Envío del Formulario para exportar imagen
            if (mainForm) {
                mainForm.addEventListener('submit', function(e) {
                    // Si no está en blanco, exportar base64. Si está en blanco enviar null.
                    if (!isBlank) {
                        hiddenSigInput.value = canvas.toDataURL('image/png');
                    }
                });
            }
        }
    </script>
</body>
</html>
