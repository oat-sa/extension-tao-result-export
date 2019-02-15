<?php
/**
 * Copyright (c) 2019 Open Assessment Technologies, S.A.
 *
 */

namespace oat\taoResultExports\test\unit\scripts\tools;

use oat\generis\test\integration\tools\InvokeMethodTrait;
use oat\generis\test\TestCase;
use oat\oatbox\filesystem\FileSystemService;
use oat\taoResultExports\model\export\renderer\CsvRenderer;
use oat\taoResultExports\model\export\renderer\StdOutRenderer;
use oat\taoResultExports\scripts\tools\ExportLoginCommand;

class ExportLoginCommandTest extends TestCase
{
    use InvokeMethodTrait;

    /**
     * @param bool $expected
     * @param mixed $withHeader
     *
     * @throws \ReflectionException
     *
     * @dataProvider provideTestCaseForWithHeaders
     */
    public function testWithHeaders($expected, $withHeader)
    {
        $exportLogin = $this->createPartialMock(ExportLoginCommand::class, ['getOption']);
        $exportLogin->expects($this->once())
            ->method('getOption')
            ->willReturn($withHeader);

        $this->assertEquals(
            $expected,
            $this->invokeMethod($exportLogin, 'areHeadersNeeded')
        );
    }

    public function provideTestCaseForWithHeaders()
    {
        return [
            'empty' => [
                false,
                null,
            ],
            'flagPresented' => [
                true,
                true,
            ],
            'flagCorrupted' => [
                false,
                false,
            ],
            'flagMoreCorrupted' => [
                false,
                'fdf',
            ],
        ];
    }

    /**
     * @param bool $expected
     * @param mixed $withoutTimestamp
     *
     * @throws \ReflectionException
     *
     * @dataProvider provideTestCaseForWithoutTimestamp
     */
    public function testWithoutTimestamp($expected, $withoutTimestamp)
    {
        $exportLogin = $this->createPartialMock(ExportLoginCommand::class, ['getOption']);
        $exportLogin->expects($this->once())
            ->method('getOption')
            ->willReturn($withoutTimestamp);

        $this->assertEquals(
            $expected,
            $this->invokeMethod($exportLogin, 'isTimestampNeeded')
        );
    }

    public function provideTestCaseForWithoutTimestamp()
    {
        return [
            'empty' => [
                true,
                null,
            ],
            'flagPresented' => [
                false,
                true,
            ],
            'flagCorrupted' => [
                true,
                false,
            ],
            'flagMoreCorrupted' => [
                true,
                'fdf',
            ],
        ];
    }

    /**
     * @param string $expected
     * @param mixed $prefix
     *
     * @throws \ReflectionException
     *
     * @dataProvider provideTestCaseForGetPrefix
     */
    public function testGetPrefix($expected, $prefix)
    {
        $exportLogin = $this->createPartialMock(ExportLoginCommand::class, ['hasOption', 'getOption']);
        $exportLogin->expects($this->once())
            ->method('hasOption')
            ->willReturn($prefix !== null);
        $exportLogin->expects($this->atMost(1))
            ->method('getOption')
            ->willReturn($prefix);

        $this->assertEquals(
            $expected,
            $this->invokeMethod($exportLogin, 'getPrefix')
        );
    }

    public function provideTestCaseForGetPrefix()
    {
        return [
            'empty' => [
                'login',
                null,
            ],
            'string' => [
                'randomString',
                'randomString',
            ],
        ];
    }

    /**
     * @param string $expected
     * @param string $rendererName
     *
     * @throws \ReflectionException
     *
     * @dataProvider provideTestCaseForGetRenderer
     */
    public function testGetRenderer($expected, $rendererName)
    {
        $exportLogin = $this->createPartialMock(
            ExportLoginCommand::class,
            ['getOption', 'getFileSystemService', 'getPrefix', 'isTimestampNeeded']
        );
        $exportLogin->expects($this->once())
            ->method('getOption')
            ->willReturn($rendererName);
        $exportLogin->expects($this->atMost(1))
            ->method('getFileSystemService')
            ->willReturn($this->createMock(FileSystemService::class));
        $exportLogin->expects($this->atMost(1))
            ->method('getPrefix')
            ->willReturn('nothing');
        $exportLogin->expects($this->atMost(1))
            ->method('isTimestampNeeded')
            ->willReturn(true);

        $this->assertInstanceOf(
            $expected,
            $this->invokeMethod($exportLogin, 'getRenderer')
        );
    }

    public function provideTestCaseForGetRenderer()
    {
        return [
            'empty' => [
                CsvRenderer::class,
                null,
            ],
            'csv' => [
                CsvRenderer::class,
                'csv',
            ],
            'stdout' => [
                StdOutRenderer::class,
                'stdout',
            ],
        ];
    }
}
