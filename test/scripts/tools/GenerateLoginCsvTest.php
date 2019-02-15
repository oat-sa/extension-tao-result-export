<?php
/**
 * Copyright (c) 2016 Open Assessment Technologies, S.A.
 *
 */

namespace oat\taoResultExports\test;

use oat\tao\test\TaoPhpUnitTestRunner;
use oat\taoResultExports\model\export\AllBookletsExportUtils;
use qtism\runtime\common\Container;
use qtism\runtime\common\MultipleContainer;
use qtism\common\enums\BaseType;
use qtism\common\datatypes\QtiPair;

class AllBookletsExportUtilsTest extends TaoPhpUnitTestRunner
{
    /**
     * @dataProvider formatDurationProvider
     */
    public function testFormatDuration($strDuration, $expected)
    {
        $this->assertEquals($expected, AllBookletsExportUtils::formatDuration($strDuration));
    }
    
    public function formatDurationProvider()
    {
        return [
            ['PT5S', '5.000'],
            ['PT1M', '60.000'],
            ['PT0S', '0.000'],
            ['PT5.345678S', '5.346'],
            ['PT0.001434S', '0.001'],
            ['PT0.000000S', '0.000'],
            ['PT1M73.022223S', '133.022'],
            
            ['foobarbaz', false],
            [null, false],
            [new \stdClass(), false]
        ];
    }
    
    /**
     * @dataProvider matchSetIndexProvider
     */
    public function testMatchSetIndex($identifier, array $matchSets, $expected)
    {
        $this->assertSame($expected, AllBookletsExportUtils::matchSetIndex($identifier, $matchSets));
    }
    
    public function matchSetIndexProvider()
    {
        return [
            ['identifier1', [['identifier1', 'identifier2', 'identifier3']], 0],
            ['identifier2', [['identifier1', 'identifier2', 'identifier3']], 1],
            ['identifier3', [['identifier1', 'identifier2', 'identifier3']], 2],
            ['identifier4', [['identifier1', 'identifier2', 'identifier3']], false],
            ['identifier1', [['identifier1', 'identifier2', 'identifier3'], ['identifier4', 'identifier5', 'identifier6']], 0],
            ['identifier2', [['identifier1', 'identifier2', 'identifier3'], ['identifier4', 'identifier5', 'identifier6']], 1],
            ['identifier3', [['identifier1', 'identifier2', 'identifier3'], ['identifier4', 'identifier5', 'identifier6']], 2],
            ['identifier4', [['identifier1', 'identifier2', 'identifier3'], ['identifier4', 'identifier5', 'identifier6']], 0],
            ['identifier5', [['identifier1', 'identifier2', 'identifier3'], ['identifier4', 'identifier5', 'identifier6']], 1],
            ['identifier6', [['identifier1', 'identifier2', 'identifier3'], ['identifier4', 'identifier5', 'identifier6']], 2]
        ];
    }
}
