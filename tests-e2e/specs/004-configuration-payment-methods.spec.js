import { test } from '../fixtures/test';

test.describe('Configuration', () => {
  test('Payment methods', async ({ helper, paymentMethodsSettingsPage }) => {
    // Setup
    const { dummy_config } = helper.webhooks;
    const countries = [
      { name: 'Spain', paymentMethods: ['Paga Después', 'Divide tu pago en 3', 'Paga Fraccionado'] },
      { name: 'France', paymentMethods: ['Payez en plusieurs fois'] },
      { name: 'Italy', paymentMethods: ['Pagamento a rate'] },
      { name: 'Portugal', paymentMethods: ['Pagamento Fracionado'] }
    ];
    // Execution
    await helper.executeWebhook({ webhook: dummy_config });
    await paymentMethodsSettingsPage.goto();
    await paymentMethodsSettingsPage.expectAvailablePaymentMethodsAreVisible(countries);
  });
});