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

use Doctrine\DBAL\Schema\Schema;
use Mouf\Utils\Patcher\PatchInterface;
use Mouf\Utils\Patcher\PatchException;
use Mouf\MoufManager;
use Mouf\Utils\Patcher\PatchType;
use Mouf\Validator\MoufStaticValidatorInterface;
use Mouf\Validator\MoufValidatorResult;

/**
 * Classes implementing this interface reprensent patches that can be applied on the application.
 *
 * @author David Negrier <david@mouf-php.com>
 */
class DatabasePatch extends AbstractDatabasePatch implements MoufStaticValidatorInterface
{
    private $uniqueName;
    private $upSqlFile;
    private $downSqlFile;
    private $description;

    /**
     * @param PatchConnection $patchConnection  The connection that will be used to run the patch.
     * @param string          $uniqueName       The unique name for this patch.
     * @param string          $upSqlFile
     * @param string          $downSqlFile
     * @param string          $description      The description for this patch.
     */
    public function __construct(PatchConnection $patchConnection = null, $uniqueName = null, $upSqlFile = null, $downSqlFile = null, $description = null, PatchType $patchType = null)
    {
        parent::__construct($patchConnection, $patchType);
        $this->uniqueName = $uniqueName;
        $this->upSqlFile = $upSqlFile;
        $this->downSqlFile = $downSqlFile;
        $this->description = $description;
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::apply()
     */
    public function apply(): void
    {
        $this->createPatchesTable();

        // Let's run the patch.
        try {
            if (!file_exists(ROOT_PATH.$this->upSqlFile)) {
                throw new PatchException("An error occured while applying patch '".$this->getUniqueName()."': the file '".$this->upSqlFile."' cannot be found.");
            }
            $this->executeSqlFile(ROOT_PATH.$this->upSqlFile);
            $this->saveDbSchema();
        } catch (\Exception $e) {
            // On error, let's mark this in database.
            $this->savePatch(PatchInterface::STATUS_ERROR, $e->getMessage());
            throw $e;
        }
        $this->savePatch(PatchInterface::STATUS_APPLIED, null);
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::revert()
     */
    public function revert(): void
    {
        $this->createPatchesTable();

        // Let's run the patch.
        try {
            if (!file_exists(ROOT_PATH.$this->downSqlFile)) {
                throw new PatchException("An error occured while applying patch '".$this->getUniqueName()."': the file '".$this->downSqlFile."' cannot be found.");
            }
            $this->executeSqlFile(ROOT_PATH.$this->downSqlFile);
            $this->saveDbSchema();
        } catch (\Exception $e) {
            // On error, let's mark this in database.
            $this->savePatch(PatchInterface::STATUS_ERROR, $e->getMessage());
            throw $e;
        }
        $this->savePatch(PatchInterface::STATUS_AWAITING, null);
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::canRevert()
     */
    public function canRevert(): bool
    {
        return !empty($this->downSqlFile);
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::getUniqueName()
     */
    public function getUniqueName(): string
    {
        return $this->uniqueName;
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::getDescription()
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Executes the given SQL file.
     * Throws an exception on error.
     *
     * Returns the number of statements executed.
     *
     * @param string $file The SQL filename
     */
    private function executeSqlFile($file)
    {
        set_time_limit(0);

        $nb_statements = 0;

        if (is_file($file) === true) {
            $fpt = fopen($file, 'r');

            if (is_resource($fpt) === true) {
                $query = array();

                while (feof($fpt) === false) {
                    $query[] = fgets($fpt);

                    if (preg_match('~'.preg_quote(';', '~').'\s*$~iS', end($query)) === 1) {
                        $query = trim(implode('', $query));

                        // Let's trim the ";" as they are not allowed by some databases (Oracle in particular)
                        $query = trim($query, ';');

                        try {
                            $this->getConnection()->exec($query);
                        } catch (\Exception $e) {
                            throw new \Exception('An error occurred while executing request: '.$query.' --- Error message: '.$e->getMessage(), 0, $e);
                        }
                        $nb_statements++;
                    }

                    if (is_string($query) === true) {
                        $query = array();
                    }
                }

                fclose($fpt);

                return $nb_statements;
            } else {
                throw new \Exception('Can not open file: '.$file);
            }
        } else {
            throw new \Exception('Can not find file: '.$file);
        }
    }

    /* (non-PHPdoc)
     * @see \Mouf\Utils\Patcher\PatchInterface::getEditUrl()
     */
    public function getEditUrl(): ?string
    {
        return 'dbpatch/?patchInstanceName='.urlencode(MoufManager::getMoufManager()->findInstanceName($this));
    }

    /**
     * Compare the current schema of your database with the old one, and create an up and down sql patch.
     *
     * @return array
     */
    public static function generateUpAndDownSqlPatches()
    {
        $result = array();
        $fileName = __DIR__.'/../../../../generated/schema';
        $dbalConnection  = \Mouf::getDbalConnection();
        if (file_exists($fileName)) {
            $oldSchema = unserialize(file_get_contents($fileName));
        } else {
            $oldSchema = new Schema();
        }
        $currentSchema = $dbalConnection->getSchemaManager()->createSchema();
        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $schemaDiffUp = $comparator->compare($oldSchema, $currentSchema);
        $schemaDiffDown = $comparator->compare($currentSchema, $oldSchema);
        $result['upPatch'] = $schemaDiffUp->toSql($dbalConnection->getDatabasePlatform()); // queries to get from one to another schema.
        $result['downPatch'] = $schemaDiffDown->toSql($dbalConnection->getDatabasePlatform()); // queries to get from one to another schema.
        return $result;
    }

    /**
     * Runs the validation of the class.
     * Returns a MoufValidatorResult explaining the result.
     *
     * @return MoufValidatorResult
     */
    public static function validateClass()
    {
        $fileName = __DIR__.'/../../../../generated/schema';
        if (!file_exists($fileName)) {
            return new MoufValidatorResult(MoufValidatorResult::SUCCESS, "<strong>Database Patcher</strong>: You haven't generated yet a patch on the database model from this computer");
        }

        $result = self::generateUpAndDownSqlPatches();
        if ($result['upPatch']) {
            return new MoufValidatorResult(MoufValidatorResult::WARN, '<strong>Database Patcher</strong>: Your database model has been modified, <a href="'.ROOT_URL.'vendor/mouf/mouf/dbpatch/?name=patchService" class="btn btn-large btn-success patch-run-all"><i class="icon-arrow-right icon-white"></i>please register a new patch.</a>');
        } else {
            return new MoufValidatorResult(MoufValidatorResult::SUCCESS, "<strong>Database Patcher</strong>: Your database model hasn't been modified");
        }
    }
}
