/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'jquery',
    'mage/utils/wrapper'
], function ($, wrapper) {
    'use strict';

    return function (targetModule) {

        var updatePrice = targetModule.prototype._UpdatePrice;
        targetModule.prototype.configurableSku = $('div.product-info-main .sku .value').html();
        var updatePriceWrapper = wrapper.wrap(updatePrice, function (original) {
            let productId = this.getProduct();
            let simpleSku = this.options.jsonConfig.skus[productId];
            let sequraElements = document.getElementsByClassName('sequra-promotion-widget');
            let miniElements = this.element[0].parentElement.getElementsByClassName('sequra-educational-popup');

            if (this.options.jsonConfig.excludedProducts.includes(simpleSku)) {
                [...sequraElements].forEach((el) => {
                    el.style.display = 'none';
                });
                [...miniElements].forEach((el) => {
                    el.style.display = 'none';
                });
            } else {
                [...sequraElements].forEach((el) => {
                    el.style.display = 'block';
                });
                [...miniElements].forEach((el) => {
                    el.style.display = 'block';
                });
            }

            return original();
        });

        targetModule.prototype._UpdatePrice = updatePriceWrapper;
        return targetModule;
    };
});
