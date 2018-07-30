<?php
/**
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA.
 */

namespace oat\taoOperations\scripts\update;

use oat\tao\model\accessControl\func\AclProxy;
use oat\tao\model\accessControl\func\AccessRule;

/**
 * TAO Operations Extension Updater.
 * 
 * This class provides an implementation of the Generis
 * Extension Updater aiming at updating the TAO Operations Extension.
 */
class Updater extends \common_ext_ExtensionUpdater {

    /**
     * Update the Extension
     * 
     * Calling this method will update the TAO Operations Extension from
     * an $initialVersion to a target version.
     * 
     * @param string $initialVersion
     * @see \common_ext_ExtensionUpdater
     * @return void
     */
    public function update($initialVersion) {
        

    }
}
