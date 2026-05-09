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
    if ($prev_log) {
        $stmt_prev_tasks = $pdo->prepare("SELECT task_code, current_value, last_change_date, next_change_value, status FROM preop_tasks WHERE log_id = ?");
        $stmt_prev_tasks->execute([$prev_log['id']]);
        $prev_tasks = $stmt_prev_tasks->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }
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

    // 4. Estado operativo
    $operating_status = $_POST['operating_status'] ?? 'pending';

    try {
        $pdo->beginTransaction();

        // Guardar reporte de cabecera
        $stmt = $pdo->prepare("INSERT INTO preop_logs (crane_id, operator_id, log_date, horometro_truck, horometro_crane, operator_name, doc_security, doc_medical, doc_card, doc_ppe, doc_extinguisher, sling_info, operating_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
            $operating_status
        ]);
        
        $log_id = $pdo->lastInsertId();

        // Guardar reporte de tareas individuales
        $stmt_task = $pdo->prepare("INSERT INTO preop_tasks (log_id, task_code, task_type, task_name, frequency, current_value, last_change_date, next_change_value, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($default_tasks as $index => $task) {
            $current_val = intval($_POST["task_curr_" . $task['code']] ?? 1);
            $last_date = $_POST["task_date_" . $task['code']] ?? null;
            if (empty($last_date)) $last_date = null;
            $next_val = intval($_POST["task_next_" . $task['code']] ?? ($current_val + $task['freq']));
            $status = $_POST["task_status_" . $task['code']] ?? 'Normal';

            $stmt_task->execute([
                $log_id,
                $task['code'],
                $task['type'],
                $task['name'],
                $task['freq'],
                $current_val,
                $last_date,
                $next_val,
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
    </style>
</head>
<body style="background-color: #f1f5f9; padding-top: 24px; padding-bottom: 120px;">

    <div class="container" style="max-width: 900px;">
        
        <!-- BARRA DE ACCIONES SUPERIOR -->
        <div class="card" style="margin-bottom: 20px; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <a href="dashboard.php" style="text-decoration:none; font-weight:600; color:var(--primary);">Volver al Dashboard</a>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted);">Registrando reporte para: <strong><?= h($crane['brand']); ?> - <?= h($crane['line']); ?></strong></span>
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
            <div class="hoja-vida-container">
                
                <!-- ENCABEZADO -->
                <div class="hv-header">
                    <div class="hv-header-left">
                        <h2 style="font-size:10px; font-weight:bold; color:var(--text-muted); margin-bottom: 2px;">HOJA DE VIDA DE EQUIPO</h2>
                        <h1 style="font-size:14px; font-weight:800; color:#1e3a8a; letter-spacing:0.5px;">GRÚAS Y TRANSPORTES DE COLOMBIA SAS</h1>
                    </div>
                    <div class="hv-header-right">
                        <div class="hv-header-cell"><strong>FORMATO:</strong> MT-F-07</div>
                        <div class="hv-header-cell"><strong>VERSIÓN:</strong> 1</div>
                        <div class="hv-header-cell"><strong>FECHA:</strong> 01-08-2023</div>
                    </div>
                </div>

                <!-- SECCIÓN 1: CARACTERÍSTICAS DEL EQUIPO Y REGISTRO FOTOGRÁFICO -->
                <div class="hv-section-title">Características del Equipo e Identificación Técnica</div>
                
                <div class="hv-grid-2" style="border-bottom: 2px solid #1e293b;">
                    <!-- Detalles de Especificación -->
                    <div style="display:flex; flex-direction:column;">
                        <div class="hv-tech-cell" style="border-right:1px solid #1e293b; background:#f8fafc;">
                            <strong>MÁQUINA:</strong> <?= h($crane['machine_name']); ?>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #1e293b;">
                            <div class="hv-tech-cell" style="border-right:1px solid #1e293b;"><strong>MARCA:</strong> <?= h($crane['brand']); ?></div>
                            <div class="hv-tech-cell" style="border-right:1px solid #1e293b;"><strong>NRO REGISTRO:</strong> MT<?= str_pad($crane['id'], 5, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #1e293b;">
                            <div class="hv-tech-cell" style="border-right:1px solid #1e293b;"><strong>LÍNEA:</strong> <?= h($crane['line']); ?></div>
                            <div class="hv-tech-cell" style="border-right:1px solid #1e293b;"><strong>MODELO:</strong> <?= h($crane['model']); ?></div>
                        </div>
                        <div class="hv-tech-cell" style="border-right:1px solid #1e293b; border-bottom:1px solid #1e293b;">
                            <strong>CAPACIDAD:</strong> <?= h($crane['capacity']); ?>
                        </div>
                        <div class="hv-tech-cell" style="border-right:1px solid #1e293b; border-bottom:1px solid #1e293b;">
                            <strong>SERIE CHASIS:</strong> <?= h($crane['chassis_series']); ?>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #1e293b;">
                            <div class="hv-tech-cell" style="border-right:1px solid #1e293b;"><strong>SERIE MOTOR:</strong> <?= h($crane['motor_series']); ?></div>
                            <div class="hv-tech-cell" style="border-right:1px solid #1e293b;"><strong>COMBUSTIBLE:</strong> <?= h($crane['fuel_type']); ?></div>
                        </div>
                        <div class="hv-tech-cell" style="border-right:1px solid #1e293b; font-size:10px; line-height:1.4;">
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
                <div class="hv-section-title" style="border-top: 1px solid #1e293b;">Validación de Documentación Operativa</div>
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
                        <tr>
                            <td style="font-weight:bold; text-align: center; font-size:12px;">G-<?= str_pad($crane['id'], 2, '0', STR_PAD_LEFT); ?></td>
                            <td style="text-align:center; font-size:11px; font-weight:600;"><?= h($crane['brand']) . ' ' . h($crane['line']); ?></td>
                            <td style="text-align:center; font-size:11px; font-weight:600;"><?= h($crane['model']); ?></td>
                            <td>
                                <input type="number" name="horometro_truck" value="<?= isset($prev_log['horometro_truck']) ? $prev_log['horometro_truck'] : ''; ?>" class="form-control-hv" placeholder="Opcional" min="0" style="font-size:12px; font-weight:700;">
                            </td>
                            <td>
                                <input type="number" name="horometro_crane" value="<?= isset($prev_log['horometro_crane']) ? $prev_log['horometro_crane'] : ''; ?>" class="form-control-hv" placeholder="Opcional" min="0" style="font-size:12px; font-weight:700;">
                            </td>
                            <td>
                                <input type="date" name="log_date" value="<?= date('Y-m-d'); ?>" class="form-control-hv" required style="font-size:11px;">
                            </td>
                            <td>
                                <input type="text" name="operator_name" id="operator_name" list="operators" value="<?= h($_SESSION['fullname'] ?? ''); ?>" class="form-control-hv" required style="text-transform:uppercase; font-size:11px; font-weight:700;" autocomplete="off">
                                <datalist id="operators">
                                    <?php foreach ($operators_list as $op): ?>
                                        <option value="<?= h($op['fullname']); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </td>
                        </tr>
                    </thead>
                </table>

                <!-- AGREGAR ACTIVIDAD AL VUELO (INTEGRADO EN EL FORMULARIO) -->
                <details style="background: #f8fafc; border: 1px dashed var(--border-color); border-radius: 8px; padding: 12px 16px; margin: 20px 0;" class="no-print">
                    <summary style="font-weight: 700; color: var(--primary); cursor: pointer; font-size: 13px;">
                        ➕ ¿Deseas agregar una nueva actividad de mantenimiento a esta máquina sobre la marcha? (Haz clic aquí)
                    </summary>
                    <div style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 1; min-width: 100px;">
                            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Código *</label>
                            <input type="text" id="new_task_code" class="form-control" placeholder="Ej: 920" style="padding: 6px; font-size: 12px;">
                        </div>
                        <div style="flex: 1.5; min-width: 130px;">
                            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Tipo *</label>
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
                            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Nombre de Actividad *</label>
                            <input type="text" id="new_task_name" class="form-control" placeholder="Ej: Cambio de poleas" style="padding: 6px; font-size: 12px;">
                        </div>
                        <div style="flex: 1.5; min-width: 100px;">
                            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Frecuencia (Hrs) *</label>
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
                            <th class="left-align" style="text-align:left; padding-left:8px;">Actividad de Mantenimiento</th>
                            <th style="width: 80px;">Frecuencia</th>
                            <th style="width: 100px;">Cambio Actual</th>
                            <th style="width: 110px;">Fecha de Cambio</th>
                            <th style="width: 100px;">Próximo Cambio</th>
                            <th style="width: 50px;" class="no-print">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($default_tasks as $task): 
                            $code = $task['code'];
                            $val_curr = isset($prev_tasks[$code]) ? $prev_tasks[$code]['current_value'] : 1;
                            $val_date = isset($prev_tasks[$code]) ? $prev_tasks[$code]['last_change_date'] : date('Y-m-d');
                            $val_next = isset($prev_tasks[$code]) ? $prev_tasks[$code]['next_change_value'] : ($val_curr + $task['freq']);
                        ?>
                            <tr>
                                <td style="font-weight:700; text-align: center;"><?= h($code); ?></td>
                                <td style="font-size:8px; font-weight:600; text-transform:uppercase; text-align: center;"><?= h($task['type']); ?></td>
                                <td class="left-align" style="text-align:left; padding-left:8px; font-weight:600;"><?= h($task['name']); ?></td>
                                <td style="text-align: center; font-weight:700;"><?= number_format($task['freq']); ?></td>
                                <td>
                                    <input type="number" 
                                           id="curr_<?= $code; ?>" 
                                           name="task_curr_<?= $code; ?>" 
                                           value="<?= $val_curr; ?>" 
                                           class="form-control-hv" 
                                           oninput="calcNext('<?= $code; ?>', <?= $task['freq']; ?>)"
                                           min="0">
                                </td>
                                <td>
                                    <input type="date" name="task_date_<?= $code; ?>" value="<?= h($val_date); ?>" class="form-control-hv">
                                </td>
                                <td>
                                    <input type="number" 
                                           id="next_<?= $code; ?>" 
                                           name="task_next_<?= $code; ?>" 
                                           value="<?= $val_next; ?>" 
                                           class="form-control-hv" 
                                           min="0">
                                </td>
                                <td style="text-align: center;" class="no-print">
                                    <button type="button" onclick="deleteActivity('<?= $code; ?>', '<?= h($task['name']); ?>')" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 14px;" title="Eliminar actividad">🗑️</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- SECCIÓN 4: DETALLE DE ESLINGAS (CAMPOS EDITABLES DINÁMICOS) -->
                <div class="hv-section-title" style="border-top:1px solid #1e293b;">Detalle de Eslingas (Certificado DEC 1930 OIN 2941)</div>
                <div style="display:grid; grid-template-columns: 1.8fr 2.2fr; font-size:9px;">
                    <div style="border-right: 2px solid #1e293b; padding-bottom: 12px;">
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
                                        <td style="border-left:none; font-weight:bold; text-align: center; font-size: 11px;">
                                            <input type="text" name="sling_item[]" value="<?= h($sling['item']); ?>" class="form-control-hv" style="font-weight:700; background:transparent; border:none;" required readonly>
                                        </td>
                                        <td>
                                            <input type="text" name="sling_precinto[]" value="<?= h($sling['no_precinto']); ?>" class="form-control-hv" placeholder="Nro Precinto" required>
                                        </td>
                                        <td>
                                            <input type="text" name="sling_cod[]" value="<?= h($sling['cod_fabrica']); ?>" class="form-control-hv" placeholder="Cód. Fábrica" required style="font-family: monospace;">
                                        </td>
                                        <td>
                                            <input type="date" name="sling_date[]" value="<?= h($sling['ultima_inspeccion']); ?>" class="form-control-hv" required>
                                        </td>
                                        <td style="border-right:none; text-align: center;" class="no-print">
                                            <button type="button" onclick="this.closest('tr').remove()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 14px;" title="Eliminar eslinga">❌</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addSlingRow()" style="margin-top: 12px; margin-left: 12px; background-color: #f1f5f9; border-color: #cbd5e1; color: #334155; font-size: 11px; font-weight: 700;">
                            ➕ Agregar Nueva Eslinga
                        </button>
                    </div>
                    <div style="padding:15px; display:flex; flex-direction:column; justify-content:center; line-height:1.6; background:#fffdf5; font-size: 10px;">
                        <p style="font-weight:bold; color:#854d0e; margin-bottom:6px; text-transform:uppercase; font-size:10px; letter-spacing: 0.5px;">Eslingas del Equipo G-<?= str_pad($crane['id'], 2, '0', STR_PAD_LEFT); ?></p>
                        <p>Las labores de eslingas nuevas iniciaron a partir del **08 de Febrero de 2024** con vigencia de un (1) año según su uso y condiciones generales. Se debe realizar obligatoriamente la inspección mensual correspondiente por el Departamento de Seguridad Industrial.</p>
                        <p style="margin-top:8px; font-weight:bold; color: #1e293b;">Última Inspección General Registrada: 06/02/2024</p>
                    </div>
                </div>

                <!-- SECCIÓN 5: PIE DE FIRMAS Y APROBACIÓN (SELECCIÓN DIRECTA) -->
                <div class="hv-footer">
                    <div class="hv-footer-section">
                        <strong style="font-size:9px; text-transform:uppercase; color:var(--text-muted);">Nombre del Operador</strong>
                        <div id="signature_operator_name" style="margin-top:10px; border-bottom:1.5px solid #000; height:35px; display:flex; align-items:flex-end; padding-bottom:4px; font-weight:700; font-size:13px; text-transform:uppercase; color: var(--primary);">
                            <?= h($_SESSION['fullname'] ?? 'OPERADOR'); ?>
                        </div>
                    </div>
                    <div class="hv-footer-section" style="background:#eff6ff;">
                        <strong style="font-size:9px; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:8px;">Condición Operativa del Equipo *</strong>
                        <div style="display:flex; justify-content:center; align-items:center; width: 100%;">
                            <select name="operating_status" class="form-control-hv" style="font-size:13px; font-weight:800; height: 38px; color:#1e3a8a; border: 1.5px solid #2563eb; background:#fff; padding: 4px 12px; border-radius: 6px; width: 80%;">
                                <option value="approved" selected>🟢 APROBADO PARA OPERAR</option>
                                <option value="pending">🟡 OPERACIÓN PENDIENTE</option>
                                <option value="rejected">🔴 NO APROBADO (INAPTO)</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>

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
        function calcNext(code, freq) {
            const currInput = document.getElementById('curr_' + code);
            const nextInput = document.getElementById('next_' + code);
            
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
                <td style="border-left:none; font-weight:bold; text-align: center; font-size: 11px;">
                    <input type="text" name="sling_item[]" value="${rowCount}" class="form-control-hv" style="font-weight:700; background:transparent; border:none;" required readonly>
                </td>
                <td>
                    <input type="text" name="sling_precinto[]" value="" class="form-control-hv" placeholder="Nro Precinto" required>
                </td>
                <td>
                    <input type="text" name="sling_cod[]" value="" class="form-control-hv" placeholder="Cód. Fábrica" required style="font-family: monospace;">
                </td>
                <td>
                    <input type="date" name="sling_date[]" value="${new Date().toISOString().split('T')[0]}" class="form-control-hv" required>
                </td>
                <td style="border-right:none; text-align: center;" class="no-print">
                    <button type="button" onclick="this.closest('tr').remove()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 14px;" title="Eliminar eslinga">❌</button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        // Sincronizar campo Nombre de Operador con la firma inferior en tiempo real
        const operatorNameInput = document.getElementById('operator_name');
        const signatureNameDiv = document.getElementById('signature_operator_name');
        if (operatorNameInput && signatureNameDiv) {
            operatorNameInput.addEventListener('input', function() {
                signatureNameDiv.textContent = this.value.trim().toUpperCase() || 'OPERADOR';
            });
            // Ejecutar inicialmente para sincronizar al cargar la página
            signatureNameDiv.textContent = operatorNameInput.value.trim().toUpperCase() || 'OPERADOR';
        }
    </script>
</body>
</html>
