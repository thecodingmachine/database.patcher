<?php


namespace Mouf\Database\Patcher;

use Doctrine\DBAL\Schema\Schema;
use Mouf\Utils\Patcher\PatchInterface;
use Mouf\MoufManager;
use Mouf\Utils\Patcher\PatchType;

/**
 * Patches extending this class can alter the model of the database easily using the up and down method.
 */
abstract class AbstractSchemaMigrationPatch extends AbstractDatabasePatch
{
    abstract public function up(Schema $schema) : void;

    public function down(Schema $schema) : void {

    }

    /**
     * (non-PHPdoc).
     *
     * @see \Mouf\Utils\Patcher\PatchInterface::apply()
     */
    public function apply(): void
    {
        $this->createPatchesTable();

        $connection = $this->getConnection();

        $schema = $connection->getSchemaManager()->createSchema();
        $oldSchema = clone $schema;

        // Let's run the patch.
        try {
            $this->up($schema);

            $sqlQueries = $oldSchema->getMigrateToSql($schema, $connection->getDatabasePlatform());
            foreach ($sqlQueries as $query) {
                $connection->exec($query);
            }

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

        $connection = $this->getConnection();

        $schema = $connection->getSchemaManager()->createSchema();
        $oldSchema = clone $schema;

        // Let's run the patch.
        try {
            $this->down($schema);

            $sqlQueries = $oldSchema->getMigrateToSql($schema, $connection->getDatabasePlatform());
            foreach ($sqlQueries as $query) {
                $connection->exec($query);
            }

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
        return true;
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

    /* (non-PHPdoc)
     * @see \Mouf\Utils\Patcher\PatchInterface::getEditUrl()
     */
    public function getEditUrl(): ?string
    {
        return 'ajaxinstance/?name='.urlencode(MoufManager::getMoufManager()->findInstanceName($this)).'&selfedit=false';
    }

}
