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

    const slugOpt = { slug: 'fusion-backpack' };
    await productPage.goto(slugOpt);

    await productPage.expectWidgetToBeVisible(dataProvider.pp3FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetToBeVisible(dataProvider.sp1FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetToBeVisible(dataProvider.i1FrontEndWidgetOptions(slugOpt));
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

    const slugOpt = { slug: 'fusion-backpack' };
    await productPage.goto(slugOpt);

    await productPage.expectWidgetToBeVisible(dataProvider.pp3FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetToBeVisible(dataProvider.sp1FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetNotToBeVisible(dataProvider.i1FrontEndWidgetOptions(slugOpt));
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

    const slugOpt = { slug: 'fusion-backpack' };
    await productPage.goto(slugOpt);

    await productPage.expectWidgetToBeVisible(dataProvider.pp3FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetToBeVisible(dataProvider.sp1FrontEndWidgetOptions(slugOpt));
    await productPage.expectWidgetNotToBeVisible(dataProvider.i1FrontEndWidgetOptions(slugOpt));
  });
});