<?php
/**
 * @package plugins.viewHistory
 * @subpackage model
 */
class ViewHistoryUserEntry extends UserEntry
{
	const CUSTOM_DATA_PLAYBACK_CONTEXT = 'playbackContext';
	const CUSTOM_DATA_LAST_TIME_REACHED = 'lastTimeReached';
	const CUSTOM_DATA_LAST_UPDATE_TIME = 'lastUpdateTime';
	const CUSTOM_DATA_LAST_ENTRY_ID = 'lastEntryId';
	
	public function __construct()
	{
		$this->setType(ViewHistoryPlugin::getViewHistoryUserEntryTypeCoreValue(ViewHistoryUserEntryType::VIEW_HISTORY));
		parent::__construct();
	}
	
	public function getPlaybackContext ()					{return $this->getFromCustomData(self::CUSTOM_DATA_PLAYBACK_CONTEXT);}
	public function getLastTimeReached ()					{return $this->getFromCustomData(self::CUSTOM_DATA_LAST_TIME_REACHED);}
	public function getLastUpdateTime ()					{return $this->getFromCustomData(self::CUSTOM_DATA_LAST_UPDATE_TIME);}
	public function getLastEntryId ()						{return $this->getFromCustomData(self::CUSTOM_DATA_LAST_ENTRY_ID);}
	
	public function setPlaybackContext ($v)					{return $this->putInCustomData(self::CUSTOM_DATA_PLAYBACK_CONTEXT, $v);}
	public function setLastTimeReached ($v)					{return $this->putInCustomData(self::CUSTOM_DATA_LAST_TIME_REACHED, $v);}
	public function setLastEntryId ($v)						{return $this->putInCustomData(self::CUSTOM_DATA_LAST_ENTRY_ID, $v);}
	
}