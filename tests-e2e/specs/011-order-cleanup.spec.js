import { test, expect } from '../fixtures/test';

const DAY = 86400;
const RUNNER_TIMEOUT = 60000;

const now = () => Math.floor(Date.now() / 1000);

const hourInShopTimezone = timestamp => Number(
  new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Madrid', hour: '2-digit', hourCycle: 'h23' })
    .format(new Date(timestamp * 1000))
);

const wakeAndWaitForTicks = async helper => {
  const wokenAt = now();
  await helper.wakeTaskRunner();
  await expect.poll(
    async () => (await helper.getOrderCleanupState()).runner_alive_since,
    { message: 'The task runner keeps ticking after being woken', timeout: RUNNER_TIMEOUT }
  ).toBeGreaterThan(wokenAt + 5);
};

test.describe('Order cleanup', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ helper }) => {
    await helper.executeWebhook({ webhook: helper.webhooks.clear_config });
    await helper.executeWebhook({ webhook: helper.webhooks.dummy_config });
  });

  test('schedules the first cleanup for a night after 02:00 in the shop timezone and keeps it on later ticks', async ({ helper }) => {
    const { next_run: nextRun } = await helper.getOrderCleanupState();

    expect(nextRun, 'Setup scheduled a future run').toBeGreaterThan(now());
    expect(nextRun, 'The first run is no later than the coming nights').toBeLessThanOrEqual(now() + 3 * DAY);
    expect(hourInShopTimezone(nextRun), 'The first run is between 02:00 and 02:59 in the shop timezone').toBe(2);

    await wakeAndWaitForTicks(helper);
    expect((await helper.getOrderCleanupState()).next_run, 'A tick keeps the existing schedule').toBe(nextRun);
  });

  test('deletes only the stale solicitations once due and enqueues a single cleanup per due run', async ({ helper, dataProvider, productPage, checkoutPage }) => {
    test.slow();
    const shopper = dataProvider.shopper();

    await productPage.addToCart({ slug: 'push-it-messenger-bag', quantity: 1 });
    await checkoutPage.goto();
    await checkoutPage.fillForm(shopper);
    await checkoutPage.placeOrder({ ...shopper, product: 'i1' });
    await checkoutPage.waitForOrderSuccess();
    const orderNumber = await checkoutPage.getOrderNumber();

    const seeded = await helper.seedOrderCleanupSolicitations(orderNumber);
    const references = Object.values(seeded);
    const {
      next_run: scheduledRun,
      cleanup_task_statuses: earlierCleanups,
      existing_references: seededReferences,
    } = await helper.getOrderCleanupState(references);
    expect([...seededReferences].sort(), 'Every seeded solicitation exists before the cleanup').toEqual([...references].sort());
    const dueRun = now() - 60;

    try {
      await helper.setOrderCleanupNextRun(dueRun);
      await helper.wakeTaskRunner();
      await expect.poll(
        async () => (await helper.getOrderCleanupState()).cleanup_task_statuses,
        { message: 'The due cleanup is enqueued once and completes', timeout: RUNNER_TIMEOUT }
      ).toEqual([...earlierCleanups, 'completed']);

      const afterCleanup = await helper.getOrderCleanupState(references);
      expect(
        [...afterCleanup.existing_references].sort(),
        'Only the stale solicitation is deleted; fresh solicitations and shop orders are kept'
      ).toEqual([seeded.fresh, seeded.linked, seeded.shop_order].sort());
      expect(afterCleanup.next_run, 'The next run moves a day ahead of the due one').toBe(dueRun + DAY);

      await wakeAndWaitForTicks(helper);
      const afterNextTicks = await helper.getOrderCleanupState();
      expect(afterNextTicks.cleanup_task_statuses, 'Waking the runner again enqueues no other cleanup').toEqual([...earlierCleanups, 'completed']);
      expect(afterNextTicks.next_run, 'Waking the runner again keeps the next run').toBe(dueRun + DAY);
    } finally {
      if (scheduledRun !== null) {
        await helper.setOrderCleanupNextRun(scheduledRun);
      }
    }
  });
});
