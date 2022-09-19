<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Sequra\Core\Gateway\Validator\CurrencyValidator;

class AbstractTeaser extends Template implements BlockInterface
{
    protected static $paymentCode;
    protected $_template = "widget/teaser.phtml";
    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $formatter;

    /**
     * @var Sequra\Core\Model\Config
     */
    protected $config;

    /**
     * @var IpAddressValidator
     */
    private $currencyValidator;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Sequra\Core\Model\Config $config,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\View\Element\Template\Context $context,
        CurrencyValidator $currencyValidator,
        array $data = []
    ) {
        $this->config = $config;
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        parent::__construct($context, $data);
        $this->formatter = $this->getFormatter();
        $this->currencyValidator = $currencyValidator;
    }
    
    /**
     * Validate before producing html
     *
     * @return string
     */
    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    protected function _toHtml()
    {
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        $storeId = $this->scopeResolver->getScope()->getStoreId();
        $result = $this->currencyValidator->validate([
            'storeId' => $storeId,
            'currency' => $currency->getCode()
        ]);
        if ($result->isValid()) {
            return parent::_toHtml();
        }
        return '';
    }
    // phpcs:enable

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

    public function getMaxOrderTotal()
    {
        return $this->config->getMaxOrderTotal();
    }

    public function getMinOrderTotal()
    {
        return $this->config->getMinOrderTotal();
    }

    public function getProduct()
    {
        return $this->config->getProduct();
    }

    public function getScriptUri()
    {
        return $this->config->getScriptUri();
    }

    public function getAssetsKey()
    {
        return $this->config->getAssetsKey();
    }

    public function getMerchantRef()
    {
        return $this->config->getMerchantRef();
    }

    public function getLocale(){
        return str_replace('_','-',$this->localeResolver->getLocale());
    }

    /** @deprecated */
    public function getSilent(){
        return 'false';
    }
}
