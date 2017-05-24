<?php

class UltraSearchEntryItem extends UltraSearchItem
{

	/**
	 * @var UltraSearchEntryFieldName
	 */
	protected $fieldName;

	/**
	 * @var string
	 */
	protected $searchTerm;

	/**
	 * @return UltraSearchEntryFieldName
	 */
	public function getFieldName()
	{
		return $this->fieldName;
	}

	/**
	 * @param UltraSearchEntryFieldName $fieldName
	 */
	public function setFieldName($fieldName)
	{
		switch ($fieldName)
		{
			case UltraSearchEntryFieldName::ENTRY_DESCRIPTION:
				$fieldName = 'ENTRY_DESCRIPTION';
				break;
			case UltraSearchEntryFieldName::ENTRY_NAME:
				$fieldName = 'ENTRY_NAME';
				break;
		}
		$this->fieldName = $fieldName;
	}

	/**
	 * @return string
	 */
	public function getSearchTerm()
	{
		return $this->searchTerm;
	}

	/**
	 * @param string $searchTerm
	 */
	public function setSearchTerm($searchTerm)
	{
		$this->searchTerm = $searchTerm;
	}

	public function createSearchQuery()
	{
		return kUltraQueryManager::createEntrySearchQuery($this);
	}


}