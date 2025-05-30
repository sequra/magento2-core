/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Sequra_Core/js/model/set-shipping-information-mixin': true
            },
            'Magento_Swatches/js/swatch-renderer': {
                'Sequra_Core/js/model/skuswitch': true
            }
        }
    }
};
