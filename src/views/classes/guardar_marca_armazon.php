<?php
// ============================================================
// src/views/classes/guardar_marca_armazon.php
// Endpoint POST — Agregar o editar marca de armazón.
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

$id     = intval($_POST['id']     ?? 0);
$nombre = trim($_POST['nombre']   ?? '');

if (!$nombre) {
    $msg = urlencode('El nombre de la marca es obligatorio.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&error=' . $msg);
    exit;
}

try {
    if ($id > 0) {
        // ── EDITAR ───────────────────────────────────────────
        // Verificar duplicado ignorando el propio registro
        $chk = $pdo->prepare('SELECT id FROM marcas_armazon WHERE nombre = :nombre AND id != :id');
        $chk->execute(['nombre' => $nombre, 'id' => $id]);
        if ($chk->fetch()) {
            $msg = urlencode('Ya existe una marca con ese nombre.');
            header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&error=' . $msg);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE marcas_armazon SET nombre = :nombre WHERE id = :id');
        $stmt->execute(['nombre' => $nombre, 'id' => $id]);
        $msg = urlencode('Marca actualizada correctamente.');

    } else {
        // ── AGREGAR ──────────────────────────────────────────
        $chk = $pdo->prepare('SELECT id FROM marcas_armazon WHERE nombre = :nombre');
        $chk->execute(['nombre' => $nombre]);
        if ($chk->fetch()) {
            $msg = urlencode('Ya existe una marca con ese nombre.');
            header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&error=' . $msg);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO marcas_armazon (nombre) VALUES (:nombre)');
        $stmt->execute(['nombre' => $nombre]);
        $msg = urlencode('Marca agregada correctamente.');
    }

    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&ok=' . $msg);
    exit;

} catch (PDOException $e) {
    $msg = urlencode('Error al guardar la marca.');
    header('Location: /optometria-inventario/public/index.php?view=catalogs&tab=marcas&error=' . $msg);
    exit;
}