import { test as baseTest, expect } from "@playwright/test";
import BackOffice from "./base/BackOffice";
import SeQuraHelper from "./utils/SeQuraHelper";
import DataProvider from "./utils/DataProvider";
import ShopRestApi from "./utils/ShopRestApi";
import ProductPage from "./pages/ProductPage";
import CheckoutPage from "./pages/CheckoutPage";
import CartPage from "./pages/CartPage";
import CategoryPage from "./pages/CategoryPage";
import HomePage from "./pages/HomePage";
import AccountPage from "./pages/AccountPage";
import ExpressCheckoutPage from "./pages/ExpressCheckoutPage";
import OnboardingSettingsPage from "./pages/OnboardingSettingsPage";
import ConnectionSettingsPage from "./pages/ConnectionSettingsPage";
import SeQuraAdminPage from "./pages/SeQuraAdminPage";

const NGROK_HEADER = 'ngrok-skip-browser-warning';

const sendNgrokHeaderOnlyToTheShop = async (page, baseURL) => {
    const shopOrigin = new URL(baseURL).origin;
    await page.route(url => url.origin !== shopOrigin, route => {
        const { [NGROK_HEADER]: _, ...headers } = route.request().headers();
        return route.continue({ headers });
    });
};

const test = baseTest.extend({
    page: async ({ page, baseURL }, use) => {
        if (baseURL && baseURL.includes('ngrok')) {
            await sendNgrokHeaderOnlyToTheShop(page, baseURL);
        }
        await use(page);
    },
    dataProvider: async ({ page, baseURL, request }, use) => await use(new DataProvider(page, baseURL, expect, request)),
    backOffice: async ({ page, baseURL }, use) => await use(new BackOffice(page, baseURL, expect)),
    helper: async ({ page, baseURL, request }, use) => await use(new SeQuraHelper(page, baseURL, expect, request)),
    shopRestApi: async ({ baseURL, request }, use) => await use(new ShopRestApi(baseURL, expect, request)),
    productPage: async ({ page, baseURL, request}, use) =>  await use(new ProductPage(page, baseURL, expect, request)),
    cartPage: async ({ page, baseURL, request}, use) =>  await use(new CartPage(page, baseURL, expect, request)),
    categoryPage: async ({ page, baseURL, request}, use) =>  await use(new CategoryPage(page, baseURL, expect, request)),
    homePage: async ({ page, baseURL, request}, use) =>  await use(new HomePage(page, baseURL, expect, request)),
    onboardingSettingsPage: async ({ page, baseURL, request, backOffice, helper}, use) =>  await use(new OnboardingSettingsPage(page, baseURL, expect, request, backOffice, helper)),
    checkoutPage: async ({ page, baseURL, request}, use) =>  await use(new CheckoutPage(page, baseURL, expect, request)),
    accountPage: async ({ page, baseURL, request}, use) =>  await use(new AccountPage(page, baseURL, expect, request)),
    expressCheckoutPage: async ({ page, baseURL}, use) =>  await use(new ExpressCheckoutPage(page, baseURL, expect)),
    connectionSettingsPage: async ({ page, baseURL, request, backOffice, helper}, use) =>  await use(new ConnectionSettingsPage(page, baseURL, expect, request, backOffice, helper)),
    sequraAdmin: async ({ page, baseURL, request}, use) =>  await use(new SeQuraAdminPage(page, baseURL, expect, request)),
});

test.afterEach(async ({ page }, testInfo) => {
    if (testInfo.status !== testInfo.expectedStatus) {
        const screenshotPath = testInfo.outputPath(`screenshot.png`);
        testInfo.attachments.push({
            name: 'screenshot', path:
                screenshotPath, contentType: 'image/png'
        });
        await page.screenshot({ path: screenshotPath, fullPage: true });
    }
});

export { test, expect };