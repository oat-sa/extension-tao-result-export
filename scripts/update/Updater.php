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

namespace oat\taoResultExports\scripts\update;

use oat\taoResultExports\model\export\AllBookletsExport;

/**
 * TAO Operations Extension Updater.
 * 
 * This class provides an implementation of the Generis
 * Extension Updater aiming at updating the TAO Operations Extension.
 */
class Updater extends \common_ext_ExtensionUpdater
{
    /**
     * Update the Extension
     * 
     * Calling this method will update the TAO Operations Extension from
     * an $initialVersion to a target version.
     * 
     * @param string $initialVersion
     * @see \common_ext_ExtensionUpdater
     * @return void
     *
     * @throws \Exception
     */
    public function update($initialVersion)
    {
        if ($this->isVersion('0.0.1')) {
            $bookletExporterService = $this->getServiceManager()->get(AllBookletsExport::SERVICE_ID);
            $bookletExporterService->setOption(AllBookletsExport::OPTION_NUMBER_OF_DAILY_EXPORT, 3);
            $this->getServiceManager()->register(AllBookletsExport::SERVICE_ID, $bookletExporterService);
            $this->setVersion('0.1.0');
        }

        $this->skip('0.1.0', '0.2.0');
    }
}
