<?php

namespace Sequra\Core\Services\BusinessLogic\PromotionalWidget;

use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\WidgetConfiguratorContracts\MiniWidgetMessagesProviderInterface;

/**
 * Class MiniWidgetMessagesProvider
 *
 * @package Sequra\Core\Services\BusinessLogic\PromotionalWidget
 */
class MiniWidgetMessagesProvider implements MiniWidgetMessagesProviderInterface
{
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    public function __construct(
        \Magento\Framework\Locale\ResolverInterface $localeResolver
    )
    {
        $this->localeResolver = $localeResolver;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return self::MINI_WIDGET_MESSAGE[$this->getCountryCode()] ?? null;
    }

    /**
     * @return string|null
     */
    public function getBelowLimitMessage(): ?string
    {
        return self::MINI_WIDGET_BELOW_LIMIT_MESSAGE[$this->getCountryCode()] ?? null;
    }

    /**
     * @return string
     */
    protected function getCountryCode(): string
    {
        $locale = $this->localeResolver->getLocale();
        
        return substr($locale, strpos($locale, '_') + 1);
    }
}
