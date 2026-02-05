<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use SeQura\Core\BusinessLogic\Domain\Integration\Log\LogServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Log\Model\Log;

class LogService implements LogServiceInterface
{
    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;
    /**
     * @var File
     */
    private File $fileIo;

    /**
     * @param DirectoryList $directoryList
     * @param File $fileIo
     */
    public function __construct(
        DirectoryList $directoryList,
        File $fileIo
    ) {
        $this->directoryList = $directoryList;
        $this->fileIo = $fileIo;
    }

    /**
     * @return Log
     */
    public function getLog(): Log
    {
        $logPath = $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . '/log/sequra_debug.log';

        if (!$this->fileIo->fileExists($logPath)) {
            return new Log([]);
        }

        $content = $this->fileIo->read($logPath);

        $arrayContent = explode(PHP_EOL, $content);

        return new Log($arrayContent);
    }

    /**
     * @return void
     */
    public function removeLog(): void
    {
        $logPath = $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . '/log/sequra_debug.log';

        if ($this->fileIo->fileExists($logPath)) {
            $this->fileIo->write($logPath, '');
        }
    }
}
