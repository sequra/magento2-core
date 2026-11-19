define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/full-screen-loader',
    'Sequra_Core/js/model/sequra-payment-service'
], function (
    $,
    wrapper,
    fullScreenLoader,
    sequraPaymentService
) {
    'use strict';

    return function (shippingInformationAction) {

        return wrapper.wrap(shippingInformationAction, function (originalAction) {
            return originalAction().then(function (result) {
                fullScreenLoader.startLoader();
                sequraPaymentService.retrievePaymentMethods().done(function(paymentMethods) {
                    sequraPaymentService.setPaymentMethods(paymentMethods);
                    fullScreenLoader.stopLoader();
                }).fail(function() {
                    console.log('Fetching the payment methods failed!');
                    fullScreenLoader.stopLoader();
                });
                return result;
            });
        });

    };
});
