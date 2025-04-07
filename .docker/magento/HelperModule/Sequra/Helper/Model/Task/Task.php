<?php
/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Phrase;
use Sequra\Core\Setup\DatabaseHandler;

class Task
{

    /**
     * Resource connection
     */
    protected $conn;
    protected $dbHandler;

    /**
     * Constructor
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->conn = $resourceConnection;
        $this->dbHandler = new DatabaseHandler($resourceConnection);
    }

    /**
     * Execute the task
     *
     * @throws \Exception If the task fails
     */
    public function execute(array $args = [])
    {
        throw new \Exception('Task not implemented', 500);
    }

    /**
     * Recreate tables in the database
     */
    protected function removeStoreDataFromEntityTable(): void
    {
        $connection = $this->conn->getConnection();
        // DELETE all rows not having type in ('Configuration', 'Process')
        $connection->delete(
            DatabaseHandler::SEQURA_ENTITY_TABLE,
            [
                'type NOT IN (?)' => ['Configuration', 'Process']
            ]
        );
    }

    protected function timeToString(int $timestamp): string
    {
        $time = (string) $timestamp;
        // append 0s to the left until reach 11 characters
        while (strlen($time) < 11) {
            $time = '0' . $time;
        }
        return $time;
    }

    /**
     * Response with an error message
     */
    public function httpErrorResponse(string $message, int $error_code)
    {
        throw new Exception(new Phrase($message), $error_code, $error_code);
    }

    /**
     * Response with an error message
     */
    public function httpSuccessResponse()
    {
        return ['success' => true, 'data' => ['message' => 'Task executed']];
    }
}
