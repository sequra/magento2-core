<?php

// copy this file as autoload.php and replace real path to the Magento installation root directory
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Cron;

require '/REAL-PATH-TO-MAGENTO-ROOT/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$bootstrap->createApplication(Cron::class);

require __DIR__ . '/../vendor/autoload.php';
