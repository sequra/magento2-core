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

            if (!config.widgetConfig.isProductEnabled) {
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
                    }
                )
                (window, document, "script", sequraConfigParams, "Sequra", "onLoad");
            } else {
                Sequra.refreshComponents?.();
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
