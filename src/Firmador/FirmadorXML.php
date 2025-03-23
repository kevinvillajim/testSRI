<?php

namespace Firmador;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecEnc;

/**
 * Clase para firmar documentos XML bajo el estándar XAdES-BES
 */
class FirmadorXML
{
    /**
     * @var string Ruta al certificado P12
     */
    protected $certificado;

    /**
     * @var string Clave del certificado
     */
    protected $clave;

    /**
     * @var array Configuración del sistema
     */
    protected $config;

    /**
     * @var array Información del certificado
     */
    protected $certInfo;

    /**
     * Constructor
     * 
     * @param array $config Configuración del sistema
     */
    public function __construct($config)
    {
        $this->certificado = $config['rutas']['certificado'];
        $this->clave = $config['rutas']['clave_certificado'];
        $this->config = $config;

        // Verificar que el certificado exista
        if (!file_exists($this->certificado)) {
            throw new \Exception("Certificado no encontrado: {$this->certificado}");
        }
    }

    /**
     * Firma un documento XML
     * 
     * @param string $xml_path Ruta al archivo XML a firmar
     * @param string $output_path Ruta donde guardar el XML firmado (opcional)
     * @return string|bool Ruta al archivo firmado o false en caso de error
     */
    // public function firmarXML($xml_path, $output_path = null)
    // {
    //     if (!file_exists($xml_path)) {
    //         throw new \Exception("El archivo XML no existe: $xml_path");
    //     }

    //     // Si no se especificó ruta de salida, generamos una
    //     if ($output_path === null) {
    //         $output_path = $this->config['rutas']['firmados'] . basename($xml_path);
    //     }

    //     try {
    //         // Cargar el XML
    //         $dom = new \DOMDocument('1.0', 'UTF-8');
    //         $dom->preserveWhiteSpace = false;
    //         $dom->formatOutput = true;
    //         $dom->load($xml_path);

    //         // Obtener el nodo raíz
    //         $root = $dom->documentElement;

    //         // Crear y configurar XMLSecurityDSig
    //         $objDSig = new XMLSecurityDSig();
    //         $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    //         $objDSig->addReference(
    //             $dom,
    //             XMLSecurityDSig::SHA1,
    //             ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
    //             ['force_uri' => true]
    //         );

    //         // Obtener información del certificado
    //         $this->obtenerInfoCertificado();

    //         // Crear nodo Signature
    //         $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
    //         $objKey->loadKey($this->certInfo['pkey']);

    //         // Firmar el documento
    //         $objDSig->sign($objKey);

    //         // Agregar certificado al XML
    //         $objDSig->add509Cert($this->certInfo['cert']);

    //         // Añadir las propiedades XAdES
    //         $this->agregarPropiedadesXAdES($dom, $objDSig);

    //         // Añadir Signature al documento
    //         $objDSig->appendSignature($root);

    //         // Guardar el documento firmado
    //         $dom->save($output_path);

    //         return $output_path;
    //     } catch (\Exception $e) {
    //         throw new \Exception("Error al firmar el XML: " . $e->getMessage());
    //     }
    // }

    public function firmarXML($xml_path, $output_path = null)
    {
        if (!file_exists($xml_path)) {
            throw new \Exception("El archivo XML no existe: $xml_path");
        }

        // Si no se especificó ruta de salida, generamos una
        if ($output_path === null) {
            $output_path = $this->config['rutas']['firmados'] . basename($xml_path);
        }

        try {
            // Leer el contenido del XML
            $xml_content = file_get_contents($xml_path);

            // Validar que el contenido no esté vacío
            if (empty($xml_content)) {
                throw new \Exception("El archivo XML está vacío");
            }

            // Crear un nuevo documento DOM
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;

            // Cargar el XML desde el contenido
            if (!$doc->loadXML($xml_content)) {
                throw new \Exception("Error al cargar el XML: documento XML inválido");
            }

            // Crear un objeto para firmar
            $signer = new XMLSecurityDSig();

            // Agregar la referencia
            $signer->addReference(
                $doc,
                XMLSecurityDSig::SHA1,
                ['http://www.w3.org/2000/09/xmldsig#enveloped-signature']
            );

            // Crear una clave privada
            $privateKey = new XMLSecurityKey(
                XMLSecurityKey::RSA_SHA1,
                ['type' => 'private']
            );

            // Cargar la clave desde el certificado
            $pkcs12 = file_get_contents($this->certificado);
            $certs = [];

            // Extraer la clave y certificado
            if (!openssl_pkcs12_read($pkcs12, $certs, $this->clave)) {
                throw new \Exception("No se pudo leer el certificado PKCS12. Verifique la clave.");
            }

            // Cargar la clave privada
            $privateKey->loadKey($certs['pkey']);

            // Firmar el documento
            $signer->sign($privateKey);

            // Agregar el certificado
            $signer->add509Cert($certs['cert']);

            // Agregar la firma al documento
            $signer->appendSignature($doc->documentElement);

            // Guardar el documento firmado
            $doc->save($output_path);

            return $output_path;
        } catch (\Exception $e) {
            throw new \Exception("Error al firmar el XML: " . $e->getMessage());
        }
    }

    /**
     * Obtiene información del certificado
     */
    private function obtenerInfoCertificado()
    {
        // Leer el certificado PKCS12
        $pkcs12 = file_get_contents($this->certificado);

        // Extraer los certificados y la clave privada
        if (!openssl_pkcs12_read($pkcs12, $certs, $this->clave)) {
            throw new \Exception("No se pudo leer el certificado PKCS12. Verifique la clave.");
        }

        // Guardar información del certificado
        $this->certInfo = [
            'cert' => $certs['cert'],
            'pkey' => $certs['pkey'],
            'extracerts' => isset($certs['extracerts']) ? $certs['extracerts'] : null
        ];

        // Verificar la fecha de validez del certificado
        $cert_data = openssl_x509_parse($this->certInfo['cert']);

        if ($cert_data['validTo_time_t'] < time()) {
            throw new \Exception("El certificado ha expirado. Renovar el certificado digital.");
        }

        if (time() < $cert_data['validFrom_time_t']) {
            throw new \Exception("El certificado aún no es válido. Verificar la fecha de inicio de validez.");
        }
    }

    /**
     * Agrega las propiedades XAdES al documento
     * 
     * @param \DOMDocument $dom Documento XML
     * @param XMLSecurityDSig $objDSig Objeto de firma
     */
    private function agregarPropiedadesXAdES(\DOMDocument $dom, XMLSecurityDSig $objDSig)
    {
        // Obtener el nodo Signature
        $signatureNode = $objDSig->sigNode;

        // Obtener el ID del nodo Signature
        $signatureId = $signatureNode->getAttribute('Id');
        if (empty($signatureId)) {
            $signatureId = 'Signature-' . uniqid();
            $signatureNode->setAttribute('Id', $signatureId);
        }

        // Crear el nodo Object para las propiedades XAdES
        $objectNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Object');
        $signatureNode->appendChild($objectNode);

        // Crear el nodo QualifyingProperties
        $qualifyingPropertiesNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:QualifyingProperties');
        $qualifyingPropertiesNode->setAttribute('Target', '#' . $signatureId);
        $objectNode->appendChild($qualifyingPropertiesNode);

        // Crear el nodo SignedProperties
        $signedPropertiesId = $signatureId . '-SignedProperties';
        $signedPropertiesNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:SignedProperties');
        $signedPropertiesNode->setAttribute('Id', $signedPropertiesId);
        $qualifyingPropertiesNode->appendChild($signedPropertiesNode);

        // Crear el nodo SignedSignatureProperties
        $signedSignaturePropertiesNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:SignedSignatureProperties');
        $signedPropertiesNode->appendChild($signedSignaturePropertiesNode);

        // Agregar el nodo SigningTime
        $signingTimeNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:SigningTime', date('c'));
        $signedSignaturePropertiesNode->appendChild($signingTimeNode);

        // Agregar el nodo SigningCertificate
        $signingCertificateNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:SigningCertificate');
        $signedSignaturePropertiesNode->appendChild($signingCertificateNode);

        // Agregar el nodo Cert
        $certNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:Cert');
        $signingCertificateNode->appendChild($certNode);

        // Agregar el nodo CertDigest
        $certDigestNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:CertDigest');
        $certNode->appendChild($certDigestNode);

        // Agregar el nodo DigestMethod
        $digestMethodNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethodNode->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $certDigestNode->appendChild($digestMethodNode);

        // Calcular el digest del certificado
        $certDigest = base64_encode(openssl_x509_fingerprint($this->certInfo['cert'], 'sha1', true));

        // Agregar el nodo DigestValue
        $digestValueNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue', $certDigest);
        $certDigestNode->appendChild($digestValueNode);

        // Agregar el nodo IssuerSerial
        $issuerSerialNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:IssuerSerial');
        $certNode->appendChild($issuerSerialNode);

        // Obtener información del certificado
        $certData = openssl_x509_parse($this->certInfo['cert']);

        // Formar el nombre del emisor
        $issuerName = '';
        if (is_array($certData['issuer'])) {
            $parts = array();
            foreach ($certData['issuer'] as $key => $value) {
                array_unshift($parts, "$key=$value");
            }
            $issuerName = implode(',', $parts);
        } else {
            $issuerName = $certData['issuer'];
        }

        // Agregar el nodo X509IssuerName
        $x509IssuerNameNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509IssuerName', $issuerName);
        $issuerSerialNode->appendChild($x509IssuerNameNode);

        // Agregar el nodo X509SerialNumber
        $x509SerialNumberNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509SerialNumber', $certData['serialNumber']);
        $issuerSerialNode->appendChild($x509SerialNumberNode);

        // Agregar el nodo SignedDataObjectProperties
        $signedDataObjectPropertiesNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:SignedDataObjectProperties');
        $signedPropertiesNode->appendChild($signedDataObjectPropertiesNode);

        // Agregar el nodo DataObjectFormat
        $dataObjectFormatNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:DataObjectFormat');
        $dataObjectFormatNode->setAttribute('ObjectReference', '#Reference-ID-1');
        $signedDataObjectPropertiesNode->appendChild($dataObjectFormatNode);

        // Agregar el nodo Description
        $descriptionNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:Description', 'contenido comprobante');
        $dataObjectFormatNode->appendChild($descriptionNode);

        // Agregar el nodo MimeType
        $mimeTypeNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:MimeType', 'text/xml');
        $dataObjectFormatNode->appendChild($mimeTypeNode);

        // Añadir una referencia a SignedProperties
        $refNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Reference');
        $refNode->setAttribute('Id', 'SignedPropertiesID');
        $refNode->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $refNode->setAttribute('URI', '#' . $signedPropertiesId);

        // Agregar SignedInfo a la referencia
        $signedInfoNode = $signatureNode->getElementsByTagName('SignedInfo')->item(0);
        $signedInfoNode->appendChild($refNode);

        // Agregar DigestMethod a la referencia
        $digestMethodNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethodNode->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $refNode->appendChild($digestMethodNode);

        // Calcular el digest de las propiedades firmadas
        $canon = $signedPropertiesNode->C14N(true, false);
        $signedPropertiesDigest = base64_encode(hash('sha1', $canon, true));

        // Agregar DigestValue a la referencia
        $digestValueNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue', $signedPropertiesDigest);
        $refNode->appendChild($digestValueNode);
    }
}
