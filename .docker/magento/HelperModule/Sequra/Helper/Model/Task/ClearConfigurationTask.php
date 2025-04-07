<?php
/**
 * Task class
 *
 * @package SeQura/Helper
 */

 namespace Sequra\Helper\Model\Task;

/**
 * Task class
 */
class ClearConfigurationTask extends Task
{

    /**
     * Execute the task
     *
     * @throws \Exception If the task fails
     */
    public function execute(array $args = [])
    {
        $this->removeStoreDataFromEntityTable();
        return $this->httpSuccessResponse();
    }
}
