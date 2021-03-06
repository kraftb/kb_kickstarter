<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 2008-2010 Bernhard Kraft (kraftb@think-open.at)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is 
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
* 
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
* 
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/** 
 * Module 'Kick Admin' for the 'kb_kickstarter' extension.
 *
 * @author	Bernhard Kraft <kraftb@think-open.at>
 */



unset($MCONF);	
require_once('conf.php');
require_once($BACK_PATH.'init.php');
require_once($BACK_PATH.'template.php');

$LANG->includeLLFile('EXT:kb_kickstarter/llxml/locallang_kickadmin.xml');

require_once(PATH_t3lib.'class.t3lib_scbase.php');
require_once(PATH_kb_kickstarter.'class.tx_kbkickstarter_misc.php');


$BE_USER->modAccess($MCONF,1);

class tx_kbkickstarter_kickadmin extends t3lib_SCbase {
	private $pageinfo = false;

	private $configObj = null;
	private $extension = '';

	private $smarty = null;
	private $cacheSmarty = false;

	/**
	 * Initialize the module
	 *
	 * @return	void
	 */
	public function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		$this->configObj = &$GLOBALS['T3_VARS']['kb_kickstarter_config'];
		$this->extension = $this->configObj->get_extension();
		parent::init();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	public function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			'function' => Array (
				'genConfig' => $LANG->getLL('function_genConfig'),
				'configExt' => $LANG->getLL('function_configExt'),
			)
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 *
	 * @return	void
	 */
	public function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;
		
		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{
	
			// Draw the header.
			$this->doc = t3lib_div::makeInstance('noDoc');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="index.php" method="POST" target="_self" enctype="multipart/form-data">';

			$cssFile = t3lib_div::getFileAbsFileName('EXT:'.$this->extension.'/mod_kickadmin/res/mod_kickadmin.css');
			$this->doc->inDocStylesArray['kb_kickstarter_kickadmin'] = t3lib_div::getURL($cssFile);

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = '.intval($this->id).';
				</script>
			';

			$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br>'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_pre($this->pageinfo['_thePath'],50);

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function'])));
			$this->content.=$this->doc->divider(5);

			
			// Render content:
			$this->moduleContent();

			
			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}
		
			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero
		
			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;
		
			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}


	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	public function printContent()	{
		$this->content.=$this->doc->endPage();
		echo $this->content;
	}


	/**
	 * Generates the module content
	 *
	 * @return	void
	 */
	private function moduleContent()	{
		global $LANG;
		$this->smarty = tx_smarty::smarty();
		$this->smarty->setTemplateDir('EXT:'.$this->extension.'/mod_kickadmin/res');
		$this->smarty->setSmartyVar('caching', $this->cacheSmarty);
		$this->smarty->setSmartyVar('compile_dir', 'typo3temp/smarty_compile');
		$this->smarty->setSmartyVar('cache_dir', 'typo3temp/smarty_cache');
		$this->smarty->registerPlugin('function', 'createDirectory', array('tx_kbkickstarter_Hooks_Smarty_CreateDirectory', 'createDirectory'));
		$this->smarty->assign('configObj', $this->configObj);
		$this->smarty->assign('extension', $this->extension);

		$function = $this->MOD_SETTINGS['function'];
		switch ($function) {
			case 'genConfig':
			case 'configExt':
				$class = __CLASS__.'_'.$function;
				$kickadmin_mod = t3lib_div::makeInstance($class);
				$kickadmin_mod->init($this, $this);
				$content = $kickadmin_mod->moduleContent();
				$this->content .= $this->doc->section($LANG->getLL('function_'.$function), $content);
			break;
/*
				require_once(PATH_kb_kickstarter.'mod_kickadmin/class.tx_kbkickstarter_kickadmin_genTCA.php');
				$kickadmin_genTCA = t3lib_div::makeInstance('tx_kbkickstarter_kickadmin_genTCA');
				$kickadmin_genTCA->init($this, $this);
				$content = $kickadmin_genTCA->moduleContent_genTCA($this);
				$this->content .= $this->doc->section($LANG->getLL('function_regenerate'), $content);
			break;
			case 'config_ext':
				require_once(PATH_kb_kickstarter.'mod_kickadmin/class.tx_kbkickstarter_kickadmin_configExt.php');
				$kickadmin_configExt = t3lib_div::makeInstance('tx_kbkickstarter_kickadmin_configExt');
				$kickadmin_configExt->init($this, $this);
				$content = $kickadmin_configExt->moduleContent_configExt($this);
			break;
*/
		} 
	}

	public function clone_smarty() {
		return clone($this->smarty);
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/kb_kickstarter/mod_kickadmin/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/kb_kickstarter/mod_kickadmin/index.php']);
}


// Make instance:
$SOBE = t3lib_div::makeInstance('tx_kbkickstarter_kickadmin');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
