<?php
/**
 * Default config header created during install
 */

use oat\taoResultExports\model\export\AllBookletsExport;
return new AllBookletsExport([
        AllBookletsExport::NOT_ATTEMPTED_OPTION => 'Y',
        AllBookletsExport::NOT_REQUIRED_OPTION => 'Z',
        AllBookletsExport::NOT_RESPONDED_OPTION => 'W',
        AllBookletsExport::OPTION_NUMBER_OF_DAILY_EXPORT => 3
    ]
);