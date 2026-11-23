<?php
// ============================================================
// src/views/classes/eliminar_armazon.php
// Endpoint POST — eliminar armazón.
//
// Antes de eliminar verifica que el armazón no tenga folios
// de garantía activos. Si los tiene, bloquea la eliminación
// para no romper las FK.
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /optometria-inventario/public/index.php?view=inventario');
    exit;
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: /optometria-inventario/public/index.php?view=inventario&error=' . urlencode('ID inválido.'));
    exit;
}

try {
    // Verificar garantías activas (abierto o enviado_proveedor)
    $chk = $pdo->prepare("
        SELECT COUNT(*) FROM folios_garantia
        WHERE armazon_id = :id AND estado IN ('abierto','enviado_proveedor')
    ");
    $chk->execute(['id' => $id]);
    if ((int)$chk->fetchColumn() > 0) {
        $msg = urlencode('No se puede eliminar: el armazón tiene garantías activas.');
        header('Location: /optometria-inventario/public/index.php?view=inventario&error=' . $msg);
        exit;
    }

    // Primero eliminar movimientos_inventario asociados (FK)
    $pdo->prepare('DELETE FROM movimientos_inventario WHERE tipo_item = "armazon" AND item_id = :id')
        ->execute(['id' => $id]);

    // Luego los folios de garantía cerrados
    $pdo->prepare('DELETE FROM folios_garantia WHERE armazon_id = :id')
        ->execute(['id' => $id]);

    // Finalmente el armazón
    $stmt = $pdo->prepare('DELETE FROM armazones WHERE id = :id');
    $stmt->execute(['id' => $id]);

    header('Location: /optometria-inventario/public/index.php?view=inventario&ok=1');
    exit;

} catch (PDOException $e) {
    $msg = urlencode('Error al eliminar el armazón.');
    header('Location: /optometria-inventario/public/index.php?view=inventario&error=' . $msg);
    exit;
}