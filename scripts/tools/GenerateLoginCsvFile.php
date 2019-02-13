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
 * Copyright (c) 2019 (original work) Open Assessment Technologies SA
 *
 */

namespace oat\taoResultExports\scripts\tools;


use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\taoResultExports\model\export\LoginExport;

/**
 * Class GenerateLoginCsvFile
 * @package oat\taoResultExports\scripts\tools
 *
 * usage : sudo -u www-data php index.php 'oat\taoResultExports\scripts\tools\GenerateLoginCsvFile' -p myDelivery
 */
class GenerateLoginCsvFile extends ScriptAction
{
    use OntologyAwareTrait;

    protected function provideDescription()
    {
        return 'TAO Results - generate Csv of logins';
    }

    protected function provideOptions()
    {
        return [
            'delivery' => [
                'prefix'      => 'd',
                'longPrefix'  => 'delivery',
                'required'    => true,
                'description' => 'List of deliveries to export (separated by comma)',
            ],
            'withHeaders' => [
                'prefix'       => 'wh',
                'longPrefix'   => 'with-headers',
                'flag'         => true,
                'description'  => 'Export csv with headers',
                'defaultValue' => false,
                'cast'         => 'boolean',
            ],
            'withoutTimestamp' => [
                'longPrefix'    => 'without-timestamp',
                'prefix'        => 'wt',
                'flag'          => true,
                'description'   => 'Setting this flag would mean that export file won\'t have timestamp postfix in filename',
                'defaultValue'  => false,
                'cast'          => 'boolean',
            ],
        ];
    }

    protected function provideUsage()
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Prints a help statement',
        ];
    }

    public function run()
    {
        $prefix = $this->hasOption('prefix')
            ? $this->getOption('prefix')
            : 'login';

        $deliveries = $this->getOption('delivery');
        $deliveryResources = [];
        foreach (explode(',', $deliveries) as $delivery){
            $deliveryResources[] = $this->getResource($delivery);
        }

        /** @var LoginExport $loginExporter */
        $loginExporter = $this->getServiceLocator()->get(LoginExport::SERVICE_ID);
        $loginExporter->setDeliveries($deliveryResources)
            ->setAllowTimestampInFilename($this->isTimestampNeeded())
            ->setWithHeaders($this->areHeadersNeeded())
            ->setPrefix($prefix);

        $exportReport = $loginExporter->export();

        $report = new \common_report_Report(
            \common_report_Report::TYPE_INFO,
            'Exporting deliveries : '. PHP_EOL . implode(PHP_EOL, array_map(function($d) {
                return $d->getUri() . ' => ' . $d->getLabel();
            }, $deliveryResources))
        );

        $report->add($exportReport);

        return $report;
    }

    protected function formatTime($s)
    {
        $h = floor($s / 3600);
        $m = floor(($s / 60) % 60);
        $s = $s % 60;

        return "${h}h ${m}m ${s}s";
    }

    protected function showTime()
    {
        return true;
    }

    private function isTimestampNeeded()
    {
        $withoutTimestamp = $this->getOption('withoutTimestamp', false);

        return !(bool)$withoutTimestamp;
    }

    private function areHeadersNeeded()
    {
        $withHeaders = $this->getOption('withHeaders', false);

        return (bool)$withHeaders === true;
    }
}
