<?php

namespace Util;

/**
 * Clase para manejo de XML
 */
class XML
{
    /**
     * Mapa de tipos de comprobante a archivos XSD
     */
    private static $xsdFiles = [];
    private static $xsdPath = '';
    private static $configLoaded = false;

    /**
     * Constructor que carga la configuración
     * 
     * @param string|null $configPath Ruta al archivo de configuración
     */
    public function __construct($configPath = null)
    {
        // Cargar la configuración al instanciar la clase
        self::loadConfig($configPath);
    }

    /**
     * Carga la configuración desde config.json
     * 
     * @param string|null $configPath Ruta al archivo de configuración
     * @return bool True si la configuración se cargó correctamente
     */
    public static function loadConfig($configPath = null)
    {
        // Si ya está cargada la configuración, no hacer nada
        if (self::$configLoaded) {
            return true;
        }

        // Si no se proporcionó una ruta, usar la predeterminada
        if ($configPath === null) {
            $configPath = dirname(__DIR__) . '../config/config.json';
        }

        // Verificar que exista el archivo de configuración
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);

            // Guardar la configuración en las propiedades estáticas
            self::$xsdPath = $config['xsd_path'] ?? '';
            self::$xsdFiles = $config['xsd_files'] ?? [];
            self::$configLoaded = true;
            return true;
        } else {
            // Si no existe el archivo, mostrar un error
            error_log("Config file not found: $configPath");
            return false;
        }
    }

    /**
     * Reinicia la configuración cargada (útil para pruebas)
     */
    public static function resetConfig()
    {
        self::$xsdFiles = [];
        self::$xsdPath = '';
        self::$configLoaded = false;
    }

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
            if (is_null($value)) {
                continue; // Saltamos los nodos nulos
            }

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
        // Cargar la configuración si no está cargada
        if (!self::$configLoaded) {
            if (!self::loadConfig()) {
                return "No se pudo cargar la configuración";
            }
        }

        // Verificar que exista el XML
        if (!file_exists($xml_path)) {
            return "El archivo XML no existe: $xml_path";
        }

        // Verificar que exista un XSD para este tipo de comprobante
        if (!isset(self::$xsdFiles[$tipo_comprobante])) {
            return "No hay un esquema XSD definido para el tipo de comprobante: $tipo_comprobante";
        }

        // Construir la ruta al XSD
        $xsd_path = self::$xsdPath . '/' . self::$xsdFiles[$tipo_comprobante];

        // Verificar que exista el XSD
        if (!file_exists($xsd_path)) {
            return "El archivo XSD no existe: $xsd_path";
        }

        // Verificar si también se necesita el esquema xmldsig-core-schema.xsd
        $xmldsig_path = dirname($xsd_path) . '/xmldsig-core-schema.xsd';
        $xsd_content = file_get_contents($xsd_path);

        if (strpos($xsd_content, 'xmldsig-core-schema.xsd') !== false && !file_exists($xmldsig_path)) {
            return "El archivo xmldsig-core-schema.xsd no existe y es requerido por el esquema XSD: $xsd_path";
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

    /**
     * Descarga los esquemas XSD del SRI
     * 
     * @return bool True si se descargaron correctamente, false en caso contrario
     */
    public static function descargarEsquemasXSD()
    {
        // Cargar la configuración si no está cargada
        if (!self::$configLoaded) {
            if (!self::loadConfig()) {
                return false;
            }
        }

        $xsdDir = self::$xsdPath;
        if (!is_dir($xsdDir)) {
            mkdir($xsdDir, 0755, true);
        }

        // URLs de los esquemas (actualizadas para las nuevas versiones)
        $esquemas = [
            'factura_V2.1.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/schemas/V2.1.0/factura.xsd',
            'NotaCredito_V1.1.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/schemas/V1.1.0/notaCredito.xsd',
            'notaDebito_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=notaDebito.xsd',
            'guiaRemision_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=guiaRemision.xsd',
            'comprobanteRetencion_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=comprobanteRetencion.xsd',
            'liquidacionCompra_v1.0.0.xsd' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?xsd=liquidacionCompra.xsd',
            'xmldsig-core-schema.xsd' => 'https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd'
        ];

        $exito = true;
        foreach ($esquemas as $nombre => $url) {
            try {
                $contenido = file_get_contents($url);
                if ($contenido === false) {
                    $exito = false;
                    continue;
                }

                file_put_contents($xsdDir . '/' . $nombre, $contenido);
            } catch (\Exception $e) {
                $exito = false;
            }
        }

        return $exito;
    }

    /**
     * Obtiene la ruta del XSD para un tipo de comprobante
     * 
     * @param string $tipo_comprobante Tipo de comprobante (01, 04, etc.)
     * @return string|false Ruta al XSD o false si no existe
     */
    public static function getXsdPath($tipo_comprobante)
    {
        // Cargar la configuración si no está cargada
        if (!self::$configLoaded) {
            if (!self::loadConfig()) {
                return false;
            }
        }

        // Verificar que exista un XSD para este tipo de comprobante
        if (!isset(self::$xsdFiles[$tipo_comprobante])) {
            return false;
        }

        $xsd_path = self::$xsdPath . '/' . self::$xsdFiles[$tipo_comprobante];

        // Verificar que exista el XSD
        if (!file_exists($xsd_path)) {
            return false;
        }

        return $xsd_path;
    }

    /**
     * Obtiene el mapa de tipos de comprobante a archivos XSD
     * 
     * @return array Mapa de tipos de comprobante a archivos XSD
     */
    public static function getXsdFiles()
    {
        // Cargar la configuración si no está cargada
        if (!self::$configLoaded) {
            self::loadConfig();
        }

        return self::$xsdFiles;
    }

    /**
     * Obtiene la ruta base de los XSD
     * 
     * @return string Ruta base de los XSD
     */
    public static function getXsdBasePath()
    {
        // Cargar la configuración si no está cargada
        if (!self::$configLoaded) {
            self::loadConfig();
        }

        return self::$xsdPath;
    }
}
