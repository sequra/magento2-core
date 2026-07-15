<?php

/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Set a store-scoped configuration value (store id 1) and flush the config cache
 */
class SetConfigTask extends Task
{

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
        $path = isset($args['path']) ? (string) $args['path'] : '';
        $value = isset($args['value']) ? (string) $args['value'] : '';
        if ($path === '') {
            $this->httpErrorResponse('Missing config path', 400);
        }

        $objectManager = ObjectManager::getInstance();
        /**
         * @var WriterInterface $writer
         */
        $writer = $objectManager->get(WriterInterface::class);
        $writer->save($path, $value, ScopeInterface::SCOPE_STORES, 1);

        /**
         * @var TypeListInterface $cacheTypeList
         */
        $cacheTypeList = $objectManager->get(TypeListInterface::class);
        $cacheTypeList->cleanType('config');

        return $this->httpSuccessResponse();
    }
}
