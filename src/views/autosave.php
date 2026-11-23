<?php

echo("Creando respaldo de datos.\nEsto podría durar un momento.");

require_once __DIR__ . '/../../config/database.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$svgWidth  = 1200;
$svgHeight = 2000;
$y = 20;

$returnToAdmin = isset($_GET['return']) && $_GET['return'] == '1';

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svgWidth . '" height="' . $svgHeight . '">';

foreach ($tables as $table) {
    $svg .= "<text x='10' y='$y' font-family='monospace' font-size='14' font-weight='bold'>Table: $table</text>";
    $y += 20;

    $stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll();

    if (count($rows) === 0) {
        $svg .= "<text x='20' y='$y' font-family='monospace' font-size='12'>-- Empty table --</text>";
        $y += 15;
        continue;
    }

    foreach ($rows as $row) {
        $line = htmlspecialchars(json_encode($row));
        $svg .= "<text x='20' y='$y' font-family='monospace' font-size='12'>$line</text>";
        $y += 15;

        if ($y > $svgHeight - 20) {
            $svgHeight += 1000;
        }
    }
    $y += 10;
}

$svg .= '</svg>';

// ── Ruta corregida: src/views/ → src/ → raíz → public/respaldos/ ──
$carpetaRespaldos = __DIR__ . '/../../public/respaldos/';

// Crear la carpeta si no existe
if (!is_dir($carpetaRespaldos)) {
    mkdir($carpetaRespaldos, 0755, true);
}

$filename = $carpetaRespaldos . 'respaldo_' . date('Y-m-d_H-i-s') . '.svg';
file_put_contents($filename, $svg);

// ── Guardar fecha del último respaldo en save.php (misma carpeta) ──
$lastBackupFile = __DIR__ . '/save.php';
$lastBackupDate = date('Y-m-d');

$phpContent  = "<?php\n";
$phpContent .= "\$lastBackup = '" . $lastBackupDate . "';\n?>";

file_put_contents($lastBackupFile, $phpContent);

if ($returnToAdmin) {
    header('Location: /optometria-inventario/public/index.php?view=admin&saved=1');
} else {
    header('Location: /optometria-inventario/public/index.php?view=dashboard');
}
exit;
?>