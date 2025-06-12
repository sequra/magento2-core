import { test, expect } from '../fixtures/test';

async function assertWidgetAndPaymentMethodVisibility(available, productPage, cartPage, checkoutPage, dataProvider, helper) {
  await helper.executeWebhook({ webhook: helper.webhooks.clear_front_end_cache });
  const slugOpt = { slug: 'push-it-messenger-bag' };
  await productPage.goto(slugOpt);
  if (available) {
    await productPage.expectWidgetToBeVisible(dataProvider.pp3FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetToBeVisible(dataProvider.sp1FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetToBeVisible(dataProvider.i1FrontEndWidgetOptions(slugOpt));
  } else {
    await productPage.expectWidgetsNotToBeVisible();
  }
  await productPage.addToCart({ ...slugOpt, quantity: 1 });
  // TODO: Uncomment this when the additional validation for the cart page is implemented.
  // await cartPage.goto();
  // if (available) {
  //   await cartPage.expectWidgetToBeVisible(
  //     dataProvider.cartFrontEndWidgetOptions({ amount: 4500, registrationAmount: null })
  //   );
  // } else {
  //   await cartPage.expectWidgetsNotToBeVisible();
  // }
  await checkoutPage.goto();
  await checkoutPage.fillForm(dataProvider.shopper());
  await checkoutPage.expectAnyPaymentMethod({ available });
}

async function assertMiniWidgetVisibility(available, categoryPage) {
  await categoryPage.goto({ slug: 'gear/bags' });
  if (available) {
    await categoryPage.expectAnyVisibleMiniWidget('pp3');
  } else {
    await categoryPage.expectMiniWidgetsNotToBeVisible('pp3');
  }
}

test.describe('Configuration', () => {

  test('Change allowed IP addresses', async ({ helper, dataProvider, backOffice, page, generalSettingsPage, productPage, checkoutPage, cartPage }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '1' }] }); // Setup for physical products.

    const badIPAddressesMatrix = [
      ['a.b.c.d'],
      ['a.b.c.d', '1.1.1.256'],
      ['a.b.c.d', '1.1.1.256', 'lorem ipsum']
    ]

    const publicIP = await dataProvider.publicIP();
    const notAllowedIPAddressesMatrix = [
      ['8.8.8.8']
    ]
    const allowedIPAddressesMatrix = [
      [],
      [publicIP],
      [publicIP, ...notAllowedIPAddressesMatrix[0]]
    ]

    const fillAndAssert = async (ipAddresses, available) => {
      await generalSettingsPage.fillAllowedIPAddresses(ipAddresses);
      await generalSettingsPage.save({ skipIfDisabled: true });
      await backOffice.logout();
      await assertWidgetAndPaymentMethodVisibility(available, productPage, cartPage, checkoutPage, generalSettingsPage, dataProvider, helper);
      await assertMiniWidgetVisibility(available, categoryPage);
      await generalSettingsPage.goto();
      await generalSettingsPage.expectLoadingShowAndHide();
    }

    // Execution.
    await generalSettingsPage.goto();
    await generalSettingsPage.expectLoadingShowAndHide();

    // Test cancellation of the changes
    await generalSettingsPage.fillAllowedIPAddresses(notAllowedIPAddressesMatrix[0]);
    await generalSettingsPage.cancel();
    await generalSettingsPage.expectAllowedIPAddressesToBeEmpty();

    // Test with invalid IP addresses
    for (const ipAddresses of badIPAddressesMatrix) {
      await generalSettingsPage.fillAllowedIPAddresses(ipAddresses);
      await generalSettingsPage.save({ expectLoadingShowAndHide: false });
      await expect(page.getByText('This field must contain only valid IP addresses.'), 'The error message under "Allowed IP addresses" field should be visible').toBeVisible();
      await page.reload();
      await generalSettingsPage.expectLoadingShowAndHide();
    }

    // Test with valid IP addresses
    for (const ipAddresses of notAllowedIPAddressesMatrix) {
      console.log('Fill not allowed IP addresses:', ipAddresses);
      await fillAndAssert(ipAddresses, false);
    }

    for (const ipAddresses of allowedIPAddressesMatrix) {
      console.log('Fill allowed IP addresses:', ipAddresses);
      await fillAndAssert(ipAddresses, true);
    }
  });

  test('Change excluded categories', async ({ helper, dataProvider, backOffice, generalSettingsPage, productPage, checkoutPage, cartPage, categoryPage }) => {

    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '1' }] }); // Setup for physical products.

    const allowedCategoriesMatrix = [
      [],
      ['Watches']
      ['Tops', 'Default Category'],
    ];

    const notAllowedCategoriesMatrix = [
      ['Bags'],
      ['Bags', 'Video Download'],
    ];

    const fillAndAssert = async (categories, available) => {
      await generalSettingsPage.selectExcludedCategories(categories);
      await generalSettingsPage.save({ skipIfDisabled: true });
      await backOffice.logout();
      await assertWidgetAndPaymentMethodVisibility(available, productPage, cartPage, checkoutPage, generalSettingsPage, dataProvider, helper);
      await assertMiniWidgetVisibility(available, categoryPage);
      await generalSettingsPage.goto();
      await generalSettingsPage.expectLoadingShowAndHide();
    }

    // Execution
    await generalSettingsPage.goto();
    await generalSettingsPage.expectLoadingShowAndHide();

    // Test cancellation of the changes
    await generalSettingsPage.selectExcludedCategories(notAllowedCategoriesMatrix[0]);
    await generalSettingsPage.cancel();
    await generalSettingsPage.expectExcludedCategoriesToBeEmpty();

    // Test with categories assigned to the product
    for (const categories of notAllowedCategoriesMatrix) {
      await fillAndAssert(categories, false);
    }

    // Test with categories not assigned to the product
    for (const categories of allowedCategoriesMatrix) {
      await fillAndAssert(categories, true);
    }
  });

  test('Change excluded products', async ({ helper, dataProvider, backOffice, generalSettingsPage, productPage, checkoutPage, cartPage }) => {

    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '1' }] });

    const sku = '24-WB04';// The product SKU.
    const allowedValuesMatrix = [
      [],
      ['24-UG05'],
      ['24-UG05', '24-MG02']
    ];
    const notAllowedValuesMatrix = [
      [sku],
      [sku, ...allowedValuesMatrix[2]],
    ];

    const fillAndAssert = async (values, available) => {
      await generalSettingsPage.fillExcludedProducts(values);
      await generalSettingsPage.save({ skipIfDisabled: true });
      await backOffice.logout();
      await assertWidgetAndPaymentMethodVisibility(available, productPage, cartPage, checkoutPage, generalSettingsPage, dataProvider, helper);
      await generalSettingsPage.goto();
      await generalSettingsPage.expectLoadingShowAndHide();
    }

    // Execution
    await generalSettingsPage.goto();
    await generalSettingsPage.expectLoadingShowAndHide();

    // Test cancellation of the changes
    await generalSettingsPage.fillExcludedProducts(notAllowedValuesMatrix[0]);
    await generalSettingsPage.cancel();
    await generalSettingsPage.expectExcludedProductsToBeEmpty();

    // Test including the product
    for (const values of notAllowedValuesMatrix) {
      await fillAndAssert(values, false);
    }

    // Test excluding the product
    for (const values of allowedValuesMatrix) {
      await fillAndAssert(values, true);
    }
  });

  test('Change available countries', async ({ helper, dataProvider, page, generalSettingsPage, checkoutPage }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config }); // Setup for physical products.
    // Remove entry having code: 'FR'
    const countries = dataProvider.countriesMerchantRefs().filter(country => country.code !== 'FR');

    // Execution
    await generalSettingsPage.goto();
    await generalSettingsPage.expectLoadingShowAndHide();
    await generalSettingsPage.expectAvailableCountries({ countries });

    // Test cancellation of the changes
    await generalSettingsPage.fillAvailableCountries({ countries: [countries[0]] });
    await generalSettingsPage.cancel();
    await generalSettingsPage.expectAvailableCountries({ countries });

    // Test wrong values.
    await generalSettingsPage.fillAvailableCountries({
      countries: [{ ...countries[0], merchantRef: 'dummy_wrong' }]
    });
    await generalSettingsPage.save({ expectLoadingShowAndHide: false });
    // await generalSettingsPage.expectCountryInputErrorToBeVisible();

    // Test valid values.
    await generalSettingsPage.fillAvailableCountries({ countries });
    await generalSettingsPage.save({ expectLoadingShowAndHide: true });
    await page.reload();
    await generalSettingsPage.expectLoadingShowAndHide();
    await generalSettingsPage.expectAvailableCountries({ countries });
  });
});