<?php
/**
 * Copyright (c) 2019 Open Assessment Technologies, S.A.
 *
 */

namespace oat\taoResultExports\test\integration\scripts\tools;

use common_report_Report;
use oat\generis\test\GenerisTestCase;
use oat\taoResultExports\scripts\tools\ExportLoginCommand;

class ExportLoginCommandTest extends GenerisTestCase
{
    /**
     * @param array $params
     *
     * @dataProvider provideTestCaseForFailure
     */
    public function testInvokeForFailure(array $params = [])
    {
        $exportLogin = new ExportLoginCommand();
        $report = $exportLogin->__invoke($params);

        $this->assertTrue(
            ($report->getType() === common_report_Report::TYPE_ERROR)
            ||
            $report->containsError()
        );
    }

    /**
     * @return array
     */
    public function provideTestCaseForFailure()
    {
        return [
            'emptyOptions' => [
                []
            ],
            'missingDelivery' => [
                ['delivery' => 'a']
            ],
        ];
    }
}
