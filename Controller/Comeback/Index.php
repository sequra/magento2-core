<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Controller\Comeback;

use Magento\Framework\Exception\RemoteServiceUnavailableException;

/**
 * Unified IPN controller for all supported PayPal methods
 */
class Index extends \Magento\Checkout\Controller\Onepage
{
    /**
     * Instantiate IPN model and pass IPN request to it
     *
     * @return void
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        $quote = $this->quoteRepository->get(
            $this->getRequest()->getParam('quote_id')
        );
        $order =
            \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Sales\Model\Order')->loadByIncrementId($quote->getReservedOrderId());
        if(!$order){
            $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        $session = $this->getOnepage()->getCheckout();
        // prepare session to success or cancellation page
        $session->clearHelperData();
        $session->setLastRealOrder($order);
        $session->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($quote->getReservedOrderId());
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }
}
