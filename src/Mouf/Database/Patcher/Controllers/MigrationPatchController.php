<?php

namespace Mouf\Database\Patcher\Controllers;

use Mouf\ClassProxy;
use Mouf\Composer\ClassNameMapper;
use Mouf\Controllers\AbstractMoufInstanceController;
use Mouf\MoufManager;
use Mouf\Mvc\Splash\Controllers\Controller;
use Mouf\Html\HtmlElement\HtmlBlock;
use Mouf\InstanceProxy;
use Mouf\UniqueIdService;
use Mouf\Utils\Patcher\PatchType;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The controller to register database migration patch via a PHP class in the patcher.
 *
 * @Logged
 */
class MigrationPatchController extends AbstractMoufInstanceController
{
    /**
     * @var HtmlBlock
     */
    public $content;

    /**
     * A list of patches returned by the getView method of the PatchService.
     *
     * @var array
     */
    protected $patchesArray;

    protected $uniqueName;
    protected $description;
    protected $types;
    protected $selectedType;
    protected $patchClassName;

    /**
     * Page used to register a new patch / edit an existing patch.
     *
     * @Action
     *
     * @param string $name              The name of the PackageService instance.
     * @param string $selfedit
     */
    public function defaultAction($name, $selfedit = 'false')
    {
        $this->initController($name, $selfedit);

        $patchService = new InstanceProxy($name, $selfedit == "true");
        $this->types = $patchService->_getSerializedTypes();

        $classNameMapper = ClassNameMapper::createFromComposerFile(__DIR__.'/../../../../../../../../composer.json');
        $namespaces = $classNameMapper->getManagedNamespaces();

        if (empty($namespaces)) {
            set_user_message("You don't have any PSR-0 or PSR-4 autoloader declared in your Composer file. Therefore, you cannot generate autoloadable patches. Please add an autoloader to your composer.json.");
        } else {
            $this->patchClassName = $namespaces[0].'Migrations\\Patch'.date('YmdHis');
        }

        $this->content->addFile(__DIR__.'/../../../../views/editMigrationPatch.php', $this);
        $this->template->toHtml();
    }

    /**
     * Saves the db patch and the files.
     *
     * @Action
     * @Logged
     *
     * @param string $name
     * @param string $selfedit
     * @param string $className
     * @param string $description
     * @param string $type
     */
    public function save($name, $selfedit,
            $className, $description, $type, $purpose)
    {
        $this->initController($name, $selfedit);

        $rootPath = realpath(ROOT_PATH.'../../../').'/';

        if (empty($className)) {
            set_user_message('Class name cannot be empty.');
            header('Location: .?name='.urlencode($name).'&selfedit='.urlencode($selfedit));

            return;
        }

        $pos = strrpos($className, '\\');
        $namespace = substr($className, 0, $pos);
        $shortClassName = substr($className, $pos + 1);
        $descriptionCode = var_export($description, true);

        if ($purpose === 'model') {
            $code = <<<EOF
<?php
namespace $namespace;

use Doctrine\DBAL\Schema\Schema;
use Mouf\Database\Patcher\AbstractSchemaMigrationPatch;
use TheCodingMachine\FluidSchema\FluidSchema;


/**
 * This class is a patch used to apply changes to the database.
 */
class $shortClassName extends AbstractSchemaMigrationPatch
{
    public function up(Schema \$schema) : void
    {
        \$db = new FluidSchema(\$schema);
        // Code your migration here.
        //
        // \$db->table('users')
        //     ->id() // Create an 'id' primary key that is an autoincremented integer
        //     ->column('login')->string(50)->unique()->then() // Create a login column with a "unique" index
        //     ->column('photo_url')->string(50)->null()->then() // Create a nullable 'photo_url' column
        //     ->column('country_id')->references('countries'); // Create a foreign key on the 'countries' table
        //
        // \$db->junctionTable('users', 'roles'); // Create a 'users_roles' junction table between 'users' and 'roles'.
        
        // More documentation here: https://github.com/thecodingmachine/dbal-fluid-schema-builder
        // and here: http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-representation.html
    }
    
    public function down(Schema \$schema) : void
    {
        // Code your migration cancellation code here.
        //
        // \$db->dropTable('users');
    }
    
    public function getDescription(): string
    {
        return $descriptionCode;
    }
}

EOF;
        } elseif ($purpose === 'data') {
            $code = <<<EOF
<?php
namespace $namespace;

use Doctrine\DBAL\Connection;
use Mouf\Database\Patcher\AbstractDataMigrationPatch;

/**
 * This class is a patch used to apply insert/update/deletes to rows of the database.
 */
class $shortClassName extends AbstractDataMigrationPatch
{
    public function up(Connection \$connection) : void
    {
        // Code your data processing code here.
        // \$connection->insert('users', [
        //     'login' => 'admin',
        //     'password' => 'someencryptedpassword'
        // ]);
    }
    
    public function down(Connection \$connection) : void
    {
        // Code your migration cancellation code here.
    }
    
    public function getDescription(): string
    {
        return $descriptionCode;
    }
}

EOF;
        } else {
            throw new \Exception('Unknown purpose '.$purpose);
        }

        $classNameMapper = ClassNameMapper::createFromComposerFile(__DIR__.'/../../../../../../../../composer.json');
        $filePaths = $classNameMapper->getPossibleFileNames($className);
        if (empty($filePaths)) {
            throw new \Exception("Could not find a suitable place to store class '$className'. Please check your autoloader configuration in composer.json.");
        }

        $fileSystem = new Filesystem();
        $fullPath = $rootPath.$filePaths[0];
        $fileSystem->dumpFile($fullPath, $code);
        chmod($fullPath, 0664);

        $patchDescriptor = $this->moufManager->createInstance($className);
        $patchDescriptor->setName($className);

        $patchDescriptor->getProperty('patchConnection')->setValue($this->moufManager->getInstanceDescriptor('patchConnection'));
        $patchDescriptor->getProperty('patchType')->setValue($this->moufManager->getInstanceDescriptor($type));


        $patchManager = $this->moufManager->getInstanceDescriptor($name);

        $patches = $patchManager->getProperty('patchs')->getValue();
        if ($patches === null) {
            $patches = array();
        }
        $patches[] = $patchDescriptor;
        $patchManager->getProperty('patchs')->setValue($patches);

        $this->moufManager->rewriteMouf();

        header('Location: '.ROOT_URL.'patcher/?name='.urlencode($name).'&selfedit='.urlencode($selfedit));
    }
}
