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
        if(config.miniWidgets && Array.isArray(config.miniWidgets)){
            for (const miniWidget of config.miniWidgets) {
                window.SequraWidgetFacade.miniWidgets.push({
                    product: miniWidget.product,
                    campaign: miniWidget.campaign,
                    priceSel: miniWidget.priceSel,
                    dest: '.product-item-info ' + miniWidget.dest,
                    theme: miniWidget.theme,
                    reverse: miniWidget.reverse,
                    minAmount: miniWidget.minAmount,
                    maxAmount: miniWidget.maxAmount,
                    message: miniWidget.miniWidgetMessage,
                    messageBelowLimit: miniWidget.miniWidgetBelowLimitMessage,
                });
            }
        }
        if (config.widgets && Array.isArray(config.widgets)) {
            for (const widget of config.widgets) {
                window.SequraWidgetFacade.widgets.push({
                    product: widget.product,
                    campaign: widget.campaign,
                    priceSel: widget.priceSel,
                    dest: widget.dest || '#' + config.divId,
                    altPriceSel: widget.altPriceSel,
                    altTriggerSelector: widget.altTriggerSelector,
                    theme: widget.theme,
                    reverse: widget.reverse,
                    minAmount: widget.minAmount,
                    maxAmount: widget.maxAmount
                });
            }
        }
    };
});
