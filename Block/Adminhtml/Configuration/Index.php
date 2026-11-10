<?php

namespace Sequra\Core\Block\Adminhtml\Configuration;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Sequra\Core\Helper\UrlHelper;
use SeQura\Core\Infrastructure\Utility\RegexProvider;

class Index extends Template
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var Session
     */
    private $authSession;
    /**
     * @var RegexProvider
     */
    private $regexProvider;
    /**
     * @var Reader
     */
    private $moduleDirReader;
    /**
     * @var File
     */
    private $fileDriver;

    /**
     * Content constructor.
     *
     * @param Context $context
     * @param UrlHelper $urlHelper
     * @param Session $authSession
     * @param RegexProvider $regexProvider
     * @param Reader $moduleDirReader
     * @param File $fileDriver
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        UrlHelper $urlHelper,
        Session $authSession,
        RegexProvider $regexProvider,
        Reader $moduleDirReader,
        File $fileDriver,
        array $data = []
    ) {
        if (!is_iterable($data)) {
            throw new \InvalidArgumentException('Data must be iterable');
        }
        parent::__construct($context, $data);

        $this->urlHelper = $urlHelper;
        $this->authSession = $authSession;
        $this->regexProvider = $regexProvider;
        $this->moduleDirReader = $moduleDirReader;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Returns URL to backend controller that provides data for the configuration page.
     *
     * @param string $controllerName Name of the configuration controller.
     * @param string $storeId Store id.
     * @param string $action Controller action.
     *
     * @return string URL to backend configuration controller.
     *
     * @throws LocalizedException
     */
    public function getControllerUrl(string $controllerName, string $storeId, string $action): string
    {
        $routeParams = [
            'storeId' => $storeId,
            'action' => $action,
            'form_key' => $this->formKey->getFormKey()
        ];

        return $this->urlHelper->getBackendUrl('sequra/configuration/' . strtolower($controllerName), $routeParams);
    }

    /**
     * Returns Sequra module translations in the default and the current system language.
     *
     * @return array<string, mixed>
     */
    public function getTranslations(): array
    {
        $user = $this->authSession->getUser();
        $currentLocale = strtolower($user ? substr($user->getInterfaceLocale(), 0, 2) : 'en');
        $langDir = $this->moduleDirReader->getModuleDir(Dir::MODULE_VIEW_DIR, 'Sequra_Core') . '/adminhtml/web/lang/';
        $default = $this->readJson($langDir . 'en.json');
        $current = $currentLocale === 'en'
            ? ($default ?? [])
            : ($this->readJson($langDir . $currentLocale . '.json') ?? []);

        return [
            'default' => str_replace("'", "\\'", (string) json_encode($default)),
            'current' => str_replace("'", "\\'", (string) json_encode($current)),
        ];
    }

    /**
     * Reads and decodes a JSON file, or returns null when the file is missing or cannot be read.
     *
     * @param string $path
     *
     * @return mixed
     */
    private function readJson(string $path)
    {
        try {
            if (!$this->fileDriver->isExists($path)) {
                return null;
            }

            return json_decode($this->fileDriver->fileGetContents($path), false);
        } catch (FileSystemException $e) {
            return null;
        }
    }

    /**
     * Get the regex in JSON format
     *
     * @return string
     */
    public function getRegexJson(): string
    {
        $regex = $this->regexProvider->toArray();

        try {
            return (string) json_encode($regex, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            // Fallback to a valid JSON literal to avoid invalid JavaScript in templates.
            return 'null';
        }
    }
}
