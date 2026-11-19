<?php

namespace Sequra\Core\Model\Webhook;

use Magento\Framework\Lock\LockManagerInterface;

class OrderWebhookLock
{
    public const WAIT_SECONDS = 10;

    private const NAME_PREFIX = 'sequra_order_webhook_';

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * Builds the lock over Magento's lock manager.
     *
     * @param LockManagerInterface $lockManager
     */
    public function __construct(LockManagerInterface $lockManager)
    {
        $this->lockManager = $lockManager;
    }

    /**
     * Runs the process while no other webhook for the same SeQura order runs.
     *
     * @param string $orderReference
     * @param callable $process
     * @phpstan-param callable(): void $process
     *
     * @return bool False when the lock was not free within self::WAIT_SECONDS and the process did not run.
     */
    public function runExclusively(string $orderReference, callable $process): bool
    {
        $name = self::NAME_PREFIX . $orderReference;
        if (!$this->lockManager->lock($name, self::WAIT_SECONDS)) {
            return false;
        }

        try {
            $process();
        } finally {
            $this->lockManager->unlock($name);
        }

        return true;
    }
}
