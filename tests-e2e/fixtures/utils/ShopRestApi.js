export default class ShopRestApi {
    constructor(baseURL, expect, request) {
        this.baseURL = baseURL;
        this.expect = expect;
        this.request = request;
        this.token = null;
    }

    async getOrderId(incrementId) {
        const query = new URLSearchParams({
            'searchCriteria[filterGroups][0][filters][0][field]': 'increment_id',
            'searchCriteria[filterGroups][0][filters][0][value]': incrementId,
        });
        const { items } = await this.call('get', `orders?${query}`);
        this.expect(items, `Order "${incrementId}" exists`).toHaveLength(1);

        return items[0].entity_id;
    }

    async shipOrder(orderId, tracks) {
        return this.call('post', `order/${orderId}/ship`, { tracks: tracks.map(this.toTrackCreation) });
    }

    async addTrack(orderId, shipmentId, track) {
        const created = await this.call('post', 'shipment/track', {
            entity: { order_id: orderId, parent_id: shipmentId, ...this.toTrackCreation(track) },
        });

        return created.entity_id;
    }

    async getTrackIds(shipmentId) {
        const { tracks } = await this.call('get', `shipment/${shipmentId}`);

        return tracks.map(({ entity_id }) => entity_id);
    }

    async deleteTrack(trackId) {
        await this.call('delete', `shipment/track/${trackId}`);
    }

    toTrackCreation({ number, title, carrierCode }) {
        return { track_number: number, title, carrier_code: carrierCode };
    }

    async call(method, path, data = undefined) {
        const response = await this.request[method](`${this.baseURL}/rest/V1/${path}`, {
            headers: { Authorization: `Bearer ${await this.getToken()}` },
            data,
        });
        this.expect(response.ok(), `${method.toUpperCase()} /V1/${path} answers ${response.status()}`).toBeTruthy();

        return response.json();
    }

    async getToken() {
        if (!this.token) {
            const response = await this.request.post(`${this.baseURL}/rest/V1/integration/admin/token`, {
                data: { username: process.env.M2_ADMIN_USER, password: process.env.M2_ADMIN_PASSWORD },
            });
            this.expect(response.ok(), 'The admin gets a REST API token').toBeTruthy();
            this.token = await response.json();
        }

        return this.token;
    }
}
