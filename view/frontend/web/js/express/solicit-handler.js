/**
 * SeQura Express Checkout — shared click flow.
 *
 * On click: a guest is sent through Magento's login pop-up first (auth-popup), then the
 * solicit runs; a logged in customer solicits directly. The solicit POSTs to the customer
 * solicit endpoint and opens the returned identification form via window.SequraFormInstance.
 * If the (now logged in) customer is not eligible the endpoint returns HTTP 422 and the
 * button is replaced with an inline "not available" message.
 *
 * Note: this deliberately avoids Magento_Checkout/js/model/{url-builder,quote}, which read
 * window.checkoutConfig at load time and throw on non-checkout pages (home, product, …).
 * The REST URL is built via mage/url; the cart id is left to the `mine` route (forced
 * server-side).
 */
define(
    [
        'jquery',
        'mage/url',
        'mage/translate',
        'Magento_Customer/js/model/customer',
        'Sequra_Core/js/express/auth-popup',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'mage/storage',
        'mage/cookies'
    ],
    function ($, url, $t, customer, authPopup, fullScreenLoader, errorProcessor, storage) {
        'use strict';

        // HTTP status the solicit endpoint returns when the customer is not eligible.
        var HTTP_NOT_ELIGIBLE = 422;

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
         * Replaces the button with an inline "not available" message.
         *
         * @param {jQuery} $button
         */
        function showUnavailable($button) {
            $button.closest('.sequra-express-checkout').html(
                $('<span/>', {
                    'class': 'sequra-express-checkout-unavailable',
                    'text': $t('SeQura is not available for your account.')
                })
            );
        }

        /**
         * Solicits an Express Checkout order for the current cart and renders the form.
         *
         * @param {jQuery} $button
         */
        function postSolicit($button) {
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

                if (response && response.status === HTTP_NOT_ELIGIBLE) {
                    showUnavailable($button);

                    return;
                }

                errorProcessor.process(response);
            }).always(function () {
                $button.prop('disabled', false);
            });
        }

        /**
         * Express Checkout click entry point.
         *
         * @param {HTMLElement|jQuery} button The clicked Express Checkout button.
         */
        return function (button) {
            var $button = $(button);

            if (!customer.isLoggedIn()) {
                authPopup.open().then(
                    function () {
                        postSolicit($button);
                    },
                    function () {
                        // Pop-up closed without logging in — nothing to do.
                    }
                );

                return;
            }

            postSolicit($button);
        };
    }
);
