<?php

namespace ApironeApi;

class LoggerWrapper {
    static $loggerInstance;

    static $debugMode;

    public static function setLogger($logger, $debug = false)
    {
        if (is_object($logger) && method_exists($logger, 'log')) {
            self::$loggerInstance = $logger;
            self::$debugMode = $debug;
        } 
        else {
            throw new \InvalidArgumentException('Invalid logger');
        }
    }

    public static function debug($message)
    {
        if (self::$debugMode) {
            self::log('debug', $message, ['source' => 'mccp_debug']);
        }
    }

    public static function error($message)
    {
        self::log('error', $message, ['source' => 'mccp_debug']);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function log($level, $message, array $context = array())
    {
        if (self::$loggerInstance !== null) {
            self::$loggerInstance->log($level, $message, $context);
        }
    }
}