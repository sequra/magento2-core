<?php

/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Sequra\Core\Setup\DatabaseHandler;

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

        $connection = $this->conn->getConnection();
        $select = $connection->select()
            ->from(DatabaseHandler::SEQURA_ORDER_TABLE, ['data'])
            ->where('index_3 = ?', $args['order_id'])
            ->limit(1);
        $result = $connection->fetchOne($select);
        if (!is_string($result)) {
            $this->httpErrorResponse('Order not found', 404);
        }

        $data = json_decode((string) $result, true);
        $trackings = is_array($data) && isset($data['trackings']) && is_array($data['trackings'])
            ? $data['trackings']
            : [];

        return ['success' => true, 'data' => ['trackings' => $trackings]];
    }
}
