import { HomePage as BaseHomePage } from "playwright-fixture-for-plugins";

/**
 * Home page
 */
export default class HomePage extends BaseHomePage {

    /**
    * Provide the home page URL
    * @param {Object} options
    * @returns {string} The home page URL
    */
    homeUrl(options) {
        return `${this.baseURL}/`;
    }
}
