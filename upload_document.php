<?php
require_once 'config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$is_ajax = isset($_POST['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
$crane_id = intval($_POST['crane_id'] ?? 0);
$document_name = trim($_POST['document_name'] ?? '');

function send_response($status, $message, $crane_id, $data = null) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        $response = ['status' => $status, 'message' => $message];
        if ($data !== null) {
            $response = array_merge($response, $data);
        }
        echo json_encode($response);
        exit;
    } else {
        $param = $status === 'success' ? 'success' : 'error';
        header("Location: crane_view.php?crane_id=$crane_id&" . $param . "=" . urlencode($message));
        exit;
    }
}

if ($crane_id <= 0) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Equipo no válido.']);
        exit;
    }
    header("Location: dashboard.php?error=" . urlencode("Equipo no válido."));
    exit;
}

// Verificar que el equipo exista
$stmt = $pdo->prepare("SELECT id FROM cranes WHERE id = ?");
$stmt->execute([$crane_id]);
if (!$stmt->fetchColumn()) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'El equipo especificado no existe.']);
        exit;
    }
    header("Location: dashboard.php?error=" . urlencode("El equipo especificado no existe."));
    exit;
}

if (empty($document_name)) {
    send_response('error', 'El nombre del documento es obligatorio.', $crane_id);
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $err_code = $_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE;
    $err_msg = "Error al subir el archivo.";
    if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
        $err_msg = "El archivo excede el tamaño máximo permitido.";
    } elseif ($err_code === UPLOAD_ERR_NO_FILE) {
        $err_msg = "Debe seleccionar un archivo para subir.";
    }
    send_response('error', $err_msg, $crane_id);
}

$file = $_FILES['document'];
$filename = $file['name'];
$tmp_name = $file['tmp_name'];
$size = $file['size'];

// Validar tamaño máximo (15MB)
$max_size = 15 * 1024 * 1024;
if ($size > $max_size) {
    send_response('error', 'El archivo supera el límite de 15 MB.', $crane_id);
}

// Validar extensión
$allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'txt', 'zip', 'rar'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_exts)) {
    send_response('error', "Extensión de archivo no permitida. Formatos válidos: " . implode(', ', $allowed_exts), $crane_id);
}

// Crear nombre único para evitar colisiones y asegurar la ruta
$unique_filename = uniqid('doc_', true) . '.' . $ext;
$upload_dir = 'uploads/documents/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$dest_path = $upload_dir . $unique_filename;

if (move_uploaded_file($tmp_name, $dest_path)) {
    try {
        $stmt_ins = $pdo->prepare("INSERT INTO crane_documents (crane_id, document_name, file_path) VALUES (?, ?, ?)");
        $stmt_ins->execute([$crane_id, $document_name, $dest_path]);
        $doc_id = $pdo->lastInsertId();
        
        $uploaded_at = date('d/m/Y h:i A');
        
        send_response('success', "Documento '$document_name' subido correctamente.", $crane_id, [
            'document' => [
                'id' => $doc_id,
                'document_name' => $document_name,
                'file_path' => $dest_path,
                'uploaded_at' => $uploaded_at
            ]
        ]);
    } catch (PDOException $e) {
        // En caso de error, borrar el archivo físico subido
        if (file_exists($dest_path)) {
            unlink($dest_path);
        }
        send_response('error', "Error en base de datos: " . $e->getMessage(), $crane_id);
    }
} else {
    send_response('error', "No se pudo mover el archivo al directorio de destino.", $crane_id);
}
