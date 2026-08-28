<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
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
 * A shopper with no address is not a blocker, and neither is a guest with no account at all: the
 * express screen exists to collect what the merchant could not send, so the solicit goes out with
 * the addresses missing (the create-order request declares `addresses_may_be_missing`, see
 * MerchantDataProvider::getOptions) and rates are collected on the round trip, once applyChange()
 * writes the address the shopper typed.
 * Only the country cannot be left blank — it is what picks the SeQura merchant — so
 * {@see resolveCountry} names one for a quote that has no address yet, ending at the store view's
 * locale so there is always an answer.
 *
 * Two entry points with different contracts:
 *  - getResolvableShippingCountry() is a read-only availability probe for storefront
 *    blocks: it returns the country the solicit would use without collecting rates
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
     * @var ResolverInterface
     */
    private ResolverInterface $localeResolver;

    /**
     * QuoteShippingResolver constructor.
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $quoteRepository,
        ResolverInterface $localeResolver
    ) {
        $this->customerRepository = $customerRepository;
        $this->quoteRepository = $quoteRepository;
        $this->localeResolver = $localeResolver;
    }

    /**
     * Read-only availability probe: returns the ISO2 country the solicit would deliver to, or
     * null when there is no country to solicit against at all.
     *
     * Guests included: they simply have no customer default address, so the chain falls through
     * to the quote's own shipping address and then the store view's locale — the very country the
     * promotional widgets on the same page resolve.
     *
     * Answers only "which country", never "is this shopper eligible": having no address is not a
     * blocker any more, so the button decision is left entirely to the caller's per-country
     * availability check on the country returned here. Because {@see resolve} resolves the
     * country the same way, a supported answer here is a country the solicit can pick a merchant
     * for — the button cannot appear where the solicit would 422 on the country.
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
            $country = $this->resolveCountry(
                $quote,
                $customer !== null ? $this->findDefaultShippingAddress($customer) : null
            );

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
     * A shopper with no default shipping address — a customer who never saved one, or a guest with
     * no account — is prepared without one: only the country is written (nothing else is known),
     * no rates are collected, and the SeQura order is solicited with the addresses missing so the
     * express screen can ask for them. See {@see prepareWithoutAddress}.
     *
     * @param Quote $quote Cart quote to mutate and save.
     *
     * @return bool True if the quote was prepared and saved; false if no delivery country can be
     *              named at all, or the customer has an address but no shipping rate for it.
     */
    public function resolve(Quote $quote): bool
    {
        try {
            $customer = $this->getQuoteCustomer($quote);
            if ($customer === null) {
                // Guest cart: no account, so nothing to import. Same treatment as a customer with
                // no saved address — the express screen collects it.
                return $this->prepareWithoutAddress($quote);
            }

            $defaultShippingAddress = $this->findDefaultShippingAddress($customer);
            if ($defaultShippingAddress === null) {
                return $this->prepareWithoutAddress($quote);
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

            $this->applyPaymentAndSave($quote);

            return true;
        } catch (Exception $e) {
            Logger::logError('Express Checkout shipping resolution failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return false;
        }
    }

    /**
     * Prepares a quote for a shopper who has no address yet (guest, or a customer who never saved
     * one): writes the delivery country onto the empty shipping/billing addresses and nothing
     * else, then saves.
     *
     * No rates are collected — there is no destination to quote for — so the order is solicited
     * with no delivery method and no shipping line. Both arrive on the round trip, once the
     * shopper fills the missing-data card and {@see applyChange} re-collects against a real
     * address.
     *
     * The country is the one exception to "leave it missing": it is what picks the SeQura
     * merchant, both here and in core's country check, so a blank one would fail the solicit
     * outright rather than defer the question to the express screen.
     *
     * @param Quote $quote Cart quote to mutate and save.
     *
     * @return bool True when prepared; false when no delivery country can be named at all.
     */
    private function prepareWithoutAddress(Quote $quote): bool
    {
        $country = $this->resolveCountry($quote, null);
        if ($country === '') {
            return false;
        }

        foreach ([$quote->getShippingAddress(), $quote->getBillingAddress()] as $address) {
            if ((string)$address->getCountryId() === '') {
                $address->setCountryId($country);
            }
        }

        $this->applyPaymentAndSave($quote);

        return true;
    }

    /**
     * The ISO2 country the express order is solicited against, most specific first: the
     * customer's default shipping country, then whatever the cart already ships to (the shopper's
     * own shipping estimate), then the store view's own locale. A guest has no customer default
     * address, so for them the chain starts at the cart's own shipping estimate.
     *
     * The locale country is the same last resort the promotional widgets use — they hand core
     * {@see \Sequra\Core\Block\WidgetTrait::getShippingAddressCountry} and
     * {@see \Sequra\Core\Block\WidgetTrait::getCurrentCountry} in that order — so a shopper
     * with no address gets the country the widgets on that very page already resolved, instead of
     * one that can silently disagree with them. It is only a starting point: the shopper's real
     * country arrives with the address they add on the express screen, and whether SeQura serves
     * this one at all is decided by the caller's availability check, which reads the same value.
     *
     * Both the probe and {@see prepareWithoutAddress} go through here, and both are handed the
     * same cart quote, so the country the button was granted for is the country the solicit uses.
     *
     * @param Quote $quote Quote whose shipping address supplies the middle fallback.
     * @param AddressInterface|null $defaultShippingAddress Customer default shipping address, if any.
     *
     * @return string ISO2 country. Never empty in practice: a store view always has a locale.
     */
    private function resolveCountry(Quote $quote, ?AddressInterface $defaultShippingAddress): string
    {
        $country = $defaultShippingAddress !== null ? (string)$defaultShippingAddress->getCountryId() : '';
        if ($country !== '') {
            return $country;
        }

        $country = (string)$quote->getShippingAddress()->getCountryId();
        if ($country !== '') {
            return $country;
        }

        // The store view's locale always carries a region (Magento only offers full `xx_YY`
        // locales, and falls back to en_US), so this names a real country for every store.
        return (string)\Locale::getRegion((string)$this->localeResolver->getLocale());
    }

    /**
     * Selects SeQura as the quote's payment method, recomputes totals from a clean state and
     * persists the quote.
     *
     * @param Quote $quote Quote to finish preparing and save.
     *
     * @return void
     *
     * @throws LocalizedException If the quote cannot be saved.
     */
    private function applyPaymentAndSave(Quote $quote): void
    {
        $quote->getPayment()->setMethod(ConfigProvider::CODE);
        $quote->setData('totals_collected_flag', false);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);
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
