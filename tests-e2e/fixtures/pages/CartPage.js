import { CartPage as BaseCartPage } from "playwright-fixture-for-plugins";

/**
 * Cart page
 */
export default class CartPage extends BaseCartPage {

    /**
     * Provide the cart URL
     * @param {Object} options
     * @returns {string} The cart URL
     */
    cartUrl(options) {
        return `${this.baseURL}/checkout/cart/`;
    }

    /**
     * Provide the locator for the coupon input
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator}
     */
    couponInputLocator(options) {
        // TODO: Implement this method
        throw new Error('Not implemented');
    }

    /**
     * Provide the locator for the apply coupon button
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator}
     */
    applyCouponBtnLocator(options) {
        // TODO: Implement this method
        throw new Error('Not implemented');
    }

    /**
     * Provide the locator for the remove coupon button
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator}
     */
    removeCouponBtnLocator(options) {
        // TODO: Implement this method
        throw new Error('Not implemented');
    }

    /**
     * Provide the locator for the quantity input
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator}
     */
    quantityInputLocator(options) {
        return this.page.locator('.field.qty input');
    }

    /**
     * Provide the locator for the update cart button
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator|null} The locator for the update cart button, or null if not applicable
     */
    updateCartBtnLocator(options) {
        return this.page.locator('.action.update');
    }

    /**
     * Some systems have a button to expand the coupon form
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator|null} The locator for the expand coupon form button, or null if not applicable
     */
    expandCouponFormBtnLocator(options) {
        // TODO: Implement this method
        throw new Error('Not implemented');
    }

    /**
     * Provide the locator to look for the text when the cart is empty
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator|null}
     */
    cartIsEmptyTextLocator(options) {
        // TODO: Implement this method
        throw new Error('Not implemented');
    }

    /**
     * Provide the locator for the remove cart item buttons
     * @param {Object} options Additional options if needed
     * @returns {import("@playwright/test").Locator}
     */
    removeCartItemBtnLocator(options) {
        // TODO: Implement this method
        throw new Error('Not implemented');
    }
}