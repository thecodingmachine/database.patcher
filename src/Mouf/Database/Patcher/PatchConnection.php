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
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Mouf\Utils\Patcher\PatchListenerInterface;


/**
 * Class representing the patches table in database
 *
 * @author Pierre Vaidie
 */
class PatchConnection implements PatchListenerInterface
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var Connection
     */
    private $dbalConnection;
    /**
     * @var Connection
     */
    private $dbalRootConnection;

    /**
     * DatabasePatchTable constructor.
     * @param  string $tableName
     * @param  Connection $dbalConnection
     * @param Connection|null $dbalRootConnection The DBAL connection with "root" credentials to drop and create the database again.
     */
    public function __construct($tableName, Connection $dbalConnection, Connection $dbalRootConnection = null)
    {
        $this->tableName = $tableName;
        $this->dbalConnection = $dbalConnection;
        $this->dbalRootConnection = $dbalRootConnection ?: $dbalConnection;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * Setter
     *
     * @deprecated
     * @param string $tableName
     */
    public function setTableName($tableName) {
        $this->tableName = $tableName;
    }

    /**
     * Getter
     *
     * @return Connection
     */
    public function getConnection() {
        return $this->dbalConnection;
    }

    /**
     * @deprecated
     * @param Connection $dbalConnection
     */
    public function setConnnection(Connection $dbalConnection) {
        $this->dbalConnection = $dbalConnection;
    }

    /**
     * Triggered when the 'reset()' method is called on the PatchService
     */
    public function onReset(): void
    {
        // Let's drop and recreate the database from 0!
        $dbName = $this->dbalConnection->getDatabase();
        $this->dbalRootConnection->getSchemaManager()->dropAndCreateDatabase($dbName);

        if ($this->dbalRootConnection->getDriver() instanceof AbstractMySQLDriver) {
            $this->dbalRootConnection->exec('USE '.$dbName);
        }
    }
}
