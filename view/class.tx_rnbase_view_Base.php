<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2006 Rene Nitzsche
 *  Contact: rene@system25.de
 *  All rights reserved
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 ***************************************************************/

/**
 * Depends on: none
 *
 * @author René Nitzsche <rene@system25.de>
 * @package TYPO3
 * @subpackage rn_base
 */

require_once(t3lib_extMgm::extPath('div') . 'class.tx_div.php');
/**
 * Base class for all views.
 * TODO: This class should have a default template path and an optional user defined path. So 
 * templates can be searched in both.
 */
class tx_rnbase_view_Base{
  var $pathToTemplates;
  var $_pathToFile;

  /**
   * Enter description here...
   *
   * @param string $view default name of view
   * @param tx_rnbase_configurations $configurations
   * @return unknown
   */
  function render($view, &$configurations){
    $this->_init($configurations);
    $cObj =& $configurations->getCObj(0);
    $templateCode = $cObj->fileResource($this->getTemplate($view,'.html'));
    if(!strlen($templateCode)) {
    	tx_div::load('tx_rnbase_util_Misc');
    	tx_rnbase_util_Misc::mayday('TEMPLATE NOT FOUND: ' . $this->getTemplate($view,'.html'));
    }

    // Die ViewData bereitstellen
    $viewData =& $configurations->getViewData();
    // Optional kann schon ein Subpart angegeben werden
    if($this->getMainSubpart()) {
    	$subpart = $this->getMainSubpart();
    	$templateCode = $cObj->getSubpart($templateCode,$subpart);
	    if(!strlen($templateCode)) {
	    	tx_div::load('tx_rnbase_util_Misc');
	    	tx_rnbase_util_Misc::mayday('SUBPART NOT FOUND: ' . $subpart);
	    }
    }
    
    $out = $this->createOutput($templateCode,$viewData, $configurations, $configurations->getFormatter());

    return $out;
  }

  /**
   * Entry point for child classes
   *
   * @param string $template
   * @param array_object $viewData
   * @param tx_rnbase_configurations $configurations
   * @param tx_rnbase_util_FormatUtil $formatter
   */
  function createOutput($template, &$viewData, &$configurations, &$formatter) {
  	
  }
  /**
   * Kindklassen können hier einen Subpart-Marker angeben, der initial als Template
   * verwendet wird. Es wird dann in createOutput nicht mehr das gesamte
   * Template übergeben, sondern nur noch dieser Abschnitt. Außerdem wird sichergestellt,
   * daß dieser Subpart im Template vorhanden ist.
   *
   * @return string like ###MY_MAIN_SUBPART### or false
   */
  function getMainSubpart() {return false;}
  /**
   * This method is called first.
   *
   * @param tx_rnbase_configurations $configurations
   */
  function _init(&$configurations) {}
  
  /**
   * Set the path of the template directory
   *
   *  You can make use the syntax  EXT:myextension/somepath.
   *  It will be evaluated to the absolute path by t3lib_div::getFileAbsFileName()
   *
   * @param	string		path to the directory containing the php templates
   * @return	void
   * @see         intro text of this class above
   */
  function setTemplatePath($pathToTemplates){
//    $path = t3lib_div::getFileAbsFileName($pathToTemplates);
//    $this->pathToTemplates = $path;

    $this->pathToTemplates = $pathToTemplates;
  }

  /**
   * Set the path of the template file.
   *
   *  You can make use the syntax  EXT:myextension/template.php
   *
   * @param	string		path to the file used as templates
   * @return	void
   */
  function setTemplateFile($pathToFile) {
//    $path = t3lib_div::getFileAbsFileName($pathToFile);
    $this->_pathToFile = $pathToFile;
  }

  /**
   * Returns the template to use. If TemplateFile is set, it is preferred. Otherwise
   * the filename is build from pathToTemplates, the templateName and $extension.
   * 
   * @param string name of template
   * @param string file extension to use
   * @return complete filename of template
   */
  function getTemplate($templateName, $extension = '.php', $forceAbsPath = 0) {
    if(strlen($this->_pathToFile) > 0) {
      return ($forceAbsPath) ? t3lib_div::getFileAbsFileName($this->_pathToFile) : $this->_pathToFile;
    }
    $path = $this->pathToTemplates;
    $path .= substr($path, -1, 1) == '/' ? $templateName : '/' . $templateName;
    $extLen = strlen($extension);
    $path .= substr($path, ($extLen * -1), $extLen) == $extension ? '' :  $extension;
    return $path;
  }

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/view/class.tx_rnbase_view_Base.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/view/class.tx_rnbase_view_Base.php']);
}
?>