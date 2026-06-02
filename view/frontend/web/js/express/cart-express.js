/**
 * SeQura Express Checkout — cart page button.
 *
 * Wires the placeholder Express Checkout button to the solicit endpoint and opens
 * the returned identification form via the same SequraFormInstance flow used by
 * the regular checkout payment method.
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'mage/storage',
        'mage/cookies'
    ],
    function ($, quote, urlBuilder, fullScreenLoader, errorProcessor, storage) {
        'use strict';

        function waitForSequraFormInstance(callback) {
            if (typeof window.SequraFormInstance === 'undefined') {
                setTimeout(waitForSequraFormInstance, 100, callback);
                return;
            }

            callback();
        }

        function showIdentificationForm(identificationForm) {
            $('body').append(identificationForm);

            waitForSequraFormInstance(function () {
                window.SequraFormInstance.setCloseCallback(function () {
                    fullScreenLoader.stopLoader();
                    // Additional stop since in some cases Magento keeps one loader on the page
                    fullScreenLoader.stopLoader();
                    window.SequraFormInstance.defaultCloseCallback();
                    delete window.SequraFormInstance;
                });

                window.SequraFormInstance.show();
                fullScreenLoader.stopLoader();
            });
        }

        return function (config, element) {
            var $root = $(element),
                $button = $root.find('.sequra-express-checkout-button');

            $button.on('click', function () {
                var serviceUrl = urlBuilder.createUrl('/sequra_core/express-checkout/carts/mine/solicit', {});

                $button.prop('disabled', true);
                fullScreenLoader.startLoader();

                storage.post(
                    serviceUrl,
                    JSON.stringify({
                        cartId: quote.getQuoteId(),
                        form_key: $.mage.cookies.get('form_key')
                    })
                ).done(function (response) {
                    showIdentificationForm(response);
                }).fail(function (response) {
                    fullScreenLoader.stopLoader();
                    errorProcessor.process(response);
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });
        };
    }
);
