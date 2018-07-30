<?php
/**
 * Copyright (c) 2016-2017 Open Assessment Technologies, S.A.
 *
 * @author A.Zagovorichev, <zagovorichev@1pt.com>
 */

namespace oat\taoResultExports\scripts\tools;


use oat\oatbox\action\Action;
use oat\oatbox\extension\script\ScriptAction;
use oat\taoDeliveryRdf\model\DeliveryAssemblyService;
use oat\taoResultExports\model\export\AllBookletsExport;
use \common_report_Report as Report;

/**
 * Class GenerateCsvFile
 * @package oat\taoResultExports\scripts\tools
 *
 * usage : sudo -u www-data php index.php 'oat\taoResultExports\scripts\tools\GenerateCsvFile' -s title --policy all -p myDelivery
 */
class GenerateCsvFile extends ScriptAction
{

    protected function provideDescription()
    {
        return 'TAO Results - generate Csv';
    }

    protected function provideOptions()
    {
        return [
            'delivery' => [
                'prefix' => 'd',
                'longPrefix' => 'delivery',
                'required' => false,
                'description' => 'List of deliveries to export'
            ],
            'strategy' => [
                'prefix' => 's',
                'longPrefix' => 'strategy',
                'required' => true,
                'description' => 'Identifier strategy to use. possible values are \'itemRef\'|\'identifier\'|\'title\'|\'label\'.'
            ],
            'policy' => [
                'longPrefix' => 'policy',
                'required' => true,
                'description' => 'variables to extract. possible values are \'all\'|\'response\'|\'outcome\'.'
            ],
            'blacklist' => [
                'prefix' => 'b',
                'longPrefix' => 'blacklist',
                'description' => 'List of variables to not extract'
            ],
            'raw' => [
                'prefix' => 'r',
                'longPrefix' => 'raw',
                'flag' => true,
                'description' => 'Export in raw mode'
            ],
            'prefix' => [
                'prefix' => 'p',
                'longPrefix' => 'prefix',
                'description' => 'Prefix of the file to export'
            ],

        ];
    }

    protected function provideUsage()
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Prints a help statement'
        ];
    }

    public function run()
    {
        $delivery = $this->getOption('delivery');

        
        if (is_null($delivery) || strtolower($delivery) === 'all'){
            $deliveries = DeliveryAssemblyService::singleton()->getRootClass()->getInstances(true);
        } else {
            $deliveries = [];
            $explodedDeliveries = explode(',', $delivery);
            foreach($explodedDeliveries as $delivery){
                  $deliveries[] = new \core_kernel_classes_Resource($delivery);
            }
        }
        
        // Param 2: Identifier strategy.
        $identifierStrategy = $this->getOption('strategy');
        
        // Param 3: Variable policy.
        $variablePolicy = $this->getOption('policy');

        $variablePolicy = self::transformStringVariablePolicyIntoConstant(strtolower($variablePolicy));


        $prefix = ($this->hasOption('prefix'))?$this->getOption('prefix'):'export';

        $bookletExporter = new AllBookletsExport($deliveries, $identifierStrategy, $prefix);
        $bookletExporter->setVariablePolicy($variablePolicy);
        
        // Param 4: Variable blacklist.
        $variableBlacklist = $this->getOption('blacklist');
        if(!is_null($variableBlacklist)){
            $bookletExporter->setVariableBlacklist(explode(',', $variableBlacklist));
        }

        
        // Param 5: Raw mode.
        $rawMode = $this->getOption('raw');
        if (is_null($rawMode) === false) {
            $bookletExporter->addAlternateMissingDataEnconding(AllBookletsExport::NOT_RESPONDED, '');
        }
        
        $exportReport = $bookletExporter->export();


        $report = new \common_report_Report(
            \common_report_Report::TYPE_INFO,
            'Writing file : '.$bookletExporter->getFilename() . PHP_EOL . 'for deliveries : '.implode(',', $deliveries)
        );
        
        $report->add($exportReport);

        return $report;
    }
    
    private static function transformStringVariablePolicyIntoConstant($strVariablePolicy)
    {
        $variablePolicy = AllBookletsExport::VARIABLE_POLICY_ALL;
        
        switch ($strVariablePolicy) {
            case 'outcome':
                $variablePolicy = AllBookletsExport::VARIABLE_POLICY_OUTCOME;
                break;
                
            case 'response':
                $variablePolicy = AllBookletsExport::VARIABLE_POLICY_RESPONSE;
                break;
        }
        
        return $variablePolicy;
    }
}
