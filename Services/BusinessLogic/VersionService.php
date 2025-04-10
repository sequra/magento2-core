<?php

namespace Sequra\Core\Services\BusinessLogic;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use Magento\Framework\Module\ModuleList;
use SeQura\Core\BusinessLogic\Domain\Integration\Version\VersionServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Version\Models\Version;

class VersionService implements VersionServiceInterface
{
    private const SEQURA_MAGENTO_REPOSITORY_URL = 'https://repo.packagist.org/p/sequra/magento2-core.json';
    private const SEQURA_MAGENTO_DOWNLOAD_URL = 'https://github.com/sequra/magento2-core/releases';

    /**
     * @var ModuleList
     */
    private $moduleList;
    
    /**
     * @var Client
     */
    private $client;
    
    /**
     * @var Uri
     */
    private $hubUri;

    /**
     * Constructor.
     *
     * @param ModuleList $moduleList
     * @param Client $client
     */
    public function __construct(ModuleList $moduleList, Client $client)
    {
        $this->moduleList = $moduleList;
        $this->client = $client;
        $this->hubUri = new Uri(self::SEQURA_MAGENTO_REPOSITORY_URL);
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): ?Version
    {
        return new Version(
            $this->getCurrentVersion(),
            $this->getLatestVersion(),
            new Uri(self::SEQURA_MAGENTO_DOWNLOAD_URL)
        );
    }

    /**
     * Gets the current plugin version.
     *
     * @return string
     */
    private function getCurrentVersion(): string
    {
        $moduleInfo = $this->moduleList->getOne('Sequra_Core');

        return $moduleInfo['setup_version'] ?? '';
    }

    /**
     * Gets the latest available plugin version.
     *
     * @return string|null
     */
    private function getLatestVersion(): ?string
    {
        try {
            $hubResponse = $this->client->request('GET', $this->hubUri);
        } catch (GuzzleException $exception) {
            return null;
        }

        $hubResponse = json_decode($hubResponse->getBody()->getContents(), true);
        $latestVersionInfo = $this->getLatestVersionFromInfoResponse($hubResponse);

        return $latestVersionInfo['version'] ?? null;
    }

    /**
     * Filters out non-version entities from the response.
     *
     * @param array $response
     *
     * @return array
     */
    private function getLatestVersionFromInfoResponse(array $response): array
    {
        $filteredArray = array_filter($response['packages']['sequra/magento2-core'], static function ($key) {
            return strpos($key, 'dev') !== 0;
        }, ARRAY_FILTER_USE_KEY);

        return end($filteredArray);
    }
}
