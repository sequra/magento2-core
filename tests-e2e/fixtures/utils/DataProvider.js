import { DataProvider as BaseDataProvider } from 'playwright-fixture-for-plugins';

export default class DataProvider extends BaseDataProvider {

    /**
    * Prepare the URL to use
    * 
    * @param {Object} options Additional options
    * @param {string} options.webhook The webhook
    * @param {Array<Object>} options.args The arguments to pass to the webhook. Each argument is an object with `name` and `value` properties
    * @returns {string} The URL to use
    */
    getWebhookUrl(options = { webhook, args: [] }) {
        const { webhook, args } = options;
        return `${this.baseURL}/rest/V1/sequrahelper/webhook/?sq-webhook=${webhook}${this.getWebhookUrlArgs(args)}`;
    }

    /**
    * Registered customer credentials. Defaults to the Luma sample account (customer id 1),
    * overridable via env for other environments.
    * @returns {{email: string, password: string}}
    */
    customer() {
        return {
            email: process.env.M2_CUSTOMER_EMAIL || 'roni_cost@example.com',
            password: process.env.M2_CUSTOMER_PASSWORD || 'roni_cost3@example.com',
        };
    }

    deploymentTargetCredentials() {
        return this.deploymentTargetsOptions().map(({ name }) => ({
            name,
            username: process.env.SQ_USER_NAME,
            password: process.env.SQ_USER_SECRET,
        }));
    }

    wrongDeploymentTargetCredentials() {
        const [first] = this.deploymentTargetCredentials();

        return [{ ...first, password: 'not-the-password' }];
    }
}
