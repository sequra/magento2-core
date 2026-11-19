<?php

namespace Sequra\Core\Block\ExpressCheckout;

use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;

/**
 * Class MiniCart
 *
 * Renders the SeQura Express Checkout button inside the mini-cart flyout, directly under
 * the "Proceed to Checkout" button. Injected into the mini-cart customer-data
 * `extra_actions` slot by Plugin\ExpressCheckout\MiniCartButton.
 */
class MiniCart extends AbstractExpressCheckoutBlock
{
    /**
     * @inheritDoc
     */
    protected function getExpressCheckoutPage(): string
    {
        return ExpressCheckoutPage::miniCart()->getPage();
    }
}
