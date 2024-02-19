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

        let sequraElements = document.querySelectorAll('[data-content-type="sequra_core"]');
        let renderDefaultMethod = function (config, element) {
            let product = config.widgetConfig.products[0];

            let newElement = element.querySelector('[data-product="' + product.id + '"]')

            if (newElement) {
                return;
            }

            newElement = document.createElement('div');
            newElement.classList.add('sequra-promotion-widget');
            newElement.style.minWidth = '277px';
            newElement.style.height = 'min-content';
            newElement.style.paddingBottom = '20px';
            newElement.setAttribute('data-amount', config.widgetConfig.amount);
            newElement.setAttribute('data-product', product.id);
            newElement.setAttribute('data-campaign', product.campaign);

            Object.keys(widgetConfig).forEach(
                key =>
                    newElement.setAttribute('data-' + key, widgetConfig[key])
            );

            element.appendChild(newElement);
        }

        if (!sequraElements.length) {
            return;
        }

        let widgetConfig = JSON.parse(config.widgetConfig.widgetConfig);

        [...sequraElements].forEach((element) => {
            let enabledMethods = element.getAttribute('data-payment-method'),
            oneRendered = false;

            if (enabledMethods === '') {
                renderDefaultMethod(config, element);

                return;
            }

            config.widgetConfig.products.forEach((product) => {
                if (element.classList.contains('sequra-educational-popup')) {
                    return;
                }

                if (element.hasAttribute('data-payment-method') && !enabledMethods.includes(product.id)) {
                    return;
                }

                oneRendered = true;
                let newElement = element.querySelector('[data-product="' + product.id + '"]')

                if (newElement) {
                    return;
                }

                newElement = document.createElement('div');
                newElement.classList.add('sequra-promotion-widget');
                newElement.style.minWidth = '277px';
                newElement.style.height = 'min-content';
                newElement.style.paddingBottom = '20px';
                newElement.setAttribute('data-amount', config.widgetConfig.amount);
                newElement.setAttribute('data-product', product.id);
                newElement.setAttribute('data-campaign', product.campaign);

                Object.keys(widgetConfig).forEach(
                    key =>
                        newElement.setAttribute('data-' + key, widgetConfig[key])
                );

                element.appendChild(newElement);
            });

            if (!oneRendered) {
                renderDefaultMethod(config, element);
            }
        });

        if (typeof Sequra !== "undefined") {
            Sequra.refreshComponents?.();
        }
    };
});
