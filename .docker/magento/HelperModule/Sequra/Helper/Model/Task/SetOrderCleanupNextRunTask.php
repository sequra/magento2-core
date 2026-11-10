<?php

namespace Sequra\Helper\Model\Task;

use SeQura\Core\BusinessLogic\Domain\OrderCleanup\Services\OrderCleanupService;
use SeQura\Core\Infrastructure\ServiceRegister;

class SetOrderCleanupNextRunTask extends Task
{
    /**
     * Overwrites the next order cleanup run with the given timestamp.
     *
     * @param string[] $args
     *
     * @return array<string, mixed>
     */
    public function execute(array $args = [])
    {
        if (!isset($args['timestamp']) || !is_numeric($args['timestamp'])) {
            $this->httpErrorResponse('Missing or invalid argument: timestamp', 400);
        }

        $this->initSeQuraServices();
        /** @var OrderCleanupService $cleanupService */
        $cleanupService = ServiceRegister::getService(OrderCleanupService::class);
        $cleanupService->schedule((int) $args['timestamp'], true);

        return $this->httpSuccessResponse();
    }
}
