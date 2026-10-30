<?php

namespace Sequra\Core\Block\Checkout\Success;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Sequra\Core\Model\Checkout\OrderReviewResolver;

/**
 * Class ReviewMessage
 *
 * Tells the shopper on the order confirmation page that seQura is still reviewing the payment.
 */
class ReviewMessage extends Template
{
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var OrderReviewResolver
     */
    private $orderReviewResolver;

    /**
     * Constructor for ReviewMessage block
     *
     * @param Template\Context $context
     * @param Session $checkoutSession
     * @param OrderReviewResolver $orderReviewResolver
     * @param mixed[] $data
     */
    public function __construct(
        Template\Context $context,
        Session $checkoutSession,
        OrderReviewResolver $orderReviewResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->orderReviewResolver = $orderReviewResolver;
    }

    /**
     * Whether the order just placed is a seQura order on hold.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->orderReviewResolver->isInReview($this->checkoutSession->getLastRealOrder());
    }
}
