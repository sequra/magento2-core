<?php

namespace Sequra\Core\Services\Infrastructure;

use SeQura\Core\Infrastructure\Logger\Interfaces\DefaultLoggerAdapter as DefaultLoggerAdapterInterface;
use Sequra\Core\Model\Logger\DebugHandler;
use SeQura\Core\Infrastructure\Logger\LogData;
use SeQura\Core\Infrastructure\Logger\Logger;

class DefaultLoggerAdapter implements DefaultLoggerAdapterInterface
{
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
        $message = $data->formatLogMessage();

        switch ($data->getLogLevel()) {
            case Logger::DEBUG:
                $this->clientLogger->debug($message);
                break;
            case Logger::WARNING:
                $this->clientLogger->warning($message);
                break;
            case Logger::ERROR:
                $this->clientLogger->error($message);
                break;
            default:
                $this->clientLogger->info($message);
                break;
        }
    }
}
