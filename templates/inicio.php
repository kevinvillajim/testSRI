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

                // Datos de comercio exterior (v2.1.0)
                if (!empty($_POST['comercio_exterior'])) {
                    $comercioExterior = [
                        'comercioExterior' => $_POST['comercio_exterior'],
                        'incoTermFactura' => $_POST['incoterm_factura'],
                        'lugarIncoTerm' => $_POST['lugar_incoterm'],
                        'paisOrigen' => $_POST['pais_origen'],
                        'paisDestino' => $_POST['pais_destino'],
                        'puertoEmbarque' => $_POST['puerto_embarque'],
                        'puertoDestino' => $_POST['puerto_destino'],
                        'paisAdquisicion' => $_POST['pais_adquisicion'] ?? ''
                    ];
                    $factura->setComercioExterior($comercioExterior);
                }

                // Datos de reembolso (v2.1.0)
                if (!empty($_POST['cod_doc_reembolso'])) {
                    $reembolso = [
                        'codDocReembolso' => $_POST['cod_doc_reembolso'],
                        'totalComprobantesReembolso' => $_POST['total_comprobantes_reembolso'],
                        'totalBaseImponibleReembolso' => $_POST['total_base_imponible_reembolso'],
                        'totalImpuestoReembolso' => $_POST['total_impuesto_reembolso']
                    ];
                    $factura->setReembolsos($reembolso);
                }

                // Agregamos los ítems
                $subtotal = 0;
                $total_descuento = 0;
                $iva_12 = 0;
                $total_subsidio = 0;

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

                    // Datos adicionales para v2.1.0
                    $unidadMedida = isset($_POST['item_unidad_medida'][$i]) ? $_POST['item_unidad_medida'][$i] : '';
                    $precioSinSubsidio = isset($_POST['item_precio_sin_subsidio'][$i]) ? floatval($_POST['item_precio_sin_subsidio'][$i]) : 0;

                    if ($precioSinSubsidio > $precio_unitario) {
                        $subsidio = ($precioSinSubsidio - $precio_unitario) * $cantidad;
                        $total_subsidio += $subsidio;
                    }

                    $item = [
                        'codigo' => $_POST['item_codigo'][$i],
                        'descripcion' => $_POST['item_descripcion'][$i],
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio_unitario,
                        'descuento' => $descuento,
                        'precio_total_sin_impuesto' => $precio_total,
                        'unidadMedida' => $unidadMedida,
                        'precioSinSubsidio' => $precioSinSubsidio > 0 ? $precioSinSubsidio : $precio_unitario,
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

                // Datos para compensaciones (v2.1.0)
                $compensaciones = [];
                if (isset($_POST['comp_codigo']) && is_array($_POST['comp_codigo'])) {
                    for ($i = 0; $i < count($_POST['comp_codigo']); $i++) {
                        $compensaciones[] = [
                            'codigo' => $_POST['comp_codigo'][$i],
                            'tarifa' => $_POST['comp_tarifa'][$i],
                            'valor' => $_POST['comp_valor'][$i]
                        ];
                    }
                }

                // Establecemos los totales
                $totales = [
                    'total_sin_impuestos' => $subtotal,
                    'total_descuento' => $total_descuento,
                    'importe_total' => $importe_total,
                    'propina' => 0,
                    'total_subsidio' => $total_subsidio,
                    'impuestos' => [
                        [
                            'codigo' => '2', // IVA
                            'codigo_porcentaje' => '2', // 12%
                            'base_imponible' => $subtotal,
                            'valor' => $iva_12
                        ]
                    ],
                    'compensaciones' => $compensaciones,
                    'pagos' => [
                        [
                            'forma_pago' => '01', // Sin utilización del sistema financiero
                            'total' => $importe_total
                        ]
                    ]
                ];

                // Campos de comercio exterior
                if (!empty($_POST['incoterm_total_sin_impuestos'])) {
                    $totales['incoTermTotalSinImpuestos'] = $_POST['incoterm_total_sin_impuestos'];
                }

                if (!empty($_POST['flete_internacional'])) {
                    $totales['fleteInternacional'] = floatval($_POST['flete_internacional']);
                }

                if (!empty($_POST['seguro_internacional'])) {
                    $totales['seguroInternacional'] = floatval($_POST['seguro_internacional']);
                }

                if (!empty($_POST['gastos_aduaneros'])) {
                    $totales['gastosAduaneros'] = floatval($_POST['gastos_aduaneros']);
                }

                if (!empty($_POST['gastos_transporte_otros'])) {
                    $totales['gastosTransporteOtros'] = floatval($_POST['gastos_transporte_otros']);
                }

                // Campos de retención
                if (!empty($_POST['valor_ret_iva'])) {
                    $totales['valorRetIva'] = floatval($_POST['valor_ret_iva']);
                }

                if (!empty($_POST['valor_ret_renta'])) {
                    $totales['valorRetRenta'] = floatval($_POST['valor_ret_renta']);
                }

                $factura->setTotales($totales);

                // Agregar retenciones
                if (isset($_POST['ret_codigo']) && is_array($_POST['ret_codigo'])) {
                    for ($i = 0; $i < count($_POST['ret_codigo']); $i++) {
                        $retencion = [
                            'codigo' => $_POST['ret_codigo'][$i],
                            'codigoPorcentaje' => $_POST['ret_codigo_porcentaje'][$i],
                            'tarifa' => $_POST['ret_tarifa'][$i],
                            'valor' => $_POST['ret_valor'][$i]
                        ];
                        $factura->agregarRetencion($retencion);
                    }
                }

                // Máquina fiscal
                if (!empty($_POST['mf_marca'])) {
                    $factura->setMaquinaFiscal([
                        'marca' => $_POST['mf_marca'],
                        'modelo' => $_POST['mf_modelo'],
                        'serie' => $_POST['mf_serie']
                    ]);
                }

                // Información adicional
                $factura->agregarInfoAdicional('Email', $_POST['email']);
                $factura->agregarInfoAdicional('Teléfono', $_POST['telefono']);

                // Generamos el XML
                $factura->generarXML();

                // Guardamos el XML
                $ruta_xml = $factura->guardarXML();

                // Firmamos el XML
                $firmador = new \Firmador\FirmadorXML($config);
                $ruta_xml_firmado = $firmador->firmarXML($ruta_xml);

                // Resto del código para enviar al SRI...

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
                    'identificacion' => $_POST['identificacion'],
                    'rise' => $_POST['rise'] ?? '' // Nuevo en v1.1.0
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

                // Compensaciones (v1.1.0)
                $compensaciones = [];
                if (isset($_POST['comp_codigo']) && is_array($_POST['comp_codigo'])) {
                    for ($i = 0; $i < count($_POST['comp_codigo']); $i++) {
                        $compensaciones[] = [
                            'codigo' => $_POST['comp_codigo'][$i],
                            'tarifa' => $_POST['comp_tarifa'][$i],
                            'valor' => $_POST['comp_valor'][$i]
                        ];
                    }
                    $nota_credito->setCompensaciones($compensaciones);
                }

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
                            'valor' => $iva_12,
                            'valorDevolucionIva' => isset($_POST['valor_devolucion_iva']) ? floatval($_POST['valor_devolucion_iva']) : 0
                        ]
                    ]
                ];

                $nota_credito->setTotales($totales);

                // Máquina fiscal (v1.1.0)
                if (!empty($_POST['mf_marca'])) {
                    $nota_credito->setMaquinaFiscal([
                        'marca' => $_POST['mf_marca'],
                        'modelo' => $_POST['mf_modelo'],
                        'serie' => $_POST['mf_serie']
                    ]);
                }

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

                // Resto del código para enviar al SRI...

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
