/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * @api
 */
define([
    'jquery'
], function ($) {
    'use strict';
    
    return function(config){
        if('undefined' === typeof window.SequraWidgetFacade){
            window.SequraWidgetFacade = {};
        }
        window.SequraWidgetFacade = {
            ...window.SequraWidgetFacade,
            ...config
        };
        (function (i, s, o, g, r, a, m) { i['SequraConfiguration'] = g; i['SequraOnLoad'] = []; i[r] = {}; i[r][a] = function (callback) { i['SequraOnLoad'].push(callback); }; (a = s.createElement(o)), (m = s.getElementsByTagName(o)[0]); a.async = 1; a.src = g.scriptUri; m.parentNode.insertBefore(a, m); })(window, document, 'script', window.SequraWidgetFacade, 'Sequra', 'onLoad');

        $(function(){
            if ('undefined' === typeof window.SequraWidgetFacade) {
                console.error('SequraWidgetFacade is not defined');
                return;
            }
            window.SequraWidgetFacade = {
                ...{
                    widgets: [],
                    miniWidgets: [],
                },
                ...SequraWidgetFacade,
                ...{
                    mutationObserver: null,
                    forcePriceSelector: true,
                    presets: {
                        L: '{"alignment":"left"}',
                        R: '{"alignment":"right"}',
                        legacy: '{"type":"legacy"}',
                        legacyL: '{"type":"legacy","alignment":"left"}',
                        legacyR: '{"type":"legacy","alignment":"right"}',
                        minimal: '{"type":"text","branding":"none","size":"S","starting-text":"as-low-as"}',
                        minimalL: '{"type":"text","branding":"none","size":"S","starting-text":"as-low-as","alignment":"left"}',
                        minimalR: '{"type":"text","branding":"none","size":"S","starting-text":"as-low-as","alignment":"right"}'
                    },
        
                    init: function () {
                        // Remove duplicated objects from this.widgets.
                        const uniqueWidgets = [];
                        this.widgets.forEach(widget => {
                            Object.keys(widget).forEach(key => {
                                if (typeof widget[key] === 'string') {
                                    widget[key] = this.decodeEntities(widget[key]);
                                }
                            });
        
                            if (!uniqueWidgets.some(w => w.price_src === widget.price_src && w.dest === widget.dest && w.product === widget.product && w.theme === widget.theme && w.reverse === widget.reverse && w.campaign === widget.campaign)) {
                                uniqueWidgets.push(widget);
                            }
                        });
                        this.widgets = uniqueWidgets;
                    },
        
                    getText: function (selector) {
                        return selector && document.querySelector(selector) ? document.querySelector(selector).textContent : "0";
                    },
        
                    nodeToCents: function (node) {
                        return this.textToCents(node ? node.textContent : "0");
                    },
        
                    selectorToCents: function (selector) {
                        return this.textToCents(this.getText(selector));
                    },
        
                    decodeEntities: function (encodedString) {
                        if (!encodedString.match(/&(nbsp|amp|quot|lt|gt|#\d+|#x[0-9A-Fa-f]+);/g)) {
                            return encodedString;
                        }
                        const elem = document.createElement('div');
                        elem.innerHTML = encodedString;
                        return elem.textContent;
                    },
        
                    textToCents: function (text) {
                        const thousandSeparator = this.decodeEntities(this.thousandSeparator);
                        const decimalSeparator = this.decodeEntities(this.decimalSeparator);
        
                        text = text.replace(/^\D*/, '').replace(/\D*$/, '');
                        if (text.indexOf(decimalSeparator) < 0) {
                            text += decimalSeparator + '00';
                        }
                        return this.floatToCents(
                            parseFloat(
                                text
                                    .replace(thousandSeparator, '')
                                    .replace(decimalSeparator, '.')
                            )
                        );
                    },
        
                    floatToCents: function (value) {
                        return parseInt(value.toFixed(2).replace('.', ''), 10);
                    },
        
                    refreshComponents: function () {
                        Sequra.onLoad(
                            function () {
                                Sequra.refreshComponents();
                            }
                        );
                    },
        
                    isVariableProduct: function (selector) {
                        return document.querySelector(selector) ? true : false;
                    },
        
                    getPriceSelector: function (widget) {
                        return widget.priceSel;
                        // return !this.forcePriceSelector && this.isVariableProduct(widget.isVariableSel) ? widget.variationPriceSel : widget.priceSel;
                    },
        
                    /**
                     * Search for child elements in the parentElem that are targets of the widget
                     * @param {object} parentElem DOM element that may contains the widget's targets
                     * @param {object} widget  Widget object
                     * @param {string} observedAt Unique identifier to avoid fetch the same element multiple times
                     * @returns {array} Array of objects containing the target elements and a reference to the widget
                     */
                    getWidgetTargets: function (parentElem, widget, observedAt) {
                        const targets = [];
                        if (widget.dest) {
                            const children = parentElem.querySelectorAll(widget.dest);
                            const productObservedAttr = 'data-sequra-observed-' + widget.product;
                            for (const child of children) {
                                if (child.getAttribute(productObservedAttr) == observedAt) {
                                    continue;// skip elements that are already observed in this mutation.
                                }
                                child.setAttribute(productObservedAttr, observedAt);
                                targets.push({ elem: child, widget });
                            }
                        }
                        return targets;
                    },
        
                    /**
                     * Search for child elements in the parentElem that are targets of the widget
                     * @param {object} widget  Widget object
                     * @returns {array} Array of objects containing the target elements and a reference to the widget
                     */
                    getMiniWidgetTargets: function (widget) {
                        const targets = [];
                        if (widget.dest) {
                            const children = document.querySelectorAll(widget.dest);
                            const prices = document.querySelectorAll(widget.priceSel);
                            const priceObservedAttr = 'data-sequra-observed-price-' + widget.product;
        
                            for (let i = 0; i < children.length; i++) {
                                const child = children[i];
        
                                const priceElem = 'undefined' !== typeof prices[i] ? prices[i] : null;
                                const priceValue = priceElem ? this.nodeToCents(priceElem) : null;
        
                                if (null === priceValue || child.getAttribute(priceObservedAttr) == priceValue) {
                                    continue;
                                }
                                child.setAttribute(priceObservedAttr, priceValue);
                                targets.push({ elem: child, priceElem, widget });
                            }
                        }
                        return targets;
                    },
        
                    /**
                     * Get an unique identifier to avoid fetch the same element multiple times
                     * @returns {number} The current timestamp
                     */
                    getObservedAt: () => Date.now(),
        
                    removeWidgetsOnPage: function () {
                        if (this.mutationObserver) {
                            this.mutationObserver.disconnect();
                        }
                        document.querySelectorAll('.sequra-promotion-widget').forEach(widget => widget.remove());
                        if (this.mutationObserver) {
                            this.mutationObserver.observe(document, { childList: true, subtree: true });
                        }
                    },
        
                    /**
                     * Draw the missing or outdated widgets in the page.
                     */
                    refreshWidgets: function () {
        
                        const targets = [];
                        for (const widget of this.widgets) {
                            const widgetTargets = this.getWidgetTargets(document, widget, this.getObservedAt());
                            targets.push(...widgetTargets);
                        }
                        for (const miniWidget of this.miniWidgets) {
                            const widgetTargets = this.getMiniWidgetTargets(miniWidget);
                            targets.push(...widgetTargets);
                        }
        
                        targets.forEach(target => {
                            const { elem, widget } = target;
                            this.isMiniWidget(widget) ? this.drawMiniWidgetOnElement(widget, elem, target.priceElem) : this.drawWidgetOnElement(widget, elem);
                        });
                    },
        
                    /**
                     * Paint the widgets in the page and observe the DOM to refresh the widgets when the page changes.
                     * @param {boolean} forcePriceSelector If true, the price selector will be forced to the simple product price selector.
                     */
                    drawWidgetsOnPage: function (forcePriceSelector = true) {

                        // Init the pre-rendered miniWidgets if any.
                        for (const widget of document.querySelectorAll('.sequra-promotion-miniwidget')) {
                            const {amount, product, label, belowLimit} = widget.dataset;
                            const innerText = this.getMiniWidgetInnerText(
                                parseInt(amount),
                                product,
                                label,
                                !belowLimit ? null : belowLimit
                            );
    
                            if(!innerText) {
                                // Remove from DOM
                                widget.remove();
                                continue;
                            }
                            widget.innerText = innerText;
                        }

                        if (!this.widgets.length && !this.miniWidgets.length) {
                            return;
                        }
        
                        if (this.mutationObserver) {
                            this.mutationObserver.disconnect();
                        }
        
                        this.forcePriceSelector = forcePriceSelector;
        
                        this.refreshWidgets();
        
                        // Then, observe the DOM to refresh the widgets when the page changes.
                        this.mutationObserver = new MutationObserver((mutations) => {
                            this.mutationObserver.disconnect();// disable the observer to avoid multiple calls to the same function.
                            for (const mutation of mutations) {
                                if (['childList', 'subtree', 'characterData'].includes(mutation.type)) {
                                    this.refreshWidgets();
                                    break;
                                }
                            }
                            this.mutationObserver.observe(document, { childList: true, subtree: true, characterData: true }); // enable the observer again.
                        });
        
                        this.mutationObserver.observe(document, { childList: true, subtree: true, characterData: true });
                    },
        
                    isMiniWidget: function (widget) {
                        return this.miniWidgets.indexOf(widget) !== -1;
                    },
                    isAmountInAllowedRange: function (widget, cents) {
                        if ('undefined' !== typeof widget.minAmount && widget.minAmount && cents < widget.minAmount) {
                            return false;
                        }
                        if ('undefined' !== typeof widget.maxAmount && widget.maxAmount && widget.maxAmount < cents) {
                            return false;
                        }
                        return true;
                    },
        
                    drawMiniWidgetOnElement: function (widget, element, priceElem) {
                        if (!priceElem) {
                            const priceSrc = this.getPriceSelector(widget);
                            priceElem = document.querySelector(priceSrc);
                            if (!priceElem) {
                                // console.error(priceSrc + ' is not a valid css selector to read the price from, for seQura mini-widget.');
                                return;
                            }
                        }
                        const cents = this.nodeToCents(priceElem);
                        const className = 'sequra-promotion-miniwidget';
                        const modifierClassName = className + '--' + widget.product;
        
                        const oldWidget = element.parentNode.querySelector('.' + className + '.' + modifierClassName);
                        if (oldWidget) {
                            if (cents == oldWidget.getAttribute('data-amount')) {
                                return; // no need to update the widget, the price is the same.
                            }
        
                            oldWidget.remove();// remove the old widget to draw a new one.
                        }
        
                        if (!this.isAmountInAllowedRange(widget, cents)) {
                            return;
                        }
        
                        const widgetNode = document.createElement('small');
                        widgetNode.className = className + ' ' + modifierClassName;
                        widgetNode.setAttribute('data-amount', cents);
                        widgetNode.setAttribute('data-product', widget.product);

                        const innerText = this.getMiniWidgetInnerText(
                            cents,
                            widget.product,
                            widget.message,
                            'undefined' !== typeof widget.messageBelowLimit ? widget.messageBelowLimit : null
                        );

                        if(!innerText) {
                            return;
                        }
                        widgetNode.innerText = innerText;
        
                        if (element.nextSibling) {//Insert after
                            element.parentNode.insertBefore(widgetNode, element.nextSibling);
                            this.refreshComponents();
                        } else {
                            element.parentNode.appendChild(widgetNode);
                        }
        
                    },

                    getMiniWidgetInnerText: function(cents, product, message, messageBelowLimit) {
                        const creditAgreements = Sequra.computeCreditAgreements({ amount: cents, product: product })[product];
                        let creditAgreement = null
                        do {
                            creditAgreement = creditAgreements.pop();
                        } while (cents < creditAgreement.min_amount.value && creditAgreements.length > 1);
                        if (cents < creditAgreement.min_amount.value && !messageBelowLimit) {
                            return null;
                        }
        
                        if (cents >= creditAgreement.min_amount.value) {
                            return message.replace('%s', creditAgreement.instalment_total.string);
                        } else {
                            return !messageBelowLimit ? null : messageBelowLimit.replace('%s', creditAgreement.min_amount.string);
                        }
                    },
        
                    drawWidgetOnElement: function (widget, element) {
                        const priceSrc = this.getPriceSelector(widget);
                        const priceElem = document.querySelector(priceSrc);
                        if (!priceElem) {
                            // console.error(priceSrc + ' is not a valid css selector to read the price from, for seQura widget.');
                            return;
                        }
                        const cents = this.nodeToCents(priceElem);
                        const className = 'sequra-promotion-widget';
                        const modifierClassName = className + '--' + widget.product;
        
                        const oldWidget = element.parentNode.querySelector('.' + className + '.' + modifierClassName);
                        if (oldWidget) {
                            if (cents == oldWidget.getAttribute('data-amount')) {
                                return; // no need to update the widget, the price is the same.
                            }
        
                            oldWidget.remove();// remove the old widget to draw a new one.
                        }

                        if (!this.isAmountInAllowedRange(widget, cents)) {
                            return;
                        }
        
                        const promoWidgetNode = document.createElement('div');
                        promoWidgetNode.className = className + ' ' + modifierClassName;
                        promoWidgetNode.setAttribute('data-amount', cents);
                        promoWidgetNode.setAttribute('data-product', widget.product);
        
                        const theme = this.presets[widget.theme] ? this.presets[widget.theme] : widget.theme;
                        try {
                            const attributes = JSON.parse(theme);
                            for (let key in attributes) {
                                promoWidgetNode.setAttribute('data-' + key, "" + attributes[key]);
                            }
                        } catch (e) {
                            promoWidgetNode.setAttribute('data-type', 'text');
                        }
        
                        if (widget.campaign) {
                            promoWidgetNode.setAttribute('data-campaign', widget.campaign);
                        }
                        if (widget.registrationAmount) {
                            promoWidgetNode.setAttribute('data-registration-amount', widget.registrationAmount);
                        }
        
                        if (element.nextSibling) {//Insert after
                            element.parentNode.insertBefore(promoWidgetNode, element.nextSibling);
                            this.refreshComponents();
                        } else {
                            element.parentNode.appendChild(promoWidgetNode);
                        }
                    }
                }
            };
            SequraWidgetFacade.init()
            Sequra.onLoad(() => {
                SequraWidgetFacade.drawWidgetsOnPage();
            });
        });
    };
});
