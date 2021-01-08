<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;


interface ReporterInterface
{
    /**
     * Build and send the delivery report to SeQura
     * @param int $codeKey shop code to build the report for
     * @param int $limit limit
     *
     * @return int
     * @throws \Exception
     */
    public function sendOrderWithShipment(int $codeKey = null, int $limit = null):array;
}
