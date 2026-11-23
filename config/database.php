<?php
// ============================================================
// config/database.php
// Conexión PDO única para toda la aplicación.
//
// PRODUCCIÓN: cambia las constantes DB_USER y DB_PASS antes
// de subir. Nunca uses root con contraseña vacía en producción.
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'optistock_bd');
define('DB_USER',    'root');          // CAMBIAR en producción
define('DB_PASS',    '');              // CAMBIAR en producción
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements reales
        ]
    );
} catch (PDOException $e) {
    // En producción este mensaje no se muestra al usuario porque
    // display_errors está apagado en app.php.
    // El error queda registrado en logs/app_errors.log.
    http_response_code(500);
    exit('Error de conexión a la base de datos.');
}