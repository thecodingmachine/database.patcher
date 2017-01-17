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


/**
 * Class representing the patches table in database
 *
 * @author Pierre Vaidie
 */
class PatchConnection
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
     * DatabasePatchTable constructor.
     * @param  string      $tableName
     * @param  Connection  $dbalConnection
     */
    public function __construct($tableName, Connection $dbalConnection)
    {
        $this->tableName = $tableName;
        $this->dbalConnection = $dbalConnection;
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
     * Setter
     *
     * @param string Connection
     */
    public function setConnnection($dbalConnection) {
        $this->dbalConnection = $dbalConnection;
    }
}
