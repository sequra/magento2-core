<?php

namespace Sequra\Core\Services\BusinessLogic\PromotionalWidget;

use Magento\Framework\Locale\ResolverInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\PromotionalWidgets\MiniWidgetMessagesProviderInterface;

class MiniWidgetMessagesProvider implements MiniWidgetMessagesProviderInterface
{
    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $localeResolver;

    /**
     * @param ResolverInterface $localeResolver
     */
    public function __construct(ResolverInterface $localeResolver)
    {
        $this->localeResolver = $localeResolver;
    }

    /**
     * @inheritDoc
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return self::MINI_WIDGET_MESSAGE[$this->getCountryCode()] ?? null;
    }

    /**
     * @inheritDoc
     *
     * @return string|null
     */
    public function getBelowLimitMessage(): ?string
    {
        return self::MINI_WIDGET_BELOW_LIMIT_MESSAGE[$this->getCountryCode()] ?? null;
    }

    /**
     * Returns country code from locale
     *
     * @return string
     */
    protected function getCountryCode(): string
    {
        $locale = $this->localeResolver->getLocale();

        return substr($locale, strpos($locale, '_') + 1);
    }
}
