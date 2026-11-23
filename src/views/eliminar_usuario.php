<?php
// ============================================================
// src/views/eliminar_usuario.php
// Endpoint POST — eliminar usuario de la BD.
//
// Restricciones:
//   - No se puede eliminar al propio admin logueado
//   - No se puede eliminar al usuario con rol admin (id=1)
//   - Responde JSON (mismo patrón que toggle_usuario.php)
// ============================================================

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

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
    exit;
}

// No puede eliminarse a sí mismo
if ($id === (int)$_SESSION['usuario_id']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No puedes eliminar tu propia cuenta.']);
    exit;
}

// No eliminar cuentas con rol admin
$chk = $pdo->prepare('SELECT rol_id FROM usuarios WHERE id = :id');
$chk->execute(['id' => $id]);
$u = $chk->fetch();

if (!$u) {
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado.']);
    exit;
}

if ((int)$u['rol_id'] === 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar una cuenta de administrador.']);
    exit;
}

try {
    // Eliminar permisos del usuario primero (FK)
    $pdo->prepare('DELETE FROM usuario_permisos WHERE usuario_id = :id')
        ->execute(['id' => $id]);

    // Eliminar el usuario
    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);

    echo json_encode(['ok' => true, 'msg' => 'Usuario eliminado correctamente.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al eliminar: ' . $e->getMessage()]);
}