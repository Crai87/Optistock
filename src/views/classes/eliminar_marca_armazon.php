<?php
// ============================================================
// src/views/classes/eliminar_marca_armazon.php
// Endpoint POST — Eliminar marca de armazón.
//
// Bloquea la eliminación si hay armazones usando esa marca.
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas');
    exit;
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    $msg = urlencode('ID de marca inválido.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&error=' . $msg);
    exit;
}

try {
    // Verificar armazones vinculados
    $chk = $pdo->prepare('SELECT COUNT(*) FROM armazones WHERE marca_id = :id');
    $chk->execute(['id' => $id]);
    $total = (int)$chk->fetchColumn();

    if ($total > 0) {
        $msg = urlencode("No se puede eliminar: la marca tiene $total armazón/es vinculados. Reasígnalos primero.");
        header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&error=' . $msg);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM marcas_armazon WHERE id = :id');
    $stmt->execute(['id' => $id]);

    $msg = urlencode('Marca eliminada correctamente.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&ok=' . $msg);
    exit;

} catch (PDOException $e) {
    $msg = urlencode('Error al eliminar la marca.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&error=' . $msg);
    exit;
}