import { test as baseTest, expect } from "@playwright/test";
import { ConnectionSettingsPage, DataProvider, GeneralSettingsPage, OnboardingSettingsPage, PaymentMethodsSettingsPage } from "playwright-fixture-for-plugins";
import BackOffice from "./base/BackOffice";
import SeQuraHelper from "./utils/SeQuraHelper";
import ProductPage from "./pages/ProductPage";
import CheckoutPage from "./pages/CheckoutPage";

const test = baseTest.extend({
    dataProvider: async ({ page, baseURL, request }, use) => await use(new DataProvider(page, baseURL, expect, request)),
    backOffice: async ({ page, baseURL }, use) => await use(new BackOffice(page, baseURL, expect)),
    helper: async ({ page, baseURL, request }, use) => await use(new SeQuraHelper(page, baseURL, expect, request)),
    paymentMethodsSettingsPage: async ({ page, baseURL, request, backOffice, helper}, use) =>  await use(new PaymentMethodsSettingsPage(page, baseURL, expect, request, backOffice, helper)),
    productPage: async ({ page, baseURL, request}, use) =>  await use(new ProductPage(page, baseURL, expect, request)),
    onboardingSettingsPage: async ({ page, baseURL, request, backOffice, helper}, use) =>  await use(new OnboardingSettingsPage(page, baseURL, expect, request, backOffice, helper)),
    checkoutPage: async ({ page, baseURL, request}, use) =>  await use(new CheckoutPage(page, baseURL, expect, request)),
    generalSettingsPage: async ({ page, baseURL, request, backOffice, helper}, use) =>  await use(new GeneralSettingsPage(page, baseURL, expect, request, backOffice, helper)),
    connectionSettingsPage: async ({ page, baseURL, request, backOffice, helper}, use) =>  await use(new ConnectionSettingsPage(page, baseURL, expect, request, backOffice, helper)),
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