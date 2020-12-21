<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;

use Exception;

/**
 * Sequra Instant Payment Notification processor model
 */
class Ipn extends \Sequra\Core\Model\AbstractNotificationListener implements IpnInterface
{
    /**
     * Get ipn data, send verification to Sequra, run corresponding handler
     *
     * @return void
     * @throws Exception
     */
    public function processIpnRequest()
    {
        $this->addDebugData('ipn', $this->getRequestData());
        $this->validateNotificationRequest();
        $this->getQuote();
        try {
            switch ($this->getRequestData('sq_state')) {
                case 'needs_review':
                    if (!$this->onHoldInSequra()) {
                        return;
                    }
                    $this->createOrderFromQuote();
                    $this->setOrderInPaymentReview();
                    $this->sendOrderRefToSequra();
                break;
                case 'approved':
                    if (!$this->approvedInSequra()) {
                        return;
                    }
                    if (!$this->orderAlreadyExists()) {
                        $this->createOrderFromQuote();
                        $this->sendOrderRefToSequra();
                    }
                    $this->processOrder();
                break;
            }
        } catch (Exception $e) {
            $this->addDebugData('exception', $e->getMessage());
            $this->debug();
            throw $e;
        }
        $this->debug();
    }
    /**
     * IPN workflow implementation
     * Everything should be added to order comments. In positive processing cases customer will get email notifications.
     * Admin will be notified on errors.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function setOrderInPaymentReview()
    {
        $this->order->setState(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);
        $this->addCommentToStatusHistory(
            __('Order ref sent to SeQura: %1', $this->order->getIncrementId())
        );
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
        // Handle payment_status
        $this->registerPaymentCapture();
        $status_name = 'new_order_status';
        if ($this->getConfigData('autoinvoice')) {
            $status_name = 'order_status';
            //Invoice is genarated or not depending on the state change
            $this->order->setState(
                $this->orderStateResolver->getStateForOrder(
                    $this->order,
                    [\Magento\Sales\Model\Order\OrderStateResolverInterface::IN_PROGRESS]
                )
            );
        }
        $status = $this->getConfigData($status_name);
        $this->addCommentToStatusHistory(
            __('Order ref sent to SeQura: %1', $this->order->getIncrementId()),
            $status
        );
        $this->order->setData('sequra_order_send', 1);
        $this->orderRepository->save($this->order);
    }

    /**
     * Process completed payment (either full or partial)
     *
     * @return void
     */
    protected function createOrderFromQuote() {
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
        } catch (\Exception $e) {
            $log_msg = 'Could not create order for Transaction Id:' . $this->getRequestData('order_ref');
            $log_msg .= "\n".$e->getMessage();
            $this->logger->log(\Psr\Log\LogLevel::CRITICAL, $log_msg);
            http_response_code(410);//Cancel in SeQura
            die('{"result": "Error", "message":"' . $e->getMessage() . '"}"');
        }
    }
    protected function orderAlreadyExists() {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $this->quote->getReservedOrderId(), 'eq')->create();
        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
        $this->order = array_pop($orderList);
        return !is_null($this->order);
    }
    /**
     * Process completed payment (either full or partial)
     *
     * @param bool $skipFraudDetection
     * @return void
     */
    protected function registerPaymentCapture($skipFraudDetection = false)
    {
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
                $this->getRequestData('order_ref')
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
                $skipFraudDetection && $this->getRequestData('order_ref')
            );

            $invoice = $payment->getCreatedInvoice();
            if (!is_null($invoice)) {
                $this->invoiceSender->send($invoice);
                $invoice->save();
                $this->addCommentToStatusHistory(
                    __('You notified customer about invoice #%1.', $invoice->getIncrementId())
                );
                $this->order->setIsCustomerNotified(true)
                    ->save();
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
    protected function sendOrderRefToSequra()
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
    private function onHoldInSequra()
    {
        return $this->setStateInSequra($this->builder::STATE_ON_HOLD);

    }

    /**
     * @return bool
     */
    private function approvedInSequra()
    {
        return $this->setStateInSequra($this->builder::STATE_CONFIRMED);
    }

    /**
     * @return bool
     */
    private function setStateInSequra(string $state)
    {
        $this->builder->setOrder($this->getQuote());
        $this->builder->build($state);
        return $this->updateOrderInSequra();
    }

    private function addCommentToStatusHistory($msg, $status = false) {
        if (method_exists($this->order, 'addCommentToStatusHistory')) {
            $this->order->addCommentToStatusHistory($msg, $status);
        } else {
            $this->order->addStatusHistoryComment($msg, $status);
        }
    }
}
