<?php
require_once 'config.php';
require_login();

$crane_id = intval($_GET['crane_id'] ?? 0);

try {
    // Cargar los datos de la grúa
    $stmt = $pdo->prepare("SELECT * FROM cranes WHERE id = ?");
    $stmt->execute([$crane_id]);
    $crane = $stmt->fetch();

    if (!$crane) {
        header("Location: dashboard.php?error=" . urlencode("La grúa especificada no existe."));
        exit;
    }

    $fluids = json_decode($crane['fluids_info'] ?? '', true) ?? [];

    // Cargar historial de reportes preoperacionales de esta grúa
    $stmt_logs = $pdo->prepare("SELECT l.*, u.fullname as operator_full FROM preop_logs l 
                                JOIN users u ON l.operator_id = u.id 
                                WHERE l.crane_id = ? 
                                ORDER BY l.log_date DESC, l.id DESC");
    $stmt_logs->execute([$crane_id]);
    $crane_logs = $stmt_logs->fetchAll();

    // Cargar las actividades de mantenimiento registradas
    $stmt_tasks = $pdo->prepare("SELECT * FROM crane_activities WHERE crane_id = ? ORDER BY task_code ASC");
    $stmt_tasks->execute([$crane_id]);
    $activities = $stmt_tasks->fetchAll();

    // Cargar los documentos anexos del equipo
    $stmt_docs = $pdo->prepare("SELECT * FROM crane_documents WHERE crane_id = ? ORDER BY uploaded_at DESC");
    $stmt_docs->execute([$crane_id]);
    $documents = $stmt_docs->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar los datos de la grúa: " . $e->getMessage());
}

$error_msg = $_GET['error'] ?? '';
$success_msg = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista de Equipo - <?= h($crane['brand']) . ' ' . h($crane['line']); ?> - G&TC</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .crane-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 32px;
            margin-bottom: 24px;
        }
        .crane-header-title h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary);
        }
        .crane-header-title p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }
        .tabs-container {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 24px;
        }
        .tab-button {
            padding: 12px 24px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
        }
        .tab-button:hover {
            color: var(--primary);
        }
        .tab-button.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .badge {
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-success { background-color: #ecfdf5; color: #065f46; }
        .badge-warning { background-color: #fffbeb; color: #92400e; }
        .badge-danger { background-color: #fef2f2; color: #991b1b; }

        /* Estilo para Ficha Técnica en Web */
        .ficha-web-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
        }
        .spec-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .spec-item:last-child {
            border-bottom: none;
        }
        .spec-item strong {
            color: var(--text-muted);
            font-weight: 500;
        }
        .spec-item span {
            color: var(--text-main);
            font-weight: 600;
        }

        /* Estilos para Gestión de Documentos */
        .docs-grid {
            display: grid;
            grid-template-columns: 2fr 1.2fr;
            gap: 24px;
            margin-top: 16px;
        }
        @media (max-width: 768px) {
            .docs-grid {
                grid-template-columns: 1fr;
            }
        }
        .doc-item-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        .doc-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: var(--primary);
        }
        .doc-meta {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .doc-icon {
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 8px;
        }
        .doc-details h4 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }
        .doc-details p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 4px 0 0 0;
        }
        .doc-actions {
            display: flex;
            gap: 8px;
        }

        /* Drag and Drop Zone */
        .drag-drop-zone {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 24px 16px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            margin-bottom: 8px;
        }
        .drag-drop-zone:hover, .drag-drop-zone.dragover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .drag-drop-zone p {
            margin: 8px 0 0 0;
            font-size: 13px;
            color: var(--text-muted);
        }
        .drag-drop-zone strong {
            color: var(--primary);
        }
        .drop-icon {
            font-size: 28px;
            display: block;
        }
        #file-info {
            display: none;
            margin-top: 12px;
            padding: 8px 12px;
            background: #ecfdf5;
            border: 1px solid #10b981;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #065f46;
            text-align: left;
            word-break: break-all;
        }
    </style>
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
                <a href="dashboard.php" class="btn btn-secondary btn-sm">Volver al Dashboard</a>
            </div>
        </div>
    </header>

    <main class="container">
        
        <div class="crane-header">
            <div class="crane-header-title">
                <h2>[<?= h($crane['crane_code'] ?: 'S/C'); ?>] <?= h($crane['machine_name']); ?></h2>
                <p>Identificación Técnica y Control Operativo: <strong><?= h($crane['brand']); ?> - <?= h($crane['line']); ?></strong></p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="preop_create.php?crane_id=<?= $crane['id']; ?>" class="btn btn-primary">Registrar Preoperacional</a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="crane_edit.php?id=<?= $crane['id']; ?>" class="btn btn-secondary">✏️ Editar Ficha</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error"><span><?= h($error_msg); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><span><?= h($success_msg); ?></span></div>
        <?php endif; ?>

        <!-- TABS SWITCHER -->
        <div class="tabs-container">
            <button class="tab-button active" onclick="switchTab(event, 'tab-ficha')">📋 Ficha Técnica</button>
            <button class="tab-button" onclick="switchTab(event, 'tab-historial')">🕒 Historial de Preoperacionales (<?= count($crane_logs); ?>)</button>
            <button class="tab-button" onclick="switchTab(event, 'tab-actividades')">🔧 Actividades de Mantenimiento (<?= count($activities); ?>)</button>
            <button class="tab-button" onclick="switchTab(event, 'tab-documentos')">📄 Documentos (<?= count($documents); ?>)</button>
        </div>

        <!-- CONTENIDO TAB 1: FICHA TÉCNICA -->
        <div id="tab-ficha" class="tab-content active">
            <div class="ficha-web-grid">
                <div class="card" style="padding: 24px;">
                    <h3 style="font-size: 16px; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-bottom: 16px; font-weight: 700; color: var(--primary);">Especificaciones Técnicas</h3>
                    
                    <div class="spec-item">
                        <strong>Código del Equipo</strong>
                        <span style="font-weight: 700; color: var(--primary);"><?= h($crane['crane_code'] ?: 'Sin código'); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Marca del Equipo</strong>
                        <span><?= h($crane['brand']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Línea / Referencia</strong>
                        <span><?= h($crane['line']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Modelo (Año)</strong>
                        <span><?= h($crane['model']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Capacidad Nominal</strong>
                        <span><?= h($crane['capacity']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Serie Chasis</strong>
                        <span><?= h($crane['chassis_series']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Serie Motor</strong>
                        <span><?= h($crane['motor_series']); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Tipo de Combustible</strong>
                        <span><?= h($crane['fuel_type']); ?></span>
                    </div>

                    <h3 style="font-size: 16px; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px; font-weight: 700; color: var(--primary);">Fluidos y Capacidades</h3>
                    <div class="spec-item">
                        <strong>Aceite Motor</strong>
                        <span><?= h($fluids['aceite_motor'] ?: 'No registrado'); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Aceite Hidráulico</strong>
                        <span><?= h($fluids['aceite_hidraulico'] ?: 'No registrado'); ?></span>
                    </div>
                    <div class="spec-item">
                        <strong>Combustible (Capacidad)</strong>
                        <span><?= h($fluids['combustible'] ?: 'No registrado'); ?></span>
                    </div>
                </div>

                <!-- Foto del Equipo -->
                <div class="card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px;">
                    <h3 style="font-size: 16px; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-bottom: 16px; font-weight: 700; color: var(--primary); width: 100%; text-align: left;">Registro Fotográfico</h3>
                    <div style="width: 100%; max-height: 350px; overflow: hidden; border-radius: 8px; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; background: #f8fafc; padding: 12px;">
                        <img src="<?= h($crane['image_path'] ?: 'images/crane_xcmg.png'); ?>" alt="Foto de la Grúa" style="max-width: 100%; max-height: 320px; border-radius: 6px; object-fit: contain;">
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTENIDO TAB 2: HISTORIAL DE PREOPERACIONALES -->
        <div id="tab-historial" class="tab-content">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
                <a href="export_excel.php?crane_id=<?= $crane['id']; ?>" class="btn btn-secondary btn-sm" style="background-color: #10b981; color: white; border: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    📥 Exportar Historial Completo a Excel
                </a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Operador</th>
                            <th>Horómetro Camión</th>
                            <th>Horómetro Grúa</th>
                            <th>Estado Operativo</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($crane_logs) > 0): ?>
                            <?php foreach ($crane_logs as $log): ?>
                                <tr>
                                    <td data-label="Fecha"><strong><?= date('d/m/Y', strtotime($log['log_date'])); ?></strong></td>
                                    <td data-label="Operador"><?= h($log['operator_full']); ?></td>
                                    <td data-label="Horómetro Camión"><?= h($log['horometro_truck']); ?> Hrs</td>
                                    <td data-label="Horómetro Grúa"><?= h($log['horometro_crane']); ?> Hrs</td>
                                    <td data-label="Estado Operativo">
                                        <span class="badge <?= $log['operating_status'] === 'approved' ? 'badge-success' : ($log['operating_status'] === 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?= $log['operating_status'] === 'approved' ? 'Aprobado' : ($log['operating_status'] === 'pending' ? 'Pendiente' : 'Rechazado'); ?>
                                        </span>
                                    </td>
                                    <td data-label="" style="text-align: right;">
                                        <a href="preop_view.php?id=<?= $log['id']; ?>" class="btn btn-secondary btn-sm">📄 Ver / Imprimir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">No se registran preoperacionales anteriores para esta grúa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONTENIDO TAB 3: ACTIVIDADES DE MANTENIMIENTO -->
        <div id="tab-actividades" class="tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 16px; font-weight: 700; color: var(--primary);">Actividades de Mantenimiento Configuradas</h3>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="crane_activities.php?crane_id=<?= $crane['id']; ?>" class="btn btn-secondary btn-sm">⚙️ Configurar Actividades</a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Código</th>
                            <th style="width: 150px;">Tipo</th>
                            <th>Actividad de Mantenimiento</th>
                            <th style="width: 180px; text-align: center;">Frecuencia (Hrs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $act): ?>
                                <tr>
                                    <td data-label="Código"><strong><?= h($act['task_code']); ?></strong></td>
                                    <td data-label="Tipo"><span class="badge" style="background:#e2e8f0; color:#475569;"><?= h($act['task_type']); ?></span></td>
                                    <td data-label="Actividad de Mantenimiento"><?= h($act['task_name']); ?></td>
                                    <td data-label="Frecuencia (Hrs)" style="text-align: center; font-weight: 600;"><?= number_format($act['frequency']); ?> Hrs</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 32px;">No hay actividades configuradas para esta grúa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONTENIDO TAB 4: DOCUMENTOS DEL EQUIPO -->
        <div id="tab-documentos" class="tab-content">
            <div class="docs-grid">
                
                <!-- Lista de Documentos -->
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 16px; margin-top: 0;">Documentos Relacionados</h3>
                    
                    <div id="documents-list">
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $doc): 
                            $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                            $icon = '📄';
                            if ($ext === 'pdf') $icon = '📕';
                            elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) $icon = '🖼️';
                            elseif (in_array($ext, ['xls', 'xlsx'])) $icon = '📊';
                            elseif (in_array($ext, ['doc', 'docx'])) $icon = '📘';
                            elseif (in_array($ext, ['zip', 'rar'])) $icon = '📦';
                        ?>
                            <div class="doc-item-card">
                                <div class="doc-meta">
                                    <div class="doc-icon"><?= $icon; ?></div>
                                    <div class="doc-details">
                                        <h4><?= h($doc['document_name']); ?></h4>
                                        <p>Subido el: <?= date('d/m/Y h:i A', strtotime($doc['uploaded_at'])); ?></p>
                                    </div>
                                </div>
                                <div class="doc-actions">
                                    <a href="<?= h($doc['file_path']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                        👁️ Ver
                                    </a>
                                    <a href="delete_document.php?id=<?= $doc['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de que desea eliminar este documento?');" style="background-color: #ef4444; border-color: #ef4444; color: white; display: inline-flex; align-items: center; gap: 4px;">
                                        🗑️ Eliminar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card no-docs-card" style="text-align: center; color: var(--text-muted); padding: 48px;">
                            <span style="font-size: 48px; display: block; margin-bottom: 16px;">📂</span>
                            No hay documentos registrados para este equipo.
                        </div>
                    <?php endif; ?>
                    </div>
                </div>

                <!-- Formulario de Carga -->
                <div>
                    <div class="card" style="padding: 24px; position: sticky; top: 20px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: var(--primary); margin-top: 0; border-bottom: 2px solid var(--primary-light); padding-bottom: 8px; margin-bottom: 20px;">Subir Nuevo Documento</h3>
                        
                        <form id="upload-form" action="upload_document.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="crane_id" value="<?= $crane['id']; ?>">
                            <input type="hidden" name="ajax" value="1">
                            
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label for="document_name" style="display: block; font-weight: 600; font-size: 13px; color: var(--text-main); margin-bottom: 6px;">Nombre del Documento / Descripción</label>
                                <input type="text" id="document_name" name="document_name" placeholder="Ej. Tarjeta de Propiedad, Seguro, Tecno" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; font-size: 13px; color: var(--text-main); margin-bottom: 6px;">Seleccionar Archivo</label>
                                <div class="drag-drop-zone" id="drop-zone">
                                    <span class="drop-icon">📂</span>
                                    <p>Arrastra tu archivo aquí o <strong>haz clic para buscar</strong></p>
                                    <input type="file" id="document" name="document" required style="display: none;">
                                    <div id="file-info"></div>
                                </div>
                                <small style="display: block; color: var(--text-muted); font-size: 11px; margin-top: 6px;">Formatos permitidos: PDF, Word, Excel, ZIP, Imágenes. Máx: 15MB.</small>
                            </div>

                            <!-- Barra de Progreso de Carga -->
                            <div id="progress-container" style="display: none; margin-bottom: 20px;">
                                <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; font-weight: 600; color: var(--text-muted);">
                                    <span>Subiendo archivo...</span>
                                    <span id="progress-percent">0%</span>
                                </div>
                                <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                    <div id="progress-bar" style="width: 0%; height: 100%; background: var(--primary); transition: width 0.1s ease;"></div>
                                </div>
                            </div>

                            <!-- Alertas de carga en AJAX -->
                            <div id="upload-alert" class="alert" style="display: none; margin-bottom: 20px; font-size: 13px; padding: 10px 12px;"></div>
                            
                            <button type="submit" id="submit-btn" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; padding: 12px;">
                                📤 Subir Archivo
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <script>
        function escapeHtml(str) {
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }

        function switchTab(event, tabId) {
            // Desactivar todos los botones de tab
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            // Desactivar todos los contenidos de tab
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Activar tab actual
            if (event) {
                event.currentTarget.classList.add('active');
            } else {
                const btn = Array.from(document.querySelectorAll('.tab-button')).find(b => {
                    const clickAttr = b.getAttribute('onclick');
                    return clickAttr && clickAttr.includes(tabId);
                });
                if (btn) btn.classList.add('active');
            }
            document.getElementById(tabId).classList.add('active');
        }

        // Auto-activar tab según el hash de la URL (ej. #documentos)
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash;
            if (hash) {
                const tabId = 'tab-' + hash.substring(1);
                if (document.getElementById(tabId)) {
                    switchTab(null, tabId);
                }
            }
        });

        // Lógica de arrastrar y soltar (Drag and Drop) para archivos
        document.addEventListener('DOMContentLoaded', () => {
            const dropZone = document.getElementById('drop-zone');
            const fileInput = document.getElementById('document');
            const fileInfo = document.getElementById('file-info');
            const docNameInput = document.getElementById('document_name');

            if (dropZone && fileInput) {
                // Abrir selector al hacer clic en la zona
                dropZone.addEventListener('click', () => fileInput.click());

                // Resaltar zona al arrastrar archivo sobre ella
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        dropZone.classList.add('dragover');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        dropZone.classList.remove('dragover');
                    }, false);
                });

                // Capturar el archivo soltado
                dropZone.addEventListener('drop', (e) => {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        updateFileInfo();
                    }
                });

                // Capturar selección manual de archivo
                fileInput.addEventListener('change', updateFileInfo);

                function updateFileInfo() {
                    if (fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        fileInfo.textContent = `📄 Seleccionado: ${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
                        fileInfo.style.display = 'block';

                        // Pre-llenar nombre del documento con el nombre del archivo sin extensión
                        if (docNameInput && docNameInput.value.trim() === '') {
                            const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                            docNameInput.value = nameWithoutExt;
                        }
                    } else {
                        fileInfo.style.display = 'none';
                    }
                }

                // Envío de archivo con barra de progreso mediante AJAX/XHR
                const uploadForm = document.getElementById('upload-form');
                const progressContainer = document.getElementById('progress-container');
                const progressBar = document.getElementById('progress-bar');
                const progressPercent = document.getElementById('progress-percent');
                const submitBtn = document.getElementById('submit-btn');
                const uploadAlert = document.getElementById('upload-alert');

                if (uploadForm) {
                    uploadForm.addEventListener('submit', (e) => {
                        e.preventDefault();

                        if (fileInput.files.length === 0) {
                            showUploadAlert('error', 'Debe seleccionar o arrastrar un archivo.');
                            return;
                        }

                        // Configuración inicial de UI
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.7';
                        submitBtn.textContent = 'Enviando...';
                        progressContainer.style.display = 'block';
                        progressBar.style.width = '0%';
                        progressPercent.textContent = '0%';
                        uploadAlert.style.display = 'none';

                        const formData = new FormData(uploadForm);
                        const xhr = new XMLHttpRequest();

                        xhr.open('POST', 'upload_document.php', true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                        // Evento de progreso de carga
                        xhr.upload.addEventListener('progress', (event) => {
                            if (event.lengthComputable) {
                                const percent = Math.round((event.loaded / event.total) * 100);
                                progressBar.style.width = percent + '%';
                                progressPercent.textContent = percent + '%';
                            }
                        });

                        // Respuesta del servidor
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.status === 'success') {
                                        showUploadAlert('success', response.message);
                                        
                                        // Insertar dinámicamente el nuevo documento en la lista
                                        const doc = response.document;
                                        const ext = doc.file_path.split('.').pop().toLowerCase();
                                        let icon = '📄';
                                        if (ext === 'pdf') icon = '📕';
                                        else if (['png', 'jpg', 'jpeg'].includes(ext)) icon = '🖼️';
                                        else if (['xls', 'xlsx'].includes(ext)) icon = '📊';
                                        else if (['doc', 'docx'].includes(ext)) icon = '📘';
                                        else if (['zip', 'rar'].includes(ext)) icon = '📦';

                                        const docCard = document.createElement('div');
                                        docCard.className = 'doc-item-card';
                                        docCard.style.opacity = '0';
                                        docCard.style.transform = 'translateY(-10px)';
                                        docCard.style.transition = 'all 0.3s ease-in-out';
                                        docCard.innerHTML = `
                                            <div class="doc-meta">
                                                <div class="doc-icon">${icon}</div>
                                                <div class="doc-details">
                                                    <h4>${escapeHtml(doc.document_name)}</h4>
                                                    <p>Subido el: ${doc.uploaded_at}</p>
                                                </div>
                                            </div>
                                            <div class="doc-actions">
                                                <a href="${escapeHtml(doc.file_path)}" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                    👁️ Ver
                                                </a>
                                                <a href="delete_document.php?id=${doc.id}" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de que desea eliminar este documento?');" style="background-color: #ef4444; border-color: #ef4444; color: white; display: inline-flex; align-items: center; gap: 4px;">
                                                    🗑️ Eliminar
                                                </a>
                                            </div>
                                        `;

                                        // Eliminar el mensaje de "no hay documentos" si estaba visible
                                        const noDocsCard = document.querySelector('.no-docs-card');
                                        if (noDocsCard) {
                                            noDocsCard.remove();
                                        }

                                        const listContainer = document.getElementById('documents-list');
                                        if (listContainer) {
                                            listContainer.insertBefore(docCard, listContainer.firstChild);
                                        }

                                        // Disparar animación de desvanecimiento hacia adentro
                                        setTimeout(() => {
                                            docCard.style.opacity = '1';
                                            docCard.style.transform = 'translateY(0)';
                                        }, 50);

                                        // Actualizar contador del tab de documentos
                                        const tabBtn = Array.from(document.querySelectorAll('.tab-button')).find(btn => {
                                            const clickAttr = btn.getAttribute('onclick');
                                            return clickAttr && clickAttr.includes('tab-documentos');
                                        });
                                        if (tabBtn && listContainer) {
                                            const currentCount = listContainer.querySelectorAll('.doc-item-card').length;
                                            tabBtn.innerHTML = `📄 Documentos (${currentCount})`;
                                        }

                                        // Reiniciar UI del formulario tras un pequeño delay
                                        setTimeout(() => {
                                            resetFormState();
                                            uploadForm.reset();
                                            if (fileInfo) fileInfo.style.display = 'none';
                                            if (uploadAlert) uploadAlert.style.display = 'none';
                                        }, 1500);

                                    } else {
                                        resetFormState();
                                        showUploadAlert('error', response.message);
                                    }
                                } catch (err) {
                                    resetFormState();
                                    showUploadAlert('error', 'Error al procesar la respuesta del servidor.');
                                }
                            } else {
                                resetFormState();
                                showUploadAlert('error', 'Ocurrió un error al comunicarse con el servidor.');
                            }
                        };

                        xhr.onerror = function() {
                            resetFormState();
                            showUploadAlert('error', 'Error de red al intentar subir el archivo.');
                        };

                        xhr.send(formData);
                    });
                }

                function showUploadAlert(type, message) {
                    if (uploadAlert) {
                        uploadAlert.textContent = message;
                        uploadAlert.style.display = 'block';
                        uploadAlert.className = type === 'success' ? 'alert alert-success' : 'alert alert-error';
                    }
                }

                function resetFormState() {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                        submitBtn.textContent = '📤 Subir Archivo';
                    }
                    if (progressContainer) {
                        progressContainer.style.display = 'none';
                    }
                }

            }
        });
    </script>
</body>
</html>
