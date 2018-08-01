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

namespace oat\taoResultExports\scripts\install;

use oat\oatbox\extension\InstallAction;
use oat\oatbox\filesystem\FileSystemService;
use oat\taoResultExports\model\export\AllBookletsExport;
use common_report_Report as Report;

/**
 * Register the export directory
 *
 * @author Antoine Robin <antoine@taotesting.com>
 */
class CreateExportDirectory extends InstallAction
{
    /**
     * @param $params
     */
    public function __invoke($params)
    {
        /** @var FileSystemService $fsService */
        $fsService = $this->getServiceLocator()->get(FileSystemService::SERVICE_ID);
        if (!$fsService->hasDirectory(AllBookletsExport::FILESYSTEM_ID)) {
            $fsService->createFileSystem(AllBookletsExport::FILESYSTEM_ID, AllBookletsExport::FILESYSTEM_NAME);
            $this->registerService(FileSystemService::SERVICE_ID, $fsService);

            return Report::createSuccess('Filesystem : ' . AllBookletsExport::FILESYSTEM_ID . ' successfully created');
        }

        return Report::createInfo('Filesystem : ' . AllBookletsExport::FILESYSTEM_ID . ' already exists');
    }
}
