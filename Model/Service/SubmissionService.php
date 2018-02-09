<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Service;

use Sequra\Core\Api\SubmissionInterface;

/**
 * Class SubmissionService
 *
 */
class SubmissionService implements SubmissionInterface
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $_context;

    /**
     * @var \Sequra\Core\Model\Api\BuilderFactory
     */
    protected $_builderFactory;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Sequra\Core\Model\Api\BuilderFactory $builderFactory
    ) {

        $this->quoteRepository = $quoteRepository;
        $this->_checkoutSession = $checkoutSession;
        $this->_scopeConfig = $scopeConfig;
        $this->_builderFactory = $builderFactory;
        $this->_context = $context;
    }

    public function getForm()
    {
        $quote = $this->_checkoutSession->getQuote();
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        $builder = $this->_builderFactory->create('order');
        $data = $builder->setOrder($quote)->build();
        $client = new \Sequra\PhpClient\Client(
            $this->getConfigData('user_name'),
            $this->getConfigData('user_secret'),
            $this->getConfigData('endpoint')
        );
        $client->startSolicitation($data);
        $url = $client->getOrderUri();
        if (!$client->succeeded()) {
            http_response_code($client->getStatus());
            die();
        }
        $payment_code = $quote->getPayment()->getMethod();
        $options = array(
            'ajax' => true,
            'product' => $this->getPaymentConfigData($payment_code, 'product'),
            'campaign' => $this->getPaymentConfigData($payment_code, 'campaign')
        );
        return $client->getIdentificationForm($url, $options);
    }

    public function getConfigData($field, $storeId = null)
    {
        $path = 'sequra/core/' . $field;

        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getPaymentConfigData($payment_code, $field, $storeId = null)
    {
        $path = 'payment/' . $payment_code . '/' . $field;

        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
