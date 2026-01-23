<?php

namespace Sequra\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Services\Bootstrap;

/**
 * Observer for service register
 *
 * Class ServiceRegisterObserver
 *
 */
class ServiceRegisterObserver implements ObserverInterface
{
    /**
     * @var Bootstrap
     */
    private Bootstrap $bootstrap;

    /**
     * Constructor
     *
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * Register all needed services on highest event
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->bootstrap->initInstance();
        } catch (LocalizedException $e) {
            Logger::logError('Failed to initialize SeQura bootstrap. Reason: ' . $e->getMessage());
        }
    }
}
