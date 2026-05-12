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
     * @var string|null $countryCode
     */
    protected ?string $countryCode = null;

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
        if ($this->countryCode === null) {
            // Only resolve locale when needed
            $locale = $this->localeResolver->getLocale();
            $underscorePos = strpos($locale, '_');
            if ($underscorePos !== false) {
                $this->countryCode = substr($locale, $underscorePos + 1);
            } else {
                $this->countryCode = $locale;
            }
        }

        return $this->countryCode;
    }
}
