<?php
declare(strict_types=1);

namespace Sequra\Core\Test\Integration\Model\Api\Builders;

use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilder;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class CreateOrderRequestBuilderTest extends TestCase
{
    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var CreateOrderRequestBuilderFactory
     */
    private $createOrderRequestBuilderFactory;

    /**
     * @var \Sequra\Core\Services\Bootstrap
     */
    private $bootstrap;
    
    /**
     * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
     * @magentoDataFixture Sequra_Core::Test/_files/sequra_configuration.php
     */
    public function testBuildCreateOrderRequestForOrderWithShippingMethod()
    {
        // quote
        $this->quote->load('test01', 'reserved_order_id');
        // setShippingAddress in spain
        $this->quote->getShippingAddress()->setCountryId('ES');
        // setShippingMethod
        $this->quote->getShippingAddress()
            ->setShippingMethod('flatrate_flatrate')
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->save();
        /** @var CreateOrderRequestBuilder $builder */
        $builder = $this->createOrderRequestBuilderFactory->create([
            'cartId' => $this->quote->getId(),
            'storeId' => (string)$this->quote->getStore()->getId(),
        ]);
        $order = $builder->build()->toArray();
        self::assertEquals($order['delivery_method']['name'], 'flatrate_flatrate');
        self::assertEquals($order['cart']['order_total_with_tax'], 1000);
    }

    /**
     * The Express Checkout CartSummary page writes an edited email onto the quote. The solicitation
     * must carry that email, not the shopper's (unchanged) account email.
     *
     * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
     * @magentoDataFixture Sequra_Core::Test/_files/sequra_configuration.php
     *
     * @return void
     */
    public function testQuoteEmailWinsOverTheAccountEmail()
    {
        $this->quote->load('test01', 'reserved_order_id');
        $this->quote->getShippingAddress()->setCountryId('ES');
        $this->quote->setCustomerEmail('edited@example.com')->save();

        /** @var CreateOrderRequestBuilder $builder */
        $builder = $this->createOrderRequestBuilderFactory->create([
            'cartId' => $this->quote->getId(),
            'storeId' => (string)$this->quote->getStore()->getId(),
        ]);
        $order = $builder->build()->toArray();

        self::assertNotEquals($this->quote->getCustomer()->getEmail(), 'edited@example.com');
        self::assertEquals('edited@example.com', $order['customer']['email']);
    }

    /**
     * With no email on the quote the existing fallbacks still apply: account email, then the
     * billing address, then the shipping address.
     *
     * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
     * @magentoDataFixture Sequra_Core::Test/_files/sequra_configuration.php
     *
     * @return void
     */
    public function testFallsBackToTheAddressEmailWhenTheQuoteAndAccountCarryNone()
    {
        $this->quote->load('test01', 'reserved_order_id');
        $this->quote->getShippingAddress()->setCountryId('ES');
        $this->quote->setCustomerEmail(null)
            ->setCustomerIsGuest(true)
            ->setCustomerId(null);
        $this->quote->getBillingAddress()->setEmail('billing@example.com');
        $this->quote->save();

        /** @var CreateOrderRequestBuilder $builder */
        $builder = $this->createOrderRequestBuilderFactory->create([
            'cartId' => $this->quote->getId(),
            'storeId' => (string)$this->quote->getStore()->getId(),
        ]);
        $order = $builder->build()->toArray();

        self::assertEquals('billing@example.com', $order['customer']['email']);
    }

    public function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $_SERVER['REMOTE_ADDR'] = "255.255.255.255";
        $_SERVER['HTTP_USER_AGENT'] = "Integration tests";
        $this->quote = $objectManager->create(Quote::class);
        $this->createOrderRequestBuilderFactory = $objectManager->create(CreateOrderRequestBuilderFactory::class);
        $this->bootstrap =$objectManager->create(\Sequra\Core\Services\Bootstrap::class);
        $this->bootstrap->initInstance();
    }
}
