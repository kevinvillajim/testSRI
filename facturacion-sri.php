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
 * │       ├── RIDE.php
 * │       ├── Validator.php
 * │       └── Logger.php
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
require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = '';
    $base_dir = __DIR__ . '/src/';
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Configuración de versiones de comprobantes
define('VERSION_FACTURA', '2.1.0');
define('VERSION_NOTA_CREDITO', '1.1.0');

// Descargar esquemas XSD si no existen
$xsdDir = __DIR__ . '/xsd';
if (!is_dir($xsdDir) || count(glob($xsdDir . '/*.xsd')) < 2) {
    Util\XML::descargarEsquemasXSD();
}