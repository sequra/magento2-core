<?php
namespace Sequra\Core\Test\Unit;

use Magento\Framework\App\ObjectManager;
use Magento\Setup\Module\Setup;
use Sequra\Core\Services\Bootstrap;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Infrastructure\TaskExecution\QueueItem;
use SeQura\Core\Tests\Infrastructure\ORM\AbstractGenericQueueItemRepositoryTest;
use Sequra\Core\Setup\DatabaseHandler;
use Sequra\Core\Test\Unit\Repository\TestQueueItemRepository;

class MagentoGenericQueueItemRepositoryTest extends AbstractGenericQueueItemRepositoryTest
{
    /**
     * @inheritdoc
     */
    public static function tearDownAfterClass(): void
    {
        $setup = ObjectManager::getInstance()->create(Setup::class);
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->dropEntityTable(TestQueueItemRepository::TABLE_NAME);

        $installer->endSetup();
    }

    /**
     * @return string
     */
    public function getQueueItemEntityRepositoryClass()
    {
        return TestQueueItemRepository::getClassName();
    }

    /**
     * @inheritdoc
     *
     * @throws \Zend_Db_Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $setup = ObjectManager::getInstance()->create(Setup::class);
        /** @var \Sequra\Core\Services\Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->createEntityTable(TestQueueItemRepository::TABLE_NAME);

        $installer->endSetup();

        RepositoryRegistry::registerRepository(QueueItem::CLASS_NAME, TestQueueItemRepository::getClassName());
    }

    /**
     * Cleans up all storage services used by repositories
     */
    public function cleanUpStorage()
    {
        self::tearDownAfterClass();
    }
}
