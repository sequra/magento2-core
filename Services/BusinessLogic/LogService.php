<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Filesystem\Io\File;
use SeQura\Core\BusinessLogic\Domain\Integration\Log\LogServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Log\Model\Log;
use Sequra\Core\Model\Logger\LogFile;

class LogService implements LogServiceInterface
{
    private const MAX_READ_BYTES = 5 * 1024 * 1024;

    /**
     * @var LogFile
     */
    private LogFile $logFile;
    /**
     * @var File
     */
    private File $fileIo;
    /**
     * @var FileDriver
     */
    private FileDriver $fileDriver;

    /**
     * @param LogFile $logFile
     * @param File $fileIo
     * @param FileDriver $fileDriver
     */
    public function __construct(
        LogFile $logFile,
        File $fileIo,
        FileDriver $fileDriver
    ) {
        $this->logFile = $logFile;
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
        $logPath = $this->logFile->getPath();

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
                        return rtrim($line, "\r");
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
        $logPath = $this->logFile->getPath();

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
