<?php

/**
 * Configuración general del sistema
 */
return [
    'ambiente' => 1, // 1: Pruebas, 2: Producción
    'tipo_emision' => 1, // 1: Normal

    // Datos del emisor
    'emisor' => [
        'ruc' => '0000000000001',
        'razon_social' => 'EMPRESA DE PRUEBA',
        'nombre_comercial' => 'EMPRESA DEMO',
        'dir_matriz' => 'DIRECCION MATRIZ PRINCIPAL',
        'contribuyente_especial' => '123', // Número de resolución o vacío
        'obligado_contabilidad' => 'SI', // SI o NO
        'regimen_microempresas' => true, // CONTRIBUYENTE RÉGIMEN RIMPE
        'agente_retencion' => false, // Número de resolución o false
    ],

    // Establecimiento y punto de emisión
    'establecimiento' => [
        'codigo' => '001',
        'punto_emision' => '001',
        'dir_establecimiento' => 'DIRECCION DEL PUNTO DE VENTA',
    ],

    // URLs de los servicios web del SRI
    'sri' => [
        'pruebas' => [
            'recepcion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'autorizacion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
        'produccion' => [
            'recepcion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'autorizacion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ],

    // Rutas de almacenamiento
    'rutas' => [
        'certificado' => __DIR__ . '/../certificados/certificado.p12',
        'clave_certificado' => '123456',
        'generados' => __DIR__ . '/../comprobantes/generados/',
        'firmados' => __DIR__ . '/../comprobantes/firmados/',
        'enviados' => __DIR__ . '/../comprobantes/enviados/',
        'autorizados' => __DIR__ . '/../comprobantes/autorizados/',
        'no_autorizados' => __DIR__ . '/../comprobantes/no_autorizados/',
        'rechazados' => __DIR__ . '/../comprobantes/rechazados/',
    ],

    // Configuración de la firma
    'firma' => [
        'tiempo_validez' => '+5 days', // Tiempo de validez de la firma
    ],

    // Versiones de documentos
    'versiones' => [
        'factura' => '2.1.0',
        'nota_credito' => '1.1.0',
        'nota_debito' => '1.0.0',
        'guia_remision' => '1.0.0',
        'comprobante_retencion' => '1.0.0',
        'liquidacion_compra' => '1.0.0'
    ]
];
