import { DataProvider as BaseDataProvider } from 'playwright-fixture-for-plugins';

export default class DataProvider extends BaseDataProvider {

    /**
    * Prepare the URL to use
    * 
    * @param {Object} options Additional options
    * @param {string} options.webhook The webhook
    * @param {Array<Object>} options.args The arguments to pass to the webhook. Each argument is an object with `name` and `value` properties
    * @returns {string} The URL to use
    */
    getWebhookUrl(options = { webhook, args: [] }) {
        const { webhook, args } = options;
        return `${this.baseURL}/rest/V1/sequrahelper/webhook/?sq-webhook=${webhook}${this.getWebhookUrlArgs(args)}`;
    }

    /**
    * Configuration for the widget form with all options enabled
    * @returns {WidgetOptions} Configuration for the widget
    */
    widgetOptions() {
        const widgetOptions = super.widgetOptions();
        return {
            ...widgetOptions,
            product: {
                ...widgetOptions.product,
                priceSel: '.product-info-price [data-price-type="finalPrice"] .price',
                locationSel: '.product.info',
                customLocations: [
                    {
                        ...widgetOptions.product.customLocations[0],
                        locationSel: '#product-addtocart-button'
                    }
                ]
            },
            cart: {
                ...widgetOptions.cart,
                priceSel: '.cart-totals .grand.totals .price',
                locationSel: '.cart-totals',
            },
            productListing: {
                ...widgetOptions.productListing,
                useSelectors: false, // Disable selectors for product listing.
            }
        }
    }

    pp3FrontEndWidgetOptions = () => this.frontEndWidgetOptions('pp3', null, 5900, null);
    sp1FrontEndWidgetOptions = () => this.frontEndWidgetOptions('sp1', 'permanente', 5900, null);
    i1FrontEndWidgetOptions = () => {
        const widget = this.widgetOptions();
        return {
            ...this.frontEndWidgetOptions('i1', null, 5900, null),
            locationSel: widget.product.customLocations[0].locationSel || widget.product.locationSel,
            widgetConfig: widget.product.customLocations[0].widgetConfig || widget.product.widgetConfig,
        };
    }
}