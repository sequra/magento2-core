import { test, expect } from '../fixtures/test';

test.describe('Widget settings', () => {

  test('Change settings', async ({ page, helper, widgetSettingsPage, dataProvider }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '0' }] }); // Setup with widgets disabled.

    const defaultSettings = dataProvider.defaultWidgetOptions();
    const widgetOptions = dataProvider.widgetOptions();
    const newSettings = {
      ...widgetOptions,
      product: {
        ...widgetOptions.product,
        priceSel: '.product-info-price [data-price-type="finalPrice"] .price',
        locationSel: '.product.info',
        customLocations: [
          {
            ...widgetOptions.product.customLocations[0],
            locationSel: '#product-addtocart-button'
          }
        ]
      },
      cart: {
        ...widgetOptions.cart,
        priceSel: '.cart-totals .grand.totals .price',
        locationSel: '.cart-totals',
      },
      productListing: {
        ...widgetOptions.productListing,
        display: false, // Disable product listing widgets.
        useSelectors: false, // Disable selectors for product listing.
      }
    }
    const onlyProductSettings = {
      ...defaultSettings,
      product: {
        ...newSettings.product,
        customLocations: []
      }
    }
    const onlyCartSettings = {
      ...defaultSettings,
      cart: newSettings.cart
    }

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
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, customLocations: [{ ...newSettings.product.customLocations[0], locationSel: invalidSelector }] } },
      { ...onlyProductSettings, product: { ...onlyProductSettings.product, customLocations: [{ ...newSettings.product.customLocations[0], widgetConfig: invalidJSON }] } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, priceSel: emptyStr } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, priceSel: invalidSelector } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, locationSel: emptyStr } },
      { ...onlyCartSettings, cart: { ...onlyCartSettings.cart, locationSel: invalidSelector } },
    ]

    // Execution.
    await widgetSettingsPage.goto();
    await widgetSettingsPage.expectLoadingShowAndHide();

    // Test cancellation of the changes.
    await widgetSettingsPage.fillForm(newSettings);
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
    await widgetSettingsPage.fillForm(newSettings);
    await widgetSettingsPage.save();
    await widgetSettingsPage.expectConfigurationMatches(newSettings);
    // Test if changes persist after reload.
    await page.reload();
    await widgetSettingsPage.expectLoadingShowAndHide();
    await widgetSettingsPage.expectConfigurationMatches(newSettings);
  });

  test('Show widget on product page', async ({ page, helper, widgetSettingsPage, dataProvider, productPage }) => {
    // Setup
    const { dummy_config, clear_config } = helper.webhooks;
    await helper.executeWebhook({ webhook: clear_config }); // Clear the configuration.
    await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'widgets', value: '0' }] }); // Setup with widgets disabled.

    const widgetOptions = dataProvider.widgetOptions();
    const newSettings = {
      ...widgetOptions,
      product: {
        ...widgetOptions.product,
        priceSel: '.product-info-price [data-price-type="finalPrice"] .price',
        locationSel: '.product.info',
        customLocations: [
          {
            ...widgetOptions.product.customLocations[0],
            locationSel: '#product-addtocart-button'
          }
        ]
      },
      cart: {
        ...widgetOptions.cart,
        display: false, // Disable cart widgets.
      },
      productListing: {
        ...widgetOptions.productListing,
        display: false, // Disable product listing widgets.
        useSelectors: false, // Disable selectors for product listing.
      }
    }

    const pp3Opt = {
      locationSel: newSettings.product.locationSel,
      widgetConfig: newSettings.widgetConfig,
      product: 'pp3',
      amount: 5900,
      registrationAmount: null,
      campaign: null
    }

    const sp1Opt = {
      locationSel: newSettings.product.locationSel,
      widgetConfig: newSettings.widgetConfig,
      product: 'sp1',
      amount: 5900,
      registrationAmount: null,
      campaign: 'permanente'
    }

    const i1Opt = {
      locationSel: newSettings.product.customLocations[0].locationSel || newSettings.product.locationSel,
      widgetConfig: newSettings.product.customLocations[0].widgetConfig || newSettings.product.widgetConfig,
      product: 'i1',
      amount: 5900,
      registrationAmount: null,
      campaign: null
    }

    // Execution
    await widgetSettingsPage.goto();
    await widgetSettingsPage.expectLoadingShowAndHide();
    await widgetSettingsPage.fillForm(newSettings);
    await widgetSettingsPage.save();

    await productPage.goto({ slug: 'fusion-backpack' });

    await productPage.expectWidgetToBeVisible(pp3Opt);
    await productPage.expectWidgetToBeVisible(sp1Opt);
    await productPage.expectWidgetToBeVisible(i1Opt);
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