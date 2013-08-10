<?php
namespace Mouf\Utils\Patcher\Controllers;

use Mouf\Controllers\AbstractMoufInstanceController;

use Mouf\Database\TDBM\Utils\TDBMDaoGenerator;

use Mouf\MoufManager;

use Mouf\Mvc\Splash\Controllers\Controller;

use Mouf\Reflection\MoufReflectionProxy;

use Mouf\Html\HtmlElement\HtmlBlock;
use Mouf\InstanceProxy;

/**
 * The controller to track which patchs have been applied.

 */
class PatchController extends AbstractMoufInstanceController {
	
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
	
	/**
	 * Page listing the patches to be applied.
	 *
	 * @Action
	 */
	public function defaultAction($name, $selfedit="false") {
		$this->initController($name, $selfedit);
		
		$patchService = new InstanceProxy($name, $selfedit == "true");
		$this->patchesArray = $patchService->getView();
		
		$this->content->addFile(dirname(__FILE__)."/../../../../views/patchesList.php", $this);
		$this->template->toHtml();
	}
	
	
}