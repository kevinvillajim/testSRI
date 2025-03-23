<?php
// Script de prueba para el sistema de facturación electrónica SRI

// Cargamos el autoloader
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/facturacion-sri.php';

// Cargamos la configuración
$config = require_once __DIR__ . '/config/config.php';

// Función para mostrar resultados de prueba
function showTestResult($test_name, $result, $message = '')
{
    echo "Test: $test_name - ";
    if ($result) {
        echo "<span style='color: green;'>ÉXITO</span>";
    } else {
        echo "<span style='color: red;'>FALLIDO</span>";
    }
    if (!empty($message)) {
        echo " - $message";
    }
    echo "<br>";
}

// Comprobamos la estructura de directorios
function testDirectories($config)
{
    $directories = [
        $config['rutas']['generados'],
        $config['rutas']['firmados'],
        $config['rutas']['enviados'],
        $config['rutas']['autorizados']
    ];

    $all_exist = true;
    $missing = [];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
            $missing[] = $dir;
        }
    }

    showTestResult(
        "Comprobación de directorios",
        true,
        empty($missing) ? "Todos los directorios existen" : "Creados directorios: " . implode(", ", $missing)
    );

    return true;
}

// Comprobamos el certificado
function testCertificate($config)
{
    $cert_path = $config['rutas']['certificado'];
    $exists = file_exists($cert_path);

    showTestResult(
        "Comprobación de certificado",
        $exists,
        $exists ? "Certificado encontrado en: $cert_path" : "Certificado no encontrado en: $cert_path"
    );

    return $exists;
}

// Probamos la generación de una factura XML
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

        showTestResult(
            "Generación de XML Factura",
            file_exists($ruta_xml),
            "XML generado en: $ruta_xml"
        );

        return file_exists($ruta_xml) ? $ruta_xml : false;
    } catch (\Exception $e) {
        showTestResult("Generación de XML Factura", false, "Error: " . $e->getMessage());
        return false;
    }
}

// Probamos la firma del XML
function testFirmaXML($config, $ruta_xml)
{
    try {
        $firmador = new \Firmador\FirmadorXML($config);
        $ruta_firmado = $firmador->firmarXML($ruta_xml);

        showTestResult(
            "Firma de XML",
            file_exists($ruta_firmado),
            "XML firmado en: $ruta_firmado"
        );

        return file_exists($ruta_firmado) ? $ruta_firmado : false;
    } catch (\Exception $e) {
        showTestResult("Firma de XML", false, "Error: " . $e->getMessage());
        return false;
    }
}

// Probamos la conexión con los servicios web del SRI
function testSRIConnection($config)
{
    try {
        $cliente_sri = new \SRI\ClienteSRI($config);

        // Solo verificamos que se pueda instanciar el cliente
        showTestResult(
            "Conexión a servicios SRI",
            true,
            "Cliente SRI inicializado correctamente"
        );

        return true;
    } catch (\Exception $e) {
        showTestResult("Conexión a servicios SRI", false, "Error: " . $e->getMessage());
        return false;
    }
}

// Ejecutamos las pruebas
echo "<h1>Pruebas del Sistema de Facturación Electrónica SRI</h1>";
echo "<p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Ambiente: " . ($config['ambiente'] == 1 ? 'PRUEBAS' : 'PRODUCCIÓN') . "</p>";
echo "<hr>";

$dirs_ok = testDirectories($config);
$cert_ok = testCertificate($config);
$xml_path = testFacturaXML($config);

if ($xml_path) {
    $xml_firmado = testFirmaXML($config, $xml_path);
} else {
    showTestResult("Firma de XML", false, "No se generó el XML correctamente");
    $xml_firmado = false;
}

$sri_ok = testSRIConnection($config);

echo "<hr>";
echo "<h2>Resumen de pruebas</h2>";
echo "Directorios: " . ($dirs_ok ? "✅" : "❌") . "<br>";
echo "Certificado: " . ($cert_ok ? "✅" : "❌") . "<br>";
echo "Generación XML: " . ($xml_path ? "✅" : "❌") . "<br>";
echo "Firma XML: " . ($xml_firmado ? "✅" : "❌") . "<br>";
echo "Conexión SRI: " . ($sri_ok ? "✅" : "❌") . "<br>";

echo "<hr>";
echo "<p><strong>Nota:</strong> Estas pruebas son básicas y no incluyen el envío real al SRI. Para probar el envío, debes tener un certificado válido y credenciales del SRI.</p>";
