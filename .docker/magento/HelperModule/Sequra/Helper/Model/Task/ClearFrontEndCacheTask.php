<?php

/**
 * Clear Magento's page cache
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\ResourceConnection;

/**
 * Clear Magento's page cache
 */
class ClearFrontEndCacheTask extends Task
{

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var Pool
     */
    private $cacheFrontendPool;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection Resource connection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        parent::__construct($resourceConnection);
        /**
         * @var TypeListInterface $cacheTypeList
         */
        $cacheTypeList = ObjectManager::getInstance()->get(TypeListInterface::class);
        $this->cacheTypeList = $cacheTypeList;
        /**
         * @var Pool $cacheFrontendPool
         */
        $cacheFrontendPool = ObjectManager::getInstance()->get(Pool::class);
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Execute the task
     *
     * @param string[] $args Arguments for the task
     *
     * @return array<string, mixed>
     *
     * @throws \Exception If the task fails
     */
    public function execute(array $args = [])
    {
        $_types = [
            'layout',
            'block_html',
            'collections',
            'full_page',
        ];

        foreach ($_types as $type) {
            $this->cacheTypeList->cleanType($type);
        }
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
        return $this->httpSuccessResponse();
    }
}
