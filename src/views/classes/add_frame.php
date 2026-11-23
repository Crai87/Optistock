<?php
// ============================================================
// src/views/classes/add_frame.php
//
// LÓGICA DE CÓDIGOS ÚNICOS:
//   - Si cantidad = 1: inserta con el código tal cual.
//   - Si cantidad > 1: añade sufijos -A, -B, -C… a cada unidad.
//   - Si ya existen registros con ese código base en la BD
//     (lotes anteriores), detecta el último sufijo usado y
//     CONTINÚA desde el siguiente.
//     Ejemplo: ya existen ARM-001-A y ARM-001-B →
//     nuevo lote de 3 crea ARM-001-C, ARM-001-D, ARM-001-E.
// ============================================================

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

// ── Helpers ──────────────────────────────────────────────────

/**
 * Número → sufijo alfabético estilo Excel:
 * 1→A, 2→B … 26→Z, 27→AA, 28→AB …
 */
function sufijo(int $n): string
{
    $resultado = '';
    while ($n > 0) {
        $n--;
        $resultado = chr(65 + ($n % 26)) . $resultado;
        $n         = intdiv($n, 26);
    }
    return $resultado;
}

/**
 * Sufijo alfabético → número:
 * A→1, B→2 … Z→26, AA→27 …
 */
function sufijoANumero(string $s): int
{
    $s   = strtoupper($s);
    $len = strlen($s);
    $n   = 0;
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($s[$i]) - 64);
    }
    return $n;
}

/**
 * Consulta la BD y devuelve el número del último sufijo
 * ya usado para ese código base (0 si ninguno aún).
 */
function ultimoSufijoUsado(PDO $pdo, string $codigoBase): int
{
    $stmt = $pdo->prepare(
        "SELECT codigo FROM armazones
         WHERE codigo LIKE :patron
           AND codigo REGEXP :regex"
    );
    $stmt->execute([
        ':patron' => $codigoBase . '-%',
        ':regex'  => '^' . preg_quote($codigoBase, '/') . '-[A-Z]+$',
    ]);
    $codigos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($codigos)) return 0;

    $maxN = 0;
    foreach ($codigos as $cod) {
        $partes = explode('-', $cod);
        $suf    = end($partes);
        if (ctype_alpha($suf)) {
            $maxN = max($maxN, sufijoANumero($suf));
        }
    }
    return $maxN;
}

// ── Recoger y validar datos ───────────────────────────────────
$codigoBase   = trim($_POST['codigo']      ?? '');
$descripcion  = trim($_POST['descripcion'] ?? '');
$area         = trim($_POST['area']        ?? '');
$marca_id     = intval($_POST['marca']     ?? 0) ?: null;
$material     = trim($_POST['material']    ?? '');
$modelo       = trim($_POST['modelo']      ?? '');
$proveedor_id = intval($_POST['proveedor'] ?? 0) ?: null;
$cantidad     = max(1, intval($_POST['stock'] ?? 1));
$costo        = ($_POST['costo'] ?? '') !== '' ? floatval($_POST['costo']) : null;
$estado       = trim($_POST['estado'] ?? 'disponible');
$usuario_id   = $_SESSION['usuario_id'];

$estados_validos = ['disponible', 'en_garantia', 'dañado', 'baja', 'donado'];
if (!in_array($estado, $estados_validos)) $estado = 'disponible';

if ($codigoBase === '' || $area === '' || !$marca_id || $material === '' || $modelo === '') {
    $err = urlencode('Faltan campos obligatorios.');
    header("Location: /optometria-inventario/public/index.php?view=agregar_armazon&error=$err");
    exit;
}

// ── Detectar desde qué sufijo continuar ──────────────────────
$desde = ($cantidad > 1) ? ultimoSufijoUsado($pdo, $codigoBase) : 0;

// ── Preparar INSERT de armazón ───────────────────────────────
$sql = "INSERT INTO armazones
            (codigo, descripcion, area, marca_id, material, modelo,
             proveedor_id, tiene_garantia, cantidad_stock, costo, estado, created_by)
        VALUES
            (:codigo, :descripcion, :area, :marca_id, :material, :modelo,
             :proveedor_id, :tiene_garantia, 1, :costo, :estado, :created_by)";

$stmt    = $pdo->prepare($sql);
$errores = [];
// Guardar IDs insertados para crear folios después
$ids_insertados = [];

// Crear folio automáticamente si estado = en_garantia
$stmtFolio = null;
if ($estado === 'en_garantia') {
    $anio    = date('Y');
    $stmtMax = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)) AS max_num
        FROM folios_garantia WHERE folio LIKE :pref
    ");
    $stmtMax->execute(['pref' => "GRT-{$anio}-%"]);
    $row     = $stmtMax->fetch(PDO::FETCH_ASSOC);
    $numBase = ($row && $row['max_num']) ? intval($row['max_num']) : 0;

    $stmtFolio = $pdo->prepare("
        INSERT INTO folios_garantia
            (folio, tipo, armazon_id, motivo, estado, proveedor_id, fecha_apertura, creado_por)
        VALUES
            (:folio, 'armazon', :armazon_id, :motivo, 'abierto', :proveedor_id, CURDATE(), :creado_por)
    ");
}

for ($i = 1; $i <= $cantidad; $i++) {
    $codigoFinal = ($cantidad === 1)
        ? $codigoBase
        : $codigoBase . '-' . sufijo($desde + $i);

    try {
        $stmt->execute([
            ':codigo'       => $codigoFinal,
            ':descripcion'  => $descripcion ?: null,
            ':area'         => $area,
            ':marca_id'     => $marca_id,
            ':material'     => $material,
            ':modelo'       => $modelo,
            ':proveedor_id' => $proveedor_id,
            ':tiene_garantia' => ($estado === 'en_garantia') ? 1 : 0,
            ':costo'        => $costo,
            ':estado'       => $estado,
            ':created_by'   => $usuario_id,
        ]);

        $nuevoId = (int)$pdo->lastInsertId();
        $ids_insertados[] = ['id' => $nuevoId, 'codigo' => $codigoFinal];

        // Crear folio de garantía inmediatamente después de cada inserción
        // usando el lastInsertId — así nunca se pierde el ID correcto
        if ($stmtFolio !== null) {
            $numBase++;
            $folio = "GRT-{$anio}-" . str_pad($numBase, 5, '0', STR_PAD_LEFT);
            try {
                $stmtFolio->execute([
                    ':folio'        => $folio,
                    ':armazon_id'   => $nuevoId,
                    ':motivo'       => 'Garantía registrada al dar de alta el armazón ' . $codigoFinal,
                    ':proveedor_id' => $proveedor_id,
                    ':creado_por'   => $usuario_id,
                ]);
            } catch (PDOException $eFolio) {
                // No bloquea el flujo — el armazón ya fue insertado
            }
        }

    } catch (PDOException $e) {
        $errores[] = ($e->getCode() === '23000')
            ? "Código '$codigoFinal' ya existe."
            : "Error en '$codigoFinal': " . $e->getMessage();
    }
}

$insertados = count($ids_insertados);

// ── Redirección con resultado ─────────────────────────────────
if ($insertados > 0 && empty($errores)) {
    header('Location: /optometria-inventario/public/index.php?view=inventario&ok=1');
} elseif ($insertados > 0) {
    $msg = urlencode("Insertados $insertados de $cantidad. Conflictos: " . implode(', ', $errores));
    header("Location: /optometria-inventario/public/index.php?view=inventario&ok=1&error=$msg");
} else {
    $msg = urlencode(implode(' | ', $errores));
    header("Location: /optometria-inventario/public/index.php?view=agregar_armazon&error=$msg");
}
exit;