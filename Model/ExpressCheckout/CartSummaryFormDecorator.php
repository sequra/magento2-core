<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Magento\Quote\Model\Quote;

/**
 * Class CartSummaryFormDecorator
 *
 * Express Checkout V1 spike (PAR-835): opens the solicited identification form on SeQura's
 * new CartSummary page. Two pieces, both applied to the form HTML the solicit returns:
 *
 *  1. Appends the `show_cart` flag to the form iframe URL (`data-base-url` and `src`), so
 *     SeQura emits the flag and the cart items in the form settings/metadata.
 *  2. Injects a script that posts the quote's shipping methods and address to the iframe
 *     via the `cartDataReady` message the checkout-form listens for.
 *
 * ponytail: spike is always-on for express solicits; gate behind a store config when productized.
 */
class CartSummaryFormDecorator
{
    /**
     * The iframe never acknowledges the message, so the injected script re-sends
     * cartDataReady until the app inside must have booted (20 × 500ms ≈ 10s).
     */
    private const POST_MESSAGE_ATTEMPTS = 20;
    private const POST_MESSAGE_INTERVAL_MS = 500;

    /**
     * Decorates the identification form HTML with the show_cart flag and the cart data script.
     *
     * @param string $form Identification form HTML returned by the solicit.
     * @param Quote $quote Solicited quote, already resolved (address set, shipping rates collected).
     *
     * @return string
     */
    public function decorate(string $form, Quote $quote): string
    {
        return $this->appendShowCartFlag($form) . $this->buildCartDataScript($quote);
    }

    /**
     * Appends show_cart=true to the iframe form URLs (data-base-url and src). Other URLs in
     * the snippet (e.g. the sequra_form script loader) are left untouched — only order form
     * URLs qualify.
     *
     * @param string $form
     *
     * @return string
     */
    private function appendShowCartFlag(string $form): string
    {
        $result = preg_replace_callback(
            '/\b(data-base-url|src)="([^"]+)"/',
            static function (array $matches): string {
                if (strpos($matches[2], '/orders/') === false) {
                    return $matches[0];
                }

                $separator = strpos($matches[2], '?') === false ? '?' : '&';

                return $matches[1] . '="' . $matches[2] . $separator . 'show_cart=true"';
            },
            $form
        );

        return $result ?? $form;
    }

    /**
     * Builds the script that posts the cartDataReady message to the form iframe.
     *
     * @param Quote $quote
     *
     * @return string
     */
    private function buildCartDataScript(Quote $quote): string
    {
        $payload = json_encode(
            [
                'type' => 'cartDataReady',
                'shippingMethods' => $this->buildShippingMethods($quote),
                'shippingAddresses' => $this->buildShippingAddresses($quote),
            ],
            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
        );

        $attempts = self::POST_MESSAGE_ATTEMPTS;
        $interval = self::POST_MESSAGE_INTERVAL_MS;

        return <<<HTML

<script type="text/javascript">
    (function () {
        var payload = {$payload};
        var attempts = 0;
        var timer = setInterval(function () {
            var iframe = document.getElementById(window.SequraFormElement);
            if (iframe && iframe.contentWindow && iframe.getAttribute('data-base-url')) {
                iframe.contentWindow.postMessage(
                    payload,
                    new URL(iframe.getAttribute('data-base-url')).origin
                );
            }
            if (++attempts >= {$attempts}) {
                clearInterval(timer);
            }
        }, {$interval});
    })();
</script>
HTML;
    }

    /**
     * Maps the quote's collected shipping rates to the checkout-form ShippingMethod shape.
     * The rate applied to the quote goes first: the checkout-form preselects the first method,
     * and the solicited order total was computed with that rate.
     *
     * @param Quote $quote
     *
     * @return array<int, array<string, string|int>>
     */
    private function buildShippingMethods(Quote $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();
        $appliedCode = (string)$shippingAddress->getShippingMethod();

        $methods = [];
        foreach ($shippingAddress->getAllShippingRates() as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }

            $method = [
                'reference' => (string)$rate->getCode(),
                'name' => (string)($rate->getMethodTitle() ?: $rate->getCode()),
                // ponytail: rate price is tax-exclusive; use the taxed shipping amount when productized.
                'costWithTax' => (int)round((float)$rate->getPrice() * 100),
                'description' => (string)$rate->getCarrierTitle(),
            ];

            if ($method['reference'] === $appliedCode) {
                array_unshift($methods, $method);
            } else {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * Maps the address the order was solicited with to the checkout-form ShippingAddress shape.
     * Only that one is sent: the CartSummary page shows a single address and its edit flow
     * (cart_address page) does not exist yet.
     *
     * @param Quote $quote
     *
     * @return array<int, array<string, string>>
     */
    private function buildShippingAddresses(Quote $quote): array
    {
        $address = $quote->getShippingAddress();
        $street = $address->getStreet();

        return [
            [
                'reference' => (string)$address->getId(),
                'fullName' => trim($address->getFirstname() . ' ' . $address->getLastname()),
                'addressLine1' => is_array($street) ? implode(', ', array_filter($street)) : (string)$street,
                'postalCode' => (string)$address->getPostcode(),
                'city' => (string)$address->getCity(),
                'countryCode' => (string)$address->getCountryId(),
            ],
        ];
    }
}
