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

        // The solicited snippet loads SeQura's checkout JS asynchronously, so window.SequraFormInstance
        // appears only once that script runs. Poll for it, but give up after MAX_ATTEMPTS so a failed/
        // blocked library load can't leave a permanent 100ms timer and a stuck blocking spinner.
        var POLL_INTERVAL_MS = 100,
            MAX_ATTEMPTS = 100; // ~10s

        function waitForSequraFormInstance(callback, onTimeout, attempt) {
            attempt = attempt || 0;

            if (typeof window.SequraFormInstance !== 'undefined') {
                callback();

                return;
            }

            if (attempt >= MAX_ATTEMPTS) {
                if (onTimeout) {
                    onTimeout();
                }

                return;
            }

            setTimeout(waitForSequraFormInstance, POLL_INTERVAL_MS, callback, onTimeout, attempt + 1);
        }

        return {
            /**
             * Appends the solicited identification form and shows it once SequraFormInstance is ready.
             *
             * @param {String} identificationForm The identification form HTML returned by the solicit endpoint.
             * @param {Function} [onShown] Called once the form is visible (e.g. to remove a caller-owned spinner).
             * @param {Function} [onFailed] Called if the form instance never appears (so the caller can
             *                               clear its spinner instead of leaving the shopper stuck).
             */
            showIdentificationForm: function (identificationForm, onShown, onFailed) {
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
                }, function () {
                    // Library script failed to expose SequraFormInstance — stop loaders, clear the
                    // caller's spinner, and surface the inline unavailable message instead of hanging.
                    fullScreenLoader.stopLoader();

                    if (onFailed) {
                        onFailed();
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
