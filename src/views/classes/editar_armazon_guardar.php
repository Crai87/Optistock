<?php
// ============================================================
// src/views/classes/editar_armazon_guardar.php
// Endpoint POST — actualizar armazón existente con PDO.
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

$id          = intval($_POST['id'] ?? 0);
$codigo      = trim($_POST['codigo']      ?? '');
$area        = trim($_POST['area']        ?? '');
$marca_id    = intval($_POST['marca']     ?? 0);
$material    = trim($_POST['material']    ?? '');
$modelo      = trim($_POST['modelo']      ?? '');
$desc        = trim($_POST['descripcion'] ?? '') ?: null;
// Stock no se edita desde aquí — se conserva el valor actual en BD
// Para agregar unidades se usa agregar_armazon.php
$stock = null; // se resolverá abajo con el valor real
$costo       = !empty($_POST['costo']) ? floatval($_POST['costo']) : null;
$estado      = $_POST['estado'] ?? 'disponible';
$proveedor_id  = !empty($_POST['proveedor']) ? intval($_POST['proveedor']) : null;
$donado_a      = trim($_POST['donado_a']      ?? '') ?: null;
$fecha_donacion = !empty($_POST['fecha_donacion']) ? $_POST['fecha_donacion'] : null;

// tiene_garantia se deriva del estado
$garantia = ($estado === 'en_garantia') ? 1 : 0;

// Si el estado ya no es 'donado', limpiar datos de donación
if ($estado !== 'donado') {
    $donado_a       = null;
    $fecha_donacion = null;
}

$estados_validos = ['disponible', 'en_garantia', 'dañado', 'baja', 'donado', 'inventario_muerto'];

// Obtener stock actual desde BD para no modificarlo
$stmtStock = $pdo->prepare('SELECT cantidad_stock FROM armazones WHERE id = :id');
$stmtStock->execute(['id' => $id]);
$rowStock = $stmtStock->fetch();
$stock = $rowStock ? (int)$rowStock['cantidad_stock'] : 0;

$errores = [];
if ($id <= 0)       $errores[] = 'ID inválido.';
if (!$codigo)       $errores[] = 'El código es obligatorio.';
if (!$area)         $errores[] = 'El área es obligatoria.';
if ($marca_id <= 0) $errores[] = 'Selecciona una marca.';
if (!$material)     $errores[] = 'El material es obligatorio.';
if (!$modelo)       $errores[] = 'El modelo es obligatorio.';
if (!in_array($estado, $estados_validos)) $errores[] = 'Estado inválido.';

if (!empty($errores)) {
    $msg = urlencode(implode(' ', $errores));
    header('Location: /optometria-inventario/public/index.php?view=editar_armazon&id=' . $id . '&error=' . $msg);
    exit;
}

try {
    // Verificar código duplicado (ignorando el mismo registro)
    $chk = $pdo->prepare('SELECT id FROM armazones WHERE codigo = :codigo AND id != :id');
    $chk->execute(['codigo' => $codigo, 'id' => $id]);
    if ($chk->fetch()) {
        $msg = urlencode('El código "' . $codigo . '" ya está en uso.');
        header('Location: /optometria-inventario/public/index.php?view=editar_armazon&id=' . $id . '&error=' . $msg);
        exit;
    }

    // Detectar si las columnas de donación ya existen en la BD
    // (pueden no existir si no se ejecutó migracion_donacion.sql todavía)
    $tieneCols = false;
    try {
        $pdo->query('SELECT donado_a FROM armazones LIMIT 1');
        $tieneCols = true;
    } catch (PDOException $e2) {
        $tieneCols = false;
    }

    if ($tieneCols) {
        $sql = '
            UPDATE armazones SET
                codigo          = :codigo,
                descripcion     = :descripcion,
                area            = :area,
                marca_id        = :marca_id,
                material        = :material,
                modelo          = :modelo,
                proveedor_id    = :proveedor_id,
                tiene_garantia  = :tiene_garantia,
                cantidad_stock  = :cantidad_stock,
                costo           = :costo,
                estado          = :estado,
                donado_a        = :donado_a,
                fecha_donacion  = :fecha_donacion
            WHERE id = :id
        ';
        $params = [
            'donado_a'       => $donado_a,
            'fecha_donacion' => $fecha_donacion,
        ];
    } else {
        // Columnas de donación aún no existen — UPDATE sin ellas
        $sql = '
            UPDATE armazones SET
                codigo         = :codigo,
                descripcion    = :descripcion,
                area           = :area,
                marca_id       = :marca_id,
                material       = :material,
                modelo         = :modelo,
                proveedor_id   = :proveedor_id,
                tiene_garantia = :tiene_garantia,
                cantidad_stock = :cantidad_stock,
                costo          = :costo,
                estado         = :estado
            WHERE id = :id
        ';
        $params = [];
    }

    $params = array_merge($params, [
        'codigo'         => $codigo,
        'descripcion'    => $desc,
        'area'           => $area,
        'marca_id'       => $marca_id,
        'material'       => $material,
        'modelo'         => $modelo,
        'proveedor_id'   => $proveedor_id,
        'tiene_garantia' => $garantia,
        'cantidad_stock' => $stock,
        'costo'          => $costo,
        'estado'         => $estado,
        'id'             => $id,
    ]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // ── Folio de garantía automático al editar ──────────────────
    // Se crea folio cuando el estado cambia a en_garantia.
    if ($estado === 'en_garantia') {
        $chkFolio = $pdo->prepare("
            SELECT COUNT(*) FROM folios_garantia
            WHERE armazon_id = :aid AND estado IN ('abierto','enviado_proveedor')
        ");
        $chkFolio->execute([':aid' => $id]);
        $folioExiste = (int)$chkFolio->fetchColumn();

        if ($folioExiste === 0) {
            $anio    = date('Y');
            $stmtMax = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)) AS max_num
                FROM folios_garantia WHERE folio LIKE :pref
            ");
            $stmtMax->execute([':pref' => "GRT-{$anio}-%"]);
            $row     = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $numBase = ($row && $row['max_num']) ? intval($row['max_num']) + 1 : 1;
            $folio   = "GRT-{$anio}-" . str_pad($numBase, 5, '0', STR_PAD_LEFT);

            // Recuperar código del armazón para el motivo
            $stmtCod = $pdo->prepare("SELECT codigo FROM armazones WHERE id = :id");
            $stmtCod->execute([':id' => $id]);
            $codArm  = $stmtCod->fetchColumn() ?: "ID $id";

            try {
                $pdo->prepare("
                    INSERT INTO folios_garantia
                        (folio, tipo, armazon_id, motivo, estado, proveedor_id, fecha_apertura, creado_por)
                    VALUES
                        (:folio, 'armazon', :armazon_id, :motivo, 'abierto', :proveedor_id, CURDATE(), :creado_por)
                ")->execute([
                    ':folio'        => $folio,
                    ':armazon_id'   => $id,
                    ':motivo'       => 'Garantía activada al editar el armazón ' . $codArm,
                    ':proveedor_id' => $proveedor_id,
                    ':creado_por'   => $_SESSION['usuario_id'],
                ]);
            } catch (PDOException $eFolio) {
                // No bloquea el guardado del armazón
            }
        }
    }

    header('Location: /optometria-inventario/public/index.php?view=inventario&ok=1');
    exit;

} catch (PDOException $e) {
    $msg = urlencode('Error al actualizar: ' . $e->getMessage());
    header('Location: /optometria-inventario/public/index.php?view=editar_armazon&id=' . $id . '&error=' . $msg);
    exit;
}