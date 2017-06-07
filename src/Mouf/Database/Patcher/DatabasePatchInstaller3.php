<?php

/*
 Copyright (C) 2013 David Négrier - THE CODING MACHINE

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Mouf\Database\Patcher;

use Doctrine\DBAL\Connection;
use Mouf\ClassProxy;
use Mouf\Installer\PackageInstallerInterface;
use Mouf\InstanceProxy;
use Mouf\MoufInstanceNotFoundException;
use Mouf\MoufManager;
use Mouf\UniqueIdService;

/**
 * This utility class is in charge of registering a new database patch in the patch system.
 * This class is designed to be used in a package installer, for instance when a package needs to create
 * tables in database.
 *
 * @author David Negrier <david@mouf-php.com>
 */
class DatabasePatchInstaller3 implements PackageInstallerInterface
{
    /**
     * (non-PHPdoc)
     * @see \Mouf\Installer\PackageInstallerInterface::install()
     * @param MoufManager $moufManager
     * @throws \Mouf\MoufException
     */
    public static function install(MoufManager $moufManager) {
        // Let's get the database connection descriptor
        $dbConnectionDescriptor = $moufManager->getInstanceDescriptor('dbalConnection');

        //Let's get the instance of PatchConnection or create it if not exist
        if ($moufManager->has('patchConnection')) {
            $patchConnection = $moufManager->get('patchConnection');
            $patchConnectionDescriptor = $moufManager->getInstanceDescriptor('patchConnection');
        } else {
            $patchConnectionDescriptor = $moufManager->createInstance("Mouf\\Database\\Patcher\\PatchConnection");
            $patchConnectionDescriptor->setName('patchConnection');
            $patchConnectionDescriptor->getProperty('tableName')->setValue('patches');
            $patchConnectionDescriptor->getProperty('dbalConnection')->setValue($dbConnectionDescriptor);

            $patchConnection = $moufManager->get('patchConnection');
        }

        $existingPatches = $moufManager->findInstances("Mouf\\Database\\Patcher\\DatabasePatch");
        foreach($existingPatches as $existingPatch) {
            $patchInstance = $moufManager->getInstanceDescriptor($existingPatch);
            $patchInstance->getProperty('patchConnection')->setValue($patchConnectionDescriptor);
        }

        $patchServiceDescriptor = $moufManager->getInstanceDescriptor('patchService');
        if ($patchServiceDescriptor->getProperty('listeners')->getValue() === null) {
            $patchServiceDescriptor->getProperty('listeners')->setValue([ $patchConnectionDescriptor ]);
        }

        // Finally, let's change the dbalConnection configuration to add an ignore rule on the "patches" table.
        $configArgument = $dbConnectionDescriptor->getConstructorArgumentProperty('config');
        $config = $configArgument->getValue();
        if ($config === null) {
            $config = $moufManager->createInstance("Doctrine\\DBAL\\Configuration");
            $config->setName('doctrineDbalConfiguration');

            $configArgument->setValue($config);
        }

        if ($config->getProperty('filterSchemaAssetsExpression')->getValue() === null) {
            $config->getProperty('filterSchemaAssetsExpression')->setValue('/^(?!'.$patchConnection->getTableName().'$).*/');
        }

        $moufManager->rewriteMouf();
        //Create patches table
        DatabasePatchInstaller::createPatchTable($patchConnection);
    }

}
