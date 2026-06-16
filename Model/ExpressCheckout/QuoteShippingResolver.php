<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class QuoteShippingResolver
 *
 * Prepares a cart quote for SeQura Express Checkout using the customer's default
 * shipping/billing addresses and the cheapest applicable shipping rate.
 *
 * Two entry points with different contracts:
 *  - getResolvableShippingCountry() is a read-only availability probe for storefront
 *    blocks: it collects rates in memory to decide whether the button should render,
 *    but never persists the quote (a render must not mutate the shopper's live cart).
 *  - resolve() performs the actual mutate-and-save once, at solicit time, recomputing
 *    totals from a clean state so the shipping line cannot compound across calls.
 */
class QuoteShippingResolver
{
    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * QuoteShippingResolver constructor.
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Read-only availability probe: returns the country of the customer's default
     * shipping address when a shipping rate is available for the cart, or null when
     * the button should not render (no customer, no default shipping address, or no
     * applicable rate). Does not set a shipping/payment method, collect totals, or
     * save — the live cart is left untouched.
     *
     * @param Quote $quote Cart quote to probe.
     *
     * @return string|null ISO2 shipping country, or null when express is unavailable.
     */
    public function getResolvableShippingCountry(Quote $quote): ?string
    {
        try {
            $customer = $this->getQuoteCustomer($quote);
            if ($customer === null) {
                return null;
            }

            $defaultShippingAddress = $this->findDefaultShippingAddress($customer);
            if ($defaultShippingAddress === null) {
                return null;
            }

            if ($this->collectCheapestRate($quote, $defaultShippingAddress) === null) {
                return null;
            }

            return (string)$quote->getShippingAddress()->getCountryId() ?: null;
        } catch (Exception $e) {
            Logger::logError('Express Checkout shipping availability check failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return null;
        }
    }

    /**
     * Applies the customer's default shipping/billing addresses, the cheapest
     * available shipping rate and the SeQura payment method to the quote, then
     * recomputes totals from a clean state and persists the quote.
     *
     * @param Quote $quote Cart quote to mutate and save.
     *
     * @return bool True if the quote was prepared and saved; false if the customer
     *              has no default shipping address or no shipping rate is available.
     */
    public function resolve(Quote $quote): bool
    {
        try {
            $customer = $this->getQuoteCustomer($quote);
            if ($customer === null) {
                return false;
            }

            $defaultShippingAddress = $this->findDefaultShippingAddress($customer);
            if ($defaultShippingAddress === null) {
                return false;
            }

            $cheapest = $this->collectCheapestRate($quote, $defaultShippingAddress);
            if ($cheapest === null) {
                return false;
            }

            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShippingMethod($cheapest->getCode());
            $shippingAddress->setCollectShippingRates(true);

            $quote->getBillingAddress()->importCustomerAddressData(
                $this->findDefaultBillingAddress($customer) ?? $defaultShippingAddress
            );

            $quote->getPayment()->setMethod(ConfigProvider::CODE);
            $quote->setData('totals_collected_flag', false);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);

            return true;
        } catch (Exception $e) {
            Logger::logError('Express Checkout shipping resolution failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return false;
        }
    }

    /**
     * Loads the quote's customer, or null when the quote belongs to a guest.
     *
     * @param Quote $quote
     *
     * @return CustomerInterface|null
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getQuoteCustomer(Quote $quote): ?CustomerInterface
    {
        $customerId = (int)$quote->getCustomerId();
        if ($customerId <= 0) {
            return null;
        }

        return $this->customerRepository->getById($customerId);
    }

    /**
     * Returns the customer's default shipping address, or null if none is set.
     *
     * @param CustomerInterface $customer
     *
     * @return AddressInterface|null
     */
    private function findDefaultShippingAddress(CustomerInterface $customer): ?AddressInterface
    {
        $defaultShippingId = $customer->getDefaultShipping();
        if (!$defaultShippingId) {
            return null;
        }

        return $this->findAddressById($customer->getAddresses() ?? [], (int)$defaultShippingId);
    }

    /**
     * Returns the customer's default billing address, or null if none is set.
     *
     * @param CustomerInterface $customer
     *
     * @return AddressInterface|null
     */
    private function findDefaultBillingAddress(CustomerInterface $customer): ?AddressInterface
    {
        $defaultBillingId = $customer->getDefaultBilling();
        if (!$defaultBillingId) {
            return null;
        }

        return $this->findAddressById($customer->getAddresses() ?? [], (int)$defaultBillingId);
    }

    /**
     * Imports the given address onto the quote's shipping address and (re)collects
     * shipping rates from a clean state, returning the cheapest available rate or null.
     * Does not persist the quote.
     *
     * @param Quote $quote
     * @param AddressInterface $address
     *
     * @return Rate|null
     */
    private function collectCheapestRate(Quote $quote, AddressInterface $address): ?Rate
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->importCustomerAddressData($address);
        $shippingAddress->setShippingMethod('');
        $shippingAddress->setCollectShippingRates(true);

        $quote->setData('totals_collected_flag', false);
        $quote->collectTotals();

        return $this->pickCheapestRate($shippingAddress->getAllShippingRates());
    }

    /**
     * Returns the address matching the given ID, or null if not found.
     *
     * @param AddressInterface[] $addresses
     * @param int $addressId
     *
     * @return AddressInterface|null
     */
    private function findAddressById(array $addresses, int $addressId): ?AddressInterface
    {
        foreach ($addresses as $address) {
            if ((int)$address->getId() === $addressId) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Returns the rate with the lowest price, or null if the list is empty or
     * contains no usable rate (errors are skipped).
     *
     * @param Rate[] $rates
     *
     * @return Rate|null
     */
    private function pickCheapestRate(array $rates): ?Rate
    {
        $cheapest = null;
        foreach ($rates as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }
            if ($cheapest === null || (float)$rate->getPrice() < (float)$cheapest->getPrice()) {
                $cheapest = $rate;
            }
        }

        return $cheapest;
    }
}
