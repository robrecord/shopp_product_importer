<?php

/**
*
*/

class ShoppData
{
	public function __construct( $object = null, $properties = array() )
	{
		if( is_object($object) ) $this->_copy( $object );
		foreach ( $properties as $key => $value )
			$this->$key = $value;
	}

	public function set($key, $value=null)
	{
		if( is_array($key) ) foreach( $key as $k => $v) $this->set($k, $v);
		else $this->$key = $value;
		return $this;
	}

	public function _copy( &$object )
	{
		foreach ( array_keys( (array) $this ) as $key )
			if( isset( $object->$key ) && !is_null( $object->$key ) )
				$this->$key = $object->$key;
		return $this;
	}

	function createInstance($className, array $arguments = array())
	{
	    if(class_exists($className)) {
	        return call_user_func_array(array(
	            new ReflectionClass($className), 'newInstance'),
	            $arguments);
	    }
	    return false;
	}
}

class DatedShoppData extends ShoppData
{
	public $created;
	public $modified;

	function __construct() {
		call_user_func_array(array('parent', '__construct'), func_get_args());
		foreach ( array( 'created', 'modified' ) as $var)
			if( empty($this->$var) ) $this->$var = date('Y-m-d H:i:s');
	}
}

class ShoppMeta extends DatedShoppData
{
	public $parent = 0;
	public $context = 'product';
	public $type = 'meta';
	public $name;
	public $value;
	public $numeral = 0.0;
	public $sortorder = 0;

	public function parent($parent_id)
	{
		$this->parent = (int) $parent_id;
	}
}

class ShoppSettingMeta extends ShoppMeta
{
	public $context = 'shopp';
	public $type = 'setting';
}


class ShoppProductImageMeta extends ShoppMeta
{
	public $type = 'image';
	public $name = 'original';
}

class ShoppImageSettingMeta extends ShoppMeta
{
	public $context = 'setting';
	public $type = 'image_setting';
}

class ShoppSpecMeta extends ShoppMeta
{
	public $type = 'spec';
}

class ShoppProductMeta extends ShoppMeta
{
	// public $context = 'product';
}

class ShoppCategoryMeta extends ShoppMeta
{
	public $context = 'category';
}

class ShoppPriceMeta extends ShoppMeta
{
	public $context = 'price';
}

class ShoppProductSpecMeta extends ShoppSpecMeta
{
	// public $context = 'product';
}

class ShoppCategorySpecMeta extends ShoppSpecMeta
{
	public $context = 'category';
}

class ShoppCategorySpecMetaRows extends ShoppData
{
	public $spectemplate;
	public $facetedmenus;
	public $variations;
	public $pricerange;
	public $priceranges;
	public $specs;
	public $options;
	public $prices;
	public $priority = 0;

	function __construct() {
		call_user_func_array(array('parent', '__construct'), func_get_args());
		foreach ( array( 'options', 'prices' ) as $var)
			if( empty($this->$var) ) $this->$var = serialize(array());
	}
}

class ShoppPriceRow extends DatedShoppData
{
	public $product		= 0;
	public $context		= 'price';
	public $type		= 0;
	public $optionkey	= 0;
	public $label		= '';
	public $sku			= '';
	public $price		= 0.0;
	public $saleprice	= 0.0;
	public $promoprice	= 0.0;
	public $cost		= 0.0;
	public $shipfee		= 0.0;
	public $stock		= 0;
	public $stocked		= 0;
	public $inventory;
	public $sale;
	public $shipping;
	public $tax;
	public $discounts	= '';
	public $sortorder	= 0;

	public function _copy( &$object )
	{
		parent::_copy( $object );
		$this->stocked = $this->stock;
		return $this;
	}
}

class ShoppSummaryRow extends ShoppData
{
	public $product = 0;
	public $sold = 0;
	public $grossed = 0.0;
	public $maxprice = 0.0;
	public $minprice = 0.0;
	public $ranges;
	public $taxed = null;
	public $lowstock;
	public $stock;
	public $inventory = 0;
	public $featured;
	public $variants;
	public $addons;
	public $sale;
	public $freeship;
	public $modified;

	function __construct() {
		call_user_func_array(array('parent', '__construct'), func_get_args());
		if( empty($this->modified) ) $this->modified = date('Y-m-d H:i:s');
	}

}
