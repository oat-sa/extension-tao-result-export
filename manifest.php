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
use oat\taoResultExports\scripts\install\CreateExportDirectory;
use oat\taoResultExports\scripts\install\RegisterExportServices;

return [
    'name' => 'taoResultExports',
    'label' => 'Export Tools',
    'description' => 'Extension providing tools dedicated to operations.',
    'license' => 'GPL-2.0',
    'version' => '0.5.0',
    'author' => 'Open Assessment Technologies',
    'requires' => [
        'generis' => '>=6.5.1',
        'funcAcl' => '>=2.9.0',
        'taoQtiTest' => '>=10.11.0',
        'taoDeliveryRdf' => '>=1.0.0',
        'tao' => '>=12.21.4'
    ],
    'acl' => [
    ],
    'routes' => [
    ],
    'update' => 'oat\\taoResultExports\\scripts\\update\\Updater',
    'install' => [
        'php'	=> [
            CreateExportDirectory::class,
            RegisterExportServices::class,
        ]
    ],
];
