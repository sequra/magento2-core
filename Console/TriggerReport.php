<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Console;

use Sequra\Core\Model\ConfigFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to trigger DR report
 */
class TriggerReport extends Command
{
    /**
     *  Command name
     */
    const NAME = 'sequra:triggerreport';

    /**
     * Names of input arguments or options
     */
    const INPUT_KEY_SHOPCODES = 'shopcodes';
    /**
     * Names of input arguments or options
     */
    const INPUT_KEY_LIMIT = 'limit';

    /**
     * Configuration Object
     *
     * @var \Sequra\Core\Model\Config
     */
    protected $config;

    /**
     * Reporter
     *
     * @var \Sequra\Core\Model\ReporterFactory
     */
    protected $reporter;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * Constructor
     *
     * @param ConfigFactory $configFactory configFactory
     * @param \Sequra\Core\Model\ReporterFactory $reporterFactory reporteFactory
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        ConfigFactory $configFactory,
        \Sequra\Core\Model\ReporterFactory $reporterFactory,
        \Magento\Framework\App\State $state
    ) {
        $this->config = $configFactory->create();
        $this->reporter = $reporterFactory->create();
        $this->state = $state;
        parent::__construct();
    }

    /**
     * Initialize triggerreport command
     *
     * @return void
     */
    protected function configure()
    {
        $this->addArgument(
            self::INPUT_KEY_SHOPCODES,
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Shop code'
        );
        $this->addOption(
            self::INPUT_KEY_LIMIT,
            'l',
            InputOption::VALUE_OPTIONAL,
            'Limit number of orders in report'
        );
        $this->setName(self::NAME)
            ->setDescription('Send Delivery Report to SeQura');

        parent::configure();
    }

    /**
     * Execute command.
     *
     * @param InputInterface  $input  InputInterface
     * @param OutputInterface $output OutputInterface
     *
     * @return                                        void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
        $codeKeys = $input->getArgument(self::INPUT_KEY_SHOPCODES);
        $limit = $input->getOption(self::INPUT_KEY_LIMIT);
        $output->write('Trigger Delivery Report for ');
        if (count($codeKeys)<1) {
            $codeKeys[0] = false;
            $output->writeln('all shops');
        } else {
            $output->writeln(implode(',', $codeKeys));
        }
        foreach ($codeKeys as $codeKey) {
            if ($results = $this->reporter->sendOrderWithShipment($codeKey, $limit)) {
                $output->writeln('Ok, report Sent!');
                foreach ($results as $key => $value) {
                    $output->writeln($key . ' => ' . $value . ' orders sent');
                }
                return;
            }
            $output->writeln('Ko, report was not sent!');
        }
    }
}
