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
 * (cart, mini-cart, product page). What splits the two branches is whether the caller can name
 * the delivery country, not whether the shopper is logged in:
 *  - country known (the cart surfaces, for guests as much as for customers — see
 *    QuoteShippingResolver::getResolvableShippingCountry, which ends in the store view's locale):
 *    the per-country check on the very country the solicit would use. Not available yields the
 *    inline "not available" message state. Only the country decides this; a shopper without an
 *    address is eligible, and adds it on the express screen.
 *  - no country (the product page, whose HTML is full-page cached and shared by every shopper, so
 *    nothing shopper-specific may enter the render decision): the country-agnostic guest check —
 *    a strict subset of the country-aware one. Not available yields the hidden state, and the
 *    delivery country is validated at solicit time instead (HTTP 422 backstop).
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
     * @param string $country ISO2 delivery country the solicit would use, or an empty string when
     *                        the caller cannot name one (see the class docblock).
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
        string $country,
        string $currency,
        string $ipAddress,
        array $productIds,
        array $categoryIds
    ): array {
        try {
            if ($country !== '') {
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
