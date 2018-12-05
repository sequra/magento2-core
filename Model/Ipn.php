<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;


use Exception;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

/**
 * Sequra Instant Payment Notification processor model
 */
class Ipn extends \Sequra\Core\Model\AbstractIpn implements IpnInterface
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote;

    /**
     * @var \Sequra\PhpClient\Client
     */
    protected $_client;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $_quoteRepository;

    /**
     * @var \Magento\Quote\Api\QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;
    /**
     * @var \Magento\Checkout\Api\PaymentInformationManagementInterface
     */
    protected $_paymentInformationManager;

    /**
     * @var \Magento\Customer\Model\Session $customerSession
     */
    protected $_customerSession;

    /**
     * Checkout data
     *
     * @var \Magento\Checkout\Helper\Data
     */
    protected $_checkoutData;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var \Sequra\Core\Model\Api\BuilderFactory
     */
    protected $_builderFactory;

    /**
     * @var \Sequra\Core\Model\Api\Builder\Order
     */
    protected $builderFactory;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteResotory
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManager
     * @param \Magento\Customer\Model\Session $customerSession ,
     * @param \Sequra\Core\Model\Api\BuilderFactory $builderFactory
     * @param \Magento\Checkout\Helper\Data $checkoutData
     * @param OrderSender $orderSender
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Api\CartRepositoryInterface $quoteResotory,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManager,
        \Magento\Customer\Model\Session $customerSession,
        \Sequra\Core\Model\Api\BuilderFactory $builderFactory,
        \Magento\Checkout\Helper\Data $checkoutData,
        OrderSender $orderSender,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($logger, $data);
        $this->_quoteRepository = $quoteResotory;
        $this->quoteManagement = $quoteManagement;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->orderSender = $orderSender;
        $this->_scopeConfig = $scopeConfig;
        $this->_paymentInformationManager = $paymentInformationManager;
        $this->_customerSession = $customerSession;
        $this->_checkoutData = $checkoutData;
        $this->_builderFactory = $builderFactory;
        $this->builder = $this->_builderFactory->create('order');
    }

    /**
     * Get ipn data, send verification to Sequra, run corresponding handler
     *
     * @return void
     * @throws Exception
     */
    public function processIpnRequest()
    {
        $this->_addDebugData('ipn', $this->getRequestData());
        $this->validateIPNRequest();
        try {
            if (!$this->updateOrderInSequra()) {
                return;
            }
            $this->_processOrder();
        } catch (Exception $e) {
            $this->_addDebugData('exception', $e->getMessage());
            $this->_debug();
            throw $e;
        }
        $this->_debug();
    }

    protected function validateIPNRequest()
    {
//        $this->_getConfig();
        if (!$this->isValidSignature()) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 498 Not valid signature', true, 498);
            exit;
        }
    }

    /**
     *
     */
    function isValidSignature()
    {
        return $this->builder->sign($this->getRequestData('id')) == $this->getRequestData('signature');
    }

    /**
     * @return bool
     */
    private function updateOrderInSequra($sendRef = false)
    {
        $quote = $this->_getQuote();
        $builder = $this->_builderFactory->create('order');
        $this->order_data = $builder->setOrder($quote)->build($builder::STATE_CONFIRMED, $sendRef);
        $this->getClient()->updateOrder($this->getRequestData('order_ref'), $this->order_data);
        if ($this->_client->succeeded()) {
            return $quote->getReservedOrderId();
        }
        if ($this->_client->cartHasChanged()) {
            $log_msg = $_SERVER['SERVER_PROTOCOL'] . ' 410 Cart has changed';
            header($log_msg, true, 410);
            die($log_msg);
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' Unknown error', true, 500);
            die($this->_client->dump());
        }

        return false;
    }

    protected function _getQuote()
    {
        if (is_null($this->_quote)) {
            $this->_quote = $this->_quoteRepository->get($this->getRequestData('id'));
            $this->_customerSession->setCustomerId($this->_quote->getCustomerId());
        }
        return $this->_quote;
    }

    protected function getClient()
    {
        if (!$this->_client) {
            $this->_client = new \Sequra\PhpClient\Client(
                $this->getConfigData('user_name'),
                $this->getConfigData('user_secret'),
                $this->getConfigData('endpoint')
            );
        }
        return $this->_client;
    }

    public function getConfigData($field, $storeId = null)
    {
        $path = 'sequra/core/' . $field;

        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * IPN workflow implementation
     * Everything should be added to order comments. In positive processing cases customer will get email notifications.
     * Admin will be notified on errors.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processOrder()
    {
        try {
            // Handle payment_status
            $this->_registerPaymentCapture();
            if($sent_ref = $this->sendOrderRefToSequra()){
                $this->_order->setState('processing');;
                $this->_order->addStatusHistoryComment(__('Order ref sent to SeQura: %1',$sent_ref),$this->getConfigData('order_status'));
                $this->_order->setData('sequra_order_send', 1);
                $this->orderRepositoryInterface->save($this->_order);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            if ($this->_order) {
                $comment = $this->_createIpnComment(__('Note: %1', $e->getMessage()), true);
                $comment->save();
            } else {
                $this->logger->log(\Psr\Log\LogLevel::WARNING, $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Process completed payment (either full or partial)
     *
     * @param bool $skipFraudDetection
     * @return void
     */
    protected function _registerPaymentCapture($skipFraudDetection = false)
    {
        $parentTransactionId = $this->getRequestData('order_ref');
        $this->_getQuote();
        if ($this->getCheckoutMethod() == \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote();
        }
        $this->ignoreAddressValidation();
        $this->_quote->collectTotals();
        if (!$this->_quote->getPayment()->getMethod()) {//@todo: In some prod envs this is sometimes empty
            $this->_quote->getPayment()->setMethod(
                $this->getRequestData('method')
            );
        }
        // Create Order From Quote
        $this->_order = $this->quoteManagement->submit($this->_quote);
        $this->_order->setEmailSent(0);
        if (!$this->_order->getEntityId()) { //@todo: test if this scenario works
            $log_msg = 'Could not create order for Transaction Id:' . $parentTransactionId;
            $this->logger->log(\Psr\Log\LogLevel::CRITICAL, $log_msg);
            $this->cancelOrderInSequra();
            http_response_code(410);
            die($log_msg = '{"result": "Error", "message":"' . $log_msg . '"}"');
        }

        $payment = $this->_order->getPayment();
        $payment->setTransactionId(
            $this->getRequestData('order_ref')
        );
        $payment->setCurrencyCode('EUR');
        $payment->setPreparedMessage(
            $this->_createIpnComment('SEQURA notification received')
        );
        $payment->setParentTransactionId(
            $parentTransactionId
        );
        $payment->setShouldCloseParentTransaction(
            true
        );
        $payment->setIsTransactionClosed(
            0
        );
        $payment->registerCaptureNotification(
            $this->order_data['cart']['order_total_with_tax'] / 100,
            $skipFraudDetection && $parentTransactionId
        );

        if ($this->getConfigData('autoinvoice')) {//@todo: find where the invoice is created
            $invoice = $payment->getCreatedInvoice();
            $this->_order->addStatusHistoryComment(
                __('You notified customer about invoice #%1.', $invoice->getIncrementId())
            );
        }
    }

    /**
     * Get checkout method
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        if ($this->getCustomerSession()->isLoggedIn()) {
            return \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER;
        }
        if (!$this->_quote->getCheckoutMethod()) {
            if ($this->_checkoutData->isAllowedGuestCheckout($this->_quote)) {
                $this->_quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
            } else {
                $this->_quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_REGISTER);
            }
        }
        return $this->_quote->getCheckoutMethod();
    }

    /**
     * Get customer session object
     *
     * @return \Magento\Customer\Model\Session
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function prepareGuestQuote()
    {
        $quote = $this->_quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * Make sure addresses will be saved without validation errors
     *
     * @return void
     */
    private function ignoreAddressValidation()
    {
        $this->_quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$this->_quote->getIsVirtual()) {
            $this->_quote->getShippingAddress()->setShouldIgnoreValidation(true);
        }
    }

    private function cancelOrderInSequra()
    {
        $this->_client->orderUpdate($this->order_data);
        return $this->_client->succeeded();
    }

    /**
     * Generate an "IPN" comment with additional explanation.
     * Returns the generated comment or order status history object
     *
     * @param string $comment
     * @param bool $addToHistory
     * @return string|\Magento\Sales\Model\Order\Status\History
     */
    protected function _createIpnComment($comment = '', $addToHistory = false)
    {
        $message = __('IPN "%1"', $this->getRequestData('order_ref'));
        if ($comment) {
            $message .= ' ' . $comment;
        }
        if ($addToHistory) {
            $message = $this->_order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }
        return $message;
    }

    /**
     * @return bool
     */
    public function sendOrderRefToSequra()
    {
        return $this->updateOrderInSequra('confirmed');
    }

    /**
     * @return bool
     */
    public function approvedBySequra()
    {
        return $this->updateOrderInSequra(true);
    }
}
