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

    /**
     * @param {Object} options Additional options to configure the widget
     * @param {string} options.slug The product slug
     * @returns {FrontEndWidgetOptions} Options for the i1 widget
     */
    pp3FrontEndWidgetOptions = (options = {}) => {
        const args = this.getFrontEndWidgetProductArguments(options);
        if (!args) {
            throw new Error(`No front-end widget arguments found for slug: ${JSON.stringify(options)}`);
        }
        return this.frontEndWidgetOptions('pp3', null, args.amount, args.registrationAmount);
    }

    /**
     * @param {Object} options Additional options to configure the widget
     * @returns {FrontEndWidgetOptions} Options for the sp1 widget
     */
    sp1FrontEndWidgetOptions = (options = {}) => {
        const args = this.getFrontEndWidgetProductArguments(options);
        if (!args) {
            throw new Error(`No front-end widget arguments found for slug: ${JSON.stringify(options)}`);
        }
        return this.frontEndWidgetOptions('sp1', 'permanente', args.amount, args.registrationAmount);
    }

    /**
     * @param {Object} options Additional options to configure the widget
     * @returns {FrontEndWidgetOptions} Options for the i1 widget
     */
    i1FrontEndWidgetOptions = (options = {}) => {
        const args = this.getFrontEndWidgetProductArguments(options);
        if (!args) {
            throw new Error(`No front-end widget arguments found for slug: ${JSON.stringify(options)}`);
        }
        const widget = this.widgetOptions();
        return {
            ...this.frontEndWidgetOptions('i1', null, args.amount, args.registrationAmount),
            locationSel: widget.product.customLocations[0].locationSel || widget.product.locationSel,
            widgetConfig: widget.product.customLocations[0].widgetConfig || widget.product.widgetConfig,
        };
    }

    /**
     * Convert the slug to the front-end widget arguments
     * @param {Object} options 
     * @param {string} options.slug The product slug.
     * @returns {Object|null} The arguments for the front-end widget or null if the slug is not recognized
     */
    getFrontEndWidgetProductArguments(options) {
        if ('undefined' === typeof options.slug) {
            return null;
        }
        return {
            "fusion-backpack": { amount: 5900, registrationAmount: null },
            "push-it-messenger-bag": { amount: 4500, registrationAmount: null },

        }[options.slug] || null;
    }
}