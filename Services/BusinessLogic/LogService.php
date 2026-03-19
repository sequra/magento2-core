<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Filesystem\Io\File;
use SeQura\Core\BusinessLogic\Domain\Integration\Log\LogServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Log\Model\Log;

class LogService implements LogServiceInterface
{
    private const MAX_READ_BYTES = 5 * 1024 * 1024;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;
    /**
     * @var File
     */
    private File $fileIo;
    /**
     * @var FileDriver
     */
    private FileDriver $fileDriver;

    /**
     * @param DirectoryList $directoryList
     * @param File $fileIo
     * @param FileDriver $fileDriver
     */
    public function __construct(
        DirectoryList $directoryList,
        File $fileIo,
        FileDriver $fileDriver
    ) {
        $this->directoryList = $directoryList;
        $this->fileIo = $fileIo;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Retrieve client-specific log entries (tail-limited to 5 MB).
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

        $content = $this->readTail($logPath, self::MAX_READ_BYTES);

        if ($content === '') {
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
     * Read at most $maxBytes from the end of a file.
     *
     * If the file is smaller than $maxBytes the full content is returned.
     * When truncated, the first (potentially partial) line is discarded.
     *
     * @param string $path
     * @param int $maxBytes
     *
     * @return string
     */
    private function readTail(string $path, int $maxBytes): string
    {
        $stat = $this->fileDriver->stat($path);
        $fileSize = (int)($stat['size'] ?? 0);
        if ($fileSize === 0) {
            return '';
        }

        $handle = $this->fileDriver->fileOpen($path, 'r');

        try {
            if ($fileSize <= $maxBytes) {
                $content = $this->fileDriver->fileRead($handle, $fileSize);
                return is_string($content) ? $content : '';
            }

            $this->fileDriver->fileSeek($handle, -$maxBytes, SEEK_END);
            $content = $this->fileDriver->fileRead($handle, $maxBytes);
            if (!is_string($content)) {
                return '';
            }

            // Discard the first partial line
            $newlinePos = strpos($content, PHP_EOL);
            if ($newlinePos !== false) {
                $content = substr($content, $newlinePos + strlen(PHP_EOL));
            }

            return $content;
        } finally {
            $this->fileDriver->fileClose($handle);
        }
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

        try {
            $handle = $this->fileDriver->fileOpen($logPath, 'c');
        } catch (FileSystemException $e) {
            return;
        }

        try {
            if ($this->fileDriver->fileLock($handle, LOCK_EX)) {
                ftruncate($handle, 0);
                $this->fileDriver->fileLock($handle, LOCK_UN);
            }
        } finally {
            $this->fileDriver->fileClose($handle);
        }
    }
}
