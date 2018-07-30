<?php
/**
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA.
 */
use oat\taoResultExports\scripts\install\CreateExportDirectory;

return [
    'name' => 'taoResultsExports',
    'label' => 'Export Tools',
    'description' => 'Extension providing tools dedicated to operations.',
    'license' => 'GPL-2.0',
    'version' => '0.0.1',
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
            CreateExportDirectory::class
        ]
    ],
];
