<?php

/**
 * Script para descargar los esquemas XSD del SRI
 */

// Definir la ruta donde se guardarán los XSD
$xsd_dir = __DIR__ . '/xsd';

// Crear el directorio si no existe
if (!is_dir($xsd_dir)) {
    if (!mkdir($xsd_dir, 0755, true)) {
        die("Error: No se pudo crear el directorio para los XSD.\n");
    }
    echo "Directorio XSD creado: $xsd_dir\n";
}

// URLs de los esquemas XSD (podrían cambiar con el tiempo)
$xsd_urls = [
    'factura_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
    'notaCredito_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=factura.xsd',
    'notaDebito_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=notaDebito.xsd',
    'guiaRemision_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=guiaRemision.xsd',
    'comprobanteRetencion_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=comprobanteRetencion.xsd',
    'liquidacionCompra_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=liquidacionCompra.xsd'
];

// Verificar que cURL esté disponible
if (!function_exists('curl_version')) {
    die("Error: La extensión cURL de PHP es requerida para descargar los XSD.\n");
}

// Descargar cada XSD
foreach ($xsd_urls as $filename => $url) {
    echo "Descargando $filename desde $url... ";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        echo "Error: " . curl_error($ch) . "\n";
    } elseif ($http_code != 200) {
        echo "Error: Código HTTP $http_code\n";
    } else {
        file_put_contents("$xsd_dir/$filename", $response);
        echo "OK\n";
    }

    curl_close($ch);
}

// Nota: En un entorno real, deberías descargar los XSD directamente del SRI.
// Como alternativa, puedes crear XSD básicos para simular la validación durante el desarrollo.

// Crear un archivo de configuración para los XSD
$xsd_config = [
    'xsd_path' => $xsd_dir,
    'xsd_files' => [
        '01' => 'factura_v1.0.0.xsd',
        '04' => 'notaCredito_v1.0.0.xsd',
        '05' => 'notaDebito_v1.0.0.xsd',
        '06' => 'guiaRemision_v1.0.0.xsd',
        '07' => 'comprobanteRetencion_v1.0.0.xsd',
        '03' => 'liquidacionCompra_v1.0.0.xsd'
    ]
];

// Guardar la configuración como JSON
file_put_contents("$xsd_dir/config.json", json_encode($xsd_config, JSON_PRETTY_PRINT));

echo "\nProceso completado. Archivos XSD guardados en: $xsd_dir\n";

// Como alternativa, creamos XSD básicos para pruebas
if (!file_exists("$xsd_dir/factura_v1.0.0.xsd")) {
    echo "\nCreando XSD básicos para pruebas...\n";

    $basic_xsd = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" elementFormDefault="qualified">
  <xs:element name="factura">
    <xs:complexType>
      <xs:sequence>
        <xs:element name="infoTributaria" type="infoTributaria"/>
        <xs:element name="infoFactura" type="infoFactura"/>
        <xs:element name="detalles" type="detalles"/>
        <xs:element name="infoAdicional" type="infoAdicional" minOccurs="0"/>
      </xs:sequence>
      <xs:attribute name="id" type="xs:string" use="required"/>
      <xs:attribute name="version" type="xs:decimal" use="required"/>
    </xs:complexType>
  </xs:element>
  
  <xs:complexType name="infoTributaria">
    <xs:sequence>
      <xs:element name="ambiente" type="xs:string"/>
      <xs:element name="tipoEmision" type="xs:string"/>
      <xs:element name="razonSocial" type="xs:string"/>
      <xs:element name="nombreComercial" type="xs:string" minOccurs="0"/>
      <xs:element name="ruc" type="xs:string"/>
      <xs:element name="claveAcceso" type="xs:string"/>
      <xs:element name="codDoc" type="xs:string"/>
      <xs:element name="estab" type="xs:string"/>
      <xs:element name="ptoEmi" type="xs:string"/>
      <xs:element name="secuencial" type="xs:string"/>
      <xs:element name="dirMatriz" type="xs:string"/>
    </xs:sequence>
  </xs:complexType>
  
  <xs:complexType name="infoFactura">
    <xs:sequence>
      <xs:element name="fechaEmision" type="xs:string"/>
      <!-- Otros elementos según la ficha técnica -->
    </xs:sequence>
  </xs:complexType>
  
  <xs:complexType name="detalles">
    <xs:sequence>
      <xs:element name="detalle" type="detalle" maxOccurs="unbounded"/>
    </xs:sequence>
  </xs:complexType>
  
  <xs:complexType name="detalle">
    <xs:sequence>
      <!-- Elementos del detalle según la ficha técnica -->
    </xs:sequence>
  </xs:complexType>
  
  <xs:complexType name="infoAdicional">
    <xs:sequence>
      <xs:element name="campoAdicional" type="campoAdicional" maxOccurs="unbounded"/>
    </xs:sequence>
  </xs:complexType>
  
  <xs:complexType name="campoAdicional">
    <xs:simpleContent>
      <xs:extension base="xs:string">
        <xs:attribute name="nombre" type="xs:string" use="required"/>
      </xs:extension>
    </xs:simpleContent>
  </xs:complexType>
</xs:schema>
XML;

    file_put_contents("$xsd_dir/factura_v1.0.0.xsd", $basic_xsd);

    // Repetir para otros tipos de documentos
    // En un entorno real, debes usar los XSD oficiales del SRI

    echo "XSD básicos creados para pruebas.\n";
}

echo "\nImportante: Para un entorno de producción, descargue los XSD oficiales del SRI.\n";
