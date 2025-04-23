<?php

namespace Sequra\Core\Services\BusinessLogic\Utility;

use Exception;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\File\Csv;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Phrase;

class SeQuraTranslationProvider
{
    /**
     * @var Reader
     */
    private $moduleDirReader;

    /**
     * @var Csv
     */
    private $csv;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var bool
     */
    private $needsFallback = false;
    
    /**
     * @var array<string>
     */
    private static $englishTranslation;

    /**
     * Constructor for SeQuraTranslationProvider
     *
     * @param Reader $moduleDirReader
     * @param Csv $csv
     * @param Session $session
     */
    public function __construct(Reader $moduleDirReader, Csv $csv, Session $session)
    {
        $this->session = $session;
        $this->csv = $csv;
        $this->moduleDirReader = $moduleDirReader;
    }

    /**
     * Translates the given label.
     *
     * @param string $text
     * @param array $arguments
     * @phpstan-param array<string, mixed> $arguments
     *
     * @return Phrase
     */
    public function translate(string $text, ...$arguments): Phrase
    {
        $locale = ($user = $this->session->getUser()) ? $user->getInterfaceLocale(): null;
        if ($locale && !self::$englishTranslation) {
            $filePath = $this->moduleDirReader->getModuleDir('i18n', 'Sequra_Core') . '/' . $locale . '.csv';
            // TODO: The use of function file_exists() is discouraged
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            if (!file_exists($filePath)) {
                $filePath = $this->moduleDirReader->getModuleDir('i18n', 'Sequra_Core') . '/en_US.csv';

                try {
                    $payload = $this->csv->getData($filePath);
                } catch (Exception $e) {
                    $payload = [];
                }

                foreach ($payload as $item) {
                    self::$englishTranslation[reset($item)] = end($item);
                }

                $this->needsFallback = true;
            }
        }

        if ($this->needsFallback) {
            $text = array_key_exists($text, self::$englishTranslation) ? self::$englishTranslation[$text] : $text;
        }

        return __($text, ...$arguments);
    }
}
