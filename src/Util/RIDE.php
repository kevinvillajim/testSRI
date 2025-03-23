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
        // Cargar el XML autorizado
        $xml = $this->cargarXMLAutorizado($xml_path);
        
        if (!$xml) {
            throw new \Exception("Error al cargar el XML autorizado: $xml_path");
        }
        
        // Extraer información del XML
        $estado = (string)$xml->estado;
        $numero_autorizacion = (string)$xml->numeroAutorizacion;
        $fecha_autorizacion = (string)$xml->fechaAutorizacion;
        $ambiente = (string)$xml->ambiente;
        
        // Cargar comprobante desde el CDATA
        $comprobante = new \SimpleXMLElement((string)$xml->comprobante);
        
        // Extraer información de la factura
        $info_tributaria = $comprobante->infoTributaria;
        $info_factura = $comprobante->infoFactura;
        $detalles = $comprobante->detalles->detalle;
        
        // Información adicional
        $info_adicional = [];
        if (isset($comprobante->infoAdicional) && isset($comprobante->infoAdicional->campoAdicional)) {
            foreach ($comprobante->infoAdicional->campoAdicional as $campo) {
                $info_adicional[(string)$campo['nombre']] = (string)$campo;
            }
        }
        
        // Generar HTML del RIDE
        $html = $this->generarHTMLFactura(
            $estado,
            $numero_autorizacion,
            $fecha_autorizacion,
            $ambiente,
            $info_tributaria,
            $info_factura,
            $detalles,
            $info_adicional
        );
        
        return $html;
    }
    
    /**
     * Genera un RIDE en HTML para una nota de crédito
     * 
     * @param string $xml_path Ruta al archivo XML autorizado
     * @return string HTML del RIDE
     */
    public function generarRIDENotaCredito($xml_path) {
        // Cargar el XML autorizado
        $xml = $this->cargarXMLAutorizado($xml_path);
        
        if (!$xml) {
            throw new \Exception("Error al cargar el XML autorizado: $xml_path");
        }
        
        // Extraer información del XML
        $estado = (string)$xml->estado;
        $numero_autorizacion = (string)$xml->numeroAutorizacion;
        $fecha_autorizacion = (string)$xml->fechaAutorizacion;
        $ambiente = (string)$xml->ambiente;
        
        // Cargar comprobante desde el CDATA
        $comprobante = new \SimpleXMLElement((string)$xml->comprobante);
        
        // Extraer información de la nota de crédito
        $info_tributaria = $comprobante->infoTributaria;
        $info_nota_credito = $comprobante->infoNotaCredito;
        $detalles = $comprobante->detalles->detalle;
        
        // Información adicional
        $info_adicional = [];
        if (isset($comprobante->infoAdicional) && isset($comprobante->infoAdicional->campoAdicional)) {
            foreach ($comprobante->infoAdicional->campoAdicional as $campo) {
                $info_adicional[(string)$campo['nombre']] = (string)$campo;
            }
        }
        
        // Generar HTML del RIDE
        $html = $this->generarHTMLNotaCredito(
            $estado,
            $numero_autorizacion,
            $fecha_autorizacion,
            $ambiente,
            $info_tributaria,
            $info_nota_credito,
            $detalles,
            $info_adicional
        );
        
        return $html;
    }
    
    /**
     * Carga un XML autorizado
     * 
     * @param string $xml_path Ruta al archivo XML autorizado
     * @return \SimpleXMLElement|false
     */
    protected function cargarXMLAutorizado($xml_path) {
        if (!file_exists($xml_path)) {
            return false;
        }
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xml_path);
        
        if (!$xml) {
            return false;
        }
        
        return $xml;
    }
    
    /**
     * Genera el HTML para una factura
     * 
     * @param string $estado Estado de autorización
     * @param string $numero_autorizacion Número de autorización
     * @param string $fecha_autorizacion Fecha de autorización
     * @param string $ambiente Ambiente
     * @param \SimpleXMLElement $info_tributaria Información tributaria
     * @param \SimpleXMLElement $info_factura Información de la factura
     * @param \SimpleXMLElement $detalles Detalles de la factura
     * @param array $info_adicional Información adicional
     * @return string HTML del RIDE
     */
    protected function generarHTMLFactura($estado, $numero_autorizacion, $fecha_autorizacion, $ambiente, $info_tributaria, $info_factura, $detalles, $info_adicional) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RIDE - Factura</title>
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
            <p><strong>Número de Autorización:</strong> ' . $numero_autorizacion . '</p>
            <p><strong>Fecha y Hora de Autorización:</strong> ' . $fecha_autorizacion . '</p>
            <p><strong>Ambiente:</strong> ' . ($ambiente == 'PRUEBAS' ? 'PRUEBAS' : 'PRODUCCIÓN') . '</p>
            <p><strong>Emisión:</strong> NORMAL</p>
            <p><strong>Clave de Acceso:</strong> ' . $numero_autorizacion . '</p>
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
                    <td>' . (string)$detalle->codigoPrincipal . '</td>
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
                    <td><strong>SUBTOTAL ' . (isset($info_factura->totalConImpuestos->totalImpuesto[0]->tarifa) ? (string)$info_factura->totalConImpuestos->totalImpuesto[0]->tarifa . '%' : '') . ':</strong></td>
                    <td>' . (string)$info_factura->totalSinImpuestos . '</td>
                </tr>
                <tr>
                    <td><strong>SUBTOTAL 0%:</strong></td>
                    <td>0.00</td>
                </tr>
                <tr>
                    <td><strong>SUBTOTAL No objeto de IVA:</strong></td>
                    <td>0.00</td>
                </tr>
                <tr>
                    <td><strong>SUBTOTAL Exento de IVA:</strong></td>
                    <td>0.00</td>
                </tr>
                <tr>
                    <td><strong>SUBTOTAL SIN IMPUESTOS:</strong></td>
                    <td>' . (string)$info_factura->totalSinImpuestos . '</td>
                </tr>
                <tr>
                    <td><strong>DESCUENTO:</strong></td>
                    <td>' . (string)$info_factura->totalDescuento . '</td>
                </tr>';
        
        foreach ($info_factura->totalConImpuestos->totalImpuesto as $impuesto) {
            if ((string)$impuesto->codigo == '2') { // IVA
                $html .= '
                <tr>
                    <td><strong>IVA ' . (string)$impuesto->tarifa . '%:</strong></td>
                    <td>' . (string)$impuesto->valor . '</td>
                </tr>';
            }
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
     * Genera el HTML para una nota de crédito
     * 
     * @param string $estado Estado de autorización
     * @param string $numero_autorizacion Número de autorización
     * @param string $fecha_autorizacion Fecha de autorización
     * @param string $ambiente Ambiente
     * @param \SimpleXMLElement $info_tributaria Información tributaria
     * @param \SimpleXMLElement $info_nota_credito Información de la nota de crédito
     * @param \SimpleXMLElement $detalles Detalles de la nota de crédito
     * @param array $info_adicional Información adicional
     * @return string HTML del RIDE
     */
    protected function generarHTMLNotaCredito($estado, $numero_autorizacion, $fecha_autorizacion, $ambiente, $info_tributaria, $info_nota_credito, $detalles, $info_adicional) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RIDE - Nota de Crédito</title>
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
            <p><strong>Número de Autorización:</strong> ' . $numero_autorizacion . '</p>
            <p><strong>Fecha y Hora de Autorización:</strong> ' . $fecha_autorizacion . '</p>
            <p><strong>Ambiente:</strong> ' . ($ambiente == 'PRUEBAS' ? 'PRUEBAS' : 'PRODUCCIÓN') . '</p>
            <p><strong>Emisión:</strong> NORMAL</p>
            <p><strong>Clave de Acceso:</strong> ' . $numero_autorizacion . '</p>
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
                    <td><strong>SUBTOTAL SIN IMPUESTOS:</strong></td>
                    <td>' . (string)$info_nota_credito->totalSinImpuestos . '</td>
                </tr>';
        
        foreach ($info_nota_credito->totalConImpuestos->totalImpuesto as $impuesto) {
            if ((string)$impuesto->codigo == '2') { // IVA
                $html .= '
                <tr>
                    <td><strong>IVA ' . (string)$impuesto->tarifa . '%:</strong></td>
                    <td>' . (string)$impuesto->valor . '</td>
                </tr>';
            }
        }
        
        $html .= '
                <tr>
                    <td><strong>VALOR TOTAL:</strong></td>
                    <td>' . (string)$info_nota_credito->valorModificacion . '</td>
                </tr>
            </table>
        </div>';
        
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
     * Obtiene el nombre del tipo de documento según su código
     * 
     * @param string $codigo Código del documento
     * @return string Nombre del documento
     */
    protected function obtenerTipoDocumento($codigo) {
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
}
