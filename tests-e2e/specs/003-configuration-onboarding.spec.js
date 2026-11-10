import { test } from '../fixtures/test';

test.describe('Configuration Onboarding', () => {
  test.beforeEach(async ({ helper }) => {
    await helper.executeWebhook({ webhook: helper.webhooks.clear_config });
  });

  test('connects every selected deployment target', async ({ dataProvider, onboardingSettingsPage, connectionSettingsPage, sequraAdmin }) => {
    const credentials = dataProvider.deploymentTargetCredentials();

    await onboardingSettingsPage.goto();
    await onboardingSettingsPage.expectLoadingShowAndHide();
    await onboardingSettingsPage.fillDeploymentTargetsForm({ deploymentTargets: dataProvider.deploymentTargetsOptions() });
    await onboardingSettingsPage.fillConnectForm({ env: 'sandbox', credentials });

    await sequraAdmin.expectConnectionSettingsPage();
    await sequraAdmin.expectPortalLinkToPointAtSeQura();

    await connectionSettingsPage.goto();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.expectDeploymentTargets(credentials.map(({ name }) => name));
    await connectionSettingsPage.expectCredentialsToBeSaved(credentials);
  });

  test('connects only the selected deployment target that has credentials', async ({ dataProvider, onboardingSettingsPage, connectionSettingsPage, sequraAdmin }) => {
    const [first] = dataProvider.deploymentTargetCredentials();

    await onboardingSettingsPage.goto();
    await onboardingSettingsPage.expectLoadingShowAndHide();
    await onboardingSettingsPage.fillDeploymentTargetsForm({ deploymentTargets: dataProvider.deploymentTargetsOptions() });
    await onboardingSettingsPage.fillConnectForm({ env: 'sandbox', credentials: [first] });

    await sequraAdmin.expectConnectionSettingsPage();

    await connectionSettingsPage.goto();
    await connectionSettingsPage.expectLoadingShowAndHide();
    await connectionSettingsPage.expectDeploymentTargets([first.name]);
    await connectionSettingsPage.expectCredentialsToBeSaved([first]);
  });

  test('reports refused credentials and stays on the connect step', async ({ dataProvider, onboardingSettingsPage, connectionSettingsPage, sequraAdmin }) => {
    await onboardingSettingsPage.goto();
    await onboardingSettingsPage.expectLoadingShowAndHide();
    await onboardingSettingsPage.fillDeploymentTargetsForm({ deploymentTargets: dataProvider.deploymentTargetsOptions() });
    await onboardingSettingsPage.fillConnectForm({ env: 'sandbox', credentials: dataProvider.wrongDeploymentTargetCredentials() });

    await connectionSettingsPage.expectCredentialsErrorToBeVisible();
    await sequraAdmin.expectConnectStep();
  });
});
