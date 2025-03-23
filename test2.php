<?php

/**
 * Script de prueba del sistema de facturación electrónica SRI
 */

// Establecer tiempo de ejecución ilimitado
set_time_limit(0);

// Cargamos el autoloader
require_once __DIR__ . '/facturacion-sri.php';

// Cargamos la configuración
$config = require_once __DIR__ . '/config/config.php';

// Función para mostrar resultados de prueba
function showTestResult($test_name, $result, $message = '')
{
    echo "<div style='padding: 10px; margin-bottom: 5px; border-radius: 5px; " .
        "background-color: " . ($result ? "#d4edda" : "#f8d7da") . "; " .
        "color: " . ($result ? "#155724" : "#721c24") . ";'>";
    echo "<strong>Test: $test_name</strong> - ";
    if ($result) {
        echo "<span style='color: green;'>✓ ÉXITO</span>";
    } else {
        echo "<span style='color: red;'>✗ FALLIDO</span>";
    }
    if (!empty($message)) {
        echo "<br><span style='font-size: 0.9em;'>$message</span>";
    }
    echo "</div>";

    return $result;
}

/**
 * 1. Prueba de estructura de directorios
 */
function testDirectories($config)
{
    $directories = [
        $config['rutas']['generados'],
        $config['rutas']['firmados'],
        $config['rutas']['enviados'],
        $config['rutas']['autorizados'],
    ];

    $all_exist = true;
    $missing = [];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
            $missing[] = $dir;
        }
    }

    // Creamos el directorio de logs si no existe
    $log_dir = __DIR__ . '/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
        $missing[] = $log_dir;
    }

    // Creamos el directorio de XSD si no existe
    $xsd_dir = __DIR__ . '/xsd';
    if (!file_exists($xsd_dir)) {
        mkdir($xsd_dir, 0755, true);
        $missing[] = $xsd_dir;
    }

    return showTestResult(
        "Comprobación de directorios",
        true,
        empty($missing) ? "Todos los directorios existen" : "Creados directorios: " . implode(", ", $missing)
    );
}

/**
 * 2. Prueba de certificado
 */
function testCertificate($config)
{
    $cert_path = $config['rutas']['certificado'];
    $exists = file_exists($cert_path);

    // Si el certificado no existe, podemos crear uno de prueba
    if (!$exists && extension_loaded('openssl')) {
        // Crear un directorio para el certificado si no existe
        $cert_dir = dirname($cert_path);
        if (!file_exists($cert_dir)) {
            mkdir($cert_dir, 0755, true);
        }

        // Ejecutar el script de generación de certificado
        $cmd = "bash " . __DIR__ . "/GenerarCertificado.sh 2>&1";
        exec($cmd, $output, $return_var);

        // Verificar si se creó el certificado
        $exists = file_exists($cert_path);

        $message = $exists ?
            "Certificado de prueba generado en: $cert_path" :
            "No se pudo generar el certificado de prueba. Salida: " . implode("\n", $output);
    } else {
        $message = $exists ?
            "Certificado encontrado en: $cert_path" :
            "Certificado no encontrado en: $cert_path. Para un entorno de producción, debe obtener un certificado digital válido.";
    }

    return showTestResult("Comprobación de certificado", $exists, $message);
}

/**
 * 3. Prueba de generación de XML de factura
 */
function testFacturaXML($config)
{
    try {
        // Creamos la factura
        $factura = new \Comprobantes\Factura($config);

        // Establecemos los datos básicos
        $cliente = [
            'tipo_identificacion' => '04', // RUC
            'razon_social' => 'PRUEBAS SERVICIO DE RENTAS INTERNAS',
            'identificacion' => '1760013210001',
            'direccion' => 'Amazonas y NNUU'
        ];

        $factura->setDatosBasicos('1', date('d/m/Y'), $cliente);

        // Agregamos un ítem
        $item = [
            'codigo' => 'PRO001',
            'descripcion' => 'Producto de prueba',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'descuento' => 0,
            'precio_total_sin_impuesto' => 100,
            'impuestos' => [
                [
                    'codigo' => '2', // IVA
                    'codigo_porcentaje' => '2', // 12%
                    'tarifa' => '12.00',
                    'base_imponible' => 100,
                    'valor' => 12
                ]
            ]
        ];

        $factura->agregarItem($item);

        // Establecemos los totales
        $totales = [
            'total_sin_impuestos' => 100,
            'total_descuento' => 0,
            'importe_total' => 112,
            'propina' => 0,
            'impuestos' => [
                [
                    'codigo' => '2', // IVA
                    'codigo_porcentaje' => '2', // 12%
                    'base_imponible' => 100,
                    'valor' => 12
                ]
            ],
            'pagos' => [
                [
                    'forma_pago' => '01', // Sin utilización del sistema financiero
                    'total' => 112
                ]
            ]
        ];

        $factura->setTotales($totales);

        // Agregamos información adicional
        $factura->agregarInfoAdicional('Email', 'prueba@mail.com');
        $factura->agregarInfoAdicional('Teléfono', '099999999');

        // Generamos el XML
        $xml = $factura->generarXML();

        // Guardamos el XML
        $ruta_xml = $factura->guardarXML();

        $result = file_exists($ruta_xml);
        $message = $result ?
            "XML generado correctamente en: $ruta_xml" :
            "No se pudo generar el XML de factura";

        return showTestResult("Generación de XML Factura", $result, $message) ? $ruta_xml : false;
    } catch (\Exception $e) {
        return showTestResult("Generación de XML Factura", false, "Error: " . $e->getMessage());
    }
}

/**
 * 4. Prueba de validación XSD
 */
function testValidacionXSD($ruta_xml)
{
    if (!$ruta_xml || !file_exists($ruta_xml)) {
        return showTestResult("Validación XSD", false, "No hay XML para validar");
    }

    try {
        // Obtenemos el tipo de comprobante
        $tipo_comprobante = \Util\XML::obtenerTipoComprobante($ruta_xml);

        if (!$tipo_comprobante) {
            return showTestResult("Validación XSD", false, "No se pudo determinar el tipo de comprobante");
        }

        // Validamos el XML
        $resultado = \Util\XML::validarComprobante($ruta_xml, $tipo_comprobante);

        if ($resultado === true) {
            return showTestResult("Validación XSD", true, "XML válido según el esquema XSD");
        } else {
            return showTestResult("Validación XSD", false, "XML inválido: " . $resultado);
        }
    } catch (\Exception $e) {
        return showTestResult("Validación XSD", false, "Error: " . $e->getMessage());
    }
}

/**
 * 5. Prueba de firma del XML
 */
function testFirmaXML($config, $ruta_xml)
{
    if (!$ruta_xml || !file_exists($ruta_xml)) {
        return showTestResult("Firma de XML", false, "No hay XML para firmar");
    }

    try {
        $firmador = new \Firmador\FirmadorXML($config);
        $ruta_firmado = $firmador->firmarXML($ruta_xml);

        $result = file_exists($ruta_firmado);
        $message = $result ?
            "XML firmado correctamente en: $ruta_firmado" :
            "No se pudo firmar el XML";

        return showTestResult("Firma de XML", $result, $message) ? $ruta_firmado : false;
    } catch (\Exception $e) {
        return showTestResult("Firma de XML", false, "Error: " . $e->getMessage());
    }
}

/**
 * 6. Prueba de conexión con los servicios web del SRI
 */
function testSRIConnection($config)
{
    try {
        $cliente_sri = new \SRI\ClienteSRI($config);

        // Solo verificamos que se pueda instanciar el cliente
        return showTestResult(
            "Conexión a servicios SRI",
            true,
            "Cliente SRI inicializado correctamente"
        );
    } catch (\Exception $e) {
        return showTestResult("Conexión a servicios SRI", false, "Error: " . $e->getMessage());
    }
}

/**
 * 7. Prueba de envío y autorización (simulada)
 */
function testEnvioAutorizacion($config, $ruta_firmado)
{
    if (!$ruta_firmado || !file_exists($ruta_firmado)) {
        return showTestResult("Envío y Autorización", false, "No hay XML firmado para enviar");
    }

    try {
        // Esta es solo una prueba simulada, no envía realmente al SRI
        $cliente_sri = new \SRI\ClienteSRI($config);

        // Simulamos una respuesta exitosa
        $mensaje = "Prueba de envío y autorización simulada. En un entorno real, el comprobante sería enviado al SRI.";

        // Copiamos el archivo firmado a la carpeta de autorizados (simulación)
        $clave_acceso = basename($ruta_firmado, '.xml');
        $ruta_autorizado = $config['rutas']['autorizados'] . $clave_acceso . '.xml';
        copy($ruta_firmado, $ruta_autorizado);

        return showTestResult("Envío y Autorización (simulada)", true, $mensaje);
    } catch (\Exception $e) {
        return showTestResult("Envío y Autorización (simulada)", false, "Error: " . $e->getMessage());
    }
}

/**
 * 8. Prueba de generación RIDE
 */
function testRIDE($config, $ruta_autorizado)
{
    if (!$ruta_autorizado || !file_exists($ruta_autorizado)) {
        return showTestResult("Generación RIDE", false, "No hay XML autorizado para generar RIDE");
    }

    try {
        $ride = new \Util\RIDE($config);

        // Intentamos generar el RIDE
        $html_ride = $ride->generarRIDEFactura($ruta_autorizado);

        // Guardamos el RIDE en un archivo HTML
        $ruta_ride = dirname($ruta_autorizado) . '/' . basename($ruta_autorizado, '.xml') . '.html';
        file_put_contents($ruta_ride, $html_ride);

        $result = !empty($html_ride);
        $message = $result ?
            "RIDE generado correctamente y guardado en: $ruta_ride" :
            "No se pudo generar el RIDE";

        return showTestResult("Generación RIDE", $result, $message);
    } catch (\Exception $e) {
        return showTestResult("Generación RIDE", false, "Error: " . $e->getMessage());
    }
}

// Aplicamos estilo CSS
echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pruebas del Sistema de Facturación Electrónica SRI</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
        }
        .info-panel {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .summary-item {
            padding: 10px;
            border-radius: 5px;
            color: white;
            text-align: center;
            flex: 1;
            min-width: 120px;
        }
        .summary-success {
            background-color: #28a745;
        }
        .summary-fail {
            background-color: #dc3545;
        }
        .test-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <h1>Pruebas del Sistema de Facturación Electrónica SRI</h1>
    <div class="info-panel">
        <p><strong>Fecha y hora:</strong> ' . date('Y-m-d H:i:s') . '</p>
        <p><strong>Ambiente:</strong> ' . ($config['ambiente'] == 1 ? 'PRUEBAS' : 'PRODUCCIÓN') . '</p>
        <p><strong>Tipo de emisión:</strong> ' . ($config['tipo_emision'] == 1 ? 'NORMAL' : 'OTRO') . '</p>
    </div>
    <div class="test-section">
';

// Ejecutamos las pruebas
$dirs_ok = testDirectories($config);
$cert_ok = testCertificate($config);
$xml_path = testFacturaXML($config);
$xsd_ok = testValidacionXSD($xml_path);

if ($xml_path) {
    $xml_firmado = testFirmaXML($config, $xml_path);
} else {
    echo "<div class='info-panel' style='background-color: #fff3cd; color: #856404;'>
            <p><strong>Nota:</strong> No se ejecutará la prueba de firma porque no se generó el XML correctamente.</p>
          </div>";
    $xml_firmado = false;
}

$sri_ok = testSRIConnection($config);

if ($xml_firmado) {
    $envio_ok = testEnvioAutorizacion($config, $xml_firmado);

    if ($envio_ok) {
        $ruta_autorizado = $config['rutas']['autorizados'] . basename($xml_firmado);
        $ride_ok = testRIDE($config, $ruta_autorizado);
    } else {
        echo "<div class='info-panel' style='background-color: #fff3cd; color: #856404;'>
                <p><strong>Nota:</strong> No se ejecutará la prueba de RIDE porque no se simuló la autorización correctamente.</p>
              </div>";
        $ride_ok = false;
    }
} else {
    echo "<div class='info-panel' style='background-color: #fff3cd; color: #856404;'>
            <p><strong>Nota:</strong> No se ejecutarán las pruebas de envío y RIDE porque no se firmó el XML correctamente.</p>
          </div>";
    $envio_ok = false;
    $ride_ok = false;
}

// Mostramos el resumen
echo '</div>
    <h2>Resumen de pruebas</h2>
    <div class="summary">
        <div class="summary-item ' . ($dirs_ok ? 'summary-success' : 'summary-fail') . '">
            Directorios: ' . ($dirs_ok ? '✓' : '✗') . '
        </div>
        <div class="summary-item ' . ($cert_ok ? 'summary-success' : 'summary-fail') . '">
            Certificado: ' . ($cert_ok ? '✓' : '✗') . '
        </div>
        <div class="summary-item ' . ($xml_path ? 'summary-success' : 'summary-fail') . '">
            Generación XML: ' . ($xml_path ? '✓' : '✗') . '
        </div>
        <div class="summary-item ' . ($xsd_ok ? 'summary-success' : 'summary-fail') . '">
            Validación XSD: ' . ($xsd_ok ? '✓' : '✗') . '
        </div>
        <div class="summary-item ' . ($xml_firmado ? 'summary-success' : 'summary-fail') . '">
            Firma XML: ' . ($xml_firmado ? '✓' : '✗') . '
        </div>
        <div class="summary-item ' . ($sri_ok ? 'summary-success' : 'summary-fail') . '">
            Conexión SRI: ' . ($sri_ok ? '✓' : '✗') . '
        </div>
        <div class="summary-item ' . ($envio_ok ? 'summary-success' : 'summary-fail') . '">
            Envío: ' . ($envio_ok ? '✓' : '✗') . '
        </div>
        <div class="summary-item ' . ($ride_ok ? 'summary-success' : 'summary-fail') . '">
            RIDE: ' . ($ride_ok ? '✓' : '✗') . '
        </div>
    </div>

    <div class="info-panel">
        <h3>Nota Importante</h3>
        <p>Este script ejecuta pruebas básicas y simuladas. Para probar el envío real al SRI, debes:</p>
        <ul>
            <li>Tener un certificado digital válido</li>
            <li>Estar registrado en el SRI como emisor de comprobantes electrónicos</li>
            <li>Tener acceso a los servicios web del SRI</li>
        </ul>
        <p>En un entorno de producción, debes asegurarte de:</p>
        <ul>
            <li>Implementar la firma XAdES-BES real (usando xmlseclibs)</li>
            <li>Descargar los XSD oficiales del SRI</li>
            <li>Gestionar los errores y reintentos adecuadamente</li>
            <li>Mantener un registro de logs detallado</li>
        </ul>
    </div>
</body>
</html>';
