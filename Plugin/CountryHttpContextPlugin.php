<?php

namespace Sequra\Core\Plugin;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Services\BusinessLogic\CountryResolverService;
use Throwable;

/**
 * Class CountryHttpContextPlugin
 *
 * Registers the SeQura-resolved country in Magento's HTTP context so FPC varies per country
 */
class CountryHttpContextPlugin
{
    private const CONTEXT_KEY = 'sequra_country';
    private const DEFAULT_COUNTRY = '';

    /**
     * @var HttpContext
     */
    private HttpContext $httpContext;

    /**
     * @var CountryResolverService
     */
    private CountryResolverService $countryResolver;

    /**
     * @param HttpContext $httpContext
     * @param CountryResolverService $countryResolver
     */
    public function __construct(
        HttpContext $httpContext,
        CountryResolverService $countryResolver
    ) {
        $this->httpContext = $httpContext;
        $this->countryResolver = $countryResolver;
    }

    /**
     * Adds the resolved country to the HTTP context so it varies the FPC key.
     *
     * @param ActionInterface $subject
     * @param RequestInterface $request
     *
     * @return array<int, RequestInterface>
     */
    public function beforeDispatch(ActionInterface $subject, RequestInterface $request): array
    {
        try {
            $country = $this->countryResolver->getCountry();
        } catch (Throwable $e) {
            Logger::logError('Resolving banner country for FPC vary failed: ' . $e->getMessage());
            $country = self::DEFAULT_COUNTRY;
        }

        $this->httpContext->setValue(self::CONTEXT_KEY, $country, self::DEFAULT_COUNTRY);

        return [$request];
    }
}
