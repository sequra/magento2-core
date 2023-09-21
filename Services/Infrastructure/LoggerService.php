<?php

namespace Sequra\Core\Services\Infrastructure;

use SeQura\Core\Infrastructure\Configuration\Configuration;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use SeQura\Core\Infrastructure\Logger\LogData;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\Singleton;
use Sequra\Core\Services\BusinessLogic\ConfigurationService;
use Psr\Log\LoggerInterface;

/**
 * Class LoggerService
 *
 * @package Sequra\Core\Services\Infrastructure
 */
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
     * @var array
     */
    private static $logLevelName = [
        Logger::ERROR => 'error',
        Logger::WARNING => 'warning',
        Logger::INFO => 'info',
        Logger::DEBUG => 'debug',
    ];
    /**
     * Magento logger interface.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Logger service constructor.
     *
     * @param LoggerInterface $logger Magento logger interface.
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();

        $this->logger = $logger;

        static::$instance = $this;
    }

    /**
     * Logs message in the system.
     *
     * @param LogData $data
     */
    public function logMessage(LogData $data)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $minLogLevel = $configService->getMinLogLevel();
        $logLevel = $data->getLogLevel();

        if (($logLevel > $minLogLevel) && !$configService->isDebugModeEnabled()) {
            return;
        }

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
                $message .= '"' . $item->getName() . '" => "' . print_r($item->getValue(), true) . '", ';
            }

            $message .= ']';
        }

        \call_user_func([$this->logger, self::$logLevelName[$logLevel]], $message);
    }
}
