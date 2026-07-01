import { Page } from "playwright-fixture-for-plugins";

/**
 * Storefront customer account page.
 */
export default class AccountPage extends Page {

    /**
    * Init the locators with the locators available
    *
    * @returns {Object}
    */
    initLocators() {
        return {
            ...super.initLocators(),
            email: () => this.page.locator('[name="login[username]"]'),
            password: () => this.page.locator('[name="login[password]"]'),
            submit: () => this.page.locator('#send2'),
        };
    }

    /**
    * Provide the login URL
    * @returns {string} The login URL
    */
    loginUrl() {
        return `${this.baseURL}/customer/account/login/`;
    }

    /**
     * Navigate to the login page
     * @returns {Promise<void>}
     */
    async goto() {
        await this.page.goto(this.loginUrl());
    }

    /**
     * Log a registered customer in and wait for the account dashboard.
     * @param {Object} options
     * @param {string} options.email Customer email
     * @param {string} options.password Customer password
     * @returns {Promise<void>}
     */
    async login(options) {
        const { email, password } = options;
        await this.goto();
        await this.locators.email().fill(email);
        await this.locators.password().fill(password);
        await this.locators.submit().click();
        await this.page.waitForURL(/customer\/account\//, { timeout: 20000 });
    }
}
