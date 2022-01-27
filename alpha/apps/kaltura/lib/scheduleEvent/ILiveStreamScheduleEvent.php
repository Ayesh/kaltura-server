<?php

interface ILiveStreamScheduleEvent extends IScheduleEvent
{
	public function getSourceEntryId();
	public function getPreStartEntryId();
	public function getPostEndEntryId();
	public function isInterruptibleNow();
	public function getEventTransitionTimes();
}