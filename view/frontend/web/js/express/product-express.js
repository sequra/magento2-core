/**
 * SeQura Express Checkout — product detail page controller.
 *
 * The button itself is rendered by the shared CDN library (sequra-checkout.min.js): it mounts an
 * iframe into the `.sequra-express-checkout-button` element and owns the click — on click it
 * fetches the element's `data-url` (GET, raw HTML) and opens the identification form. This
 * controller therefore never binds a click on the button; it keeps the mount element in sync:
 *
 *  - readiness gating: the wrapper is CSS-disabled (pointer-events) until the required options
 *    for the product type are selected, so the click can't reach the library iframe early;
 *  - `data-url` is rewritten with the serialized add-to-cart form on every change (the library
 *    reads it at click time, no refresh call needed);
 *  - `data-amount` follows the priceBox final price (informational; the solicit amount is
 *    computed server-side).
 *
 * Login gating is server-driven: the click always goes through to the library, and the solicit
 * endpoint answers 401 for guests — surfaced via the shared library-button error handler, which
 * opens the login pop-up and retries the solicit after a successful login. Client-side login
 * state (window.isCustomerLoggedIn, the customer-data section) is deliberately not consulted:
 * both are stale or absent on full-page-cached pages, which made the pop-up appear for logged
 * in shoppers. 422 (not eligible) renders the inline message instead.
 */
define(
    [
        'jquery',
        'Sequra_Core/js/express/library-button'
    ],
    function ($, libraryButton) {
        'use strict';

        return function (config, element) {
            var $wrapper = $(element),
                $mount = $wrapper.find('.sequra-express-checkout-button'),
                $form = $('#product_addtocart_form'),
                productType = config.productType,
                lastAmount = null;

            /**
             * Whether the currently selected options satisfy the minimum needed to solicit.
             *
             * @returns {Boolean}
             */
            function isReady() {
                if (!$form.length) {
                    return false;
                }

                var qty = parseFloat($form.find('[name="qty"]').first().val());
                if (!isNaN(qty) && qty < 1) {
                    return false;
                }

                if (productType === 'configurable') {
                    var configurableReady = true;
                    $form.find('[name^="super_attribute["]').each(function () {
                        if (!$(this).val()) {
                            configurableReady = false;
                        }
                    });

                    return configurableReady;
                }

                if (productType === 'grouped') {
                    var anyQty = false;
                    $form.find('[name^="super_group["]').each(function () {
                        if (parseFloat($(this).val()) > 0) {
                            anyQty = true;
                        }
                    });

                    return anyQty;
                }

                if (productType === 'bundle') {
                    var bundleReady = true;
                    $form.find('[name^="bundle_option["]').filter('[data-validate*="required"],.required-bundle-option,[required]').each(function () {
                        if (!$(this).val()) {
                            bundleReady = false;
                        }
                    });

                    return bundleReady;
                }

                // simple and any other type: server-side validation is the gate.
                return true;
            }

            /**
             * Rewrites data-url with the current add-to-cart form state.
             *
             * The library reads data-url at click time, so no refresh call is needed.
             */
            function syncUrl() {
                $mount.attr('data-url', config.productSolicitUrl + '?' + $form.serialize());
            }

            /**
             * Syncs data-amount from the priceBox final price and re-prices the mounted button.
             */
            function syncAmount() {
                var attr = $('.product-info-main [data-price-type="finalPrice"]').first().attr('data-price-amount'),
                    price = parseFloat(attr),
                    cents = isNaN(price) ? 0 : Math.round(price * 100);

                if (cents === lastAmount) {
                    return;
                }

                lastAmount = cents;
                $mount.attr('data-amount', cents);
                libraryButton.refreshButtons();
            }

            /**
             * Re-evaluates readiness and re-syncs the mount element after an option/qty change.
             */
            function refresh() {
                $wrapper.toggleClass('sequra-express-checkout--disabled', !isReady());
                syncUrl();
                syncAmount();
            }

            // The click happens inside the library's iframe and cannot be intercepted, so the
            // solicit response is the gate: the shared error handler opens the login pop-up on
            // 401 and retries, or shows the inline message on 422.
            libraryButton.attachErrorHandler();

            $form.on('change', refresh);
            $form.on('input', '[name="qty"]', refresh);
            // Visual swatches update hidden super_attribute inputs asynchronously after a click.
            $form.on('click', '.swatch-option', function () {
                setTimeout(refresh, 50);
            });
            // Magento's priceBox recalculates the final price on option/variant changes.
            $('.product-info-main [data-role=priceBox]').on('priceUpdated', function () {
                setTimeout(refresh, 0);
            });

            refresh();
        };
    }
);
