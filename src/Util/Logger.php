<?php

namespace Util;

/**
 * Clase para manejo de logs del sistema
 */
class Logger
{
    /**
     * @var string Ruta base de los logs
     */
    protected $logDir;

    /**
     * @var string Nivel mínimo de log
     */
    protected $minLevel;

    /**
     * @var array Mapeo de niveles de log a valores numéricos
     */
    protected $levelMap = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];

    /**
     * Constructor
     * 
     * @param string $logDir Directorio de logs
     * @param string $minLevel Nivel mínimo de log (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     */
    public function __construct($logDir = null, $minLevel = 'INFO')
    {
        $this->logDir = $logDir ?: __DIR__ . '/../../logs';
        $this->minLevel = strtoupper($minLevel);

        // Crear directorio de logs si no existe
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    /**
     * Registra un mensaje en el log
     * 
     * @param string $message Mensaje a registrar
     * @param string $level Nivel del mensaje (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     * @param string $context Contexto del mensaje
     * @return bool True si se registró el mensaje, false en caso contrario
     */
    public function log($message, $level = 'INFO', $context = 'general')
    {
        $level = strtoupper($level);

        // Verificar si el nivel es válido
        if (!isset($this->levelMap[$level])) {
            return false;
        }

        // Verificar si el nivel es suficiente
        if ($this->levelMap[$level] < $this->levelMap[$this->minLevel]) {
            return false;
        }

        // Formatear mensaje
        $datetime = date('Y-m-d H:i:s');
        $formattedMessage = "[$datetime] [$level] [$context] $message\n";

        // Determinar nombre de archivo
        $logFile = $this->logDir . '/' . date('Y-m-d') . '-' . $context . '.log';

        // Guardar mensaje
        return (bool) file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    /**
     * Registra un mensaje de debug
     * 
     * @param string $message Mensaje
     * @param string $context Contexto
     * @return bool Resultado
     */
    public function debug($message, $context = 'general')
    {
        return $this->log($message, 'DEBUG', $context);
    }

    /**
     * Registra un mensaje de información
     * 
     * @param string $message Mensaje
     * @param string $context Contexto
     * @return bool Resultado
     */
    public function info($message, $context = 'general')
    {
        return $this->log($message, 'INFO', $context);
    }

    /**
     * Registra un mensaje de advertencia
     * 
     * @param string $message Mensaje
     * @param string $context Contexto
     * @return bool Resultado
     */
    public function warning($message, $context = 'general')
    {
        return $this->log($message, 'WARNING', $context);
    }

    /**
     * Registra un mensaje de error
     * 
     * @param string $message Mensaje
     * @param string $context Contexto
     * @return bool Resultado
     */
    public function error($message, $context = 'general')
    {
        return $this->log($message, 'ERROR', $context);
    }

    /**
     * Registra un mensaje crítico
     * 
     * @param string $message Mensaje
     * @param string $context Contexto
     * @return bool Resultado
     */
    public function critical($message, $context = 'general')
    {
        return $this->log($message, 'CRITICAL', $context);
    }
}
