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
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;


    /**
     * Constructor
     *
     * @param ConfigFactory                               $configFactory
     * @param \Psr\Log\LoggerInterface                    $logger
     * @param \Sequra\Core\Model\Api\BuilderFactory       $builderFactory
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        ConfigFactory $configFactory,
        \Psr\Log\LoggerInterface $logger,
        \Sequra\Core\Model\Api\BuilderFactory $builderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->config = $configFactory->create();
        $this->logger = $logger;
        $this->builderFactory = $builderFactory;
        $this->storeManager = $storeManager;
    }


    /*
     * @return: int orders sent
     */
    public function sendOrderWithShipment($codeKey = false, $limit = null)
    {
        $ret = array();
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            if ($codeKey && $store->getCode()!==$codeKey) {
                continue;
            }
            $client = new \Sequra\PhpClient\Client(
                $this->config->getCoreValue('user_name',$store->getId()),
                $this->config->getCoreValue('user_secret',$store->getId()),
                $this->config->getCoreValue('endpoint',$store->getId())
            );
            $builder = $this->builderFactory->create('report');
            $builder->setMerchantId(
                $this->config->getCoreValue('merchant_ref',$store->getId())
            );
            $builder->build($store->getId(), $limit);
            $this->logger->info('SEQURA: ' . $builder->getOrderCount() . ' orders ready to be sent');
            $client->sendDeliveryReport($builder->getBuiltData());
            if ($client->getStatus() == 204) {
                $builder->setOrdersAsSent();
                $this->logger->info('SEQURA: ' . $builder->getOrderCount() . ' orders sent successfully');
                $ret[$store->getName()] = $builder->getOrderCount();
            } elseif ($client->getStatus() >= 200 && $client->getStatus() <= 299 || $client->getStatus() == 409) {
                $x = $client->getJson(); // return array, not object
                $this->logger->info('Delivery ERROR ' . $store->getName() . ' ' . $client->getStatus());
            }
            var_dump( $client);
        }
        return count($ret) ? $ret : false;
    }
}
