<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\ScopeInterface;

class CountryResolverService
{
    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepository;

    /**
     * @var ResolverInterface
     */
    private ResolverInterface $localeResolver;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var string|null
     */
    private ?string $resolvedCountry = null;

    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param AddressRepositoryInterface $addressRepository
     * @param ResolverInterface $localeResolver
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        AddressRepositoryInterface $addressRepository,
        ResolverInterface $localeResolver,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->addressRepository = $addressRepository;
        $this->localeResolver = $localeResolver;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Return country code
     *
     * @return string
     */
    public function getCountry(): string
    {
        if ($this->resolvedCountry !== null) {
            return $this->resolvedCountry;
        }

        $country = $this->getBillingAddressCountry();
        if ($country === '') {
            $country = $this->getCountryFromLocale();
        }

        if ($country === '') {
            $country = $this->getShopDefaultCountry();
        }

        $this->resolvedCountry = $country;

        return $country;
    }

    /**
     * Fetch country code from the billing address if it is available
     *
     * @return string
     */
    private function getBillingAddressCountry(): string
    {
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (NoSuchEntityException | LocalizedException $e) {
            $quote = null;
        }

        $address = $quote ? $quote->getBillingAddress() : null;
        if ($address && $address->getCountryId()) {
            return strtoupper((string)$address->getCountryId());
        }

        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        try {
            $customer = $this->customerSession->getCustomerData();
        } catch (NoSuchEntityException | LocalizedException $e) {
            $customer = null;
        }

        $defaultBillingId = $customer ? $customer->getDefaultBilling() : null;
        if (!$defaultBillingId) {
            return '';
        }

        try {
            $countryId = $this->addressRepository->getById((int)$defaultBillingId)->getCountryId();
        } catch (NoSuchEntityException | LocalizedException $e) {
            return '';
        }

        return $countryId ? strtoupper($countryId) : '';
    }

    /**
     * Fetch country code from the locale
     *
     * @return string
     */
    private function getCountryFromLocale(): string
    {
        $parts = explode('_', $this->localeResolver->getLocale());
        if (count($parts) > 1 && strlen($parts[1]) === 2) {
            return strtoupper($parts[1]);
        }

        return '';
    }

    /**
     * Fetch default shop country code
     *
     * @return string
     */
    private function getShopDefaultCountry(): string
    {
        $value = $this->scopeConfig->getValue(
            Data::XML_PATH_DEFAULT_COUNTRY,
            ScopeInterface::SCOPE_STORE
        );

        return strtoupper(is_string($value) ? $value : '');
    }
}
