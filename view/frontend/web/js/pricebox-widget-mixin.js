define(['jquery'], function ($) {
    'use strict';

    var priceBoxWidget = {

        /**
         * Updating the price
         * @param {String} newPrices
         * @returns {*}
         */
        updatePrice: function (newPrices) {
            var ret = this._super(newPrices);
            let sequraElements = document.getElementsByClassName('sequra-promotion-widget');
            let miniElements = document.getElementsByClassName('sequra-promotion-miniwidget');
            var price = Math.round(this.cache.displayPrices.finalPrice.amount * 100);
            var productId = this.element[0].getAttribute('data-product-id');

            [...sequraElements].forEach((el) => {
                el.setAttribute('data-amount', price);
            });

            [...miniElements].forEach((el) => {
                if (typeof Sequra !== "undefined" && Sequra.computeCreditAgreements) {
                    this.updateMiniWidgets(el, productId, price);
                }
            });

            if (typeof Sequra !== "undefined") {
                Sequra.refreshComponents?.();
            }

            return ret;
        },

        updateMiniWidgets: function (el, productId, price) {
            if (el.parentElement.parentElement.getAttribute('data-product-id') === productId || el.innerText === '') {
                let creditAgreement = Sequra.computeCreditAgreements({
                    amount: price,
                    product: el.getAttribute('data-product')
                });

                if (Object.keys(creditAgreement).length === 0) {
                    return;
                }

                creditAgreement = creditAgreement[el.getAttribute('data-product')]
                    .filter(function (item) {
                        return item.default
                    })[0];

                el.setAttribute('data-amount', price);
                let minAmount = el.getAttribute('data-min-amount');

                if (parseInt(price) >= parseInt(minAmount)) {
                    el.innerText = el.getAttribute('data-label').replace('%s', creditAgreement.instalment_total.string);
                } else {
                    el.innerText = el.getAttribute('data-below-limit').replace('%s', creditAgreement.min_amount.string);
                }
            }
        }
    };

    return function (targetWidget) {
        $.widget('mage.priceBox', targetWidget, priceBoxWidget);

        return $.mage.priceBox;
    };
});
