/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_SalesRule/js/action/set-coupon-code',
        'Magento_SalesRule/js/action/cancel-coupon',
        'Sequra_Core/js/model/sequra-payment-service'
    ],
    function (
        Component,
        rendererList,
        fullScreenLoader,
        setCouponCodeAction,
        cancelCouponAction,
        sequraPaymentService
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'sequra_payment',
                component: 'Sequra_Core/js/view/payment/method-renderer/sequra-payment'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({
            initialize: function () {
                this._super();

                var retrievePaymentMethods = function (){
                    fullScreenLoader.startLoader();

                    sequraPaymentService.retrievePaymentMethods().done(function(paymentMethods) {
                        sequraPaymentService.setPaymentMethods(paymentMethods);
                        fullScreenLoader.stopLoader();
                    }).fail(function() {
                        console.log('Fetching the payment methods failed!');
                        fullScreenLoader.stopLoader();
                    });
                };
                retrievePaymentMethods();
                //Retrieve payment methods to ensure the amount is updated, when applying the discount code
                setCouponCodeAction.registerSuccessCallback(function () {
                    retrievePaymentMethods();
                });
                //Retrieve payment methods to ensure the amount is updated, when canceling the discount code
                cancelCouponAction.registerSuccessCallback(function () {
                    retrievePaymentMethods();
                });
            }
        });
    }
);
