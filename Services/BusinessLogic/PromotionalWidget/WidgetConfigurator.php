<?php

namespace Sequra\Core\Services\BusinessLogic\PromotionalWidget;

use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use NumberFormatter;
use SeQura\Core\BusinessLogic\Domain\Integration\PromotionalWidgets\WidgetConfiguratorInterface;

class WidgetConfigurator implements WidgetConfiguratorInterface
{
    /**
     * @var ScopeResolverInterface
     */
    protected $scopeResolver;
    /**
     * @var ResolverInterface
     */
    protected $localeResolver;
    /**
     * @var NumberFormatter
     */
    protected $formatter;

    /**
     * Construct
     *
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver
    ) {
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->formatter = $this->getFormatter();
    }

    /**
     * Returns current locale
     *
     * @return string
     */
    public function getLocale(): string
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }

    /**
     * Returns current currency
     *
     * @return string
     */
    public function getCurrency(): string
    {
        /**
         * @var Store $store
         */
        $store = $this->scopeResolver->getScope();

        return $store->getCurrentCurrency()->getCode();
    }

    /**
     * Returns decimal separator
     *
     * @return string
     */
    public function getDecimalSeparator(): string
    {
        return (string) $this->formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    /**
     * Returns thousand separator
     *
     * @return string
     */
    public function getThousandsSeparator(): string
    {
        return (string) $this->formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }

    /**
     * Get formatter instance.
     *
     * @return NumberFormatter
     */
    private function getFormatter(): NumberFormatter
    {
        $localeCode = $this->localeResolver->getLocale();

        return new NumberFormatter(
            $localeCode . '@currency=' . $this->getCurrency(),
            NumberFormatter::CURRENCY
        );
    }
}
