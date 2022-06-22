<?php

declare(strict_types=1);
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;

use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use \Magento\Sales\Model\Order;
use Sequra\Core\Model\Adminhtml\Source\Endpoint;

/**
 * Sequra Instant Payment Notification processor model
 */
class OrderUpdater implements OrderUpdaterInterface
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderCollection
     */
    protected $sequraOrders = null;

    /**
     * @var \Sequra\Core\Model\Config
     */
    protected $config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Sequra\Core\Model\Api\BuilderFactory
     */
    protected $builderFactory;

    /**
     * Constructor
     *
     * @param ConfigFactory                               $configFactory
     * @param \Psr\Log\LoggerInterface                    $logger
     * @param \Sequra\Core\Model\Api\BuilderFactory       $builderFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     */
    public function __construct(
        ConfigFactory $configFactory,
        \Psr\Log\LoggerInterface $logger,
        \Sequra\Core\Model\Api\BuilderFactory $builderFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    ) {
        $this->config = $configFactory->create();
        $this->logger = $logger;
        $this->builderFactory = $builderFactory;
        $this->orderRepository = $orderRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Send to SeQura teh current states oo orders paid with sequra from $updatedDate on,
     * limited to $limit
     *
     * @param int|bool $updatedDate
     * @param int $limit
     * @return int
     */
    public function sendOrderUpdates($updatedDate = false, $limit = false): int
    {
        $ret = 0;
        $this->getOrders($updatedDate, $limit);
        foreach ($this->sequraOrders as $order) {
            $endpoint = $this->config->getCoreValue('endpoint', $order->getStoreId());
            $client = new \Sequra\PhpClient\Client(
                $this->config->getCoreValue('user_name', $order->getStoreId()),
                $this->config->getCoreValue('user_secret', $order->getStoreId()),
                $endpoint,
                $endpoint != Endpoint::LIVE
            );
            $orderObj = $this->orderRepository->get($order->getId());
            if (!$this->shouldSendUpdte($orderObj)) {
                $this->logger->info('SEQURA: Skip order update ' . $order->getId() . ' not ready yet');
                continue;
            }
            $builder = $this->builderFactory->create('order-update')
                ->setOrder($orderObj)
                ->build()
                ->addMerchantReferences();
            $client->orderUpdate($builder->getData());
            if ($client->getStatus() == 422) { //Just in case it failed because order_ref_2
                $builder->addMerchantReferences(false);
                $client->orderUpdate($builder->getData());
            }
            if ($client->getStatus() >= 200 && $client->getStatus() <= 299) {
                $this->logger->info('SEQURA: Order update ' . $order->getId() . ' successfully');
                $ret++;
            } else {
                $x = $client->getJson(); // return array, not object
                $this->logger->error('SEQURA: Order update ' . $order->getId() . ' error ' . $client->getStatus() . print_r($x, true));
            }
        }
        return $ret;
    }
    protected function shouldSendUpdte($order)
    {
        //Sync if order has already been addend in a previous DR
        // or if it has been refunded.
        return $order->getData('sequra_order_send') == '0' || Order::STATE_CLOSED == $order->getState();
    }

    protected function addCommentToOrder($order, $comment): void
    {
        if (method_exists($order, 'addCommentToStatusHistory')) {
            $order->addCommentToStatusHistory($comment);
        } else {
            $order->addStatusHistoryComment($comment);
        }
    }

    /**
     * Loads orders paid with sequra from updatedDate on limted to limit
     * 
     * @param int $updatedDate
     * @param int $limit
     *
     * @return Collection
     */
    protected function getOrders($updatedDate, $limit = false): OrderCollection
    {
        $collection = $this->orderCollectionFactory->create()
            ->addFieldToSelect([
                'entity_id', //load minimun fields, anyway later, we need to populate all and load related objects.
                'store_id'
            ])
            ->addFieldToFilter(
                'main_table.updated_at',
                ['gteq' => $updatedDate]
            );
        /* join with payment table */
        $collection->getSelect()
            ->join(
                ["sop" => $collection->getTable('sales_order_payment')],
                'main_table.entity_id = sop.parent_id',
                ['method']
            )
            ->where('sop.method like ?', 'sequra\_%')
            ->distinct(true);
        if ($limit) {
            $collection->getSelect()->limit($limit);
        }
        $this->sequraOrders = $collection;

        return $this->sequraOrders;
    }
}
