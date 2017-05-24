<?php
/**
 * @package plugins.elasticSearch
 * @subpackage api.objects
 */
class KalturaUltraSearchCaptionItem extends KalturaUltraSearchItem {

	/**
	 * @var string
	 */
	public $searchTerm;

	private static $map_between_objects = array(
		'searchTerm',
	);

	protected function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	public function toObject($object_to_fill = null, $props_to_skip = array())
	{
		if (!$object_to_fill)
			$object_to_fill = new UltraSearchCaptionItem();
		return parent::toObject($object_to_fill, $props_to_skip);
	}


}
