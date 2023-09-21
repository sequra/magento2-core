/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Sequra_Core/js/model/set-shipping-information-mixin': true
            },
            'Magento_Catalog/js/price-box': {
                'Sequra_Core/js/pricebox-widget-mixin': true
            }
        }
    }
};
