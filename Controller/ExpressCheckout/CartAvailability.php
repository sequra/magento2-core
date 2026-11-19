<?php

namespace Sequra\Core\Controller\ExpressCheckout;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutInterface;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\ExpressCheckout\MiniCart;
use Sequra\Core\Model\ExpressCheckout\AvailabilityEvaluator;

/**
 * Class CartAvailability
 *
 * Live (non-cached) availability re-check for the mini-cart Express Checkout button. The button
 * markup is injected into the cached `cart` customer-data section, which is only re-fetched on a
 * cart change or login — so after the merchant disables Express Checkout in the portal (synced in
 * via webhook) the button stays in the shopper's cached section until then. The storefront calls
 * this endpoint when it mounts the mini-cart button and tears it down if the answer is no longer
 * "available", keeping the section's render-state decision out of the cached HTML.
 *
 * Reuses the MiniCart block so the answer is identical to what the block would render server-side.
 */
class CartAvailability implements HttpGetActionInterface
{
    use ResponseTrait;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;
    /**
     * @var LayoutInterface
     */
    private LayoutInterface $layout;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param LayoutInterface $layout
     */
    public function __construct(JsonFactory $resultJsonFactory, LayoutInterface $layout)
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->layout = $layout;
    }

    /**
     * Returns the current mini-cart render state for the session cart.
     *
     * The answer depends on the live merchant config and the session cart, so it must never be
     * page-cached.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $this->noStore($result);

        try {
            /** @var MiniCart $block */
            $block = $this->layout->createBlock(MiniCart::class);

            if ($block->isAvailable()) {
                return $result->setData(['state' => AvailabilityEvaluator::STATE_BUTTON]);
            }

            if ($block->showUnavailableMessage()) {
                return $result->setData([
                    'state' => AvailabilityEvaluator::STATE_MESSAGE,
                    'message' => $block->getUnavailableMessage(),
                ]);
            }

            return $result->setData(['state' => AvailabilityEvaluator::STATE_HIDDEN]);
        } catch (Exception $e) {
            Logger::logError('Express Checkout mini-cart availability check failed: ' . $e->getMessage());

            // Fail open on an unexpected controller error: keep whatever the storefront has rather
            // than hide a button that may well be valid.
            return $result->setData(['state' => AvailabilityEvaluator::STATE_BUTTON]);
        }
    }
}
