<?php
/**
 * estado_garantia.php
 * ====================
 * Endpoint POST — Cambiar el estado de un folio de garantía.
 *
 * UBICACIÓN: /src/views/estado_garantia.php
 *
 * ¿POR QUÉ EXISTE ESTE ARCHIVO?
 * Maneja las tres acciones de estado del panel lateral de detalle:
 *   - "Enviar a proveedor"    → cambia estado a 'enviado_proveedor'
 *   - "Marcar como resuelto"  → cambia estado a 'resuelto' + fecha_resolucion
 *   - "Marcar como rechazado" → cambia estado a 'rechazado' + fecha_resolucion
 *
 * ¿CÓMO FUNCIONA?
 * 1. Recibe "id" (ID del folio) y "estado" (nuevo estado) por POST.
 * 2. Valida que el estado sea uno de los 3 estados válidos.
 * 3. Si el estado es 'resuelto' o 'rechazado', automáticamente establece
 *    la fecha de resolución a la fecha actual (CURDATE()).
 * 4. Ejecuta el UPDATE y responde JSON.
 *
 * CAMPOS ESPERADOS:
 * - id     (obligatorio: ID numérico del folio)
 * - estado (obligatorio: 'enviado_proveedor', 'resuelto' o 'rechazado')
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// ── 1. Verificar autenticación ──────────────────────────────────────────────
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

// ── 3. Leer y validar campos ────────────────────────────────────────────────
$id = intval($_POST['id'] ?? 0);
$estado = trim($_POST['estado'] ?? '');

// Lista blanca de estados permitidos.
// Solo estos 3 valores son aceptados para evitar manipulación.
$estados_validos = ['enviado_proveedor', 'resuelto', 'rechazado'];

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'ID de folio inválido.']);
    exit;
}

if (!in_array($estado, $estados_validos)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Estado no válido. Debe ser: enviado_proveedor, resuelto o rechazado.']);
    exit;
}

try {
    // ── 4. Construir la consulta según el estado ────────────────────────────
    // Si el estado es 'resuelto' o 'rechazado', además de cambiar el estado
    // se establece la fecha_resolucion a hoy (CURDATE()). Esto es porque
    // al resolver o rechazar una garantía se cierra el caso y se debe
    // registrar cuándo ocurrió.
    // Si el estado es 'enviado_proveedor', solo se cambia el estado sin
    // tocar la fecha de resolución.
    if ($estado === 'resuelto' || $estado === 'rechazado') {
        $stmt = $pdo->prepare("
            UPDATE folios_garantia
            SET estado = :estado, fecha_resolucion = CURDATE()
            WHERE id = :id
        ");
    }
    else {
        $stmt = $pdo->prepare("
            UPDATE folios_garantia
            SET estado = :estado
            WHERE id = :id
        ");
    }

    $stmt->execute(['estado' => $estado, 'id' => $id]);

    // ── 5. Responder con éxito ──────────────────────────────────────────────
    // Se incluyen los datos del estado y la fecha para que el frontend
    // pueda actualizar la UI sin hacer otra petición.
    $etiquetas = [
        'enviado_proveedor' => 'Enviado a proveedor',
        'resuelto' => 'Resuelto',
        'rechazado' => 'Rechazado',
    ];

    echo json_encode([
        'ok' => true,
        'msg' => 'Estado cambiado a: ' . $etiquetas[$estado],
        'estado' => $estado,
        'fecha_resolucion' => ($estado === 'resuelto' || $estado === 'rechazado') ? date('Y-m-d') : null,
    ]);

}
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al cambiar estado: ' . $e->getMessage()]);
}
