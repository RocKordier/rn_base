<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Rene Nitzsche (rene@system25.de)
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
tx_rnbase::load('Tx_Rnbase_Backend_Utility');

/**
 */
class tx_rnbase_mod_Util {
	/**
	 * Retrieve (and update) a value from module data.
	 * @param string $key
	 * @param tx_rnbase_mod_IModule $mod
	 * @param array $options
	 */
	public static function getModuleValue($key, tx_rnbase_mod_IModule $mod, $options=array()) {
		$changedSettings = is_array($options['changed']) ? $options['changed'] : array();
		$type = isset($options['type']) ? $options['type'] : '';
		$modData = Tx_Rnbase_Backend_Utility::getModuleData(array ($key => ''), $changedSettings, $mod->getName(), $type);
		return isset($modData[$key]) ? $modData[$key] : NULL;
	}
	/**
	 * Returns all data for a module for current BE user.
	 * @param tx_rnbase_mod_IModule $mod
	 * @param	string $type If type is 'ses' then the data is stored as session-lasting data. This means that it'll be wiped out the next time the user logs in.
	 */
	public static function getUserData(tx_rnbase_mod_IModule $mod, $type='') {
		$settings = $GLOBALS['BE_USER']->getModuleData($mod->getName(), $type);
		return $settings;
	}

	/**
	 * Returns a TYPO3 sprite icon
	 *
	 * @param string $iconName
	 * @param array $options
	 * @param array $overlays
	 *
	 * @return string The full HTML tag (usually a <span>)
	 */
	public static function getSpriteIcon($iconName, array $options = array(), array $overlays = array()) {
		// TODO: add support for older TYPO3 versions
		return t3lib_iconWorks::getSpriteIcon($iconName, $options, $overlays);
	}
	/**
	 * Returns a string with all available Icons in TYPO3 system. Each icon has a tooltip with its identifier.
	 * @return string
	 */
	public static function debugSprites() {
		$icons .= '<h2>iconsAvailable</h2>';
		foreach($GLOBALS['TBE_STYLES']['spriteIconApi']['iconsAvailable'] AS $icon) {
			$icons .= "<span title=\"$icon\">".self::getSpriteIcon($icon) .'</span>';
		}
		return $icons;
	}



	/**
	 * Gibt einen selector mit den elementen im gegebenen array zurück
	 *
	 * @TODO: move to an selector!
	 *
	 * @param array $aItems Array mit den werten der Auswahlbox
	 * @param mixed $selectedItem
	 * @param string $sDefId ID-String des Elements
	 * @param array $aData enthält die Formularelement für die Ausgabe im Screen. Keys: selector, label
	 * @param array $aOptions zusätzliche Optionen: label, id
	 * @return string selected item
	 */
	public static function showSelectorByArray($aItems, $selectedItem, $sDefId, &$aData, $aOptions=array()) {
		$id = isset($aOptions['id']) && $aOptions['id'] ? $aOptions['id'] : $sDefId;
		$pid = isset($aOptions['pid']) && $aOptions['pid'] ? $aOptions['pid'] : 0;

		// Build select box items
		$aData['selector'] = Tx_Rnbase_Backend_Utility::getFuncMenu(
			$pid, 'SET['.$id.']', $selectedItem, $aItems
		);

		//label
		$aData['label'] = $aOptions['label'];

		// as the deleted fe users have always to be hidden the function returns always FALSE
		//@todo wozu die alte abfrage? return $defId==$id ? FALSE : $selectedItem;
		return $selectedItem;
	}

	/**
	 * /**
	 * Wrapper method for t3lib_iconWorks::mapRecordTypeToSpriteIconName() or \TYPO3\CMS\Backend\Utility\IconUtility::mapRecordTypeToSpriteIconName()
	 *
	 * @param string $tableThe TCA table
	 * @param array $row The selected record
	 * @return string The CSS class for the sprite icon of that DB record
	 */
	static public function mapRecordTypeToSpriteIconName($table, array $row) {
		if (tx_rnbase_util_TYPO3::isTYPO60OrHigher()) {
			$iconName = \TYPO3\CMS\Backend\Utility\IconUtility::mapRecordTypeToSpriteIconName($table, $row);
		} else{
			$iconName = t3lib_div::mapRecordTypeToSpriteIconName($table, $row);
		}

		return $iconName ;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/mod/class.tx_rnbase_mod_Util.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/mod/class.tx_rnbase_mod_Util.php']);
}