<?php
/**
 * Copyright (c) 2019 Open Assessment Technologies, S.A.
 *
 */

namespace oat\taoResultExports\test\export\render;

use oat\tao\test\TaoPhpUnitTestRunner;
use oat\taoResultExports\model\export\renderer\StdOutRenderer;

class StdOutRendererTest extends TaoPhpUnitTestRunner
{
    public function testAddRowOnSuccess()
    {
        $stdOutRenderer = new StdOutRenderer();
        $this->assertTrue($stdOutRenderer->addRow([]));
    }
    public function testRenderOnSuccess()
    {
        $stdOutRenderer = new StdOutRenderer();
        $this->assertEquals('stdout', $stdOutRenderer->render());
    }
}
