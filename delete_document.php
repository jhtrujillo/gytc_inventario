<?php
require_once 'config.php';
require_login();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: dashboard.php?error=" . urlencode("ID de documento no válido."));
    exit;
}

try {
    // Buscar el documento y su ruta de archivo
    $stmt = $pdo->prepare("SELECT * FROM crane_documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        header("Location: dashboard.php?error=" . urlencode("El documento no existe."));
        exit;
    }

    $crane_id = $doc['crane_id'];

    // Eliminar archivo físico si existe
    if (file_exists($doc['file_path'])) {
        unlink($doc['file_path']);
    }

    // Eliminar registro de base de datos
    $stmt_del = $pdo->prepare("DELETE FROM crane_documents WHERE id = ?");
    $stmt_del->execute([$id]);

    header("Location: crane_view.php?crane_id=$crane_id&success=" . urlencode("Documento eliminado correctamente."));
    exit;

} catch (PDOException $e) {
    header("Location: dashboard.php?error=" . urlencode("Error al eliminar el documento: " . $e->getMessage()));
    exit;
}
