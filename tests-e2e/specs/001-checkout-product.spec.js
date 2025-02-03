import { test } from '../fixtures/test';

test.describe('Product checkout', () => {

  test('All available seQura products appear in the checkout', async ({ helper, dataProvider, productPage, checkoutPage }) => {
    // Setup
    const { dummy_config } = helper.webhooks;
    const shopper = dataProvider.shopper();
    const paymentMethods = dataProvider.checkoutPaymentMethods();
    await helper.executeWebhook({ webhook: dummy_config }); // Setup for physical products.

    // Execution
    await productPage.addToCart({ slug: 'push-it-messenger-bag', quantity: 1 });
    await checkoutPage.goto();
    await checkoutPage.fillForm(shopper);
    for (const paymentMethod of paymentMethods) {
      await checkoutPage.expectPaymentMethodToBeVisible(paymentMethod);
    }
  });

  // test('Make a successful payment using any shopper name', async ({ productPage, checkoutPage }) => {
  //   await checkoutPage.setupForPhysicalProducts();
  //   await productPage.addToCart({ slug: 'sunglasses', quantity: 1 });

  //   await checkoutPage.goto();
  //   await checkoutPage.fillWithNonSpecialShopperName({});
  //   await checkoutPage.expectPaymentMethodsBeingReloaded();
  //   await checkoutPage.placeOrderUsingI1({ shopper: 'nonSpecial' });
  //   await checkoutPage.waitForOrderSuccess();
  // });

  // test('Make a 🍊 payment with "Review test approve" names', async ({ productPage, checkoutPage }) => {
  //   await checkoutPage.setupForPhysicalProducts();
  //   await productPage.addToCart({ slug: 'sunglasses', quantity: 1 });

  //   await checkoutPage.goto();
  //   await checkoutPage.fillWithReviewTest({});
  //   await checkoutPage.expectPaymentMethodsBeingReloaded();
  //   await checkoutPage.placeOrderUsingI1({});
  //   await checkoutPage.waitForOrderOnHold();
  //   await checkoutPage.expectOrderChangeTo({ toStatus: 'wc-processing' });
  // });

  // test('Make a 🍊 payment with "Review test cancel" names', async ({ productPage, checkoutPage }) => {
  //   await checkoutPage.setupForPhysicalProducts();
  //   await productPage.addToCart({ slug: 'sunglasses', quantity: 1 });

  //   await checkoutPage.goto();
  //   await checkoutPage.fillWithReviewTest({ shopper: 'cancel' });
  //   await checkoutPage.expectPaymentMethodsBeingReloaded();
  //   await checkoutPage.placeOrderUsingI1({});
  //   await checkoutPage.waitForOrderOnHold();
  //   await checkoutPage.expectOrderChangeTo({ toStatus: 'wc-cancelled' });
  // });
});