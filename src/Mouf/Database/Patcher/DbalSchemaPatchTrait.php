<?php


namespace Mouf\Database\Patcher;

use Mouf\Utils\Patcher\PatchInterface;
use Mouf\MoufManager;
use Mouf\Utils\Patcher\PatchType;

trait DbalSchemaPatchTrait
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
     * @param PatchType $patchType
     */
    public function __construct(PatchConnection $patchConnection, PatchType $patchType)
    {
        $this->patchConnection = $patchConnection;
        $this->patchType = $patchType;
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
        // First, let's check that the patches table exists and let's create the table if it does not.
        DatabasePatchInstaller::createPatchTable($this->patchConnection);
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::canRevert()
     */
    public function canRevert(): bool
    {
        return true;
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

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::getUniqueName()
     */
    public function getUniqueName(): string
    {
        return get_class($this);
    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::getDescription()
     */
    public function getDescription(): string
    {
        return '';
    }

    private function savePatch($status, $error_message)
    {
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
        $errorMessage = $this->patchConnection->getConnection()->fetchColumn('SELECT error_message FROM '.$this->patchConnection->getTableName().' WHERE unique_name = ?', array($this->getUniqueName()));
        if (!$errorMessage) {
            return null;
        }

        return $errorMessage;
    }


    /* (non-PHPdoc)
     * @see \Mouf\Utils\Patcher\PatchInterface::getEditUrl()
     */
    public function getEditUrl(): ?string
    {
        return 'ajaxinstance/?name='.urlencode(MoufManager::getMoufManager()->findInstanceName($this)).'&selfedit=false';
    }

    private function saveDbSchema()
    {
        $schema = $this->patchConnection->getConnection()->getSchemaManager()->createSchema();
        file_put_contents(__DIR__.'/../../../../generated/schema', serialize($schema));
        chmod(__DIR__.'/../../../../generated/schema', 0664);
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
}
