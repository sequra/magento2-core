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
            let miniElements = document.getElementsByClassName('sequra-educational-popup');
            var price = Math.round(this.cache.displayPrices.finalPrice.amount * 100);
            var productId = this.element[0].getAttribute('data-product-id');

            [...sequraElements].forEach((el) => {
                el.setAttribute('data-amount', price);
            });

            [...miniElements].forEach((el) => {
                if (el.parentElement.parentElement.getAttribute('data-product-id') === productId) {
                    el.setAttribute('data-amount', price);
                    let minAmount = el.getAttribute('data-min-amount');

                    if (price > minAmount) {
                        el.innerText = el.getAttribute('data-label');
                    } else {
                        el.innerText = el.getAttribute('data-below-limit');
                    }
                }
            });

            if (typeof Sequra !== "undefined") {
                Sequra.refreshComponents?.();
            }

            return ret;
        }
    };

    return function (targetWidget) {
        $.widget('mage.priceBox', targetWidget, priceBoxWidget);

        return $.mage.priceBox;
    };
});
