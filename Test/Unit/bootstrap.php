<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Cron;
use Magento\Framework\Autoload\AutoloaderRegistry;

// Load magento's booststrap
require __DIR__ . '/../../../../../app/bootstrap.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    include __DIR__ . '/vendor/autoload.php';
}

AutoloaderRegistry::getAutoloader()->addPsr4(
    'SeQura\\Core\\Tests\\',
    BP . '/vendor/sequra/integration-core/tests/'
);

$bootstrap = Bootstrap::create(BP, $_SERVER);
$bootstrap->createApplication(Cron::class);
