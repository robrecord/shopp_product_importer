<?php
/**
 * Catalog class
 *
 * Catalog navigational experience data manager
 *
 * @author Jonathan Davis
 * @version 1.1
 * @since 1.0
 * @copyright Ingenesis Limited, 24 June, 2010
 * @package shopp
 * @subpackage storefront
 **/

require_once("EDGECategory.model.php");

class EDGECatalog extends Catalog {
	
	static $table = "edge_catalog";

	function __construct ($type="catalog") {
		global $Shopp;
		$this->init(self::$table);
		$this->type = $type;
		$this->outofstock = ($Shopp->Settings->get('outofstock_catalog') == "on");
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
	function load_categories ($loading=array(),$showsmart=false,$results=false,$edge=true) {
		$db = DB::get();
		$edge_category_map_table = DatabaseObject::tablename(EDGECatMap::$table);
		$edge_catalog_table = DatabaseObject::tablename(EDGECatalog::$table);
		$shopp_catalog_table = DatabaseObject::tablename(Catalog::$table);
		$catalog_table = $edge ? $edge_catalog_table : $shopp_catalog_table;
		$edge_category_table = DatabaseObject::tablename(EDGECategory::$table);
		$shopp_category_table = DatabaseObject::tablename(Category::$table);
		$category_table = $edge ? $edge_category_table : $shopp_category_table;
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
		

		// SELECT edge_cat.id,edge_cat.category,edge_cat.edge_category 
		// FROM wp_shopp_edge_category_map AS edge_cat 
		// LEFT JOIN wp_shopp_category ON edge_cat.category=wp_shopp_category.id
		// WHERE edge_category=990 
		// GROUP BY edge_cat.id 
		// ORDER BY edge_cat.category DESC;

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
			$query = "SELECT shopp_cat.id,shopp_cat.name FROM $edge_category_map_table AS edge_cat LEFT JOIN $shopp_category_table AS shopp_cat ON edge_cat.category=shopp_cat.id WHERE edge_category={$category->id} GROUP BY edge_cat.id ORDER BY edge_cat.category DESC $limit";
			$shopp_categories = $db->query($query,AS_ARRAY);
			$shopp_category_array = array();
			foreach ($shopp_categories as $shopp_category) {
				if ($shopp_category->name) $shopp_category_array[$shopp_category->id] = $shopp_category->name;
			}
			$this->categories[$id]->shopp_categories = new stdClass();
			$this->categories[$id]->shopp_categories->string = implode (', ',$shopp_category_array);
			$this->categories[$id]->shopp_categories->categories = $shopp_category_array;
			
			$query = "SELECT product.id,product.name FROM $edge_catalog_table AS edge_cat LEFT JOIN $product_table AS product ON edge_cat.product=product.id WHERE parent={$category->id} ORDER BY product.id DESC $limit";
			$products = $db->query($query,AS_ARRAY);
			// $this->categories[$id]->products = $products;
			// var_dump($query,$products);
			foreach ($products as $product) {
				// echo $product_id;
				$this->categories[$id]->products[$product->id] = $product->name;
			}
			
		}

		if ($showsmart == "before" || $showsmart == "after")
			$this->smart_categories($showsmart);

		return true;
	}


} // END class Catalog

?>