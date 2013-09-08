<?php
/**
 * Category class
 *
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  9 April, 2008
 * @package shopp
 **/

class EDGECategory extends Category {

	static $table = "edge_category";
	
	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		
		// $this->load_data(array('categories'));
		
		if (!$id) return;
		if ($this->load($id,$key)) return true;
		return false;
	}

	/**
	 * Loads specified relational data associated with the product
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $options List of data to load (prices, images, categories, tags, specs)
	 * @param array $products List of products to load data for
	 * @return void
	 **/
	function load_data ($options=false,&$edge_cats=false) {
		global $Shopp;
		$db =& DB::get();
		
		// Load object schemas on request

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$edge_catmap_table = DatabaseObject::tablename(EDGECatMap::$table);
		$edge_catalog_table = DatabaseObject::tablename(EDGECatalog::$table);


		$Dataset = array();

		if (in_array('categories',$options)) {
			$this->categories = array();
			$Dataset['categories'] = new Category();
			unset($Dataset['categories']->_datatypes['priceranges']);
			unset($Dataset['categories']->_datatypes['specs']);
			unset($Dataset['categories']->_datatypes['options']);
			unset($Dataset['categories']->_datatypes['prices']);
		}

		// Determine the maximum columns to allocate
		$maxcols = 0;
		foreach ($Dataset as $set) {
			$cols = count($set->_datatypes);
			if ($cols > $maxcols) $maxcols = $cols;
		}

		// Prepare category list depending on single cat or entire list
		$ids = array();
		if (isset($edge_cats) && is_array($edge_cats)) {
			foreach ($edge_cats as $edge_cat) $ids[] = $edge_cat->id;
		} else $ids[0] = $this->id;

		// Skip if there are no product ids
		if (empty($ids) || empty($ids[0])) return false;

		// var_dump($Dataset);

		// Build the mega-query
		foreach ($Dataset as $rtype => $set) {

			// Allocate generic columns for record data
			$columns = array(); $i = 0;
			foreach ($set->_datatypes as $key => $datatype)
				$columns[] = ((strpos($datatype,'.')!==false)?"$datatype":"{$set->_table}.$key")." AS c".($i++);
			for ($i = $i; $i < $maxcols; $i++)
				$columns[] = "'' AS c$i";

			$cols = join(',',$columns);

			// Build object-specific selects and UNION them
			$where = "";
			if (isset($query)) $query .= " UNION ";
			else $query = "";
			switch($rtype) {
				case "categories":
				
					foreach ($ids as $id) $where .= ((!empty($where))?" OR ":"")."catalog.edge_category=$id";
					$where = "($where)";
					$query .= "(SELECT '$set->_table' as dataset,catalog.category AS category,'$rtype' AS rtype,$set->_table.name AS alphaorder,0 AS sortorder,$cols FROM $edge_catmap_table AS catalog LEFT JOIN $set->_table ON catalog.category=$set->_table.id WHERE $where)";
					break;
			}
		}

		// Add order by columns
		$query .= " ORDER BY sortorder";
		// die($query);

		// Execute the query
		$data = $db->query($query,AS_ARRAY);

		// Process the results into specific product object data in a product set

		foreach ($data as $row) {
			if (is_array($products) && isset($products[$row->product]))
				$target = $products[$row->product];
			else $target = $this;

			$record = new stdClass(); $i = 0; $name = "";
			foreach ($Dataset[$row->rtype]->_datatypes AS $key => $datatype) {
				$column = 'c'.$i++;
				$record->{$key} = '';
				if ($key == "name") $name = $row->{$column};
				if (!empty($row->{$column})) {
					if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/",$row->{$column}))
						$row->{$column} = unserialize($row->{$column});
					$record->{$key} = $row->{$column};
				}
			}

			$target->{$row->rtype}[] = $record;
			if (!empty($name)) {
				if (isset($target->{$row->rtype.'key'}[$name]))
					$target->{$row->rtype.'key'}[$name] = array($target->{$row->rtype.'key'}[$name],$record);
				else $target->{$row->rtype.'key'}[$name] = $record;
			}
		}

		if (is_array($products)) {
			foreach ($products as $product) if (!empty($product->prices)) $product->pricing();
		} else {
			if (!empty($this->prices)) $this->pricing($options);
		}
		
		// echo thiscats;
		// var_dump($this->categories);

	} // end load_data()

	/**
	 * Saves category assignments to the catalog
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $updates Updated list of category ids the product is assigned to
	 * @return void
	 **/
	function save_categories ($updates) {
		$db = DB::get();

		if (empty($updates)) $updates = array();
		
		$current = array();
		
		// echo passedcats_save;
		// var_dump($this->categories);
		$this->load_data(array('categories'),$this->id);
		// echo afterload;
		// var_dump($this->categories);
		
		if (is_object($this->categories[0]))
			foreach ($this->categories as $category) $current[] = $category->id;
		elseif ($this->categories) $current = $this->categories;
		
		$new_cats = $updates;
		$updates = array();
		if (is_object($new_cats[0]))
			foreach ($new_cats as $category) $updates[] = $category->id;
		elseif ($new_cats) $updates = $new_cats;
		
		// echo diff;
		// var_dump($updates,$current);

		$added = array_diff($updates,$current);
		$removed = array_diff($current,$updates);
		
		// echo added;
		// var_dump($added);
		// 
		// echo removed;
		// var_dump($removed);

		$table = DatabaseObject::tablename(EDGECatMap::$table);

		if (!empty($added)) {
			foreach ($added as $id) {
				if (empty($id)) continue;
				$db->query("INSERT $table SET edge_category='$this->id',category='$id',created=now(),modified=now()");
			}
		}

		if (!empty($removed)) {
			foreach ($removed as $id) {
				if (empty($id)) continue;
				$db->query("DELETE LOW_PRIORITY FROM $table WHERE category='$id' AND edge_category='$this->id'");
			}

		}

	}

} // END class Category




?>