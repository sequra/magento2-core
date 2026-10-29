import { ConnectionSettingsPage as ConnectionSettingsPageBase } from 'playwright-fixture-for-plugins';

export default class ConnectionSettingsPage extends ConnectionSettingsPageBase {
    initLocators() {
        return {
            ...super.initLocators(),
            disconnect: () => this.page.locator('.sqm--bellow-frame button.sq-button.sqt--danger'),
            reRegisterWebhooks: () => this.page.locator('.sqm--bellow-frame button.sq-button.sqt--secondary'),
            successMessage: () => this.page.locator('.sqp-flash-message-wrapper .sq-alert.sqt--success'),
            errorMessage: () => this.page.locator('.sqp-flash-message-wrapper .sq-alert.sqt--error'),
        };
    }

    async expectDeploymentTargets(names) {
        await this.expect
            .poll(
                async () => (await this.locators.deploymentTargetTab().allInnerTexts()).map((text) => text.trim()),
                { message: `The store is connected to exactly ${names.join(', ')}` }
            )
            .toEqual(names);
    }

    async expectCredentialsToBeSaved(credentials) {
        for (const credential of credentials) {
            await this.locators.deploymentTargetTab(credential.name).click();
            await this.expect(
                this.locators.username(),
                `The username saved for the "${credential.name}" deployment target`
            ).toHaveValue(credential.username);
        }
    }

    async reRegisterWebhooks() {
        await this.locators.reRegisterWebhooks().click();
        await this.expectLoadingShowAndHide();
    }

    async expectCredentialsErrorToBeVisible() {
        await this.expect(this.locators.errorMessage(), 'The application reports the connection as refused').toBeVisible();
    }

    async expectSuccessMessageToBeVisible() {
        await this.expect(this.locators.successMessage(), 'The application reports the action as successful').toBeVisible();
        await this.expect(this.locators.errorMessage(), 'The application reports no error').toHaveCount(0);
    }
}
