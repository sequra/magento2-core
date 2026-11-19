<?php

namespace Sequra\Core\Model\Logger;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

/**
 * Resolves and writes the debug log file of the store currently in context.
 *
 * The Advanced tab is store-scoped, so each store gets its own file. Sharing this
 * class between the writer (DefaultLoggerAdapter) and the reader (LogService)
 * keeps both ends on the same path.
 */
class LogFile
{
    /**
     * Truncated once it grows past this, mirroring WooCommerce's Log_File.
     */
    private const MAX_FILE_BYTES = 2 * 1024 * 1024;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;
    /**
     * @var FileDriver
     */
    private FileDriver $fileDriver;

    /**
     * @param DirectoryList $directoryList
     * @param FileDriver $fileDriver
     */
    public function __construct(DirectoryList $directoryList, FileDriver $fileDriver)
    {
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Path of the debug log for the store currently in context.
     *
     * Falls back to the unsuffixed file when no store is in context, which covers
     * writes from outside StoreContext::doWithStore(). That file is the catch-all
     * for unscoped messages; the Advanced tab always reads a store-suffixed one.
     *
     * @return string
     * @throws FileSystemException
     */
    public function getPath(): string
    {
        return $this->directoryList->getPath(DirectoryList::LOG)
            . '/sequra_debug' . $this->storeSuffix() . '.log';
    }

    /**
     * Append a message, truncating the file first if it outgrew the cap.
     *
     * Every failure is swallowed: the callers are core's Logger, often reached from
     * a catch block reporting an earlier error, and a logging exception there would
     * replace the failure the merchant needs to see.
     *
     * @param string $message
     *
     * @return void
     */
    public function append(string $message): void
    {
        try {
            $path = $this->getPath();
            $handle = $this->fileDriver->fileOpen($path, 'a');

            try {
                $this->fileDriver->fileLock($handle, LOCK_EX);

                if ($this->size($path) >= self::MAX_FILE_BYTES) {
                    ftruncate($handle, 0);
                }

                $this->fileDriver->fileWrite($handle, $message);
                $this->fileDriver->fileLock($handle, LOCK_UN);
            } finally {
                $this->fileDriver->fileClose($handle);
            }
        } catch (FileSystemException $e) {
            return;
        }
    }

    /**
     * Filename suffix identifying the store in context.
     *
     * The store id reaches this from a request parameter, so keep it to
     * characters that cannot escape the log directory.
     *
     * @return string
     */
    private function storeSuffix(): string
    {
        $storeId = (string)preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            StoreContext::getInstance()->getStoreId()
        );

        return $storeId === '' ? '' : '.' . $storeId;
    }

    /**
     * Current size of the log file in bytes.
     *
     * @param string $path
     *
     * @return int
     * @throws FileSystemException
     */
    private function size(string $path): int
    {
        $stat = $this->fileDriver->stat($path);

        return (int)($stat['size'] ?? 0);
    }
}
