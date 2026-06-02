/**
 * SeQura Express Checkout — shared solicit flow.
 *
 * POSTs to the logged-in customer solicit endpoint and opens the returned identification
 * form via window.SequraFormInstance. Shared by the cart-page and mini-cart buttons so
 * both behave identically.
 *
 * Note: this deliberately avoids Magento_Checkout/js/model/{url-builder,quote}, which read
 * window.checkoutConfig at load time and therefore throw on non-checkout pages (home,
 * product, …). The mini-cart button lives on every page, so the REST URL is built via
 * mage/url instead, and the cart id is left to the `mine` route (forced server-side).
 */
define(
    [
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'mage/storage',
        'mage/cookies'
    ],
    function ($, url, fullScreenLoader, errorProcessor, storage) {
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

        /**
         * Builds the customer solicit REST URL, including the store code when it is known.
         *
         * @returns {String}
         */
        function getSolicitUrl() {
            var storeCode = window.checkoutConfig && window.checkoutConfig.storeCode
                ? window.checkoutConfig.storeCode + '/'
                : '';

            return url.build('rest/' + storeCode + 'V1/sequra_core/express-checkout/carts/mine/solicit');
        }

        /**
         * Solicits an Express Checkout order for the current cart and renders the form.
         *
         * @param {HTMLElement|jQuery} button The clicked Express Checkout button.
         */
        return function (button) {
            var $button = $(button);

            $button.prop('disabled', true);
            fullScreenLoader.startLoader();

            storage.post(
                getSolicitUrl(),
                JSON.stringify({
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
        };
    }
);
