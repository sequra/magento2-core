import { CheckoutPage as BaseCheckoutPage } from "playwright-fixture-for-plugins";

/**
 * Checkout page
 */
export default class CheckoutPage extends BaseCheckoutPage {

    /**
    * Init the locators with the locators available
    * 
    * @returns {Object}
    */
    initLocators() {
        return {
            //    ...super.initLocators(),
            //    messageSuccess: () => this.page.locator('.message-success'),
            loader: () => this.page.locator('.loading-mask', { state: 'visible' }),
            email: () => this.page.locator('#customer-email'),
            firstName: () => this.page.locator('[name=firstname]'),
            lastName: () => this.page.locator('[name=lastname]'),
            // locator for selector [name="street[0]"]
            address1: () => this.page.locator('[name="street[0]"]'),
            country: () => this.page.locator('[name=country_id]'),
            state: () => this.page.locator('[name=region_id]'),
            city: () => this.page.locator('[name=city]'),
            postcode: () => this.page.locator('[name=postcode]'),
            phone: () => this.page.locator('[name=telephone]'),
            flatRateShipping: () => this.page.locator('[value="flatrate_flatrate"]'),
            continueButton: () => this.page.locator('.action.continue'),
        };
    }

    /**
    * Provide the checkout URL
    * @param {Object} options
    * @returns {string} The checkout URL
    */
    checkoutUrl(options = {}) {
        return `${this.baseURL}/checkout/`;
    }

    /**
     * Fill the checkout page's form
     * @param {Object} options Contains the data to fill the form
     * @param {string} options.email Email
     * @param {string} options.firstName First name
     * @param {string} options.lastName Last name
     * @param {string} options.address1 Address first line
     * @param {string} options.country Typically a 2-letter ISO country code
     * @param {string} options.state Name of the state
     * @param {string} options.city Name of the city
     * @param {string} options.postcode Postcode
     * @param {string} options.phone Phone number
     * @param {string} options.shippingMethod Shipping method
     * @returns {Promise<void>}
     */
    async fillForm(options) {
        await this.fillShippingForm(options);
        await this.selectShippingMethod(options);
        await this.locators.continueButton().click();
        // TODO: Implement the form filling
    }

    /**
     * Fill the shipping form
     * @param {Object} options
     * @param {string} options.email Email
     * @param {string} options.firstName First name
     * @param {string} options.lastName Last name
     * @param {string} options.address1 Address first line
     * @param {string} options.country Typically a 2-letter ISO country code
     * @param {string} options.state Name of the state
     * @param {string} options.city Name of the city
     * @param {string} options.postcode Postcode
     * @param {string} options.phone Phone number
     * @returns {Promise<void>}
     */
    async fillShippingForm(options) {
        await this.page.waitForURL(/#shipping/);
        await this.#waitForFinishLoading();
        const { email, firstName, lastName, address1, country, state, city, postcode, phone } = options;
        // TODO: Implement the form filling
        await this.locators.email().fill(email);
        await this.locators.firstName().fill(firstName);
        await this.locators.lastName().fill(lastName);
        await this.locators.address1().fill(address1);
        await this.locators.country().selectOption(country);
        await this.locators.state().selectOption({ label: state });
        await this.locators.city().fill(city);
        await this.locators.postcode().fill(postcode);
        await this.locators.phone().fill(phone);
    }

    /**
     * Select the shipping method
     * @param {Object} options
     * @param {string} options.shippingMethod Shipping method
     * @returns {Promise<void>}
     */
    async selectShippingMethod(options) {
        await this.page.waitForURL(/#shipping/);
        const { shippingMethod } = options;
        // TODO: Implement the method selection
        await this.#waitForFinishLoading();
        this.locators.flatRateShipping().click();
    }

    /**
     * Wait for the checkout to finish loading
     * @returns {Promise<void>}
     */
    async #waitForFinishLoading() {
        do {
            await this.expect(this.locators.loader().first()).toBeHidden();
        } while ((await this.locators.loader()) > 0);
    }
}