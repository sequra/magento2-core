<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Controller\Comeback;

/**
 * Comeback controller
 */
class Index extends \Magento\Checkout\Controller\Onepage
{
    /**
     * Rebuild session and redirect to default success controller
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
        if (!$order) {
            $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        $session = $this->getOnepage()->getCheckout();
        // prepare session to success or cancellation page
        $session->clearHelperData();
        $session->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($quote->getReservedOrderId());
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }
}
