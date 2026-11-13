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
            clear_front_end_cache: 'clear_front_end_cache',
            verify_order_has_merchant_id: 'verify_order_has_merchant_id',
            set_config: 'set_config',
            get_order_trackings: 'get_order_trackings',
            seed_order_cleanup_solicitations: 'seed_order_cleanup_solicitations',
            get_order_cleanup_state: 'get_order_cleanup_state',
            set_order_cleanup_next_run: 'set_order_cleanup_next_run',
            wake_task_runner: 'wake_task_runner',
            put_order_on_hold: 'put_order_on_hold',
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

    async getOrderTrackings(orderNumber) {
        const [, { trackings }] = await this.executeWebhook({
            webhook: this.webhooks.get_order_trackings,
            args: [{ name: 'order_id', value: orderNumber }],
            returnResponse: true,
        });

        return trackings;
    }

    async seedOrderCleanupSolicitations(orderNumber) {
        const [, references] = await this.executeWebhook({
            webhook: this.webhooks.seed_order_cleanup_solicitations,
            args: [{ name: 'order_id', value: orderNumber }],
            returnResponse: true,
        });

        return references;
    }

    async getOrderCleanupState(references = []) {
        const [, state] = await this.executeWebhook({
            webhook: this.webhooks.get_order_cleanup_state,
            args: [{ name: 'references', value: references.join(',') }],
            returnResponse: true,
        });

        return state;
    }

    async setOrderCleanupNextRun(timestamp) {
        await this.executeWebhook({
            webhook: this.webhooks.set_order_cleanup_next_run,
            args: [{ name: 'timestamp', value: String(timestamp) }],
        });
    }

    async wakeTaskRunner() {
        await this.executeWebhook({ webhook: this.webhooks.wake_task_runner });
    }

    async putOrderOnHold(orderNumber) {
        const [, { cart_id }] = await this.executeWebhook({
            webhook: this.webhooks.put_order_on_hold,
            args: [{ name: 'order_id', value: orderNumber }],
            returnResponse: true,
        });

        return cart_id;
    }
}
