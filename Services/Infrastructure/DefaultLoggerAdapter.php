<?php

namespace Sequra\Core\Services\Infrastructure;

use SeQura\Core\Infrastructure\Logger\Interfaces\DefaultLoggerAdapter as DefaultLoggerAdapterInterface;
use SeQura\Core\Infrastructure\Logger\LogData;
use Sequra\Core\Model\Logger\LogFile;

class DefaultLoggerAdapter implements DefaultLoggerAdapterInterface
{
    /**
     * Debug log file of the store in context.
     *
     * @var LogFile
     */
    private LogFile $logFile;

    /**
     * @param LogFile $logFile
     */
    public function __construct(LogFile $logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * Logs message in the system.
     *
     * @param LogData $data
     *
     * @return void
     */
    public function logMessage(LogData $data): void
    {
        $this->logFile->append($data->formatLogMessage());
    }
}
