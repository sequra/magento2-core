import { SeQuraHelper as BaseSeQuraHelper } from 'playwright-fixture-for-plugins';

export default class SeQuraHelper extends BaseSeQuraHelper {

    /**
     * Init the webhooks available
     * 
     * @returns {Object} The webhooks available
     */
    initWebhooks() {
        return {
            clear_config: 'clear_config',
            dummy_config: 'dummy_config',
            // dummyServicesConfig: 'dummy_services_config',
            // removeDbTables: 'remove_db_tables'
        };
    }

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
}