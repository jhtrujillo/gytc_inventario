<?php
require_once 'config.php';
require_login();

$crane_id = intval($_GET['crane_id'] ?? 0);
if ($crane_id === 0) {
    die("ID de grúa no especificado.");
}

try {
    // 1. Obtener información de la grúa
    $stmt_crane = $pdo->prepare("SELECT * FROM cranes WHERE id = ?");
    $stmt_crane->execute([$crane_id]);
    $crane = $stmt_crane->fetch();
    if (!$crane) {
        die("La grúa especificada no existe.");
    }

    $filename = "Historial_Preoperacionales_" . ($crane['crane_code'] ?: 'G-' . $crane_id) . "_" . date('Ymd_His') . ".xls";

    // Headers para forzar la descarga de Excel
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // 2. Obtener todas las actividades detalladas asociadas a los reportes de la grúa
    $stmt_tasks = $pdo->prepare("
        SELECT 
            c.crane_code,
            l.id as log_id,
            l.log_date,
            t.task_code,
            t.task_type,
            t.task_name,
            t.current_value,
            t.current_value_truck,
            t.last_change_date,
            t.next_change_value,
            t.next_change_value_truck,
            l.operator_name
        FROM preop_tasks t
        JOIN preop_logs l ON t.log_id = l.id
        JOIN cranes c ON l.crane_id = c.id
        WHERE l.crane_id = ?
        ORDER BY l.log_date DESC, l.id DESC, CAST(t.task_code AS UNSIGNED) ASC, t.task_code ASC
    ");
    $stmt_tasks->execute([$crane_id]);
    $tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

    // Escribir cabecera HTML/Excel con codificación UTF-8
    echo "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\">";
    echo "<head><meta http-equiv=\"Content-type\" content=\"text/html;charset=utf-8\" /></head>";
    echo "<body>";
    echo "<table border='1'>";
    echo "<tr style='background-color: #1e3a8a; color: white; font-weight: bold;'>
            <th>EQUIPO</th>
            <th>CODIGO REPORTE</th>
            <th>FECHA ACTUALIZACION</th>
            <th>CODIGO TAREA</th>
            <th>TIPO TAREA</th>
            <th>ACTIVIDAD REALIZADA</th>
            <th>HOROMETRO ACTUAL (GRUA)</th>
            <th>HOROMETRO ACTUAL (CAMION)</th>
            <th>FECHA ULTIMO CAMBIO</th>
            <th>HOROMETRO PROXIMO (GRUA)</th>
            <th>HOROMETRO PROXIMO (CAMION)</th>
            <th>OPERADOR</th>
          </tr>";

    foreach ($tasks as $t) {
        $fecha_act = date('d/m/Y', strtotime($t['log_date']));
        $fecha_ult = $t['last_change_date'] ? date('d/m/Y', strtotime($t['last_change_date'])) : '-';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($t['crane_code'] ?: 'S/C') . "</td>";
        echo "<td>" . htmlspecialchars($t['log_id']) . "</td>";
        echo "<td>" . htmlspecialchars($fecha_act) . "</td>";
        echo "<td>" . htmlspecialchars($t['task_code']) . "</td>";
        echo "<td>" . htmlspecialchars($t['task_type']) . "</td>";
        echo "<td>" . htmlspecialchars($t['task_name']) . "</td>";
        echo "<td>" . htmlspecialchars($t['current_value']) . "</td>";
        echo "<td>" . htmlspecialchars($t['current_value_truck']) . "</td>";
        echo "<td>" . htmlspecialchars($fecha_ult) . "</td>";
        echo "<td>" . htmlspecialchars($t['next_change_value']) . "</td>";
        echo "<td>" . htmlspecialchars($t['next_change_value_truck']) . "</td>";
        echo "<td>" . htmlspecialchars($t['operator_name']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</body>";
    echo "</html>";

} catch (Exception $e) {
    die("Error al exportar a Excel: " . $e->getMessage());
}
