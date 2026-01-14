<?php

namespace Sequra\Core\Observer;

use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
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
     * @var State
     */
    private $state;

    /**
     * Constructor
     *
     * @param Bootstrap $bootstrap
     * @param State $state
     */
    public function __construct(Bootstrap $bootstrap, State $state)
    {
        $this->bootstrap = $bootstrap;
        $this->state = $state;
    }

    /**
     * Register all needed services on highest event
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->state->getAreaCode();
            $this->bootstrap->initInstance();
        } catch (LocalizedException $e) {
            // Area code not set (e.g., during setup:di:compile or setup:upgrade)
            // Skip bootstrap initialization - it will be initialized later when area is set
        }
    }
}
