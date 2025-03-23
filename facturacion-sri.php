<?php
/**
 * Estructura de directorios:
 * 
 * /facturacion-sri/
 * ├── config/
 * │   └── config.php
 * ├── src/
 * │   ├── Comprobantes/
 * │   │   ├── Factura.php
 * │   │   └── NotaCredito.php
 * │   ├── Firmador/
 * │   │   └── FirmadorXML.php
 * │   ├── SRI/
 * │   │   ├── ClienteSRI.php
 * │   │   └── Autorizacion.php
 * │   └── Util/
 * │       ├── XML.php
 * │       └── RIDE.php
 * ├── public/
 * │   ├── index.php
 * │   ├── css/
 * │   └── js/
 * ├── templates/
 * │   ├── factura.php
 * │   └── nota-credito.php
 * ├── certificados/
 * └── comprobantes/
 *     ├── generados/
 *     ├── firmados/
 *     ├── enviados/
 *     └── autorizados/
 */

// Estructura básica para autoload
spl_autoload_register(function ($class) {
    $prefix = '';
    $base_dir = __DIR__ . '/src/';
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
