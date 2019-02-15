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


use core_kernel_classes_Resource;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\filesystem\FileSystemService;
use oat\taoResultExports\model\export\LoginExport;
use oat\taoResultExports\model\export\renderer\CsvRenderer;
use oat\taoResultExports\model\export\renderer\RendererInterface;
use oat\taoResultExports\model\export\renderer\StdOutRenderer;

/**
 * Class GenerateLoginCsvFile
 * @package oat\taoResultExports\scripts\tools
 *
 * usage : sudo -u www-data php index.php 'oat\taoResultExports\scripts\tools\GenerateLoginCsvFile' -p myDelivery
 */
class GenerateLoginCsvFile extends ScriptAction
{
    use OntologyAwareTrait;

    private $availableRenderers = [
        CsvRenderer::SHORT_NAME,
        StdOutRenderer::SHORT_NAME,
    ];

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
            'renderer' => [
                'prefix'       => 'r',
                'longPrefix'   => 'renderer',
                'description'  => 'Renderer of the report [' . implode(',', $this->availableRenderers) . ']',
                'defaultValue' => CsvRenderer::SHORT_NAME,
                'cast'         => 'string',
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
        $deliveryResources = $this->getDeliveryResources();

        /** @var LoginExport $loginExporter */
        $loginExporter = $this->getServiceLocator()->get(LoginExport::SERVICE_ID);
        $loginExporter->setDeliveries($deliveryResources)
            ->setRenderer($this->getRenderer())
            ->setWithHeaders($this->areHeadersNeeded());

        $report = new \common_report_Report(
            \common_report_Report::TYPE_INFO,
            'Exporting deliveries : '. PHP_EOL . implode(PHP_EOL, array_map(function($d) {
                return $d->getUri() . ' => ' . $d->getLabel();
            }, $deliveryResources))
        );

        // Do the export
        $report->add($loginExporter->export());

        return $report;
    }

    /**
     * @return core_kernel_classes_Resource[]
     */
    private function getDeliveryResources()
    {
        $deliveries = $this->getOption('delivery');
        $deliveryResources = [];
        foreach (explode(',', $deliveries) as $delivery){
            $deliveryResources[] = $this->getResource($delivery);
        }

        return $deliveryResources;
    }

    /**
     * @return RendererInterface
     */
    private function getRenderer()
    {
        $rendererName = $this->getOption('renderer');
        if (!in_array($rendererName, $this->availableRenderers)) {
            $rendererName = reset($this->availableRenderers);
        }

        return $this->buildRenderer($rendererName);
    }

    /**
     * @param $rendererName
     *
     * @return RendererInterface
     *
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    private function buildRenderer($rendererName)
    {
        switch ($rendererName) {
            case CsvRenderer::SHORT_NAME:
                return new CsvRenderer(
                    $this->getServiceLocator()->get(FileSystemService::SERVICE_ID),
                    $this->getPrefix(),
                    $this->isTimestampNeeded()
                );
                break;

            case StdOutRenderer::SHORT_NAME:
            default:
                return new StdOutRenderer();
        }
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

    private function getPrefix()
    {
        return $this->hasOption('prefix')
            ? $this->getOption('prefix')
            : 'login';
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
