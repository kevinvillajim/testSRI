<?php

namespace Util;

/**
 * Clase para manejo de XML
 */
class XML
{
    /**
     * Ruta base a los esquemas XSD
     */
    const XSD_PATH = __DIR__ . '/../../xsd/';

    /**
     * Mapa de tipos de comprobante a archivos XSD
     */
    const XSD_FILES = [
        '01' => 'factura_v1.0.0.xsd',  // Factura
        '04' => 'notaCredito_v1.0.0.xsd', // Nota de Crédito
        '05' => 'notaDebito_v1.0.0.xsd',  // Nota de Débito
        '06' => 'guiaRemision_v1.0.0.xsd', // Guía de Remisión
        '07' => 'comprobanteRetencion_v1.0.0.xsd', // Retención
        '03' => 'liquidacionCompra_v1.0.0.xsd' // Liquidación de Compra
    ];

    /**
     * Convierte un array a XML
     * 
     * @param array $data Los datos a convertir
     * @param \SimpleXMLElement $xml_data El elemento XML padre
     * @return \SimpleXMLElement
     */
    public static function arrayToXML($data, \SimpleXMLElement &$xml_data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item' . $key; // Manejo de arrays numéricos
                }

                // Si el valor es un array con un atributo especial '@attributes'
                if (isset($value['@attributes']) || isset($value['@value'])) {
                    $node = $xml_data->addChild($key, isset($value['@value']) ? $value['@value'] : null);

                    // Añadir atributos si existen
                    if (isset($value['@attributes']) && is_array($value['@attributes'])) {
                        foreach ($value['@attributes'] as $attr_name => $attr_value) {
                            $node->addAttribute($attr_name, $attr_value);
                        }
                    }
                } else {
                    $subnode = $xml_data->addChild($key);
                    self::arrayToXML($value, $subnode);
                }
            } else {
                // Escapar valores especiales y convertir a string
                $xml_data->addChild($key, htmlspecialchars((string)$value));
            }
        }
        return $xml_data;
    }

    /**
     * Genera una clave de acceso para documentos SRI
     * 
     * @param string $fecha Fecha en formato ddmmaaaa
     * @param string $tipo_comprobante Tipo de comprobante (01: Factura, 04: Nota de Crédito, etc.)
     * @param string $ruc RUC del emisor
     * @param string $ambiente Tipo de ambiente (1: Pruebas, 2: Producción)
     * @param string $serie Establecimiento + punto de emisión (001001)
     * @param string $secuencial Número secuencial del comprobante
     * @param string $codigo_numerico Código numérico (8 dígitos)
     * @param string $tipo_emision Tipo de emisión (1: Normal)
     * @return string Clave de acceso de 49 dígitos
     */
    public static function generarClaveAcceso($fecha, $tipo_comprobante, $ruc, $ambiente, $serie, $secuencial, $codigo_numerico, $tipo_emision = '1')
    {
        $clave = $fecha . $tipo_comprobante . $ruc . $ambiente . $serie . $secuencial . $codigo_numerico . $tipo_emision;
        $digito_verificador = self::calcularDigitoModulo11($clave);
        return $clave . $digito_verificador;
    }

    /**
     * Calcula el dígito verificador usando el algoritmo módulo 11
     * 
     * @param string $cadena Cadena para calcular el dígito verificador
     * @return string Dígito verificador
     */
    public static function calcularDigitoModulo11($cadena)
    {
        $factor = 2;
        $suma = 0;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += $factor * intval($cadena[$i]);
            $factor = $factor % 7 == 0 ? 2 : $factor + 1;
        }

        $digito = 11 - ($suma % 11);

        if ($digito == 11) {
            $digito = 0;
        } else if ($digito == 10) {
            $digito = 1;
        }

        return $digito;
    }

    /**
     * Guarda un XML en disco
     * 
     * @param \SimpleXMLElement $xml El documento XML
     * @param string $ruta Ruta donde guardar el archivo
     * @return string Ruta del archivo generado
     */
    public static function guardarXML(\SimpleXMLElement $xml, $ruta)
    {
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($ruta);
        return $ruta;
    }

    /**
     * Valida un XML contra un XSD
     * 
     * @param string $xml_path Ruta del archivo XML
     * @param string $tipo_comprobante Tipo de comprobante (01, 04, etc.)
     * @return bool|string true si es válido, mensaje de error si no
     */
    public static function validarComprobante($xml_path, $tipo_comprobante)
    {
        // Verificar que exista el XML
        if (!file_exists($xml_path)) {
            return "El archivo XML no existe: $xml_path";
        }

        // Verificar que exista un XSD para este tipo de comprobante
        if (!isset(self::XSD_FILES[$tipo_comprobante])) {
            return "No hay un esquema XSD definido para el tipo de comprobante: $tipo_comprobante";
        }

        // Construir la ruta al XSD
        $xsd_path = self::XSD_PATH . self::XSD_FILES[$tipo_comprobante];

        // Verificar que exista el XSD
        if (!file_exists($xsd_path)) {
            return "El archivo XSD no existe: $xsd_path";
        }

        try {
            // Validar el XML contra el XSD
            $xml = new \DOMDocument();
            $xml->load($xml_path);

            libxml_use_internal_errors(true);
            $result = $xml->schemaValidate($xsd_path);

            if (!$result) {
                $errors = libxml_get_errors();
                $error_message = "Error de validación XSD:\n";
                foreach ($errors as $error) {
                    $error_message .= "Línea {$error->line}: {$error->message}\n";
                }
                libxml_clear_errors();
                return $error_message;
            }

            return true;
        } catch (\Exception $e) {
            return "Error al validar XML: " . $e->getMessage();
        }
    }

    /**
     * Normaliza un XML quitando espacios innecesarios y cambiando a UTF-8
     * 
     * @param string $xml_content Contenido del XML
     * @return string XML normalizado
     */
    public static function normalizeXML($xml_content)
    {
        // Configurar la librería DOM para UTF-8
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Cargar el XML
        $dom->loadXML($xml_content);

        // Retornar el XML normalizado
        return $dom->saveXML();
    }

    /**
     * Obtiene el tipo de comprobante de un XML
     * 
     * @param string $xml_path Ruta al archivo XML
     * @return string|false Tipo de comprobante o false si no se puede determinar
     */
    public static function obtenerTipoComprobante($xml_path)
    {
        // Verificar que exista el XML
        if (!file_exists($xml_path)) {
            return false;
        }

        // Cargar el XML
        $xml = simplexml_load_file($xml_path);

        // Verificar si es un XML de comprobante electrónico
        if (!$xml) {
            return false;
        }

        // Intentar obtener el tipo de comprobante
        if (isset($xml->infoTributaria->codDoc)) {
            return (string)$xml->infoTributaria->codDoc;
        }

        return false;
    }

    public static function descargarEsquemasXSD()
    {
        $xsdDir = self::XSD_PATH;
        if (!is_dir($xsdDir)) {
            mkdir($xsdDir, 0755, true);
        }

        // URLs de los esquemas oficiales del SRI
        $esquemas = [
            'factura' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=factura.xsd',
            'notaCredito' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=notaCredito.xsd',
            // Añadir los demás esquemas
        ];

        foreach ($esquemas as $nombre => $url) {
            $destino = $xsdDir . '/' . $nombre . '.xsd';
            file_put_contents($destino, file_get_contents($url));
        }
    }
}
