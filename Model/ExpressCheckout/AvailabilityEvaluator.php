<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Exception;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\ExpressCheckoutAvailabilityRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Requests\GuestExpressCheckoutAvailabilityRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Responses\ExpressCheckoutAvailabilityResponse;
use SeQura\Core\BusinessLogic\CheckoutAPI\ExpressCheckout\Responses\GuestExpressCheckoutAvailabilityResponse;
use SeQura\Core\Infrastructure\Logger\Logger;

/**
 * Class AvailabilityEvaluator
 *
 * Asks the integration-core availability guard whether SeQura Express Checkout applies for a
 * given storefront context and maps the answer to the render state shared by every surface
 * (cart, mini-cart, product page):
 *  - logged in customer: the per-country check on the country QuoteShippingResolver says the
 *    solicit would use — their default shipping country, or the store default when they have no
 *    address yet. Not available (or no resolvable country) yields the inline "not available"
 *    message state. Only the country decides this; a customer without an address is eligible,
 *    and adds it on the express screen.
 *  - guest: the country-agnostic guest check. Not available yields the hidden state; the
 *    customer's actual country is validated after login at solicit time (HTTP 422 backstop).
 */
class AvailabilityEvaluator
{
    /**
     * Render the Express Checkout button.
     */
    public const STATE_BUTTON = 'button';
    /**
     * Render the inline "not available" message in place of the button.
     */
    public const STATE_MESSAGE = 'message';
    /**
     * Render nothing.
     */
    public const STATE_HIDDEN = 'hidden';

    /**
     * Evaluates the render state for the given storefront context.
     *
     * @param string $storeId
     * @param string $page Integration-core page identifier (e.g. 'cart', 'mini-cart', 'product').
     * @param bool $isLoggedIn Whether the shopper is a logged in customer.
     * @param string $country ISO2 country used for the logged in check (ignored for guests).
     * @param string $currency Display currency code.
     * @param string $ipAddress Shopper IP address.
     * @param string[] $productIds Product entity IDs in context.
     * @param string[] $categoryIds Category IDs in context.
     *
     * @return array{state: string, buttonStyle: string|null} State is one of self::STATE_*.
     */
    public function evaluate(
        string $storeId,
        string $page,
        bool $isLoggedIn,
        string $country,
        string $currency,
        string $ipAddress,
        array $productIds,
        array $categoryIds
    ): array {
        try {
            if ($isLoggedIn) {
                /** @var ExpressCheckoutAvailabilityResponse $response */
                $response = CheckoutAPI::get()->expressCheckout($storeId)->isAvailable(
                    new ExpressCheckoutAvailabilityRequest(
                        $page,
                        $currency,
                        $ipAddress,
                        $country,
                        $productIds,
                        $categoryIds
                    )
                );
                $unavailableState = self::STATE_MESSAGE;
            } else {
                /** @var GuestExpressCheckoutAvailabilityResponse $response */
                $response = CheckoutAPI::get()->expressCheckout($storeId)->isAvailableForGuest(
                    new GuestExpressCheckoutAvailabilityRequest(
                        $page,
                        $currency,
                        $ipAddress,
                        $productIds,
                        $categoryIds
                    )
                );
                $unavailableState = self::STATE_HIDDEN;
            }

            $data = $response->toArray();
            $buttonStyle = $data['buttonStyle'] ?? null;

            return [
                'state' => ($response->isSuccessful() && !empty($data['available']))
                    ? self::STATE_BUTTON
                    : $unavailableState,
                'buttonStyle' => is_string($buttonStyle) ? $buttonStyle : null,
            ];
        } catch (Exception $e) {
            Logger::logError('Checking Express Checkout availability failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [
                'state' => self::STATE_HIDDEN,
                'buttonStyle' => null,
            ];
        }
    }
}
