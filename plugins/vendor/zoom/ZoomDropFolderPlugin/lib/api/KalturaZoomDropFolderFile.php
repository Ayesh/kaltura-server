<?php
/**
 * @package plugins.ZoomDropFolder
 * @subpackage api.objects
 */
class KalturaZoomDropFolderFile extends KalturaDropFolderFile
{
	/**
	 * @var KalturaMeetingMetadata
	 */
	public $meetingMetadata;
	
	/**
	 * @var KalturaRecordingFile
	 */
	public $recordingFile;
	
	/**
	 * @var string
	 */
	public $parentEntryId;
	
	/**
	 * @var bool
	 */
	public $isParentEntry;

	/*
	 * mapping between the field on this object (on the left) and the setter/getter on the entry object (on the right)
	 */
	private static $map_between_objects = array(
		'meetingMetadata',
		'recordingFile',
		'parentEntryId',
		'isParentEntry'
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	public function toObject($dbObject = null, $skip = array())
	{
		if (!$dbObject)
			$dbObject = new ZoomDropFolderFile();

		return parent::toObject($dbObject, $skip);
	}
}
