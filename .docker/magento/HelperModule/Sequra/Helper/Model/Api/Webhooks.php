<?php

namespace Sequra\Helper\Model\Api;

use Sequra\Helper\Api\WebhooksInterface;
use Magento\Framework\App\ResourceConnection;
use Sequra\Helper\Model\Task\ClearConfigurationTask;
use Sequra\Helper\Model\Task\ClearFrontEndCacheTask;
use Sequra\Helper\Model\Task\ConfigureDummyTask;
use Sequra\Helper\Model\Task\GetOrderTrackingsTask;
use Sequra\Helper\Model\Task\PutOrderOnHoldTask;
use Sequra\Helper\Model\Task\SetConfigTask;
use Sequra\Helper\Model\Task\Task;
use Sequra\Helper\Model\Task\VerifyOrderHasMerchantIdTask;
use Sequra\Core\Services\Bootstrap;

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
     * @var Bootstrap
     */
    private $bootstrap;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param Bootstrap $bootstrap
     */
    public function __construct(ResourceConnection $resourceConnection, Bootstrap $bootstrap)
    {
        $this->conn = $resourceConnection;
        $this->bootstrap = $bootstrap;
    }

    /**
     * Execute webhook
     */
    public function execute()
    {
        $this->bootstrap->initInstance();

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
            'verify_order_has_merchant_id' => VerifyOrderHasMerchantIdTask::class,
            'set_config'            => SetConfigTask::class,
            'get_order_trackings'   => GetOrderTrackingsTask::class,
            'put_order_on_hold'     => PutOrderOnHoldTask::class,
        ];
        return ! isset($map[$webhook]) ? new Task($this->conn) : new $map[$webhook]($this->conn);
    }
}
