<?php
/**
  	Copyright: Copyright � 2010 Catskin Studio
	Licence: see index.php for full licence details
 */
?>
<?php
require_once('spi_files.php');
require_once('spi_images.php');
if (!class_exists('ShoppData'))
	require_once('shopp_migrate/Shopp.Objects.php');

class spi_model {

	//Initialize Externals
	var $Shopp;
	var $spi;

	//Initialize Workers
	var $map 		= array();
	var $variations = array();
	var $products 	= array();
	var $categories = array();

	//Initialize Product
	var $global_variation_counter = 0;
	var $global_spec_counter = 1;
	var $global_spec_total = 0;

	//Initialize Categories
	//var $cat_index = 0;
	var $edge_cat_builder = array();

	public $result = array();

	function spi_model($spi) {
		if($spi){
			$this->spi = $spi;
			$this->Shopp = $spi->Shopp;
			//Is this used?
			// $this->cat_index = $this->get_next_shopp_category_id();
			$this->files = new spi_files($this->spi);
		}
	}

	// !bookmark : Execution Functions
	function execute() {

		$count_products = $this->count_products_to_import();
		$this->spi->log( 'Products before filtering: '.$count_products );
		if( $count_products === 0 ) return 0;

		//The link between CSV and Known Shopp Fields...
		//Map them out so we can work with them
		$this->initialize_map();

		//get_next_product selects the next product from the shopp_product_importer data table
		//which meets the status code criteria.

		//get_next_set selects all products in the table with the id returned by get_next_product

		//process_set updates the status for the rows we've just used so we don't reuse them.

		$this->spi->log('Filtering products');
		while ( $p_row = $this->get_next_product( 0 ) ) {
			$this->filter_by_edge_category( $p_row->spi_id );
			$this->filter_by_inventory_status( $p_row->spi_id );
			$this->filter_by_image_presence( $p_row->spi_id );
			$this->process_set( $p_row->spi_id, 10 );
		}

		$this->remove_products($_SESSION['spi_products_to_remove']);

		$count_products = $this->count_products_to_import();
		$this->spi->log('Products left after filtering: '.$count_products);
		if( $count_products === 0 ) return 0;

		// // 0 - Initialize Variations
		// $this->spi->log('Processing products - Initialize Variations');
		// $this->variations = array();
		// while ( $p_row = $this->get_next_product( 10 ) ) {
		// 	$this->initialize_variations( $p_row->spi_id );
		// 	$this->process_set( $p_row->spi_id, 20 );
		// }

		// // 1 - Populate Variations
		// $this->spi->log('Processing products - Populate Variations');
		// while ( $p_row = $this->get_next_product( 20 ) ) {
		// 	$p_set = $this->get_next_set( $p_row->spi_id );
		// 	$this->populate_variations( $p_set );
		// 	$this->process_set( $p_row->spi_id, 30 );
		// }

		// 2 - Initialize Categories
		$this->spi->log( 'Processing products - Initialize Categories' );
		$this->categories = $this->edge_categories = array();
		while ( $p_row = $this->get_next_product( 10 ) ) {
			$this->initialize_categories( $p_row->spi_id );
			$this->process_set( $p_row->spi_id, 40 );
		}
		$this->spi->log( 'Categories used: '.count($this->edge_categories) );

		// 3 - Initialize Products
		$this->spi->log( 'Processing products - Initialize Products' );
		while ( $p_row = $this->get_next_product( 40 ) ) {
			//Does the product already exist in shopp?
			$product_id = $this->product_exists( $p_row->spi_sku );
			$this->products[] = $this->initialize_product( $p_row->spi_id, $product_id );
			$this->process_set($p_row->spi_id, 50, $product_id);
		}

		// 4 - Initialize Prices
		$this->spi->log( 'Processing products - Initialize Prices' );
		foreach ($this->products as $map_product) {
			$this->initialize_prices($map_product);
		}

		$this->process_all( 60 );

		return $this->products;
	}

	function execute_images() {
		try {
			$this->initialize_map();
			//Populate Images
			$cnt = 0;
			$output = array();
			$images = array();
			//Debugging Any Images Exist... Check console or remove debug line from function any_images_exist
			if (($images_left=$this->any_images_exist()) > 0)
			{
				if ($p_row = $this->get_next_product(60))
				{
					$p_set = $this->get_next_product(60,true);
					foreach ($p_set as $pmap)
					{
						foreach ($this->map as $mset)
						{
							if ($mset['type']=='image')
							{
								$filename = $this->get_row_mapped_var($pmap->id,$mset['header']);
								if (!empty($filename))
								{
									$error = false;
									$report = true;
									$url_template = home_url('/'.$this->Shopp->Settings->get('catskin_importer_imageformat'));
									$url = str_replace('{val}',$filename,$url_template);
									if ( $this->Shopp->Settings->get( 'catskin_importer_import_only_products_with_images' ) == 'yes' || $this->file_exists_from_url($url) )
									{
										if ($result = $this->_populate_image($p_set,$pmap,$mset))
										{
											$cnt += $result;
											$message = 'success';
										} else
										{
											$message = 'image already exists';
											$error = true;
											$report = false;
										}
									} else
									{
										$message = 'file missing: '.$url;
										$error = true;
									}
									$this->spi->log("processed image $filename - $message");
									if ($report) {
										$output[] = array(
											'sku'=>$this->get_row_mapped_var($p_row->id,"spi_sku"),
											'message'=>$message,
											'error'=>$error,
											'url'=>$url,
											'count'=>$cnt,
											'filename'=>$filename,
										);
									}
								}
							}
						}
					}
					$this->process_product( $p_row->id, 70 );
					if ($output) {
						if ($this->spi->auto_import) return true;
						else return $this->image_output_html($output);
					} elseif ($this->spi->auto_import) return null;
				} else {
					// $this->process_product($p_row->spi_id,50);
					if ($this->spi->auto_import) return false;
					return "no-more";
				}
			} else {
				if ($this->spi->auto_import) return false;
				return "no-images";
			}
		} catch (Exception $e) {
			if ($this->spi->auto_import) return false;
		    return 'Caught exception: '.  $e->getMessage(). "\n";
		}
	}

	function image_html($url)
	{
		return "<img src='$url' style='max-width:100px; max-height:90px' />";
	}
	function image_report_html($output)
	{
		$contents = "<p style='font-size:75%;text-align:center;'>{$output['filename']}</p>";
		$contents .= $output['error'] ?
			"<p style='font-size:75%;color:red'>{$output['message']}</p>"
		:	$this->image_html($output['url']);
		return "<div style='float:left; display:inline; width:110px; height:130px; border:1px solid #CCC; background:#FFF; margin:3px; padding:5px; margin-top:15px;'>$contents</div>";
	}
	function image_output_html($output)
	{
		$return = '';
		foreach ($output as $o) {
			$return .= $this->image_report_html($o);
		}
		return $return;
	}
	function file_exists_from_url($url)
	{
		if (file_exists($this->path_from_url($url))) return true;
		else var_dump($this->path_from_url($url));
	}

	function path_from_url($url)
	{
		$wp_path = str_replace(home_url('/'), '', site_url('/'));
		$abs_path = str_replace($wp_path, '', ABSPATH);
		$path = str_replace(home_url('/'), $abs_path, $url);
		return $path;
	}

	function execute_mega_query() {

		global $wpdb;

		$product_index = 0;
		$price_index = 0;
		$tag_index = 0;
		$category_index = 0;
		$catalog_index = 0;
		$spec_index = 0;
		// $next_tag_id =  $this->get_next_shopp_tag_id();
		// $next_category_id = $this->get_next_shopp_category_id();
		$used_tags = array();
		$used_categories = array();
		$values = "";
		$prices = "";
		$tags = "";
		$catalogs = "";
		$categories = "";
		$specs = "";
		$numSpecs = 0;
		$update_products = array();

		foreach ($this->products as $map_product) {

			// If the item is in the order_only_items table already, we don't want to update
			// or add, we just want to delete from that table since it is now in stock
			if ($this->in_order_only_items($map_product->sku)){
				$this->delete_from_order_only($map_product->sku);
				$this->spi->result['remove_from_order_only'] += 1;
				continue;
			}

			if (strlen($map_product->description) > 0) {
				$description = $spi_files->load_html_from_file($map_product->description);
			} else {
				$description = $map_product->description_text;
			}
			// var_dump( $map_product->sku, $this->product_exists( $map_product->sku ) )
			if ($map_product->id = $this->product_exists( $map_product->sku )) {
				$this->update_wp_product($map_product,$description);
				$updated_products[] = $map_product->id;
			} else {
				$insert_products[$map_product->id] = $map_product->id = $this->create_wp_product($map_product,$description);
				$this->spi->result['products'][] = $map_product->sku;
			}

			// $this->create_new_shopp_product_meta( $map_product, $map_product->id );



			// var_dump($this->spi->result, $update_products, $insert_products)


			// foreach ($map_product->specs as $spec) $specs[] = $this->create_specs_sql($spec);

			// Prices
			if ( $this->Shopp->Settings->get('catskin_importer_clear_prices') == 'yes' ) {
				// $this->spi->log("Clearing price lines for {$map_product->sku}" );
				$this->truncate_prices_for_product($map_product->sku);
			}

			foreach ($map_product->prices as $price) {
				$price->product = $map_product->id;
				$price_meta = $price->_meta;
				unset( $price->_meta );
				$wpdb->insert( "{$wpdb->prefix}shopp_price", get_object_vars( $price ) );
				if( $price->type != 'N/A' ) {
					$this->create_new_shopp_price_meta( $price_meta, $wpdb->insert_id );
				}
			}

			// if (count($map_product->tags) > 0) {
			// 	foreach ($map_product->tags as $tag) {
			// 		$tag_value = htmlentities($tag->value, ENT_QUOTES, "UTF-8");
			// 		if (array_key_exists($tag_value, $used_tags) === false) {
			// 			$used_tags[$tag_value] = $next_tag_id;
			// 			$tags[] = $this->create_tags_sql($next_tag_id,$next_tag_id);
			// 			$next_tag_id++;
			// 		}
			// 		$catalogs[] = $this->create_tag_catalog_sql($map_product,$tag,$used_tags);
			// 	}
			// }
			// var_dump($map_product);

			// Assign categories
			foreach ($this->edge_categories as $edge_id=>$skus) {
				// foreach ($skus as $sku) {
				if (in_array($map_product->sku, $skus)) {
					$cat_map_results = $wpdb->get_results("SELECT category FROM {$wpdb->prefix}shopp_edge_category_map WHERE `edge_category`='$edge_id';");
					$edge_cats = array();
					foreach( $cat_map_results as $cat_map )
						$edge_cats[] = (int) $cat_map->category;
					wp_set_object_terms($map_product->id, $edge_cats, 'shopp_category' );
				}
			}

		}

		unset($spi_files);

		$wpdb->show_errors();

		die("OK");

		//Update Product Lines
		if (!isset($this->spi->result['products_updated'])) $this->spi->result['products_updated'] = array();
		if (isset($update_products)) {
			foreach ($update_products as $id => $product) {
				foreach ($product as $var=>$value) $set[] = "$var=$value";
				$query = " UPDATE {$wpdb->prefix}shopp_product SET ".(implode(', ',$set))." WHERE id = $id;";
				if ($wpdb->query($query)) {
					$this->spi->result['products_updated'][] = $this->products[$id]->sku;
				} else {
					$this->spi->log('failed to update product '.$this->products[$id]->sku);
				}
			}
		}

		//Import Product Lines
		if (isset($insert_products)) {
			$query = " INSERT INTO {$wpdb->prefix}shopp_product (id,name,slug,summary,description,publish,featured,variations,options,created,modified) VALUES %values%;";
			$this->spi->result['products_imported'] = $this->chunk_query($insert_products,$query);
		}

		//Import Prices
		if (isset($prices)) {
			$query = " INSERT INTO {$wpdb->prefix}shopp_price (product,options,optionkey,label,context,type,sku,price,saleprice,weight,shipfee,stock,inventory,sale,shipping,tax,donation,sortorder,created,modified) VALUES %values%; ";
			$this->spi->result['prices'] = $this->chunk_query($prices,$query);
		}

		//Import Tags
		if (isset($tags)) {
			$query = " INSERT INTO {$wpdb->prefix}shopp_tag (id,name,created,modified) VALUES %values%; ";
			$this->spi->result['tags'] = $this->chunk_query($tags,$query);
		}

		//Import Categories
		if (isset($categories)) {
			$query = " INSERT INTO {$wpdb->prefix}shopp_category (id,parent,name,slug,uri,description,spectemplate,facetedmenus,variations,pricerange,priceranges,specs,options,prices,created,modified) VALUES %values%; ";
			$this->spi->result['categories'] = $this->chunk_query($categories,$query);
		}


		//Import Catalogs
		if (isset($catalogs)) {
			$query = " INSERT INTO {$wpdb->prefix}shopp_catalog (product,parent,type,created,modified) VALUES %values%; ";
			$this->spi->result['catalogs'] = $this->chunk_query($catalogs,$query);
		}


		//Import Specs
		if (isset($specs)) {
			$query = " INSERT INTO {$wpdb->prefix}shopp_meta (parent,name,value,type,created,modified) VALUES %values%; ";
			$this->spi->result['specs'] = $this->chunk_query($specs,$query);
		}



		return $this->spi->result;
	}

	function truncate_prices_for_product($product_sku) {
		global $wpdb;
		$price_row_ids = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}shopp_price WHERE sku='{$product_sku}'" );
		$result = $wpdb->get_var( "DELETE FROM {$wpdb->prefix}shopp_price WHERE sku='{$product_sku}'" );
		foreach( $price_row_ids as $price_row )
		{
			$result .= $wpdb->get_var( "DELETE FROM {$wpdb->prefix}shopp_meta WHERE parent='{$price_row->id}'" );
		}
		// TODO remove meta too
		//$result = $wpdb->get_var( "DELETE FROM wp_shopp_meta WHERE context='price' AND parent='{$result}'" );
		return $result;
	}

	function create_new_shopp_product_meta( $product, $post_id )
	{
		$product_meta_options = array(
			'processing'	=> 'off',
			'minprocess'	=> '1d',
			'maxprocess'	=> '1d',
			'options'		=> ( $product->options === '' ) ?
				serialize(array()) : $product->options
		);
		foreach( $product_meta_options as $meta_name => $meta_value )
		{
			$this->create_wp_shopp_meta_row(
				new ShoppProductMeta( null, array(
					'name'		=> $meta_name,
					'value'		=> $meta_value,
					'parent'	=> $post_id
				)
			) );
		}
	}

	function create_new_shopp_price_meta( $price_meta, $price_row_id )
	{

		// name: settings, options
		// settings: dimensions -> weight
		// 			recurring
		// options: 1,2

		$price_meta['settings'] = serialize( array( 'dimensions' => array( 'weight' => (float) $price_meta['weight'] ) ) );
		unset( $price_meta['weight'] );

		if( empty( $price_meta['options'] ) ) unset( $price_meta['options'] );

		foreach( $price_meta as $meta_name => $meta_value )
		{
			$this->create_wp_shopp_meta_row(
				new ShoppPriceMeta( null, array(
					'name'		=> $meta_name,
					'value'		=> $meta_value,
					'parent'	=> $price_row_id
				)
			) );
		}
	}

	function create_wp_shopp_meta_row( $new_meta_data )
	{
		global $wpdb;
		return $wpdb->insert( "{$wpdb->prefix}shopp_meta", (array) $new_meta_data );
	}

	function remove_products($items){

		foreach($items as $sku=>$name) {
			$pieces = explode("-", $sku);
			$category = $pieces[1];
			$testToKeep = $this->test_if_order_only_category($category);

			$id = $this->product_exists($sku);

			if($testToKeep && $id){

				$this->insert_order_only_item($id, $name, $sku);

			}elseif ($id && $this->remove_product_existing($id)){

				$this->spi->result['products_removed'][] = $sku;

			}

		}
	}

	function insert_order_only_item($id, $name, $sku){

		if(!$this->in_order_only_items($sku)){

			global $wpdb;
			$safename = str_replace("'", "''", $name);
			$query = "INSERT INTO {$wpdb->prefix}shopp_order_only_items (id,name,sku) VALUES ({$id}, '{$safename}', '{$sku}')";
			$added = $wpdb->query($query);
			$this->spi->result['added_to_order_only'] += 1;

		}

	}

	function test_if_order_only_category($category)  {
		global $wpdb;
		$query = "SELECT 1 FROM {$wpdb->prefix}shopp_order_only_cats WHERE cat_id='{$category}'";
		$result = $wpdb->query($query);
		return $result;
	}

	function delete_from_order_only($sku) {
		global $wpdb;
		$query = "DELETE FROM {$wpdb->prefix}shopp_order_only_items WHERE sku='{$sku}'";
		$result = $wpdb->query($query);
		$query = "DELETE FROM {$wpdb->prefix}shopp_importer WHERE spi_sku='{$sku}'";
		$result = $wpdb->query($query);
		return $result;

	}

	function in_order_only_items($sku){
		global $wpdb;
		$query = "SELECT 1 FROM {$wpdb->prefix}shopp_order_only_items WHERE sku='{$sku}'";
		$result = $wpdb->query($query);
		return $result;
	}

	function chunk_query($values,$query,$size=100)
	{
		if (isset($values) && !empty($values)) {
			global $wpdb;
			$result=0;
			if (!empty($values)) {
				for ($slice=0; $slice < count($values); $slice+=$size) {
					$values_slice = implode(
						', ', array_slice($values,$slice,$size)
					);
					$result += $wpdb->query(
						str_replace("%values%", $values_slice, $query)
					);
				}
				return $result;
			}
		}
	}

	function create_wp_product( $map_product, $description )
	{
		return wp_insert_post( array(
	        'post_title'        => $map_product->name,
	        'post_content'      => $description,
	        'post_excerpt'		=> $map_product->summary,
	        'post_status'       => 'publish',
	        'post_type'			=> 'shopp_product',
	        'comment_status'	=> 'closed',
	        'ping_status'		=> 'closed'
	    ) );
	}

	function update_wp_product( $map_product, $description )
	{
		return wp_update_post( array(
			'ID'				=> $map_product->id,
	        'post_title'        => $map_product->name,
	        'post_content'      => $description,
	        'post_excerpt'		=> $map_product->summary
	    ) );
	}

	function create_specs_sql($id,$spec)
	{
		$specs  = "'".mysql_real_escape_string($id)."',";
		$specs .= "'".mysql_real_escape_string($spec->name)."',";
		$specs .= "'".mysql_real_escape_string($spec->value)."',";
		$specs .= "'spec',";
		$specs .= "CURDATE(),CURDATE()";
		return "($specs)";
	}
	// function create_prices_sql($price)
	// {
	// 	$prices  = "'".mysql_real_escape_string($price['product'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['options'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['optionkey'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['label'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['context'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['type'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['sku'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['price'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['saleprice'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['weight'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['shipfee'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['stock'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['inventory'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['sale'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['shipping'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['tax'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['donation'])."',";
	// 	$prices .= "'".mysql_real_escape_string($price['sortorder'])."',";
	// 	$prices .= "CURDATE(),CURDATE()";
	// 	return "($prices)";
	// }


	function create_category_sql($category)
	{
		$categories  = "'".mysql_real_escape_string($category->id)."',";
		$categories .= "'".mysql_real_escape_string($category->parent_id)."',";
		$categories .= "'".mysql_real_escape_string($category->value)."'".",";
		$categories .= "'".mysql_real_escape_string($category->slug)."',";
		$categories .= "'".mysql_real_escape_string($category->uri)."',";
		$categories .= "'',";
		$categories .= "'off',";
		$categories .= "'off',";
		$categories .= "'off',";
		$categories .= "'disabled',";
		$categories .= "'',";
		$categories .= "'',";
		$categories .= "'',";
		$categories .= "'',";
		$categories .= "CURDATE(),CURDATE()";
		return "($categories)";
	}

	function create_category_catalog_sql($csv_id,$cat_id)
	{
		$prd = $this->product_by_csv_id($csv_id);
		$catalogs  = "'".mysql_real_escape_string($prd->id)."',";
		$catalogs .= "'".mysql_real_escape_string($cat_id)."',";
		$catalogs .= "'category',";
		$catalogs .= "CURDATE(),CURDATE()";
		return "($catalogs)";
	}

	function create_tags_sql($next_tag_id,$tag)
	{
		$tags  = "'".mysql_real_escape_string($next_tag_id)."'".",";
		$tags .= "'".mysql_real_escape_string($tag->value)."'".",";
		$tags .= "CURDATE(),CURDATE()";
		return "($tags)";
	}

	function create_tag_catalog_sql($map_product,$tag_id)
	{
		$catalogs  = "'".mysql_real_escape_string($map_product->id)."'".",";
		$catalogs .= "'0',";
		$catalogs .= "'".mysql_real_escape_string($tag_id)."'".",";
		$catalogs .= "CURDATE(),CURDATE()";
		return "($catalogs)";
	}

	function index_content() {


	}

	// !bookmark : (End) Execution Functions
	// !bookmark : Processing Fuctions

	function initialize_map() {
		//Load the map from shopp's Settings table. Saved there by column mapping in
		//importer settings page and apply it to an array that we can use to understand the
		//data being pulled in.
		$map = $this->Shopp->Settings->get('catskin_importer_column_map');

		//initialize counters
		$column = 0;
		$variation = 0;
		$category = 0;
		$tag = 0;
		$image = 0;
		$spec = 0;
		// echo "Initialize_map<br>";
		//Using $map array create a global field map based on the currently active CSV
		foreach ($map as $item)
		{
			if ($item['type'] !== '')
			{
				//does the map item have a special power?
				//Special power columns arent exclusive so we need to count
				//how many of each special powers we have.
				//$hidx holds the index conter for that special power
				switch ($item['type']) {
					case 'variation': $variation++; $hidx = $variation; break;
					case 'spec': $spec++; $hidx = $spec; break;
					case 'category': $category++; $hidx = $category; break;
					case 'tag': $tag++; $hidx = $tag; break;
					case 'image': $image++; $hidx = $image; break;
					default: $hidx = '';
				}
				//We handle variations by name for labeling purposes so instead of getting an index
				//it's given a name
				if ($item['type'] == 'variation' || $item['type'] == 'spec')
					$column_header = 'spi_'.$item['label'];
				else
					$column_header = 'spi_'.$item['type'].$hidx;

				$map = array('type'=>$item['type'],'label'=>$item['label'],'header'=>$column_header,'idx'=>$hidx);
				// $this->map[] = $map;

				// we use the condition here to help the hack below
				if ($item['type'] !== 'id')
					$this->map[] = $map;
				// hack to make product identifier same as sku
				if ($item['type'] == 'sku')
					$this->map[] = array('type'=>'id','label'=>$item['label'],'header'=>'spi_id','idx'=>$hidx);
			}

		}
		$this->global_spec_total = $spec;
	}

	function filter_by_edge_category($csv_product_id)
	{
		$allowed_categories = array(
			'100' // diamond engagement rings
		,	'110' // diamond wedding bands
		,	'115' // diamond wedding sets
		,	'120' // diamond anniversary rings
		,	'130' // women's diamond fashion rings
		,	'135' // men's diamond & fashion rings
		,	'140' // diamond ring mountings
		,	'150' // diamond earrings
		,	'151' // diamond stud earrings
		,	'160' // diamond pendants
		,	'165' // diamond necklaces
		,	'170' // diamond bracelets
		,	'200' // women's colored stone rings
		,	'205' // men's colored stone rings
		,	'210' // colored stone earrings
		,	'230' // colored stone pendants
		,	'235' // colored stone necklaces
		,	'240' // colored stone bracelets
		,	'300' // pearl rings
		,	'310' // pearl earrings
		,	'320' // pearl pendants
		,	'325' // pearl necklaces
		,	'330' // pearl bracelets
		,	'400' // gold wedding bands
		,	'401' // fancy wedding bands
		,	'425' // gold earrings
		,	'435' // gold pendants/charms ??
		,	'440' // gold bracelets womens
		// ,	'506' // accutron mens watches
		// ,	'507' // accutron ladies watches
		// ,	'510' // caravelle womens watches
		// ,	'515' // caravelle mens watches
		// ,	'520' // seiko womens watches
		// ,	'525' // seiko mens watches
		// ,	'530' // pulsar womens watches
		// ,	'535' // mens pulsar watches
		,	'550' // misc watches
		,	'580' // gents pocket watches
		,	'610' // SS & GF Bracelets bracelets
		,	'620' // silver rings
		,	'630' // silver rings
		,	'640' // silver pendants
		,	'645' // silver earrings
		,	'800' // gents jewelry
		);
		if (!isset($this->result['filtered'])) $this->result['filtered'] = 0;
		foreach ($this->map as $mset) {
			switch ($mset['type']) {
				case 'edge_category_id':
					if ($this->any_exist($mset['header'],$csv_product_id) > 0) {
						$cat_id = $this->get_mapped_var($csv_product_id,$mset['header']);
						if (!in_array($cat_id,$allowed_categories)) {
							if ($this->Shopp->Settings->get('catskin_importer_empty_first') == 'no') {
								$sku = $this->get_mapped_var($csv_product_id,'spi_sku');
								$id = $this->product_exists($sku);
								if ($id) $_SESSION['spi_products_to_remove'][$sku] = $name;
							}
							$this->result['filtered'] += $this->remove_product_import($csv_product_id);
							$_SESSION['spi_products_filtered_cat'][] = $csv_product_id;
							return;
						}
					}
				break;
			}
		}

	}
	function filter_by_inventory_status($csv_product_id)
	{

		// foreach ($this->examine_data as $key=>$row) {
		// 	switch ($row[ $this->column_map['edge_inventory_status'] ]) {
		// 					case 'I':	// I    In-stock
		// 					break;
		// 					case 'X':	// X    Scrapped
		// 					case '-':	// -	Deleted
		// 					case 'L':	// L    Layaway
		// 					case 'S':	// S    Sold
		// 					case 'V':	// V    Returned to vendor
		// 					case 'M':	// M    Missing
		// 					case 'U':	// U    Consumed as part (assembled into item or used in repair job)
		// 					default:

		// 			if ( $this->Shopp->Settings->get('catskin_importer_empty_first') == 'no' )
		// 			{
		// 				// record lines to remove from DB
		// 				$_SESSION[ 'spi_products_to_remove' ][ $row[ $this->column_map[ 'sku' ] ] ] = $row[ $this->column_map[ 'name' ] ];
		// 				}
		// 			else
		// 			{
		// 				// or if starting from empty, log which were filtered out
		// 				$_SESSION[ 'spi_products_filtered_inv' ][] = $this->column_map[ 'sku' ];
		// 			}
		// 			unset($this->examine_data[$key]);
		// 		break;
		// 	}
		// }
		foreach ($this->map as $mset) {
			switch ($mset['type']) {
				case 'edge_inventory_status':
					if ($this->any_exist($mset['header'],$csv_product_id) > 0) {

						$status = $this->get_mapped_var($csv_product_id,$mset['header']);


						switch ($status) {
							case 'I':	// I    In-stock
							break;
							case 'S':	// S    Sold
							case 'L':	// L    Layaway
							case 'O':	// O 	Special order
							case 'X':	// X    Scrapped
							case '-':	// -	Deleted
							case 'V':	// V    Returned to vendor
							case 'M':	// M    Missing
							case 'U':	// U    Consumed as part (assembled into item or used in repair job)
							default:
								$sku = $this->get_mapped_var($csv_product_id,'spi_sku');
								$id = $this->product_exists($sku);

								if ($id) $_SESSION[ 'spi_products_to_remove' ][] = $sku;
								else $_SESSION[ 'spi_products_filtered_inv' ][] = $sku;
								$this->remove_product_import( $csv_product_id );

							break;
						}
					}
				break;
			}
		}
	}

	function filter_by_image_presence($csv_product_id)
	{

		// following code imports only products with images, if set to do so
		if ( $this->Shopp->Settings
				->get('catskin_importer_import_only_products_with_images') == 'yes') {
			if (!$this->image_exists($csv_product_id) ) {
				if ($this->remove_product_import($csv_product_id)) $_SESSION['spi_products_filtered_img'][] = $csv_product_id;
			}
		}
	}

	function initialize_variations($csv_product_id) {
		foreach ($this->map as $mset) {
			switch ($mset['type']) {
				case 'variation':
					if ($this->any_exist($mset['header'],$csv_product_id) > 0) {
						$map_variation = new map_variation();
						$map_variation->name =  $mset['header'];
						$map_variation->csv_product_id = $csv_product_id;
						$map_variation->values = array();
						if (array_search($map_variation,$this->variations) === false) {
							$this->variations[] = $map_variation;
						}
					}
					break;
				}
		}
	}

	function populate_variations($product_set) {
		foreach ($product_set as $pmap) {
			foreach ($this->map as $mset) {
				switch ($mset['type']) {
					case 'variation':
						$variation_value = new map_variation_value();
						eval('$variation_value->value = $pmap->'.$mset['header'].';');
						if ($this->find_variation($mset['header'],$pmap->spi_id) > -1 &&
							$this->find_variation_value(
								$this->variations[$this->find_variation($mset['header'],$pmap->spi_id)]->values,
									$variation_value->value) == -1)
										$this->variations[$this->find_variation($mset['header'],$pmap->spi_id)]->values[] = $variation_value;
						break;
				}
			}
		}
	}

	function initialize_categories($csv_product_id) {
		// $cat_index = $this->cat_index;
		$parent_index = 0;
		$type=false;
		$cat_array=array();
		$cat_string=false;

		for ($i=0; $i<count($this->map); $i++) {
			$mset = $this->map[$i];
			switch ($mset['type']) {
				case 'edge_category_id': // EDGE Category ID
					$this->edge_categories[$this->get_mapped_var( $csv_product_id, $mset[ 'header' ] )][] = $this->get_mapped_var( $csv_product_id, 'spi_sku' );
					return;

			}
		}
	}

	function make_edge_categories($csv_product_id)
	{
		$edge_data = $this->edge_cat_builder[$csv_product_id];
		$cat_array = array();
		if (isset($edge_data['type'])
			&&	isset($edge_data['desc'])
			&&	isset($edge_data['name'])
			&&	isset($edge_data['id'])
			&&	is_string($cat_array[0] = $edge_data['type'])
			&&	is_string($cat_array[1] = $edge_data['desc'])
			&&	is_string($cat_array[2] = $edge_data['name'])
			&&	($id = $edge_data['id'])
		) {
			if (false) {
				$watch_list = array(
					'Watch'=>'es'
				,   'Ring'=>'s'
				,   'Band'=>'s'
				,   'Necklace'=>'s'
				,   'Bracelet'=>'s'
				,   'Pendant'=>'s'
				,   'Chain'=>'s'
				,   'Earring'=>'s'
				,   'Charm'=>'s'
				);
				$delete_list = array(
					'\s*-'
				,	'\s*Jewelry Line'
				,	'Pandora\s'
				);
				$gender_list = array(
					'Him' => 'Men\'?s?'
				,	'Her' => 'Women\'?s?'
				);
				$cat_array = array_unique($cat_array);
				foreach ($cat_array as &$value) {
					if ($value === 'Bulk' || $value === 'Other') {
						unset ($cat_array[key($cat_array)-1]);
						// $cat_arrays[] = array($value);
						continue;
					} else {
						foreach ($delete_list as $word) {
							$value = trim( preg_replace("/(.*?){$word}\s*(.*?)/",'$1 $2',$value) );
						}
						foreach ($watch_list as $word => $suffix) {
							$value = preg_replace("/(.*?{$word})(?!{$suffix})(.*?)/","$1{$suffix}$3",$value);
						}
						foreach ($gender_list as $gender => $word) {
							$oldvalue = $value;
							$value = preg_replace("/(.*?){$word}(.*?)/",'$1$2',$value);
							if ($value!==$oldvalue) $cat_arrays[] = array('Gifts for',$gender);
						}
						if (@$strings) foreach ($strings as $string) {
							$value = trim( preg_replace("/(.*?){$string}\s*(.*?)/",'$1$2',$value) );
						}
						$strings[] = $value;
					}
				}
				$cat_array = array_values($cat_array);
				$cat_arrays[$id] = $cat_array;
			}

			$cat_arrays[$id] = implode('/',$cat_array);

			// if ($mset['header'] !== 'category') {
			// 	$mset['header'] = 'spi_category';
			// 	$mset['type'] = 'category';
			// }
			return $cat_arrays;
		}
	}

	function make_category( &$cat_array, &$cat_index, &$parent_index, &$csv_product_id, $index=null )
	{
		//initialize our arrays for reuse
		$uri_array = array();

		//reverse the array for ease of use
		if ( ! is_array( $cat_array ) ) $cat_array = array( $cat_array );
		array_reverse( $cat_array );


		for ( $i = 0; $i < count( $cat_array ); $i ++ ) {
			//build an array of category uri's we're going to use these as the
			//unique identifier for categories
			$uri_array[ $i ] = sanitize_title_with_dashes( strtr( $cat_array[ $i ], '/', '-' ) );
		}
		for ( $i = 0; $i < count( $cat_array ); $i ++ ) {
			$map_category = new map_category();
			$map_category->name = 'spi_category';
			$map_category->value = $cat_array[$i];
			// echo "  > slug : ",
			$map_category->slug = $uri_array[$i];
			// echo "  > id   : ",
			$map_category->id = ($index>0?$index:$cat_index);
			// echo "  > prnt : ",
			$map_category->parent_id = $parent_index;
			$map_category->csv_product_id = $csv_product_id;
			$map_category->csv_product_ids[] = $csv_product_id;

			$pop_array = $uri_array;
			for( $j = 0; $j < ( count( $cat_array ) - ( $i + 1 ) ); $j ++ ) {
				array_pop( $pop_array );
			}
			$parent_pop_array = $uri_array;
			for( $j = 0; $j < ( count( $cat_array ) - ( $i ) ); $j ++ ) {
				array_pop( $parent_pop_array );
			}
			if( count( $pop_array ) == 1 ) {
				$map_category->parent_id = 0;
			} else {
				$map_category->parent_id = $parent_index;
			}
			$map_category->uri = join( '/', $pop_array );
			$map_category->parent_uri = join( '/', $parent_pop_array );

			$existing_shopp_category = $this->category_exists( $map_category->uri, $index );
			// if (!$existing_shopp_category) $this->spi->log('make_category new: '.$index);

			if( $this->category_by_uri( $map_category->uri ) )
			{
				$this->categories[ $this->key_to_category_by_uri( $map_category->uri ) ]->csv_product_ids[] = $csv_product_id;
			}
			elseif( $this->Shopp->Settings->get( 'catskin_importer_create_categories' ) == 'yes' )
			{
				if( is_null($this->category_by_uri($map_category->parent_uri)))
				{
					$map_category->parent_id = 0;
				}
				else
				{
					$parent_category = $this->category_by_uri($map_category->parent_uri);
					$map_category->parent_id = $parent_category->id;
				}

				if ($existing_shopp_category)
				{
					$map_category->id = $existing_shopp_category->id;
					$map_category->exists = true;
					$map_category->parent_id = $existing_shopp_category->parent;
				}
				else
					$cat_index++;

				if ($index)
					$this->categories['edge_'.$index] = $map_category;
				else
					$this->categories[] = $map_category;
			}
		}
	}
	// TODO:
	/*
		enable category matching

	*/

	function initialize_product( $csv_product_id, $shopp_product_id=null ) {

		$map_product = new map_product();
		$this->global_spec_counter = 1;
		if( $shopp_product_id ) $map_product->id = $shopp_product_id;
		$map_product->csv_id = $csv_product_id;

		foreach ($this->map as $mset) {
			$parent_index = 0;
			$value = $this->get_mapped_var( $csv_product_id, $mset['header'] );
			switch ($mset['type'])
			{
				case 'image':
					$map_image = new map_image();
					$map_image->name =  $mset['header'];
					$map_image->value = $value;
					if (strlen($map_image->value) > 0) $map_product->images[] = $map_image;
					break;
				case 'price':
				case 'weight':
					$map_product->{$mset['type']} = $this->parse_float($value);
					break;
				case 'descriptiontext':
					$map_product->description_text = $value;
					break;
				case 'saleprice':
					$map_product->sale_price = $this->parse_float($value);
					break;
				case 'shipfee':
					$map_product->ship_fee = $this->parse_float($value);
					break;
				case 'tag':
					$map_tag = new map_tag();
					$map_tag->name =  $mset['header'];
					$map_tag->value = $value;
					if (strlen($map_tag->value) > 0) $map_product->tags[] = $map_tag;
					break;
				case 'pricetype':
					$map_product->price_type = $value;
					break;
				case 'variation':
					if ($this->any_exist( $mset['header'], $csv_product_id) > 0 ) {
						$map_variation = new map_variation();
						$map_variation->name =  $mset['header'];
						$this->global_variation_counter++;
						$map_variation->id = $this->global_variation_counter;
						$map_variation->values = array();
						$map_product->variations[] = $map_variation;
					}
					break;
				case 'spec':
			 		$map_spec = new map_spec();
					$map_spec->name =  substr($mset['header'],4);
					$map_spec->value = $this->get_mapped_var( $csv_product_id, 'spi_spec'.$this->global_spec_counter );
					$map_product->specs[] = $map_spec;
					$this->global_spec_counter++;
					break;
				default:
					$map_product->{$mset['type']} = $value;
					break;
			}
		}

		$this->last_csv_product_id = $csv_product_id;
		$map_product->has_variations = (
			(!isset($map_product->variations)) ||
			(!is_array($map_product->variations)) ||
			(count($map_product->variations) == 0)
		) ? 'off' : 'on';
		$map_product->options = $this->determine_product_options( $map_product, $csv_product_id );

		return $map_product;
	}

	function remove_product(&$map_product,$csv_product_id,$shopp_product_id)
	{
		$map_product->id = $shopp_product_id;
		$map_product->csv_id = $csv_product_id;
		$cat_index = $this->cat_index;
		foreach ($this->map as $mset) {
			$parent_index = 0;
			switch ($mset['type']) {
				case 'description':
					$map_product->description = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
			}
		}
	}
	function initialize_prices( &$map_product ) {
		// if( count( unserialize( $map_product->options ) ) > 0 )
		// {
		// 	$combinations = array();
		// 	$product_options = unserialize($map_product->options);
		// 	foreach ($product_options as $option_group) {
		// 		$sets = false;
		// 		foreach ($option_group['options'] as $options) {
		// 			$sets[]= $options['id'];
		// 		}
		// 		$groups[] = $sets;
		// 	}
		// 	$this->_get_combos($groups,$combinations);
		// }

		$row_data = $this->get_importer_data( $map_product );

		// $row_type = isset($groups) ?
		// 	"N/A" :
		// 	$this->defval( $row_data->spi_type, "Shipped" );
		// $row_price = isset($groups) ?
		// 	"0.00" :
		// 	$this->defval( $row_data->spi_price, "0.00" );

		// $tc1 = array(
		// 	'product'  	=> $map_product->id,
		// 	'options'  	=> "",
		// 	'optionkey'	=> "0",
		// 	'label'    	=> "Price & Delivery",
		// 	'context'  	=> "product",
		// 	'type'     	=> $row_type,
		// 	'sku'      	=> isset( $groups ) ? "" : $this->defval( $row_data->spi_sku, "" ),
		// 	'price'    	=> $this->parse_float($row_price),
		// 	'saleprice'	=> $this->parse_float((isset($groups))?"0.00":$this->defval($row_data->spi_saleprice,"0.00")),
		// 	'weight'   	=> $this->parse_float((isset($groups))?"0.000":$this->defval($row_data->spi_weight,"0.000")),
		// 	'shipfee'  	=> $this->parse_float((isset($groups))?"0.00":$this->defval($row_data->spi_shipfee,"0.00")),
		// 	'stock'    	=> isset( $groups ) ? "0" : $this->defval( $row_data->spi_stock, "0" ),
		// 	'inventory'	=> isset( $groups ) ? "off" : $this->defval( $row_data->spi_inventory, "off" ),
		// 	'sale'     	=> isset( $groups ) ? "off" : $this->defval( $row_data->spi_sale, "off" ),
		// 	'shipping' 	=> isset( $groups ) ? "on" : $this->defval( $row_data->spi_shipping, "on" ),
		// 	'tax'      	=> isset( $groups ) ? "on" : $this->defval( $row_data->spi_tax, "on" ),
		// 	'donation' 	=> $this->defval($row_data->spi_donation,'a:2:{s:3:"var";s:3:"off";s:3:"min";s:3:"off";}'),
		// 	'sortorder'	=> isset( $groups ) ? "0" : $this->defval( $row_data->spi_order, "0" )
		// );

		$PriceRow = new ShoppPriceRow( null, array(
			'product'  	=> $map_product->id,
			'label'    	=> "Price & Delivery",
			'context'  	=> 'product',
			'type'     	=> $row_data->spi_type ? $row_data->spi_type : 'Shipped',
			'sku'      	=> $row_data->spi_sku,
			'price'    	=> (float) $row_data->spi_price,
			'saleprice'	=> (float) ($row_data->spi_saleprice === $row_data->spi_price) ? 0 : $row_data->spi_saleprice,
			// TODO meta 'weight'   	=> (float) $row_data->spi_weight,
			'shipfee'  	=> (float) $row_data->spi_shipfee,
			'stock'    	=> (int) ( $row_data->spi_stock ? $row_data->spi_stock : 1 ),
			// 'inventory'	=> isset( $groups ) ? "off" : $this->defval( $row_data->spi_inventory, "off" ),
			'inventory' => $row_data->spi_inventory ? $row_data->spi_inventory : 'on',
			'sale'     	=> $row_data->spi_sale ? $row_data->spi_sale : 'off',
			// 'shipping' 	=> isset( $groups ) ? "on" : $this->defval( $row_data->spi_shipping, "on" ),
			'shipping'  => $row_data->spi_shipping ? $row_data->spi_shipping : 'off',
			'tax'      	=> $row_data->spi_tax ? $row_data->spi_tax : 'off',
			// TODO meta 'donation' 	=> $row_data->spi_donation ? $row_data->spi_donation : 'a:2:{s:3:"var";s:3:"off";s:3:"min";s:3:"off";}',
			'sortorder'	=> (int) $row_data->spi_order ? $row_data->spi_order : 0,
			'_meta'		=> array(
				'weight'   	=> (float) $row_data->spi_weight,
				'options'	=> null
			)
		));

		$map_product->prices[] = $PriceRow;

		// if (isset($combinations)) {
		// 	foreach ($combinations as $combo) {
		// 		unset($row_data);
		// 		$row_data = $this->get_option_optionkey_data($map_product,implode(',',$combo));
		// 		$row_type = $this->defval($row_data->spi_type,"Shipped");
		// 		$row_price = $this->defval($row_data->spi_price,"0.00");
		// 		if ($row_price == "0.00" || $row_price == "0" || strlen($row_price) == 0){
		// 			$row_type = "N/A";
		// 			$row_price = "";
		// 		}

		// 		$tc1 = array(
		// 			'product'  	=> $map_product->id,
		// 			'options'  	=> implode( ',', $combo ),
		// 			'optionkey'	=> $this->get_option_optionkey( $map_product, $combo ),
		// 			'label'    	=> $this->get_option_label( $map_product, $combo ),
		// 			'context'  	=> "variation",
		// 			'type'     	=> $row_type,
		// 			'sku'      	=> $this->defval( $row_data->spi_sku, "" ),
		// 			'price'    	=> $this->parse_float( $row_price ),
		// 			'saleprice'	=> $this->parse_float( $this->defval( $row_data->spi_saleprice, "0.00" ) ),
		// 			'weight'   	=> $this->parse_float( $this->defval( $row_data->spi_weight, "0.000" ) ),
		// 			'shipfee'  	=> $this->parse_float( $this->defval( $row_data->spi_shipfee, "0.00" ) ),
		// 			'stock'    	=> $this->defval( $row_data->spi_stock, "0" ),
		// 			'inventory'	=> $this->defval( $row_data->spi_inventory, "off" ),
		// 			'sale'     	=> $this->defval( $row_data->spi_sale, "off" ),
		// 			'shipping' 	=> $this->defval( $row_data->spi_shipping, "on" ),
		// 			'tax'      	=> $this->defval( $row_data->spi_tax, "on" ),
		// 			'donation' 	=> $this->defval( $row_data->spi_donation, 'a:2:{s:3:"var";s:3:"off";s:3:"min";s:3:"off";}' ),
		// 			'sortorder'	=> $this->defval( $row_data->spi_order, "0" )
		// 		);
		// 		$this->products[$map_product->id]->prices[] = $tc1;
		// 	}
		// }

	}

	// !bookmark : (End) Processing Functions

	function url_exists($url) {
	    // Version 4.x supported
	    $handle   = curl_init($url);
	    if (false === $handle) return false;
	    curl_setopt($handle, CURLOPT_HEADER, false);
	    curl_setopt($handle, CURLOPT_FAILONERROR, true);  // this works
	    curl_setopt($handle, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") ); // request as if Firefox
	    curl_setopt($handle, CURLOPT_NOBODY, true);
	    curl_setopt($handle, CURLOPT_RETURNTRANSFER, false);
	    $connectable = curl_exec($handle);
	    curl_close($handle);
	    return $connectable;
	}

	function image_exists($id)
	{
		$image_exists = false;
		foreach ($this->map as $map) {
			if ($map['type']==='image') {
				$filename = $this->get_mapped_var($id,$map['header']);
				if (!empty($filename)) {
					$url_template = home_url('/'.$this->Shopp->Settings->get('catskin_importer_imageformat'));
					$url = str_replace('{val}',$filename,$url_template);
					if ($this->file_exists_from_url($url)) $image_exists = true;
				}
				break;
			}
		}

		return $image_exists;

	}

	function microtime_float()
	{
	    list($usec, $sec) = explode(" ", microtime());
	    return ((float)$usec + (float)$sec);
	}

	//Checks to see if a specific type of field exists in the shopp_product_importer data table
	//csv_product_id relates to $this->map[$id]['header'] eg. spi_saleprice, spi_tag1, spi_name
	//returns a count of those existing.
	function any_exist($header,$csv_product_id) {
		global $wpdb;
			$query = "SELECT COUNT(NULLIF(TRIM({$header}), '')) FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$csv_product_id}';";
			$result = $wpdb->get_var($query);
		return $result;
	}

	function any_images_exist() {
		global $wpdb;
		$result = 0;
		foreach ($this->map as $mset) {
			switch ($mset['type']) {
				case 'image':
					$query = "SELECT COUNT(NULLIF(TRIM({$mset['header']}), '')) FROM {$wpdb->prefix}shopp_importer;";
					$result = $result + $wpdb->get_var($query);
					break;
			}
		}
		return $result;
	}

	function category_by_uri($uri) {
		foreach ($this->categories as $category) {

			if ($category->uri == $uri) return $category;
		}
		return null;
	}

	function category_exists($uri, $edge=true) {
		global $wpdb;

		if( $edge )
		{
 			$args = array(
				'name' => $uri,
				'post_type' => 'shopp_edge_category',
				'posts_per_page' => 1
			);
			$result = get_posts( $args );
		}
		else
		{
			$result = is_category( $uri );
		}

		return $result;
	}


	function determine_product_options($map_product,$csv_product_id) {
		$options = array();
		$options_index = 1;
		$option_value_uid = 1;
		foreach ($this->variations as $variation) {
			if ($variation->csv_product_id == $csv_product_id) {
				$option_values = array();
				$option_value_index = 0;
				foreach ($variation->values as $val) {
					$option_values[$option_value_index] = array(
						"id"=>(string)$option_value_uid,
						"name"=>$val->value,
						"linked"=>"off"
					);
					$option_value_uid++;
					$option_value_index++;
				}
				$options[$options_index] =
					array(
						"id"=>(string)$options_index,
						"name"=>ltrim($variation->name,"spi_"),
						"options"=>$option_values);
				$options_index++;
			}
		}
		return serialize($options);
	}

	function find_variation($name, $csv_product_id) {
		foreach ($this->variations as $index=>$var) {
			if ($var->name == $name && $var->csv_product_id == $csv_product_id) {
				return $index;
			}
		}
		return -1;
	}

	function find_variation_value($valuearray,$value) {
		foreach ($valuearray as $index=>$var) {
			if ($var->value == $value) {
				return $index;
			}
		}
		return -1;
	}

	private function _get_combos(&$lists,&$result,$stack=array(),$pos=0)
	{
		$list = $lists[$pos];
	 	if(is_array($list)) {
	  		foreach($list as $word) {
	   			array_push($stack,$word);
	   			if(count($lists)==count($stack)) {
	   				$result[]=$stack;
	   			} else {
	   				if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
	   					$this->_get_combos($lists,$result,$stack,$pos+1);
	   				} else {
	   					$this->_get_combos($lists,$result,$stack,$pos+1);
	   				}
	   			}
	   			array_pop($stack);
	  		}
	 	}
	}

	function get_importer_data($map_product,$combostring = '') {
		global $wpdb;

		$empty_result = (object)null;
		$empty_result->spi_type = null;
		$empty_result->spi_price = null;
		$empty_result->spi_sku = null;
		$empty_result->spi_saleprice = null;
		$empty_result->spi_weight = null;
		$empty_result->spi_shipfee = null;
		$empty_result->spi_stock = null;
		$empty_result->spi_inventory = null;
		$empty_result->spi_sale = null;
		$empty_result->spi_shipping = null;
		$empty_result->spi_tax = null;
		$empty_result->spi_donation = null;
		$empty_result->spi_order = null;

		if (strlen($combostring) > 0) {
			$combo = explode(",",$combostring);
			$combo_index = 0;
			$string = "";
			foreach ($map_product->variations as $variation) {
				$option_id_label = $this->get_optionid_label($map_product,$combo[$combo_index]);
				if ($combo_index > 0) $and = " AND "; else $and = "";
				$string .= "{$and} {$variation->name} = '{$option_id_label}' ";
				$combo_index++;
			}
			$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE {$string} AND spi_id = '{$map_product->csv_id}'";
		} else {
			$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$map_product->csv_id}'";
		}
		$result = $wpdb->get_row($query);
		$merged_result = (object) array_merge((array) $empty_result, (array) $result);
		return $merged_result;
	}

	function get_mapped_var($id,$column_header) {
		global $wpdb;
		$query = "SELECT {$column_header} FROM {$wpdb->prefix}shopp_importer WHERE (spi_id = '{$id}') ORDER BY id limit 1";
		$result = $wpdb->get_var($query);
		return $result;
	}

	//get_next_product selects the next product from the shopp_product_importer data table
	//which meets the status code criteria.
	function get_next_product( $status, $as_set=false ) {
		global $wpdb;
		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE (processing_status = {$status}) ORDER BY id limit 1";
		if ($as_set) $result = $wpdb->get_results($query,OBJECT);
		else $result = $wpdb->get_row($query,OBJECT);
		return $result;
	}

	//get_next_set selects all products in the table with the id returned by get_next_product
	function get_next_set($id) {
		global $wpdb;
		$id = trim($id);
		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$id}' ORDER BY id ";
		$result = $wpdb->get_results($query,OBJECT);
		return $result;
	}

	function count_products_to_import() {
		global $wpdb;
		$query = "SELECT COUNT(id) AS count FROM {$wpdb->prefix}shopp_importer";
		$result = $wpdb->get_row($query);
		return (int) $result->count;
	}

	function get_next_shopp_product_id() {
		global $wpdb;
		$query = "SELECT id FROM {$wpdb->prefix}shopp_product ORDER BY id DESC limit 1";
		$result = $wpdb->get_var($query);
		if (!is_numeric($result)) $result = 1; else $result++;
		return $result;
	}

	function get_next_shopp_tag_id() {
		global $wpdb;
		$query = "SELECT id FROM {$wpdb->prefix}shopp_tag ORDER BY id DESC limit 1";
		$result = $wpdb->get_var($query);
		if (!is_numeric($result)) $result = 1; else $result++;
		return $result;
	}

	function get_next_shopp_category_id() {
		global $wpdb;
		$query = "SELECT id FROM {$wpdb->prefix}shopp_category ORDER BY id DESC limit 1";
		$result = $wpdb->get_var($query);
		if (!is_numeric($result)) $result = 1; else $result++;
		return $result;
	}

	function get_option_label($map_product,$combo) {

		if (is_array(unserialize($map_product->options))) {
			$product_options = unserialize($map_product->options);
			$lbl_index = 0;
			$label = "";
			foreach ($product_options as $gkey=>$option_group) {
				foreach($option_group['options'] as $okey=>$option) {
					foreach ($combo as $check_value) {
						if ($option['id'] == $check_value) {
							if ($lbl_index > 0) $seperator = ', '; else $seperator = '';
							$label .= $seperator.$option['name'];
							$lbl_index++;
						}
					}
				}
			}
		}
		return $label;
	}

	function get_option_optionkey($map_product,$ids,$deprecated = false) {
		if ($deprecated) $factor = 101;
		else $factor = 7001;
		if (empty($ids)) return 0;
		$key = null;
		foreach ($ids as $set => $id)
			$key = $key ^ ($id*$factor);
		return $key;
	}

	function get_optionid_label($map_product, $check_value) {
		if (is_array(unserialize($map_product->options))) {
			$product_options = unserialize($map_product->options);
			foreach ($product_options as $gkey=>$option_group) {
				foreach($option_group['options'] as $okey=>$option) {
					if ($option['id'] == $check_value) {
						return $option['name'];
					}
				}
			}
		}
	}

	function get_row_mapped_var($id,$column_header) {
		global $wpdb;
		$query = "SELECT {$column_header} FROM {$wpdb->prefix}shopp_importer WHERE (id = '{$id}') ORDER BY id limit 1";
		$result = $wpdb->get_var($query);
		return $result;
	}

	function key_to_category_by_uri($uri) {
		foreach ($this->categories as $key=>$category) {
			if ($category->uri == $uri) return $key;
		}
		return null;
	}

	function parse_float($floatString){
	    if (is_numeric($floatString)) return $floatString;
	    $LocaleInfo = localeconv();
	    $thousep = strlen($LocaleInfo["mon_thousands_sep"]>0)?$LocaleInfo["mon_thousands_sep"]:",";
	    $decplac = strlen($LocaleInfo["mon_decimal_point"]>0)?$LocaleInfo["mon_decimal_point"]:".";
	    $newfloatString = str_replace($thousep, "", $floatString);
	    $newfloatString = str_replace($decplac, ".", $newfloatString);
	    return floatval(preg_replace('/[^0-9.]*/','',$newfloatString));
	}

	// !bookmark : function populate_images (to be employed later)

	/*function populate_images($product_set,&$images = array()) {
		$product_set_id = '';
		foreach ($product_set as $pmap) {
			foreach ($this->map as $mset) {
				switch ($mset['type']) {
					case 'image':
						$img = $this->get_mapped_var($pmap->spi_id,$mset['header']);
						if (array_search($img, $images) === false) {
							$images[] = $this->get_mapped_var($pmap->spi_id,$mset['header']);
							$product_set_id = $pmap->product_id;
						}
						break;
				}
			}
		}
		if (strlen($product_set_id) > 0) {
			$spi_images = new spi_images($this->spi);
				$process_count = $spi_images->import_product_images($product_set_id,$images);
			unset($spi_images);
		}
		return $process_count;
	}*/

	function _populate_image($product_set,$pmap,$mset) {

		$process_count = 0;
		$product_set_id = $pmap->product_id;
		$img = $this->get_mapped_var($pmap->spi_id,$mset['header']);
		if (strlen($product_set_id) > 0) {
			$spi_images = new spi_images($this->spi);

			$img = home_url( '/' . $this->Shopp->Settings->get( 'catskin_importer_imageformat' ) );
			$img = str_replace( '{val}', $this->get_row_mapped_var( $pmap->id, $mset[ 'header' ] ), $img );
			$process_count = $spi_images->import_product_images( $product_set_id, array( $img ) );
			unset($spi_images);
		}
		return $process_count;
	}

	function process_all( $status ) {
		global $wpdb;
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status};";
		$result = $wpdb->query($query);
		return $result;
	}

	function process_image( $id, $column_header, $column_value, $status ) {
		global $wpdb;
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status} WHERE spi_id  = '{$id}' AND {$column_header} = '{$column_value}'";
		$result = $wpdb->query($query);
		return $result;
	}

	function process_product($row_id,$status) {
		global $wpdb;
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status} WHERE id = '{$row_id}'";
		$result = $wpdb->query($query);
		return $result;
	}

	function process_set($id,$status,$shopp_product_id = null) {
		global $wpdb;
		$id = trim($id);
		$prod_id = ( !is_null( $shopp_product_id ) ) ?
			", product_id = '{$shopp_product_id}'" :
			"";
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status} {$prod_id} WHERE spi_id = '{$id}'";
		$result = $wpdb->query($query);
		return $result;
	}

	function remove_product_existing( $id ) {
		global $wpdb;
		$id = trim($id);
		wp_delete_post( (int) $id, true );

		$image_caches = $wpdb->get_results( "SELECT id FROM wp_shopp_meta WHERE context='product' AND type='image' AND parent='{$id}';" );
		foreach( $image_caches as $image_cache)
		{
			$wpdb->query( "DELETE FROM wp_shopp_meta WHERE context='image' AND parent='$image_cache->id';" );
		}
		$wpdb->query( "DELETE FROM wp_shopp_meta WHERE context='product' AND parent='$id';" );
		$prices = $wpdb->get_results( "SELECT id FROM wp_shopp_price WHERE product='$id';" );
		$wpdb->query( "DELETE FROM wp_shopp_price WHERE product='$id';" );
		foreach ($prices as $price) {
			$wpdb->query( "DELETE FROM wp_shopp_meta WHERE context='price' AND parent='{$price->id}';" );
		}
		return 1;
	}

	function remove_product_import( $id ) {
		global $wpdb;
		$id = trim($id);
		$wpdb->show_errors();
		$query = "DELETE FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$id}'";
		$result = $wpdb->query($query);
		return $result;
	}

	function product_by_csv_id( $csv_id ) {
		foreach ($this->products as $product) {
			if ($product->csv_id == $csv_id) return $product;
		}
		return null;
	}

	function product_exists( $sku ) {
		global $wpdb;
		$query = "SELECT product FROM {$wpdb->prefix}shopp_price WHERE (sku='".addslashes($sku)."' ) LIMIT 1;";
		return $wpdb->get_var($query);
	}

	function tag_exists( $name, $id ) {
		global $wpdb;
			$query = "SELECT * FROM {$wpdb->prefix}shopp_tag t,{$wpdb->prefix}shopp_catalog c WHERE (t.id = c.tag) AND (t.name = '{$name}' AND c.product = '{$id}');";
			$result = $wpdb->get_row($query);
		return $result;
	}
}

class map_product {
	var $id;
	var $shopp_id;
	var $categories = array();
	var $description;
	var $description_text;
	var $featured;
	var $images = array();
	var $inventory;
	var $name;
	var $options = array();
	var $prices = array();
	var $price;
	var $published;
	var $sale;
	var $sale_price;
	var $ship_fee;
	var $sku;
	var $slug;
	var $order;
	var $stock;
	var $summary;
	var $tags = array();
	var $tax;
	var $price_type;
	var $variations = array();
	var $specs = array();
	var $weight;
}

class map_category {
	var $name;
	var $value;
	var $exists;
	var $id;
	var $parent_id;
	var $slug;
	var $uri;
}

class map_tag {
	var $name;
	var $value;
	var $exists;
	var $id;
}

class map_image {
	var $name;
	var $value;
	var $exists;
	var $id;
}

class map_variation {
	var $name;
	var $id;
	var $shopp_product_id;
	var $csv_product_id;
	var $values;
}

class map_variation_value {
	var $key;
	var $value;
	var $index;
}

class map_variations {
	var $name;
	var $value;
	var $option_id;
	var $exists;
	var $id;
}

class map_spec {
	var $name;
	var $value;
	var $exists;
	var $id;
}
?>
