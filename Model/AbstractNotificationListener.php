<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;

class AbstractNotificationListener
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * IPN request data
     *
     * @var array
     */
    protected $request;

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $debugData = [];

       /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    /**
     * @var \Sequra\PhpClient\Client
     */
    protected $client;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Quote\Api\QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Checkout\Api\PaymentInformationManagementInterface
     */
    protected $paymentInformationManager;

    /**
     * @var \Magento\Customer\Model\Session $customerSession
     */
    protected $customerSession;

    /**
     * Checkout data
     *
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutData;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Sequra\Core\Model\Api\Builder\Order
     */
    protected $builder;

    /**
     * Order status resolver
     *
     * @var \Magento\Sales\Model\Order\OrderStateResolverInterface
     */
    protected $orderStateResolver;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteResotory
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement,
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManager
     * @param \Magento\Customer\Model\Session $customerSession ,
     * @param \Sequra\Core\Model\Api\BuilderFactory $builderFactory
     * @param \Magento\Checkout\Helper\Data $checkoutData
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Sales\Model\Order\OrderStateResolverInterface $orderStateResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Api\CartRepositoryInterface $quoteResotory,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManager,
        \Magento\Customer\Model\Session $customerSession,
        \Sequra\Core\Model\Api\BuilderFactory $builderFactory,
        \Magento\Checkout\Helper\Data $checkoutData,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Order\OrderStateResolverInterface $orderStateResolver,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->request = $data;
        $this->quoteRepository = $quoteResotory;
        $this->quoteManagement = $quoteManagement;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->invoiceSender = $invoiceSender;
        $this->scopeConfig = $scopeConfig;
        $this->paymentInformationManager = $paymentInformationManager;
        $this->customerSession = $customerSession;
        $this->checkoutData = $checkoutData;
        $this->orderStateResolver = $orderStateResolver;
        $this->builder = $builderFactory->create('order');
    }

    /**
     * Notification request data getter
     *
     * @param string $key
     * @return array|string
     */
    public function getRequestData($key = null, $default = null)
    {
        if (null === $key) {
            return $this->request;
        }
        return $this->request[$key]??($this->request['m_'.$key]??$default);
    }

    /**
     * @param string $key
     * @param array|string $value
     * @return $this
     */
    protected function addDebugData($key, $value)
    {
        $this->debugData[$key] = $value;
        return $this;
    }

    /**
     * Log debug data to file
     *
     * @return void
     */
    protected function debug()
    {
        if ($this->config && $this->config->getValue('debug')) {
            $this->logger->debug(var_export($this->debugData, true));
        }
    }

    protected function validateNotificationRequest()
    {
        if (!$this->isValidSignature()) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 498 Not valid signature', true, 498);
            exit;
        }
    }

    /**
     *
     */
    private function isValidSignature()
    {
        return
            $this->builder->sign($this->getRequestData('id')) == $this->getRequestData('signature');
    }

    protected function getConfigData($field, $storeId = null)
    {
        $path = 'sequra/core/' . $field;

        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    protected function orderAlreadyExists() {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $this->quote->getReservedOrderId(), 'eq')->create();
        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
        $this->order = array_pop($orderList);
        return !is_null($this->order);
    }

    /**
     * @return bool
     */
    protected function updateOrderInSequra()
    {
        $data = $this->builder->getData();
        $this->getClient()->updateOrder(
            $this->getRequestData('order_ref'),
            $data
        );
        if ($this->client->succeeded()) {
            return $this->quote->getReservedOrderId();
        }
        if ($this->client->cartHasChanged()) {
            http_response_code(410);
            die(
                json_encode($this->client->getJson())
            );
        } else {
            http_response_code(500);
            die(
                $_SERVER['SERVER_PROTOCOL'] . ' Unknown error' .
                "\n" . $this->client->dump()
            );
        }

        return false;
    }

    protected function getQuote()
    {
        if (is_null($this->quote)) {
            $this->quote = $this->quoteRepository->get($this->getRequestData('id'));
            $this->customerSession->setCustomerId($this->quote->getCustomerId());
        }
        return $this->quote;
    }

    protected function getClient()
    {
        if (!$this->client) {
            $this->client = new \Sequra\PhpClient\Client(
                $this->getConfigData('user_name'),
                $this->getConfigData('user_secret'),
                $this->getConfigData('endpoint')
            );
        }
        return $this->client;
    }
}
