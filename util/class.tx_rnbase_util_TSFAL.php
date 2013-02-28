<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Rene Nitzsche
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


define('DEFAULT_LOCAL_FIELD', '_LOCALIZED_UID');

require_once(t3lib_extMgm::extPath('rn_base') . 'class.tx_rnbase.php');

tx_rnbase::load('tx_rnbase_util_TYPO3');
if(!tx_rnbase_util_TYPO3::isTYPO60OrHigher())
	return;

/**
 * Contains utility functions for FAL
 */
class tx_rnbase_util_TSFAL {

	/**
	 * Typoscript USER function for rendering DAM images. 
	 * This is a minimal Setup:
	 * <pre>
	 * yourObject.imagecol = USER
	 * yourObject.imagecol {
	 *   userFunc=tx_rnbase_util_TSFAL->printImages
	 *   refField=imagecol
	 *   refTable=tx_yourextkey_tablename
	 *   template = EXT:rn_base/res/simplegallery.html
	 *   # media is the dam record
	 *   media {
	 *     # field file contains the complete image path
	 *     file = IMAGE
	 *     file.file.import.field = file
	 *   }
	 *   # Optional setting for limit
	 *   # limit = 1
	 * }
	 * </pre>
	 * There are three additional fields in media record: file, file1 and thumbnail containing the complete
	 * image path. 
	 * The output is rendered via HTML template with ListBuilder. Have a look at EXT:rn_base/res/simplegallery.html
	 * Possible Typoscript options:
	 * refField: DAM reference field of the media records (defined in TCA and used to locate the record in MM-Table)
	 * refTable: should be the tablename where the DAM record is referenced to
	 * template: Full path to HTML template file.
	 * media: Formatting options of the DAM record. Have a look at tx_dam to find all column names
	 * limit: Limits the number of medias
	 * offset: Start media output with an offset
	 * forcedIdField: force another refernce column (other than UID or _LOCALIZED_UID)
	 * 
	 *
	 * @param string $content
	 * @param array $tsConf
	 * @return string
	 */
	function printImages ($content, $tsConf) {
		if(!t3lib_extMgm::isLoaded('dam')) return '';

		$conf = $this->createConf($tsConf);
		$file = $conf->get('template');
		$file = $file ? $file : 'EXT:rn_base/res/simplegallery.html';
		$subpartName = $conf->get('subpartName');
		$subpartName = $subpartName ? $subpartName : '###DAM_IMAGES###';
		$templateCode = tx_rnbase_util_Templates::getSubpartFromFile($file, $subpartName);

		if(!$templateCode) return '<!-- NO TEMPLATE OR SUBPART '.$subpartName.' FOUND -->';

		// Is there a customized language field configured
		$langField = DEFAULT_LOCAL_FIELD;
		$locUid = $conf->getCObj()->data[$langField]; // Save original uid
		if($conf->get('forcedIdField')) {
			$langField = $conf->get('forcedIdField');
			// Copy localized UID
			$conf->getCObj()->data[DEFAULT_LOCAL_FIELD] = $conf->getCObj()->data[$langField];
		}
		// Check if there is a valid uid given.
		$parentUid = intval($conf->getCObj()->data[DEFAULT_LOCAL_FIELD] ? $conf->getCObj()->data[DEFAULT_LOCAL_FIELD] : $conf->getCObj()->data['uid']);
		if(!$parentUid) return '<!-- Invalid data record given -->';

		$damPics = $this->fetchFileList($tsConf, $conf->getCObj());
		$conf->getCObj()->data[DEFAULT_LOCAL_FIELD] = $locUid; // Reset UID
		$offset = intval($conf->get('offset'));
		$limit = intval($conf->get('limit'));
		if((!$limit && $offset) && count($damPics))
			$damPics = array_slice($damPics,$offset);
		elseif($limit && count($damPics))
			$damPics = array_slice($damPics,$offset,$limit);

		$damDb = tx_rnbase::makeInstance('tx_dam_db');
		
		$medias = array();
		while(list($uid, $baseRecord) = each($damPics)) {
			$mediaObj = tx_rnbase::makeInstance('tx_rnbase_model_media', $baseRecord['uid']);
			// Localize data (DAM 1.1.0)
			if(method_exists($damDb, 'getRecordOverlay')) {
				$loc = $damDb->getRecordOverlay('tx_dam', $mediaObj->record, array('sys_language_uid'=>$GLOBALS['TSFE']->sys_language_uid));
				if ($loc) $mediaObj->record = $loc;
			}

			$mediaObj->record['parentuid'] = $parentUid;
			$medias[] = $mediaObj;
		}
		
		$listBuilder = tx_rnbase::makeInstance('tx_rnbase_util_ListBuilder');
		$out = $listBuilder->render($medias, false, $templateCode, 'tx_rnbase_util_MediaMarker',
						'media.', 'MEDIA', $conf->getFormatter());

		// Now set the identifier
		$markerArray['###MEDIA_PARENTUID###'] = $parentUid;
		$out = tx_rnbase_util_BaseMarker::substituteMarkerArrayCached($out, $markerArray);
		return $out;
	}

	/**
	 * Erstellt eine Instanz von tx_rnbase_configurations
	 *
	 * @param array $conf
	 * @return tx_rnbase_configurations
	 */
	function createConf($conf) {
		$configurations = tx_rnbase::makeInstance('tx_rnbase_configurations');
		$configurations->init($conf, $this->cObj, $conf['qualifier'], $conf['qualifier']);
		return $configurations;
	}

	/**
	 * Returns the first reference of a file. Usage by typoscript:
	 * 
	 * lib.logo = IMAGE
	 * lib.logo {
	 *   file.maxH = 30
	 *   file.maxW = 30
	 *   file.treatIdAsReference = 1
	 *   file.import.cObject = USER
	 *   file.import.cObject {
	 *     userFunc=tx_rnbase_util_TSFAL->fetchFirstReference
	 *     refField=t3logo
	 *     refTable=tx_cfcleague_teams
	 *   }
	 * }
	 *
	 * @param array $conf
	 * @return array
	 */
	public function fetchFirstReference ($content, $conf) {
		$cObj = $this->cObj;

		$uid      = $cObj->data['_LOCALIZED_UID'] ? $cObj->data['_LOCALIZED_UID'] : $cObj->data['uid'];
		$refTable = ($conf['refTable'] && is_array($GLOBALS['TCA'][$conf['refTable']])) ? $conf['refTable'] : 'tt_content';
		$refField = trim($cObj->stdWrap($conf['refField'],$conf['refField.']));
		
		if (isset($GLOBALS['BE_USER']->workspace) && $GLOBALS['BE_USER']->workspace !== 0) {
			$workspaceRecord = t3lib_BEfunc::getWorkspaceVersionOfRecord(
				$GLOBALS['BE_USER']->workspace,
				'tt_content',
				$uid,
				'uid'
			);

			if ($workspaceRecord) {
				$uid = $workspaceRecord['uid'];
			}
		}
		$fileRepository = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');
//		if ($table === 'pages' && isset($row['_LOCALIZED_UID']) && intval($row['sys_language_uid']) > 0) {
//			$table = 'pages_language_overlay';
//		}
		$files = $fileRepository->findByRelation($refTable, $refField, $uid);
		if(!empty($files)) {
			// Die erste Referenz zurück
			return $files[0]->getUid();
		}
		return '';
	}

	/**
	 * Fetches DAM records
	 *
	 * @param string $tablename
	 * @param int $uid
	 * @param string $refField
	 * @return array
	 */
	static function fetchFiles($tablename, $uid, $refField) {
		require_once(t3lib_extMgm::extPath('dam').'lib/class.tx_dam_db.php');
		return tx_dam_db::getReferencedFiles($tablename, $uid, $refField);
	}

	/**
	 * Test is DAM version 1.0 is installed.
	 *
	 * @return boolean
	 */
	static function isVersion10() {
		tx_rnbase::load('tx_rnbase_util_TYPO3');
		$version = tx_rnbase_util_TYPO3::getExtVersion('dam');
		if(preg_match('(\d*\.\d*\.\d)',$version, $versionArr)) {
			$version = $versionArr[0];
		}
		return version_compare($version, '1.1.0','<');
	}
	/**
	 * Create Thumbnails of DAM images in BE. Take care of installed DAM-Version and supports 1.0 and 1.1
	 *
	 * @param array $damFiles
	 * @param string $size i.e. '50x50'
	 * @param string $addAttr
	 * @return string image tag
	 */
	static function createThumbnails($damFiles, $size, $addAttr) {
		if(self::isVersion10()) {
			return self::createThumbnails10($damFiles, $size, $addAttr);
		}
		else {
			return self::createThumbnails11($damFiles, $size, $addAttr);
		}
	}
	static function createThumbnails11($damFiles, $size, $addAttr) {
		require_once(t3lib_extMgm::extPath('dam').'lib/class.tx_dam_image.php');
		$files = $damFiles['rows'];
		$ret = array();
		foreach($files As $key => $info ) {
			$ret[] = tx_dam_image::previewImgTag($info['file_path'].$info['file_name'], $size, $addAtrr);
		}
		return $ret;
	}
	static function createThumbnails10($damFiles, $size, $addAttr) {
		require_once(t3lib_extMgm::extPath('dam').'lib/class.tx_dam.php');
		$files = $damFiles['rows'];
		$ret = array();
		foreach($files As $key => $info ) {
			$thumbScript = $GLOBALS['BACK_PATH'].'thumbs.php';
			$filepath = tx_dam::path_makeAbsolute($info['file_path']);
			$ret[] = t3lib_BEfunc::getThumbNail($thumbScript, $filepath.$info['file_name'], $addAttr, $size);
		}
		return $ret;
	}

	/**
	 * Returns the TCA description for a DAM media field
	 *
	 *	$options = array(
	 *			'label' => 'Ein Bild',
	 *			'config' => array(
	 *					'maxitems' => 2,
	 *					'size' => 2,
	 *				),
	 *		)
	 *
	 * @param array $ref
	 * @param array $options	These options are merged into the resulting TCA
	 * @return array
	 */
	public static function getMediaTCA($ref, $options=array()) {
		// $options war früher ein String. Daher muss auf String getestet werden.
		$type = 'image';
		if(is_string($options))
			$type = $options;
		if(is_array($options)) {
			$type = isset($options['type']) ? $options['type'] : $type;
			unset($options['type']);
		}

		$tca = array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.images',
			'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig($ref, array(
				'appearance' => array(
					'createNewRelationLinkTitle' => 'LLL:EXT:cms/locallang_ttc.xlf:images.addFileReference'
				),
				// custom configuration for displaying fields in the overlay/reference table
				// to use the imageoverlayPalette instead of the basicoverlayPalette
				'foreign_types' => array(
					'0' => array(
						'showitem' => '
							--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
							--palette--;;filePalette'
					),
					\TYPO3\CMS\Core\Resource\File::FILETYPE_TEXT => array(
						'showitem' => '
							--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
							--palette--;;filePalette'
					),
					\TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE => array(
						'showitem' => '
							--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
							--palette--;;filePalette'
					),
					\TYPO3\CMS\Core\Resource\File::FILETYPE_AUDIO => array(
						'showitem' => '
							--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
							--palette--;;filePalette'
					),
					\TYPO3\CMS\Core\Resource\File::FILETYPE_VIDEO => array(
						'showitem' => '
							--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
							--palette--;;filePalette'
					),
					\TYPO3\CMS\Core\Resource\File::FILETYPE_SOFTWARE => array(
						'showitem' => '
							--palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
							--palette--;;filePalette'
					)
				)
			), $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'])
		);
		
		if (!empty($tca) && is_array($options)) {
			foreach ($options as $key=>$option) {
				if (is_array($option)) {
					if (!isset($tca[$key])) $tca[$key] = array();
					foreach ($option as $subkey=>$suboption) $tca[$key][$subkey] = $suboption;
				}
				else $tca[$key] = $option;
			}
		}
		return $tca;
	}

	/**
	 * Add a reference to a DAM media file
	 *
	 * @param string $tableName
	 * @return int 
	 */
	public static function addReference($tableName, $fieldName, $itemId, $uid) {
		$data = array();
		$data['uid_foreign'] = $itemId;
		$data['uid_local'] = $uid;
		$data['tablenames'] = $tableName;
		$data['ident'] = $fieldName;

		$id = tx_rnbase_util_DB::doInsert('tx_dam_mm_ref',$data);
		
		// Now count all items
		self::updateImageCount($tableName, $fieldName, $itemId);
		
		return $id;
	}

	/**
	 * Removes dam references. If no parameter is given, all references will be removed.
	 *
	 * @param string $uids commaseperated uids
	 */
	public static function deleteReferences($tableName, $fieldName, $itemId, $uids = '') {

		$where = 'tablenames=\'' . $tableName . '\' AND ident=\'' . $fieldName .'\' AND uid_foreign=' . $itemId;
		if(strlen(trim($uids))) {
			$uids = implode(',',t3lib_div::intExplode(',',$uids));
			$where .= ' AND uid_local IN (' . $uids .') ';
		}
		tx_rnbase_util_DB::doDelete('tx_dam_mm_ref',$where);
		// Jetzt die Bildanzahl aktualisieren
		self::updateImageCount($tableName, $fieldName, $itemId);
	}

	/**
	 * die Bildanzahl aktualisieren
	 *
	 */
	public static function updateImageCount($tableName, $fieldName, $itemId) {
		$values = array();
		$values[$fieldName] = self::getImageCount($tableName, $fieldName, $itemId);		
		tx_rnbase_util_DB::doUpdate($tableName,'uid='.$itemId,$values,0);
	}
	/**
	 * Get picture count
	 * @return int
	 */
	public static function getImageCount($tableName, $fieldName, $itemId) {
		$options['where'] = 'tablenames=\'' . $tableName . '\' AND ident=\'' . $fieldName .'\' AND uid_foreign=' . $itemId;
		$options['count'] = 1;
		$options['enablefieldsoff'] = 1;
		$ret = tx_rnbase_util_DB::doSelect('count(*) AS \'cnt\'', 'tx_dam_mm_ref', $options, 0);
		$cnt = count($ret) ? intval($ret[0]['cnt']) : 0;
		return $cnt;
	}

	/**
	 * Return all references for the given reference data
	 * 
	 * @param string $refTable
	 * @param string $refField
	 * @return array
	 */
	public static function getReferences($refTable, $refUid, $refField) {
		require_once(t3lib_extMgm::extPath('dam') . 'lib/class.tx_dam_db.php');
		return tx_dam_db::getReferencedFiles($refTable, $refUid, $refField);
	}
	
	/**
	 * Return file info for all references for the given reference data
	 * 
	 * @param string $refTable
	 * @param string $refField
	 * @return array
	 */
	public static function getReferencesFileInfo($refTable, $refUid, $refField) {
		$refs = self::getReferences($refTable, $refUid, $refField);
		$res = array();
		if (isset($refs['rows']) && count($refs['rows'])) {
			foreach ($refs['rows'] as $uid=>$record) {
				$fileInfo = self::getFileInfo($record);
				if (isset($refs['files'][$uid]))
					$fileInfo['file_path_name'] = $refs['files'][$uid];
				$fileInfo['file_abs_url'] = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $fileInfo['file_path_name'];
				$res[$uid] = $fileInfo;
			}
		}
		return $res;
	}
	
	/**
	 * Return first reference for the given reference data
	 * 
	 * @param string $refTable
	 * @param int $refUid
	 * @param string $refField
	 * @return array
	 */
	static public function getFirstReference($refTable, $refUid, $refField) {
		$refs = self::getReferences($refTable, $refUid, $refField);
		
		if (!empty($refs)) {
			$res = array();
			// Loop through all data ...
			foreach ($refs as $key=>$data) {
				// ... and use only the first record WITH its uid!
				$uid = key($refs[$key]);
				$res[$key] = array($uid => $data[$uid]);
			}
			return $res;
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/util/class.tx_rnbase_util_TSDAM.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/util/class.tx_rnbase_util_TSDAM.php']);
}

?>