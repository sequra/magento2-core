<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Entities\ExpressCheckoutSettings;
use Sequra\Core\Setup\DatabaseHandler;

class MigrateExpressCheckoutSettingsToCoreEntity implements DataPatchInterface
{
    private const MODULE_ENTITY_CLASS =
        // phpcs:ignore Magento2.PHP.LiteralNamespaces.LiteralClassUsage
        'Sequra\Core\Services\BusinessLogic\ExpressCheckout\Entities\ExpressCheckoutSettings';

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

        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable(DatabaseHandler::SEQURA_ENTITY_TABLE);
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['id', 'data'])
                ->where('type = ?', (new ExpressCheckoutSettings())->getConfig()->getType())
        );

        foreach ($rows as $id => $data) {
            $entity = json_decode((string)$data, true);
            if (!is_array($entity) || ($entity['class_name'] ?? null) !== self::MODULE_ENTITY_CLASS) {
                continue;
            }

            $entity['class_name'] = ExpressCheckoutSettings::getClassName();
            $connection->update(
                $table,
                ['data' => json_encode($entity, JSON_THROW_ON_ERROR)],
                ['id = ?' => $id]
            );
        }

        $this->moduleDataSetup->endSetup();
    }
}
