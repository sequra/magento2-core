import { test } from '../fixtures/test';

test.describe('Configuration Onboarding', () => {
  test('Configure using dummy', async ({ helper, dataProvider, onboardingSettingsPage }) => {
    // Setup
    const { clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration
    const connect = { username: process.env.SQ_USER_NAME, password: process.env.SQ_USER_SECRET };
    const countriesForm = { countries: dataProvider.countriesMerchantRefs(connect.username) };
    const widgetForm = { assetsKey: process.env.SQ_ASSETS_KEY };
    // Execution
    await onboardingSettingsPage.goto();
    await onboardingSettingsPage.fillConnectForm(connect);
    await onboardingSettingsPage.fillCountriesForm(countriesForm);
    await onboardingSettingsPage.fillWidgetsForm(widgetForm);
  });
});