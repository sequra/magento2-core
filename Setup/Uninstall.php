<?php

namespace Sequra\Core\Setup;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Psr\Log\LoggerInterface;
use Sequra\Core\Services\BusinessLogic\BannerService;

class Uninstall implements UninstallInterface
{
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     */
    public function __construct(Filesystem $filesystem, LoggerInterface $logger)
    {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    /**
     * Removes plugin database tables and stored banner images.
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->dropEntityTable(DatabaseHandler::SEQURA_ENTITY_TABLE);
        $databaseHandler->dropEntityTable(DatabaseHandler::SEQURA_QUEUE_TABLE);
        $databaseHandler->dropEntityTable(DatabaseHandler::SEQURA_ORDER_TABLE);

        $this->deleteBannerMedia();

        $installer->endSetup();
    }

    /**
     * Removes the banner media directory.
     *
     * Failures are logged but won't abort uninstall.
     */
    private function deleteBannerMedia(): void
    {
        try {
            $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            if ($mediaDir->isExist(BannerService::BANNER_MEDIA_DIR)) {
                $mediaDir->delete(BannerService::BANNER_MEDIA_DIR);
            }
        } catch (FileSystemException $e) {
            $this->logger->warning(
                'Failed to remove banner media directory during Sequra uninstall: ' . $e->getMessage()
            );
        }
    }
}
