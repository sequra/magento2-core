<?php

namespace Sequra\Core\Block\ExpressCheckout;

use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;

/**
 * Class CartPage
 *
 * Renders the SeQura Express Checkout button on the cart page (template
 * Sequra_Core::express/cart.phtml).
 */
class CartPage extends AbstractExpressCheckoutBlock
{
    /**
     * @inheritDoc
     */
    protected function getExpressCheckoutPage(): string
    {
        return ExpressCheckoutPage::cart()->getPage();
    }
}
