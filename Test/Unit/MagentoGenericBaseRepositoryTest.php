<?php

namespace Sequra\Core\Test\Unit;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Setup\Module\Setup;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Sequra\Core\Services\Bootstrap;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use SeQura\Core\Infrastructure\ORM\QueryFilter\Operators;
use SeQura\Core\Infrastructure\ORM\QueryFilter\QueryFilter;
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
     * Deletes the entities whose ids are in the list.
     *
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    public function testDeleteWhereByIdIn()
    {
        $ids = [$this->saveStudent('ana'), $this->saveStudent('bob'), $this->saveStudent('eve')];
        $repository = RepositoryRegistry::getRepository(StudentEntity::getClassName());

        $repository->deleteWhere((new QueryFilter())->where('id', Operators::IN, [$ids[0], $ids[2]]));

        $this->assertSame(['bob'], $this->remainingUsernames());
    }

    /**
     * Deletes the entities matching an indexed column that is NULL or equal to a value.
     *
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    public function testDeleteWhereByIndexedColumnNullOrEqual()
    {
        $this->saveStudent('ana', null);
        $this->saveStudent('bob', '');
        $this->saveStudent('eve', 'eve@example.com');
        $repository = RepositoryRegistry::getRepository(StudentEntity::getClassName());

        $repository->deleteWhere(
            (new QueryFilter())->where('email', Operators::NULL)->orWhere('email', Operators::EQUALS, '')
        );

        $this->assertSame(['eve'], $this->remainingUsernames());
    }

    /**
     * Deletes only as many entities as the limit allows, in the order of the filter.
     *
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    public function testDeleteWhereRespectsTheLimit()
    {
        $this->saveStudent('ana');
        $this->saveStudent('bob');
        $this->saveStudent('eve');
        $repository = RepositoryRegistry::getRepository(StudentEntity::getClassName());

        $repository->deleteWhere((new QueryFilter())->orderBy('id')->setLimit(2));

        $this->assertSame(['eve'], $this->remainingUsernames());
    }

    /**
     * Without a filter every entity of the repository's type is deleted, and nothing of another type.
     *
     * @return void
     *
     * @throws RepositoryNotRegisteredException
     */
    public function testDeleteWhereWithoutFilterDeletesOnlyTheEntityType()
    {
        $this->saveStudent('ana');
        $this->saveStudent('bob');
        $connection = $this->getConnection();
        $connection->insert($connection->getTableName(TestRepository::TABLE_NAME), ['type' => 'Other', 'data' => '{}']);
        $repository = RepositoryRegistry::getRepository(StudentEntity::getClassName());

        $repository->deleteWhere();

        $this->assertSame([], $this->remainingUsernames());
        $this->assertSame(1, $this->countRowsOfType('Other'));
    }

    /**
     * A condition on a column that is not indexed is refused.
     *
     * @return void
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    public function testDeleteWhereOnANotIndexedColumnThrows()
    {
        $repository = RepositoryRegistry::getRepository(StudentEntity::getClassName());

        $this->expectException(QueryFilterInvalidParamException::class);

        $repository->deleteWhere((new QueryFilter())->where('addresses', Operators::EQUALS, 'x'));
    }

    /**
     * Saves a student with the given username and email.
     *
     * @param string $username
     * @param string $email
     *
     * @return int
     *
     * @throws RepositoryNotRegisteredException
     */
    private function saveStudent($username, $email = 'student@example.com')
    {
        $student = new StudentEntity();
        $student->localId = 1;
        $student->username = $username;
        $student->email = $email;
        $student->gender = 'F';
        $student->firstName = $username;
        $student->lastName = $username;

        return (int)RepositoryRegistry::getRepository(StudentEntity::getClassName())->save($student);
    }

    /**
     * Returns the usernames of the students still stored, in id order.
     *
     * @return string[]
     *
     * @throws QueryFilterInvalidParamException
     * @throws RepositoryNotRegisteredException
     */
    private function remainingUsernames()
    {
        $students = RepositoryRegistry::getRepository(StudentEntity::getClassName())
            ->select((new QueryFilter())->orderBy('id'));

        return array_map(static function (StudentEntity $student) {
            return $student->username;
        }, $students);
    }

    /**
     * Counts the rows of the given type in the test table.
     *
     * @param string $type
     *
     * @return int
     */
    private function countRowsOfType($type)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName(TestRepository::TABLE_NAME), 'COUNT(*)')
            ->where('type = ?', $type);

        return (int)$connection->fetchOne($select);
    }

    /**
     * Returns the database connection.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function getConnection()
    {
        /** @var ResourceConnection $resource */
        $resource = ObjectManager::getInstance()->get(ResourceConnection::class);

        return $resource->getConnection();
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
