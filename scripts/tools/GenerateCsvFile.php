<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA
 *
 */

declare(strict_types=1);

namespace oat\taoResultExports\scripts\tools;

use common_report_Report as Report;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\taoDeliveryRdf\model\DeliveryAssemblyService;
use oat\taoResultExports\model\export\AllBookletsExport;

/**
 * Class GenerateCsvFile
 * @package oat\taoResultExports\scripts\tools
 *
 * Usage: sudo -u www-data php index.php 'oat\taoResultExports\scripts\tools\GenerateCsvFile' -s title --policy all -p myDelivery
 */
class GenerateCsvFile extends ScriptAction
{
    use OntologyAwareTrait;

    public const OPTION_DELIVERY = 'delivery';
    public const OPTION_STRATEGY = 'strategy';
    public const OPTION_POLICY = 'policy';
    public const OPTION_BLACKLIST = 'blacklist';
    public const OPTION_RAW = 'raw';
    public const OPTION_PREFIX = 'prefix';
    public const OPTION_DAILY = 'daily';
    public const OPTION_EXOTIC = 'exotic';
    public const OPTION_WITHOUT_TIMESTAMP = 'without-timestamp';

    protected const OPTIONS_POSSIBLE_VALUES_MAP = [
        self::OPTION_STRATEGY => ['itemRef', 'identifier', 'title', 'label'],
        self::OPTION_POLICY => ['all', 'response', 'outcome'],
    ];

    protected const OPTIONS_ALLOWED_PERIODS = [
        self::OPTION_DAILY
    ];

    protected static function possibleValues(string $option): string
    {
        if (!array_key_exists($option, static::OPTIONS_POSSIBLE_VALUES_MAP)) {
            return '';
        }

        return sprintf('Possible values are: %s', implode('|', static::OPTIONS_POSSIBLE_VALUES_MAP[$option]));
    }

    protected function provideDescription(): string
    {
        return 'TAO Results - Generate CSV';
    }

    protected function provideOptions(): array
    {
        return [

            static::OPTION_DELIVERY => [
                'longPrefix' => static::OPTION_DELIVERY,
                'prefix' => 'd',
                'required' => false,
                'description' => 'List of deliveries to export'
            ],

            static::OPTION_STRATEGY => [
                'longPrefix' => static::OPTION_STRATEGY,
                'prefix' => 's',
                'required' => true,
                'description' => 'Identifier strategy to use. '
                    . static::possibleValues(static::OPTION_STRATEGY)
            ],

            static::OPTION_POLICY => [
                'longPrefix' => static::OPTION_POLICY,
                'prefix' => 'p',
                'required' => true,
                'description' => 'Variables to extract. '
                    . static::possibleValues(static::OPTION_POLICY)
            ],

            static::OPTION_BLACKLIST => [
                'longPrefix' => static::OPTION_BLACKLIST,
                'prefix' => 'b',
                'required' => false,
                'description' => 'List of variables to not extract'
            ],

            static::OPTION_RAW => [
                'longPrefix' => static::OPTION_RAW,
                'prefix' => 'r',
                'flag' => true,
                'description' => 'Export in raw mode',
                'defaultValue' => false,
                'cast' => 'boolean'
            ],

            static::OPTION_PREFIX => [
                'longPrefix' => static::OPTION_PREFIX,
                'prefix' => 'p',
                'description' => 'Prefix of the file to export',
                'defaultValue' => 'export',
                'cast' => 'string',
            ],

            static::OPTION_DAILY => [
                'longPrefix' => static::OPTION_DAILY,
                'flag' => true,
                'description' => 'Split result exports by day'
            ],

            static::OPTION_EXOTIC => [
                'longPrefix' => static::OPTION_EXOTIC,
                'prefix' => 'e',
                'flag' => true,
                'description' => 'Allows export of exotic characters',
                'defaultValue' => false,
                'cast' => 'boolean',
            ],

            static::OPTION_WITHOUT_TIMESTAMP => [
                'longPrefix' => static::OPTION_WITHOUT_TIMESTAMP,
                'prefix' => 'wt',
                'flag' => true,
                'description' => 'Setting this flag would mean that export file won\'t have timestamp postfix in filename',
                'defaultValue' => false,
                'cast' => 'boolean',
            ]

        ];
    }

    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Prints a help statement'
        ];
    }

    public function run(): Report
    {
        $exporter = $this->getConfiguredBookletExporter();

        $deliveries = $this->fetchDeliveries();
        $exporter->setDeliveries($deliveries);

        $touchedDeliveriesMessage = implode(PHP_EOL, array_map(static function ($d) {
            return $d->getUri() . '=>' . $d->getLabel();
        }, $deliveries));

        $report = Report::createInfo('Exporting deliveries:' . PHP_EOL . $touchedDeliveriesMessage);
        
        // -c = daily // --daily
        // -c = weekly // --weekly


        // Param 6: split export by day
        if (!$this->hasOption('daily')) {
            // fallback to simple export
            $exportReport = $bookletExporter->export();
        } else {
            $exportReport = $bookletExporter->dailyExport();
        }

        
        $report->add($exportReport);

        return $report;
    }

    protected function fetchDeliveries(): array
    {
        $deliveryOption = $this->getOption(static::OPTION_DELIVERY);

        if (is_null($deliveryOption) || strtolower($deliveryOption) === 'all') {
            return DeliveryAssemblyService::singleton()->getRootClass()->getInstances(true);
        }

        $explodedDeliveries = explode(',', $deliveryOption);
        foreach($explodedDeliveries as $delivery){
            $deliveries[] = $this->getResource($delivery);
        }

        return $deliveries ?? [];
    }

    protected function getConfiguredBookletExporter(): AllBookletsExport
    {
        /** @var AllBookletsExport $bookletExporter */
        $bookletExporter = $this->getServiceLocator()->get(AllBookletsExport::SERVICE_ID);

        $bookletExporter->setIdentifierStrategy($this->getOption(static::OPTION_STRATEGY));
        $bookletExporter->setAllowTimestampInFilename($this->isTimeStampNeeded());
        $bookletExporter->setPrefix($this->getOption(static::OPTION_PREFIX));
        $bookletExporter->setVariablePolicy(
            self::transformStringVariablePolicyIntoConstant(
                strtolower(
                    $this->getOption(static::OPTION_POLICY)
                )
            )
        );

        $variableBlacklist = $this->getOption(static::OPTION_BLACKLIST);
        if (!is_null($variableBlacklist)) {
            $bookletExporter->setVariableBlacklist(explode(',', $variableBlacklist));
        }

        if ($this->getOption(static::OPTION_RAW)) {
            $bookletExporter->addAlternateMissingDataEnconding(
                $bookletExporter->getOption(AllBookletsExport::NOT_RESPONDED_OPTION),
                ''
            );
        }

        if ($this->hasOption(static::OPTION_EXOTIC)) {
            $bookletExporter->setAllowExoticCharactersExport($this->getOption(static::OPTION_EXOTIC));
        }

        return $bookletExporter;
    }

    protected function showTime(): bool
    {
        return true;
    }

    private static function transformStringVariablePolicyIntoConstant(string $strVariablePolicy): int
    {
        switch ($strVariablePolicy) {
            case 'outcome':
                $variablePolicy = AllBookletsExport::VARIABLE_POLICY_OUTCOME;
                break;
            case 'response':
                $variablePolicy = AllBookletsExport::VARIABLE_POLICY_RESPONSE;
                break;
            default:
                $variablePolicy = AllBookletsExport::VARIABLE_POLICY_ALL;
                break;
        }
        
        return $variablePolicy;
    }

    private function isTimeStampNeeded(): bool
    {
        return ! $this->getOption(static::OPTION_WITHOUT_TIMESTAMP);
    }
}
