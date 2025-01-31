import { test } from '../fixtures/test';

test.describe('Configuration', () => {
  test('Payment methods', async ({ helper, dataProvider, paymentMethodsSettingsPage }) => {
    // Setup
    const { dummy_config } = helper.webhooks;
    const countries = dataProvider.countriesPaymentMethods();
    // Execution
    await helper.executeWebhook({ webhook: dummy_config });
    await paymentMethodsSettingsPage.goto();
    await paymentMethodsSettingsPage.expectAvailablePaymentMethodsAreVisible(countries);
  });
});