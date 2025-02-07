import { test } from '../fixtures/test';

test.describe('Connection settings', () => {

  test('Disconnect', async ({ helper, connectionSettingsPage }) => {
    // Setup
    const { dummy_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: dummy_config }); // Setup for physical products.

    // Execution
    await connectionSettingsPage.goto();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.disconnect();
  });

  test('Change', async ({ helper, page, connectionSettingsPage }) => {
    // Setup
    const { dummy_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: dummy_config }); // Setup for physical products.

    const credentials = {
      username: process.env.SQ_USER_NAME,
      password: process.env.SQ_USER_SECRET,
      env: 'sandbox'
    };

    const credentialsForServices = {
      ...credentials,
      username: process.env.SQ_USER_SERVICES_NAME,
      password: process.env.SQ_USER_SERVICES_SECRET
    };

    const wrongSandboxCredentials = { ...credentials, password: '1234' };
    const wrongLiveCredentials = { ...credentials, env: 'live' };

    // Execution
    await connectionSettingsPage.goto();
    await connectionSettingsPage.expectLoadingShowAndHide();

    // Test cancellation of the changes
    await connectionSettingsPage.fillForm(wrongSandboxCredentials);
    await connectionSettingsPage.cancel();
    await connectionSettingsPage.expectFormToHaveValues(credentials);

    // Test wrong values keeping env in sandbox.
    await connectionSettingsPage.fillForm(wrongSandboxCredentials);
    await connectionSettingsPage.save();
    await connectionSettingsPage.expectCredentialsErrorToBeVisible();

    // Test wrong values changing env to live.
    await page.reload();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.fillForm(wrongLiveCredentials);
    await connectionSettingsPage.save({ expectLoadingShowAndHide: false });
    await connectionSettingsPage.confirmModal();
    await connectionSettingsPage.expectCredentialsErrorToBeVisible();

    // Test valid values.
    await page.reload();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.fillForm(credentialsForServices);
    await connectionSettingsPage.save({ expectLoadingShowAndHide: true });
    await page.waitForURL(/#onboarding-countries/);
  });
});