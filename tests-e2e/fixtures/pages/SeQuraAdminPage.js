import { Page } from 'playwright-fixture-for-plugins';

export default class SeQuraAdminPage extends Page {
    initLocators() {
        return {
            portalLink: () => this.page.locator('.sqp-portal-link'),
            deploymentsForm: () => this.page.locator('.sq-content-inner.sqv--deployments'),
            connectForm: () => this.page.locator('.sq-content-inner.sqv--connect'),
        };
    }

    async expectConnectionSettingsPage(options = {}) {
        await this.expectConnectFormAt('#settings-connection', 'The application settled on the connection settings', options);
    }

    async expectConnectStep(options = {}) {
        await this.expectConnectFormAt('#onboarding-connect', 'The application stays on the connect step', options);
    }

    async expectConnectFormAt(hash, message, options = {}) {
        const { timeout = 30000 } = options;

        await this.expect(this.locators.connectForm(), 'The connect form is shown').toBeVisible({ timeout });
        await this.expect.poll(() => new URL(this.page.url()).hash, { message, timeout }).toBe(hash);
    }

    async expectDeploymentTargetsStep(options = {}) {
        const { timeout = 30000 } = options;

        await this.expect(this.locators.deploymentsForm(), 'The deployment targets step is shown').toBeVisible({ timeout });
    }

    async expectPortalLinkToPointAtSeQura() {
        const link = this.locators.portalLink();

        await this.expect(link, 'The header links to the seQura portal').toHaveCount(1);
        await this.expect(link, 'The portal link opens in a new tab').toHaveAttribute('target', '_blank');
        await this.expect(link, 'The portal link points at seQura').toHaveAttribute('href', /^https?:\/\/[^/]*sequra/);
    }
}
