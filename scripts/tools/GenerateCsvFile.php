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

use common_Exception;
use common_exception_Error;
use common_exception_NotFound;
use common_report_Report as Report;
use LengthException;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\taoDeliveryRdf\model\DeliveryAssemblyService;
use oat\taoResultExports\model\export\AllBookletsExport;
use qtism\data\storage\php\PhpStorageException;
use RuntimeException;

/**
 * Class GenerateCsvFile
 * @package oat\taoResultExports\scripts\tools
 *
 * Usage: sudo -u www-data php index.php 'oat\taoResultExports\scripts\tools\GenerateCsvFile' -s title --policy all -p myDelivery

 * todo: add field validation, especially with possible values
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
    public const OPTION_COVERAGE = 'coverage';
    public const OPTION_DAILY = 'daily';
    public const OPTION_EXOTIC = 'exotic';
    public const OPTION_WITHOUT_TIMESTAMP = 'without-timestamp';

    protected const OPTION_STRATEGY_VALUES = ['itemRef', 'identifier', 'title', 'label'];
    protected const OPTION_POLICY_VALUES = ['all', 'response', 'outcome'];
    protected const OPTION_COVERAGE_VALUES = [self::OPTION_DAILY];

    protected static function possibleValues(array $values): string
    {
        return sprintf('Possible values are: %s', implode('|', $values));
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
                    . static::possibleValues(static::OPTION_STRATEGY_VALUES)
            ],

            static::OPTION_POLICY => [
                'longPrefix' => static::OPTION_POLICY,
                'prefix' => 'p',
                'required' => true,
                'description' => 'Variables to extract. '
                    . static::possibleValues(static::OPTION_POLICY_VALUES)
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

            static::OPTION_COVERAGE => [
                'longPrefix' => static::OPTION_COVERAGE,
                'prefix' => 'c',
                'required' => false,
                'description' => 'Period for which results are selected. '
                    . static::possibleValues(static::OPTION_COVERAGE_VALUES)
            ],

            static::OPTION_DAILY => [
                'longPrefix' => static::OPTION_DAILY,
                'flag' => true,
                'description' => sprintf('DEPRECATED. Please, use %s option. Split result exports by day', static::OPTION_COVERAGE)
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

    /**
     * @return Report
     * @throws common_Exception
     * @throws common_exception_Error
     * @throws common_exception_NotFound
     * @throws PhpStorageException
     */
    public function run(): Report
    {
        $exporter = $this->getConfiguredBookletExporter();

        $deliveries = $this->fetchDeliveries();
        $exporter->setDeliveries($deliveries);

        $touchedDeliveriesMessage = implode(PHP_EOL, array_map(static function ($d) {
            return $d->getUri() . ' => ' . $d->getLabel();
        }, $deliveries));

        $report = Report::createInfo('Exporting deliveries:' . PHP_EOL . $touchedDeliveriesMessage);

        $coverage = $this->getCoverage();

        // Fallback to default export without eny restrictions
        if (empty($coverage)) {
            $report->add($exporter->export());

            return $report;
        }

        $method = sprintf('%sExport', $coverage);

        if (!method_exists($exporter, $method)) {
            throw new RuntimeException(
                sprintf(
                    'Implementation (method %s of class %s) for specific export not found',
                    $method,
                    get_class($exporter)
                )
            );
        }

        $report->add($exporter->$method());

        return $report;
    }

    protected function getCoverage()
    {
        if ($this->hasOption(static::OPTION_COVERAGE)) {
            return $this->getOption(static::OPTION_COVERAGE);
        }

        $coverageFlags = [];
        foreach (static::OPTION_COVERAGE_VALUES as $value) {
            if ($this->hasOption($value)) {
                $coverageFlags[] = $value;
            }
        }

        if (count($coverageFlags) > 1) {
            throw new LengthException('Only one coverage period allowed at the same time');
        }

        return reset($coverageFlags);
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
