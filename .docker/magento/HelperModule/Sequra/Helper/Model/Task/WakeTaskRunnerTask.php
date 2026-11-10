<?php

namespace Sequra\Helper\Model\Task;

use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup;

class WakeTaskRunnerTask extends Task
{
    /**
     * Wakes the SeQura task runner up.
     *
     * @param string[] $args
     *
     * @return array<string, mixed>
     */
    public function execute(array $args = [])
    {
        $this->initSeQuraServices();
        /** @var TaskRunnerWakeup $wakeup */
        $wakeup = ServiceRegister::getService(TaskRunnerWakeup::CLASS_NAME);
        $wakeup->wakeup();

        return $this->httpSuccessResponse();
    }
}
