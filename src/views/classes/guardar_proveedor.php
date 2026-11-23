<?php
// ============================================================
// src/views/classes/guardar_proveedor.php
// Endpoint POST — Agregar o editar proveedor.
// Si viene campo "id" > 0 → UPDATE, si no → INSERT.
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

$id       = intval($_POST['id']       ?? 0);
$nombre   = trim($_POST['nombre']     ?? '');
$telefono = trim($_POST['telefono']   ?? '') ?: null;
$email    = trim($_POST['email']      ?? '') ?: null;

if (!$nombre) {
    $msg = urlencode('El nombre del proveedor es obligatorio.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&error=' . $msg);
    exit;
}

try {
    if ($id > 0) {
        // ── EDITAR ───────────────────────────────────────────
        $stmt = $pdo->prepare('
            UPDATE proveedores SET nombre = :nombre, telefono = :telefono, email = :email
            WHERE id = :id
        ');
        $stmt->execute(['nombre' => $nombre, 'telefono' => $telefono, 'email' => $email, 'id' => $id]);
        $msg = urlencode('Proveedor actualizado correctamente.');
    } else {
        // ── AGREGAR — verificar nombre duplicado ─────────────
        $chk = $pdo->prepare('SELECT id FROM proveedores WHERE nombre = :nombre');
        $chk->execute(['nombre' => $nombre]);
        if ($chk->fetch()) {
            $msg = urlencode('Ya existe un proveedor con ese nombre.');
            header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&error=' . $msg);
            exit;
        }

        $stmt = $pdo->prepare('
            INSERT INTO proveedores (nombre, telefono, email) VALUES (:nombre, :telefono, :email)
        ');
        $stmt->execute(['nombre' => $nombre, 'telefono' => $telefono, 'email' => $email]);
        $msg = urlencode('Proveedor agregado correctamente.');
    }

    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&ok=' . $msg);
    exit;

} catch (PDOException $e) {
    $msg = urlencode('Error al guardar el proveedor.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=proveedores&error=' . $msg);
    exit;
}