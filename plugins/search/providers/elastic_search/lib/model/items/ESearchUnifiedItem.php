<?php
/**
 * @package plugins.elasticSearch
 * @subpackage model.items
 */
class ESearchUnifiedItem extends ESearchItem
{

	const UNIFIED = 'unified';

	const ENTRY_QUERY_GROUP = 'entry';
	const CATEGORY_ENTRY_QUERY_GROUP = 'category_entry';
	const CUE_POINT_QUERY_GROUP = 'cue_point';
	const CAPTIONS_QUERY_GROUP = 'captions';
	const METADATA_QUERY_GROUP = 'metadata';

	public static $unifiedQueryGroups = array(self::ENTRY_QUERY_GROUP, self::CATEGORY_ENTRY_QUERY_GROUP, self::CUE_POINT_QUERY_GROUP, self::CAPTIONS_QUERY_GROUP, self::METADATA_QUERY_GROUP);
	public static $excludedUnifiedQueryGroups = array();
	/**
	 * @var string
	 */
	protected $searchTerm;

	/**
	 * @return string
	 */
	public function getSearchTerm()
	{
		return $this->searchTerm;
	}

	public static function setExcludedUnifiedQueryGroups($excludedUnifiedQueryGroups)
	{
		$excludedUnifiedQueryGroups = preg_replace('/\s+/','',$excludedUnifiedQueryGroups);
		self::$excludedUnifiedQueryGroups = explode(',', $excludedUnifiedQueryGroups);
	}

	/**
	 * @param string $searchTerm
	 */
	public function setSearchTerm($searchTerm)
	{
		$this->searchTerm = $searchTerm;
	}

	public static function createSearchQuery($eSearchItemsArr, $boolOperator, &$queryAttributes, $eSearchOperatorType = null)
	{
		$outQuery = array();

		$allowedUnifiedGroups = array_diff(self::$unifiedQueryGroups, self::$excludedUnifiedQueryGroups);

		foreach ($eSearchItemsArr as $eSearchUnifiedItem)
		{
			self::validateUnifiedAllowedTypes($eSearchUnifiedItem);
			$subQuery = new kESearchBoolQuery();

			foreach ($allowedUnifiedGroups as $unifiedQueryGroup)
			{
				switch ($unifiedQueryGroup)
				{
					case self::ENTRY_QUERY_GROUP:
						self::addEntryFieldsToUnifiedQuery($eSearchUnifiedItem, $subQuery, $queryAttributes);
						break;
					case self::CATEGORY_ENTRY_QUERY_GROUP:
						self::addCategoryEntryFieldsToUnifiedQuery($eSearchUnifiedItem, $subQuery, $queryAttributes);
						break;
					case self::CUE_POINT_QUERY_GROUP:
						self::addCuePointFieldsToUnifiedQuery($eSearchUnifiedItem, $subQuery, $queryAttributes);
						break;
					case self::CAPTIONS_QUERY_GROUP:
						self::addCaptionFieldsToUnifiedQuery($eSearchUnifiedItem, $subQuery, $queryAttributes);
						break;
					case self::METADATA_QUERY_GROUP:
						self::addMetadataFieldsToUnifiedQuery($eSearchUnifiedItem, $subQuery, $queryAttributes);
						break;
				}
			}
			$outQuery[] = $subQuery;
		}
		return $outQuery;
	}

	private static function addEntryFieldsToUnifiedQuery($eSearchUnifiedItem, &$entryUnifiedQuery, &$queryAttributes)
	{
		$entryItems = array();
		$entryAllowedFields = ESearchEntryItem::getAllowedSearchTypesForField();
		//Start handling entry fields
		foreach($entryAllowedFields as $fieldName => $fieldAllowedTypes)
		{
			if (in_array($eSearchUnifiedItem->getItemType(), $fieldAllowedTypes) && in_array(self::UNIFIED, $fieldAllowedTypes))
			{
				$entryItem = new ESearchEntryItem();
				$entryItem->setFieldName($fieldName);
				$entryItem->setSearchTerm($eSearchUnifiedItem->getSearchTerm());
				$entryItem->setItemType($eSearchUnifiedItem->getItemType());
				$entryItem->setAddHighlight($eSearchUnifiedItem->getAddHighlight());
				if($eSearchUnifiedItem->getItemType() == ESearchItemType::RANGE)
					$entryItem->setRange($eSearchUnifiedItem->getRange());
				$entryItems[] = $entryItem;
			}
		}

		if(count($entryItems))
		{
			$entryQueries = ESearchEntryItem::createSearchQuery($entryItems, 'should', $queryAttributes,  null);
			foreach ($entryQueries as $entryQuery)
			{
				$entryUnifiedQuery->addToShould($entryQuery);
			}
		}

	}

	private static function addCategoryEntryFieldsToUnifiedQuery($eSearchUnifiedItem, &$entryUnifiedQuery, &$queryAttributes)
	{
		$categoryEntryItems = array();
		$categoryEntryNameAllowedFields = ESearchCategoryEntryNameItem::getAllowedSearchTypesForField();


		foreach($categoryEntryNameAllowedFields as $fieldName => $fieldAllowedTypes)
		{
			if (in_array($eSearchUnifiedItem->getItemType(), $fieldAllowedTypes) && in_array(self::UNIFIED, $fieldAllowedTypes))
			{
				$categoryEntryItem = new ESearchCategoryEntryNameItem();
				$categoryEntryItem->setFieldName($fieldName);
				$categoryEntryItem->setSearchTerm($eSearchUnifiedItem->getSearchTerm());
				$categoryEntryItem->setItemType($eSearchUnifiedItem->getItemType());
				$categoryEntryItem->setAddHighlight($eSearchUnifiedItem->getAddHighlight());
				$categoryEntryItems[] = $categoryEntryItem;
			}
		}

		$categoryEntryAncestorNameAllowedFields = ESearchCategoryEntryAncestorNameItem::getAllowedSearchTypesForField();
		foreach($categoryEntryAncestorNameAllowedFields as $fieldName => $fieldAllowedTypes)
		{
			if (in_array($eSearchUnifiedItem->getItemType(), $fieldAllowedTypes) && in_array(self::UNIFIED, $fieldAllowedTypes))
			{
				$categoryEntryItem = new ESearchCategoryEntryAncestorNameItem();
				$categoryEntryItem->setFieldName($fieldName);
				$categoryEntryItem->setSearchTerm($eSearchUnifiedItem->getSearchTerm());
				$categoryEntryItem->setItemType($eSearchUnifiedItem->getItemType());
				$categoryEntryItem->setAddHighlight($eSearchUnifiedItem->getAddHighlight());

				$categoryEntryItems[] = $categoryEntryItem;
			}
		}

		if(count($categoryEntryItems))
		{
			$categoryEntryQueries = ESearchBaseCategoryEntryItem::createSearchQuery($categoryEntryItems, 'should', $queryAttributes,  null);
			foreach ($categoryEntryQueries as $categoryEntryQuery)
			{
				$entryUnifiedQuery->addToShould($categoryEntryQuery);
			}
		}

	}

	private static function addCuePointFieldsToUnifiedQuery($eSearchUnifiedItem, &$entryUnifiedQuery, &$queryAttributes)
	{
		$cuePointAllowedFields = ESearchCuePointItem::getAllowedSearchTypesForField();
		$cuePointItems = array();
		//Start handling cue-point fields
		foreach($cuePointAllowedFields as $fieldName => $fieldAllowedTypes)
		{
			if (in_array($eSearchUnifiedItem->getItemType(), $fieldAllowedTypes) && in_array(self::UNIFIED, $fieldAllowedTypes))
			{
				$cuePointItem = new ESearchCuePointItem();
				$cuePointItem->setFieldName($fieldName);
				$cuePointItem->setSearchTerm($eSearchUnifiedItem->getSearchTerm());
				$cuePointItem->setItemType($eSearchUnifiedItem->getItemType());
				$cuePointItem->setAddHighlight($eSearchUnifiedItem->getAddHighlight());
				if($eSearchUnifiedItem->getItemType() == ESearchItemType::RANGE)
					$cuePointItem->setRange($eSearchUnifiedItem->getRange());
				$cuePointItems[] = $cuePointItem;
			}
		}

		if(count($cuePointItems))
		{
			$cuePointQueries = ESearchCuePointItem::createSearchQuery($cuePointItems, 'should', $queryAttributes, null);
			foreach ($cuePointQueries as $cuePointQuery)
			{
				$entryUnifiedQuery->addToShould($cuePointQuery);
			}
		}
	}

	private static function addCaptionFieldsToUnifiedQuery($eSearchUnifiedItem, &$entryUnifiedQuery, &$queryAttributes)
	{
		$captionItems = array();
		$captionAllowedFields = ESearchCaptionItem::getAllowedSearchTypesForField();
		foreach($captionAllowedFields as $fieldName => $fieldAllowedTypes)
		{
			if (in_array($eSearchUnifiedItem->getItemType(), $fieldAllowedTypes) && in_array(self::UNIFIED, $fieldAllowedTypes))
			{
				$captionItem = new ESearchCaptionItem();
				$captionItem->setFieldName($fieldName);
				$captionItem->setSearchTerm($eSearchUnifiedItem->getSearchTerm());
				$captionItem->setItemType($eSearchUnifiedItem->getItemType());
				$captionItem->setAddHighlight($eSearchUnifiedItem->getAddHighlight());
				if($eSearchUnifiedItem->getItemType() == ESearchItemType::RANGE)
					$captionItem->setRange($eSearchUnifiedItem->getRange());
				$captionItems[] = $captionItem;
			}
		}

		if(count($captionItems))
		{
			$captionQueries = ESearchCaptionItem::createSearchQuery($captionItems, 'should', $queryAttributes, null);
			foreach ($captionQueries as $captionQuery)
			{
				$entryUnifiedQuery->addToShould($captionQuery);
			}
		}

	}

	private static function addMetadataFieldsToUnifiedQuery($eSearchUnifiedItem, &$entryUnifiedQuery, &$queryAttributes)
	{
		//metadata is special case - we don't need to check for allowed field types
		$metadataItems = array();
		$metadataItem = new ESearchMetadataItem();
		$metadataItem->setSearchTerm($eSearchUnifiedItem->getSearchTerm());
		$metadataItem->setItemType($eSearchUnifiedItem->getItemType());
		$metadataItem->setAddHighlight($eSearchUnifiedItem->getAddHighlight());
		if($eSearchUnifiedItem->getItemType() == ESearchItemType::RANGE)
			$metadataItem->setRange($eSearchUnifiedItem->getRange());
		$metadataItems[] = $metadataItem;

		if(count($metadataItems))
		{
			$metadataQueries = ESearchMetadataItem::createSearchQuery($metadataItems, 'should', $queryAttributes, null);
			foreach ($metadataQueries as $metadataQuery)
			{
				$entryUnifiedQuery->addToShould($metadataQuery);
			}
		}
	}

	protected static function validateUnifiedAllowedTypes($eSearchUnifiedItem)
	{
		if (in_array($eSearchUnifiedItem->getItemType(), array(ESearchItemType::RANGE, ESearchItemType::EXISTS)))
		{
			$data = array();
			$data['itemType'] = $eSearchUnifiedItem->getItemType();
			throw new kESearchException('Item type ['.$eSearchUnifiedItem->getItemType().']. is not allowed in Unified Search', kESearchException::SEARCH_TYPE_NOT_ALLOWED_ON_UNIFIED_SEARCH, $data);
		}
	}

	public function shouldAddLanguageSearch()
	{

	}

	public function getItemMappingFieldsDelimiter()
	{

	}

}