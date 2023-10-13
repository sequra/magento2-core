define([
    'jquery',
    'underscore',
], function (
    $,
    _
) {
    'use strict';

    return function (config, element) {
        if (!config.widgetConfig || !config.widgetConfig.products
            || (config.widgetConfig.action_name !== 'catalog_product_view'
                && config.widgetConfig.action_name !== 'checkout_cart_index') ||
            (!config.widgetConfig.isProductEnabled && config.widgetConfig.action_name === 'catalog_product_view')) {
            return;
        }

        let sequraElements = document.querySelectorAll('[data-content-type="Sequra_Core"]');

        if (!sequraElements.length) {
            return;
        }

        config.widgetConfig.products.forEach((product) => {
            [...sequraElements].forEach((element) => {
                if (element.classList.contains('sequra-educational-popup')) {
                    return;
                }

                let newElement = element.querySelector('[data-product="' + product + '"]')

                if (newElement) {
                    return;
                }

                newElement = document.createElement('div');
                newElement.classList.add('sequra-promotion-widget');
                newElement.style.minWidth = '277px';
                newElement.style.height = 'min-content';
                newElement.style.paddingBottom = '20px';
                newElement.setAttribute('data-amount', config.widgetConfig.amount);
                newElement.setAttribute('data-product', product);
                newElement.setAttribute('data-type', config.widgetConfig.widgetConfig['type']);
                newElement.setAttribute('data-size', config.widgetConfig.widgetConfig['size']);
                newElement.setAttribute('data-font-color', config.widgetConfig.widgetConfig['font-color']);
                newElement.setAttribute('data-background-color', config.widgetConfig.widgetConfig['background-color']);
                newElement.setAttribute('data-alignment', config.widgetConfig.widgetConfig['alignment']);
                newElement.setAttribute('data-branding', config.widgetConfig.widgetConfig['branding']);
                newElement.setAttribute('data-starting-text', config.widgetConfig.widgetConfig['starting-text']);
                newElement.setAttribute('data-amount-font-size', config.widgetConfig.widgetConfig['amount-font-size']);
                newElement.setAttribute('data-amount-font-color', config.widgetConfig.widgetConfig['amount-font-color']);
                newElement.setAttribute('data-amount-font-bold', config.widgetConfig.widgetConfig['amount-font-bold']);
                newElement.setAttribute('data-link-font-color', config.widgetConfig.widgetConfig['link-font-color']);
                newElement.setAttribute('data-link-underline', config.widgetConfig.widgetConfig['link-underline']);
                newElement.setAttribute('data-border-color', config.widgetConfig.widgetConfig['border-color']);
                newElement.setAttribute('data-border-radius', config.widgetConfig.widgetConfig['border-radius']);
                newElement.setAttribute('data-no-costs-claim', config.widgetConfig.widgetConfig['no-costs-claim']);

                element.appendChild(newElement);
            });
        });

        if (typeof Sequra !== "undefined") {
            Sequra.refreshComponents?.();
        }
    };
});
