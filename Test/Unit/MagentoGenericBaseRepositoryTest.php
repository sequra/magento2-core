<?php

namespace Sequra\Core\Test\Unit;

use Magento\Framework\App\ObjectManager;
use Magento\Setup\Module\Setup;
use Sequra\Core\Services\Bootstrap;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Tests\Infrastructure\Common\TestComponents\ORM\Entity\StudentEntity;
use SeQura\Core\Tests\Infrastructure\ORM\AbstractGenericStudentRepositoryTest;
use Sequra\Core\Setup\DatabaseHandler;
use Sequra\Core\Test\Unit\Repository\TestRepository;

class MagentoGenericBaseRepositoryTest extends AbstractGenericStudentRepositoryTest
{
    /**
     * @return string
     */
    public function getStudentEntityRepositoryClass()
    {
        return TestRepository::getClassName();
    }

    /**
     * @inheritdoc
     *
     * @throws \Zend_Db_Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        /** @var Setup $setup */
        $setup = ObjectManager::getInstance()->create(Setup::class);
        /** @var \Sequra\Core\Services\Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->createEntityTable(TestRepository::TABLE_NAME);

        $installer->endSetup();

        RepositoryRegistry::registerRepository(StudentEntity::CLASS_NAME, TestRepository::getClassName());
    }

    /**
     * Cleans up all storage services used by repositories
     */
    public function cleanUpStorage()
    {
        /** @var Setup $setup */
        $setup = ObjectManager::getInstance()->create(Setup::class);
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->dropEntityTable(TestRepository::TABLE_NAME);

        $installer->endSetup();
    }
}
