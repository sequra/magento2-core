<?php
namespace Sequra\Helper\Model\Api;

use Sequra\Helper\Api\WebhooksInterface;
use Magento\Framework\App\ResourceConnection;
use Sequra\Helper\Model\Task\ClearConfigurationTask;
use Sequra\Helper\Model\Task\ConfigureDummyTask;
use Sequra\Helper\Model\Task\Task;

class Webhooks implements WebhooksInterface
{
    protected $request;

    /**
	 * Resource connection
	 */
	protected $conn;

    /**
	 * Constructor
	 */
	public function __construct(ResourceConnection $resourceConnection) {
		$this->conn = $resourceConnection;
	}

    public function execute()
    {
        return $this->getTaskForWebhook((string)($_GET['sq-webhook'] ?? null))->execute();
    }


	/**
	 * Get task for webhook
	 */
	private function getTaskForWebhook( $webhook ): Task {
		$map = array(
			// 'dummy_services_config' => ConfigureDummy_Service_Task::class,
			'dummy_config'          => ConfigureDummyTask::class,
			'clear_config'          => ClearConfigurationTask::class,
			// 'remove_db_tables'      => RemoveDbTablesTask::class

		);
		return ! isset( $map[ $webhook ] ) ? new Task($this->conn) : new $map[ $webhook ]($this->conn);
	}

}