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
use Doctrine\DBAL\Schema\Schema;
use Mouf\Utils\Patcher\Dumper\DumpableInterface;
use Mouf\Utils\Patcher\Dumper\DumperInterface;
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
abstract class AbstractDatabasePatch implements PatchInterface, DumpableInterface
{
    /**
     * @var PatchConnection
     */
    private $patchConnection;
    /**
     * @var PatchType
     */
    private $patchType;

    /**
     * @param PatchConnection $patchConnection The connection that will be used to run the patch.
     * @param PatchType|null $patchType
     */
    public function __construct(PatchConnection $patchConnection = null, PatchType $patchType = null)
    {
        $this->patchConnection = $patchConnection;
        $this->patchType = $patchType;
        if ($patchType === null) {
            // In case no patch type is set, let's declare a default type (useful for migration purposes from old version where all patches have no type).
            $this->patchType = new PatchType('', '');
        }
    }

    /* (non-PHPdoc)
     * @see \Mouf\Utils\Patcher\PatchInterface::skip()
     */
    public function skip(): void
    {
        $this->saveDbSchema();
        $this->createPatchesTable();
        $this->savePatch(PatchInterface::STATUS_SKIPPED, null);
    }

    /**
     * Creates the 'patches' table if it does not exists yet.
     *
     * @throws \Exception
     */
    protected function createPatchesTable()
    {
        $this->checkdbalConnection();
        // First, let's check that the patches table exists and let's create the table if it does not.
        DatabasePatchInstaller::createPatchTable($this->patchConnection);
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::getStatus()
     */
    public function getStatus(): string
    {
        $this->createPatchesTable();

        $status = $this->patchConnection->getConnection()->fetchColumn('SELECT status FROM '.$this->patchConnection->getTableName().' WHERE unique_name = ?', array($this->getUniqueName()));
        if (!$status) {
            return PatchInterface::STATUS_AWAITING;
        }

        return $status;
    }

    protected function savePatch($status, $error_message)
    {
        $this->checkdbalConnection();
        $id = $this->patchConnection->getConnection()->fetchColumn('SELECT id FROM '.$this->patchConnection->getTableName().' WHERE unique_name = ?', array($this->getUniqueName()));
        if ($id) {
            $this->patchConnection->getConnection()->update($this->patchConnection->getTableName(),
                array(
                    'unique_name' => $this->getUniqueName(),
                    'status' => $status,
                    'exec_date' => date('Y-m-d H:i:s'),
                    'error_message' => $error_message,
                ),
                array(
                    'id' => $id,
                )
            );
        } else {
            $this->patchConnection->getConnection()->insert($this->patchConnection->getTableName(),
                array(
                    'unique_name' => $this->getUniqueName(),
                    'status' => $status,
                    'exec_date' => date('Y-m-d H:i:s'),
                    'error_message' => $error_message,
                )
            );
        }
    }

    /* (non-PHPdoc)
     * @see \Mouf\Utils\Patcher\PatchInterface::getLastErrorMessage()
     */
    public function getLastErrorMessage(): ?string
    {
        $this->checkdbalConnection();
        $errorMessage = $this->patchConnection->getConnection()->fetchColumn('SELECT error_message FROM '.$this->patchConnection->getTableName().' WHERE unique_name = ?', array($this->getUniqueName()));
        if (!$errorMessage) {
            return null;
        }

        return $errorMessage;
    }

    /**
     * Throws an exception if dbalConnection is not set.
     */
    private function checkdbalConnection()
    {
        if ($this->patchConnection->getConnection() === null) {
            throw new PatchException("Error in patch '".htmlentities($this->getUniqueName(), ENT_QUOTES, 'utf-8')."'. The dbalConnection is not set for this patch.");
        }
    }

    protected function saveDbSchema()
    {
        $schema = $this->patchConnection->getConnection()->getSchemaManager()->createSchema();
        file_put_contents(__DIR__.'/../../../../generated/schema', serialize($schema));
        @chmod(__DIR__.'/../../../../generated/schema', 0664);
    }

    /**
     * Returns the type of the patch.
     *
     * @return PatchType
     */
    public function getPatchType(): PatchType
    {
        return $this->patchType;
    }

    protected function getConnection(): Connection
    {
        return $this->patchConnection->getConnection();
    }

    public function setDumper(DumperInterface $dumper)
    {
        $this->patchConnection->setDumper($dumper);
    }
}
