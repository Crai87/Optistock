<?php
/**
 * toggle_usuario.php
 * Endpoint POST — activar o desactivar un usuario (campo activo).
 * Ubicación esperada: /src/views/toggle_usuario.php
 */


require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

$id     = (int) ($_POST['id']     ?? 0);
$activo = (int) ($_POST['activo'] ?? 0);  // 0 o 1

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
    exit;
}

// No permitir que el admin se desactive a sí mismo
if ($id === (int) $_SESSION['usuario_id']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No puedes desactivar tu propia cuenta.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE usuarios SET activo = :a WHERE id = :id");
$stmt->execute(['a' => ($activo ? 1 : 0), 'id' => $id]);

echo json_encode(['ok' => true, 'msg' => 'Estado actualizado.']);
