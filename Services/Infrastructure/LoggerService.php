<?php

namespace Sequra\Core\Services\Infrastructure;

use Sequra\Core\Model\Logger\DebugHandler;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use SeQura\Core\Infrastructure\Logger\LogData;
use SeQura\Core\Infrastructure\Logger\Logger;

class LoggerService implements ShopLoggerAdapter
{
    /**
     * Log level names for corresponding log level codes.
     *
     * @var array<string>
     */
    private static $logLevelName = [
        Logger::ERROR => 'error',
        Logger::WARNING => 'warning',
        Logger::INFO => 'info',
        Logger::DEBUG => 'debug',
    ];
    /**
     *  Debug handler for client log file.
     *
     * @var DebugHandler
     */
    private DebugHandler $clientLogger;

    /**
     * Debug handler for client log file.
     *
     * @param DebugHandler $clientLogger
     */
    public function __construct(DebugHandler $clientLogger)
    {
        $this->clientLogger = $clientLogger;
    }

    /**
     * Logs message in the system.
     *
     * @param LogData $data
     */
    public function logMessage(LogData $data): void
    {
        // TODO: The use of function call_user_func() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        \call_user_func([$this->clientLogger, self::$logLevelName[$data->getLogLevel()]], $data->formatLogMessage()); //
        // @phpstan-ignore-line
    }
}
