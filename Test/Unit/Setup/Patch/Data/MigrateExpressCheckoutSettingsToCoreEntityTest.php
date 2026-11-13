<?php

namespace Sequra\Core\Test\Unit\Setup\Patch\Data;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Entities\ExpressCheckoutSettings;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPageConfig;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use Sequra\Core\Services\Bootstrap;
use Sequra\Core\Setup\DatabaseHandler;
use Sequra\Core\Setup\Patch\Data\MigrateExpressCheckoutSettingsToCoreEntity;

class MigrateExpressCheckoutSettingsToCoreEntityTest extends TestCase
{
    private const STORE_ID = 'par980-migration-test';
    private const URL = 'https://shop.example.com/terms';
    private const MODULE_ENTITY_CLASS =
        'Sequra\Core\Services\BusinessLogic\ExpressCheckout\Entities\ExpressCheckoutSettings';

    /**
     * @var int[]
     */
    private array $insertedIds = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        /** @var Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->insertedIds !== []) {
            $this->connection()->delete($this->table(), ['id IN (?)' => $this->insertedIds]);
        }
    }

    /**
     * @return void
     */
    public function testARowStoredByTheModuleEntityReadsBackAsTheCoreEntityWithItsUrl(): void
    {
        $this->insertRow(self::MODULE_ENTITY_CLASS);

        $this->patch()->apply();

        $entity = $this->storedEntity();
        $this->assertSame(ExpressCheckoutSettings::class, get_class($entity));
        $settings = $entity->getExpressCheckoutSettings();
        $this->assertSame(self::URL, $settings->getTermsAndConditionsUrl());
        $this->assertSame(
            [['page' => 'product', 'enabled' => true], ['page' => 'cart', 'enabled' => true]],
            array_map(static function (ExpressCheckoutPageConfig $config) {
                return $config->toArray();
            }, $settings->getExpressCheckoutConfigs())
        );
    }

    /**
     * @return void
     */
    public function testLeavesARowStoredByTheCoreEntityUntouched(): void
    {
        $id = $this->insertRow(ExpressCheckoutSettings::class);
        $before = $this->rawData($id);

        $this->patch()->apply();

        $this->assertSame($before, $this->rawData($id));
    }

    /**
     * @return void
     */
    public function testApplyingItAgainChangesNothing(): void
    {
        $id = $this->insertRow(self::MODULE_ENTITY_CLASS);
        $this->patch()->apply();
        $migrated = $this->rawData($id);

        $this->patch()->apply();

        $this->assertSame($migrated, $this->rawData($id));
    }

    /**
     * @param string $className
     *
     * @return int
     */
    private function insertRow(string $className): int
    {
        $data = [
            'class_name' => $className,
            'storeId' => self::STORE_ID,
            'expressCheckoutSettings' => [
                'expressCheckoutConfigs' => [
                    ['page' => 'product', 'enabled' => true],
                    ['page' => 'cart', 'enabled' => true],
                ],
                'buttonStyle' => null,
                'termsAndConditionsUrl' => self::URL,
            ],
        ];
        $connection = $this->connection();
        $connection->insert($this->table(), [
            'type' => 'ExpressCheckoutSettings',
            'index_1' => self::STORE_ID,
            'data' => json_encode($data),
        ]);
        $id = (int)$connection->lastInsertId($this->table());
        $connection->update(
            $this->table(),
            ['data' => json_encode(['class_name' => $className, 'id' => $id] + $data)],
            ['id = ?' => $id]
        );
        $this->insertedIds[] = $id;

        return $id;
    }

    /**
     * @return ExpressCheckoutSettings
     */
    private function storedEntity(): ExpressCheckoutSettings
    {
        $entity = RepositoryRegistry::getRepository(ExpressCheckoutSettings::getClassName())->selectOne(
            (new QueryFilter())->where('storeId', Operators::EQUALS, self::STORE_ID)
        );
        $this->assertInstanceOf(ExpressCheckoutSettings::class, $entity);

        return $entity;
    }

    /**
     * @param int $id
     *
     * @return string
     */
    private function rawData(int $id): string
    {
        $connection = $this->connection();

        return (string)$connection->fetchOne(
            $connection->select()->from($this->table(), ['data'])->where('id = ?', $id)
        );
    }

    /**
     * @return MigrateExpressCheckoutSettingsToCoreEntity
     */
    private function patch(): MigrateExpressCheckoutSettingsToCoreEntity
    {
        $setup = $this->createStub(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection());
        $setup->method('getTable')->willReturnCallback(function (string $table) {
            return $this->resource()->getTableName($table);
        });

        return new MigrateExpressCheckoutSettingsToCoreEntity($setup);
    }

    /**
     * @return string
     */
    private function table(): string
    {
        return $this->resource()->getTableName(DatabaseHandler::SEQURA_ENTITY_TABLE);
    }

    /**
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resource()->getConnection();
    }

    /**
     * @return ResourceConnection
     */
    private function resource(): ResourceConnection
    {
        /** @var ResourceConnection $resource */
        $resource = ObjectManager::getInstance()->get(ResourceConnection::class);

        return $resource;
    }
}
