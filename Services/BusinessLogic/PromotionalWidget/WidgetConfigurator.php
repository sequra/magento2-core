<?php

namespace Sequra\Core\Services\BusinessLogic\PromotionalWidget;

use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\WidgetConfiguratorContracts\WidgetConfiguratorInterface;
use Magento\Directory\Model;

/**
 * Interface WidgetConfigurator
 *
 * @package Sequra\Core\Services\BusinessLogic\PromotionalWidget
 */
class WidgetConfigurator implements WidgetConfiguratorInterface
{
    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \NumberFormatter
     */
    protected $formatter;

    public function __construct(
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface $localeResolver
    )
    {
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->formatter = $this->getFormatter();
    }

    /**
     * Returns locale
     *
     * @return string
     */
    public function getLocale(): string
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }

    /**
     * Returns currency
     *
     * @return string
     */
    public function getCurrency(): string
    {
        /**
         * @var \Magento\Store\Model\Store $store
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
        return (string) $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    /**
     * Returns thousand separator
     *
     * @return string
     */
    public function getThousandsSeparator(): string
    {
        return (string) $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }

    /**
     * Get formatter instance.
     *
     * @return \NumberFormatter
     */
    private function getFormatter(): \NumberFormatter
    {
        $localeCode = $this->localeResolver->getLocale();

        return new \NumberFormatter(
            $localeCode . '@currency=' . $this->getCurrency(),
            \NumberFormatter::CURRENCY
        );
    }
}
