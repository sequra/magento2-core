define([
    'jquery'
], function ($) {
    'use strict';
    return function(config){
        if('undefined' === typeof window.SequraWidgetFacade){
            window.SequraWidgetFacade = {};
        }
        if('undefined' === typeof window.SequraWidgetFacade.widgets){
            window.SequraWidgetFacade.widgets = [];
        }
        if('undefined' === typeof window.SequraWidgetFacade.miniWidgets){
            window.SequraWidgetFacade.miniWidgets = [];
        }
        if (config.widgets && Array.isArray(config.widgets)) {
            for (const widget of config.widgets) {
                window.SequraWidgetFacade.widgets.push({
                    product: widget.product,
                    campaign: widget.campaign,
                    priceSel: widget.priceSel,
                    dest: widget.dest || '#' + config.divId,
                    theme: widget.theme,
                    reverse: widget.reverse,
                    minAmount: widget.minAmount,
                    maxAmount: widget.maxAmount
                });
            }
        }
    };
});
