#!/bin/bash
# Script para generar un certificado de prueba .p12

# Crear directorio para archivos temporales
mkdir -p temp_cert

# Generar clave privada RSA
openssl genrsa -out temp_cert/private.key 2048

# Crear una solicitud de certificado (CSR)
openssl req -new -key temp_cert/private.key -out temp_cert/request.csr -subj "//C=EC//ST=Pichincha//L=Quito//O=Empresa de Prueba//OU=IT//CN=test.ejemplo.com//emailAddress=test@ejemplo.com"

# Generar un certificado X509 auto-firmado (válido por 365 días)
openssl x509 -req -days 365 -in temp_cert/request.csr -signkey temp_cert/private.key -out temp_cert/certificate.crt

# Convertir la clave privada y el certificado a formato PKCS#12 (.p12)
openssl pkcs12 -export -out certificados/certificado.p12 -inkey temp_cert/private.key -in temp_cert/certificate.crt -name "Certificado de Prueba" -passout pass:123456

# Limpiar archivos temporales
rm -rf temp_cert

echo "Certificado de prueba generado en certificados/certificado.p12"
echo "Clave del certificado: 123456"