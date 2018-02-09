<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;


/**
 * Sequra Instant Payment Notification processor model
 */
class Reporter implements ReporterInterface
{

    /**
     * @var \Sequra\Core\Model\Config
     */
    protected $_config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Sequra\Core\Model\Api\BuilderFactory
     */
    protected $_builder;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;


    /**
     * Constructor
     *
     * @param ConfigFactory $configFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Sequra\Core\Model\Api\BuilderFactory $builderFactory
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        ConfigFactory $configFactory,
        \Psr\Log\LoggerInterface $logger,
        \Sequra\Core\Model\Api\BuilderFactory $builderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_config = $configFactory->create();
        $this->_logger = $logger;
        $this->_builder = $builderFactory->create('report');;
        $this->_storeManager = $storeManager;
    }


    /*
     * @return: int orders sent
     */
    public function sendOrderWithShipment()
    {
        $ret = array();
        $stores = $this->_storeManager->getStores($withDefault = false);
        $builder = $this->_builder;
        foreach ($stores as $store) {
            $client = new \Sequra\PhpClient\Client(
                $this->_config->getCoreValue('user_name'),
                $this->_config->getCoreValue('user_secret'),
                $this->_config->getCoreValue('endpoint')
            );
            $builder->build($store->getId());
            $this->_logger->info('SEQURA: ' . $builder->getOrderCount() . ' orders ready to be sent');
            $client->sendDeliveryReport($builder->getBuiltData());
            if ($client->getStatus() == 204) {
                $builder->setOrdersAsSent();
                $this->_logger->info('SEQURA: ' . $builder->getOrderCount() . ' orders sent successfully');
                $ret[$store->getName()] = $builder->getOrderCount();
            } elseif ($client->getStatus() >= 200 && $client->getStatus() <= 299 || $client->getStatus() == 409) {
                $x = $client->getJson(); // return array, not object
                $this->_logger->info('Delivery ERROR ' . $store->getName() . ' ' . $client->getStatus());
                $this->_logger->info($x);
            }
        }
        return count($ret) ? $ret : false;
    }
}
