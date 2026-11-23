<?php
/**
 * eliminar_garantia.php
 * =====================
 * Endpoint POST — Eliminar un folio de garantía de la base de datos.
 *
 * UBICACIÓN: /src/views/eliminar_garantia.php
 *
 * ¿POR QUÉ EXISTE ESTE ARCHIVO?
 * Sigue el patrón de toggle_usuario.php: un endpoint separado que recibe
 * peticiones AJAX (fetch) desde el frontend y responde JSON.
 * Separar la lógica de eliminación en su propio archivo mantiene el código
 * organizado y permite reutilizarlo si se necesita en otra parte.
 *
 * ¿CÓMO FUNCIONA?
 * 1. Verifica que el usuario esté autenticado (sesión activa).
 * 2. Verifica que el método sea POST.
 * 3. Recibe el "id" del folio a eliminar.
 * 4. Primero elimina los movimientos_inventario asociados (FK constraint).
 * 5. Luego elimina el folio de folios_garantia.
 * 6. Responde JSON con el resultado.
 *
 * CAMPO ESPERADO:
 * - id (obligatorio: ID numérico del folio a eliminar)
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// ── 1. Verificar autenticación ──────────────────────────────────────────────
// Protección contra accesos no autenticados.
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado.']);
    exit;
}

// ── 2. Solo aceptar método POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

// ── 3. Leer y validar el ID ─────────────────────────────────────────────────
// Se convierte a entero para evitar inyecciones y se valida que sea positivo.
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'ID de folio inválido.']);
    exit;
}

try {
    // ── 4. Eliminar movimientos asociados primero ───────────────────────────
    // La tabla movimientos_inventario tiene una FK hacia folios_garantia.
    // Si existen movimientos vinculados a este folio, hay que borrarlos
    // primero para no violar la restricción de clave foránea.
    $stmtMov = $pdo->prepare("DELETE FROM movimientos_inventario WHERE folio_garantia_id = :id");
    $stmtMov->execute(['id' => $id]);

    // ── 5. Eliminar el folio de garantía ────────────────────────────────────
    // Se ejecuta el DELETE con prepared statement.
    $stmt = $pdo->prepare("DELETE FROM folios_garantia WHERE id = :id");
    $stmt->execute(['id' => $id]);

    // ── 6. Verificar si realmente se eliminó ────────────────────────────────
    // rowCount() devuelve el número de filas afectadas. Si es 0, el folio
    // no existía en la BD.
    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true, 'msg' => 'Folio eliminado correctamente.']);
    }
    else {
        echo json_encode(['ok' => false, 'msg' => 'No se encontró el folio a eliminar.']);
    }

}
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al eliminar: ' . $e->getMessage()]);
}
