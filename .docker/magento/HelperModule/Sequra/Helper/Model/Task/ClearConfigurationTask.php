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
        $mediaDir = ObjectManager::getInstance()->get(Filesystem::class)->getDirectoryWrite(DirectoryList::MEDIA);
        $path = 'sequra/banners';
        if ($mediaDir->isExist($path)) {
            $mediaDir->delete($path);
        }
    }
}
