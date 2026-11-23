<?php
// ============================================================
// config/app.php
// Configuración general de la aplicación.
//
// PRODUCCIÓN: cambia ENVIRONMENT a 'production' antes de subir.
// Los errores dejarán de mostrarse en pantalla y se escribirán
// en logs/ en lugar de exponerse al usuario.
// ============================================================

define('APP_NAME',    'OptiStock — Inventario Optometría');
define('BASE_URL',    'http://localhost/optometria-inventario-main');
define('TIMEZONE',    'America/Monterrey');
define('ENVIRONMENT', 'production'); // Cambiar a 'production' al subir al servidor

date_default_timezone_set(TIMEZONE);

if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);

    // En producción los errores van a un archivo de log
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    ini_set('log_errors',      1);
    ini_set('error_log',       $logDir . '/app_errors.log');
}