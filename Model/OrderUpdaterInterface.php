<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;


interface OrderUpdaterInterface
{
    /**
     * Send Order Update to SeQura
     * @param int $firstIncrementId increment id for the first order to update
     *
     * @return int
     * @throws \Exception
     */
    public function sendOrderUpdates($firstIncrementId = false, $limit = 1):int;
}
