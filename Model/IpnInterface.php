<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;


interface IpnInterface
{
    /**
     * Get ipn data, send verification to PayPal, run corresponding handler
     *
     * @return void
     * @throws \Exception
     */
    public function processIpnRequest();
}
