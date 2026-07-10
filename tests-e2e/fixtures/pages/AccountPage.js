import { AccountPage as Base } from "playwright-fixture-for-plugins";

/**
 * Magento storefront customer account page.
 */
export default class AccountPage extends Base {

    /**
    * Init the locators with the locators available
    * @returns {Object}
    */
    initLocators() {
        return {
            ...super.initLocators(),
            email: () => this.page.locator('[name="login[username]"]'),
            password: () => this.page.locator('[name="login[password]"]'),
            submit: () => this.page.locator('button.action.login.primary'),
        };
    }

    /**
    * Provide the login URL
    * @returns {string}
    */
    loginUrl() {
        return `${this.baseURL}/customer/account/login/`;
    }

    /**
     * Wait for the account dashboard — not the login page, which also contains "customer/account/".
     * @returns {Promise<void>}
     */
    async expectLoggedIn() {
        await this.page.waitForURL(/\/customer\/account\/(?!login)/, { timeout: 20000 });
    }
}
