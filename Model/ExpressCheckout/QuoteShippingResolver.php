<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
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
 *    blocks: it returns the customer's default-shipping country without collecting rates
 *    or touching the cart (it runs on the uncached cart section, so forcing rate collection
 *    would hit every carrier on each request).
 *  - resolve() performs the actual mutate-and-save once, at solicit time, recomputing
 *    totals from a clean state so the shipping line cannot compound across calls.
 *  - applyChange() is the re-solicit counterpart: it writes a shopper-supplied change from the
 *    CartSummary form (address, carrier, email) instead of the customer's defaults, then
 *    re-collects and saves the same way.
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
     * Read-only availability probe: returns the ISO2 country of the customer's default shipping
     * address, or null when the button should not render (guest, or no default shipping address).
     *
     * Deliberately does NOT collect shipping rates or touch the quote. This runs from the cart
     * customer-data section, which Magento serves uncached on nearly every navigation and cart
     * change, so forcing collectTotals()/getAllShippingRates() here would invoke every configured
     * shipping carrier — including live-rate carriers (UPS/FedEx/DHL) — on each section load.
     * Whether a usable rate exists for the destination is decided by resolve() at solicit time,
     * which is authoritative and fails closed (HTTP 422 → inline "not available" message).
     *
     * @param Quote $quote Cart quote whose customer is probed (not mutated).
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

            $country = (string)$defaultShippingAddress->getCountryId();

            return $country !== '' ? $country : null;
        } catch (Exception $e) {
            Logger::logError('Express Checkout shipping availability check failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return null;
        }
    }

    /**
     * Applies the customer's default shipping/billing addresses, a shipping rate and the SeQura
     * payment method to the quote, then recomputes totals from a clean state and persists the
     * quote. The rate already selected by the shopper (e.g. on the cart/checkout) is kept when it
     * is still available; otherwise the cheapest rate is used.
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

            $shippingAddress = $quote->getShippingAddress();
            // Capture the shopper's choice before re-collecting rates (which clears the method), so
            // express keeps a higher-cost method they explicitly selected instead of silently
            // downgrading to the cheapest — which would make the solicited amount diverge from the
            // placed order and fail payment.
            $preselectedMethod = (string)$shippingAddress->getShippingMethod();

            $rate = $this->selectRate(
                $this->collectRates($quote, $defaultShippingAddress),
                $preselectedMethod
            );
            if ($rate === null) {
                return false;
            }

            $shippingAddress->setShippingMethod($rate->getCode());
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
     * Applies a shopper change coming from the CartSummary form — any combination of shipping
     * address, chosen carrier and email — then re-collects rates from a clean state and saves,
     * so the follow-up solicit sees the new figures.
     *
     * Unlike {@see resolve} this never re-imports the customer's default address: that is the
     * whole point of the change. The billing address is left as the initial solicit imported it —
     * the CartSummary page edits delivery only.
     *
     * ponytail: the region is only cleared on a country change, never re-derived from the new
     * postcode; countries whose carriers key off the region need a region lookup here.
     *
     * @param Quote $quote Quote to mutate and save.
     * @param mixed[] $change Validated change: address, shippingMethodReference, email.
     *
     * @return bool True when applied; false when the resulting address has no usable rate.
     *
     * @throws WebapiException HTTP 400 when the requested carrier is not one of the rates the
     *                         quote actually offers.
     * @throws LocalizedException If the quote cannot be saved.
     */
    public function applyChange(Quote $quote, array $change): bool
    {
        $shippingAddress = $quote->getShippingAddress();

        if (isset($change['address']) && is_array($change['address'])) {
            $this->applyAddress($shippingAddress, $change['address']);
        }

        if (isset($change['email']) && is_string($change['email'])) {
            $quote->setCustomerEmail($change['email']);
            $shippingAddress->setEmail($change['email']);
            $quote->getBillingAddress()->setEmail($change['email']);
        }

        $preselectedMethod = (string)$shippingAddress->getShippingMethod();
        $requestedMethod = isset($change['shippingMethodReference']) && is_string($change['shippingMethodReference'])
            ? $change['shippingMethodReference']
            : '';

        $rates = $this->recollectRates($quote);

        if ($requestedMethod !== '') {
            $rate = $this->findRate($rates, $requestedMethod);
            if ($rate === null) {
                // A reference the quote does not offer: stale after an address change, or forged.
                // Never written to the quote as-is — the shopper's carrier choice is only ever one
                // of the collected rates.
                throw new WebapiException(
                    __('Invalid Express Checkout request.'),
                    0,
                    WebapiException::HTTP_BAD_REQUEST
                );
            }
        } else {
            $rate = $this->selectRate($rates, $preselectedMethod);
        }

        if ($rate === null) {
            return false;
        }

        $shippingAddress->setShippingMethod($rate->getCode());
        $shippingAddress->setCollectShippingRates(true);
        $quote->setData('totals_collected_flag', false);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        return true;
    }

    /**
     * Writes the shopper-supplied address fields onto the quote's shipping address.
     *
     * @param Address $shippingAddress Quote shipping address to overwrite.
     * @param array<string, string> $address Validated address fields.
     *
     * @return void
     */
    private function applyAddress(Address $shippingAddress, array $address): void
    {
        $street = [$address['addressLine1']];
        if (isset($address['addressLine2'])) {
            $street[] = $address['addressLine2'];
        }

        $shippingAddress->setFirstname($address['givenName']);
        $shippingAddress->setLastname($address['surnames']);
        $shippingAddress->setStreet($street);
        $shippingAddress->setPostcode($address['postalCode']);
        $shippingAddress->setCity($address['city']);

        $country = $address['countryCode'] ?? '';
        if ($country !== '' && $country !== (string)$shippingAddress->getCountryId()) {
            $shippingAddress->setCountryId($country);
            // The stored region belongs to the previous country; carrying it over would quote
            // rates (and place the order) against a region that does not exist there. Magento
            // reads 0 / '' as "no region".
            $shippingAddress->setRegionId(0);
            $shippingAddress->setRegion('');
        }

        // The quote address no longer mirrors the customer address book entry it was imported
        // from, so drop the link rather than leave it pointing at different data.
        $shippingAddress->setCustomerAddressId(null);
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
     * Imports the given address onto the quote's shipping address and (re)collects shipping rates
     * from a clean state, returning every available rate. Does not persist the quote.
     *
     * @param Quote $quote
     * @param AddressInterface $address
     *
     * @return Rate[]
     */
    private function collectRates(Quote $quote, AddressInterface $address): array
    {
        $quote->getShippingAddress()->importCustomerAddressData($address);

        return $this->recollectRates($quote);
    }

    /**
     * (Re)collects the quote's shipping rates from a clean state — the selected method is cleared
     * so every carrier is re-quoted for whatever address is currently on the quote — and returns
     * them. Does not persist the quote.
     *
     * @param Quote $quote
     *
     * @return Rate[]
     */
    private function recollectRates(Quote $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setShippingMethod('');
        $shippingAddress->setCollectShippingRates(true);

        $quote->setData('totals_collected_flag', false);
        $quote->collectTotals();

        return $shippingAddress->getAllShippingRates();
    }

    /**
     * Returns the rate matching the already-selected method when it is still available, otherwise
     * the cheapest available rate (or null when there is none).
     *
     * @param Rate[] $rates
     * @param string $selectedCode Shipping method code already on the quote, if any.
     *
     * @return Rate|null
     */
    private function selectRate(array $rates, string $selectedCode): ?Rate
    {
        if ($selectedCode !== '') {
            $rate = $this->findRate($rates, $selectedCode);
            if ($rate !== null) {
                return $rate;
            }
        }

        return $this->pickCheapestRate($rates);
    }

    /**
     * Returns the usable rate carrying the given code, or null when the quote does not offer it.
     *
     * @param Rate[] $rates
     * @param string $code Shipping method code to look for.
     *
     * @return Rate|null
     */
    private function findRate(array $rates, string $code): ?Rate
    {
        foreach ($rates as $rate) {
            if (!$rate->getErrorMessage() && (string)$rate->getCode() === $code) {
                return $rate;
            }
        }

        return null;
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
