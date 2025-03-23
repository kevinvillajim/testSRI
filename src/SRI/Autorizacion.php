<?php
namespace SRI;

/**
 * Clase para gestionar el proceso de autorización de comprobantes
 */
class Autorizacion {
    /**
     * @var array Configuración del sistema
     */
    protected $config;
    
    /**
     * @var \SRI\ClienteSRI Cliente SRI para comunicación con servicios web
     */
    protected $clienteSRI;
    
    /**
     * @var array Resultados del proceso de autorización
     */
    protected $resultados;
    
    /**
     * Constructor
     * 
     * @param array $config Configuración del sistema
     */
    public function __construct($config) {
        $this->config = $config;
        $this->clienteSRI = new ClienteSRI($config);
        $this->resultados = [
            'estado' => '',
            'mensajes' => [],
            'clave_acceso' => '',
            'ruta_autorizado' => '',
            'xml_autorizado' => null
        ];
    }
    
    /**
     * Autoriza un comprobante electrónico
     * 
     * @param string $tipo_comprobante Tipo de comprobante (factura, nota-credito, etc.)
     * @param string $clave_acceso Clave de acceso del comprobante
     * @param string $ruta_firmado Ruta al archivo firmado
     * @return array Resultados del proceso de autorización
     */
    public function autorizar($tipo_comprobante, $clave_acceso, $ruta_firmado) {
        $this->resultados['clave_acceso'] = $clave_acceso;
        
        try {
            // Paso 1: Enviar el comprobante al SRI
            $respuesta_recepcion = $this->clienteSRI->enviarComprobante($ruta_firmado);
            $resultado_recepcion = $this->clienteSRI->procesarRespuestaRecepcion($respuesta_recepcion);
            
            // Si se recibió correctamente, consultamos la autorización
            if ($resultado_recepcion['estado'] === 'RECIBIDA') {
                // Esperamos unos segundos para dar tiempo al SRI a procesar
                sleep(3);
                
                // Paso 2: Consultar el estado de autorización
                $respuesta_autorizacion = $this->clienteSRI->consultarAutorizacion($clave_acceso);
                $resultado_autorizacion = $this->clienteSRI->procesarRespuestaAutorizacion($respuesta_autorizacion);
                
                // Verificamos el estado de autorización
                if (!empty($resultado_autorizacion['autorizaciones'])) {
                    $estado = $resultado_autorizacion['autorizaciones'][0]['estado'];
                    $this->resultados['estado'] = $estado;
                    
                    // Si está autorizado, guardamos el comprobante
                    if ($estado === 'AUTORIZADO') {
                        $ruta_autorizado = $this->clienteSRI->guardarComprobanteAutorizado(
                            $resultado_autorizacion, 
                            $clave_acceso
                        );
                        
                        $this->resultados['ruta_autorizado'] = $ruta_autorizado;
                        
                        // Cargar el XML autorizado
                        if (file_exists($ruta_autorizado)) {
                            $this->resultados['xml_autorizado'] = simplexml_load_file($ruta_autorizado);
                        }
                    }
                    
                    // Guardamos los mensajes
                    if (!empty($resultado_autorizacion['autorizaciones'][0]['mensajes'])) {
                        $this->resultados['mensajes'] = $resultado_autorizacion['autorizaciones'][0]['mensajes'];
                    }
                } else {
                    $this->resultados['estado'] = 'NO_AUTORIZADO';
                    $this->resultados['mensajes'][] = [
                        'identificador' => '0',
                        'mensaje' => 'No se recibió respuesta de autorización del SRI',
                        'tipo' => 'ERROR'
                    ];
                }
            } else {
                $this->resultados['estado'] = 'NO_RECIBIDO';
                
                // Guardamos los mensajes de recepción
                if (!empty($resultado_recepcion['comprobantes'])) {
                    foreach ($resultado_recepcion['comprobantes'] as $comprobante) {
                        if (!empty($comprobante['mensajes'])) {
                            foreach ($comprobante['mensajes'] as $mensaje) {
                                $this->resultados['mensajes'][] = $mensaje;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->resultados['estado'] = 'ERROR';
            $this->resultados['mensajes'][] = [
                'identificador' => '999',
                'mensaje' => 'Error en el proceso de autorización: ' . $e->getMessage(),
                'tipo' => 'ERROR'
            ];
        }
        
        return $this->resultados;
    }
    
    /**
     * Verifica el estado de autorización de un comprobante
     * 
     * @param string $clave_acceso Clave de acceso del comprobante
     * @return array Resultados de la consulta
     */
    public function verificarEstado($clave_acceso) {
        $this->resultados['clave_acceso'] = $clave_acceso;
        
        try {
            // Consultar el estado de autorización
            $respuesta_autorizacion = $this->clienteSRI->consultarAutorizacion($clave_acceso);
            $resultado_autorizacion = $this->clienteSRI->procesarRespuestaAutorizacion($respuesta_autorizacion);
            
            // Verificamos el estado de autorización
            if (!empty($resultado_autorizacion['autorizaciones'])) {
                $estado = $resultado_autorizacion['autorizaciones'][0]['estado'];
                $this->resultados['estado'] = $estado;
                
                // Guardamos los mensajes
                if (!empty($resultado_autorizacion['autorizaciones'][0]['mensajes'])) {
                    $this->resultados['mensajes'] = $resultado_autorizacion['autorizaciones'][0]['mensajes'];
                }
                
                // Si está autorizado, verificamos si ya tenemos el comprobante guardado
                if ($estado === 'AUTORIZADO') {
                    $ruta_autorizado = $this->config['rutas']['autorizados'] . $clave_acceso . '.xml';
                    
                    if (!file_exists($ruta_autorizado)) {
                        // No existe, lo guardamos
                        $ruta_autorizado = $this->clienteSRI->guardarComprobanteAutorizado(
                            $resultado_autorizacion, 
                            $clave_acceso
                        );
                    }
                    
                    $this->resultados['ruta_autorizado'] = $ruta_autorizado;
                    
                    // Cargar el XML autorizado
                    if (file_exists($ruta_autorizado)) {
                        $this->resultados['xml_autorizado'] = simplexml_load_file($ruta_autorizado);
                    }
                }
            } else {
                $this->resultados['estado'] = 'NO_ENCONTRADO';
                $this->resultados['mensajes'][] = [
                    'identificador' => '0',
                    'mensaje' => 'No se encontró el comprobante con la clave de acceso proporcionada',
                    'tipo' => 'ERROR'
                ];
            }
        } catch (\Exception $e) {
            $this->resultados['estado'] = 'ERROR';
            $this->resultados['mensajes'][] = [
                'identificador' => '999',
                'mensaje' => 'Error en la consulta de estado: ' . $e->getMessage(),
                'tipo' => 'ERROR'
            ];
        }
        
        return $this->resultados;
    }
    
    /**
     * Genera un RIDE para un comprobante autorizado
     * 
     * @param string $tipo_comprobante Tipo de comprobante (factura, nota-credito, etc.)
     * @param string $ruta_autorizado Ruta al archivo XML autorizado
     * @return string HTML del RIDE
     */
    public function generarRIDE($tipo_comprobante, $ruta_autorizado) {
        try {
            $ride = new \Util\RIDE($this->config);
            
            switch ($tipo_comprobante) {
                case 'factura':
                    return $ride->generarRIDEFactura($ruta_autorizado);
                
                case 'nota-credito':
                    return $ride->generarRIDENotaCredito($ruta_autorizado);
                
                default:
                    throw new \Exception("Tipo de comprobante no soportado para RIDE: $tipo_comprobante");
            }
        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error al generar RIDE: ' . $e->getMessage() . '</div>';
        }
    }
}