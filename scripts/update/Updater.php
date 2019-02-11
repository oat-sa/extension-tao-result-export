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

        $this->skip('0.1.0', '0.1.2');

        if ($this->isVersion('0.1.2') ){
            $bookletExporterService = $this->getServiceManager()->get(AllBookletsExport::SERVICE_ID);
            $vacbluary = [
                1,
                2,
                3,
                4,
                5,
                6,
                7,
                8,
                9,
                10,
                11,
                12,
                13,
                14,
                15,
                16,
                17,
                18,
                19,
                20,
                21,
                22,
                23,
                24,
                25,
                26,
                27,
                28,
                29,
                30,
                31,
                95,
                96,
                123,
                124,
                125,
                126,
                127,
                128,
                129,
                131,
                132,
                134,
                135,
                136,
                137,
                139,
                140,
                142,
                143,
                144,
                145,
                146,
                147,
                148,
                150,
                152,
                153,
                154,
                155,
                156,
                157,
                158,
                159,
                160,
                161,
                162,
                163,
                164,
                165,
                166,
                167,
                168,
                169,
                170,
                171,
                172,
                173,
                174,
                175,
                176,
                177,
                178,
                179,
                180,
                181,
                182,
                183,
                184,
                185,
                186,
                187,
                188,
                189,
                190,
                191,
                192,
                193,
                194,
                195,
                196,
                197,
                198,
                199,
                200,
                201,
                202,
                203,
                204,
                205,
                206,
                207,
                208,
                209,
                210,
                211,
                213,
                214,
                215,
                216,
                217,
                218,
                219,
                220,
                221,
                222,
                223,
                224,
                225,
                226,
                227,
                228,
                229,
                230,
                231,
                232,
                233,
                234,
                235,
                236,
                237,
                238,
                239,
                240,
                241,
                242,
                243,
                244,
                245,
                246,
                247,
                248,
                249,
                250,
                251,
                253,
                254,
                255,
            ];
            $bookletExporterService->setOption(AllBookletsExport::OPTION_EXOTIC_VOCABULARY, $vacbluary);
            $this->getServiceManager()->register(AllBookletsExport::SERVICE_ID, $bookletExporterService);
            $this->setVersion('0.2.0');
        }

        $this->skip('0.2.0', '0.3.0');
    }
}
