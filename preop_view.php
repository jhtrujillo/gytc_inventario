<?php
require_once 'config.php';
require_login();

$id = intval($_GET['id'] ?? 0);

try {
    // Obtener la cabecera del reporte con los datos de la grúa y del operador
    $stmt = $pdo->prepare("SELECT l.*, c.crane_code, c.machine_name, c.brand, c.line, c.capacity, c.model, c.chassis_series, c.motor_series, c.fuel_type, c.fluids_info, c.image_path, u.fullname as registered_by 
                           FROM preop_logs l 
                           JOIN cranes c ON l.crane_id = c.id 
                           JOIN users u ON l.operator_id = u.id 
                           WHERE l.id = ?");
    $stmt->execute([$id]);
    $log = $stmt->fetch();

    if (!$log) {
        header("Location: dashboard.php?error=" . urlencode("El reporte preoperacional especificado no existe."));
        exit;
    }

    // Decodificar JSONs
    $fluids = json_decode($log['fluids_info'] ?? '', true) ?? [];
    $slings = json_decode($log['sling_info'] ?? '', true) ?? [];

    // Obtener las tareas del reporte
    $stmt_tasks = $pdo->prepare("SELECT * FROM preop_tasks WHERE log_id = ? ORDER BY CAST(task_code AS UNSIGNED) ASC, task_code ASC");
    $stmt_tasks->execute([$id]);
    $tasks = $stmt_tasks->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar el reporte preoperacional: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoja de Vida - <?= h($log['brand']) . ' ' . h($log['line']); ?> - G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilos adicionales específicos para el calco perfecto del diseño físico */
        .hv-table th, .hv-table td {
            border: 1px solid #000;
        }

        .no-print-bar {
            background-color: #1e293b;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        @media print {
            .no-print-bar {
                display: none !important;
            }
            body {
                background-color: white;
                padding-bottom: 0;
            }
            .hoja-vida-container {
                box-shadow: none;
                margin: 0;
                border: 2px solid #000;
            }
        }
    </style>
</head>
<body style="background-color: #f1f5f9; padding-top: 24px;">

    <div class="container" style="max-width: 1300px;">
        
        <!-- BARRA DE ACCIONES (OCULTA AL IMPRIMIR) -->
        <div class="no-print-bar">
            <div style="display:flex; align-items:center; gap:8px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <a href="dashboard.php" style="color:white; text-decoration:none; font-weight:500;">Volver al Dashboard</a>
            </div>
            <div style="display:flex; gap:12px;">
                <button onclick="window.print();" class="btn btn-primary btn-sm" style="background:#10b981; border:none;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Imprimir / Descargar PDF
                </button>
            </div>
        </div>

        <!-- CONTENEDOR DE LA HOJA DE VIDA (IDÉNTICO AL FORMATO FÍSICO) -->
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
                        <strong>MÁQUINA:</strong> <?= h($log['machine_name']); ?>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #cbd5e1;">
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>MARCA:</strong> <?= h($log['brand']); ?></div>
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>NRO REGISTRO:</strong> MT<?= str_pad($log['crane_id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #cbd5e1;">
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>LÍNEA:</strong> <?= h($log['line']); ?></div>
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>MODELO:</strong> <?= h($log['model']); ?></div>
                    </div>
                    <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1; border-bottom:1px solid #cbd5e1;">
                        <strong>CAPACIDAD:</strong> <?= h($log['capacity']); ?>
                    </div>
                    <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1; border-bottom:1px solid #cbd5e1;">
                        <strong>SERIE CHASIS:</strong> <?= h($log['chassis_series']); ?>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; border-bottom:1px solid #cbd5e1;">
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>SERIE MOTOR:</strong> <?= h($log['motor_series']); ?></div>
                        <div class="hv-tech-cell" style="border-right:1px solid #cbd5e1;"><strong>COMBUSTIBLE:</strong> <?= h($log['fuel_type']); ?></div>
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
                    <img src="<?= h($log['image_path'] ?? 'images/crane_xcmg.png'); ?>" alt="Registro Fotográfico Grúa">
                </div>
            </div>

            <!-- SECCIÓN 2: DOCUMENTACIÓN -->
            <div class="hv-section-title" style="border-top: 1px solid #334155;">Validación de Documentación Operativa</div>
            <div class="hv-doc-checklist">
                <div class="hv-doc-row">
                    <span>Planilla de Seguridad del Operador</span>
                    <span class="hv-doc-checkbox"><?= $log['doc_security'] ? 'X' : ' '; ?></span>
                </div>
                <div class="hv-doc-row">
                    <span>Examen Médico Operador</span>
                    <span class="hv-doc-checkbox"><?= $log['doc_medical'] ? 'X' : ' '; ?></span>
                </div>
                <div class="hv-doc-row">
                    <span>Carnet de Operador</span>
                    <span class="hv-doc-checkbox"><?= $log['doc_card'] ? 'X' : ' '; ?></span>
                </div>
                <div class="hv-doc-row">
                    <span>Elementos de EPP</span>
                    <span class="hv-doc-checkbox"><?= $log['doc_ppe'] ? 'X' : ' '; ?></span>
                </div>
                <div class="hv-doc-row" style="border-right:none;">
                    <span>Extintor PQS</span>
                    <span class="hv-doc-checkbox"><?= $log['doc_extinguisher'] ? 'X' : ' '; ?></span>
                </div>
            </div>

            <!-- SECCIÓN 3: CONTROL DE ACTIVIDADES DE MANTENIMIENTO -->
            <div class="hv-section-title">Ficha de Control de Actividades de Mantenimiento</div>
            
            <table class="hv-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Código</th>
                        <th style="width: 100px;">Marca Equipo</th>
                        <th style="width: 80px;">Modelo Equipo</th>
                        <th style="width: 70px;">Horómetro Camión</th>
                        <th style="width: 70px;">Horómetro Grúa</th>
                        <th style="width: 80px;">Fecha Act.</th>
                        <th style="width: 140px;">Operador Equipo</th>
                    </tr>
                    <tr style="background-color: #f1f5f9;">
                        <td style="font-weight:bold; color:#1e3a8a;"><?= h($log['crane_code'] ?: 'G-' . str_pad($log['crane_id'], 2, '0', STR_PAD_LEFT)); ?></td>
                        <td style="font-weight: 600;"><?= h($log['brand']) . ' ' . h($log['line']); ?></td>
                        <td style="font-weight: 600;"><?= h($log['model']); ?></td>
                        <td style="font-weight: 600;"><?= number_format($log['horometro_truck']); ?> Hrs</td>
                        <td style="font-weight: 600;"><?= number_format($log['horometro_crane']); ?> Hrs</td>
                        <td style="font-weight: 600;"><?= date('d/m/Y', strtotime($log['log_date'])); ?></td>
                        <td style="text-transform:uppercase; font-weight:700; color:#1e3a8a;"><?= h($log['operator_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th class="left-align" style="text-align:left; padding-left:12px;">Actividad de Mantenimiento</th>
                        <th>Frecuencia</th>
                        <th>Horómetro Grúa (Actual)</th>
                        <th>Horómetro Camión (Actual)</th>
                        <th>Fecha de Cambio</th>
                        <th>Horómetro Grúa (Próximo)</th>
                        <th>Horómetro Camión (Próximo)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): 
                        $is_truck = (stripos($task['task_name'], 'camion') !== false || stripos($task['task_name'], 'camión') !== false || in_array(substr($task['task_code'], 0, 1), ['1', '2', '3', '4', '5']));
                        $curr_crane = $is_truck ? '-' : number_format($task['current_value']) . ' Hrs';
                        $curr_truck = $is_truck ? number_format($task['current_value_truck']) . ' Hrs' : '-';
                        $next_crane = $is_truck ? '-' : number_format($task['next_change_value']) . ' Hrs';
                        $next_truck = $is_truck ? number_format($task['next_change_value_truck']) . ' Hrs' : '-';
                    ?>
                        <tr>
                            <td style="font-weight:700; color: #1e3a8a;"><?= h($task['task_code']); ?></td>
                            <td style="font-weight:700; text-transform:uppercase; color:#64748b; font-size: 10px;"><?= h($task['task_type']); ?></td>
                            <td class="left-align" style="text-align:left; padding-left:12px; font-weight:600;"><?= h($task['task_name']); ?></td>
                            <td><?= number_format($task['frequency']); ?></td>
                            <td><?= $curr_crane; ?></td>
                            <td><?= $curr_truck; ?></td>
                            <td><?= $task['last_change_date'] ? date('d/m/Y', strtotime($task['last_change_date'])) : 'N/A'; ?></td>
                            <td style="font-weight:700; color: #10b981;"><?= $next_crane; ?></td>
                            <td style="font-weight:700; color: #10b981;"><?= $next_truck; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- SECCIÓN 4: DETALLE DE ESLINGAS -->
            <div class="hv-section-title" style="border-top:1px solid #334155;">Detalle de Eslingas (Certificado DEC 1930 OIN 2941)</div>
            <div style="display:grid; grid-template-columns: 1.5fr 2.5fr; font-size:12px;">
                <div style="border-right: 2px solid #334155;">
                    <table class="hv-table" style="width:100%; border:none;">
                        <thead>
                            <tr>
                                <th style="border-top:none; border-left:none;">Item</th>
                                <th style="border-top:none;">Nro Precinto</th>
                                <th style="border-top:none; border-right:none;">Código Fábrica</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slings as $sling): ?>
                                <tr>
                                    <td style="border-left:none; font-weight:bold;"><?= h($sling['item']); ?></td>
                                    <td><?= h($sling['no_precinto']); ?></td>
                                    <td style="border-right:none; font-family:monospace;"><?= h($sling['cod_fabrica']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding:16px; display:flex; flex-direction:column; justify-content:center; line-height:1.6; background:#fffdf5;">
                    <p style="font-weight:800; color:#854d0e; margin-bottom:8px; text-transform:uppercase;">Eslingas del Equipo <?= h($log['crane_code'] ?: 'G-' . str_pad($log['crane_id'], 2, '0', STR_PAD_LEFT)); ?></p>
                    <p>Las labores de eslingas nuevas iniciaron a partir del **08 de Febrero de 2024** con vigencia de un (1) año según su uso y condiciones generales. Se debe realizar obligatoriamente la inspección mensual correspondiente por el Departamento de Seguridad Industrial.</p>
                    <p style="margin-top:10px; font-weight:800; color:#1e293b;">Última Inspección General Registrada: 06/02/2024</p>
                </div>
            </div>

            <!-- SECCIÓN 5: PIE DE FIRMAS Y APROBACIÓN -->
            <div class="hv-footer">
                <div class="hv-footer-section">
                    <strong style="font-size:11px; text-transform:uppercase; color:var(--text-muted); display:block;">Nombre del Operador</strong>
                    <div style="margin-top:8px; border-bottom:2px solid #334155; height:80px; display:flex; align-items:center; justify-content:center; position:relative; background: white; border-radius:4px; box-shadow:inset 0 1px 3px rgba(0,0,0,0.05);">
                        <?php if (!empty($log['signature_data'])): ?>
                            <img src="<?= $log['signature_data']; ?>" style="max-height:95%; max-width:95%; object-fit:contain; mix-blend-mode:multiply;">
                        <?php endif; ?>
                    </div>
                    <div style="font-weight:800; font-size:13px; text-transform:uppercase; text-align:center; margin-top:8px; color:#1e3a8a;">
                        <?= h($log['operator_name']); ?>
                    </div>
                </div>
                <div class="hv-footer-section" style="background:#f8fafc; display:flex; flex-direction:column; justify-content:center; align-items:center;">
                    <strong style="font-size:11px; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:16px;">Condición Operativa del Equipo</strong>
                    <div style="display:flex; justify-content:space-around; align-items:center; width:100%;">
                        <span class="status-badge <?= $log['operating_status'] === 'approved' ? 'status-badge-approved' : ($log['operating_status'] === 'pending' ? 'status-badge-pending' : 'status-badge-rejected'); ?>">
                            <?php 
                            if ($log['operating_status'] === 'approved') echo "APROBADO PARA OPERAR";
                            elseif ($log['operating_status'] === 'pending') echo "OPERACIÓN PENDIENTE";
                            else echo "NO APROBADO (INAPTO)";
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            </div>
        </div> <!-- Fin .hoja-vida-scroll-wrapper -->
        
        <div style="text-align:center; margin-top:24px; font-size:11px; color:var(--text-muted);" class="no-print">
            <p>Generado digitalmente por G&TC Control de Grúas. Listo para imprimir en formato A4 / Carta.</p>
        </div>

    </div>

</body>
</html>
