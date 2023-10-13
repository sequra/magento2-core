/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * @api
 */
define([
    'underscore',
    'jquery',
    'mage/apply/main',
    'Magento_Ui/js/lib/view/utils/dom-observer'
], function (_, $, mage, domObserver) {
    'use strict';

    function refreshWidgets() {
        if (!Sequra.computeCreditAgreements) {
            setTimeout(function () {
                refreshWidgets();
            }, 1000);

            return;
        }

        let miniElements = document.getElementsByClassName('sequra-educational-popup');

        [...miniElements].forEach((el) => {
            if (el.innerText === '' && Sequra.computeCreditAgreements) {
                let creditAgreement = Sequra.computeCreditAgreements({
                    amount: el.getAttribute('data-amount'),
                    product: el.getAttribute('data-product')
                });

                if (Object.keys(creditAgreement).length === 0) {
                    return;
                }

                creditAgreement = creditAgreement[el.getAttribute('data-product')]
                    .filter(function (item) {
                        return item.default
                    })[0];

                let minAmount = el.getAttribute('data-min-amount');

                if (parseInt(el.getAttribute('data-amount')) >= parseInt(minAmount)) {
                    el.innerText = el.getAttribute('data-label').replace('%s', creditAgreement.instalment_total.string);
                } else {
                    el.innerText = el.getAttribute('data-below-limit').replace('%s', creditAgreement.min_amount.string);
                }
            }
        });
        Sequra.refreshComponents?.();
    }

    /**
     * Initializes components assigned to HTML elements.
     *
     *
     * @param {HTMLElement} el
     * @param {Array} data
     * @param {Object} breakpoints
     * @param {Object} currentViewport
     */
    function initializeWidget(el, data, breakpoints, currentViewport) {
        _.each(data, function (config, component) {
            config = config || {};
            config.breakpoints = breakpoints;
            config.currentViewport = currentViewport;
            mage.applyFor(el, config, component);

            if (!config.widgetConfig.isProductEnabled && config.widgetConfig.action_name === 'catalog_product_view') {
                let sequraElements = document.getElementsByClassName('sequra-promotion-widget');

                [...sequraElements].forEach((el) => {
                    el.parentNode.removeChild(el);
                });
            }

            if (!config.widgetConfig.isProductListingEnabled) {
                let miniElements = document.getElementsByClassName('sequra-educational-popup');

                [...miniElements].forEach((el) => {
                    el.parentNode.removeChild(el);
                });
            }

            if (!config.widgetConfig.hasOwnProperty('merchant')) {
                return;
            }

            if (typeof Sequra === "undefined") {
                var sequraConfigParams = {
                    merchant: config.widgetConfig.merchant,
                    assetKey: config.widgetConfig.assetKey,
                    products: config.widgetConfig.products,
                    scriptUri: config.widgetConfig.scriptUri,
                    decimalSeparator: config.widgetConfig.decimalSeparator,
                    thousandSeparator: config.widgetConfig.thousandSeparator,
                    locale: config.widgetConfig.locale,
                    currency: config.widgetConfig.currency,
                };

                (
                    function (i, s, o, g, r, a, m) {
                        i["SequraConfiguration"] = g;
                        i["SequraOnLoad"] = [];
                        i[r] = {};
                        i[r][a] = function (callback) {
                            i["SequraOnLoad"].push(callback);
                        };
                        (a = s.createElement(o)),
                            (m = s.getElementsByTagName(o)[0]);
                        a.async = 1;
                        a.src = g.scriptUri;
                        m.parentNode.insertBefore(a, m);
                        a.onload = function () {
                            refreshWidgets();
                        }
                    }
                )
                (window, document, "script", sequraConfigParams, "Sequra", "onLoad");
            } else {
                refreshWidgets();
            }
        });
    }

    return function (data, contextElement) {
        _.each(data.config, function (componentConfiguration, elementPath) {
            domObserver.get(
                elementPath,
                function (element) {
                    var $element = $(element);

                    if (contextElement) {
                        $element = $(contextElement).find(element);
                    }

                    if ($element.length) {
                        initializeWidget($element, componentConfiguration, data.breakpoints, data.currentViewport);
                    }
                }
            );
        });
    };
});
