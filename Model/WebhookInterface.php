<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;


interface WebhookInterface
{
    /**
     * Get Webhook data and run corresponding handler
     *
     * @return void
     * @throws \Exception
     */
    public function processWebhookRequest();
}
