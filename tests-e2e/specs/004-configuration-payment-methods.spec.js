import { test } from '../fixtures/test';

test.describe('Configuration', () => {
  test('Payment methods', async ({ helper, dataProvider, paymentMethodsSettingsPage }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config });
    await helper.executeWebhook({ webhook: dummy_config });
    const countries = dataProvider.countriesPaymentMethods();
    // Execution
    await paymentMethodsSettingsPage.goto();
    await paymentMethodsSettingsPage.expectAvailablePaymentMethodsAreVisible(countries);
  });
});