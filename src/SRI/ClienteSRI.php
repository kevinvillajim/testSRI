<?php
namespace SRI;

/**
 * Clase para comunicarse con los servicios web del SRI
 */
class ClienteSRI {
    /**
     * @var array Configuración del sistema
     */
    protected $config;
    
    /**
     * @var \SoapClient Cliente SOAP para recepción
     */
    protected $clienteRecepcion;
    
    /**
     * @var \SoapClient Cliente SOAP para autorización
     */
    protected $clienteAutorizacion;
    
    /**
     * Constructor
     * 
     * @param array $config Configuración del sistema
     */
    public function __construct($config) {
        $this->config = $config;
        
        // Determinar URLs según el ambiente
        $ambiente = $this->config['ambiente'] == 1 ? 'pruebas' : 'produccion';
        
        // Inicializar clientes SOAP
        $this->inicializarClientes($ambiente);
    }
    
    /**
     * Inicializa los clientes SOAP
     * 
     * @param string $ambiente Ambiente ('pruebas' o 'produccion')
     */
    protected function inicializarClientes($ambiente) {
        $opciones = [
            'soap_version' => SOAP_1_1,
            'trace' => 1,
            'exceptions' => 1,
            'connection_timeout' => 30,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ])
        ];
        
        try {
            $this->clienteRecepcion = new \SoapClient(
                $this->config['sri'][$ambiente]['recepcion'],
                $opciones
            );
            
            $this->clienteAutorizacion = new \SoapClient(
                $this->config['sri'][$ambiente]['autorizacion'],
                $opciones
            );
        } catch (\SoapFault $e) {
            throw new \Exception("Error al inicializar clientes SOAP: " . $e->getMessage());
        }
    }
    
    /**
     * Envía un comprobante al SRI
     * 
     * @param string $xml_path Ruta al archivo XML firmado
     * @return object Respuesta del servicio de recepción
     */
    public function enviarComprobante($xml_path) {
        if (!file_exists($xml_path)) {
            throw new \Exception("El archivo XML no existe: $xml_path");
        }
        
        // Leer contenido del archivo XML
        $xml_content = file_get_contents($xml_path);
        
        try {
            // Enviar el comprobante al servicio de recepción
            $respuesta = $this->clienteRecepcion->validarComprobante([
                'xml' => $xml_content
            ]);
            
            // Guardar el XML enviado en la carpeta de enviados
            $nombre_archivo = basename($xml_path);
            copy($xml_path, $this->config['rutas']['enviados'] . $nombre_archivo);
            
            return $respuesta;
        } catch (\SoapFault $e) {
            throw new \Exception("Error al enviar comprobante: " . $e->getMessage());
        }
    }
    
    /**
     * Consulta la autorización de un comprobante
     * 
     * @param string $clave_acceso Clave de acceso del comprobante
     * @return object Respuesta del servicio de autorización
     */
    public function consultarAutorizacion($clave_acceso) {
        try {
            // Consultar la autorización del comprobante
            $respuesta = $this->clienteAutorizacion->autorizacionComprobante([
                'claveAccesoComprobante' => $clave_acceso
            ]);
            
            return $respuesta;
        } catch (\SoapFault $e) {
            throw new \Exception("Error al consultar autorización: " . $e->getMessage());
        }
    }
    
    /**
     * Procesa la respuesta de recepción
     * 
     * @param object $respuesta Respuesta del servicio de recepción
     * @return array Información procesada
     */
    public function procesarRespuestaRecepcion($respuesta) {
        $resultado = [
            'estado' => $respuesta->RespuestaRecepcionComprobante->estado,
            'comprobantes' => []
        ];
        
        // Si hay comprobantes con mensajes, los procesamos
        if (isset($respuesta->RespuestaRecepcionComprobante->comprobantes) && 
            isset($respuesta->RespuestaRecepcionComprobante->comprobantes->comprobante)) {
            
            $comprobantes = $respuesta->RespuestaRecepcionComprobante->comprobantes->comprobante;
            
            // Si solo hay un comprobante, lo convertimos en array
            if (!is_array($comprobantes)) {
                $comprobantes = [$comprobantes];
            }
            
            foreach ($comprobantes as $comprobante) {
                $comp_info = [
                    'claveAcceso' => $comprobante->claveAcceso,
                    'mensajes' => []
                ];
                
                // Procesar mensajes
                if (isset($comprobante->mensajes) && isset($comprobante->mensajes->mensaje)) {
                    $mensajes = $comprobante->mensajes->mensaje;
                    
                    // Si solo hay un mensaje, lo convertimos en array
                    if (!is_array($mensajes)) {
                        $mensajes = [$mensajes];
                    }
                    
                    foreach ($mensajes as $mensaje) {
                        $comp_info['mensajes'][] = [
                            'identificador' => $mensaje->identificador,
                            'mensaje' => $mensaje->mensaje,
                            'informacionAdicional' => isset($mensaje->informacionAdicional) ? 
                                                      $mensaje->informacionAdicional : '',
                            'tipo' => $mensaje->tipo
                        ];
                    }
                }
                
                $resultado['comprobantes'][] = $comp_info;
            }
        }
        
        return $resultado;
    }
    
    /**
     * Procesa la respuesta de autorización
     * 
     * @param object $respuesta Respuesta del servicio de autorización
     * @return array Información procesada
     */
    public function procesarRespuestaAutorizacion($respuesta) {
        $resultado = [
            'claveAccesoConsultada' => $respuesta->RespuestaAutorizacionComprobante->claveAccesoConsultada,
            'numeroComprobantes' => $respuesta->RespuestaAutorizacionComprobante->numeroComprobantes,
            'autorizaciones' => []
        ];
        
        // Si hay autorizaciones, las procesamos
        if (isset($respuesta->RespuestaAutorizacionComprobante->autorizaciones) && 
            isset($respuesta->RespuestaAutorizacionComprobante->autorizaciones->autorizacion)) {
            
            $autorizaciones = $respuesta->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;
            
            // Si solo hay una autorización, la convertimos en array
            if (!is_array($autorizaciones)) {
                $autorizaciones = [$autorizaciones];
            }
            
            foreach ($autorizaciones as $autorizacion) {
                $auth_info = [
                    'estado' => $autorizacion->estado,
                    'numeroAutorizacion' => isset($autorizacion->numeroAutorizacion) ? 
                                           $autorizacion->numeroAutorizacion : '',
                    'fechaAutorizacion' => isset($autorizacion->fechaAutorizacion) ? 
                                          $autorizacion->fechaAutorizacion : '',
                    'ambiente' => isset($autorizacion->ambiente) ? $autorizacion->ambiente : '',
                    'comprobante' => isset($autorizacion->comprobante) ? $autorizacion->comprobante : '',
                    'mensajes' => []
                ];
                
                // Procesar mensajes
                if (isset($autorizacion->mensajes) && isset($autorizacion->mensajes->mensaje)) {
                    $mensajes = $autorizacion->mensajes->mensaje;
                    
                    // Si solo hay un mensaje, lo convertimos en array
                    if (!is_array($mensajes)) {
                        $mensajes = [$mensajes];
                    }
                    
                    foreach ($mensajes as $mensaje) {
                        $auth_info['mensajes'][] = [
                            'identificador' => $mensaje->identificador,
                            'mensaje' => $mensaje->mensaje,
                            'informacionAdicional' => isset($mensaje->informacionAdicional) ? 
                                                      $mensaje->informacionAdicional : '',
                            'tipo' => $mensaje->tipo
                        ];
                    }
                }
                
                $resultado['autorizaciones'][] = $auth_info;
            }
        }
        
        return $resultado;
    }
    
    /**
     * Guarda un comprobante autorizado
     * 
     * @param array $respuesta Respuesta procesada de autorización
     * @param string $clave_acceso Clave de acceso del comprobante
     * @return bool|string Ruta del archivo guardado o false en caso de error
     */
    public function guardarComprobanteAutorizado($respuesta, $clave_acceso) {
        // Verificar si hay autorizaciones y si el estado es AUTORIZADO
        if (empty($respuesta['autorizaciones']) || 
            $respuesta['autorizaciones'][0]['estado'] !== 'AUTORIZADO') {
            return false;
        }
        
        // Obtener el comprobante autorizado
        $comprobante_xml = $respuesta['autorizaciones'][0]['comprobante'];
        
        // Crear el XML de autorización
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><autorizacion></autorizacion>');
        
        $xml->addChild('estado', $respuesta['autorizaciones'][0]['estado']);
        $xml->addChild('numeroAutorizacion', $respuesta['autorizaciones'][0]['numeroAutorizacion']);
        $xml->addChild('fechaAutorizacion', $respuesta['autorizaciones'][0]['fechaAutorizacion']);
        $xml->addChild('ambiente', $respuesta['autorizaciones'][0]['ambiente']);
        
        // Agregar el comprobante como CDATA
        $comprobante_node = $xml->addChild('comprobante');
        $dom = dom_import_simplexml($comprobante_node);
        $cdata = $dom->ownerDocument->createCDATASection($comprobante_xml);
        $dom->appendChild($cdata);
        
        // Guardar el archivo XML
        $ruta_archivo = $this->config['rutas']['autorizados'] . $clave_acceso . '.xml';
        
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($ruta_archivo);
        
        return $ruta_archivo;
    }
}
