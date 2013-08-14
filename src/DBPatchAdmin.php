<?php
use Mouf\MoufManager;
use Mouf\MoufUtils;

MoufUtils::registerMainMenu('utilsMainMenu', 'Utils', null, 'mainMenu', 200);
MoufUtils::registerMenuItem('utilsPatchInterfaceMenu', 'Patches management', null, 'utilsMainMenu', 50);
MoufUtils::registerChooseInstanceMenuItem('utilsRegisterDbPatchInterfaceMenuItem', 'Register a database patch', 'dbpatch/', 'Mouf\\Utils\\Patcher\\PatchService', 'utilsPatchInterfaceMenu', 10);


// Controller declaration
$moufManager = MoufManager::getMoufManager();
$moufManager->declareComponent('dbpatch', 'Mouf\\Database\\Patcher\\Controllers\\DatabasePatchController', true);
$moufManager->bindComponents('dbpatch', 'template', 'moufTemplate');
$moufManager->bindComponents('dbpatch', 'content', 'block.content');

