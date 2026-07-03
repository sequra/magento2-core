import { ExpressCheckoutPage as Base } from "playwright-fixture-for-plugins";

/**
 * Magento storefront Express Checkout button.
 */
export default class ExpressCheckoutPage extends Base {

    /**
    * Wrapper selector per storefront surface.
    * @param {string} surface One of 'product', 'cart', 'mini-cart'.
    * @returns {string}
    */
    wrapperSelector(surface) {
        return {
            'product': '[data-role="sequra-express-checkout-product"]',
            'cart': '[data-role="sequra-express-checkout"]',
            'mini-cart': '.sequra-express-checkout--minicart',
        }[surface];
    }

    /**
     * Open the header mini-cart dropdown so its express button renders.
     * @returns {Promise<void>}
     */
    async openMiniCart() {
        await this.page.locator('a.action.showcart').click();
        await this.page.locator('.minicart-wrapper.active, .block-minicart').first()
            .waitFor({ state: 'visible', timeout: 10000 });
    }
}
