/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'mage/cookies',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Ui/js/modal/modal'
    ],
    function ($, quote, urlBuilder, storage, cookies, errorProcessor, fullScreenLoader, setPaymentInformation, modal) {
        'use strict';
        return function (messageContainer) {
            var serviceUrl,
                placeOrder = function () {
                    fullScreenLoader.startLoader();
                    if (typeof window.SequraFormInstance === 'undefined') {
                        setTimeout(placeOrder, 100);
                        return;
                    }
                    window.SequraFormInstance.setCloseCallback(function () {
                        fullScreenLoader.stopLoader();
                        window.SequraFormInstance.defaultCloseCallback();
                        delete window.SequraFormInstance;
                    });
                    window.SequraFormInstance.show();
                    fullScreenLoader.stopLoader();
                };

            return setPaymentInformation(messageContainer, quote.paymentMethod()).done(
                function () {
                    serviceUrl = urlBuilder.createUrl('/sequra_core/Submission', {});
                    storage.get(serviceUrl).done(
                        function (response) {
                            $('body').append(response);
                            if($('#sequra-remotesales').length>0) {
                                var options = {
                                    type: 'popup',
                                    responsive: true,
                                    innerScroll: true,
                                    title: 'Venta remota',
                                    buttons: [{
                                        text: $.mage.__('Close'),
                                        class: 'modal-close',
                                        click: function (){
                                            this.closeModal();
                                        }
                                    }]
                                };
                                modal(options, $('#sequra-remotesales'));
                                $("#sequra-remotesales").modal("openModal");
                                return;
                            }
                            placeOrder();
                        }
                    ).fail(
                        function (response) {
                            errorProcessor.process(response, messageContainer);
                            fullScreenLoader.stopLoader();
                        }
                    );
                });
        };
    }
);