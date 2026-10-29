import { test, expect } from '../fixtures/test';

const DHL = { number: 'JD014600006281', title: 'DHL Express', carrierCode: 'dhl' };
const UPS = { number: '1Z999AA10123456784', title: '', carrierCode: 'ups' };

const expectedTracking = (trackId, { number }, carrier) => ({
  type: 'postal',
  reference: String(trackId),
  tracking_number: number,
  carrier,
  tracking_url: expect.stringMatching(/\/shipping\/tracking\/popup\?hash=/),
});

const expectTrackings = async (helper, orderNumber, trackings, message) => {
  await expect.poll(() => helper.getOrderTrackings(orderNumber), { message, timeout: 30000 }).toEqual(trackings);
};

test.describe('Shipment tracking', () => {
  test.beforeEach(async ({ helper }) => {
    await helper.executeWebhook({ webhook: helper.webhooks.clear_config });
    await helper.executeWebhook({ webhook: helper.webhooks.dummy_config });
  });

  test('sends the tracking numbers of the shipments to seQura as they are added and deleted through the REST API', async ({ helper, dataProvider, productPage, checkoutPage, shopRestApi }) => {
    test.slow();
    const shopper = dataProvider.shopper();

    await productPage.addToCart({ slug: 'push-it-messenger-bag', quantity: 1 });
    await checkoutPage.goto();
    await checkoutPage.fillForm(shopper);
    await checkoutPage.placeOrder({ ...shopper, product: 'i1' });
    await checkoutPage.waitForOrderSuccess();

    const orderNumber = await checkoutPage.getOrderNumber();
    const orderId = await shopRestApi.getOrderId(orderNumber);
    await expectTrackings(helper, orderNumber, [], 'A new order has no trackings');

    const shipmentId = await shopRestApi.shipOrder(orderId, [DHL]);
    const [dhlTrackId] = await shopRestApi.getTrackIds(shipmentId);
    await expectTrackings(
      helper,
      orderNumber,
      [expectedTracking(dhlTrackId, DHL, DHL.title)],
      'The tracking number the shipment was created with is sent'
    );

    const upsTrackId = await shopRestApi.addTrack(orderId, shipmentId, UPS);
    await expectTrackings(
      helper,
      orderNumber,
      [expectedTracking(dhlTrackId, DHL, DHL.title), expectedTracking(upsTrackId, UPS, UPS.carrierCode)],
      'An added tracking number is sent with the others, carried by its carrier code when it has no title'
    );

    await shopRestApi.deleteTrack(upsTrackId);
    await expectTrackings(
      helper,
      orderNumber,
      [expectedTracking(dhlTrackId, DHL, DHL.title)],
      'A deleted tracking number is no longer sent'
    );

    await shopRestApi.deleteTrack(dhlTrackId);
    await expectTrackings(helper, orderNumber, [], 'Deleting the last tracking number clears the trackings');
  });
});
