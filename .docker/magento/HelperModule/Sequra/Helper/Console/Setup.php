<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Helper\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to trigger DR report
 */
class Setup extends Command
{
    /**
     *  Command name
     */
    public const NAME = 'sequra-helper:setup';
    
   /**
    * Initialize triggerreport command
    *
    * @return void
    */
    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Setup data for quick testing');
        parent::configure();
    }
    /**
     * Execute command.
     *
     * @param InputInterface  $input  InputInterface
     * @param OutputInterface $output OutputInterface
     *
     * @return int 0 if everything went fine, or an exit code
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty(getenv('M2_SAMPLE_DATA'))) {
            $output->writeln("Skip setup, M2_SAMPLE_DATA is not set");
            return 0;
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /**
         * @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
         */
        $customerRepository = $objectManager->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
    
        $customer = $customerRepository->getById(1);
        $customer->setLastname('Costello Costello');
        $addresses = $customer->getAddresses();
        if (!empty($addresses)) {
            $address = reset($addresses);
            $address->setStreet(['Nueva Calle', 'Piso 2']);
            $address->setCity('Barcelona');
            $address->setPostcode('08010');
            $address->setCountryId('ES');
            $address->setTelephone('666666666');
            /**
             * @var \Magento\Directory\Model\RegionFactory $regionFactory
             */
            $regionFactory = $objectManager->create(\Magento\Directory\Model\RegionFactory::class);
            $region = $regionFactory->create()->loadByName('Barcelona', 'ES');
            $regionId = $region->getId();
            if ($regionId && is_numeric($regionId)) {
                $address->setRegionId((int) $regionId);
            }
        }
       
        $customerRepository->save($customer);
        $output->writeln("Dirección actualizada correctamente");
        return 0;
    }
}
