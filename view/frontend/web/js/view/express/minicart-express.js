/**
 * SeQura Express Checkout — mini-cart button binder.
 *
 * Initialised once per page via a global `text/x-magento-init` ("*" target) rendered in
 * before.body.end, so the click handler is present on every storefront page (home,
 * product, category, …). The button HTML is injected into the `cart` customer-data
 * `extra_actions` slot and re-rendered dynamically, so a delegated handler on `document`
 * reliably wires every render to the shared solicit flow — identical behaviour to the
 * cart-page button. The `--minicart` class scopes it so it never double-handles the
 * cart-page button.
 */
define(
    [
        'jquery'
    ],
    function ($) {
        'use strict';

        return function () {
            $(document)
                .off('click.sequraExpressMinicart')
                .on(
                    'click.sequraExpressMinicart',
                    '.sequra-express-checkout--minicart .sequra-express-checkout-button',
                    function (event) {
                        var button = this;

                        event.preventDefault();

                        require(
                            ['Sequra_Core/js/express/solicit-handler'],
                            function (solicit) {
                                solicit(button);
                            },
                            function (error) {
                                if (window.console) {
                                    window.console.error('SeQura Express Checkout: failed to load solicit handler', error);
                                }
                            }
                        );
                    }
                );
        };
    }
);
