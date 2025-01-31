import { test as baseTest, expect } from "@playwright/test";
import { PaymentMethodsSettingsPage } from "playwright-fixture-for-plugins";
import MagentoBackOffice from "./MagentoBackOffice";
import MagentoSeQuraHelper from "./MagentoSeQuraHelper";

const test = baseTest.extend({
    backOffice: async ({ page, baseURL }, use) => await use(new MagentoBackOffice(page, baseURL, expect)),
    helper: async ({ page, baseURL, request }, use) => await use(new MagentoSeQuraHelper(page, baseURL, expect, request)),
    paymentMethodsSettingsPage: async ({ page, baseURL, request, backOffice, helper}, use) =>  await use(new PaymentMethodsSettingsPage(page, baseURL, expect, request, backOffice, helper)),
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