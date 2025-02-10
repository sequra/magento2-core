// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests-e2e/specs',
  timeout: 5 * 60 * 1000, // 5 minutes
  /* Run tests in files in parallel */
  fullyParallel: false,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: 0,
  /* Opt out of parallel tests on CI. */
  workers: 1,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: process.env.CI ? 'dot' : 'list',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: process.env.PUBLIC_URL,
    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'checkout-product',
      use: { ...devices['Desktop Chrome'] },
      testMatch: '001-checkout-product.spec.js',
    },
    {
      name: 'configuration-onboarding',
      use: { ...devices['Desktop Chrome'] },
      testMatch: '003-configuration-onboarding.spec.js',
    },
    {
      name: 'configuration-payment-methods',
      use: { ...devices['Desktop Chrome'] },
      testMatch: '004-configuration-payment-methods.spec.js',
    },
    {
      name: 'configuration-general',
      use: { ...devices['Desktop Chrome'] },
      testMatch: '006-configuration-general.spec.js',
    },
    {
      name: 'configuration-connection',
      use: { ...devices['Desktop Chrome'] },
      testMatch: '007-configuration-connection.spec.js',
    },
  ],
});

