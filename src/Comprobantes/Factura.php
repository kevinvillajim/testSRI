<?php

namespace Comprobantes;

use Util\XML;

/**
 * Clase para generar facturas electrónicas según esquema 2.1.0
 */
class Factura
{
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
    public function __construct($config)
    {
        $this->config = $config;
        $this->datos = [
            'infoTributaria' => [],
            'infoFactura' => [],
            'detalles' => ['detalle' => []],
            'reembolsos' => ['reembolsoDetalle' => []],
            'retenciones' => ['retencion' => []],
            'infoSustitutivaGuiaRemision' => null,
            'otrosRubrosTerceros' => ['rubro' => []],
            'tipoNegociable' => null,
            'maquinaFiscal' => null,
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
    public function setDatosBasicos($secuencial, $fechaEmision, $cliente)
    {
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
     * Establecer datos para comercio exterior (v2.1.0)
     */
    public function setComercioExterior($datos)
    {
        if (isset($datos['comercioExterior'])) {
            $this->datos['infoFactura']['comercioExterior'] = $datos['comercioExterior']; // EXPORTADOR
        }

        if (isset($datos['incoTermFactura'])) {
            $this->datos['infoFactura']['incoTermFactura'] = $datos['incoTermFactura'];
        }

        if (isset($datos['lugarIncoTerm'])) {
            $this->datos['infoFactura']['lugarIncoTerm'] = $datos['lugarIncoTerm'];
        }

        if (isset($datos['paisOrigen'])) {
            $this->datos['infoFactura']['paisOrigen'] = $datos['paisOrigen'];
        }

        if (isset($datos['puertoEmbarque'])) {
            $this->datos['infoFactura']['puertoEmbarque'] = $datos['puertoEmbarque'];
        }

        if (isset($datos['puertoDestino'])) {
            $this->datos['infoFactura']['puertoDestino'] = $datos['puertoDestino'];
        }

        if (isset($datos['paisDestino'])) {
            $this->datos['infoFactura']['paisDestino'] = $datos['paisDestino'];
        }

        if (isset($datos['paisAdquisicion'])) {
            $this->datos['infoFactura']['paisAdquisicion'] = $datos['paisAdquisicion'];
        }

        return $this;
    }

    /**
     * Establece campos de reembolso (v2.1.0)
     */
    public function setReembolsos($datos)
    {
        if (isset($datos['codDocReembolso'])) {
            $this->datos['infoFactura']['codDocReembolso'] = $datos['codDocReembolso'];
        }

        if (isset($datos['totalComprobantesReembolso'])) {
            $this->datos['infoFactura']['totalComprobantesReembolso'] = number_format($datos['totalComprobantesReembolso'], 2, '.', '');
        }

        if (isset($datos['totalBaseImponibleReembolso'])) {
            $this->datos['infoFactura']['totalBaseImponibleReembolso'] = number_format($datos['totalBaseImponibleReembolso'], 2, '.', '');
        }

        if (isset($datos['totalImpuestoReembolso'])) {
            $this->datos['infoFactura']['totalImpuestoReembolso'] = number_format($datos['totalImpuestoReembolso'], 2, '.', '');
        }

        return $this;
    }

    /**
     * Agrega un ítem a la factura
     * 
     * @param array $item Datos del ítem
     * @return $this
     */
    public function agregarItem($item)
    {
        $detalle = [
            'codigoPrincipal' => $item['codigo'],
            'descripcion' => $item['descripcion'],
            'cantidad' => number_format($item['cantidad'], 6, '.', ''),
            'precioUnitario' => number_format($item['precio_unitario'], 6, '.', ''),
            'descuento' => number_format($item['descuento'], 2, '.', ''),
            'precioTotalSinImpuesto' => number_format($item['precio_total_sin_impuesto'], 2, '.', ''),
            'impuestos' => [
                'impuesto' => []
            ]
        ];

        // Agregar campos nuevos de la v2.1.0
        if (isset($item['unidadMedida'])) {
            $detalle['unidadMedida'] = $item['unidadMedida'];
        }

        if (isset($item['precioSinSubsidio'])) {
            $detalle['precioSinSubsidio'] = number_format($item['precioSinSubsidio'], 6, '.', '');
        }

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
    public function setTotales($totales)
    {
        $this->datos['infoFactura']['totalSinImpuestos'] = number_format($totales['total_sin_impuestos'], 2, '.', '');
        $this->datos['infoFactura']['totalDescuento'] = number_format($totales['total_descuento'], 2, '.', '');
        $this->datos['infoFactura']['importeTotal'] = number_format($totales['importe_total'], 2, '.', '');
        $this->datos['infoFactura']['propina'] = number_format($totales['propina'], 2, '.', '');

        // Agregar campo de subsidio (nuevo en v2.1.0)
        if (isset($totales['total_subsidio'])) {
            $this->datos['infoFactura']['totalSubsidio'] = number_format($totales['total_subsidio'], 2, '.', '');
        }

        // Agregar campos para comercio exterior (nuevos en v2.1.0)
        if (isset($totales['incoTermTotalSinImpuestos'])) {
            $this->datos['infoFactura']['incoTermTotalSinImpuestos'] = $totales['incoTermTotalSinImpuestos'];
        }

        if (isset($totales['fleteInternacional'])) {
            $this->datos['infoFactura']['fleteInternacional'] = number_format($totales['fleteInternacional'], 2, '.', '');
        }

        if (isset($totales['seguroInternacional'])) {
            $this->datos['infoFactura']['seguroInternacional'] = number_format($totales['seguroInternacional'], 2, '.', '');
        }

        if (isset($totales['gastosAduaneros'])) {
            $this->datos['infoFactura']['gastosAduaneros'] = number_format($totales['gastosAduaneros'], 2, '.', '');
        }

        if (isset($totales['gastosTransporteOtros'])) {
            $this->datos['infoFactura']['gastosTransporteOtros'] = number_format($totales['gastosTransporteOtros'], 2, '.', '');
        }

        // Agregar campos de retención IVA y renta (nuevos en v2.1.0)
        if (isset($totales['valorRetIva'])) {
            $this->datos['infoFactura']['valorRetIva'] = number_format($totales['valorRetIva'], 2, '.', '');
        }

        if (isset($totales['valorRetRenta'])) {
            $this->datos['infoFactura']['valorRetRenta'] = number_format($totales['valorRetRenta'], 2, '.', '');
        }

        // Agregar totales por impuesto
        foreach ($totales['impuestos'] as $impuesto) {
            $impuestoInfo = [
                'codigo' => $impuesto['codigo'],
                'codigoPorcentaje' => $impuesto['codigo_porcentaje'],
                'baseImponible' => number_format($impuesto['base_imponible'], 2, '.', ''),
                'valor' => number_format($impuesto['valor'], 2, '.', '')
            ];

            // Agregar campos nuevos de v2.1.0
            if (isset($impuesto['descuentoAdicional'])) {
                $impuestoInfo['descuentoAdicional'] = number_format($impuesto['descuentoAdicional'], 2, '.', '');
            }

            if (isset($impuesto['tarifa'])) {
                $impuestoInfo['tarifa'] = $impuesto['tarifa'];
            }

            if (isset($impuesto['valorDevolucionIva'])) {
                $impuestoInfo['valorDevolucionIva'] = number_format($impuesto['valorDevolucionIva'], 2, '.', '');
            }

            $this->datos['infoFactura']['totalConImpuestos']['totalImpuesto'][] = $impuestoInfo;
        }

        // Agregar compensaciones (nuevo en v2.1.0)
        if (isset($totales['compensaciones']) && !empty($totales['compensaciones'])) {
            $this->datos['infoFactura']['compensaciones'] = ['compensacion' => []];

            foreach ($totales['compensaciones'] as $comp) {
                $this->datos['infoFactura']['compensaciones']['compensacion'][] = [
                    'codigo' => $comp['codigo'],
                    'tarifa' => $comp['tarifa'],
                    'valor' => number_format($comp['valor'], 2, '.', '')
                ];
            }
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
     * Agregar retenciones (nuevo en v2.1.0)
     */
    public function agregarRetencion($retencion)
    {
        if (!isset($this->datos['retenciones'])) {
            $this->datos['retenciones'] = ['retencion' => []];
        }

        $this->datos['retenciones']['retencion'][] = [
            'codigo' => $retencion['codigo'],
            'codigoPorcentaje' => $retencion['codigoPorcentaje'],
            'tarifa' => $retencion['tarifa'],
            'valor' => number_format($retencion['valor'], 2, '.', '')
        ];

        return $this;
    }

    /**
     * Agregar información sustitutiva de guía de remisión (v2.1.0)
     */
    public function setInfoSustitutivaGuiaRemision($guia)
    {
        $this->datos['infoSustitutivaGuiaRemision'] = [
            'dirPartida' => $guia['dirPartida'],
            'dirDestinatario' => $guia['dirDestinatario'],
            'fechaIniTransporte' => $guia['fechaIniTransporte'],
            'fechaFinTransporte' => $guia['fechaFinTransporte'],
            'razonSocialTransportista' => $guia['razonSocialTransportista'],
            'tipoIdentificacionTransportista' => $guia['tipoIdentificacionTransportista'],
            'rucTransportista' => $guia['rucTransportista'],
            'placa' => $guia['placa'],
            'destinos' => ['destino' => []]
        ];

        foreach ($guia['destinos'] as $destino) {
            $destinoInfo = [
                'motivoTraslado' => $destino['motivoTraslado'],
                'codEstabDestino' => $destino['codEstabDestino']
            ];

            if (isset($destino['docAduaneroUnico'])) {
                $destinoInfo['docAduaneroUnico'] = $destino['docAduaneroUnico'];
            }

            if (isset($destino['ruta'])) {
                $destinoInfo['ruta'] = $destino['ruta'];
            }

            $this->datos['infoSustitutivaGuiaRemision']['destinos']['destino'][] = $destinoInfo;
        }

        return $this;
    }

    /**
     * Agregar otros rubros terceros (v2.1.0)
     */
    public function agregarRubroTerceros($rubro)
    {
        if (!isset($this->datos['otrosRubrosTerceros'])) {
            $this->datos['otrosRubrosTerceros'] = ['rubro' => []];
        }

        $this->datos['otrosRubrosTerceros']['rubro'][] = [
            'concepto' => $rubro['concepto'],
            'total' => number_format($rubro['total'], 2, '.', '')
        ];

        return $this;
    }

    /**
     * Establecer tipo negociable (v2.1.0)
     */
    public function setTipoNegociable($correo)
    {
        $this->datos['tipoNegociable'] = [
            'correo' => $correo
        ];

        return $this;
    }

    /**
     * Establecer información de máquina fiscal (v2.1.0)
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
     * Agrega información adicional a la factura
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
     * Genera el XML de la factura
     * 
     * @return \SimpleXMLElement
     */
    public function generarXML()
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="2.1.0" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="file:/C:/borrar/xsd/111-xsd-1_V2.1.0.xsd"></factura>');

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
