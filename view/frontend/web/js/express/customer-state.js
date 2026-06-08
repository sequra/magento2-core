/**
 * SeQura Express Checkout — FPC-safe login state.
 *
 * Magento_Customer/js/model/customer derives isLoggedIn from window.isCustomerLoggedIn, a
 * server-rendered global that is baked into full-page-cached pages (e.g. the product page) as the
 * guest value — so on a cached page a logged in shopper reads as a guest. The `customer`
 * customer-data section is private content fetched per-user and never cached, so its `firstname`
 * is the reliable login signal across cached and uncached pages alike.
 */
define(
    [
        'Magento_Customer/js/customer-data'
    ],
    function (customerData) {
        'use strict';

        return {
            /**
             * Whether the current shopper is a logged in customer.
             *
             * @returns {Boolean}
             */
            isLoggedIn: function () {
                var customer = customerData.get('customer')();

                return !!(customer && customer.firstname);
            }
        };
    }
);
