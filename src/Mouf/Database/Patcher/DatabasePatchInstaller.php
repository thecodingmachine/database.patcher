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
use Mouf\InstanceProxy;
use Mouf\MoufManager;
use Mouf\UniqueIdService;

/**
 * This utility class is in charge of registering a new database patch in the patch system.
 * This class is designed to be used in a package installer, for instance when a package needs to create
 * tables in database.
 *
 * @author David Negrier <david@mouf-php.com>
 */
class DatabasePatchInstaller
{
    /**
     * Registers a database patch in the patch system.
     * Note: the patch will not be executed, only registered in "Awaiting" state.
     * The user will have to manually execute the patch.
     *
     * Note: if the patch already exists (if an instance name is "dbpatch.$uniqueName"), we will update this instance.
     *
     * @param MoufManager $moufManager
     * @param string      $uniqueName      Unique name for that patch
     * @param string      $description     The description of that patch
     * @param string      $upSqlFileName   The SQL file containing the patch, relative to ROOT_PATH. Should not start with /.
     * @param string      $downSqlFileName (optional) The SQL file containing the revert patch, relative to ROOT_PATH. Should not start with /.
     */
    public static function registerPatch(MoufManager $moufManager, $uniqueName, $description, $upSqlFileName, $downSqlFileName = null)
    {
        // First, let's find if this patch already exists... We assume that $uniqueName = "dbpatch.$instanceName".

        // If the patch already exists, we go in edit mode.
        if ($moufManager->has('dbpatch.'.$uniqueName)) {
            $patchDescriptor = $moufManager->getInstanceDescriptor('dbpatch.'.$uniqueName);
            $exists = true;
        } else {
            $patchDescriptor = $moufManager->createInstance("Mouf\\Database\\Patcher\\DatabasePatch");
            $patchDescriptor->setName('dbpatch.'.$uniqueName);
            $exists = false;
        }

        $patchDescriptor->getProperty('uniqueName')->setValue($uniqueName);
        $patchDescriptor->getProperty('description')->setValue($description);
        $patchDescriptor->getProperty('dbalConnection')->setValue($moufManager->getInstanceDescriptor('dbalConnection'));

        $patchDescriptor->getProperty('upSqlFile')->setValue($upSqlFileName);
        $patchDescriptor->getProperty('downSqlFile')->setValue($downSqlFileName);

        // Register the patch in the patchService.
        $patchManager = $moufManager->getInstanceDescriptor('patchService');
        if (!$exists) {
            $patchs = $patchManager->getProperty('patchs')->getValue();
            if ($patchs === null) {
                $patchs = array();
            }
            $patchs[] = $patchDescriptor;
            $patchManager->getProperty('patchs')->setValue($patchs);
        }
    }

    public static function generatePatch(MoufManager $moufManager, $description, $instanceName, $selfedit = 'false')
    {
        // First, let's find if this patch already exists... We assume that $uniqueName = "dbpatch.$instanceName".
        $uniqueName = UniqueIdService::getUniqueId().'-'.date('YmdHis').'-patch';
        // If the patch already exists, we go in edit mode.
        if ($moufManager->has('dbpatch.'.$uniqueName)) {
            $patchDescriptor = $moufManager->getInstanceDescriptor('dbpatch.'.$uniqueName);
            $exists = true;
        } else {
            $patchDescriptor = $moufManager->createInstance("Mouf\\Database\\Patcher\\DatabasePatch");
            $patchDescriptor->setName('dbpatch.'.$uniqueName);
            $exists = false;
        }

        $patchDescriptor->getProperty('uniqueName')->setValue($uniqueName);
        $patchDescriptor->getProperty('description')->setValue($description);
        $patchDescriptor->getProperty('dbalConnection')->setValue($moufManager->getInstanceDescriptor('dbalConnection'));

        $upSqlFileName = 'database/up/'.date('YmdHis').'-patch.sql';
        $databasePatchClass = new ClassProxy('Mouf\\Database\\Patcher\\DatabasePatch', $selfedit == 'true');
        $result = $databasePatchClass->generateUpAndDonwSqlPatches();
        if(isset($result['upPatch'][0]) && !empty($result['upPatch'][0])){
            $upSql = implode(";\n", $result['upPatch']).";\n";
            $downSql = implode(";\n", $result['downPatch']).";\n";
            $downSqlFileName = 'database/down/'.date('YmdHis').'-patch.sql';
        }

        // Let's create the directory
        $baseDirSqlFile = ROOT_PATH.'../../../';

        file_put_contents($baseDirSqlFile.'/'.$upSqlFileName, $upSql);
        file_put_contents($baseDirSqlFile.'/'.$downSqlFileName, $downSql);
        $patchDescriptor->getProperty('upSqlFile')->setValue($upSqlFileName);
        $patchDescriptor->getProperty('downSqlFile')->setValue($downSqlFileName);
        // Register the patch in the patchService.
        $patchManager = $moufManager->getInstanceDescriptor('patchService');
        if (!$exists) {
            $patchs = $patchManager->getProperty('patchs')->getValue();
            if ($patchs === null) {
                $patchs = array();
            }
            $patchs[] = $patchDescriptor;
            $patchManager->getProperty('patchs')->setValue($patchs);
        }
        $moufManager->rewriteMouf();
        // Now, let's mark this patch as "skipped".
        $patchService = new InstanceProxy('patchService');
        $patchService->skip($uniqueName);

    }

    /**
     *
     */
    public static function createPatchTable(Connection $dbalConnection) {
        if(!$dbalConnection->getSchemaManager()->tablesExist(array('patches'))){
            $sm = $dbalConnection->getSchemaManager();
            $table = new \Doctrine\DBAL\Schema\Table('patches');
            $table->addColumn('id', 'integer', array('autoincrement' => true));
            $table->addColumn('unique_name', 'string', array("length" => 255, 'customSchemaOptions' => array('unique' => true)));
            $table->addColumn('status', 'string', array("length" => 10));
            $table->addColumn('exec_date', 'datetime');
            $table->addColumn('error_message', 'text');
            $sm->createTable($table);
        }
    }
}
