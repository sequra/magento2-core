<?php

/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

/**
 * Returns the trackings stored for a seQura order, which are the ones last sent to seQura.
 */
class GetOrderTrackingsTask extends Task
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
        if (!isset($args['order_id'])) {
            $this->httpErrorResponse('Missing required argument: order_id', 400);
        }

        $data = $this->getSeQuraOrderData((string) $args['order_id']);
        $trackings = isset($data['trackings']) && is_array($data['trackings']) ? $data['trackings'] : [];

        return ['success' => true, 'data' => ['trackings' => $trackings]];
    }
}
