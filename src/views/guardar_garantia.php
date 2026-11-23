<?php
/**
 * guardar_garantia.php
 * =====================
 * Endpoint POST — Crear o editar un folio de garantía.
 *
 * UBICACIÓN: /src/views/guardar_garantia.php
 *
 * ¿POR QUÉ ESTÁ AQUÍ?
 * Sigue el mismo patrón que guardar_usuario.php: se ubica en /src/views/
 * porque index.php carga las vistas desde esa carpeta. Al registrarlo en
 * $permisos_requeridos de index.php, el sistema se encarga de verificar sesión
 * y permisos antes de llegar a este archivo.
 *
 * ¿CÓMO FUNCIONA?
 * 1. Recibe datos vía POST (FormData desde fetch() en el frontend).
 * 2. Si el campo "id" está presente y es > 0, se trata de una EDICIÓN (UPDATE).
 * 3. Si "id" no viene o es 0, se trata de una CREACIÓN (INSERT) con folio
 *    auto-generado.
 * 4. Responde siempre JSON para que el frontend actualice la UI sin recargar.
 *
 * CAMPOS ESPERADOS:
 * - id              (opcional: si viene, es edición)
 * - armazon_id      (obligatorio: ID del armazón seleccionado)
 * - motivo          (obligatorio: texto con el defecto reportado)
 * - proveedor_id    (opcional: ID del proveedor asignado)
 * - fecha_apertura  (obligatorio: fecha en formato YYYY-MM-DD)
 * - fecha_resolucion(opcional: fecha de resolución)
 * - resolucion      (opcional: notas de resolución)
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// ── 1. Verificar autenticación ──────────────────────────────────────────────
// Se verifica que exista una sesión activa. Si no, se rechaza con 401.
// Esto es una segunda capa de seguridad; index.php ya verifica sesión,
// pero este check protege contra accesos directos al archivo.
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado.']);
    exit;
}

// ── 2. Solo aceptar método POST ─────────────────────────────────────────────
// Rechazar GET, PUT, DELETE, etc. Solo POST es válido para esta operación.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

// ── 3. Leer y sanear campos del formulario ──────────────────────────────────
// Se usa trim() para eliminar espacios en blanco al inicio y al final.
// Se usa intval() para asegurar que los IDs sean enteros válidos.
$id = intval($_POST['id'] ?? 0);
$armazon_id = intval($_POST['armazon_id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');
$proveedor_id = intval($_POST['proveedor_id'] ?? 0);
$fecha_apertura = trim($_POST['fecha_apertura'] ?? '');
$fecha_resolucion = trim($_POST['fecha_resolucion'] ?? '');
$resolucion = trim($_POST['resolucion'] ?? '');

// ── 4. Validar campos obligatorios ──────────────────────────────────────────
// armazon_id, motivo y fecha_apertura son requeridos para crear o editar.
if ($armazon_id <= 0 || !$motivo || !$fecha_apertura) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Completa los campos obligatorios (armazón, motivo, fecha apertura).']);
    exit;
}

try {
    // ── 5A. EDICIÓN — Si viene un ID válido, actualizar el registro ─────────
    // Se usa UPDATE con prepared statements para prevenir SQL injection.
    // Se actualizan todos los campos editables del folio.
    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE folios_garantia SET
                armazon_id       = :armazon_id,
                motivo           = :motivo,
                proveedor_id     = :proveedor_id,
                fecha_apertura   = :fecha_apertura,
                fecha_resolucion = :fecha_resolucion,
                resolucion       = :resolucion
            WHERE id = :id
        ");
        $stmt->execute([
            'armazon_id' => $armazon_id,
            'motivo' => $motivo,
            'proveedor_id' => $proveedor_id > 0 ? $proveedor_id : null,
            'fecha_apertura' => $fecha_apertura,
            'fecha_resolucion' => $fecha_resolucion ?: null,
            'resolucion' => $resolucion ?: null,
            'id' => $id,
        ]);

        echo json_encode(['ok' => true, 'msg' => 'Folio actualizado correctamente.', 'id' => $id]);

    }
    else {
        // ── 5B. CREACIÓN — Generar folio automático e insertar ───────────────
        // El folio sigue el formato GRT-YYYY-NNNNN.
        // Se consulta el número máximo existente para el año actual y se
        // incrementa en 1. Si no hay folios previos, se empieza en 00001.
        $anio = date('Y');
        $stmtMax = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)) AS max_num
            FROM folios_garantia
            WHERE folio LIKE :prefijo
        ");
        $stmtMax->execute(['prefijo' => "GRT-{$anio}-%"]);
        $row = $stmtMax->fetch();
        $siguiente = ($row && $row['max_num']) ? intval($row['max_num']) + 1 : 1;
        $folio = "GRT-{$anio}-" . str_pad($siguiente, 5, '0', STR_PAD_LEFT);

        // Insertar el nuevo registro en la tabla folios_garantia.
        // tipo se fija como 'armazon' porque esta página solo gestiona armazones.
        // creado_por guarda el ID del usuario logueado desde la sesión.
        $stmt = $pdo->prepare("
            INSERT INTO folios_garantia
                (folio, tipo, armazon_id, motivo, estado, proveedor_id,
                 fecha_apertura, fecha_resolucion, resolucion, creado_por)
            VALUES
                (:folio, 'armazon', :armazon_id, :motivo, 'abierto', :proveedor_id,
                 :fecha_apertura, :fecha_resolucion, :resolucion, :creado_por)
        ");
        $stmt->execute([
            'folio' => $folio,
            'armazon_id' => $armazon_id,
            'motivo' => $motivo,
            'proveedor_id' => $proveedor_id > 0 ? $proveedor_id : null,
            'fecha_apertura' => $fecha_apertura,
            'fecha_resolucion' => $fecha_resolucion ?: null,
            'resolucion' => $resolucion ?: null,
            'creado_por' => $_SESSION['usuario_id'],
        ]);

        $nuevoId = (int)$pdo->lastInsertId();

        echo json_encode([
            'ok' => true,
            'msg' => 'Folio creado correctamente.',
            'id' => $nuevoId,
            'folio' => $folio,
        ]);
    }

}
catch (PDOException $e) {
    // ── 6. Manejo de errores de BD ──────────────────────────────────────────
    // Si ocurre un error de base de datos, se responde con 500 y el mensaje
    // del error para facilitar el debugging en desarrollo.
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}
