<?php

namespace Sequra\Core\Model\ExpressCheckout;

/**
 * Class ExpressCheckoutFlow
 *
 * Request-scoped marker saying that the create-order request being built right now belongs to
 * Express Checkout.
 *
 * It exists because {@see \SeQura\Core\BusinessLogic\Domain\Integration\Order\MerchantDataProviderInterface}
 * is a context-free hook: `getOptions()` is handed no cart, no order and no flow, yet Express
 * Checkout needs it to declare `addresses_may_be_missing` while regular checkout must not. Sending
 * that flag on every request would hand back the server-side guard that refuses a regular-checkout
 * order arriving with a broken or empty address — a guard worth keeping for the flow that has no
 * reason to lose it.
 *
 * Magento's object manager gives this a single instance per request, which is exactly the scope
 * wanted: the express solicit turns it on around its own call and off again in a finally, so
 * nothing else in the request can see it on.
 */
class ExpressCheckoutFlow
{
    /**
     * @var bool
     */
    private bool $soliciting = false;

    /**
     * Marks the current request as building an Express Checkout create-order request.
     *
     * @return void
     */
    public function enterSolicit(): void
    {
        $this->soliciting = true;
    }

    /**
     * Clears the marker.
     *
     * @return void
     */
    public function leaveSolicit(): void
    {
        $this->soliciting = false;
    }

    /**
     * Whether an Express Checkout solicit is being built right now.
     *
     * @return bool
     */
    public function isSoliciting(): bool
    {
        return $this->soliciting;
    }
}
