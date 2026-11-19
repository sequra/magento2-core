/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'jquery',
        'underscore',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'ko',
        'mage/cookies'
    ],
    function(
        $,
        _,
        quote,
        customer,
        urlBuilder,
        storage,
        ko
    ) {
        'use strict';
        return {
            paymentMethods: ko.observable([]),

            /**
             * Retrieve the list of available payment methods from SeQura
             */
            retrievePaymentMethods: function() {
                // url for guest users
                var serviceUrl = urlBuilder.createUrl(
                    '/sequra_core/guest-carts/:cartId/retrieve-sequra_payment-methods', {
                        cartId: quote.getQuoteId(),
                    });

                // url for logged in users
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl(
                        '/sequra_core/carts/mine/retrieve-sequra_payment-methods', {});
                }

                return storage.post(
                    serviceUrl,
                    JSON.stringify({
                        cartId: quote.getQuoteId(),
                        form_key: $.mage.cookies.get('form_key')
                    })
                );
            },
            fetchIdentificationForm: function (productData) {
                var serviceUrl = urlBuilder.createUrl(
                    '/sequra_core/guest-carts/:cartId/fetch-sequra_payment-form', {
                        cartId: quote.getQuoteId(),
                    });

                // url for logged in users
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl(
                        '/sequra_core/carts/mine/fetch-sequra_payment-form', {});
                }

                return storage.post(
                    serviceUrl,
                    JSON.stringify({
                        cartId: quote.getQuoteId(),
                        form_key: $.mage.cookies.get('form_key'),
                        product_data: productData
                    })
                );
            },
            getPaymentMethods: function() {
                return this.paymentMethods;
            },
            setPaymentMethods: function(paymentMethods) {
                this.paymentMethods(paymentMethods);
            }
        };
    }
);
