<?php
// Cargamos el autoloader
require_once __DIR__ . '/../facturacion-sri.php';

// Cargamos la configuración
$config = require_once __DIR__ . '/../config/config.php';

// Creamos las carpetas necesarias si no existen
$carpetas = [
    $config['rutas']['generados'],
    $config['rutas']['firmados'],
    $config['rutas']['enviados'],
    $config['rutas']['autorizados']
];

foreach ($carpetas as $carpeta) {
    if (!file_exists($carpeta)) {
        mkdir($carpeta, 0755, true);
    }
}

// Detectamos la acción a realizar
$accion = isset($_GET['accion']) ? $_GET['accion'] : 'inicio';

// Manejamos las acciones
switch ($accion) {
    case 'generar-factura':
        require_once __DIR__ . '/../templates/generar-factura.php';
        break;
        
    case 'generar-nota-credito':
        require_once __DIR__ . '/../templates/generar-nota-credito.php';
        break;
        
    case 'procesar-factura':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Creamos la factura
                $factura = new \Comprobantes\Factura($config);
                
                // Establecemos los datos básicos
                $cliente = [
                    'tipo_identificacion' => $_POST['tipo_identificacion'],
                    'razon_social' => $_POST['razon_social'],
                    'identificacion' => $_POST['identificacion'],
                    'direccion' => $_POST['direccion']
                ];
                
                $factura->setDatosBasicos($_POST['secuencial'], $_POST['fecha_emision'], $cliente);
                
                // Agregamos los ítems
                $subtotal = 0;
                $total_descuento = 0;
                $iva_12 = 0;
                
                for ($i = 0; $i < count($_POST['item_codigo']); $i++) {
                    $cantidad = floatval($_POST['item_cantidad'][$i]);
                    $precio_unitario = floatval($_POST['item_precio'][$i]);
                    $descuento = isset($_POST['item_descuento'][$i]) ? floatval($_POST['item_descuento'][$i]) : 0;
                    $precio_total = ($cantidad * $precio_unitario) - $descuento;
                    
                    $subtotal += $precio_total;
                    $total_descuento += $descuento;
                    
                    // Calculamos IVA 12%
                    $iva = $precio_total * 0.12;
                    $iva_12 += $iva;
                    
                    $item = [
                        'codigo' => $_POST['item_codigo'][$i],
                        'descripcion' => $_POST['item_descripcion'][$i],
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio_unitario,
                        'descuento' => $descuento,
                        'precio_total_sin_impuesto' => $precio_total,
                        'impuestos' => [
                            [
                                'codigo' => '2', // IVA
                                'codigo_porcentaje' => '2', // 12%
                                'tarifa' => '12.00',
                                'base_imponible' => $precio_total,
                                'valor' => $iva
                            ]
                        ]
                    ];
                    
                    $factura->agregarItem($item);
                }
                
                // Calculamos el importe total
                $importe_total = $subtotal + $iva_12;
                
                // Establecemos los totales
                $totales = [
                    'total_sin_impuestos' => $subtotal,
                    'total_descuento' => $total_descuento,
                    'importe_total' => $importe_total,
                    'propina' => 0,
                    'impuestos' => [
                        [
                            'codigo' => '2', // IVA
                            'codigo_porcentaje' => '2', // 12%
                            'base_imponible' => $subtotal,
                            'valor' => $iva_12
                        ]
                    ],
                    'pagos' => [
                        [
                            'forma_pago' => '01', // Sin utilización del sistema financiero
                            'total' => $importe_total
                        ]
                    ]
                ];
                
                $factura->setTotales($totales);
                
                // Agregamos información adicional
                $factura->agregarInfoAdicional('Email', $_POST['email']);
                $factura->agregarInfoAdicional('Teléfono', $_POST['telefono']);
                
                // Generamos el XML
                $factura->generarXML();
                
                // Guardamos el XML
                $ruta_xml = $factura->guardarXML();
                
                // Firmamos el XML
                $firmador = new \Firmador\FirmadorXML($config);
                $ruta_xml_firmado = $firmador->firmarXML($ruta_xml);
                
                // Enviamos el comprobante al SRI
                $cliente_sri = new \SRI\ClienteSRI($config);
                $respuesta_recepcion = $cliente_sri->enviarComprobante($ruta_xml_firmado);
                
                // Procesamos la respuesta de recepción
                $resultado_recepcion = $cliente_sri->procesarRespuestaRecepcion($respuesta_recepcion);
                
                // Si el estado es RECIBIDA, consultamos la autorización
                if ($resultado_recepcion['estado'] === 'RECIBIDA') {
                    // Esperamos un momento para que el SRI procese el comprobante
                    sleep(3);
                    
                    // Consultamos la autorización
                    $respuesta_autorizacion = $cliente_sri->consultarAutorizacion($factura->getClaveAcceso());
                    
                    // Procesamos la respuesta de autorización
                    $resultado_autorizacion = $cliente_sri->procesarRespuestaAutorizacion($respuesta_autorizacion);
                    
                    // Guardamos el comprobante autorizado si corresponde
                    if (!empty($resultado_autorizacion['autorizaciones']) && 
                        $resultado_autorizacion['autorizaciones'][0]['estado'] === 'AUTORIZADO') {
                        $ruta_autorizado = $cliente_sri->guardarComprobanteAutorizado($resultado_autorizacion, $factura->getClaveAcceso());
                        
                        // Generamos el RIDE
                        $ride = new \Util\RIDE($config);
                        $html_ride = $ride->generarRIDEFactura($ruta_autorizado);
                        
                        // Mostramos el resultado
                        echo "
                            <div class='alert alert-success'>
                                <h4>¡Factura generada y autorizada correctamente!</h4>
                                <p>Clave de Acceso: {$factura->getClaveAcceso()}</p>
                                <p>Estado: AUTORIZADO</p>
                                <h5>RIDE:</h5>
                                <div style='border: 1px solid #ccc; padding: 10px;'>
                                    $html_ride
                                </div>
                            </div>
                        ";
                    } else {
                        // Mostrar mensajes de error de autorización
                        echo "
                            <div class='alert alert-warning'>
                                <h4>Factura enviada pero no autorizada</h4>
                                <p>Clave de Acceso: {$factura->getClaveAcceso()}</p>
                                <p>Estado: " . (isset($resultado_autorizacion['autorizaciones'][0]['estado']) ? $resultado_autorizacion['autorizaciones'][0]['estado'] : 'DESCONOCIDO') . "</p>
                        ";
                        
                        if (!empty($resultado_autorizacion['autorizaciones'][0]['mensajes'])) {
                            echo "<h5>Mensajes:</h5><ul>";
                            foreach ($resultado_autorizacion['autorizaciones'][0]['mensajes'] as $mensaje) {
                                echo "<li>{$mensaje['identificador']}: {$mensaje['mensaje']}</li>";
                            }
                            echo "</ul>";
                        }
                        
                        echo "</div>";
                    }
                } else {
                    // Mostrar mensajes de error de recepción
                    echo "
                        <div class='alert alert-danger'>
                            <h4>Error al enviar la factura</h4>
                            <p>Estado: {$resultado_recepcion['estado']}</p>
                    ";
                    
                    if (!empty($resultado_recepcion['comprobantes'])) {
                        echo "<h5>Mensajes:</h5><ul>";
                        foreach ($resultado_recepcion['comprobantes'][0]['mensajes'] as $mensaje) {
                            echo "<li>{$mensaje['identificador']}: {$mensaje['mensaje']}</li>";
                        }
                        echo "</ul>";
                    }
                    
                    echo "</div>";
                }
            } catch (\Exception $e) {
                echo "
                    <div class='alert alert-danger'>
                        <h4>Error al procesar la factura</h4>
                        <p>{$e->getMessage()}</p>
                    </div>
                ";
            }
        }
        break;
        
    case 'procesar-nota-credito':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Creamos la nota de crédito
                $nota_credito = new \Comprobantes\NotaCredito($config);
                
                // Establecemos los datos básicos
                $cliente = [
                    'tipo_identificacion' => $_POST['tipo_identificacion'],
                    'razon_social' => $_POST['razon_social'],
                    'identificacion' => $_POST['identificacion']
                ];
                
                $documento_modificado = [
                    'tipo_doc' => $_POST['tipo_doc_modificado'],
                    'numero' => $_POST['num_doc_modificado'],
                    'fecha_emision' => $_POST['fecha_doc_modificado']
                ];
                
                $nota_credito->setDatosBasicos(
                    $_POST['secuencial'], 
                    $_POST['fecha_emision'], 
                    $cliente, 
                    $documento_modificado, 
                    $_POST['motivo']
                );
                
                // Agregamos los ítems
                $subtotal = 0;
                $iva_12 = 0;
                
                for ($i = 0; $i < count($_POST['item_codigo']); $i++) {
                    $cantidad = floatval($_POST['item_cantidad'][$i]);
                    $precio_unitario = floatval($_POST['item_precio'][$i]);
                    $descuento = isset($_POST['item_descuento'][$i]) ? floatval($_POST['item_descuento'][$i]) : 0;
                    $precio_total = ($cantidad * $precio_unitario) - $descuento;
                    
                    $subtotal += $precio_total;
                    
                    // Calculamos IVA 12%
                    $iva = $precio_total * 0.12;
                    $iva_12 += $iva;
                    
                    $item = [
                        'codigo' => $_POST['item_codigo'][$i],
                        'descripcion' => $_POST['item_descripcion'][$i],
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio_unitario,
                        'descuento' => $descuento,
                        'precio_total_sin_impuesto' => $precio_total,
                        'impuestos' => [
                            [
                                'codigo' => '2', // IVA
                                'codigo_porcentaje' => '2', // 12%
                                'tarifa' => '12.00',
                                'base_imponible' => $precio_total,
                                'valor' => $iva
                            ]
                        ]
                    ];
                    
                    $nota_credito->agregarItem($item);
                }
                
                // Calculamos el importe total
                $importe_total = $subtotal + $iva_12;
                
                // Establecemos los totales
                $totales = [
                    'total_sin_impuestos' => $subtotal,
                    'valor_modificacion' => $importe_total,
                    'impuestos' => [
                        [
                            'codigo' => '2', // IVA
                            'codigo_porcentaje' => '2', // 12%
                            'base_imponible' => $subtotal,
                            'valor' => $iva_12
                        ]
                    ]
                ];
                
                $nota_credito->setTotales($totales);
                
                // Agregamos información adicional
                $nota_credito->agregarInfoAdicional('Email', $_POST['email']);
                $nota_credito->agregarInfoAdicional('Teléfono', $_POST['telefono']);
                
                // Generamos el XML
                $nota_credito->generarXML();
                
                // Guardamos el XML
                $ruta_xml = $nota_credito->guardarXML();
                
                // Firmamos el XML
                $firmador = new \Firmador\FirmadorXML($config);
                $ruta_xml_firmado = $firmador->firmarXML($ruta_xml);
                
                // Enviamos el comprobante al SRI
                $cliente_sri = new \SRI\ClienteSRI($config);
                $respuesta_recepcion = $cliente_sri->enviarComprobante($ruta_xml_firmado);
                
                // Procesamos la respuesta de recepción
                $resultado_recepcion = $cliente_sri->procesarRespuestaRecepcion($respuesta_recepcion);
                
                // Si el estado es RECIBIDA, consultamos la autorización
                if ($resultado_recepcion['estado'] === 'RECIBIDA') {
                    // Esperamos un momento para que el SRI procese el comprobante
                    sleep(3);
                    
                    // Consultamos la autorización
                    $respuesta_autorizacion = $cliente_sri->consultarAutorizacion($nota_credito->getClaveAcceso());
                    
                    // Procesamos la respuesta de autorización
                    $resultado_autorizacion = $cliente_sri->procesarRespuestaAutorizacion($respuesta_autorizacion);
                    
                    // Guardamos el comprobante autorizado si corresponde
                    if (!empty($resultado_autorizacion['autorizaciones']) && 
                        $resultado_autorizacion['autorizaciones'][0]['estado'] === 'AUTORIZADO') {
                        $ruta_autorizado = $cliente_sri->guardarComprobanteAutorizado($resultado_autorizacion, $nota_credito->getClaveAcceso());
                        
                        // Generamos el RIDE
                        $ride = new \Util\RIDE($config);
                        $html_ride = $ride->generarRIDENotaCredito($ruta_autorizado);
                        
                        // Mostramos el resultado
                        echo "
                            <div class='alert alert-success'>
                                <h4>¡Nota de Crédito generada y autorizada correctamente!</h4>
                                <p>Clave de Acceso: {$nota_credito->getClaveAcceso()}</p>
                                <p>Estado: AUTORIZADO</p>
                                <h5>RIDE:</h5>
                                <div style='border: 1px solid #ccc; padding: 10px;'>
                                    $html_ride
                                </div>
                            </div>
                        ";
                    } else {
                        // Mostrar mensajes de error de autorización
                        echo "
                            <div class='alert alert-warning'>
                                <h4>Nota de Crédito enviada pero no autorizada</h4>
                                <p>Clave de Acceso: {$nota_credito->getClaveAcceso()}</p>
                                <p>Estado: " . (isset($resultado_autorizacion['autorizaciones'][0]['estado']) ? $resultado_autorizacion['autorizaciones'][0]['estado'] : 'DESCONOCIDO') . "</p>
                        ";
                        
                        if (!empty($resultado_autorizacion['autorizaciones'][0]['mensajes'])) {
                            echo "<h5>Mensajes:</h5><ul>";
                            foreach ($resultado_autorizacion['autorizaciones'][0]['mensajes'] as $mensaje) {
                                echo "<li>{$mensaje['identificador']}: {$mensaje['mensaje']}</li>";
                            }
                            echo "</ul>";
                        }
                        
                        echo "</div>";
                    }
                } else {
                    // Mostrar mensajes de error de recepción
                    echo "
                        <div class='alert alert-danger'>
                            <h4>Error al enviar la nota de crédito</h4>
                            <p>Estado: {$resultado_recepcion['estado']}</p>
                    ";
                    
                    if (!empty($resultado_recepcion['comprobantes'])) {
                        echo "<h5>Mensajes:</h5><ul>";
                        foreach ($resultado_recepcion['comprobantes'][0]['mensajes'] as $mensaje) {
                            echo "<li>{$mensaje['identificador']}: {$mensaje['mensaje']}</li>";
                        }
                        echo "</ul>";
                    }
                    
                    echo "</div>";
                }
            } catch (\Exception $e) {
                echo "
                    <div class='alert alert-danger'>
                        <h4>Error al procesar la nota de crédito</h4>
                        <p>{$e->getMessage()}</p>
                    </div>
                ";
            }
        }
        break;
        
    default:
        require_once __DIR__ . '/../templates/inicio.php';
        break;
}
