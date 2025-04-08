<?php

namespace Sequra\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
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
    private $bootstrap;

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
        $this->bootstrap->initInstance();
    }
}
