<?php


namespace Mouf\Database\Patcher;

use Doctrine\DBAL\Connection;
use Mouf\Utils\Patcher\PatchInterface;

/**
 * Patches extending this class can alter the data of the database easily using the up and down method.
 */
abstract class AbstractDataMigrationPatch implements PatchInterface
{
    use DbalSchemaPatchTrait;

    abstract public function up(Connection $schema) : void;

    public function down(Connection $schema) : void {

    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::apply()
     */
    public function apply(): void
    {
        $this->createPatchesTable();

        $connection = $this->patchConnection->getConnection();

        // Let's run the patch.
        try {
            $this->up($connection);

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

        $connection = $this->patchConnection->getConnection();

        // Let's run the patch.
        try {
            $this->down($connection);

            $this->saveDbSchema();
        } catch (\Exception $e) {
            // On error, let's mark this in database.
            $this->savePatch(PatchInterface::STATUS_ERROR, $e->getMessage());
            throw $e;
        }
        $this->savePatch(PatchInterface::STATUS_AWAITING, null);
    }
}
