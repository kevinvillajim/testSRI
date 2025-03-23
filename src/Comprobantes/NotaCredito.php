<?php
namespace Comprobantes;

use Util\XML;

/**
 * Clase para generar notas de crédito electrónicas
 */
class NotaCredito {
    /**
     * @var array Configuración del sistema
     */
    protected $config;
    
    /**
     * @var array Datos de la nota de crédito
     */
    protected $datos;
    
    /**
     * @var string Clave de acceso
     */
    protected $claveAcceso;
    
    /**
     * @var \SimpleXMLElement Documento XML de la nota de crédito
     */
    protected $xml;
    
    /**
     * Constructor
     * 
     * @param array $config Configuración del sistema
     */
    public function __construct($config) {
        $this->config = $config;
        $this->datos = [
            'infoTributaria' => [],
            'infoNotaCredito' => [],
            'detalles' => ['detalle' => []],
            'maquinaFiscal' => null,
            'infoAdicional' => ['campoAdicional' => []]
        ];
    }
    
    /**
     * Establece los datos básicos de la nota de crédito
     * 
     * @param string $secuencial Número secuencial de la nota de crédito
     * @param string $fechaEmision Fecha de emisión (dd/mm/aaaa)
     * @param array $cliente Datos del cliente
     * @param array $documento_modificado Datos del documento modificado
     * @param string $motivo Motivo de la nota de crédito
     * @return $this
     */
    public function setDatosBasicos($secuencial, $fechaEmision, $cliente, $documento_modificado, $motivo) {
        // Formateamos el secuencial a 9 dígitos
        $secuencial = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
        
        // Generamos la clave de acceso
        $fecha_ymd = \DateTime::createFromFormat('d/m/Y', $fechaEmision)->format('dmY');
        $serie = $this->config['establecimiento']['codigo'] . $this->config['establecimiento']['punto_emision'];
        $codigo_numerico = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        
        $this->claveAcceso = XML::generarClaveAcceso(
            $fecha_ymd,
            '04', // 04 = Nota de Crédito
            $this->config['emisor']['ruc'],
            $this->config['ambiente'],
            $serie,
            $secuencial,
            $codigo_numerico,
            $this->config['tipo_emision']
        );
        
        // Establecemos los datos de información tributaria
        $this->datos['infoTributaria'] = [
            'ambiente' => $this->config['ambiente'],
            'tipoEmision' => $this->config['tipo_emision'],
            'razonSocial' => $this->config['emisor']['razon_social'],
            'nombreComercial' => $this->config['emisor']['nombre_comercial'],
            'ruc' => $this->config['emisor']['ruc'],
            'claveAcceso' => $this->claveAcceso,
            'codDoc' => '04', // 04 = Nota de Crédito
            'estab' => $this->config['establecimiento']['codigo'],
            'ptoEmi' => $this->config['establecimiento']['punto_emision'],
            'secuencial' => $secuencial,
            'dirMatriz' => $this->config['emisor']['dir_matriz']
        ];
        
        // Agregar campos condicionales para emisores
        if ($this->config['emisor']['agente_retencion']) {
            $this->datos['infoTributaria']['agenteRetencion'] = $this->config['emisor']['agente_retencion'];
        }
        
        if ($this->config['emisor']['regimen_microempresas']) {
            $this->datos['infoTributaria']['contribuyenteRimpe'] = 'CONTRIBUYENTE RÉGIMEN RIMPE';
        }
        
        // Establecemos los datos de información de nota de crédito
        $this->datos['infoNotaCredito'] = [
            'fechaEmision' => $fechaEmision,
            'dirEstablecimiento' => $this->config['establecimiento']['dir_establecimiento'],
            'tipoIdentificacionComprador' => $cliente['tipo_identificacion'],
            'razonSocialComprador' => $cliente['razon_social'],
            'identificacionComprador' => $cliente['identificacion'],
            'codDocModificado' => $documento_modificado['tipo_doc'],
            'numDocModificado' => $documento_modificado['numero'],
            'fechaEmisionDocSustento' => $documento_modificado['fecha_emision'],
            'totalSinImpuestos' => '0.00',
            'valorModificacion' => '0.00',
            'moneda' => 'DOLAR',
            'totalConImpuestos' => ['totalImpuesto' => []],
            'motivo' => $motivo
        ];
        
        // Agregar campos condicionales
        if (!empty($this->config['emisor']['contribuyente_especial'])) {
            $this->datos['infoNotaCredito']['contribuyenteEspecial'] = $this->config['emisor']['contribuyente_especial'];
        }
        
        if (!empty($this->config['emisor']['obligado_contabilidad'])) {
            $this->datos['infoNotaCredito']['obligadoContabilidad'] = $this->config['emisor']['obligado_contabilidad'];
        }
        
        // Campos adicionales en v1.1.0
        if (isset($cliente['rise'])) {
            $this->datos['infoNotaCredito']['rise'] = $cliente['rise'];
        }
        
        return $this;
    }
    
    /**
     * Establecer compensaciones (nuevo en v1.1.0)
     */
    public function setCompensaciones($compensaciones) {
        if (!empty($compensaciones)) {
            $this->datos['infoNotaCredito']['compensaciones'] = ['compensacion' => []];
            
            foreach ($compensaciones as $comp) {
                $this->datos['infoNotaCredito']['compensaciones']['compensacion'][] = [
                    'codigo' => $comp['codigo'],
                    'tarifa' => $comp['tarifa'],
                    'valor' => number_format($comp['valor'], 2, '.', '')
                ];
            }
        }
        
        return $this;
    }
    
    /**
     * Agrega un ítem a la nota de crédito
     * 
     * @param array $item Datos del ítem
     * @return $this
     */
    public function agregarItem($item) {
        $detalle = [
            'codigoInterno' => $item['codigo'],
            'descripcion' => $item['descripcion'],
            'cantidad' => number_format($item['cantidad'], 6, '.', ''),
            'precioUnitario' => number_format($item['precio_unitario'], 6, '.', ''),
            'descuento' => number_format($item['descuento'], 2, '.', ''),
            'precioTotalSinImpuesto' => number_format($item['precio_total_sin_impuesto'], 2, '.', ''),
            'impuestos' => [
                'impuesto' => []
            ]
        ];

        // Agregar código auxiliar si existe
        if (isset($item['codigo_auxiliar'])) {
            $detalle['codigoAdicional'] = $item['codigo_auxiliar'];
        }

        // Agregar impuestos
        foreach ($item['impuestos'] as $impuesto) {
            $detalle['impuestos']['impuesto'][] = [
                'codigo' => $impuesto['codigo'],
                'codigoPorcentaje' => $impuesto['codigo_porcentaje'],
                'tarifa' => $impuesto['tarifa'],
                'baseImponible' => number_format($impuesto['base_imponible'], 2, '.', ''),
                'valor' => number_format($impuesto['valor'], 2, '.', '')
            ];
        }

        $this->datos['detalles']['detalle'][] = $detalle;

        return $this;
    }

    /**
     * Agrega información de totales
     * 
     * @param array $totales Datos de totales
     * @return $this
     */
    public function setTotales($totales)
    {
        $this->datos['infoNotaCredito']['totalSinImpuestos'] = number_format($totales['total_sin_impuestos'], 2, '.', '');
        $this->datos['infoNotaCredito']['valorModificacion'] = number_format($totales['valor_modificacion'], 2, '.', '');

        // Agregar totales por impuesto
        foreach ($totales['impuestos'] as $impuesto) {
            $impuestoInfo = [
                'codigo' => $impuesto['codigo'],
                'codigoPorcentaje' => $impuesto['codigo_porcentaje'],
                'baseImponible' => number_format($impuesto['base_imponible'], 2, '.', ''),
                'valor' => number_format($impuesto['valor'], 2, '.', '')
            ];

            // Nuevos campos en v1.1.0
            if (isset($impuesto['valorDevolucionIva'])) {
                $impuestoInfo['valorDevolucionIva'] = number_format($impuesto['valorDevolucionIva'], 2, '.', '');
            }

            $this->datos['infoNotaCredito']['totalConImpuestos']['totalImpuesto'][] = $impuestoInfo;
        }

        return $this;
    }

    /**
     * Establecer información de máquina fiscal (v1.1.0)
     */
    public function setMaquinaFiscal($maquina)
    {
        $this->datos['maquinaFiscal'] = [
            'marca' => $maquina['marca'],
            'modelo' => $maquina['modelo'],
            'serie' => $maquina['serie']
        ];

        return $this;
    }

    /**
     * Agrega información adicional a la nota de crédito
     * 
     * @param string $nombre Nombre del campo
     * @param string $valor Valor del campo
     * @return $this
     */
    public function agregarInfoAdicional($nombre, $valor)
    {
        $this->datos['infoAdicional']['campoAdicional'][] = [
            '@attributes' => ['nombre' => $nombre],
            '@value' => $valor
        ];

        return $this;
    }

    /**
     * Genera el XML de la nota de crédito
     * 
     * @return \SimpleXMLElement
     */
    public function generarXML()
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><notaCredito id="comprobante" version="1.1.0" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="file:/C:/borrar/xsd/11-xsd-3_V1.1.0.xsd"></notaCredito>');

        // Convertir el array a XML
        XML::arrayToXML($this->datos, $xml);

        // Procesamos la información adicional
        if (!empty($this->datos['infoAdicional']['campoAdicional'])) {
            $info_adicional = $xml->addChild('infoAdicional');

            foreach ($this->datos['infoAdicional']['campoAdicional'] as $campo) {
                $campo_adicional = $info_adicional->addChild('campoAdicional', $campo['@value']);
                $campo_adicional->addAttribute('nombre', $campo['@attributes']['nombre']);
            }
        }

        $this->xml = $xml;
        return $this->xml;
    }

    /**
     * Guarda el XML en disco
     * 
     * @return string Ruta del archivo generado
     */
    public function guardarXML()
    {
        if (!$this->xml) {
            $this->generarXML();
        }

        $ruta = $this->config['rutas']['generados'] . $this->claveAcceso . '.xml';
        return XML::guardarXML($this->xml, $ruta);
    }

    /**
     * Obtiene la clave de acceso
     * 
     * @return string
     */
    public function getClaveAcceso()
    {
        return $this->claveAcceso;
    }
}