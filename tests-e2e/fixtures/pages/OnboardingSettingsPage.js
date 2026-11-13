import { OnboardingSettingsPage as OnboardingSettingsPageBase } from 'playwright-fixture-for-plugins';

export default class OnboardingSettingsPage extends OnboardingSettingsPageBase {
    async fillConnectForm(options) {
        const { credentials, env } = options;

        await this.connectionSettingsPage.fillForm({ credentials, env });
        await this.locators.primaryButton().click();
    }
}
