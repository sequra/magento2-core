<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use SeQura\Core\BusinessLogic\Domain\Migration\Tasks\DeploymentMigrateTask;
use SeQura\Core\Infrastructure\Logger\Logger;
use Throwable;

class Version280 implements DataPatchInterface
{
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
    public function apply()
    {
        try {
            (new DeploymentMigrateTask())->execute();

            Logger::logInfo('Migration ' . self::class . ' has been successfully finished.');
        } catch (Throwable $e) {
            Logger::logInfo('Update script V2.8.0.0 execution failed because: ' . $e->getMessage());
        }
    }
}
