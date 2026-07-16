<?php
/**
 * Task class
 *
 * @package SeQura/Helper
 */

 namespace Sequra\Helper\Model\Task;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Sequra\Core\Services\BusinessLogic\BannerService;

/**
 * Task class
 */
class ClearConfigurationTask extends Task
{

    /**
     * Execute the task
     *
     * @param string[] $args Arguments for the task
     *
     * @return array<string, mixed>
     *
     * @throws \Exception If the task fails
     */
    public function execute(array $args = [])
    {
        $this->removeStoreDataFromEntityTable();
        $this->removeBannerMedia();
        return $this->httpSuccessResponse();
    }

    /**
     * Remove seeded banner images from the media directory
     */
    private function removeBannerMedia(): void
    {
        /**
         * @var Filesystem $filesystem
         */
        $filesystem = ObjectManager::getInstance()->get(Filesystem::class);
        $mediaDir = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $path = BannerService::BANNER_MEDIA_DIR;
        if ($mediaDir->isExist($path)) {
            $mediaDir->delete($path);
        }
    }
}
