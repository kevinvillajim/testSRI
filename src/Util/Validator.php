<?php

namespace Util;

/**
 * Clase para validación de datos
 */
class Validator
{
    /**
     * Valida un RUC ecuatoriano
     */
    public static function validarRUC($ruc)
    {
        // El RUC debe tener 13 dígitos
        if (!preg_match('/^\d{13}$/', $ruc)) {
            return false;
        }

        // El tercero dígito debe ser menor a 6 para personas naturales,
        // o 6 para sociedades públicas, o 9 para sociedades privadas
        $tercerDigito = (int) substr($ruc, 2, 1);
        if ($tercerDigito < 0 || $tercerDigito > 9) {
            return false;
        }

        // Validar los últimos 3 dígitos, deben ser 001
        $sucursal = substr($ruc, 10, 3);
        if ($sucursal != '001') {
            return false;
        }

        // Validar el dígito verificador para personas naturales o sociedades
        if ($tercerDigito < 6) {
            // Persona natural
            return self::validarDigitoVerificadorPN($ruc);
        } elseif ($tercerDigito == 6) {
            // Sociedad pública
            return self::validarDigitoVerificadorSP($ruc);
        } else {
            // Sociedad privada (incluye extranjeros)
            return self::validarDigitoVerificadorSP($ruc);
        }
    }

    /**
     * Valida una cédula ecuatoriana
     */
    public static function validarCedula($cedula)
    {
        // La cédula debe tener 10 dígitos
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }

        // El tercero dígito debe ser menor a 6
        $tercerDigito = (int) substr($cedula, 2, 1);
        if ($tercerDigito < 0 || $tercerDigito >= 6) {
            return false;
        }

        // Aplicar algoritmo módulo 10
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $digitoVerificador = (int) substr($cedula, 9, 1);
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $valor = (int) substr($cedula, $i, 1) * $coeficientes[$i];
            $suma += ($valor >= 10) ? $valor - 9 : $valor;
        }

        $digitoCalculado = ($suma % 10 === 0) ? 0 : 10 - ($suma % 10);

        return $digitoVerificador === $digitoCalculado;
    }

    /**
     * Valida una fecha en formato dd/mm/yyyy
     */
    public static function validarFecha($fecha)
    {
        if (!preg_match('/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[012])\/(20[0-9][0-9])$/', $fecha)) {
            return false;
        }

        list($dia, $mes, $anio) = explode('/', $fecha);
        return checkdate($mes, $dia, $anio);
    }

    /**
     * Valida un código de país (3 dígitos)
     */
    public static function validarCodigoPais($codigo)
    {
        return preg_match('/^[0-9]{3}$/', $codigo);
    }

    /**
     * Valida un código de incoterm
     */
    public static function validarIncoterm($incoterm)
    {
        // Debe ser solo letras mayúsculas
        return preg_match('/^[A-Z]{1,10}$/', $incoterm);
    }

    /**
     * Valida un código de forma de pago
     */
    public static function validarFormaPago($formaPago)
    {
        // Debe ser dos dígitos
        return preg_match('/^(0[1-9]|1[0-9]|2[0-1])$/', $formaPago);
    }

    /**
     * Valida un número de placa
     */
    public static function validarPlaca($placa)
    {
        return strlen($placa) <= 20;
    }

    /**
     * Valida el dígito verificador de un RUC para persona natural
     */
    private static function validarDigitoVerificadorPN($ruc)
    {
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $digitoVerificador = (int) substr($ruc, 9, 1);
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $valor = (int) substr($ruc, $i, 1) * $coeficientes[$i];
            $suma += ($valor >= 10) ? $valor - 9 : $valor;
        }

        $digitoCalculado = ($suma % 10 === 0) ? 0 : 10 - ($suma % 10);

        return $digitoVerificador === $digitoCalculado;
    }

    /**
     * Valida el dígito verificador de un RUC para sociedad pública o privada
     */
    private static function validarDigitoVerificadorSP($ruc)
    {
        $coeficientes = [3, 2, 7, 6, 5, 4, 3, 2];
        $digitoVerificador = (int) substr($ruc, 8, 1);
        $suma = 0;

        for ($i = 0; $i < 8; $i++) {
            $suma += (int) substr($ruc, $i, 1) * $coeficientes[$i];
        }

        $residuo = $suma % 11;
        $digitoCalculado = ($residuo === 0) ? 0 : 11 - $residuo;

        return $digitoVerificador === $digitoCalculado;
    }
}
