<?php
namespace Mouf\Database\Patcher\Controllers;

use Mouf\Controllers\AbstractMoufInstanceController;

use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;

use Mouf\MoufManager;

use Mouf\Mvc\Splash\Controllers\Controller;

use Mouf\Reflection\MoufReflectionProxy;

use Mouf\Html\HtmlElement\HtmlBlock;
use Mouf\InstanceProxy;
use Mouf\Utils\Patcher\PatchInterface;

/**
 * The controller to register database patches in the patcher.

 */
class DatabasePatchController extends AbstractMoufInstanceController {
	
	/**
	 *
	 * @var HtmlBlock
	 */
	public $content;
	
	/**
	 * A list of patches returned by the getView method of the PatchService. 
	 * @var array
	 */
	protected $patchesArray;
	
	protected $patchInstanceName;
	protected $uniqueName;
	protected $oldUniqueName;
	protected $description;
	protected $upSql;
	protected $downSql;
	protected $upSqlFileName;
	protected $downSqlFileName;
	protected $status;
	
	/**
	 * Page used to register a new patch / edit an existing patch.
	 *
	 * @Action
	 * @param string $name The name of the PackageService instance.
	 * @param string $patchInstanceName The name of the patch instance (if in edition mode).
	 * @param string $selfedit
	 */
	public function defaultAction($name, $patchInstanceName = null, $selfedit="false") {
		$this->initController($name, $selfedit);
		
		$rootPath = realpath(ROOT_PATH."../../../")."/";
		
		$this->patchInstanceName = $patchInstanceName;
		/*$patchService = new InstanceProxy($name, $selfedit == "true");
		$this->patchesArray = $patchService->getView();*/
		
		if ($patchInstanceName == null) {
			$this->uniqueName = date("Ymd")."-patch";
			$this->oldUniqueName = "";
			$this->upSqlFileName = "database/up/".date("Ymd")."-patch.sql";
			$this->downSqlFileName = "database/down/".date("Ymd")."-patch.sql";
		} else {
			$patchDescriptor = $this->moufManager->getInstanceDescriptor($this->patchInstanceName);
			
			$this->uniqueName = $patchDescriptor->getProperty("uniqueName")->getValue();
			$this->description = $patchDescriptor->getProperty("description")->getValue();
			$this->oldUniqueName = $this->uniqueName;
			$this->upSqlFileName = $patchDescriptor->getProperty("upSqlFile")->getValue();
			$this->downSqlFileName = $patchDescriptor->getProperty("downSqlFile")->getValue();
			if (!file_exists($rootPath.$this->upSqlFileName)) {
				set_user_message("Unable to locate file '".$this->upSqlFileName."'. Was it created by you? Did someone forget to commit this file?");
			} else {
				$this->upSql = file_get_contents($rootPath.$this->upSqlFileName);
			}
			if ($this->downSqlFileName != null) {
				if(!file_exists($rootPath.$this->downSqlFileName)) {
					set_user_message("Unable to locate file '".$this->downSqlFileName."'. Was it created by you? Did someone forget to commit this file?");
				} else {
					$this->downSql = file_get_contents($rootPath.$this->downSqlFileName);
				}
			}
			if ($this->downSqlFileName == null) {
				$this->downSqlFileName = "database/down/".$this->uniqueName.".sql";
			}
		}
		$this->status = "skipped";
				
		$this->content->addFile(dirname(__FILE__)."/../../../../views/editPatch.php", $this);
		$this->template->toHtml();
	}
	
	/**
	 * Saves the db patch and the files.
	 * 
	 * @Action
	 * @param string $name
	 * @param string $patchInstanceName
	 * @param string $selfedit
	 * @param string $uniqueName
	 * @param string $description
	 * @param string $upSql
	 * @param string $upSqlFileName
	 * @param string $downSql
	 * @param string $downSqlFileName
	 * @param string $oldUniqueName
	 */
	public function save($name, $patchInstanceName, $selfedit,
			$uniqueName, $description, $upSql, $upSqlFileName, $downSql, $downSqlFileName, 
			$oldUniqueName, $status, $action) {
		$this->initController($name, $selfedit);
		
		if ($action == "delete") {
			$this->moufManager->removeComponent($patchInstanceName);
			$this->moufManager->rewriteMouf();
			
			header("Location: ".ROOT_URL."patcher/?name=".urlencode($name)."&selfedit=".urlencode($selfedit));
			return;
		}
		
		$rootPath = realpath(ROOT_PATH."../../../")."/";
		
		if ($uniqueName == "") {
			set_user_message("Unique name cannot be empty.");
			header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
			return;
		}

		if ($upSqlFileName == "") {
			set_user_message("SQL file name cannot be empty.");
			header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
			return;
		}

		$upSql = trim($upSql);
		$downSql = trim($downSql);
		if ($downSql != '' && $downSqlFileName == "") {
			set_user_message("Revert SQL file name cannot be empty.");
			header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
			return;
		}
		
		// Let's check that the unique name is truly unique.
		if ($oldUniqueName != $uniqueName) {
			$patchService = new InstanceProxy($name, $selfedit == "true");
			$this->patchesArray = $patchService->getView();
			foreach ($this->patchesArray as $patch) {
				if ($patch['uniqueName'] == $uniqueName) {
					set_user_message("Your unique name (".plainstring_to_htmlprotected($uniqueName).") is already in use.");
					header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
					return;
				}
			}
		}
				
		if ($patchInstanceName) {
			$patchDescriptor = $this->moufManager->getInstanceDescriptor($patchInstanceName);
		} else {
			$patchDescriptor = $this->moufManager->createInstance("Mouf\\Database\\Patcher\\DatabasePatch");
			if (!$this->moufManager->has("dbpatch.".$uniqueName)) {
				$patchDescriptor->setName("dbpatch.".$uniqueName);
			}
		}
		
		$patchDescriptor->getProperty("uniqueName")->setValue($uniqueName);
		$patchDescriptor->getProperty("description")->setValue($description);
		$patchDescriptor->getProperty("dbConnection")->setValue($this->moufManager->getInstanceDescriptor("dbConnection"));
		
		if ($downSql == "") {
			$downSql = null;
		}
		if ($downSqlFileName == "") {
			$downSqlFileName = null;
		}

		$oldUpSqlFile = $patchDescriptor->getProperty("upSqlFile")->getValue();
		$oldDownSqlFile = $patchDescriptor->getProperty("downSqlFile")->getValue();
		
		if ($oldUpSqlFile) {
			// We must remove this file before creating a new one.
			if (file_exists($rootPath.$oldUpSqlFile) && !is_writable($rootPath.$oldUpSqlFile)) {
				set_user_message("Sorry, impossible to edit file '".plainstring_to_htmlprotected($rootPath.$oldUpSqlFile)."'. Please check permissions on that file.");
				header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
				return;
			}
			
		}

		if ($oldDownSqlFile) {
			// We must remove this file before creating a new one.
			if (file_exists($rootPath.$oldDownSqlFile) && !is_writable($rootPath.$oldDownSqlFile)) {
				set_user_message("Sorry, impossible to edit file '".plainstring_to_htmlprotected($rootPath.$oldDownSqlFile)."'. Please check permissions on that file.");
				header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
				return;
			}
		}
		
			
		if ($oldUpSqlFile != $upSqlFileName) {
			$baseDirUpSqlFile = dirname($rootPath.$upSqlFileName);
			
			// Let's create the directory
			if (!file_exists($baseDirUpSqlFile)) {
				$old = umask(0);
				$result = @mkdir($baseDirUpSqlFile, 0775, true);
				umask($old);
				if (!$result) {
					set_user_message("Sorry, impossible to create directory '".plainstring_to_htmlprotected($baseDirUpSqlFile)."'. Please check directory permissions.");
					header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
					return;
				}
			}
			
			if (!is_writable($baseDirUpSqlFile)) {
				set_user_message("Sorry, directory '".plainstring_to_htmlprotected($baseDirUpSqlFile)."' is not writable. Please check directory permissions.");
				header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
				return;
			}
			
			if ($oldUpSqlFile) {
				if (file_exists($rootPath.$oldUpSqlFile)) {
					unlink($rootPath.$oldUpSqlFile);
				}
			}
		}

		if ($oldDownSqlFile != $downSqlFileName) {
			if ($downSql) {
				$baseDirDownSqlFile = dirname($rootPath.$downSqlFileName);
					
				// Let's create the directory
				if (!file_exists($baseDirDownSqlFile)) {
					$old = umask(0);
					$result = @mkdir($baseDirDownSqlFile, 0775, true);
					umask($old);
					if (!$result) {
						set_user_message("Sorry, impossible to create directory '".plainstring_to_htmlprotected($baseDirDownSqlFile)."'. Please check directory permissions.");
						header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
						return;
					}
				}
					
				if (!is_writable($baseDirDownSqlFile)) {
					set_user_message("Sorry, directory '".plainstring_to_htmlprotected($baseDirDownSqlFile)."' is not writable. Please check directory permissions.");
					header("Location: .?name=".urlencode($name)."&selfedit=".urlencode($selfedit).($patchInstanceName?"&patchInstanceName=".$patchInstanceName:""));
					return;
				}
			}
				
			if ($oldDownSqlFile) {
				if (file_exists($rootPath.$oldUpSqlFile)) {
					unlink($rootPath.$oldDownSqlFile);
				}
			}
		}
		
		file_put_contents($rootPath.$upSqlFileName, $upSql);
		// Chmod may fail if the file does not belong to the Apache user.
		@chmod($rootPath.$upSqlFileName, 0664);
		if ($downSql) {
			file_put_contents($rootPath.$downSqlFileName, $downSql);
			// Chmod may fail if the file does not belong to the Apache user.
			@chmod($rootPath.$downSqlFileName, 0664);
		}
		
		$patchDescriptor->getProperty("upSqlFile")->setValue($upSqlFileName);
		if ($downSql) {
			$patchDescriptor->getProperty("downSqlFile")->setValue($downSqlFileName);
		} else {
			$patchDescriptor->getProperty("downSqlFile")->setValue(null);
		}
		
		$patchManager = $this->moufManager->getInstanceDescriptor($name);
		if (empty($patchInstanceName)) {
			$patchs = $patchManager->getProperty("patchs")->getValue();
			if ($patchs === null) {
				$patchs = array();
			}
			$patchs[] = $patchDescriptor;
			$patchManager->getProperty("patchs")->setValue($patchs);
		}
		
		$this->moufManager->rewriteMouf();
		
		// Run patch / manage status.
		$patchService = new InstanceProxy($name, $selfedit == "true");
		if ($status == "skipped") {
			$patchService->skip($uniqueName);			
		} elseif ($status == "saveandexecute") {
			$patchService->apply($uniqueName);
		} else {
			// Do not apply: do nothing.
		}
		
		
		header("Location: ".ROOT_URL."patcher/?name=".urlencode($name)."&selfedit=".urlencode($selfedit));
	}
	
}