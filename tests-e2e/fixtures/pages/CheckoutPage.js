import { BackOffice, CheckoutPage as BaseCheckoutPage } from "playwright-fixture-for-plugins";

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
            ...super.initLocators(),
            loader: () => this.page.locator('.loading-mask', { state: 'visible' }),
            email: () => this.page.locator('#customer-email'),
            firstName: () => this.page.locator('[name=firstname]'),
            lastName: () => this.page.locator('[name=lastname]'),
            address1: () => this.page.locator('[name="street[0]"]'),
            country: () => this.page.locator('[name=country_id]'),
            state: () => this.page.locator('[name=region_id]'),
            city: () => this.page.locator('[name=city]'),
            postcode: () => this.page.locator('[name=postcode]'),
            phone: () => this.page.locator('[name=telephone]'),
            flatRateShipping: () => this.page.locator('[value="flatrate_flatrate"]'),
            continueButton: () => this.page.locator('.action.continue'),
            submitCheckout: () => this.page.locator('.payment-method._active .action.checkout'),
            orderRowStatus: orderNumber => this.page.locator(`.data-row:has(td:has-text("${orderNumber}")) td:nth-child(9)`),
            orderNumber: () => this.page.locator('.checkout-success p>span')
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
        await this.#waitForFinishLoading();
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
        await this.#waitForFinishLoading();
        await this.locators.flatRateShipping().click();
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

    /**
    * Provide the locator to input the payment method
    * @param {Object} options
    * @param {string} options.product seQura product (i1, pp3, etc)
    * @param {boolean} options.checked Whether the payment method should be checked
    * @returns {import("@playwright/test").Locator}
    */
    paymentMethodInputLocator(options) {
        return this.page.locator(`#sequra_${options.product}${options.checked ? ':checked' : ''}`);
    }

    /**
     * Provide the locator to input the payment method
     * @param {Object} options
     * @param {string} options.product seQura product (i1, pp3, etc)
     * @param {string} options.title Payment method title as it appears in the UI
     * @returns {import("@playwright/test").Locator}
     */
    paymentMethodTitleLocator(options) {
        return this.page.locator(`#sequra_${options.product} + label`).getByText(options.title);
    }

    /**
     * Provide the locator seQura payment methods
     * @param {Object} options
     * @returns {import("@playwright/test").Locator}
     */
    paymentMethodsLocator(options) {
        return this.page.locator('[id^="sequra_"]');
    }

    /**
    * Select the payment method and place the order
    * @param {Object} options 
    * @param {string} options.product seQura product (i1, pp3, etc)
    * @param {string} options.dateOfBirth Date of birth
    * @param {string} options.dni National identification number
    * @param {string[]} options.otp Digits of the OTP
    */
    async placeOrder(options) {
        await this.locators.paymentMethodInput({ ...options, checked: false }).click();
        await this.#waitForFinishLoading();
        await this.locators.submitCheckout().click();
        await this.#waitForFinishLoading();
        // Fill checkout form.
        switch (options.product) {
            case 'i1':
                await this.fillI1CheckoutForm(options);
                break;
            case 'pp3':
                await this.fillPp3CheckoutForm(options);
                break;
            case 'sp1':
                await this.fillSp1CheckoutForm(options);
                break;
            default:
                throw new Error(`Unknown product ${options.product}`);
        }
    }

    /**
     * Provide the locator for the moreInfo tag 
     * 
     * @param {Object} options
     * @param {string} options.product seQura product (i1, pp3, etc)
     * @returns {import("@playwright/test").Locator}
     */
    moreInfoLinkLocator(options) {
        return this.page.locator(`label[for="sequra_${options.product}"] .sequra-educational-popup`)
    }

    /**
    * Define the expected behavior after placing an order
    * @param {Object} options 
    */
    async waitForOrderSuccess(options) {
        await this.page.waitForURL(/checkout\/onepage\/success\//, { timeout: 30000, waitUntil: 'commit' });
    }

    /**
     * Read the order number from the success page
     * 
     * @returns {Promise<string>}
     */
    async getOrderNumber() {
        return await this.locators.orderNumber().textContent();
    }

    /**
    * Expects the order to have the expected status
    * @param {Object} options 
    * @param {string} options.orderNumber The order number
    * @param {string} options.status The expected status
    * @returns {Promise<void>}
    */
    async expectOrderHasStatus(options) {
        const { orderNumber, status } = options;
        await this.expect(this.locators.orderRowStatus(orderNumber)).toHaveText(status);
    }

    /**
    * The timeout to wait before retrying to check the order status
    * @param {Object} options 
    * @returns {int}
    */
    getOrderStatusTimeoutInMs(options) {
        return 0;
    }

    /**
    * Check if the order changes to the expected state
    * @param {BackOffice} backOffice
    * @param {Object} options
    * @param {string} options.toStatus The expected status
    * @param {string} options.fromStatus The initial status. Optional
    * @param {int} options.waitFor The maximum amount of seconds to wait for the order status to change
    */
    async expectOrderChangeTo(backOffice, options) {
        const { toStatus, fromStatus = null, waitFor = 60 } = options;
        const orderNumber = await this.getOrderNumber();
        await backOffice.gotoOrderListing();
        if (fromStatus) {
            await this.waitForOrderStatus({ orderNumber, status: fromStatus, waitFor: 10 });
        }
        await this.waitForOrderStatus({ orderNumber, status: toStatus, waitFor });
    }
}