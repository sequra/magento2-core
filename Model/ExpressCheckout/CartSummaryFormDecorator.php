<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Exception;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver;
use Sequra\Core\Model\QuoteEmailResolver;

/**
 * Class CartSummaryFormDecorator
 *
 * Express Checkout V1 spike (PAR-835): opens the solicited identification form on SeQura's
 * new CartSummary page. Two pieces, both applied to the form HTML the solicit returns:
 *
 *  1. Appends the `show_cart` flag to the form iframe URL (`data-base-url` and `src`), so
 *     SeQura emits the flag and the cart items in the form settings/metadata.
 *  2. Injects a script that posts everything the CartSummary page renders but the solicited
 *     order does not carry, or carries frozen at the figures the boot-time solicit produced —
 *     the order-scoped form endpoints, the store identity, the shopper's email and address, the
 *     shipping methods, the cart item images and the quote's own total and shipping cost — to
 *     the iframe via the `cartDataReady` message the checkout-form listens for. The same script
 *     relays the
 *     `Sequra.cartUpdate` message the form posts back (address, carrier or email changed) to
 *     the cart-update endpoint and forwards its refreshed payload on to the iframe — first
 *     reloading that iframe when the update landed on a freshly minted order. When that
 *     endpoint does not answer with a usable payload the iframe gets a `cartUpdateFailed`
 *     message instead, so the form never waits on a reply that is not coming.
 *
 * ponytail: spike is always-on for express solicits; gate behind a store config when productized.
 */
class CartSummaryFormDecorator
{
    /**
     * The form acknowledges cartDataReady, and the injected script stops re-sending as soon as it
     * does. These bound the wait for an acknowledgement that never comes (20 × 500ms ≈ 10s).
     */
    private const POST_MESSAGE_ATTEMPTS = 20;
    private const POST_MESSAGE_INTERVAL_MS = 500;

    /**
     * How long to wait for a reloaded iframe's `load` before offering it the payload anyway.
     * Only a backstop: `load` is what normally starts the retries.
     */
    private const RELOAD_LOAD_TIMEOUT_MS = 3000;

    /**
     * @var ImageHelper
     */
    private ImageHelper $imageHelper;
    /**
     * @var ItemResolverInterface
     */
    private ItemResolverInterface $itemResolver;
    /**
     * @var ShippingMethodConverter
     */
    private ShippingMethodConverter $shippingMethodConverter;
    /**
     * @var LogoPathResolver
     */
    private LogoPathResolver $logoPathResolver;
    /**
     * @var FormKey
     */
    private FormKey $formKey;
    /**
     * @var QuoteEmailResolver
     */
    private QuoteEmailResolver $emailResolver;
    /**
     * @var DirectoryHelper
     */
    private DirectoryHelper $directoryHelper;

    /**
     * CartSummaryFormDecorator constructor.
     *
     * @param ImageHelper $imageHelper
     * @param ItemResolverInterface $itemResolver
     * @param ShippingMethodConverter $shippingMethodConverter
     * @param LogoPathResolver $logoPathResolver
     * @param FormKey $formKey
     * @param QuoteEmailResolver $emailResolver
     * @param DirectoryHelper $directoryHelper
     */
    public function __construct(
        ImageHelper $imageHelper,
        ItemResolverInterface $itemResolver,
        ShippingMethodConverter $shippingMethodConverter,
        LogoPathResolver $logoPathResolver,
        FormKey $formKey,
        QuoteEmailResolver $emailResolver,
        DirectoryHelper $directoryHelper
    ) {
        $this->imageHelper = $imageHelper;
        $this->itemResolver = $itemResolver;
        $this->shippingMethodConverter = $shippingMethodConverter;
        $this->logoPathResolver = $logoPathResolver;
        $this->formKey = $formKey;
        $this->emailResolver = $emailResolver;
        $this->directoryHelper = $directoryHelper;
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
        $endpoints = $this->buildEndpoints($this->flaggedBaseUrl($flagged));
        $data = $this->buildPayload($quote, $endpoints) + $this->buildStaticData($quote);

        return $flagged . $this->buildCartDataScript($data, $quote);
    }

    /**
     * The cartDataReady payload on its own, for the cart-update endpoint to answer a shopper
     * change with.
     *
     * Same builders as the payload {@see decorate} injects — the checkout-form adopts the reply
     * in place of the one it booted with, so the two must be produced by one piece of code — plus
     * `reloadUrl`, the re-solicited form URL. The injected script compares it against the URL the
     * iframe is showing and reloads onto the new order when they differ; it never reaches the
     * form itself. See {@see buildCartDataScript}.
     *
     * @param string $form Identification form HTML returned by the re-solicit.
     * @param Quote $quote Re-solicited quote, already mutated and re-collected.
     *
     * @return array<string, mixed>
     */
    public function buildCartData(string $form, Quote $quote): array
    {
        $baseUrl = $this->flaggedBaseUrl($this->appendShowCartFlag($form));

        $data = $this->buildPayload($quote, $this->buildEndpoints($baseUrl)) + $this->buildStaticData($quote);

        // Omitted rather than sent empty: with no usable URL there is nothing to reload onto.
        if ($baseUrl !== null) {
            $data['reloadUrl'] = $baseUrl;
        }

        return $data;
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
     * Builds the part of the cartDataReady payload a shopper change can move: what the CartSummary
     * page renders that the solicited order does not carry — or carries frozen at the figures the
     * boot-time solicit produced — minus {@see buildStaticData}.
     *
     * @param Quote $quote
     * @param array<string, string> $endpoints Order-scoped form endpoints, possibly empty.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Quote $quote, array $endpoints): array
    {
        $address = $this->buildShippingAddress($quote);

        $data = [
            'type' => 'cartDataReady',
            'email' => $this->emailResolver->resolve($quote),
            'address' => $address,
            'shippingMethods' => $this->buildShippingMethods($quote),
            'shippingAddresses' => [$address],
            'totalWithTax' => $this->buildTotalWithTax($quote),
        ];

        // Omitted rather than sent as 0: to the checkout-form a missing shipping cost is "not
        // known yet" and renders as a dash, while 0 is free shipping. A quote with no rate
        // applied has no shipping cost at all, and calling that free would be a lie the shopper
        // reads as a promise.
        $shippingCostWithTax = $this->buildShippingCostWithTax($quote);
        if ($shippingCostWithTax !== null) {
            $data['shippingCostWithTax'] = $shippingCostWithTax;
        }

        // Omitted rather than sent empty: the checkout-form only overwrites what it receives.
        if ($endpoints !== []) {
            $data['endpoints'] = $endpoints;
        }

        // Presence is the whole signal: the express address sheet renders the province selector
        // when the key is there and skips it when it is not, so an empty list is never sent.
        $regionOptions = $this->buildRegionOptions((string)$quote->getShippingAddress()->getCountryId());
        if ($regionOptions !== []) {
            $data['regionOptions'] = $regionOptions;
        }

        return $data;
    }

    /**
     * The quote's grand total in cents, tax included — the "Total a pagar hoy" line.
     *
     * Deliberately the exact expression {@see \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder}
     * sends as the order's `order_total_with_tax`, off the same quote in the same request, so the
     * figure the shopper confirms is the figure SeQura was asked to fund rather than a second
     * opinion assembled here. Adding the order lines up instead would drift the moment the total
     * includes something that is not one of them.
     *
     * Quote currency, not base: `getGrandTotal()` is the side of the pair the builder declares as
     * `cart.currency = getQuoteCurrencyCode()`, and the side {@see buildShippingMethods} already
     * converts its rates into.
     *
     * Deliberately not part of {@see buildStaticData}: every change the shopper makes re-solicits,
     * and a re-solicit is precisely when this moves.
     *
     * @param Quote $quote
     *
     * @return int
     */
    private function buildTotalWithTax(Quote $quote): int
    {
        return (int)round(100 * (float)$quote->getGrandTotal());
    }

    /**
     * The shipping cost the quote actually applied, in cents and tax included, or null when the
     * quote has no shipping to cost.
     *
     * The address's own `shipping_incl_tax`, which is the exact expression
     * {@see \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder} sends as the order's
     * `handling` line — the line the checkout-form has been reading this figure off all along, so
     * preferring this one changes nothing but its freshness. Deliberately not the applied rate's
     * converted price from {@see buildShippingMethods}: that is the carrier's quote, while this is
     * what the quote's totals collector settled on and therefore what the grand total above
     * contains. They normally agree, and when they do not it is this one that makes the three
     * summary rows add up.
     *
     * Quote currency on both counts: Magento keeps `shipping_incl_tax` on the address next to its
     * `base_` twin, and the un-prefixed one is the converted one — the same side of the pair
     * `getGrandTotal()` is on.
     *
     * Null when no rate is applied: an addressless quote, or one whose collected rates have not
     * been chosen from. Not 0 — see the caller.
     *
     * @param Quote $quote
     *
     * @return int|null
     */
    private function buildShippingCostWithTax(Quote $quote): ?int
    {
        $shippingAddress = $quote->getShippingAddress();
        if ((string)$shippingAddress->getShippingMethod() === '') {
            return null;
        }

        return (int)round(100 * (float)$shippingAddress->getShippingInclTax());
    }

    /**
     * The regions the delivery country accepts, as the checkout-form's {id, name} options, or an
     * empty list when it needs none.
     *
     * Magento refuses placeOrder() without a region_id for every country in
     * `general/region/state_required` (39 in a stock store), and it enumerates the acceptable ones
     * in `directory_country_region` — so free text cannot satisfy it and the shopper has to pick
     * from this list. `id` is Magento's own region_id: the form carries it back untouched, which
     * is why no name or code mapping has to agree across the two systems.
     *
     * Empty for a country that requires no region, and empty for one that requires one but has no
     * rows — that second case would be a store misconfiguration, and offering an empty selector is
     * worse than offering none.
     *
     * Deliberately not part of {@see buildStaticData}: an address edit can move the country, and
     * with it the whole list.
     *
     * @param string $countryId ISO2 delivery country, possibly empty on an addressless quote.
     *
     * @return array<int, array<string, string>>
     */
    private function buildRegionOptions(string $countryId): array
    {
        if ($countryId === '' || !$this->directoryHelper->isRegionRequired($countryId)) {
            return [];
        }

        // Magento's own region source, the one the regular checkout's province selector is built
        // from: the directory region collection, keyed by country and already carrying the
        // store-locale name. Not the table — the helper owns the join and the sort order.
        $rows = $this->directoryHelper->getRegionData()[$countryId] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $options = [];
        foreach ($rows as $regionId => $row) {
            $name = is_array($row) && isset($row['name']) && is_scalar($row['name']) ? (string)$row['name'] : '';
            if ($name === '') {
                continue;
            }

            $options[] = ['id' => (string)$regionId, 'name' => $name];
        }

        return $options;
    }

    /**
     * The half of the payload nothing the shopper does on the CartSummary page can change: the
     * store identity and the cart item thumbnails.
     *
     * Sent by updates too, not only by the initial solicit: an update may answer with a reload
     * onto a freshly minted order, and the document that boots into it never sees the boot-time
     * payload — that interval finished long ago — so leaving this out would drop the store name,
     * the logo and the item thumbnails. It costs one ImageHelper resolution per cart line, which
     * is nothing next to the round trip to SeQura the same request already made.
     *
     * @param Quote $quote
     *
     * @return array<string, mixed>
     */
    private function buildStaticData(Quote $quote): array
    {
        $data = [
            'storeName' => $quote->getStore()->getFrontendName(),
            'itemImages' => $this->buildItemImages($quote),
        ];

        // Omitted rather than sent empty: the checkout-form rejects a store logo that is not an
        // absolute http(s) URL.
        $storeLogoUrl = $this->buildStoreLogoUrl($quote);
        if ($storeLogoUrl !== null) {
            $data['storeLogoUrl'] = $storeLogoUrl;
        }

        return $data;
    }

    /**
     * Builds the script that drives both directions of the CartSummary conversation: it posts
     * the cartDataReady message into the form iframe, and it relays the `Sequra.cartUpdate`
     * message the form posts back out (address saved, carrier picked, email saved) to the
     * cart-update endpoint, forwarding that endpoint's refreshed payload on to the same iframe.
     *
     * That payload carries a `reloadUrl` the script strips before forwarding. When it differs
     * from the URL the iframe is on, the re-solicit minted a new order and the iframe is
     * pointed at it instead of being patched in place — see the branch itself for why nothing
     * else can be done about a new order's secret.
     *
     * Every relayed update is answered, success or not: a request that does not yield a usable
     * payload sends `{type: 'cartUpdateFailed', status: <int>}` instead. The status is the one
     * the endpoint deliberately chose ({@see \Sequra\Core\Controller\ExpressCheckout\CartUpdate}
     * maps 400/422/429/500), or 0 when no response arrived, and is all the form gets — enough to
     * separate "your change was refused" from "try again", with no server text leaked into the
     * page.
     *
     * The reply is targeted at the iframe's data-base-url origin, never '*', exactly like the
     * initial message; inbound messages from any other origin are ignored.
     *
     * The endpoint is a state-changing frontend POST, so the request carries Magento's session
     * form key — minted here rather than read from the form_key cookie, which is set by the page
     * cache layer and cannot be relied on.
     *
     * @param array<string, mixed> $data cartDataReady payload.
     * @param Quote $quote
     *
     * @return string
     */
    private function buildCartDataScript(array $data, Quote $quote): string
    {
        $payload = json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
        $updateUrl = json_encode(
            $quote->getStore()->getUrl('sequra/expresscheckout/cartupdate'),
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
        );
        $formKey = json_encode($this->formKey->getFormKey(), JSON_HEX_TAG);

        $attempts = self::POST_MESSAGE_ATTEMPTS;
        $interval = self::POST_MESSAGE_INTERVAL_MS;
        $loadTimeout = self::RELOAD_LOAD_TIMEOUT_MS;

        return <<<HTML

<script type="text/javascript">
    (function () {
        var payload = {$payload};
        var updateUrl = {$updateUrl};
        var formKey = {$formKey};

        function formIframe() {
            var el = document.getElementById(window.SequraFormElement);

            return (el && el.contentWindow && el.getAttribute('data-base-url')) ? el : null;
        }

        function formOrigin(el) {
            return new URL(el.getAttribute('data-base-url')).origin;
        }

        function send(el, data) {
            el.contentWindow.postMessage(data, formOrigin(el));
        }

        // Tells the form the change was not applied. `status` is the HTTP status the endpoint
        // answered with — 400/422 the shopper's change was refused, 429/500 the store could not
        // deal with it right now — or 0 when there was no answer at all. Never the server's
        // message: the form owns what the shopper reads.
        function sendFailure(status) {
            var el = formIframe();
            if (el) {
                send(el, { type: 'cartUpdateFailed', status: status });
            }
        }

        // One timer for the page: an update starting its own loop retires the one still
        // running, so two changes in quick succession cannot leave two loops posting.
        var timer = null;

        function postWithRetries(data) {
            var attempts = 0;
            clearInterval(timer);
            timer = setInterval(function () {
                var el = formIframe();
                if (el) {
                    send(el, data);
                }
                if (++attempts >= {$attempts}) {
                    clearInterval(timer);
                }
            }, {$interval});
        }

        // Runs once the iframe has finished navigating. The document being replaced is still
        // alive and still listening while the new one loads, and its acknowledgement would stop
        // the retries before the new document has booted — leaving it with no cart data at all.
        function onceLoaded(el, run) {
            var ran = false;

            function go() {
                if (ran) {
                    return;
                }
                ran = true;
                el.removeEventListener('load', go);
                run();
            }

            el.addEventListener('load', go);
            // Backstop for a load event that never arrives; the retries are bounded either way.
            setTimeout(go, {$loadTimeout});
        }

        postWithRetries(payload);

        // One listener per page. It resolves the iframe and its origin on every message, so a
        // second solicit (cancel, then retry) is served by the listener the first one installed
        // instead of stacking duplicate listeners that would each fire their own update.
        if (window.SequraCartUpdateBound) {
            return;
        }
        window.SequraCartUpdateBound = true;

        window.addEventListener('message', function (event) {
            var el = formIframe();
            if (!el || event.origin !== formOrigin(el)) {
                return;
            }

            var message = event.data;
            if (typeof message === 'string') {
                try {
                    message = JSON.parse(message);
                } catch (e) {
                    return;
                }
            }
            if (!message) {
                return;
            }

            // The form got the payload, so stop offering it. Left running, the retries keep
            // re-delivering a payload the form has already applied: every arrival closes an open
            // bottom sheet and, once an update has been applied, re-applying the boot-time one
            // puts the shopper's own change back to what it was.
            if (message.action === 'Sequra.cartDataReceived') {
                clearInterval(timer);

                return;
            }

            if (message.action !== 'Sequra.cartUpdate') {
                return;
            }

            var body = new URLSearchParams();
            body.append('form_key', formKey);
            body.append('payload', JSON.stringify({
                address: message.address,
                shippingMethodReference: message.shippingMethodReference,
                email: message.email
            }));

            fetch(updateUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        sendFailure(response.status);

                        return null;
                    }

                    return response.json();
                })
                .then(function (data) {
                    var target = formIframe();
                    if (!data || !target) {
                        return;
                    }

                    // Ours, never the form's: it fingerprints the whole payload to decide the
                    // update is a new one, so a key it does not know about would pollute that.
                    var reloadUrl = data.reloadUrl;
                    delete data.reloadUrl;

                    if (reloadUrl && reloadUrl !== target.getAttribute('data-base-url')) {
                        // Nothing must be offered to the document on its way out, including a
                        // loop still running from boot.
                        clearInterval(timer);

                        // The re-solicit minted a new order — SeQura will not reuse one whose
                        // total moved, nor one already carrying an identification, which is
                        // every recognised shopper here since the OTP runs before the summary.
                        // A new order means a new uuid AND a new secret, and only the metadata
                        // endpoint can be re-derived from the URL: the identification endpoint
                        // needs the secret, which never reaches this server. A document left on
                        // the old order would keep confirming against a deleted one — charging
                        // the shopper with no shop order behind it — so point the iframe at the
                        // new order rather than patching the document in place.
                        target.setAttribute('data-base-url', reloadUrl);
                        if (window.SequraFormInstance) {
                            // Through the loader, which re-reads data-base-url and runs its own
                            // buildIframeUrl: assigning src here would drop the webview
                            // parameters it appends inside the SeQura app. Re-adding its message
                            // listener is a no-op — same function on the same instance.
                            window.SequraFormInstance.setElement(window.SequraFormElement);
                        } else {
                            target.src = reloadUrl;
                        }

                        // The reloaded document boots empty and misses anything sent before it
                        // is listening, so keep offering the payload while it comes up — but only
                        // once it is the one listening.
                        onceLoaded(target, function () {
                            postWithRetries(data);
                        });
                    } else {
                        send(target, data);
                    }
                })
                .catch(function () {
                    // No usable answer: the request never completed, or a 200 whose body did not
                    // parse. Either way the form has been left waiting, so tell it so.
                    sendFailure(0);
                });
        });
    })();
</script>
HTML;
    }

    /**
     * The iframe URL the solicit returned, read off the flagged HTML, or null when the snippet
     * carries no usable one.
     *
     * @param string $form Form HTML, already carrying the show_cart flag.
     *
     * @return string|null
     */
    private function flaggedBaseUrl(string $form): ?string
    {
        if (!preg_match('/\bdata-base-url="([^"]+)"/', $form, $matches)) {
            return null;
        }

        // In the HTML the attribute value is escaped (&amp;), unlike the value getAttribute()
        // hands the injected script at runtime.
        $baseUrl = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);

        return preg_match('#^https?://#i', $baseUrl) ? $baseUrl : null;
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
     * @param string|null $baseUrl Flagged iframe URL, or null when the snippet carried none.
     *
     * @return array<string, string>
     */
    private function buildEndpoints(?string $baseUrl): array
    {
        if ($baseUrl === null) {
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
     * Maps the quote's collected shipping rates to the checkout-form ShippingMethod shape.
     * The rate applied to the quote goes first: the checkout-form preselects the first method,
     * and the solicited order total was computed with that rate.
     *
     * Each rate goes through Magento's own ShippingMethodConverter — the same one
     * ShippingMethodManagement::getList feeds the regular checkout — rather than a hand-rolled
     * copy, so the price is taxed AND converted from the store's base currency into the quote
     * currency. On a store displaying a currency other than its base one, doing the tax half only
     * would put a base-currency shipping line next to a quote-currency total.
     *
     * @param Quote $quote
     *
     * @return array<int, array<string, string|int>>
     */
    private function buildShippingMethods(Quote $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();
        $appliedCode = (string)$shippingAddress->getShippingMethod();
        $quoteCurrencyCode = (string)$quote->getQuoteCurrencyCode();

        $methods = [];
        foreach ($shippingAddress->getAllShippingRates() as $rate) {
            $converted = $this->shippingMethodConverter->modelToDataObject($rate, $quoteCurrencyCode);
            if (!$converted->getAvailable()) {
                continue;
            }

            $reference = (string)$rate->getCode();
            // Carrier as the method name and method title as the description, matching the
            // CartSummary card ("GLS" / "Entrega a domicilio 2-3 días").
            $method = [
                'reference' => $reference,
                'name' => (string)($converted->getCarrierTitle() ?: $reference),
                'costWithTax' => (int)round((float)$converted->getPriceInclTax() * 100),
                'description' => (string)$converted->getMethodTitle(),
            ];

            if ($reference === $appliedCode) {
                array_unshift($methods, $method);
            } else {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * The address's Magento region id as the string the checkout-form carries, or '' when the
     * address has none.
     *
     * @param Address $address
     *
     * @return string
     */
    private function regionIdOf(Address $address): string
    {
        $regionId = $address->getRegionId();

        return is_scalar($regionId) && (int)$regionId > 0 ? (string)$regionId : '';
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
            try {
                // getFinalProduct picks parent or child for configurable/grouped/bundle lines and
                // honours checkout/cart/configurable_product_image, the way Magento's own cart and
                // mini-cart thumbnails do (Checkout\CustomerData\DefaultItem).
                $product = $this->itemResolver->getFinalProduct($item);
                // getFinalProduct is typed to the interface; ImageHelper wants the concrete
                // model, which is what every implementation hands back (and what
                // Checkout\CustomerData\DefaultItem feeds it).
                // @phpstan-ignore-next-line
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
     * `regionId` is echoed back so the round trip is lossless: it is the id the shopper picked out
     * of `regionOptions` (or, before they have seen the sheet, the one the solicit derived), and
     * without it the province selector would reopen blank and the address card would stop naming
     * the province the moment the store answers.
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
            'fullName' => trim((string)$address->getName()),
            'givenName' => (string)$address->getFirstname(),
            'surnames' => (string)$address->getLastname(),
            'addressLine1' => is_array($street) ? implode(', ', array_filter($street)) : (string)$street,
            'postalCode' => (string)$address->getPostcode(),
            'city' => (string)$address->getCity(),
            'countryCode' => (string)$address->getCountryId(),
            'regionId' => $this->regionIdOf($address),
            'mobilePhone' => (string)$address->getTelephone(),
        ];
    }
}
