<?php

namespace Sequra\Helper\Model\Api;

use Sequra\Helper\Api\WebhooksInterface;
use Magento\Framework\App\ResourceConnection;
use Sequra\Helper\Model\Task\ClearConfigurationTask;
use Sequra\Helper\Model\Task\ClearFrontEndCacheTask;
use Sequra\Helper\Model\Task\ConfigureDummyTask;
use Sequra\Helper\Model\Task\Task;

class Webhooks implements WebhooksInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * Resource connection
     *
     * @var ResourceConnection
     */
    protected $conn;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->conn = $resourceConnection;
    }

    /**
     * Execute webhook
     */
    public function execute()
    {
        return $this->getTaskForWebhook((string)($_GET['sq-webhook'] ?? null))->execute($_REQUEST);
    }

    /**
     * Get task for webhook
     *
     * @param string $webhook
     */
    private function getTaskForWebhook($webhook): Task
    {
        $map = [
            'dummy_config'          => ConfigureDummyTask::class,
            'clear_config'          => ClearConfigurationTask::class,
            'clear_front_end_cache' => ClearFrontEndCacheTask::class,
        ];
        return ! isset($map[$webhook]) ? new Task($this->conn) : new $map[$webhook]($this->conn);
    }
}
