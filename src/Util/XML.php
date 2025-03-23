<?php
namespace Util;

/**
 * Clase para manejo de XML
 */
class XML {
    /**
     * Convierte un array a XML
     * 
     * @param array $data Los datos a convertir
     * @param \SimpleXMLElement $xml_data El elemento XML padre
     * @return \SimpleXMLElement
     */
    public static function arrayToXML($data, \SimpleXMLElement &$xml_data) {
        foreach($data as $key => $value) {
            if(is_array($value)) {
                if(is_numeric($key)){
                    $key = 'item'.$key; // Manejo de arrays numéricos
                }
                $subnode = $xml_data->addChild($key);
                self::arrayToXML($value, $subnode);
            } else {
                $xml_data->addChild("$key", htmlspecialchars("$value"));
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
    public static function generarClaveAcceso($fecha, $tipo_comprobante, $ruc, $ambiente, $serie, $secuencial, $codigo_numerico, $tipo_emision = '1') {
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
    public static function calcularDigitoModulo11($cadena) {
        $factor = 2;
        $suma = 0;
        
        for($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += $factor * intval($cadena[$i]);
            $factor = $factor % 7 == 0 ? 2 : $factor + 1;
        }
        
        $digito = 11 - ($suma % 11);
        
        if($digito == 11) {
            $digito = 0;
        } else if($digito == 10) {
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
    public static function guardarXML(\SimpleXMLElement $xml, $ruta) {
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
     * @param string $xsd_path Ruta del archivo XSD
     * @return bool|string true si es válido, mensaje de error si no
     */
    public static function validarXSD($xml_path, $xsd_path) {
        $xml = new \DOMDocument();
        $xml->load($xml_path);
        
        if (!$xml->schemaValidate($xsd_path)) {
            $errors = libxml_get_errors();
            $error_message = "Error de validación XSD:\n";
            foreach ($errors as $error) {
                $error_message .= "Línea {$error->line}: {$error->message}\n";
            }
            libxml_clear_errors();
            return $error_message;
        }
        
        return true;
    }
}
