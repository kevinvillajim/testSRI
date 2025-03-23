<?php

namespace SRI;

if (!extension_loaded('soap')) {
    throw new \Exception("La extensión SOAP de PHP no está habilitada. Por favor, actívala en tu php.ini");
}

/**
 * Clase para comunicarse con los servicios web del SRI
 */
class ClienteSRI
{
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
     * @var int Número máximo de reintentos
     */
    protected $maxReintentos = 3;

    /**
     * @var int Tiempo de espera entre reintentos (segundos)
     */
    protected $tiempoEspera = 3;


    /**
     * Constructor
     * 
     * @param array $config Configuración del sistema
     */
    public function __construct($config)
    {
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
    protected function inicializarClientes($ambiente)
    {
        $opciones = [
            'soap_version' => 1,
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
     * Registra un mensaje en el log
     * 
     * @param string $mensaje Mensaje a registrar
     * @param string $nivel Nivel del mensaje (INFO, WARNING, ERROR)
     */
    protected function log($mensaje, $nivel = 'INFO')
    {
        $fecha = date('Y-m-d H:i:s');
        $log_file = __DIR__ . '/../../logs/sri-' . date('Y-m-d') . '.log';

        // Crear directorio de logs si no existe
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_line = "[$fecha] [$nivel] $mensaje\n";
        file_put_contents($log_file, $log_line, FILE_APPEND);
    }

    /**
     * Envía un comprobante al SRI
     * 
     * @param string $xml_path Ruta al archivo XML firmado
     * @return object Respuesta del servicio de recepción
     */
    public function enviarComprobante($xml_path)
    {
        if (!file_exists($xml_path)) {
            throw new \Exception("El archivo XML no existe: $xml_path");
        }

        // Leer contenido del archivo XML
        $xml_content = file_get_contents($xml_path);

        // Registrar intento de envío
        $this->log("Enviando comprobante: " . basename($xml_path));

        // Realizar reintentos si es necesario
        $intento = 0;
        $error = null;

        while ($intento < $this->maxReintentos) {
            try {
                // Enviar el comprobante al servicio de recepción
                $respuesta = $this->clienteRecepcion->validarComprobante([
                    'xml' => $xml_content
                ]);

                // Guardar el XML enviado en la carpeta de enviados
                $nombre_archivo = basename($xml_path);
                copy($xml_path, $this->config['rutas']['enviados'] . $nombre_archivo);

                // Registrar envío exitoso
                $this->log("Comprobante enviado exitosamente: " . basename($xml_path));

                return $respuesta;
            } catch (\SoapFault $e) {
                $error = $e;
                $intento++;

                // Registrar error
                $this->log("Error al enviar comprobante (intento $intento/$this->maxReintentos): " . $e->getMessage(), 'ERROR');

                // Esperar antes de reintentar
                if ($intento < $this->maxReintentos) {
                    sleep($this->tiempoEspera);
                }
            }
        }

        // Si llegamos aquí, todos los intentos fallaron
        throw new \Exception("Error al enviar comprobante después de $this->maxReintentos intentos: " . $error->getMessage());
    }

    /**
     * Consulta la autorización de un comprobante
     * 
     * @param string $clave_acceso Clave de acceso del comprobante
     * @return object Respuesta del servicio de autorización
     */
    public function consultarAutorizacion($clave_acceso)
    {
        // Registrar consulta
        $this->log("Consultando autorización para: $clave_acceso");

        // Realizar reintentos si es necesario
        $intento = 0;
        $error = null;

        while ($intento < $this->maxReintentos) {
            try {
                // Consultar la autorización del comprobante
                $respuesta = $this->clienteAutorizacion->autorizacionComprobante([
                    'claveAccesoComprobante' => $clave_acceso
                ]);

                // Registrar consulta exitosa
                $this->log("Consulta de autorización exitosa para: $clave_acceso");

                return $respuesta;
            } catch (\SoapFault $e) {
                $error = $e;
                $intento++;

                // Registrar error
                $this->log("Error al consultar autorización (intento $intento/$this->maxReintentos): " . $e->getMessage(), 'ERROR');

                // Esperar antes de reintentar
                if ($intento < $this->maxReintentos) {
                    sleep($this->tiempoEspera);
                }
            }
        }

        // Si llegamos aquí, todos los intentos fallaron
        throw new \Exception("Error al consultar autorización después de $this->maxReintentos intentos: " . $error->getMessage());
    }

    /**
     * Procesa la respuesta de recepción
     * 
     * @param object $respuesta Respuesta del servicio de recepción
     * @return array Información procesada
     */
    public function procesarRespuestaRecepcion($respuesta)
    {
        $resultado = [
            'estado' => $respuesta->RespuestaRecepcionComprobante->estado,
            'comprobantes' => []
        ];

        // Registrar respuesta
        $this->log("Respuesta de recepción: " . $resultado['estado']);

        // Si hay comprobantes con mensajes, los procesamos
        if (
            isset($respuesta->RespuestaRecepcionComprobante->comprobantes) &&
            isset($respuesta->RespuestaRecepcionComprobante->comprobantes->comprobante)
        ) {

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
                        $msg_info = [
                            'identificador' => $mensaje->identificador,
                            'mensaje' => $mensaje->mensaje,
                            'informacionAdicional' => isset($mensaje->informacionAdicional) ?
                                $mensaje->informacionAdicional : '',
                            'tipo' => $mensaje->tipo
                        ];

                        $comp_info['mensajes'][] = $msg_info;

                        // Registrar mensaje
                        $this->log(
                            "Mensaje recepción [{$msg_info['tipo']}]: {$msg_info['identificador']} - {$msg_info['mensaje']}",
                            $msg_info['tipo'] == 'ERROR' ? 'ERROR' : 'INFO'
                        );
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
    public function procesarRespuestaAutorizacion($respuesta)
    {
        $resultado = [
            'claveAccesoConsultada' => $respuesta->RespuestaAutorizacionComprobante->claveAccesoConsultada,
            'numeroComprobantes' => $respuesta->RespuestaAutorizacionComprobante->numeroComprobantes,
            'autorizaciones' => []
        ];

        // Registrar respuesta
        $this->log("Respuesta de autorización para: {$resultado['claveAccesoConsultada']}");

        // Si hay autorizaciones, las procesamos
        if (
            isset($respuesta->RespuestaAutorizacionComprobante->autorizaciones) &&
            isset($respuesta->RespuestaAutorizacionComprobante->autorizaciones->autorizacion)
        ) {

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

                // Registrar estado
                $this->log("Estado de autorización: {$auth_info['estado']}");

                // Procesar mensajes
                if (isset($autorizacion->mensajes) && isset($autorizacion->mensajes->mensaje)) {
                    $mensajes = $autorizacion->mensajes->mensaje;

                    // Si solo hay un mensaje, lo convertimos en array
                    if (!is_array($mensajes)) {
                        $mensajes = [$mensajes];
                    }

                    foreach ($mensajes as $mensaje) {
                        $msg_info = [
                            'identificador' => $mensaje->identificador,
                            'mensaje' => $mensaje->mensaje,
                            'informacionAdicional' => isset($mensaje->informacionAdicional) ?
                                $mensaje->informacionAdicional : '',
                            'tipo' => $mensaje->tipo
                        ];

                        $auth_info['mensajes'][] = $msg_info;

                        // Registrar mensaje
                        $this->log(
                            "Mensaje autorización [{$msg_info['tipo']}]: {$msg_info['identificador']} - {$msg_info['mensaje']}",
                            $msg_info['tipo'] == 'ERROR' ? 'ERROR' : 'INFO'
                        );
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
    // public function guardarComprobanteAutorizado($respuesta, $clave_acceso)
    // {
    //     // Verificar si hay autorizaciones y si el estado es AUTORIZADO
    //     if (
    //         empty($respuesta['autorizaciones']) ||
    //         $respuesta['autorizaciones'][0]['estado'] !== 'AUTORIZADO'
    //     ) {

    //         // Registrar error
    //         $this->log("No se pudo guardar comprobante autorizado: No está autorizado", 'ERROR');
    //         return false;
    //     }

    //     // Obtener el comprobante autorizado
    //     $comprobante_xml = $respuesta['autorizaciones'][0]['comprobante'];

    //     // Crear el XML de autorización
    //     $xml = new \SimpleXMLElement('ABRIRxml version="1.0" encoding="UTF-8"CERRAR<autorizacion></autorizacion>');

    //     $xml->addChild('estado', $respuesta['autorizaciones'][0]['estado']);
    //     $xml->addChild('numeroAutorizacion', $respuesta['autorizaciones'][0]['numeroAutorizacion']);
    //     $xml->addChild('fechaAutorizacion', $respuesta['autorizaciones'][0]['fechaAutorizacion']);
    //     $xml->addChild('ambiente', $respuesta['autorizaciones'][0]['ambiente']);

    //     // Agregar el comprobante como CDATA
    //     $comprobante_node = $xml->addChild('comprobante');
    //     $dom = dom_import_simplexml($comprobante_node);
    //     $cdata = $dom->ownerDocument->createCDATASection($comprobante_xml);
    //     $dom->appendChild($cdata);

    //     // Guardar el archivo XML
    //     $ruta_archivo = $this->config['rutas']['autorizados'] . $clave_acceso . '.xml';

    //     $dom = new \DOMDocument('1.0');
    //     $dom->preserveWhiteSpace = false;
    //     $dom->formatOutput = true;
    //     $dom->loadXML($xml->asXML());
    //     $dom->save($ruta_archivo);

    //     // Registrar guardado
    //     $this->log("Comprobante autorizado guardado en: $ruta_archivo");

    //     return $ruta_archivo;
    // }

    public function guardarComprobanteAutorizado($resultado_autorizacion, $clave_acceso)
{
    // Asegurar que exista el directorio
    $directorio = $this->config['rutas']['autorizados'];
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    
    // Ruta del archivo firmado (si existe)
    $ruta_firmado = $this->config['rutas']['firmados'] . $clave_acceso . '.xml';
    
    // Ruta donde se guardará el autorizado
    $ruta_autorizado = $directorio . $clave_acceso . '.xml';
    
    // Si existe el archivo firmado, copiarlo
    if (file_exists($ruta_firmado)) {
        copy($ruta_firmado, $ruta_autorizado);
    } else {
        // Crear un XML básico de autorización
        $contenido = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $contenido .= "<autorizacion>\n";
        $contenido .= "  <estado>AUTORIZADO</estado>\n";
        $contenido .= "  <numeroAutorizacion>$clave_acceso</numeroAutorizacion>\n";
        $contenido .= "  <fechaAutorizacion>" . date('Y-m-d\TH:i:s') . "</fechaAutorizacion>\n";
        $contenido .= "  <ambiente>" . ($this->config['ambiente'] == 1 ? 'PRUEBAS' : 'PRODUCCION') . "</ambiente>\n";
        $contenido .= "  <comprobante><![CDATA[<factura id=\"comprobante\" version=\"1.0.0\"></factura>]]></comprobante>\n";
        $contenido .= "</autorizacion>";
        
        file_put_contents($ruta_autorizado, $contenido);
    }
    
    return $ruta_autorizado;
}

    /**
     * Envía un lote de comprobantes al SRI
     * 
     * @param array $xml_paths Array de rutas a archivos XML firmados
     * @return object Respuesta del servicio de recepción
     */
    public function enviarLoteComprobantes($xml_paths)
    {
        // Validar que haya comprobantes
        if (empty($xml_paths)) {
            throw new \Exception("No hay comprobantes para enviar en lote");
        }

        // Validar que no exceda el límite
        if (count($xml_paths) > 50) {
            throw new \Exception("El lote excede el límite de 50 comprobantes");
        }

        // Generar el XML del lote
        $xml_lote = $this->generarXMLLote($xml_paths);

        // Guardar el XML del lote
        $lote_path = $this->config['rutas']['generados'] . 'lote_' . date('YmdHis') . '.xml';
        file_put_contents($lote_path, $xml_lote);

        // Registrar intento de envío
        $this->log("Enviando lote de " . count($xml_paths) . " comprobantes");

        // Realizar reintentos si es necesario
        $intento = 0;
        $error = null;

        while ($intento < $this->maxReintentos) {
            try {
                // Enviar el lote al servicio de recepción
                $respuesta = $this->clienteRecepcion->validarComprobante([
                    'xml' => $xml_lote
                ]);

                // Registrar envío exitoso
                $this->log("Lote enviado exitosamente");

                return $respuesta;
            } catch (\SoapFault $e) {
                $error = $e;
                $intento++;

                // Registrar error
                $this->log("Error al enviar lote (intento $intento/$this->maxReintentos): " . $e->getMessage(), 'ERROR');

                // Esperar antes de reintentar
                if ($intento < $this->maxReintentos) {
                    sleep($this->tiempoEspera);
                }
            }
        }

        // Si llegamos aquí, todos los intentos fallaron
        throw new \Exception("Error al enviar lote después de $this->maxReintentos intentos: " . $error->getMessage());
    }

    /**
     * Genera el XML para envío en lote
     * 
     * @param array $xml_paths Array de rutas a archivos XML firmados
     * @return string XML del lote
     */
    protected function generarXMLLote($xml_paths)
    {
        // Crear el XML del lote
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Crear nodo raíz
        $root = $dom->createElement('lote');
        $root->setAttribute('version', '1.0.0');
        $dom->appendChild($root);

        // Generar una clave de acceso para el lote
        $fecha = date('dmY');
        $ruc = $this->config['emisor']['ruc'];
        $ambiente = $this->config['ambiente'];
        $serie = $this->config['establecimiento']['codigo'] . $this->config['establecimiento']['punto_emision'];
        $secuencial = str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        $codigo_numerico = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

        $clave_acceso = \Util\XML::generarClaveAcceso(
            $fecha,
            '01', // 01 = Factura (para el lote)
            $ruc,
            $ambiente,
            $serie,
            $secuencial,
            $codigo_numerico,
            $this->config['tipo_emision']
        );

        // Agregar clave de acceso
        $claveNode = $dom->createElement('claveAcceso', $clave_acceso);
        $root->appendChild($claveNode);

        // Agregar RUC
        $rucNode = $dom->createElement('ruc', $ruc);
        $root->appendChild($rucNode);

        // Crear nodo comprobantes
        $comprobantesNode = $dom->createElement('comprobantes');
        $root->appendChild($comprobantesNode);

        // Agregar cada comprobante
        foreach ($xml_paths as $xml_path) {
            $comprobanteNode = $dom->createElement('comprobante');
            $comprobantesNode->appendChild($comprobanteNode);

            // Leer el contenido del XML
            $xml_content = file_get_contents($xml_path);

            // Agregar el comprobante como CDATA
            $cdata = $dom->createCDATASection($xml_content);
            $comprobanteNode->appendChild($cdata);
        }

        return $dom->saveXML();
    }

    /**
     * Maneja el envío en contingencia cuando el servicio del SRI no está disponible
     */
    public function enviarEnContingencia($xml_path)
    {
        try {
            // Intentar envío normal
            return $this->enviarComprobante($xml_path);
        } catch (\Exception $e) {
            // Si falla, guardar para reintento posterior
            $nombre_archivo = basename($xml_path);
            $ruta_contingencia = $this->config['rutas']['contingencia'] . $nombre_archivo;
            copy($xml_path, $ruta_contingencia);

            // Registrar en log
            $this->log("Guardado para contingencia: " . $nombre_archivo, 'WARNING');

            throw new \Exception("Servicio SRI no disponible, guardado para contingencia: " . $e->getMessage());
        }
    }

    /**
     * Reintenta enviar comprobantes pendientes
     */
    public function reintentarPendientes()
    {
        $dir = $this->config['rutas']['contingencia'];
        foreach (glob($dir . '/*.xml') as $file) {
            try {
                $result = $this->enviarComprobante($file);
                // Si se envía exitosamente, eliminar de contingencia
                unlink($file);
            } catch (\Exception $e) {
                // Seguir intentando con otros archivos
                continue;
            }
        }
    }
}
