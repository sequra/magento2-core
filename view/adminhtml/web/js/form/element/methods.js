define([
    'jquery'
], function ($) {
    'use strict';

    let previousStoreView = '';
    let selectedMethods = [];

    return function (config, conditionsFormPlaceholder) {
        let select = $('select[name="payment_method"]')[0];
        let selectedStoreViews = [];
        let result;

        select.addEventListener('change', () => {
            let widget = document.getElementById(window.currentWidgetId).firstElementChild,
                children = widget.querySelectorAll('.sequra-promotion-widget');

            children.forEach((child) => {
                child.remove();
            })

            selectedMethods[window.currentWidgetId] = [];
            [...select.selectedOptions].forEach(
                (selectedOption) => {
                    selectedMethods[window.currentWidgetId].push(selectedOption.value);

                    let newElement = document.createElement('div');
                    newElement.classList.add('sequra-promotion-widget');
                    newElement.style.minWidth = '277px';
                    newElement.style.height = 'min-content';
                    newElement.style.paddingBottom = '20px';
                    newElement.setAttribute('data-amount', "15000");
                    newElement.setAttribute('data-product', selectedOption.value);

                    let campaign = '';

                    window.sequraConfigParams.products.map((product) => {
                        if (product.id === selectedOption.value) {
                            campaign = product.campaign;
                        }
                    });

                    newElement.setAttribute('data-campaign', campaign);

                    widget.appendChild(newElement);
                }
            );

            widget.setAttribute('data-payment-method', selectedMethods[window.currentWidgetId]);

            Sequra.refreshComponents?.();
        });

        [...conditionsFormPlaceholder.selectedOptions].forEach(
            (o) => {
                selectedStoreViews.push(o.value);
            }
        );

        selectedStoreViews.forEach((storeView) => {
            if (result) {
                return;
            }

            if (storeView === '0') {
                result = config.method.length === 0 ? null : Object.values(config.method)[0];
            } else {
                let methods = Object.entries(config.method);

                methods.forEach((method) => {
                    if (storeView === method[0]) {
                        result = method[1];
                    }
                });
            }

            if (result) {
                let errorMessage = document.getElementById('sequra-widgets-form-error'),
                    widget = document.getElementById(window.currentWidgetId).firstElementChild,
                    enabledMethods = widget.getAttribute('data-payment-method');
                errorMessage.style.display = 'none';

                [...result].forEach((r) => {
                    const option = document.createElement('option');
                    option.value = r.product;
                    option.innerHTML = r.title;

                    if (enabledMethods && enabledMethods.includes(r.product)) {
                        option.selected = true;
                    }

                    select.appendChild(option);
                })
            }

            previousStoreView = storeView;
        });

        if (!result) {
            let errorMessage = document.getElementById('sequra-widgets-form-error');
            errorMessage.style.display = 'block';
            errorMessage.parentNode.style.display = 'block';
        }
    }
});
