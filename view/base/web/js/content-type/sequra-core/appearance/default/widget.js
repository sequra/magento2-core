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

        let widgetConfig = JSON.parse(config.widgetConfig.widgetConfig);

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

                Object.keys(widgetConfig).forEach(
                    key =>
                        newElement.setAttribute('data-' + key, widgetConfig[key])
                );

                element.appendChild(newElement);
            });
        });

        if (typeof Sequra !== "undefined") {
            Sequra.refreshComponents?.();
        }
    };
});
