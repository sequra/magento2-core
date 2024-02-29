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
    private $csv;
    private $session;

    private $needsFallback = false;
    /**
     * @var array
     */
    private static $englishTranslation;

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
     *
     * @return Phrase
     */
    public function translate(string $text, ...$arguments): Phrase
    {
        $locale = ($user = $this->session->getUser()) ? $user->getInterfaceLocale(): null;
        if ($locale && !self::$englishTranslation) {
            $filePath = $this->moduleDirReader->getModuleDir('i18n', 'Sequra_Core') . '/' . $locale . '.csv';
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
