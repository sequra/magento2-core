import { env } from 'process';
import { test } from '../fixtures/test';

test.describe('Connection settings', () => {

  test('Disconnect', async ({ helper, connectionSettingsPage }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config }); // Setup for physical products.
    const credential = {
      username: process.env.SQ_USER_NAME,
      password: process.env.SQ_USER_SECRET,
      name: 'SVEA'
    };

    // Execution
    await connectionSettingsPage.goto();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.disconnect({ credential }); // Disconnect the SVEA credential.
    await connectionSettingsPage.fillManageDeploymentTargetsForm({ credential, save: false }); // Test the cancel button in the manage deployment targets form.
    await connectionSettingsPage.fillManageDeploymentTargetsForm({ credential, save: true }); // Test the save button in the manage deployment targets form.
    await connectionSettingsPage.disconnectAll(); // Disconnect all credentials.
  });

  test('Change', async ({ helper, page, connectionSettingsPage }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config }); // Setup for physical products.

    const options = {
      credentials: [
        {
          username: process.env.SQ_USER_NAME,
          password: process.env.SQ_USER_SECRET,
          name: 'seQura'
        }
      ],
      env: 'sandbox'
    };
    const optionsForServices = {
      credentials: [
        {
          username: process.env.SQ_USER_SERVICES_NAME,
          password: process.env.SQ_USER_SERVICES_SECRET,
          name: 'seQura'
        }
      ],
      env: 'sandbox'
    };
    const wrongSandboxOptions = {
      ...options,
      credentials: [
        {
          ...options.credentials[0],
          password: '1234' // Wrong password for sandbox.
        }
      ]
    };
    const wrongLiveOptions = {
      ...options,
      env: 'live', // Change to live environment.
    };

    // Execution
    await connectionSettingsPage.goto();
    await connectionSettingsPage.expectLoadingShowAndHide();

    // Test cancellation of the changes
    await connectionSettingsPage.fillForm(wrongSandboxOptions);
    await connectionSettingsPage.cancel();
    await connectionSettingsPage.expectFormToHaveValues({
      env: options.env,
      ...options.credentials[0]
    });

    // Test wrong values keeping env in sandbox.
    await connectionSettingsPage.fillForm(wrongSandboxOptions);
    await connectionSettingsPage.save();
    await connectionSettingsPage.expectCredentialsErrorToBeVisible();

    // Test wrong values changing env to live.
    await page.reload();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.fillForm(wrongLiveOptions);
    await connectionSettingsPage.save({ expectLoadingShowAndHide: false });
    await connectionSettingsPage.confirmModal();
    await connectionSettingsPage.expectCredentialsErrorToBeVisible();

    // Test valid values.
    await page.reload();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.fillForm(optionsForServices);
    await connectionSettingsPage.save({ expectLoadingShowAndHide: true });
    // await page.waitForURL(/#onboarding-countries/);
  });
});