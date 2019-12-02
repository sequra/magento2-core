<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;

use Exception;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\OrderStateResolverInterface;

/**
 * Sequra Instant Payment Notification processor model
 */
class Ipn extends \Sequra\Core\Model\AbstractIpn implements IpnInterface
{
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
    protected $orderRepositoryInterface;
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
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var \Sequra\Core\Model\Api\Builder\Order
     */
    protected $builder;

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
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManager
     * @param \Magento\Customer\Model\Session $customerSession ,
     * @param \Sequra\Core\Model\Api\BuilderFactory $builderFactory
     * @param \Magento\Checkout\Helper\Data $checkoutData
     * @param OrderSender $orderSender
     * @param OrderStateResolverInterface $orderStateResolver
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
        OrderStateResolverInterface $orderStateResolver,
        array $data = []
    ) {
        parent::__construct($logger, $data);
        $this->quoteRepository = $quoteResotory;
        $this->quoteManagement = $quoteManagement;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->orderSender = $orderSender;
        $this->scopeConfig = $scopeConfig;
        $this->paymentInformationManager = $paymentInformationManager;
        $this->customerSession = $customerSession;
        $this->checkoutData = $checkoutData;
        $this->orderStateResolver = $orderStateResolver;
        $this->builder = $builderFactory->create('order');
    }

    /**
     * Get ipn data, send verification to Sequra, run corresponding handler
     *
     * @return void
     * @throws Exception
     */
    public function processIpnRequest()
    {
        $this->addDebugData('ipn', $this->getRequestData());
        $this->validateIPNRequest();
        try {
            if (!$this->approvedBySequra()) {
                return;
            }
            $this->processOrder();
        } catch (Exception $e) {
            $this->addDebugData('exception', $e->getMessage());
            $this->debug();
            throw $e;
        }
        $this->debug();
    }

    protected function validateIPNRequest()
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
        return $this->builder->sign($this->getRequestData('id')) == $this->getRequestData('signature');
    }

    /**
     * @return bool
     */
    private function updateOrderInSequra()
    {
        $this->getClient()->updateOrder(
            $this->getRequestData('order_ref'),
            $this->builder->getData()
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

    public function getConfigData($field, $storeId = null)
    {
        $path = 'sequra/core/' . $field;

        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * IPN workflow implementation
     * Everything should be added to order comments. In positive processing cases customer will get email notifications.
     * Admin will be notified on errors.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processOrder()
    {
        try {
            // Handle payment_status
            $this->registerPaymentCapture();
            $sent_ref = $this->sendOrderRefToSequra();
            if ($sent_ref) {
                $status_name = 'neworder_status';
                if ($this->getConfigData('autoinvoice')) {
                    $status_name = 'order_status';
                    //Invoice is genarated or not depending on the state change
                    $this->order->setState(
                        $this->orderStateResolver->getStateForOrder(
                            $this->order,
                            [OrderStateResolverInterface::IN_PROGRESS]
                        )
                    );
                }
                $status = $this->getConfigData($status_name);
                $this->order->addStatusHistoryComment(
                    __('Order ref sent to SeQura: %1', $sent_ref),
                    $status
                );
                $this->order->setData('sequra_order_send', 1);
                $this->orderRepositoryInterface->save($this->order);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            if ($this->order) {
                $comment = $this->createIpnComment(__('Note: %1', $e->getMessage()), true);
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
    protected function registerPaymentCapture($skipFraudDetection = false)
    {
        $parentTransactionId = $this->getRequestData('order_ref');
        $this->getQuote();
        if ($this->getCheckoutMethod() == \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote();
        }
        $this->ignoreAddressValidation();
        //$this->quote->collectTotals();
        if (!$this->quote->getPayment()->getMethod()) {//@todo: In some prod envs this is sometimes empty
            $this->quote->getPayment()->setMethod(
                $this->getRequestData('method')
            );
        }
        // Create Order From Quote
        try {
            $this->order = $this->quoteManagement->submit($this->quote);
            $this->order->setEmailSent(0);
        } catch (\Exception $e) {
            $log_msg = 'Could not create order for Transaction Id:' . $parentTransactionId;
            $log_msg .= "\n".$e->getMessage();
            $this->logger->log(\Psr\Log\LogLevel::CRITICAL, $log_msg);
            http_response_code(410);//$this->cancelInSequra();
            die('{"result": "Error", "message":"' . $e->getMessage() . '"}"');
        }
        if ((bool) $this->getConfigData('autoinvoice')) {//@todo: find where the invoice is created
            $payment = $this->order->getPayment();
            $payment->setTransactionId(
                $this->getRequestData('order_ref')
            );
            $payment->setCurrencyCode('EUR');
            $payment->setPreparedMessage(
                $this->createIpnComment('SEQURA notification received')
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
            $order_data = $this->builder->getData();
            $payment->registerCaptureNotification(
                $order_data['cart']['order_total_with_tax'] / 100,
                $skipFraudDetection && $parentTransactionId
            );

            $invoice = $payment->getCreatedInvoice();
            if (!is_null($invoice)) {
                $this->order->addStatusHistoryComment(
                    __('You notified customer about invoice #%1.', $invoice->getIncrementId())
                );
            }
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
        if (!$this->quote->getCheckoutMethod()) {
            if ($this->checkoutData->isAllowedGuestCheckout($this->quote)) {
                $this->quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
            } else {
                $this->quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_REGISTER);
            }
        }
        return $this->quote->getCheckoutMethod();
    }

    /**
     * Get customer session object
     *
     * @return \Magento\Customer\Model\Session
     */
    public function getCustomerSession()
    {
        return $this->customerSession;
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function prepareGuestQuote()
    {
        $this->quote->setCustomerId(null)
            ->setCustomerEmail($this->quote->getBillingAddress()->getEmail())
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
        $this->quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$this->quote->getIsVirtual()) {
            $this->quote->getShippingAddress()->setShouldIgnoreValidation(true);
        }
    }

    /**
     * Generate an "IPN" comment with additional explanation.
     * Returns the generated comment or order status history object
     *
     * @param string $comment
     * @param bool $addToHistory
     * @return string|\Magento\Sales\Model\Order\Status\History
     */
    protected function createIpnComment($comment = '', $addToHistory = false)
    {
        $message = __('IPN "%1"', $this->getRequestData('order_ref'));
        if ($comment) {
            $message .= ' ' . $comment;
        }
        if ($addToHistory) {
            $message = $this->order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }
        return $message;
    }

    /**
     * @return bool
     */
    private function sendOrderRefToSequra()
    {
        $this->builder->setMerchantRefence(
            $this->order->getIncrementId(),
            $this->order->getId()
        );
        return $this->updateOrderInSequra();
    }

    /**
     * @return bool
     */
    private function approvedBySequra()
    {
        $this->builder->setOrder($this->getQuote());
        $this->builder->build($this->builder::STATE_CONFIRMED);
        return $this->updateOrderInSequra();
    }

    /**
     * @return bool
     */
    private function cancelInSequra()
    {
        $this->builder->setOrder($this->getQuote());
        $this->builder->setState($this->builder::STATE_CANCELLED);
        return $this->updateOrderInSequra();
    }
}
