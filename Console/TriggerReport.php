<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Console;

use \Sequra\Core\Model\ConfigFactory;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class TriggerReport extends Command
{   
    /** Command name */
    const NAME = 'sequra:triggerreport';

    /**
     * Names of input arguments or options
     */
    const INPUT_KEY_SHOPCODES = 'shopcodes';

    /**
     * @var \Sequra\Core\Model\Config
     */
    protected $_config;

    /**
     * @var \Sequra\Core\Model\ReporterFactory
     */
    protected $_reporter;


    /**
     * Constructor
     *
     * @param ConfigFactory $configFactory
     * @param \Sequra\Core\Model\ReporterFactory $reporterFactory
     */
    public function __construct(
        ConfigFactory $configFactory,
        \Sequra\Core\Model\ReporterFactory $reporterFactory
    ) {
        $this->_config = $configFactory->create();
        $this->_reporter = $reporterFactory->create();
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument(
            self::INPUT_KEY_SHOPCODES,
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Shop code'
        );
        $this->setName(self::NAME)
             ->setDescription('Send Delivery Report to SeQura');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $codeKeys = $input->getArgument(self::INPUT_KEY_SHOPCODES);
        $output->write('Trigger Delivery Report for ');
        if(count($codeKeys)<1){
            $codeKeys[0] = false; 
            $output->writeln('all shops');
        } else {
            $output->writeln(implode(',',$codeKeys));
        }
        foreach ($codeKeys as $codeKey){
            if ($results = $this->_reporter->sendOrderWithShipment($codeKey)) {
                $output->writeln('Ok, report Sent!');
                foreach($results as $key => $value){
                    $output->writeln($key . ' => ' . $value . ' orders sent');
                }
                return;
            }
            $output->writeln('Ko, report was not sent!');
        }
    }
}