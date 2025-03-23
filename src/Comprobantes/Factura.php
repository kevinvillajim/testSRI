<?php
namespace Comprobantes;

use Util\XML;

/**
 * Clase para generar facturas electrónicas
 */
class Factura {
    /**
     * @var array Configuración del sistema
     */
    protected $config;
    
    /**
     * @var array Datos de la factura
     */
    protected $datos;
    
    /**
     * @var string Clave de acceso
     */
    protected $claveAcceso;
    
    /**
     * @var \SimpleXMLElement Documento XML de la factura
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
            'infoFactura' => [],
            'detalles' => ['detalle' => []],
            'infoAdicional' => ['campoAdicional' => []]
        ];
    }
    
    /**
     * Establece los datos básicos de la factura
     * 
     * @param string $secuencial Número secuencial de la factura
     * @param string $fechaEmision Fecha de emisión (dd/mm/aaaa)
     * @param array $cliente Datos del cliente
     * @return $this
     */
    public function setDatosBasicos($secuencial, $fechaEmision, $cliente) {
        // Formateamos el secuencial a 9 dígitos
        $secuencial = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
        
        // Generamos la clave de acceso
        $fecha_ymd = \DateTime::createFromFormat('d/m/Y', $fechaEmision)->format('dmY');
        $serie = $this->config['establecimiento']['codigo'] . $this->config['establecimiento']['punto_emision'];
        $codigo_numerico = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        
        $this->claveAcceso = XML::generarClaveAcceso(
            $fecha_ymd,
            '01', // 01 = Factura
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
            'codDoc' => '01', // 01 = Factura
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
        
        // Establecemos los datos de información de factura
        $this->datos['infoFactura'] = [
            'fechaEmision' => $fechaEmision,
            'dirEstablecimiento' => $this->config['establecimiento']['dir_establecimiento'],
            'tipoIdentificacionComprador' => $cliente['tipo_identificacion'],
            'razonSocialComprador' => $cliente['razon_social'],
            'identificacionComprador' => $cliente['identificacion'],
            'direccionComprador' => $cliente['direccion'],
            'totalSinImpuestos' => '0.00',
            'totalDescuento' => '0.00',
            'totalConImpuestos' => ['totalImpuesto' => []],
            'propina' => '0.00',
            'importeTotal' => '0.00',
            'moneda' => 'DOLAR'
        ];
        
        // Agregar campos condicionales
        if (!empty($this->config['emisor']['contribuyente_especial'])) {
            $this->datos['infoFactura']['contribuyenteEspecial'] = $this->config['emisor']['contribuyente_especial'];
        }
        
        if (!empty($this->config['emisor']['obligado_contabilidad'])) {
            $this->datos['infoFactura']['obligadoContabilidad'] = $this->config['emisor']['obligado_contabilidad'];
        }
        
        return $this;
    }
    
    /**
     * Agrega un ítem a la factura
     * 
     * @param array $item Datos del ítem
     * @return $this
     */
    public function agregarItem($item) {
        $detalle = [
            'codigoPrincipal' => $item['codigo'],
            'descripcion' => $item['descripcion'],
            'cantidad' => number_format($item['cantidad'], 2, '.', ''),
            'precioUnitario' => number_format($item['precio_unitario'], 2, '.', ''),
            'descuento' => number_format($item['descuento'], 2, '.', ''),
            'precioTotalSinImpuesto' => number_format($item['precio_total_sin_impuesto'], 2, '.', ''),
            'impuestos' => [
                'impuesto' => []
            ]
        ];
        
        // Agregar código auxiliar si existe
        if (isset($item['codigo_auxiliar'])) {
            $detalle['codigoAuxiliar'] = $item['codigo_auxiliar'];
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
    public function setTotales($totales) {
        $this->datos['infoFactura']['totalSinImpuestos'] = number_format($totales['total_sin_impuestos'], 2, '.', '');
        $this->datos['infoFactura']['totalDescuento'] = number_format($totales['total_descuento'], 2, '.', '');
        $this->datos['infoFactura']['importeTotal'] = number_format($totales['importe_total'], 2, '.', '');
        $this->datos['infoFactura']['propina'] = number_format($totales['propina'], 2, '.', '');
        
        // Agregar totales por impuesto
        foreach ($totales['impuestos'] as $impuesto) {
            $this->datos['infoFactura']['totalConImpuestos']['totalImpuesto'][] = [
                'codigo' => $impuesto['codigo'],
                'codigoPorcentaje' => $impuesto['codigo_porcentaje'],
                'baseImponible' => number_format($impuesto['base_imponible'], 2, '.', ''),
                'valor' => number_format($impuesto['valor'], 2, '.', '')
            ];
        }
        
        // Agregar formas de pago
        $this->datos['infoFactura']['pagos'] = ['pago' => []];
        
        foreach ($totales['pagos'] as $pago) {
            $pago_info = [
                'formaPago' => $pago['forma_pago'],
                'total' => number_format($pago['total'], 2, '.', '')
            ];
            
            // Agregar plazo si existe
            if (isset($pago['plazo'])) {
                $pago_info['plazo'] = $pago['plazo'];
                $pago_info['unidadTiempo'] = $pago['unidad_tiempo'];
            }
            
            $this->datos['infoFactura']['pagos']['pago'][] = $pago_info;
        }
        
        return $this;
    }
    
    /**
     * Agrega información adicional a la factura
     * 
     * @param string $nombre Nombre del campo
     * @param string $valor Valor del campo
     * @return $this
     */
    public function agregarInfoAdicional($nombre, $valor) {
        $this->datos['infoAdicional']['campoAdicional'][] = [
            '@attributes' => ['nombre' => $nombre],
            '@value' => $valor
        ];
        
        return $this;
    }
    
    /**
     * Genera el XML de la factura
     * 
     * @return \SimpleXMLElement
     */
    public function generarXML() {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="1.0.0"></factura>');
        
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
    public function guardarXML() {
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
    public function getClaveAcceso() {
        return $this->claveAcceso;
    }
}
