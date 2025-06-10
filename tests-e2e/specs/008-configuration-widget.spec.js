import { test, expect } from '../fixtures/test';

test.describe('Widget settings', () => {

  test('Change settings', async ({ page, helper, widgetSettingsPage, dataProvider }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '0' }] }); // Setup with widgets disabled.

    const defaultSettings = dataProvider.defaultWidgetOptions();
    const widgetOptions = dataProvider.widgetOptions();
    const onlyProductSettings = dataProvider.onlyProductWidgetOptions();
    const onlyCartSettings = dataProvider.onlyCartWidgetOptions();

    const emptyStr = "";
    const invalidSelector = "!.summary .price>.amount,.summary .price ins .amount";
    const invalidJSON = "{";

    const invalidSettingsList = [
      { ...defaultSettings, widgetConfig: emptyStr },
      { ...defaultSettings, widgetConfig: invalidJSON },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, priceSel: emptyStr } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, priceSel: invalidSelector } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, altPriceSel: invalidSelector } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, altPriceTriggerSel: invalidSelector } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, locationSel: emptyStr } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, locationSel: invalidSelector } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, customLocations: [{ ...widgetOptions.product.customLocations[0], locationSel: invalidSelector }] } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, customLocations: [{ ...widgetOptions.product.customLocations[0], widgetConfig: invalidJSON }] } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, priceSel: emptyStr } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, priceSel: invalidSelector } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, locationSel: emptyStr } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, locationSel: invalidSelector } },
    ]

    // Execution.
    await widgetSettingsPage.goto();
    await widgetSettingsPage.expectLoadingShowAndHide();

    // Test cancellation of the changes.
    await widgetSettingsPage.fillForm(widgetOptions);
    await widgetSettingsPage.cancel();
    await widgetSettingsPage.expectConfigurationMatches(defaultSettings);

    // Test invalid values.
    for (const invalid of invalidSettingsList) {
      await widgetSettingsPage.fillForm(invalid);
      await widgetSettingsPage.expectErrorMessageToBeVisible();
      await page.reload();
      await widgetSettingsPage.expectLoadingShowAndHide();
    }

    // Test valid values.
    await widgetSettingsPage.fillForm(widgetOptions);
    await widgetSettingsPage.save();
    await widgetSettingsPage.expectConfigurationMatches(widgetOptions);
    // Test if changes persist after reload.
    await page.reload();
    await widgetSettingsPage.expectLoadingShowAndHide();
    await widgetSettingsPage.expectConfigurationMatches(widgetOptions);
  });

  test('Show widget on product page', async ({ helper, widgetSettingsPage, dataProvider, productPage }) => {
    // Setup
    const { dummy_config, clear_config, clear_front_end_cache } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '0' }] }); // Setup with widgets disabled.

    // Execution
    await widgetSettingsPage.goto();
    await widgetSettingsPage.expectLoadingShowAndHide();
    await widgetSettingsPage.fillForm(dataProvider.widgetOptions());
    await widgetSettingsPage.save();

    await helper.executeWebhook({ webhook: clear_front_end_cache }); // Clear the page cache.

    await productPage.goto({ slug: 'fusion-backpack' });

    await productPage.expectWidgetToBeVisible(dataProvider.pp3FrontEndWidgetOptions());
    await productPage.expectWidgetToBeVisible(dataProvider.sp1FrontEndWidgetOptions());
    await productPage.expectWidgetToBeVisible(dataProvider.i1FrontEndWidgetOptions());
  });

  test('Do not display the widget on the product page when the selector is invalid', async ({ helper, widgetSettingsPage, dataProvider, productPage }) => {
    // Setup
    const { dummy_config, clear_config, clear_front_end_cache } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '0' }] }); // Setup with widgets disabled.

    const widgetOptions = dataProvider.onlyProductWidgetOptions();
    widgetOptions.product.customLocations[0].locationSel = '#product-addtocart-button-bad-selector';

    // Execution
    await widgetSettingsPage.goto();
    await widgetSettingsPage.expectLoadingShowAndHide();
    await widgetSettingsPage.fillForm(widgetOptions);
    await widgetSettingsPage.save();

    await helper.executeWebhook({ webhook: clear_front_end_cache }); // Clear the page cache.

    await productPage.goto({ slug: 'fusion-backpack' });

    await productPage.expectWidgetToBeVisible(dataProvider.pp3FrontEndWidgetOptions());
    await productPage.expectWidgetToBeVisible(dataProvider.sp1FrontEndWidgetOptions());
    await productPage.expectWidgetNotToBeVisible(dataProvider.i1FrontEndWidgetOptions());
  });

  test('Do not display the widget on the product page when custom location is disabled', async ({ helper, widgetSettingsPage, dataProvider, productPage }) => {
    // Setup
    const { dummy_config, clear_config, clear_front_end_cache } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '0' }] }); // Setup with widgets disabled.

    const widgetOptions = dataProvider.onlyProductWidgetOptions();
    widgetOptions.product.customLocations[0].display = false;

    // Execution
    await widgetSettingsPage.goto();
    await widgetSettingsPage.expectLoadingShowAndHide();
    await widgetSettingsPage.fillForm(widgetOptions);
    await widgetSettingsPage.save();

    await helper.executeWebhook({ webhook: clear_front_end_cache }); // Clear the page cache.

    await productPage.goto({ slug: 'fusion-backpack' });

    await productPage.expectWidgetToBeVisible(dataProvider.pp3FrontEndWidgetOptions());
    await productPage.expectWidgetToBeVisible(dataProvider.sp1FrontEndWidgetOptions());
    await productPage.expectWidgetNotToBeVisible(dataProvider.i1FrontEndWidgetOptions());
  });

  // test('Don\'t show widget for banned product', async ({ productPage, generalSettingsPage, widgetSettingsPage, wpAdmin, request }) => {
  //   const helper = new SeQuraHelper(request, expect);
  //   await helper.executeWebhook({ webhook: helper.webhooks.SET_THEME, args: [{ name: 'theme', value: 'twentytwentyfour' }] });
  //   await helper.executeWebhook({ webhook: helper.webhooks.SET_THEME, args: [{ name: 'theme', value: 'twentytwentyfour' }] });
  //   const productId = 13;

  //   await widgetSettingsPage.goto();
  //   await widgetSettingsPage.expectLoadingShowAndHide();
  //   await widgetSettingsPage.fill({
  //     ...widgetSettingsPage.getDefaultSettings(),
  //     enabled: true,
  //     priceSel: ".wc-block-components-product-price>.amount,.wc-block-components-product-price ins .amount",
  //     locationSel: ".wc-block-components-product-price"
  //   });
  //   await widgetSettingsPage.save({ expectLoadingShowAndHide: true, skipIfDisabled: true });

  //   const expectWidgetNotToBeVisible = async () => {
  //     await productPage.goto({ slug: 'sunglasses' });
  //     await productPage.expectWidgetsNotToBeVisible();
  //   }

  //   // Test by including the product SKU in "Excluded products" list.
  //   await generalSettingsPage.goto();
  //   await generalSettingsPage.expectLoadingShowAndHide();
  //   await generalSettingsPage.fillExcludedProducts(['woo-sunglasses']);
  //   await generalSettingsPage.save({});
  //   await expectWidgetNotToBeVisible();

  //   // Test by including the product ID in "Excluded products" list.
  //   await generalSettingsPage.goto();
  //   await generalSettingsPage.expectLoadingShowAndHide();
  //   await generalSettingsPage.fillExcludedProducts([`${productId}`]);
  //   await generalSettingsPage.save({});
  //   await expectWidgetNotToBeVisible();

  //   await generalSettingsPage.goto();
  //   await generalSettingsPage.expectLoadingShowAndHide();
  //   await generalSettingsPage.fillExcludedProducts([]); // Clear the excluded products.

  //   // Test by including the product category in "Excluded category" list.
  //   await generalSettingsPage.selectExcludedCategories(['Accessories']);
  //   await generalSettingsPage.save({});
  //   await expectWidgetNotToBeVisible();

  //   await generalSettingsPage.goto();
  //   await generalSettingsPage.expectLoadingShowAndHide();
  //   await generalSettingsPage.selectExcludedCategories([]); // Clear the excluded categories.
  //   await generalSettingsPage.save({});

  //   // Test by checking "Do not offer seQura for this product" on the product back-office page.
  //   await wpAdmin.gotoProduct({ productId });
  //   await wpAdmin.setProductAsBanned();
  //   await expectWidgetNotToBeVisible();

  //   await wpAdmin.gotoProduct({ productId });
  //   await wpAdmin.setProductAsBanned(false); // Restore previous state.
  // });
});