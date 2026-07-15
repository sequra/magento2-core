import { test } from '../fixtures/test';
import { DataProvider } from 'playwright-fixture-for-plugins';

const byLocation = (dataProvider) =>
  Object.fromEntries(dataProvider.bannerOptions().map(b => [b.displayLocation, b]));

const seedBanners = async (helper, enabled) => {
  const { dummy_config, clear_config, clear_front_end_cache } = helper.webhooks;
  await helper.executeWebhook({ webhook: clear_config });
  await helper.executeWebhook({ webhook: dummy_config, args: [{ name: 'banners', value: enabled ? '1' : '0' }] });
  await helper.executeWebhook({ webhook: clear_front_end_cache });
};

test.describe('Storefront banners', () => {

  test('Display banners on every location for the resolved country', async ({ helper, dataProvider, homePage, categoryPage, productPage, cartPage }) => {
    await seedBanners(helper, true);

    const banners = byLocation(dataProvider);

    // Home page: image wrapped in a link.
    await homePage.goto();
    await homePage.expectBannerToBeVisible(banners[DataProvider.BANNER_ON_HOME]);

    // Product listing page: image wrapped in a link.
    await categoryPage.goto({ slug: 'gear/bags' });
    await categoryPage.expectBannerToBeVisible(banners[DataProvider.BANNER_ON_LISTING]);

    // Product page: bare image, no link.
    await productPage.goto({ slug: 'fusion-backpack' });
    await productPage.expectBannerToBeVisible(banners[DataProvider.BANNER_ON_PRODUCT]);

    // Cart page: bare image, no link. Needs a non-empty cart.
    await productPage.addToCart({ slug: 'fusion-backpack', quantity: 1 });
    await cartPage.goto();
    await cartPage.expectBannerToBeVisible(banners[DataProvider.BANNER_ON_CART]);
  });

  test('Do not display banners when none are configured', async ({ helper, homePage, categoryPage, productPage, cartPage }) => {
    await seedBanners(helper, false);

    await homePage.goto();
    await homePage.expectBannerNotToBeVisible();

    await categoryPage.goto({ slug: 'gear/bags' });
    await categoryPage.expectBannerNotToBeVisible();

    await productPage.goto({ slug: 'fusion-backpack' });
    await productPage.expectBannerNotToBeVisible();

    await productPage.addToCart({ slug: 'fusion-backpack', quantity: 1 });
    await cartPage.goto();
    await cartPage.expectBannerNotToBeVisible();
  });
});
