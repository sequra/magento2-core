import { test } from '../fixtures/test';

test.describe('Configuration Onboarding', () => {
  test('Configure using dummy', async ({ helper, dataProvider, onboardingSettingsPage }) => {
    // Setup
    const { clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration
    const credential = { name: 'seQura', username: process.env.SQ_USER_NAME, password: process.env.SQ_USER_SECRET };
    const connect = {
      env: 'sandbox', 
      credentials: [
        credential,
        { ...credential, name: 'SVEA' }
      ]
    };
    const countriesForm = { countries: dataProvider.countriesMerchantRefs(process.env.SQ_USER_NAME) };
    const deploymentTargets = { deploymentTargets: dataProvider.deploymentTargetsOptions() };
    // Execution
    await onboardingSettingsPage.goto();
    await onboardingSettingsPage.expectLoadingShowAndHide();
    await onboardingSettingsPage.fillDeploymentTargetsForm(deploymentTargets);
    await onboardingSettingsPage.fillConnectForm(connect);
    await onboardingSettingsPage.fillCountriesForm(countriesForm);
  });
});