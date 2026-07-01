import { Fixture } from "playwright-fixture-for-plugins";

/**
 * SeQura Express Checkout button, rendered by the CDN library into `.sequra-express-checkout-button`
 * on the product, cart and mini-cart surfaces. Operates on whatever page is currently loaded.
 */
export default class ExpressCheckoutPage extends Fixture {

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
    * Init the locators with the locators available
    *
    * @returns {Object}
    */
    initLocators() {
        return {
            mount: surface => this.page.locator(`${this.wrapperSelector(surface)} .sequra-express-checkout-button`),
            wrapper: surface => this.page.locator(this.wrapperSelector(surface)),
            button: surface => this.page.frameLocator(`${this.wrapperSelector(surface)} iframe[title="Pago exprés con seQura"]`).getByRole('button'),
            showCart: () => this.page.locator('a.action.showcart'),
            identificationForm: () => this.page.locator('[id^="sq-identification-"]'),
        };
    }

    /**
     * Open the header mini-cart dropdown so its express button is rendered/visible.
     * @returns {Promise<void>}
     */
    async openMiniCart() {
        await this.locators.showCart().click();
        await this.page.locator('.minicart-wrapper.active, .block-minicart').first().waitFor({ state: 'visible', timeout: 10000 });
    }

    /**
     * Assert the express button is mounted and enabled on the given surface.
     * @param {string} surface One of 'product', 'cart', 'mini-cart'.
     * @returns {Promise<void>}
     */
    async expectButtonVisible(surface) {
        await this.expect(this.locators.mount(surface).first()).toBeVisible({ timeout: 15000 });
        await this.expect(this.locators.wrapper(surface).first())
            .not.toHaveClass(/sequra-express-checkout--disabled/);
    }

    /**
     * Click the CDN button on the given surface and wait for the SeQura identification form.
     * @param {string} surface One of 'product', 'cart', 'mini-cart'.
     * @returns {Promise<void>}
     */
    async startCheckout(surface) {
        await this.locators.button(surface).first().click();
        await this.locators.identificationForm().first().waitFor({ state: 'visible', timeout: 20000 });
    }
}
