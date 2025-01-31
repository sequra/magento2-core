import { BackOffice as BaseBackOffice } from 'playwright-fixture-for-plugins';
export default class BackOffice extends BaseBackOffice {

    /**
     * Init the locators with the locators available
     * 
     * @returns {Object}
     */
    initLocators() {
        return {
            usernameInput: () => this.page.locator('#username'),
            passwordInput: () => this.page.locator('#login'),
            loginButton: () => this.page.locator('.action-login'),
            // menuBarItemSales: () => this.page.locator('#menu-magento-sales-sales'),
            menuBarItemSeQuraLink: () => this.page.locator('[data-ui-id="menu-sequra-core-menu"] > a'),
        };
    }

    /**
     * Login
     * 
     * @param {Object} options Additional options
     * @returns {Promise<void>}
     */
    async login(options = { waitUntil: 'load' }) {
        const user = process.env.M2_ADMIN_USER;
        const pass = process.env.M2_ADMIN_PASSWORD;
        const usernameInput = this.locators.usernameInput();

        try {
            await this.page.goto(`${this.baseURL}/${process.env.M2_BACKEND_FRONTNAME}`, { waitUntil: 'domcontentloaded' });
            await this.expect(usernameInput, 'Username input is visible').toBeVisible({ timeout: 100 });
        }
        catch {
            return;
        }

        console.log(`Logging in as user: "${user}" with password: "${pass}"`);

        await usernameInput.fill(user);
        await this.locators.passwordInput().fill(pass);
        await this.locators.loginButton().click();
        await this.page.waitForURL(/admin/, options);
    }

    /**
     * Logout
     * 
     * @param {Object} options Additional options
     * @returns {Promise<void>}
     */
    async logout(options = {}) {
        throw new Error('Not implemented');
    }

    /**
     * Navigate to SeQura settings page
     * 
     * @param {Object} options
     * @param {string} options.page The page within settings to navigate to
     */
    async gotoSeQuraSettings(options = { page: '' }) {
        await this.login();
        // await this.locators.menuBarItemSales().click();
        const link = this.locators.menuBarItemSeQuraLink();
        const url = (await link.getAttribute('href')) + `#${options.page}`;
        await this.page.goto(url, { waitUntil: 'domcontentloaded' });
    }
}