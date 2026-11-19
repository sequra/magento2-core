define([
    'Magento_Checkout/js/view/payment/default',
    'ko',
    'jquery',
    'underscore',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/action/set-payment-information',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'Sequra_Core/js/model/sequra-payment-service',
    'Magento_Checkout/js/model/payment/additional-validators',
    "uiRegistry",
    "mage/utils/wrapper"
], function (
    Component,
    ko,
    $,
    _,
    selectPaymentMethodAction,
    setPaymentInformationAction,
    errorProcessor,
    quote,
    checkoutData,
    fullScreenLoader,
    sequraPaymentService,
    additionalValidators,
    uiRegistry,
    wrapper
) {
    'use strict';

    var selectedProduct = ko.observable(null),
        sequraPaymentMethods = ko.observable([]),
        canReloadPayments = ko.observable(true);

    function getSelectedMethodAdditionalData() {
        var selectedPaymentMethod = _.find(sequraPaymentMethods(), function (method) {
            return method.product === selectedProduct();
        });

        if (!selectedPaymentMethod) {
            return null;
        }

        return {
            sequra_product: selectedPaymentMethod.product,
            sequra_campaign: selectedPaymentMethod.campaign
        };
    }

    return Component.extend({
        defaults: {
            template: 'Sequra_Core/payment/form'
        },

        initObservable: function () {
            this._super().observe([
                'selectedProduct',
                'sequraPaymentMethods'
            ]);
            return this;
        },

        initialize: function () {
            var self = this;
            this._super();

            fullScreenLoader.startLoader();

            var paymentMethodsObserver = sequraPaymentService.getPaymentMethods();

            self.loadSequraPaymentMethods(paymentMethodsObserver());

            // Subscribe to any further changes (shipping address or shipping method might change on the payment page)
            paymentMethodsObserver.subscribe(function (paymentMethodsResponse) {
                self.loadSequraPaymentMethods(paymentMethodsResponse);
            });

            [quote.billingAddress, quote.shippingAddress, quote.shippingMethod].forEach(function (observable) {
                observable.subscribe(function (value) {
                    self.reloadPaymentMethods(value);
                });
            });

            // Add compatibility to One Step Checkout module
            uiRegistry.async("checkout.iosc.payments")(
                function (payments) {
                    if (typeof payments.getObservableId === 'function') {
                        payments.getObservableId = wrapper.wrap(
                            payments.getObservableId, function (originalMethod, observable) {
                                if (
                                    observable.method === 'sequra_payment' &&
                                    observable.additional_data !== undefined &&
                                    observable.additional_data.sequra_product !== undefined
                                ) {
                                    return "#sequra_" + observable.additional_data.sequra_product;
                                }

                                return originalMethod(observable);
                            });
                    }

                    if (typeof payments.getComponentFromButton === 'function') {
                        payments.getComponentFromButton = wrapper.wrap(
                            payments.getComponentFromButton, function (originalMethod, buttonElem) {
                                if (![...buttonElem.classList].some(cls => cls.startsWith('sequra_'))) {
                                    return originalMethod(buttonElem);
                                }

                                let component = originalMethod(buttonElem);
                                if (typeof component.isPlaceOrderActionAllowed !== 'function') {
                                    component.isPlaceOrderActionAllowed = ko.observable(true);
                                }

                                if (typeof component.getData !== 'function') {
                                    component.$parent = ko.contextFor(buttonElem).$parent;
                                    component.getData = function () {
                                        let innerSelf = this.$parent;
                                        let data = {};
                                        data.method = innerSelf.index;

                                        let additionalData = {};
                                        additionalData.sequra_product = "#sequra_" + this.product;
                                        data.additional_data = additionalData;

                                        return data;

                                    }.bind(component);
                                }

                                return component;
                            });
                    }
                }.bind(this)
            );

            fullScreenLoader.stopLoader();
        },

        loadSequraPaymentMethods: function (paymentMethodsResponse) {
            if (JSON.stringify(paymentMethodsResponse) === '{}') {
                return;
            }

            const self = this;
            const enrichedPaymentMethods = paymentMethodsResponse.map(function (method) {
                return Object.assign({}, method, {
                    getCode: function () {
                        return 'sequra_payment';
                    }
                });
            });

            self.sequraPaymentMethods(enrichedPaymentMethods);
            sequraPaymentMethods(enrichedPaymentMethods);
        },

        reloadPaymentMethods: function (value) {
            if (!value || !canReloadPayments()) {
                return;
            }

            // Enables screen loader management if Sequra method is selected
            const stopLoaderCondition = quote.paymentMethod()?.method === this.getCode();

            canReloadPayments(false);
            if (stopLoaderCondition) {
                fullScreenLoader.startLoader();
            }

            sequraPaymentService.retrievePaymentMethods()
                .done(function (paymentMethods) {
                    sequraPaymentService.setPaymentMethods(paymentMethods);
                })
                .fail(function () {
                    console.log('Fetching the payment methods failed!');
                })
                .always(function () {
                    if (stopLoaderCondition) {
                        fullScreenLoader.stopLoader();
                    }
                    canReloadPayments(true);
                });
        },

        getCode: function () {
            return 'sequra_payment';
        },

        isVisible: function () {
            return true;
        },

        getSelectedProduct: ko.computed(function () {
            if (!quote.paymentMethod()) {
                return null;
            }

            if (quote.paymentMethod().method === 'sequra_payment') {
                return selectedProduct();
            }

            return null;
        }),

        selectProduct: function () {
            var self = this;

            selectedProduct(self.product);

            // set payment method to sequra_payment
            var data = {
                'method': 'sequra_payment',
                'po_number': null,
                'additional_data': getSelectedMethodAdditionalData(),
            };

            selectPaymentMethodAction(data);
            checkoutData.setSelectedPaymentMethod('sequra_payment');

            return true;
        },

        placeOrder: function () {
            var self = this;

            if (!additionalValidators.validate() || self.isPlaceOrderActionAllowed() !== true) {
                return false;
            }

            // set payment method to sequra_payment
            var data = {
                'method': 'sequra_payment',
                'po_number': null,
                'additional_data': getSelectedMethodAdditionalData(),
            };

            fullScreenLoader.startLoader();
            $.when(
                selectPaymentMethodAction(data),
                checkoutData.setSelectedPaymentMethod('sequra_payment'),
                setPaymentInformationAction(self.messageContainer, data)
            ).done(function () {
                if (window.checkoutConfig.payment.sequra_payment.showSeQuraCheckoutAsHostedPage) {
                    const hppPageUrl = new URL(
                        window.checkoutConfig.payment.sequra_payment.sequraCheckoutHostedPage
                    );

                    hppPageUrl.searchParams.append("sequra_product", data.additional_data.sequra_product);
                    hppPageUrl.searchParams.append(
                        "sequra_campaign", data.additional_data.sequra_campaign || ''
                    );

                    window.location.replace(hppPageUrl.href);

                    return;
                }

                sequraPaymentService.fetchIdentificationForm(data.additional_data).done(function (response) {
                    fullScreenLoader.stopLoader();
                    self.showIdentificationForm(response);
                }).fail(function (response) {
                    errorProcessor.process(response, self.messageContainer);
                    fullScreenLoader.stopLoader();
                });
            }).fail(function (response) {
                errorProcessor.process(response, self.messageContainer);
                fullScreenLoader.stopLoader();
            });

            return false;
        },

        showIdentificationForm: function (identificationForm) {
            function waitForSequraFormInstance(callback) {
                if (typeof window.SequraFormInstance === 'undefined') {
                    setTimeout(waitForSequraFormInstance, 100, callback);
                    return;
                }

                callback();
            }

            function showForm() {
                window.SequraFormInstance.setCloseCallback(function () {
                    fullScreenLoader.stopLoader();
                    // Add additional stop since in some cases magento keeps one loader on the page
                    fullScreenLoader.stopLoader();
                    window.SequraFormInstance.defaultCloseCallback();
                    delete window.SequraFormInstance;
                });

                window.SequraFormInstance.show();
                fullScreenLoader.stopLoader();
            }

            fullScreenLoader.startLoader();
            $('body').append(identificationForm);
            waitForSequraFormInstance(showForm);
        },

        getAmount: function () {
            var totals = quote.getTotals()();
            if (totals) {
                return Math.round(totals['base_grand_total'] * 100);
            }
            return Math.round(quote['base_grand_total'] * 100);
        },

        showLogo: function () {
            return window.checkoutConfig.payment.sequra_payment.showlogo;
        },

        isSequraObjectAvailable: function () {
            return (typeof Sequra !== 'undefined' && typeof Sequra.refreshComponents === 'function');
        },
    });
});
