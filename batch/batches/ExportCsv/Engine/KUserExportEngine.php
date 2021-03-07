<?php
/**
 * @package Scheduler
 * @subpackage ExportCsv
 */
class KUserExportEngine extends KObjectExportEngine
{
	protected function getMappedFieldsAsAssociativeArray($mappedFields)
	{
		$ret = array();
		foreach($mappedFields as $mappedField)
		{
			$ret[$mappedField->key] = $mappedField->value;
		}
		return $ret;
	}
	
	public function fillCsv(&$csvFile, &$data)
	{
		KalturaLog::info ('Exporting content for user items');
		$filter = clone $data->filter;
		$pager = new KalturaFilterPager();
		$pager->pageSize = 500;
		$pager->pageIndex = 1;
		$mappedFields = $this->getMappedFieldsAsAssociativeArray($data->mappedFields);
		$additionalFields = $data->additionalFields;
		$this->addHeaderRowToCsv($csvFile,$additionalFields, $mappedFields);

		$lastCreatedAtObjectIdList = array();
		$lastCreatedAt=0;
		$totalCount=0;
		$filter->orderBy = KalturaUserOrderBy::CREATED_AT_ASC;
		do
		{
			if($lastCreatedAt)
			{
				$filter->createdAtGreaterThanOrEqual = $lastCreatedAt;
			}
			try
			{
				$userList = KBatchBase::$kClient->user->listAction($filter, $pager);
				$returnedSize = $userList->objects ? count($userList->objects) : 0;
			}
			catch(Exception $e)
			{
				KalturaLog::info("Couldn't list users on page: [$pager->pageIndex]" . $e->getMessage());
				$this->apiError = $e;
				return;
			}
			
			$lastObject = $userList->objects[$returnedSize-1];
			$lastCreatedAt=$lastObject->createdAt;
			$newCreatedAtListObject = array();
			
			//contain only the users that are were not the former list
			$uniqUsers = array();
			foreach ($userList->objects as $user)
			{
				if(!in_array($user->id, $lastCreatedAtObjectIdList))
					$uniqUsers[]=$user;
			}
			//Prepare list of the last second users to avoid duplicate in the next iteration
			foreach ($uniqUsers as $user)
			{
				if($user->createdAt == $lastCreatedAt)
					$newCreatedAtListObject[]=$user->id;
			}
			$lastCreatedAtObjectIdList = $newCreatedAtListObject;
			$this->addUsersToCsv($uniqUsers,
			                     $csvFile,
			                     $data->metadataProfileId,
			                     $additionalFields,
			                     $mappedFields);
			$totalCount+=count($uniqUsers);
			KalturaLog::debug("Adding More  - ".count($uniqUsers). " totalCount - ". $totalCount);
			unset($newCreatedAtListObject);
			unset($uniqUsers);
			unset($userList);
			if(function_exists('gc_collect_cycles')) // php 5.3 and above
				gc_collect_cycles();
		}
		while ($pager->pageSize == $returnedSize);
	}
	
	/**
	 * Generate the first csv row containing the fields
	 */
	protected function addHeaderRowToCsv($csvFile, $additionalFields,
	                                     $mappedFields = null)
	{
		$headerRow = 'User ID,First Name,Last Name,Email';
		foreach ($additionalFields as $field)
			$headerRow .= ','.$field->fieldName;
		
		foreach ($mappedFields as $key => $value)
		{
			$headerRow .= ',' . $key;
		}
		
		KCsvWrapper::sanitizedFputCsv($csvFile, explode(',', $headerRow));
		
		return $csvFile;
	}
	
	/**
	 * The function grabs all the fields values for each user and adding them as a new row to the csv file
	 */
	protected function addUsersToCsv(&$users,
	                                 &$csvFile,
	                                 $metadataProfileId,
	                                 $additionalFields,
	                                 $mappedFields)
	{
		if(!$users)
			return ;
		
		$userIds = array();
		$userIdToRow = array();
		
		foreach ($users as $user)
		{
			$userIds[] = $user->id;
			$userIdToRow = $this->initializeCsvRowValues($user,
			                                             $additionalFields,
			                                             $userIdToRow,
			                                             $mappedFields);
		}
		
		if($metadataProfileId)
		{
			$usersMetadataObjects = $this->retrieveUsersMetadata($userIds, $metadataProfileId);
			if ($usersMetadataObjects)
			{
				$userIdToRow = $this->fillAdditionalFieldsFromMetadata($usersMetadataObjects, $additionalFields, $userIdToRow);
			}
		}
		
		
		foreach ($userIdToRow as $key=>$val)
		{
			KCsvWrapper::sanitizedFputCsv($csvFile, $val);
		}
	}
	
	/**
	 * adds the default fields values and the additional fields as nulls
	 */
	protected function initializeCsvRowValues($user, $additionalFields,
	                                          $userIdToRow,$mappedFields)
	{
		$defaultRowValues = array(
			'id' => $user->id,
			'firstName' => $user->firstName,
			'lastName' => $user->lastName,
			'email' =>$user->email
		);
		
		//add mapped fields
		foreach($mappedFields as $key=>$value)
		{
			if(!isset($value))//if only key
			{
				$defaultRowValues[$key] = $user->$key;
			}
			else
			{
				$fieldMap = explode(':',$value);
				if(count($fieldMap)==1)//if simple value
				{
					$defaultRowValues[$key] = $user->$value;
				}
				else //if value maps to a sub fields
				{
					$fieldName = $fieldMap[0];
					$subFieldName = $fieldMap[1];
					$fieldValues = json_decode($user->$fieldName);
					if($fieldValues)
					{
						$defaultRowValues[$key] = $fieldValues -> $subFieldName;
					}
				}
			}
		}
		
		$additionalKeys = array();
		foreach ($additionalFields as $field)
			$additionalKeys[] = $field->fieldName;
		$additionalRowValues = array_fill_keys($additionalKeys, null);
		$row = array_merge($defaultRowValues, $additionalRowValues);
		
		$userIdToRow[$user->id] = $row;
		
		return $userIdToRow;
	}
	
	
	
	/**
	 * Retrieve all the metadata objects for all the users in specific page
	 */
	protected function retrieveUsersMetadata($userIds, $metadataProfileId)
	{
		$result = array();
		$pager = new KalturaFilterPager();
		$pager->pageSize = 500;
		$pager->pageIndex = 0;
		$filter = new KalturaMetadataFilter();
		$filter->objectIdIn = implode(',', $userIds);
		$filter->metadataObjectTypeEqual = MetadataObjectType::USER;
		$filter->metadataProfileIdEqual = $metadataProfileId;
		$metadataClient = KalturaMetadataClientPlugin::get(KBatchBase::$kClient);
		do
		{
			$pager->pageIndex++;

			try
			{
				$ret = $metadataClient->metadata->listAction($filter, $pager);
			}
			catch (Exception $e)
			{
				KalturaLog::info("Couldn't list metadata objects for metadataProfileId: [$metadataProfileId]" . $e->getMessage());
				$this->apiError = $e;
				break;
			}

			if(count($ret->objects))
			{
				$result = array_merge($result, $ret->objects);
			}

		}
		while(count($ret->objects) >= $pager->pageSize);

		return $result;
	}
	
	/**
	 * Extract specific value from xml using given xpath
	 */
	protected function getValueFromXmlElement($xml, $xpath)
	{
		$strValue = null;
		try
		{
			$xmlObj = new SimpleXMLElement($xml);
		}
		catch(Exception $ex)
		{
			return null;
		}
		$value = $xmlObj->xpath($xpath);
		if(is_array($value) && count($value) == 1)
			$strValue = (string)$value[0];
		else if (count($value) == 1)
			KalturaLog::err("Unknown element in the base xml when quering the xpath: [$xpath]");
		
		return $strValue;
	}
	
	/**
	 * the function run over each additional field and returns the value for the given field xpath
	 */
	private function fillAdditionalFieldsFromMetadata($usersMetadataObjects, $additionalFields, $userIdToRow)
	{
		foreach($usersMetadataObjects as $metadataObj)
		{
			foreach ($additionalFields as $field)
			{
				if($field->xpath)
				{
					KalturaLog::info("current field xpath: [$field->xpath]");
					$strValue = $this->getValueFromXmlElement($metadataObj->xml, $field->xpath);
					if($strValue)
					{
						$objectRow = $userIdToRow[$metadataObj->objectId];
						$objectRow[$field->fieldName] = $strValue;
						$userIdToRow[$metadataObj->objectId] = $objectRow;
					}
				}
			}
		}
		return $userIdToRow;
	}
}