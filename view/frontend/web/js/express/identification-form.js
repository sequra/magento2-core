/**
 * SeQura Express Checkout — shared identification-form display.
 *
 * Renders the solicited identification form via the global window.SequraFormInstance and exposes
 * the inline "not available" fallback. Shared by every solicit surface (cart, mini-cart, product
 * page) so the post-solicit display logic lives in one place.
 */
define(
    [
        'jquery',
        'mage/translate',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, $t, fullScreenLoader) {
        'use strict';

        function waitForSequraFormInstance(callback) {
            if (typeof window.SequraFormInstance === 'undefined') {
                setTimeout(waitForSequraFormInstance, 100, callback);

                return;
            }

            callback();
        }

        return {
            /**
             * Appends the solicited identification form and shows it once SequraFormInstance is ready.
             *
             * @param {String} identificationForm The identification form HTML returned by the solicit endpoint.
             * @param {Function} [onShown] Called once the form is visible (e.g. to remove a caller-owned spinner).
             */
            showIdentificationForm: function (identificationForm, onShown) {
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

                    if (onShown) {
                        onShown();
                    }
                });
            },

            /**
             * Replaces the button with an inline "not available" message.
             *
             * @param {jQuery} $button The Express Checkout button to replace.
             */
            showUnavailable: function ($button) {
                $button.closest('.sequra-express-checkout').html(
                    $('<span/>', {
                        'class': 'sequra-express-checkout-unavailable',
                        'text': $t('SeQura is not available for your account.')
                    })
                );
            }
        };
    }
);
