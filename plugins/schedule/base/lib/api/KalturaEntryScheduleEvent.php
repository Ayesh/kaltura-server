<?php
/**
 * @package plugins.schedule
 * @subpackage api.objects
 * @abstract
 */
abstract class KalturaEntryScheduleEvent extends KalturaScheduleEvent
{
	/**
	 * Entry to be used as template during content ingestion
	 * @var string
	 * @filter eq
	 */
	public $templateEntryId;

	/**
	 * Entries that associated with this event
	 * @var string
	 * @filter like,mlikeor,mlikeand
	 */
	public $entryIds;
	
	/**
	 * Categories that associated with this event
	 * @var string
	 * @filter like,mlikeor,mlikeand
	 */
	public $categoryIds;

	/**
	 * Blackout schedule events the conflict with this event
	 * @readonly
	 * @var KalturaScheduleEventArray
	 */
	public $blackoutConflicts;

	/*
	 * Mapping between the field on this object (on the left) and the setter/getter on the entry object (on the right)  
	 */
	private static $map_between_objects = array 
	 (	
		'templateEntryId',
		'entryIds',
		'categoryIds',
		'blackoutConflicts',
	 );
		 
	/* (non-PHPdoc)
	 * @see KalturaObject::getMapBetweenObjects()
	 */
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

    public function validate($startDate, $endDate)
    {
        if ($this->recurrenceType === ScheduleEventRecurrenceType::RECURRENCE) {
            throw new KalturaAPIException(KalturaErrors::INVALID_ENUM_VALUE, $this->recurrenceType, 'recurrenceType', 'KalturaScheduleEventRecurrenceType');
        }

        if ($startDate > $endDate) {
            throw new KalturaAPIException(KalturaScheduleErrors::INVALID_SCHEDULE_END_BEFORE_START, $startDate, $endDate);
        }

        $maxDuration = SchedulePlugin::getScheduleEventmaxDuration();
        if (($endDate - $startDate) > $maxDuration) {
            throw new KalturaAPIException(KalturaScheduleErrors::MAX_SCHEDULE_DURATION_REACHED, $maxDuration);
        }

        if ($this->recurrenceType == KalturaScheduleEventRecurrenceType::NONE)
        {
            $events = ScheduleEventPeer::retrieveOtherEvents($this->templateEntryId, $startDate, $endDate, array($this->id));

            if (count($events) > 0) {
                throw new KalturaAPIException(KalturaScheduleErrors::SCHEDULE_TIME_IN_USE);
            }
        }
    }
}