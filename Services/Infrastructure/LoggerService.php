<?php

namespace Sequra\Core\Services\Infrastructure;

use Sequra\Core\Model\Logger\DebugHandler;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use SeQura\Core\Infrastructure\Logger\LogData;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\Singleton;

class LoggerService extends Singleton implements ShopLoggerAdapter
{
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;
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
    private $clientLogger;

    /**
     * Debug handler for client log file.
     *
     * @param DebugHandler $clientLogger
     */
    public function __construct(DebugHandler $clientLogger)
    {
        parent::__construct();

        $this->clientLogger = $clientLogger;

        static::$instance = $this;
    }

    /**
     * Logs message in the system.
     *
     * @param LogData $data
     */
    public function logMessage(LogData $data): void
    {
        $logLevel = $data->getLogLevel();

        $message = 'SEQURA LOG:
            Date: ' . date('d/m/Y') . '
            Time: ' . date('H:i:s') . '
            Log level: ' . self::$logLevelName[$logLevel] . '
            Message: ' . $data->getMessage();
        $context = $data->getContext();
        if (!empty($context)) {
            $message .= '
            Context data: [';
            foreach ($context as $item) {
                // TODO: The use of function print_r() is discouraged
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                $message .= '"' . $item->getName() . '" => "' . print_r($item->getValue(), true) . '", ';
            }

            $message .= ']';
        }
        // TODO: The use of function call_user_func() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        \call_user_func([$this->clientLogger, self::$logLevelName[$logLevel]], $message); // @phpstan-ignore-line
    }
}
