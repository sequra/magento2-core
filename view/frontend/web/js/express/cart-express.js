/**
 * SeQura Express Checkout — cart page button.
 *
 * Binds the cart-page Express Checkout button to the shared solicit flow.
 */
define(
    [
        'jquery',
        'Sequra_Core/js/express/solicit-handler'
    ],
    function ($, solicit) {
        'use strict';

        return function (config, element) {
            $(element).find('.sequra-express-checkout-button').on('click', function () {
                solicit(this);
            });
        };
    }
);
