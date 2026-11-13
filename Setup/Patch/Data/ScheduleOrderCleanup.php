<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Sequra\Core\Services\Bootstrap;
use Sequra\Core\Services\BusinessLogic\OrderCleanup\OrderCleanupScheduler;

/**
 * Schedules the first nightly cleanup of stale solicitations. An existing schedule is kept.
 */
class ScheduleOrderCleanup implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var OrderCleanupScheduler
     */
    private $scheduler;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param OrderCleanupScheduler $scheduler
     * @param Bootstrap $bootstrap
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        OrderCleanupScheduler $scheduler,
        Bootstrap $bootstrap
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->scheduler = $scheduler;

        $bootstrap->initInstance();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [Initializer::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();
        $this->scheduler->ensureScheduled();
        $this->moduleDataSetup->endSetup();
    }
}
