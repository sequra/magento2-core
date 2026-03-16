<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use SeQura\Core\BusinessLogic\Domain\Migration\Tasks\StoreIntegrationMigrateTask;
use SeQura\Core\Infrastructure\Logger\Logger;
use Throwable;

class Version400 implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
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

        try {
            (new StoreIntegrationMigrateTask())->execute();

            Logger::logInfo('Migration ' . self::class . ' has been successfully finished.');
        } catch (Throwable $e) {
            Logger::logError('Update script V4.0.0 execution failed because: ' . $e->getMessage());
        }

        $this->moduleDataSetup->endSetup();
    }
}
