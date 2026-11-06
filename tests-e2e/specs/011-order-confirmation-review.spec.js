import { test } from '../fixtures/test';

test.describe('Order confirmation page', () => {
  test.beforeEach(async ({ helper, dataProvider, productPage, checkoutPage }) => {
    await helper.executeWebhook({ webhook: helper.webhooks.clear_config });
    await helper.executeWebhook({ webhook: helper.webhooks.dummy_config });

    const shopper = dataProvider.shopper();
    await productPage.addToCart({ slug: 'push-it-messenger-bag', quantity: 1 });
    await checkoutPage.goto();
    await checkoutPage.fillForm(shopper);
    await checkoutPage.placeOrder({ ...shopper, product: 'i1' });
    await checkoutPage.waitForOrderSuccess();
  });

  test('tells the shopper that seQura is processing the request when the payment is on hold', async ({ helper, checkoutPage }) => {
    const cartId = await helper.putOrderOnHold(await checkoutPage.getOrderNumber());

    await checkoutPage.returnFromSeQura(cartId);

    await checkoutPage.expectReviewMessageToBeVisible();
  });

  test('shows no review message when the payment is approved', async ({ checkoutPage }) => {
    await checkoutPage.expectReviewMessageNotToBeVisible();
  });
});
