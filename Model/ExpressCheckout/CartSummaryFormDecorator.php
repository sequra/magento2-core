<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Exception;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver;

/**
 * Class CartSummaryFormDecorator
 *
 * Express Checkout V1 spike (PAR-835): opens the solicited identification form on SeQura's
 * new CartSummary page. Two pieces, both applied to the form HTML the solicit returns:
 *
 *  1. Appends the `show_cart` flag to the form iframe URL (`data-base-url` and `src`), so
 *     SeQura emits the flag and the cart items in the form settings/metadata.
 *  2. Injects a script that posts everything the CartSummary page renders but the solicited
 *     order does not carry — the order-scoped form endpoints, the store identity, the shopper's
 *     email and address, the shipping methods and the cart item images — to the iframe via the
 *     `cartDataReady` message the checkout-form listens for.
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
     * @var ImageHelper
     */
    private ImageHelper $imageHelper;
    /**
     * @var TaxHelper
     */
    private TaxHelper $taxHelper;
    /**
     * @var LogoPathResolver
     */
    private LogoPathResolver $logoPathResolver;

    /**
     * CartSummaryFormDecorator constructor.
     *
     * @param ImageHelper $imageHelper
     * @param TaxHelper $taxHelper
     * @param LogoPathResolver $logoPathResolver
     */
    public function __construct(
        ImageHelper $imageHelper,
        TaxHelper $taxHelper,
        LogoPathResolver $logoPathResolver
    ) {
        $this->imageHelper = $imageHelper;
        $this->taxHelper = $taxHelper;
        $this->logoPathResolver = $logoPathResolver;
    }

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
        // The endpoints are read back off the flagged HTML so they carry show_cart too.
        $flagged = $this->appendShowCartFlag($form);

        return $flagged . $this->buildCartDataScript($quote, $this->buildEndpoints($flagged));
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
     * @param array<string, string> $endpoints Order-scoped form endpoints, possibly empty.
     *
     * @return string
     */
    private function buildCartDataScript(Quote $quote, array $endpoints): string
    {
        $address = $this->buildShippingAddress($quote);

        $data = [
            'type' => 'cartDataReady',
            'storeName' => $quote->getStore()->getFrontendName(),
            'email' => $this->buildEmail($quote),
            'address' => $address,
            'shippingMethods' => $this->buildShippingMethods($quote),
            'shippingAddresses' => [$address],
            'itemImages' => $this->buildItemImages($quote),
        ];

        // Both keys are omitted rather than sent empty: the checkout-form only overwrites what
        // it receives, and it rejects a store logo that is not an absolute http(s) URL.
        if ($endpoints !== []) {
            $data['endpoints'] = $endpoints;
        }

        $storeLogoUrl = $this->buildStoreLogoUrl($quote);
        if ($storeLogoUrl !== null) {
            $data['storeLogoUrl'] = $storeLogoUrl;
        }

        $payload = json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

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
     * Derives the order-scoped form endpoints from the iframe URL the solicit returned.
     *
     * Only the metadata endpoint is derivable, as the form URL with its last path segment swapped
     * for `metadata`: that keeps the order id and every carried-over query parameter (product,
     * campaign, validation code, show_cart). The identification endpoint needs the order secret
     * and the shopper-token endpoint is not a SeQura URL at all; neither appears in the snippet,
     * so both are omitted and the checkout-form keeps the ones it booted with.
     *
     * @param string $form Form HTML, already carrying the show_cart flag.
     *
     * @return array<string, string>
     */
    private function buildEndpoints(string $form): array
    {
        if (!preg_match('/\bdata-base-url="([^"]+)"/', $form, $matches)) {
            return [];
        }

        // In the HTML the attribute value is escaped (&amp;), unlike the value getAttribute()
        // hands the injected script at runtime.
        $baseUrl = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        if (!preg_match('#^https?://#i', $baseUrl)) {
            return [];
        }

        $replacements = 0;
        $metadataEndpoint = preg_replace(
            '#(/orders/[^/?\#]+)/[^/?\#]+#',
            '$1/metadata',
            $baseUrl,
            1,
            $replacements
        );

        if ($metadataEndpoint === null || $replacements === 0) {
            return [];
        }

        return ['metadataEndpoint' => $metadataEndpoint];
    }

    /**
     * Absolute URL of the store's configured header logo, or null when there is none.
     *
     * Resolved the way Magento's own header logo block does it: the `design/header/logo_src`
     * config under the logo upload directory, joined to the store's media base URL.
     *
     * ponytail: unlike the block this does not check the file is actually there — a stale
     * config yields a broken image rather than the theme's fallback logo.
     *
     * @param Quote $quote
     *
     * @return string|null
     */
    private function buildStoreLogoUrl(Quote $quote): ?string
    {
        $path = (string)$this->logoPathResolver->getPath();
        // With no logo configured the resolver returns the bare upload directory.
        if ($path === '' || substr($path, -1) === '/') {
            return null;
        }

        $baseUrl = $quote->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * The shopper's email, resolved through the same chain the create-order request uses so the
     * CartSummary page shows the address the SeQura order was solicited with.
     *
     * @param Quote $quote
     *
     * @return string
     */
    private function buildEmail(Quote $quote): string
    {
        return (string)($quote->getCustomer()->getEmail()
            ?: $quote->getBillingAddress()->getEmail()
            ?: $quote->getShippingAddress()->getEmail());
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
        $customerTaxClassId = $quote->getCustomerTaxClassId();

        $methods = [];
        foreach ($shippingAddress->getAllShippingRates() as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }

            // Carrier as the method name and method title as the description, matching the
            // CartSummary card ("GLS" / "Entrega a domicilio 2-3 días").
            $method = [
                'reference' => (string)$rate->getCode(),
                'name' => (string)($rate->getCarrierTitle() ?: $rate->getCode()),
                // The rate price is tax-exclusive, and the checkout-form renders this as the
                // shipping line and folds it into the total. Taxed per rate the same way
                // Magento's own ShippingMethodConverter does it.
                'costWithTax' => (int)round(
                    (float)$this->taxHelper->getShippingPrice(
                        (float)$rate->getPrice(),
                        true,
                        // The helper's docblock says Customer\Model\Address, but it is fed a quote
                        // address here and by Magento's own ShippingMethodConverter.
                        // @phpstan-ignore-next-line
                        $shippingAddress,
                        $customerTaxClassId
                    ) * 100
                ),
                'description' => (string)$rate->getMethodTitle(),
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
     * Maps the quote items to product thumbnails, keyed by SKU — the reference the SeQura order
     * items carry — so the checkout-form can pair image and cart item.
     *
     * @param Quote $quote
     *
     * @return array<string, string>
     */
    private function buildItemImages(Quote $quote): array
    {
        $images = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if (!$product) {
                continue;
            }

            try {
                $url = $this->imageHelper->init($product, 'cart_page_product_thumbnail')->getUrl();
            } catch (Exception $e) {
                continue;
            }

            if ($url !== '') {
                $images[(string)$item->getSku()] = $url;
            }
        }

        return $images;
    }

    /**
     * Maps the address the order was solicited with to the checkout-form ShippingAddress shape.
     * Only that one is sent — as the `shippingAddresses` list and as the singular `address` the
     * prefill takes — because the CartSummary page shows a single address.
     *
     * `fullName` is kept for the summary line while `givenName`/`surnames` feed the separate
     * Nombre / Apellidos inputs of the address sheet.
     *
     * @param Quote $quote
     *
     * @return array<string, string>
     */
    private function buildShippingAddress(Quote $quote): array
    {
        $address = $quote->getShippingAddress();
        $street = $address->getStreet();
        $addressId = $address->getId();

        return [
            'reference' => is_scalar($addressId) ? (string)$addressId : '',
            'fullName' => trim($address->getFirstname() . ' ' . $address->getLastname()),
            'givenName' => (string)$address->getFirstname(),
            'surnames' => (string)$address->getLastname(),
            'addressLine1' => is_array($street) ? implode(', ', array_filter($street)) : (string)$street,
            'postalCode' => (string)$address->getPostcode(),
            'city' => (string)$address->getCity(),
            'countryCode' => (string)$address->getCountryId(),
        ];
    }
}
