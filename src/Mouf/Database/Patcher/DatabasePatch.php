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

use Mouf\Utils\Patcher\PatchInterface;
use Mouf\Database\DBConnection\ConnectionInterface;
use Mouf\Database\DBConnection\DBConnectionException;
use Mouf\Utils\Patcher\PatchException;
use Mouf\MoufManager;

/**
 * Classes implementing this interface reprensent patches that can be applied on the application.
 * 
 * @author David Negrier <david@mouf-php.com>
 */
class DatabasePatch implements PatchInterface {

	private $uniqueName;
	private $upSqlFile;
	private $downSqlFile;
	private $description;
	
	/**
	 * 
	 * @var ConnectionInterface
	 */
	private $dbConnection;

	/**
	 * 
	 * @param ConnectionInterface $dbConnection The DBConnection that will be used to run the patch.
	 * @param string $uniqueName The unique name for this patch.
	 * @param string $upSqlFile
	 * @param string $downSqlFile
	 * @param string $description The description for this patch.
	 */
	public function __construct(ConnectionInterface $dbConnection = null, $uniqueName = null, $upSqlFile = null, $downSqlFile = null, $description = null) {
		$this->dbConnection = $dbConnection;
		$this->uniqueName = $uniqueName;
		$this->upSqlFile = $upSqlFile;
		$this->downSqlFile = $downSqlFile;
		$this->description = $description;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::apply()
	 */
	public function apply() {
		$this->createPatchesTable();
		
		// Let's run the patch.
		try {
			if (!file_exists(ROOT_PATH.$this->upSqlFile)) {
				throw new PatchException("An error occured while applying patch '".$this->getUniqueName()."': the file '".$this->upSqlFile."' cannot be found.");
			}
			$this->executeSqlFile(ROOT_PATH.$this->upSqlFile);
		} catch (\Exception $e) {
			// On error, let's mark this in database.
			$this->savePatch(PatchInterface::STATUS_ERROR, $e->getMessage());
			throw $e;
		}
		$this->savePatch(PatchInterface::STATUS_APPLIED, null);
	}

	/* (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::skip()
	 */
	public function skip() {
		$this->createPatchesTable();
		$this->savePatch(PatchInterface::STATUS_SKIPPED, null);	
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::revert()
	 */
	public function revert() {
		$this->createPatchesTable();
		
		// Let's run the patch.
		try {
			if (!file_exists(ROOT_PATH.$this->downSqlFile)) {
				throw new PatchException("An error occured while applying patch '".$this->getUniqueName()."': the file '".$this->downSqlFile."' cannot be found.");
			}
			$this->executeSqlFile(ROOT_PATH.$this->downSqlFile);
		} catch (\Exception $e) {
			// On error, let's mark this in database.
			$this->savePatch(PatchInterface::STATUS_ERROR, $e->getMessage());
			throw $e;
		}
		$this->savePatch(PatchInterface::STATUS_AWAITING, null);
	}
	
	/**
	 * Creates the 'patches' table if it does not exists yet.
	 * @throws \Exception
	 */
	private function createPatchesTable() {
		$this->checkDbConnection();
		// First, let's check that the patches table exists and let's create the table if it does not.
		$tables = $this->dbConnection->getListOfTables();
		if (array_search('patches', $tables) === false) {
			// Let's create the table.
			$result = $this->executeSqlFile(__DIR__.'/../../../../database/create_patches_table.sql');
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::canRevert()
	 */
	public function canRevert() {
		return !empty($this->downSqlFile);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::getStatus()
	 */
	public function getStatus() {
		$this->createPatchesTable();
		
		$status = $this->dbConnection->getOne('SELECT status FROM patches WHERE unique_name = '.$this->dbConnection->quoteSmart($this->uniqueName));
		if (!$status) {
			return PatchInterface::STATUS_AWAITING;
		}
		return $status;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::getUniqueName()
	 */
	public function getUniqueName() {
		return $this->uniqueName;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::getDescription()
	 */
	public function getDescription() {
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
	private function executeSqlFile($file) {

        set_time_limit(0);

		$nb_statements = 0;

        if (is_file($file) === true)
        {
            $fpt = fopen($file, 'r');

            if (is_resource($fpt) === true)
            {
                $query = array();

                while (feof($fpt) === false)
                {
                    $query[] = fgets($fpt);

                    if (preg_match('~' . preg_quote(';', '~') . '\s*$~iS', end($query)) === 1)
                    {
                        $query = trim(implode('', $query));

                        try {
                            $this->dbConnection->exec($query);
                        } catch (\Exception $e) {
                            throw new \Exception("An error occurred while executing request: ".$query." --- Error message: ".$e->getMessage(), 0, $e);
			            }
                        $nb_statements++;
                    }

                    if (is_string($query) === true)
                    {
                        $query = array();
                    }
                }

                fclose($fpt);
                return $nb_statements;
            } else {
                throw new \Exception("Can not open file: ".$file);
            }
        } else {
            throw new \Exception("Can not find file: ".$file);
        }
	}
	
	private function savePatch($status, $error_message) {
		$this->checkDbConnection();
		$id = $this->dbConnection->getOne('SELECT id FROM patches WHERE unique_name = '.$this->dbConnection->quoteSmart($this->uniqueName));
		if ($id) {
			$this->dbConnection->exec('UPDATE patches SET unique_name = '.$this->dbConnection->quoteSmart($this->uniqueName).',
					status = '.$this->dbConnection->quoteSmart($status).',
					exec_date = '.$this->dbConnection->quoteSmart(date('Y-m-d H:i:s')).',
					error_message = '.$this->dbConnection->quoteSmart($error_message).' WHERE id = '.$this->dbConnection->quoteSmart($id));
		} else {
			$this->dbConnection->exec('INSERT INTO patches (unique_name, status, exec_date, error_message) 
					VALUES  ('.$this->dbConnection->quoteSmart($this->uniqueName).',
					'.$this->dbConnection->quoteSmart($status).',
					'.$this->dbConnection->quoteSmart(date('Y-m-d H:i:s')).',
					'.$this->dbConnection->quoteSmart($error_message).')');
		}
	}
	
	/* (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::getLastErrorMessage()
	 */
	public function getLastErrorMessage() {
		$this->checkDbConnection();
		$errorMessage = $this->dbConnection->getOne('SELECT error_message FROM patches WHERE unique_name = '.$this->dbConnection->quoteSmart($this->uniqueName));
		if (!$errorMessage) {
			return null;
		}
		return $errorMessage;
	}

	/**
	 * Throws an exception if dbConnection is not set.
	 * 
	 */
	private function checkDbConnection() {
		if ($this->dbConnection == null) {
			throw new PatchException("Error in patch '".htmlentities($this->getUniqueName(), ENT_QUOTES, 'utf-8')."'. The dbConnection is not set for this patch.");
		}
	}

	/* (non-PHPdoc)
	 * @see \Mouf\Utils\Patcher\PatchInterface::getEditUrl()
	 */
	public function getEditUrl() {
		return "dbpatch/?patchInstanceName=".urlencode(MoufManager::getMoufManager()->findInstanceName($this));
	}

}
