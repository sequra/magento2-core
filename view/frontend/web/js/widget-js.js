define([
    'jquery'
], function ($) {
    'use strict';
    $.widget('sequra.widget', {
        options: {
            max_amount: 0,
            css_price_selector: '.price',
            css_dest_selector: '',
            product: 'i1',
            theme: ''
        },
        presets: {
            L:        {"alignment":"left"},
            R:        {"alignment":"right"},
            legacy:   {"type":"legacy"},
            legacyL:  {"type":"legacy","alignment":"left"},
            legacyR:  {"type":"legacy","alignment":"right"},
            minimal:  {"type":"text","branding":"none","size":"S","starting-text":"as-low-as"},
            minimalL: {"type":"text","branding":"none","size":"S","starting-text":"as-low-as","alignment":"left"},
            minimalR: {"type":"text","branding":"none","size":"S","starting-text":"as-low-as","alignment":"right"}  
        },
        drawnWidgets: [],
        getText: function (selector) {
            return  selector && document.querySelector(selector)?document.querySelector(selector).innerText:"0";
        },
    
        selectorToCents: function (selector) {
            return this.textToCents(this.getText(selector));
        },
    
        textToCents: function (text) {
            return this.floatToCents(
              parseFloat(
                  text.replace(/^\D*/,'')
                      .replace(window.SequraConfiguration.thousandSeparator,'')
                      .replace(window.SequraConfiguration.decimalSeparator,'.')
              )
            );
        },
    
        floatToCents: function (value) { 
            return parseInt(value.toFixed(2).replace('.', ''), 10);
        },
    
        mutationCallback: function(mutationlist, mutationobserver) {
            var price_src = mutationobserver.observed_as;
            var new_amount = this.selectorToCents(price_src);
            document.querySelectorAll('[observes=\"' + price_src + '\"]').forEach(function(item) {
                item.setAttribute('data-amount', new_amount);
            });
            Sequra.refreshComponents();
        },

        drawPromotionWidget: function (price_src,dest,product,theme,reverse,campaign) {
            if(this.drawnWidgets.indexOf(price_src+dest+product+theme+reverse+campaign)>=0){
                  return;
              }
            this.drawnWidgets.push(price_src+dest+product+theme+reverse+campaign);
            var promoWidgetNode = document.createElement('div');
            var price_in_cents = 0;
            try{
                var srcNode = document.querySelector(price_src);
                var MutationObserver    = window.MutationObserver || window.WebKitMutationObserver;
                if(MutationObserver && srcNode){//Don't break if not supported in browser
                    if(!srcNode.getAttribute('observed-by-sequra-promotion-widget')){//Define only one observer per price_src
                        var mo = new MutationObserver(this.mutationCallback);
                        mo.observe(srcNode, {childList: true, subtree: true});
                        mo.observed_as = price_src;
                        srcNode.setAttribute('observed-by-sequra-promotion-widget',1);
                    }
                }
                promoWidgetNode.setAttribute('observes',price_src);
                price_in_cents = this.selectorToCents(price_src)
            }
            catch(e){
                if(price_src){
                    console.error(price_src + ' is not a valid css selector to read the price from, for sequra widget.');
                    return;
                }
            }
            try{
                var destNode = document.querySelector(dest);
            }
            catch(e){
                console.error(dest + ' is not a valid css selector to write sequra widget to.');
                return;
            }
            promoWidgetNode.className = 'sequra-promotion-widget';
            promoWidgetNode.setAttribute('data-amount',price_in_cents);
            promoWidgetNode.setAttribute('data-product',product);
            var attributes = this.presets[theme];
            if('undefined' === typeof attributes){
                try {
                    attributes = JSON.parse(theme);
                } catch(e){
                    attributes = {"data-type":"text"};
                }
            }
            for (var key in attributes) {
                promoWidgetNode.setAttribute('data-'+key,""+attributes[key]);
            }
            if(reverse){
                promoWidgetNode.setAttribute('data-reverse',reverse);
            }
            if(campaign){
                promoWidgetNode.setAttribute('data-campaign',campaign);
            }
            if (destNode.nextSibling) {//Insert after
                destNode.parentNode.insertBefore(promoWidgetNode, destNode.nextSibling);
            }
            else {
                destNode.parentNode.appendChild(promoWidgetNode);
            }
            Sequra.onLoad(
                function(){
                    Sequra.refreshComponents();
                }
            );
        },

        _create: function () {
            //var decimalSymbol = priceUtils.globalPriceFormat.decimalSymbol;//@todo
            var decimalSymbol = ',';
            var self = this;
            var patt = new RegExp("[^\\" + decimalSymbol + "\\d]", 'g');
            var price = parseFloat(
                $(self.options.css_price_selector)
                    .text()
                    .replace(patt, '')
                    .replace(decimalSymbol, '.')
            );
            if (self.options.max_amount==0 || price < self.options.max_amount) {
                if('undefined' == typeof window.Sequra){
                    window.SequraConfiguration = self.options.sequra_configuration;
                    window.SequraOnLoad = [];
                    window.Sequra = {
                        onLoad: function (callback) {
                            window.SequraOnLoad.push(callback);
                        }
                    };
                    var a = document.createElement('script');a.async = 1;a.src = window.SequraConfiguration.scriptUri;
                    var m = document.getElementsByTagName('script')[0];
                    m.parentNode.insertBefore(a, m);
                }
                $(function () { window.Sequra.onLoad(function () {
                    self.drawPromotionWidget(self.options.css_price_selector, self.options.css_dest_selector, self.options.product, self.options.theme, 0);
                    window.Sequra.refreshComponents();
                })});
            }
        }
    });

    return $.sequra.widget;
});