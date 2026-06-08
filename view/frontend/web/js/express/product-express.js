/**
 * SeQura Express Checkout — product detail page controller.
 *
 * Initialized via x-magento-init with { productSolicitUrl, productId, productType }. Passively
 * enables the express button once the required options for the product type are selected, and on
 * click serializes the add-to-cart form and POSTs it to the product-solicit endpoint, which builds
 * a detached temporary quote and returns the identification form. Guests are routed through the
 * shared login pop-up first (no reload). The shopper's real cart is never touched.
 *
 * The passive enable/disable is a lightweight UX hint; the authoritative gate is the add-to-cart
 * form's own validation('isValid') run on click, which enforces every required option (including
 * custom options and bundle/grouped selections).
 */
define(
    [
        'jquery',
        'Sequra_Core/js/express/customer-state',
        'Sequra_Core/js/express/auth-popup',
        'Sequra_Core/js/express/identification-form',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'mage/storage',
        'mage/validation'
    ],
    function ($, customerState, authPopup, identificationForm, fullScreenLoader, errorProcessor, storage) {
        'use strict';

        // HTTP status the solicit endpoint returns when the request is not eligible.
        var HTTP_NOT_ELIGIBLE = 422;

        return function (config, element) {
            var $button = $(element).find('.sequra-express-checkout-button'),
                $form = $('#product_addtocart_form'),
                productType = config.productType;

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

                // simple, bundle and any other type: the click-time form validation is the gate.
                return true;
            }

            /**
             * Re-evaluates button readiness after an option/qty change.
             */
            function refresh() {
                $button.prop('disabled', !isReady());
            }

            /**
             * Serializes the add-to-cart form and solicits the Express Checkout order.
             */
            function postSolicit() {
                $button.prop('disabled', true);
                fullScreenLoader.startLoader();

                storage.post(
                    config.productSolicitUrl,
                    JSON.stringify({ payload: $form.serialize() })
                ).done(function (response) {
                    identificationForm.showIdentificationForm(response);
                }).fail(function (response) {
                    fullScreenLoader.stopLoader();

                    if (response && response.status === HTTP_NOT_ELIGIBLE) {
                        identificationForm.showUnavailable($button);

                        return;
                    }

                    errorProcessor.process(response);
                }).always(function () {
                    refresh();
                });
            }

            /**
             * Click entry point: validates the form, logs a guest in first, then solicits.
             */
            function onClick() {
                if ($form.validation && !$form.validation('isValid')) {
                    return;
                }

                if (!customerState.isLoggedIn()) {
                    authPopup.open().then(postSolicit, function () {
                        // Pop-up closed without logging in — nothing to do.
                    });

                    return;
                }

                postSolicit();
            }

            $form.on('change', refresh);
            $form.on('input', '[name="qty"]', refresh);
            // Visual swatches update hidden super_attribute inputs asynchronously after a click.
            $form.on('click', '.swatch-option', function () {
                setTimeout(refresh, 50);
            });
            $button.on('click', onClick);

            refresh();
        };
    }
);
