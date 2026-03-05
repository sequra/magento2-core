<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
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
     * Retrieve client-specific log entries.
     *
     * @return Log
     * @throws FileSystemException
     */
    public function getLog(): Log
    {
        $logPath = $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . '/log/sequra_debug.log';

        if (!$this->fileIo->fileExists($logPath)) {
            return new Log([]);
        }

        $content = $this->fileIo->read($logPath);

        if (!is_string($content) || $content === '') {
            return new Log([]);
        }

        $arrayContent = array_values(
            array_filter(
                array_map(
                    static function (string $line): string {
                        return (string)preg_replace('/^\[.*?\]\s\w+\.\w+:\s/', '', $line);
                    },
                    explode(PHP_EOL, $content)
                ),
                static fn(string $line): bool => $line !== ''
            )
        );

        return new Log($arrayContent);
    }

    /**
     * Clear client log file content.
     *
     * @return void
     * @throws FileSystemException
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
