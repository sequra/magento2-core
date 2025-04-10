<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\TaskExecution\Exceptions\TaskRunnerStatusStorageUnavailableException;

class Version256 implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * Returns array of dependencies.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Returns array of aliases.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Apply migration.
     *
     * @return void
     */
    public function apply()
    {
        try {
            $this->moduleDataSetup->getConnection()->startSetup();

            // Delete rows where type is 'PaymentMethods'
            $this->moduleDataSetup->getConnection()->delete(
                $this->moduleDataSetup->getTable('sequra_entity'),
                ['type = ?' => 'PaymentMethods']
            );

            $this->moduleDataSetup->getConnection()->endSetup();
        } catch (TaskRunnerStatusStorageUnavailableException $e) {
            Logger::logInfo('Update script V2.6.0.0 execution failed because: ' . $e->getMessage());
        }
    }
}
