<?php
// ============================================================
// src/views/classes/eliminar_proveedor.php
// Endpoint POST — Eliminar proveedor.
//
// Verifica que el proveedor no tenga armazones o garantías
// vinculadas antes de eliminar para no romper FK.
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores');
    exit;
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    $msg = urlencode('ID de proveedor inválido.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&error=' . $msg);
    exit;
}

try {
    // Verificar armazones vinculados
    $chkArm = $pdo->prepare('SELECT COUNT(*) FROM armazones WHERE proveedor_id = :id');
    $chkArm->execute(['id' => $id]);
    if ((int)$chkArm->fetchColumn() > 0) {
        $msg = urlencode('No se puede eliminar: el proveedor tiene armazones vinculados. Reasígnalos primero.');
        header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&error=' . $msg);
        exit;
    }

    // Verificar garantías vinculadas
    $chkGrt = $pdo->prepare('SELECT COUNT(*) FROM folios_garantia WHERE proveedor_id = :id');
    $chkGrt->execute(['id' => $id]);
    if ((int)$chkGrt->fetchColumn() > 0) {
        $msg = urlencode('No se puede eliminar: el proveedor tiene folios de garantía vinculados.');
        header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&error=' . $msg);
        exit;
    }

    // Eliminar contactos del proveedor primero (FK)
    $pdo->prepare('DELETE FROM contactos_proveedor WHERE proveedor_id = :id')->execute(['id' => $id]);

    // Eliminar el proveedor
    $stmt = $pdo->prepare('DELETE FROM proveedores WHERE id = :id');
    $stmt->execute(['id' => $id]);

    $msg = urlencode('Proveedor eliminado correctamente.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&ok=' . $msg);
    exit;

} catch (PDOException $e) {
    $msg = urlencode('Error al eliminar el proveedor.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&error=' . $msg);
    exit;
}