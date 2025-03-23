<?php
namespace Util;

/**
 * Clase para generar la Representación Impresa de Documentos Electrónicos (RIDE)
 */
class RIDE {
    /**
     * @var array Configuración del sistema
     */
    protected $config;
    
    /**
     * Constructor
     * 
     * @param array $config Configuración del sistema
     */
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Genera un RIDE en HTML para una factura
     * 
     * @param string $xml_path Ruta al archivo XML autorizado
     * @return string HTML del RIDE
     */
    public function generarRIDEFactura($xml_path) {
        try {
            // Verificar que el archivo exista
            if (!file_exists($xml_path)) {
                throw new \Exception("El archivo XML autorizado no existe: $xml_path");
            }

            // Cargar el XML
            $xml = simplexml_load_file($xml_path);
            
            // Determinar la versión
            $version = isset($xml['version']) ? (string)$xml['version'] : '1.0.0';
            
            // Generar el RIDE según la versión
            if (version_compare($version, '2.0.0', '>=')) {
                return $this->generarRIDEFacturaV2($xml);
            } else {
                return $this->generarRIDEFacturaV1($xml);
            }
        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error al generar RIDE: ' . $e->getMessage() . '</div>';
        }
    }
    
    /**
     * Genera el RIDE para facturas versión 2.1.0
     */
    protected function generarRIDEFacturaV2($xml) {
        // Extraer toda la información del XML
        $info_tributaria = $xml->infoTributaria;
        $info_factura = $xml->infoFactura;
        $detalles = $xml->detalles->detalle;
        
        // Información adicional
        $info_adicional = [];
        if (isset($xml->infoAdicional) && isset($xml->infoAdicional->campoAdicional)) {
            foreach ($xml->infoAdicional->campoAdicional as $campo) {
                $info_adicional[(string)$campo['nombre']] = (string)$campo;
            }
        }
        
        // Generar HTML con todos los campos nuevos
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RIDE - Factura v2.1.0</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .container {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 20px;
        }
        .header {
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .info-factura {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 10px;
        }
        .cliente {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .totales {
            border: 1px solid #000;
            padding: 10px;
            margin-top: 10px;
        }
        .info-adicional {
            border: 1px solid #000;
            padding: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>FACTURA</h2>
            <p><strong>Número de Autorización:</strong> ' . (string)$info_tributaria->claveAcceso . '</p>
            <p><strong>Fecha y Hora de Autorización:</strong> ' . date('Y-m-d H:i:s') . '</p>
            <p><strong>Ambiente:</strong> ' . ((string)$info_tributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN') . '</p>
            <p><strong>Emisión:</strong> ' . ((string)$info_tributaria->tipoEmision == '1' ? 'NORMAL' : 'CONTINGENCIA') . '</p>
            <p><strong>Clave de Acceso:</strong> ' . (string)$info_tributaria->claveAcceso . '</p>
        </div>
        
        <div class="info-factura">
            <table>
                <tr>
                    <td><strong>Razón Social:</strong> ' . (string)$info_tributaria->razonSocial . '</td>
                    <td><strong>RUC:</strong> ' . (string)$info_tributaria->ruc . '</td>
                </tr>
                <tr>
                    <td><strong>Nombre Comercial:</strong> ' . (string)$info_tributaria->nombreComercial . '</td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>Dirección Matriz:</strong> ' . (string)$info_tributaria->dirMatriz . '</td>
                    <td><strong>Dirección Sucursal:</strong> ' . (string)$info_factura->dirEstablecimiento . '</td>
                </tr>';
                
        if (isset($info_factura->contribuyenteEspecial)) {
            $html .= '<tr><td colspan="2"><strong>Contribuyente Especial Nro:</strong> ' . (string)$info_factura->contribuyenteEspecial . '</td></tr>';
        }
        
        if (isset($info_factura->obligadoContabilidad)) {
            $html .= '<tr><td colspan="2"><strong>Obligado a Llevar Contabilidad:</strong> ' . (string)$info_factura->obligadoContabilidad . '</td></tr>';
        }
        
        if (isset($info_tributaria->contribuyenteRimpe)) {
            $html .= '<tr><td colspan="2"><strong>Contribuyente RIMPE:</strong> ' . (string)$info_tributaria->contribuyenteRimpe . '</td></tr>';
        }
        
        $html .= '
            </table>
        </div>
        
        <div class="cliente">
            <table>
                <tr>
                    <td><strong>Razón Social / Nombres y Apellidos:</strong> ' . (string)$info_factura->razonSocialComprador . '</td>
                    <td><strong>Identificación:</strong> ' . (string)$info_factura->identificacionComprador . '</td>
                </tr>
                <tr>
                    <td><strong>Fecha Emisión:</strong> ' . (string)$info_factura->fechaEmision . '</td>
                    <td><strong>Guía de Remisión:</strong> ' . (isset($info_factura->guiaRemision) ? (string)$info_factura->guiaRemision : '') . '</td>
                </tr>';
        
        // Campos de comercio exterior (v2.1.0)
        if (isset($info_factura->comercioExterior)) {
            $html .= '<tr><td colspan="2"><strong>Comercio Exterior:</strong> ' . (string)$info_factura->comercioExterior . '</td></tr>';
        }
        
        if (isset($info_factura->incoTermFactura)) {
            $html .= '<tr><td><strong>Incoterm Factura:</strong> ' . (string)$info_factura->incoTermFactura . '</td>';
            $html .= '<td><strong>Lugar Incoterm:</strong> ' . (string)$info_factura->lugarIncoTerm . '</td></tr>';
        }
        
        if (isset($info_factura->paisOrigen)) {
            $html .= '<tr><td><strong>País Origen:</strong> ' . (string)$info_factura->paisOrigen . '</td>';
            $html .= '<td><strong>País Destino:</strong> ' . (string)$info_factura->paisDestino . '</td></tr>';
        }
        
        $html .= '
            </table>
        </div>
        
        <h3>Detalles</h3>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Unidad</th>
                    <th>Precio Unitario</th>
                    <th>Descuento</th>
                    <th>Precio Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($detalles as $detalle) {
            $html .= '
                <tr>
                    <td>' . (string)$detalle->codigoPrincipal . '</td>
                    <td>' . (string)$detalle->descripcion . '</td>
                    <td>' . (string)$detalle->cantidad . '</td>
                    <td>' . (isset($detalle->unidadMedida) ? (string)$detalle->unidadMedida : '') . '</td>
                    <td>' . (string)$detalle->precioUnitario . '</td>
                    <td>' . (string)$detalle->descuento . '</td>
                   <td>' . (string)$detalle->precioTotalSinImpuesto . '</td>
               </tr>';
        }

        $html .= '
           </tbody>
       </table>
       
       <div class="totales">
           <table>
               <tr>
                   <td><strong>SUBTOTAL 12%:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_factura->totalConImpuestos->totalImpuesto, '2', '2') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL 0%:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_factura->totalConImpuestos->totalImpuesto, '2', '0') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL No objeto de IVA:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_factura->totalConImpuestos->totalImpuesto, '2', '6') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL Exento de IVA:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_factura->totalConImpuestos->totalImpuesto, '2', '7') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL SIN IMPUESTOS:</strong></td>
                   <td>' . (string)$info_factura->totalSinImpuestos . '</td>
               </tr>
               <tr>
                   <td><strong>DESCUENTO:</strong></td>
                   <td>' . (string)$info_factura->totalDescuento . '</td>
               </tr>';

        // Total de subsidio (v2.1.0)
        if (isset($info_factura->totalSubsidio)) {
            $html .= '
               <tr>
                   <td><strong>SUBSIDIO:</strong></td>
                   <td>' . (string)$info_factura->totalSubsidio . '</td>
               </tr>';
        }

        // Recorrer todos los impuestos para IVA
        if (isset($info_factura->totalConImpuestos->totalImpuesto)) {
            foreach ($info_factura->totalConImpuestos->totalImpuesto as $impuesto) {
                if ((string)$impuesto->codigo == '2') { // IVA
                    $html .= '
               <tr>
                   <td><strong>IVA ' . (string)$impuesto->codigoPorcentaje . ':</strong></td>
                   <td>' . (string)$impuesto->valor . '</td>
               </tr>';
                }
            }
        }

        // Campos de transporte internacional (v2.1.0)
        if (isset($info_factura->fleteInternacional)) {
            $html .= '
               <tr>
                   <td><strong>FLETE INTERNACIONAL:</strong></td>
                   <td>' . (string)$info_factura->fleteInternacional . '</td>
               </tr>';
        }

        if (isset($info_factura->seguroInternacional)) {
            $html .= '
               <tr>
                   <td><strong>SEGURO INTERNACIONAL:</strong></td>
                   <td>' . (string)$info_factura->seguroInternacional . '</td>
               </tr>';
        }

        if (isset($info_factura->gastosAduaneros)) {
            $html .= '
               <tr>
                   <td><strong>GASTOS ADUANEROS:</strong></td>
                   <td>' . (string)$info_factura->gastosAduaneros . '</td>
               </tr>';
        }

        if (isset($info_factura->gastosTransporteOtros)) {
            $html .= '
               <tr>
                   <td><strong>GASTOS TRANSPORTE OTROS:</strong></td>
                   <td>' . (string)$info_factura->gastosTransporteOtros . '</td>
               </tr>';
        }

        // Valores de retención (v2.1.0)
        if (isset($info_factura->valorRetIva)) {
            $html .= '
               <tr>
                   <td><strong>RETENCIÓN IVA:</strong></td>
                   <td>' . (string)$info_factura->valorRetIva . '</td>
               </tr>';
        }

        if (isset($info_factura->valorRetRenta)) {
            $html .= '
               <tr>
                   <td><strong>RETENCIÓN RENTA:</strong></td>
                   <td>' . (string)$info_factura->valorRetRenta . '</td>
               </tr>';
        }

        $html .= '
               <tr>
                   <td><strong>PROPINA:</strong></td>
                   <td>' . (string)$info_factura->propina . '</td>
               </tr>
               <tr>
                   <td><strong>VALOR TOTAL:</strong></td>
                   <td>' . (string)$info_factura->importeTotal . '</td>
               </tr>
           </table>
       </div>';

        // Compensaciones (v2.1.0)
        if (isset($info_factura->compensaciones) && isset($info_factura->compensaciones->compensacion)) {
            $html .= '
       <div class="totales">
           <h3>Compensaciones</h3>
           <table>
               <tr>
                   <th>Código</th>
                   <th>Tarifa</th>
                   <th>Valor</th>
               </tr>';

            foreach ($info_factura->compensaciones->compensacion as $compensacion) {
                $html .= '
               <tr>
                   <td>' . (string)$compensacion->codigo . '</td>
                   <td>' . (string)$compensacion->tarifa . '</td>
                   <td>' . (string)$compensacion->valor . '</td>
               </tr>';
            }

            $html .= '
           </table>
       </div>';
        }

        // Formas de pago
        if (isset($info_factura->pagos) && isset($info_factura->pagos->pago)) {
            $html .= '
       <div class="totales">
           <h3>Formas de Pago</h3>
           <table>
               <tr>
                   <th>Forma Pago</th>
                   <th>Total</th>
                   <th>Plazo</th>
                   <th>Unidad de Tiempo</th>
               </tr>';

            foreach ($info_factura->pagos->pago as $pago) {
                $html .= '
               <tr>
                   <td>' . $this->obtenerFormaPago((string)$pago->formaPago) . '</td>
                   <td>' . (string)$pago->total . '</td>
                   <td>' . (isset($pago->plazo) ? (string)$pago->plazo : '') . '</td>
                   <td>' . (isset($pago->unidadTiempo) ? (string)$pago->unidadTiempo : '') . '</td>
               </tr>';
            }

            $html .= '
           </table>
       </div>';
        }

        // Retenciones (v2.1.0)
        if (isset($xml->retenciones) && isset($xml->retenciones->retencion)) {
            $html .= '
       <div class="totales">
           <h3>Retenciones</h3>
           <table>
               <tr>
                   <th>Código</th>
                   <th>Código Porcentaje</th>
                   <th>Tarifa</th>
                   <th>Valor</th>
               </tr>';

            foreach ($xml->retenciones->retencion as $retencion) {
                $html .= '
               <tr>
                   <td>' . (string)$retencion->codigo . '</td>
                   <td>' . (string)$retencion->codigoPorcentaje . '</td>
                   <td>' . (string)$retencion->tarifa . '</td>
                   <td>' . (string)$retencion->valor . '</td>
               </tr>';
            }

            $html .= '
           </table>
       </div>';
        }

        // Información adicional
        if (!empty($info_adicional)) {
            $html .= '
       <div class="info-adicional">
           <h3>Información Adicional</h3>
           <table>';

            foreach ($info_adicional as $nombre => $valor) {
                $html .= '
               <tr>
                   <td><strong>' . $nombre . ':</strong></td>
                   <td>' . $valor . '</td>
               </tr>';
            }

            $html .= '
           </table>
       </div>';
        }

        $html .= '
   </div>
</body>
</html>';

        return $html;
    }

    /**
     * Genera un RIDE en HTML para una nota de crédito
     * 
     * @param string $xml_path Ruta al archivo XML autorizado
     * @return string HTML del RIDE
     */
    public function generarRIDENotaCredito($xml_path)
    {
        try {
            // Verificar que el archivo exista
            if (!file_exists($xml_path)) {
                throw new \Exception("El archivo XML autorizado no existe: $xml_path");
            }

            // Cargar el XML
            $xml = simplexml_load_file($xml_path);

            // Determinar la versión
            $version = isset($xml['version']) ? (string)$xml['version'] : '1.0.0';

            // Generar el RIDE según la versión
            if (version_compare($version, '1.1.0', '>=')) {
                return $this->generarRIDENotaCreditoV11($xml);
            } else {
                return $this->generarRIDENotaCreditoV1($xml);
            }
        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error al generar RIDE: ' . $e->getMessage() . '</div>';
        }
    }

    /**
     * Genera el RIDE para notas de crédito versión 1.1.0
     */
    protected function generarRIDENotaCreditoV11($xml)
    {
        // Extraer información del XML
        $info_tributaria = $xml->infoTributaria;
        $info_nota_credito = $xml->infoNotaCredito;
        $detalles = $xml->detalles->detalle;

        // Información adicional
        $info_adicional = [];
        if (isset($xml->infoAdicional) && isset($xml->infoAdicional->campoAdicional)) {
            foreach ($xml->infoAdicional->campoAdicional as $campo) {
                $info_adicional[(string)$campo['nombre']] = (string)$campo;
            }
        }

        // Generar HTML
        $html = '<!DOCTYPE html>
<html>
<head>
   <meta charset="UTF-8">
   <title>RIDE - Nota de Crédito v1.1.0</title>
   <style>
       body {
           font-family: Arial, sans-serif;
           font-size: 12px;
           margin: 0;
           padding: 20px;
       }
       .container {
           border: 1px solid #000;
           padding: 10px;
           margin-bottom: 20px;
       }
       .header {
           border-bottom: 1px solid #000;
           padding-bottom: 10px;
           margin-bottom: 10px;
       }
       .info-nota-credito {
           border: 1px solid #000;
           padding: 10px;
           margin-bottom: 10px;
       }
       .cliente {
           border: 1px solid #000;
           padding: 10px;
           margin-bottom: 10px;
       }
       table {
           width: 100%;
           border-collapse: collapse;
       }
       th, td {
           border: 1px solid #000;
           padding: 5px;
           text-align: left;
       }
       th {
           background-color: #f2f2f2;
       }
       .totales {
           border: 1px solid #000;
           padding: 10px;
           margin-top: 10px;
       }
       .info-adicional {
           border: 1px solid #000;
           padding: 10px;
           margin-top: 10px;
       }
   </style>
</head>
<body>
   <div class="container">
       <div class="header">
           <h2>NOTA DE CRÉDITO</h2>
           <p><strong>Número de Autorización:</strong> ' . (string)$info_tributaria->claveAcceso . '</p>
           <p><strong>Fecha y Hora de Autorización:</strong> ' . date('Y-m-d H:i:s') . '</p>
           <p><strong>Ambiente:</strong> ' . ((string)$info_tributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN') . '</p>
           <p><strong>Emisión:</strong> ' . ((string)$info_tributaria->tipoEmision == '1' ? 'NORMAL' : 'CONTINGENCIA') . '</p>
           <p><strong>Clave de Acceso:</strong> ' . (string)$info_tributaria->claveAcceso . '</p>
       </div>
       
       <div class="info-nota-credito">
           <table>
               <tr>
                   <td><strong>Razón Social:</strong> ' . (string)$info_tributaria->razonSocial . '</td>
                   <td><strong>RUC:</strong> ' . (string)$info_tributaria->ruc . '</td>
               </tr>
               <tr>
                   <td><strong>Nombre Comercial:</strong> ' . (string)$info_tributaria->nombreComercial . '</td>
                   <td></td>
               </tr>
               <tr>
                   <td><strong>Dirección Matriz:</strong> ' . (string)$info_tributaria->dirMatriz . '</td>
                   <td><strong>Dirección Sucursal:</strong> ' . (string)$info_nota_credito->dirEstablecimiento . '</td>
               </tr>';

        if (isset($info_nota_credito->contribuyenteEspecial)) {
            $html .= '<tr><td colspan="2"><strong>Contribuyente Especial Nro:</strong> ' . (string)$info_nota_credito->contribuyenteEspecial . '</td></tr>';
        }

        if (isset($info_nota_credito->obligadoContabilidad)) {
            $html .= '<tr><td colspan="2"><strong>Obligado a Llevar Contabilidad:</strong> ' . (string)$info_nota_credito->obligadoContabilidad . '</td></tr>';
        }

        if (isset($info_tributaria->contribuyenteRimpe)) {
            $html .= '<tr><td colspan="2"><strong>Contribuyente RIMPE:</strong> ' . (string)$info_tributaria->contribuyenteRimpe . '</td></tr>';
        }

        // Campo RISE (v1.1.0)
        if (isset($info_nota_credito->rise)) {
            $html .= '<tr><td colspan="2"><strong>RISE:</strong> ' . (string)$info_nota_credito->rise . '</td></tr>';
        }

        $html .= '
           </table>
       </div>
       
       <div class="cliente">
           <table>
               <tr>
                   <td><strong>Razón Social / Nombres y Apellidos:</strong> ' . (string)$info_nota_credito->razonSocialComprador . '</td>
                   <td><strong>Identificación:</strong> ' . (string)$info_nota_credito->identificacionComprador . '</td>
               </tr>
               <tr>
                   <td><strong>Fecha Emisión:</strong> ' . (string)$info_nota_credito->fechaEmision . '</td>
                   <td></td>
               </tr>
               <tr>
                   <td><strong>Comprobante que modifica:</strong> ' . $this->obtenerTipoDocumento((string)$info_nota_credito->codDocModificado) . '</td>
                   <td><strong>Número:</strong> ' . (string)$info_nota_credito->numDocModificado . '</td>
               </tr>
               <tr>
                   <td><strong>Fecha Emisión Comprobante:</strong> ' . (string)$info_nota_credito->fechaEmisionDocSustento . '</td>
                   <td><strong>Motivo:</strong> ' . (string)$info_nota_credito->motivo . '</td>
               </tr>
           </table>
       </div>
       
       <h3>Detalles</h3>
       <table>
           <thead>
               <tr>
                   <th>Código</th>
                   <th>Descripción</th>
                   <th>Cantidad</th>
                   <th>Precio Unitario</th>
                   <th>Descuento</th>
                   <th>Precio Total</th>
               </tr>
           </thead>
           <tbody>';

        foreach ($detalles as $detalle) {
            $html .= '
               <tr>
                   <td>' . (string)$detalle->codigoInterno . '</td>
                   <td>' . (string)$detalle->descripcion . '</td>
                   <td>' . (string)$detalle->cantidad . '</td>
                   <td>' . (string)$detalle->precioUnitario . '</td>
                   <td>' . (string)$detalle->descuento . '</td>
                   <td>' . (string)$detalle->precioTotalSinImpuesto . '</td>
               </tr>';
        }

        $html .= '
           </tbody>
       </table>
       
       <div class="totales">
           <table>
               <tr>
                   <td><strong>SUBTOTAL 12%:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_nota_credito->totalConImpuestos->totalImpuesto, '2', '2') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL 0%:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_nota_credito->totalConImpuestos->totalImpuesto, '2', '0') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL No objeto de IVA:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_nota_credito->totalConImpuestos->totalImpuesto, '2', '6') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL Exento de IVA:</strong></td>
                   <td>' . $this->buscarImpuestoPorCodigo($info_nota_credito->totalConImpuestos->totalImpuesto, '2', '7') . '</td>
               </tr>
               <tr>
                   <td><strong>SUBTOTAL SIN IMPUESTOS:</strong></td>
                   <td>' . (string)$info_nota_credito->totalSinImpuestos . '</td>
               </tr>';

        // Recorrer todos los impuestos para IVA
        foreach ($info_nota_credito->totalConImpuestos->totalImpuesto as $impuesto) {
            if ((string)$impuesto->codigo == '2') { // IVA
                $html .= '
               <tr>
                   <td><strong>IVA ' . (string)$impuesto->codigoPorcentaje . ':</strong></td>
                   <td>' . (string)$impuesto->valor . '</td>
               </tr>';

                // Campo valorDevolucionIva (v1.1.0)
                if (isset($impuesto->valorDevolucionIva)) {
                    $html .= '
               <tr>
                   <td><strong>DEVOLUCIÓN IVA:</strong></td>
                   <td>' . (string)$impuesto->valorDevolucionIva . '</td>
               </tr>';
                }
            }
        }

        $html .= '
               <tr>
                   <td><strong>VALOR TOTAL:</strong></td>
                   <td>' . (string)$info_nota_credito->valorModificacion . '</td>
               </tr>
           </table>
       </div>';

        // Compensaciones (v1.1.0)
        if (isset($info_nota_credito->compensaciones) && isset($info_nota_credito->compensaciones->compensacion)) {
            $html .= '
       <div class="totales">
           <h3>Compensaciones</h3>
           <table>
               <tr>
                   <th>Código</th>
                   <th>Tarifa</th>
                   <th>Valor</th>
               </tr>';

            foreach ($info_nota_credito->compensaciones->compensacion as $compensacion) {
                $html .= '
               <tr>
                   <td>' . (string)$compensacion->codigo . '</td>
                   <td>' . (string)$compensacion->tarifa . '</td>
                   <td>' . (string)$compensacion->valor . '</td>
               </tr>';
            }

            $html .= '
           </table>
       </div>';
        }

        // Información adicional
        if (!empty($info_adicional)) {
            $html .= '
       <div class="info-adicional">
           <h3>Información Adicional</h3>
           <table>';

            foreach ($info_adicional as $nombre => $valor) {
                $html .= '
               <tr>
                   <td><strong>' . $nombre . ':</strong></td>
                   <td>' . $valor . '</td>
               </tr>';
            }

            $html .= '
           </table>
       </div>';
        }

        $html .= '
   </div>
</body>
</html>';

        return $html;
    }

    /**
     * Busca un impuesto por código y código de porcentaje
     */
    private function buscarImpuestoPorCodigo($impuestos, $codigo, $codigoPorcentaje)
    {
        foreach ($impuestos as $impuesto) {
            if ((string)$impuesto->codigo === $codigo && (string)$impuesto->codigoPorcentaje === $codigoPorcentaje) {
                return (string)$impuesto->baseImponible;
            }
        }
        return '0.00';
    }

    /**
     * Obtiene el nombre del tipo de documento según su código
     */
    private function obtenerTipoDocumento($codigo)
    {
        $tipos = [
            '01' => 'FACTURA',
            '03' => 'LIQUIDACIÓN DE COMPRA',
            '04' => 'NOTA DE CRÉDITO',
            '05' => 'NOTA DE DÉBITO',
            '06' => 'GUÍA DE REMISIÓN',
            '07' => 'COMPROBANTE DE RETENCIÓN'
        ];

        return isset($tipos[$codigo]) ? $tipos[$codigo] : 'DESCONOCIDO';
    }

    /**
     * Obtiene la descripción de la forma de pago según su código
     */
    private function obtenerFormaPago($codigo)
    {
        $formas = [
            '01' => 'SIN UTILIZACIÓN DEL SISTEMA FINANCIERO',
            '15' => 'COMPENSACIÓN DE DEUDAS',
            '16' => 'TARJETA DE DÉBITO',
            '17' => 'DINERO ELECTRÓNICO',
            '18' => 'TARJETA PREPAGO',
            '19' => 'TARJETA DE CRÉDITO',
            '20' => 'OTROS CON UTILIZACIÓN DEL SISTEMA FINANCIERO',
            '21' => 'ENDOSO DE TÍTULOS'
        ];

        return isset($formas[$codigo]) ? $formas[$codigo] . ' (' . $codigo . ')' : 'DESCONOCIDO (' . $codigo . ')';
    }
}