<?php

namespace Sequra\Core\Test\Unit\Repository;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Setup\Module\Setup;
use PHPUnit\Framework\TestCase;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Infrastructure\TaskExecution\Interfaces\Priority;
use SeQura\Core\Infrastructure\TaskExecution\QueueItem;
use Sequra\Core\Services\Bootstrap;
use Sequra\Core\Setup\DatabaseHandler;

class QueueItemRepositoryTest extends TestCase
{
    private const CORE_51_ORDER_REPORTER = 'SeQura\\Core\\BusinessLogic\\Domain\\OrderReport\\OrderReporter';

    /**
     * @return void
     *
     * @throws \Zend_Db_Exception
     */
    protected function setUp(): void
    {
        /** @var Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();

        /** @var Setup $setup */
        $setup = ObjectManager::getInstance()->create(Setup::class);
        $installer = $setup->startSetup();
        (new DatabaseHandler($installer))->createEntityTable(TestQueueItemRepository::TABLE_NAME);
        $installer->endSetup();

        RepositoryRegistry::registerRepository(QueueItem::CLASS_NAME, TestQueueItemRepository::getClassName());
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        /** @var Setup $setup */
        $setup = ObjectManager::getInstance()->create(Setup::class);
        $installer = $setup->startSetup();
        (new DatabaseHandler($installer))->dropEntityTable(TestQueueItemRepository::TABLE_NAME);
        $installer->endSetup();
    }

    /**
     * @return void
     */
    public function testReadsAQueuedCore51OrderReporterAsAnOrderReporterItem(): void
    {
        $this->insertRawQueueRow((string) json_encode([
            'class_name' => self::CORE_51_ORDER_REPORTER,
            'page' => 1,
            'storeId' => '1',
        ]));

        /** @var QueueItem[] $items */
        $items = RepositoryRegistry::getQueueItemRepository()->select();

        $this->assertCount(1, $items);
        $this->assertSame('OrderReporter', $items[0]->getTaskType());
    }

    /**
     * @param string $serializedTask
     *
     * @return void
     */
    private function insertRawQueueRow(string $serializedTask): void
    {
        $item = new QueueItem();
        $item->setStatus(QueueItem::QUEUED);
        $item->setContext('1');
        $item->setQueueName('legacy-queue');
        $item->setPriority(Priority::NORMAL);
        $item->setSerializedTask($serializedTask);
        $data = $item->toArray();

        /** @var ResourceConnection $resource */
        $resource = ObjectManager::getInstance()->get(ResourceConnection::class);
        $resource->getConnection()->insert(
            $resource->getTableName(TestQueueItemRepository::TABLE_NAME),
            [
                'type' => 'QueueItem',
                'index_1' => QueueItem::QUEUED,
                'index_7' => (string) time(),
                'data' => (string) json_encode($data),
            ]
        );
    }
}
