<?php
/**
 * @package plugins.vendor
 */
abstract class KVendorDropFolderEngine extends KDropFolderFileTransferEngine
{
	const MAX_PUSER_LENGTH = 100;
	const TAG_SOURCE = "source";
	const SOURCE_FLAVOR_ID = 0;
	
	abstract protected function getDefaultUserString();
	
	abstract protected function getEntryOwnerId($vendorIntegration, $hostEmail);
	
	protected function excludeRecordingIngestForUser($vendorIntegration, $hostEmail, $groupParticipationType, $optInGroupNames, $optOutGroupNames)
	{
		$partnerId = $this->dropFolder->partnerId;
		
		$userId = $this->getEntryOwnerId($vendorIntegration, $hostEmail);
		if (!$userId)
		{
			KalturaLog::err("Could not find user [$hostEmail]");
			return true;
		}
		
		if ($groupParticipationType == kVendorGroupParticipationType::OPT_IN)
		{
			KalturaLog::debug('Account is configured to OPT IN the users that are members of the following groups ['.print_r($optInGroupNames, true).']');
			return $this->isUserNotMemberOfGroups($userId, $partnerId, $optInGroupNames);
		}
		elseif ($groupParticipationType == kVendorGroupParticipationType::OPT_OUT)
		{
			KalturaLog::debug('Account is configured to OPT OUT the users that are members of the following groups ['.print_r($optOutGroupNames, true).']');
			return $this->isUserNotMemberOfGroups($userId, $partnerId, $optOutGroupNames);
		}
	}
	
	protected function isUserNotMemberOfGroups($userId, $partnerId, $participationGroupList)
	{
		$userFilter = new KalturaGroupUserFilter();
		$userFilter->userIdEqual = $userId;
		
		KBatchBase::impersonate($partnerId);
		$userGroupsResponse = KBatchBase::$kClient->groupUser->listAction($userFilter);
		KBatchBase::unimpersonate();
		
		$userGroupsArray = $userGroupsResponse->objects;
		
		$userGroupNamesArray = array();
		foreach ($userGroupsArray as $group)
		{
			array_push($userGroupNamesArray, $group->groupId);
		}
		
		KalturaLog::debug('User with id ['.$userId.'] is a member of the following groups ['.print_r($userGroupNamesArray, true).']');
		
		$intersection = array_intersect($userGroupNamesArray, $participationGroupList);
		return empty($intersection);
	}
	
	protected function addEntryToCategory($categoryName, $entryId, $partnerId)
	{
		$categoryId = $this->findCategoryIdByName($categoryName);
		if ($categoryId)
		{
			$this->addCategoryEntry($categoryId, $entryId);
		}
	}
	
	protected function findCategoryIdByName($categoryName)
	{
		$isFullPath = $this->isFullPath($categoryName);
		
		$categoryFilter = new KalturaCategoryFilter();
		if ($isFullPath)
		{
			$categoryFilter->fullNameEqual = $categoryName;
		}
		else
		{
			$categoryFilter->nameOrReferenceIdStartsWith = $categoryName;
		}
		
		$categoryResponse = KBatchBase::$kClient->category->listAction($categoryFilter, new KalturaFilterPager());
		$categoryId = null;
		if ($isFullPath)
		{
			if ($categoryResponse->objects && count($categoryResponse->objects) == 1)
			{
				$categoryId = $categoryResponse->objects[0]->id;
			}
		}
		else
		{
			$categoryIds = array();
			foreach ($categoryResponse->objects as $category)
			{
				if ($category->name === $categoryName)
				{
					$categoryIds[] = $category->id;
				}
			}
			$categoryId = (count($categoryIds) == 1) ? $categoryIds[0] : null;
		}
		return $categoryId;
	}
	
	protected function isFullPath($categoryName)
	{
		$numCategories = count(explode('>', $categoryName));
		return ($numCategories > 1);
	}
	
	protected function addCategoryEntry($categoryId, $entryId)
	{
		$categoryEntry = new KalturaCategoryEntry();
		$categoryEntry->categoryId = $categoryId;
		$categoryEntry->entryId = $entryId;
		KBatchBase::$kClient->categoryEntry->add($categoryEntry);
	}
	
	protected function getKalturaUserIdsFromVendorUsers($vendorUsers, $partnerId, $createIfNotFound, $userToExclude)
	{
		if (!$vendorUsers)
		{
			return $vendorUsers;
		}
		
		$userIdsList = array();
		foreach ($vendorUsers as $vendorUser)
		{
			/* @var $vendorUser kVendorUser */
			/* @var $kalturaUser KalturaUser */
			$kalturaUser = $this->getKalturaUser($partnerId, $vendorUser);
			if ($kalturaUser)
			{
				if (strtolower($kalturaUser->id) !== $userToExclude)
				{
					$userIdsList[] = $kalturaUser->id;
				}
			}
			elseif ($createIfNotFound)
			{
				$this->createNewVendorUser($partnerId, $vendorUser->getProcessedName());
				$userIdsList[] = $vendorUser->getProcessedName();
			}
		}
		return $userIdsList;
	}
	
	protected function getKalturaUser($partnerId, kVendorUser $vendorUser)
	{
		$pager = new KalturaFilterPager();
		$pager->pageSize = 1;
		$pager->pageIndex = 1;
		
		$filter = new KalturaUserFilter();
		$filter->partnerIdEqual = $partnerId;
		$filter->idEqual = $vendorUser->getProcessedName();
		$kalturaUser = KBatchBase::$kClient->user->listAction($filter, $pager);
		if (!$kalturaUser->objects)
		{
			$email = $vendorUser->getOriginalName();
			$filterUser = new KalturaUserFilter();
			$filterUser->partnerIdEqual = $partnerId;
			$filterUser->emailStartsWith = $email;
			$kalturaUser = KBatchBase::$kClient->user->listAction($filterUser, $pager);
			if (!$kalturaUser->objects || strcasecmp($kalturaUser->objects[0]->email, $email) != 0)
			{
				return null;
			}
		}
		
		if($kalturaUser->objects)
		{
			return $kalturaUser->objects[0];
		}
		return null;
	}
	
	protected function createNewVendorUser($partnerId, $puserId)
	{
		if (!is_null($puserId))
		{
			$puserId = substr($puserId, 0, self::MAX_PUSER_LENGTH);
		}
		
		$user = new KalturaUser();
		$user->id = $puserId;
		$user->screenName = $puserId;
		$user->firstName = $puserId;
		$user->isAdmin = false;
		$user->type = KalturaUserType::USER;
		$kalturaUser = KBatchBase::$kClient->user->add($user);
		return $kalturaUser;
	}
	
	protected function handleParticipants($entry, $userIdsList, $handleParticipantMode)
	{
		if ($handleParticipantMode == kHandleParticipantsMode::IGNORE)
		{
			return $entry;
		}
		
		if ($userIdsList)
		{
			switch ($handleParticipantMode)
			{
				case kHandleParticipantsMode::ADD_AS_CO_PUBLISHERS:
					$entry->entitledUsersPublish = implode(',', array_unique($userIdsList));
					break;
				case kHandleParticipantsMode::ADD_AS_CO_VIEWERS:
					$entry->entitledUsersView = implode(',', array_unique($userIdsList));
					break;
				default:
					break;
			}
		}
		
		return $entry;
	}
	
	protected function createFlavorAssetForEntry($entryId)
	{
		$kFlavorAsset = new KalturaFlavorAsset();
		$kFlavorAsset->tags = self::TAG_SOURCE;
		$kFlavorAsset->flavorParamsId = self::SOURCE_FLAVOR_ID;
		$kFlavorAsset->fileExt = strtolower($this->dropFolderFile->fileExtension);
		return KBatchBase::$kClient->flavorAsset->add($entryId, $kFlavorAsset);
	}
}
