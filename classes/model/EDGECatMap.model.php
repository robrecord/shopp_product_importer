<?php
/**
 * EDGE Category Map class
 *
 * @author Rob Record
 * @package shopp
 * @subpackage importer-catmap
 **/

class EDGECatMap extends DatabaseObject {
	static $table = "edge_category_map";

	// var $categories = array();
	// var $outofstock = true;

	function __construct ($type="category_map") {
		global $Shopp;
		$this->init(self::$table);
		$this->type = $type;
		// $this->outofstock = ($Shopp->Settings->get('outofstock_catalog') == "on");
	}

	/**
	 * Load categories from the catalog index
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $loading (optional) Loading options for building the query
	 * @param boolean $showsmart (optional) Include smart categories in the listing
	 * @param boolean $results (optional) Return the raw structure of results without aggregate processing
	 * @return boolean|object True when categories are loaded and processed, object of results when $results is set
	 **/
	function load_categories ($loading=array(),$showsmart=false,$results=false) {
		$db = DB::get();
		$category_table = DatabaseObject::tablename(Category::$table);
		$product_table = DatabaseObject::tablename(Product::$table);
		$price_table = DatabaseObject::tablename(Price::$table);

		$defaults = array(
			'columns' => "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug,count(DISTINCT pd.id) AS total,IF(SUM(IF(pt.inventory='off',1,0) OR pt.inventory IS NULL)>0,'off','on') AS inventory, SUM(pt.stock) AS stock",
			'where' => array(),
			'joins' => array(
				"LEFT JOIN $this->_table AS sc ON sc.parent=cat.id AND sc.type='category'",
				"LEFT JOIN $product_table AS pd ON sc.product=pd.id",
				"LEFT JOIN $price_table AS pt ON pt.product=pd.id AND pt.type != 'N/A'"
			),
			'limit' => false,
			'orderby' => 'name',
			'order' => 'ASC',
			'parent' => false,
			'ancestry' => false,
			'outofstock' => $this->outofstock
		);
		$options = array_merge($defaults,$loading);
		extract($options);

		if (!is_array($where)) $where = array($where);

		if (!$outofstock) $where[] = "(pt.inventory='off' OR (pt.inventory='on' AND pt.stock > 0))";

		if ($parent !== false) $where[] = "cat.parent=".$parent;
		else $parent = 0;

		if ($ancestry) {
			if (!empty($where))	$where = array("cat.id IN (SELECT parent FROM $category_table WHERE parent != 0) OR (".join(" AND ",$where).")");
			else $where = array("cat.id IN (SELECT parent FROM $category_table WHERE parent != 0)");
		}

		switch(strtolower($orderby)) {
			case "id": $orderby = "cat.id"; break;
			case "slug": $orderby = "cat.slug"; break;
			case "count": $orderby = "total"; break;
			default: $orderby = "cat.name";
		}

		switch(strtoupper($order)) {
			case "DESC": $order = "DESC"; break;
			default: $order = "ASC";
		}

		if ($limit !== false) $limit = "LIMIT $limit";

		$joins = join(' ',$joins);
		if (!empty($where)) $where = "WHERE ".join(' AND ',$where);
		else $where = false;

		$query = "SELECT $columns FROM $category_table AS cat $joins $where GROUP BY cat.id ORDER BY cat.parent DESC,cat.priority,$orderby $order $limit";
		$categories = $db->query($query,AS_ARRAY);

		if (count($categories) > 1) $categories = sort_tree($categories, $parent);
		if ($results) return $categories;

		foreach ($categories as $category) {
			$category->outofstock = false;
			if (isset($category->inventory)) {
				if ($category->inventory == "on" && $category->stock == 0)
					$category->outofstock = true;

				if (!$this->outofstock && $category->outofstock) continue;
			}
			$id = '_'.$category->id;

			$this->categories[$id] = new Category();
			$this->categories[$id]->populate($category);

			if (isset($category->depth))
				$this->categories[$id]->depth = $category->depth;
			else $this->categories[$id]->depth = 0;

			if (isset($category->total))
				$this->categories[$id]->total = $category->total;
			else $this->categories[$id]->total = 0;

			if (isset($category->stock))
				$this->categories[$id]->stock = $category->stock;
			else $this->categories[$id]->stock = 0;


			if (isset($category->outofstock))
				$this->categories[$id]->outofstock = $category->outofstock;

			$this->categories[$id]->_children = false;
			if (isset($category->total)
				&& $category->total > 0 && isset($this->categories[$category->parent])) {
				$ancestor = $category->parent;

				// Recursively flag the ancestors as having children
				while (isset($this->categories[$ancestor])) {
					$this->categories[$ancestor]->_children = true;
					$ancestor = $this->categories[$ancestor]->parent;
				}
			}

		}


		return true;
	}

	/**
	 * Load a any category from the catalog including smart categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param string|int $category The identifying element of a category (by id/slug or uri)
	 * @param array $options (optional) Any shopp() tag-compatible options to pass on to smart categories
	 * @return object The loaded Category object
	 **/
	function load_category ($category,$options=array()) {
		global $Shopp;
		$SmartCategories = array_reverse($Shopp->SmartCategories);
		foreach ($SmartCategories as $SmartCategory) {
			$SmartCategory_slug = get_class_property($SmartCategory,'_slug');
			if ($category == $SmartCategory_slug)
				return new $SmartCategory($options);
		}

		$key = "id";
		if (!preg_match("/^\d+$/",$category)) $key = "uri";
		return new Category($category,$key);

	}



} // END class Catalog

?>