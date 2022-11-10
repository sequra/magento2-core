<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

//@todo: Implement toknization as option

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'core';

    /**
     * @var Magento\Payment\Model\Method\ConfigInterface
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    public function __construct(
        \Magento\Payment\Model\Method\ConfigInterface $config,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface $localeResolver
    ) {
        $this->config = $config;
        $this->localeResolver = $localeResolver;
        $this->scopeResolver = $scopeResolver;
        $this->formatter = $this->getFormatter();
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                'sequra_configuration' => [
                    'merchant' => $this->config->getMerchantRef(),
                    'assetKey' => $this->config->getAssetsKey(),
                    'products' => ['i1','pp3','pp5','pp6','pp9','sp1'],
                    'scriptUri' => $this->config->getScriptUri(),
                    'decimalSeparator' => $this->getDecimalSeparator(),
                    'thousandSeparator' => $this->getThousandsSeparator(),
                    'locale': str_replace('_','-',$this->localeResolver->getLocale());
                ]
            ]
        ];
    }

    private function getFormatter()
    {
        $localeCode = $this->localeResolver->getLocale();
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    public function getDecimalSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    public function getThousandsSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }
}
