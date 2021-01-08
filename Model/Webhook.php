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
class Webhook extends \Sequra\Core\Model\AbstractNotificationListener implements WebhookInterface
{
    /**
     * Get Webhook data, send verification to Sequra, run corresponding handler
     *
     * @return void
     * @throws Exception
     */
    public function processWebhookRequest()
    {
        $this->addDebugData('Webhook', $this->getRequestData());
        $this->builder->setQuoteAsOrder($this->getQuote());
        $this->validateNotificationRequest();
        if (!$this->orderAlreadyExists()) {
            http_response_code(404);
            die();
        }
        $event = $this->getRequestData('event');
        switch ($event) {
            case 'cancelled':
                $this->cancelOrder();
                break;
            case 'cancel':
                $this->processOrderCancelation();
        }
        $this->debug();
    }

    /**
     * Tries to cancel teh order in magento
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processOrderCancelation()
    {
        if ($this->cancelOrder()) {
            if ($this->cancelConfirmedOrderInSequra()) {
                die(json_encode(['result' => 'cancelled']));
            }
        }
        //If the invoice was created we can't refund
        //@todo: create creditmemo if there is no shipping and cancel.
        die(json_encode(['result' => 'toolate', 'since' => 0]));
    }

    /**
     * @return bool
     */
    protected function cancelConfirmedOrderInSequra()
    {
        $data = $this->builder->getData();

        $data['shipped_cart'] =
        $data['unshipped_cart'] = array(
            'items'=>array(),
            'order_total_without_tax'=>0,
            'order_total_with_tax'=>0,
            'currency' => 'EUR'
        );

        $this->getClient()->orderUpdate(
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

    protected function cancelOrder() {
        if ($this->order->canUnhold()) {
            $this->orderManagement->unHold($this->order->getId());
        }
        return $this->orderManagement->cancel($this->order->getId());
    }
}
