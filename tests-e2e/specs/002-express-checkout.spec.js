import { test, expect } from '../fixtures/test';

test.describe('Express checkout', () => {

  test.beforeEach(async ({ helper }) => {
    const { clear_config, dummy_config, clear_front_end_cache } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    // Setup the dummy merchant with Express Checkout enabled.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'express', value: '1' }] });
    await helper.executeWebhook({ webhook: clear_front_end_cache }); // Clear the page cache.
  });

  test('The express checkout button appears on the product, cart and mini-cart', async ({ dataProvider, accountPage, productPage, cartPage, expressCheckoutPage }) => {
    await accountPage.login(dataProvider.customer());

    await productPage.addToCart({ slug: 'push-it-messenger-bag', quantity: 1 });
    await expressCheckoutPage.expectButtonVisible('product');

    await expressCheckoutPage.openMiniCart();
    await expressCheckoutPage.expectButtonVisible('mini-cart');

    await cartPage.goto();
    await expressCheckoutPage.expectButtonVisible('cart');
  });

  test('A guest opens the express checkout form without being asked to log in', async ({ productPage, cartPage, expressCheckoutPage }) => {
    // No accountPage.login(): express runs on the guest session cart, and startCheckout() waits
    // for the identification form — which never appears if the solicit answers with a login gate.
    await productPage.addToCart({ slug: 'push-it-messenger-bag', quantity: 1 });
    await expressCheckoutPage.expectButtonVisible('product');

    await cartPage.goto();
    await expressCheckoutPage.expectButtonVisible('cart');
    await expressCheckoutPage.startCheckout('cart');
  });

  test('The cart summary gets its data without posting into the form before it has loaded', async ({ page, productPage, cartPage, expressCheckoutPage }) => {
    // The browser drops a message targeted at the form's origin while the iframe still holds its
    // initial about:blank, and logs this warning for each one.
    const refusedPosts = [];
    page.on('console', message => {
      if (message.text().includes('The target origin provided')) refusedPosts.push(message.text());
    });

    await productPage.addToCart({ slug: 'push-it-messenger-bag', quantity: 1 });
    await cartPage.goto();

    const cartDataReceived = page.evaluate(() => new Promise(resolve => {
      window.addEventListener('message', event => {
        if (typeof event.data === 'string' && event.data.includes('"Sequra.cartDataReceived"')) resolve();
      });
    }));
    await expressCheckoutPage.startCheckout('cart');
    await cartDataReceived;

    expect(refusedPosts).toEqual([]);
  });

  test('A registered customer completes an express checkout purchase from the product page', async ({ helper, dataProvider, accountPage, productPage, expressCheckoutPage, checkoutPage }) => {
    await accountPage.login(dataProvider.customer());
    const shopper = dataProvider.shopper('spain');

    await productPage.goto({ slug: 'push-it-messenger-bag' });
    await expressCheckoutPage.startCheckout('product');
    // The logged-in shopper's name, birth date and phone are pre-filled; only the NIN is supplied.
    await checkoutPage.fillExpressCheckoutForm('i1', { nin: shopper.nin, otp: shopper.otp });
    await checkoutPage.waitForOrderSuccess();
    await checkoutPage.expectOrderHasTheCorrectMerchantId('ES', helper, dataProvider);
  });
});
