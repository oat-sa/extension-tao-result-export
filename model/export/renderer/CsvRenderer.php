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

namespace oat\taoResultExports\model\export\renderer;

use oat\oatbox\filesystem\File;
use oat\oatbox\filesystem\FileSystemService;
use oat\taoResultExports\model\export\AllBookletsExport;

class CsvRenderer implements RendererInterface
{
    const SHORT_NAME = 'csv';

    /** @var FileSystemService */
    private $fileSystemService;

    /** @var string  */
    private $prefix;

    /** @var bool   On FALSE there will not be timestamp in the filename */
    private $allowTimestampInFilename = true;

    /** @var resource The resource to handle the temporary file */
    protected $tempExportFile;

    /**
     * CsvRenderer constructor.
     *
     * @param FileSystemService $fileSystemService
     * @param string $prefix
     * @param bool $allowTimestampInFilename
     */
    public function __construct(FileSystemService $fileSystemService, $prefix = '', $allowTimestampInFilename = true)
    {
        $this->setFileSystemService($fileSystemService);
        $this->setAllowTimestampInFilename($allowTimestampInFilename);
        $this->setPrefix($prefix);
        $this->initializeTempFile();
    }

    private function setFileSystemService(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
    }

    private function setPrefix($prefix)
    {
        if ($this->allowTimestampInFilename) {
            $this->prefix = rtrim($prefix,'_') . '_';
        } else {
            $this->prefix = rtrim($prefix,'_');
        }

        return $this;
    }

    private function setAllowTimestampInFilename($allowTimestamp = true)
    {
        $this->allowTimestampInFilename = $allowTimestamp;

        return $this;
    }

    private function initializeTempFile()
    {
        $this->tempExportFile = fopen($this->getTempFilePath(), 'w+');
    }

    /**
     * @inheritdoc
     */
    public function addRow(array $row)
    {
        return fputcsv($this->tempExportFile, $row) !== false;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        // Write as stream and close temporary file.
        $exportFile = $this->createExportFile();
        rewind($this->tempExportFile);
        $exportFile->write($this->tempExportFile);

        unlink($this->getFilePathFromResource($this->tempExportFile));

        return $exportFile->getPrefix();
    }

    /**
     * Returns the file path from resource.
     *
     * @param resource $file
     *
     * @return string
     */
    private function getFilePathFromResource($file)
    {
        $metaData = stream_get_meta_data($file);

        return $metaData['uri'];
    }

    /**
     * Create the filename for the export
     *
     * If there is a daily mode then add the date as subfolder
     *
     * @return File
     */
    private function createExportFile()
    {
        /** @var File $file */
        $file = $this->fileSystemService
            ->getDirectory(AllBookletsExport::FILESYSTEM_ID)
            ->getFile($this->getFilePath());

        if ($this->allowTimestampInFilename === false && $file->exists()) {
            $file->delete();
        }

        return $file;
    }

    /**
     * Returns the temp file path.
     *
     * @param string $prefix
     *
     * @return string
     */
    private function getTempFilePath($prefix = 'invalsiexport')
    {
        return tempnam(sys_get_temp_dir(), $prefix);
    }

    /**
     * Returns the normal file path.
     *
     * @return string
     */
    private function getFilePath()
    {
        $directory = date('Y_m_d') . DIRECTORY_SEPARATOR . gethostname() . DIRECTORY_SEPARATOR;
        $postfix = $this->allowTimestampInFilename ? date('His') : '';

        return $directory . $this->prefix . $postfix . '.csv';
    }
}
