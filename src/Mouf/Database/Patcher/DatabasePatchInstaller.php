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
class DatabasePatchInstaller implements PackageInstallerInterface
{
    /**
     * (non-PHPdoc)
     * @see \Mouf\Installer\PackageInstallerInterface::install()
     * @param MoufManager $moufManager
     * @throws \Mouf\MoufException
     */
    public static function install(MoufManager $moufManager) {
        //Let's create an instance of DatabasePatchTable if not exist
        try {
            $databasePatchTable = $moufManager->get('databasePatchTable');
        } catch (MoufInstanceNotFoundException $e) {
            $databasePatchTableDescriptor = $moufManager->createInstance("Mouf\\Database\\Patcher\\DatabasePatchTable");
            $databasePatchTableDescriptor->setName('databasePatchTable');
            $databasePatchTableDescriptor->getProperty('tableName')->setValue('patches');

            $databasePatchTable = $moufManager->get('databasePatchTable');
        }

        // Let's create the table.
        $dbConnection = $moufManager->get('dbalConnection');
        /* @var $dbConnection Connection */

        $existingPatches = $moufManager->findInstances("Mouf\\Database\\Patcher\\DatabasePatch");
        $dbConnectionDescriptor = $moufManager->getInstanceDescriptor('dbalConnection');
        $databasePatchTableDescriptor = $moufManager->getInstanceDescriptor('databasePatchTable');
        foreach($existingPatches as $existingPatche){
            $patchIntance = $moufManager->getInstanceDescriptor($existingPatche);
            $patchIntance->getProperty('dbalConnection')->setValue($dbConnectionDescriptor);
            $patchIntance->getProperty('patchesTable')->setValue($databasePatchTableDescriptor);
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
            $config->getProperty('filterSchemaAssetsExpression')->setValue('/^(?!'.$databasePatchTable->getTableName().'$).*/');
        }

        $moufManager->rewriteMouf();
        //Create patches table
        self::createPatchTable($dbConnection, $databasePatchTable);
    }

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
        $patchDescriptor->getProperty('patchesTable')->setValue($moufManager->getInstanceDescriptor('databasePatchTable'));

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
        $patchDescriptor->getProperty('patchesTable')->setValue($moufManager->getInstanceDescriptor('databasePatchTable'));

        $upSqlFileName = 'database/up/'.date('YmdHis').'-patch.sql';
        $databasePatchClass = new ClassProxy('Mouf\\Database\\Patcher\\DatabasePatch', $selfedit == 'true');
        $result = $databasePatchClass->generateUpAndDownSqlPatches();
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
    public static function createPatchTable(Connection $dbalConnection, DatabasePatchTable $databasePatchTable) {
        // Note: the "patches" table is most of the time filtered out.
        // Lets disable filters.

        $filterSchemaAssetExpression = $dbalConnection->getConfiguration()->getFilterSchemaAssetsExpression();
        $dbalConnection->getConfiguration()->setFilterSchemaAssetsExpression(null);

        if(!$dbalConnection->getSchemaManager()->tablesExist(array($databasePatchTable->getTableName()))) {
            $sm = $dbalConnection->getSchemaManager();
            $table = new \Doctrine\DBAL\Schema\Table($databasePatchTable->getTableName());
            $table->addColumn('id', 'integer', array('autoincrement' => true));
            $table->addColumn('unique_name', 'string', array("length" => 255, 'customSchemaOptions' => array('unique' => true)));
            $table->addColumn('status', 'string', array("length" => 10));
            $table->addColumn('exec_date', 'datetime');
            $table->addColumn('error_message', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $sm->createTable($table);
        }

        $dbalConnection->getConfiguration()->setFilterSchemaAssetsExpression($filterSchemaAssetExpression);
    }
}
