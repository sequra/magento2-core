<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Cron;

// Load magento's booststrap
require __DIR__ . '/../../../../../app/bootstrap.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    include __DIR__ . '/vendor/autoload.php';
}

$bootstrap = Bootstrap::create(BP, $_SERVER);
$bootstrap->createApplication(Cron::class);
