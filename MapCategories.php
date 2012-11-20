<?php
/**
 * Categorize
 *
 * Flow controller for category management interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage categories
 **/


class MapCategories extends AdminController {

	/**
	 * Categorize constructor
	 *
	 * @return void
	 * @author Rob Record
	 **/
	
	public $spi;
	
	function __construct ($spi) {
		parent::__construct();

		$this->spi = $spi;
		global $Shopp;

		$this->set_paths();
		// if (@$_GET['clean'] == 'clean') $this->clean_shopp_settings();

		//Maintenance Message??? 1.1 dev
		// $shopp_data_version = (int)$Shopp->Settings->get('db_version');
		// $shopp_first_run = $Shopp->Settings->get('display_welcome');
		// $shopp_setup_status = $Shopp->Settings->get('shopp_setup');	
		// $shopp_maintenance_mode = $Shopp->Settings->get('maintenance');	
		// if (SHOPP_VERSION >= '1.1' && ($data_version >= 1100 || $shopp_first_run != "off")) {
		// 		exit("<h2>Shopp Product Importer</h2><p>Complete Shopp installation prior to importing CSV's.</p>");
		// 		return false;
		// 	}
		// } elseif ($shopp_setup_status != "completed" || $shopp_maintenance_mode != "off" || $shopp_first_run != "off") {
		// 	exit("<h2>Shopp Product Importer</h2><p>Complete Shopp installation prior to importing CSV's.</p>");
		// 	return false;
		// }
		// 
		// $has_error = false;
		// $uuid = uniqid();  	





		// $temp = $this->get_shopp_categories();
		// foreach ($temp as $cat) {
		// 	$this->shopp_categories[$cat->id] = $cat;
		// 	$this->cat_parents[$cat->parent][$cat->id] = $cat->name;
		// }
		// $temp = $this->shopp_categories;
		// foreach ($this->cat_parents as $parent => $children) {
		// 	$this->shopp_categories[$parent]->children = $children;
		// }
		// foreach ($this->shopp_categories as $id => &$cat) {
		// 	if (!$cat->children) {
		// 		$cat_uris = $this->recurse_array($id);
		// 	}
		// 	// var_dump($cat_uris);
		// }




		// $cat_struct[($parent_id=0)]=array();
		// $cats = $this->shopp_categories;
		// var_dump($this->shopp_categories);
		// while (!empty($cats)) {
		// 	$cat_struct[$parent_id] = $this->add_children(&$cats,$parent_id);
		// 	if ($level > 5) break;
		// }
		// var_dump($cat_struct);die();
		// var_dump($cat_parents);die();
		// $parent_id = 0;
		// $i = 0;
		// while (!empty($temp)) {
			// while ($i<count($temp)) {
			// 	$cat = &$temp[$i];
			// 	echo "<p>cat ".$cat->id."</p>";
			// 	if ($cat->parent == $parent_id) {
			// 		echo "<p>cat is parent ".$parent_id."</p>";
			// 		unset($temp[$i]);
			// 	}
			// 	echo "<p>".count($temp)." left</p>";
			// 	$i++;
			// }
			// $i=0;
			// $parent_id = $temp[$i]->parent;
		// }
		
		if (!empty($_GET['id']) && !isset($_GET['a'])) {

			wp_enqueue_script('postbox');
			if ( user_can_richedit() ) {
				wp_enqueue_script('editor');
				wp_enqueue_script('quicktags');
				add_action( 'admin_print_footer_scripts', 'wp_tiny_mce', 20 );
			}
			
			shopp_enqueue_script('colorbox');
			shopp_enqueue_script('editors');
			shopp_enqueue_script('category-editor');
			shopp_enqueue_script('priceline');
			shopp_enqueue_script('ocupload');
			shopp_enqueue_script('swfupload');
			shopp_enqueue_script('shopp-swfupload-queue');

			do_action('shopp_category_editor_scripts');
			add_action('admin_head',array(&$this,'layout'));
		} elseif (!empty($_GET['a']) && $_GET['a'] == 'arrange') {
			shopp_enqueue_script('category-arrange');
			do_action('shopp_category_arrange_scripts');
			add_action('admin_print_scripts',array(&$this,'arrange_cols'));
		} elseif (!empty($_GET['a']) && $_GET['a'] == 'products') {
			shopp_enqueue_script('products-arrange');
			do_action('shopp_category_products_arrange_scripts');
			add_action('admin_print_scripts',array(&$this,'products_cols'));
		} else add_action('admin_print_scripts',array(&$this,'columns'));
		
		do_action('shopp_category_admin_scripts');
		add_action('load-shopp_page_shopp-importer-catmap',array(&$this,'workflow'));
	}
	function set_paths() {
		if ( !defined('ABSPATH') )
			define('ABSPATH', dirname(__FILE__) . '/');
		
		$this->basepath = WP_PLUGIN_DIR;
		$this->path = WP_PLUGIN_DIR . '/' . array_pop(explode('/',dirname(__FILE__)));
		$this->directory = basename($this->path);
		$this->csv_upload_path = ABSPATH.'../wordpress2/edge3/';
		$this->csv_get_path = WP_CONTENT_DIR.'/edge/';
		$this->csv_archive_path = WP_CONTENT_DIR.'/import_archive/';
		$this->html_get_path = WP_CONTENT_DIR.'/product_htmls/';
		$this->image_put_path = WP_CONTENT_DIR.'/products/';
		$this->csv_missing_images_path = $this->csv_archive_path.'missing_images/';
	}
	

	/**
	 * Parses admin requests to determine which interface to display
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function admin () {
		if (!empty($_GET['id']) && !isset($_GET['a'])) $this->editor();
		elseif (!empty($_GET['id']) && isset($_GET['a']) && $_GET['a'] == "products") $this->products();
		elseif (@$_GET['mi'] == "missing") $this->missing_images();
		else $this->categories();
	}

	/**
	 * Handles loading, saving and deleting categories in a workflow context
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function workflow () {
		global $Shopp;
		$db =& DB::get();
		$defaults = array(
			'page' => false,
			'deleting' => false,
			'delete' => false,
			'id' => false,
			'save' => false,
			'duplicate' => false,
			'next' => false
		);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		if (!defined('WP_ADMIN') || !isset($page)
			|| $page != $this->Admin->pagename('importer-catmap')
			)
				return false;

		$adminurl = admin_url('admin.php');
		
		
		if ($page == $this->Admin->pagename('importer-catmap')
				&& !empty($deleting)
				&& !empty($delete)
				&& is_array($delete)) {
			foreach($delete as $deletion) {
				$EDGECategory = new EDGECategory($deletion);
				if (empty($EDGECategory->id)) continue;
				$db->query("UPDATE $EDGECategory->_table SET parent=0 WHERE parent=$Category->id");
				$EDGECategory->delete();
			}
			$redirect = (add_query_arg(array_merge($_GET,array('delete'=>null,'deleting'=>null)),$adminurl));
			shopp_redirect($redirect);
		}
		
		if ($id && $id != "new") {
			$Shopp->EDGECategory = new EDGECategory($id);
			$Shopp->EDGECategory->load_data(array('categories'));
		}
		else $Shopp->EDGECategory = new EDGECategory();
		
		if ($save) {
			$this->save($Shopp->EDGECategory);
			$this->Notice = '<strong>'.stripslashes($Shopp->EDGECategory->name).'</strong> '.__('has been saved.','Shopp');

			if ($next) {
				if ($next != "new")
					$Shopp->EDGECategory = new EDGECategory($next);
				else {
					$Shopp->EDGECategory = new EDGECategory();
					$Shopp->EDGECategory->load_data(array('categories'));
				}
			} else {
				if (empty($id)) $id = $Shopp->EDGECategory->id;
				$Shopp->EDGECategory = new EDGECategory($id);
				$Shopp->EDGECategory->load_data(array('categories'));
			}
		}
	}
	function missing_images ($workflow=false) {
		global $Shopp;
		$db = DB::get();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		// var_dump($Shopp);
		
		$pd = DatabaseObject::tablename(Product::$table);
		$pt = DatabaseObject::tablename(Price::$table);
		$catt = DatabaseObject::tablename(Category::$table);
		$clog = DatabaseObject::tablename(Catalog::$table);
		//$clog = DatabaseObject::tablename(Catalog::$table);

		$orderby = "pd.created DESC";

		$where = "true";
		$having = "";

		
		$columns = "SQL_CALC_FOUND_ROWS pd.id,pd.name,pd.slug, pt.sku";

		// Load the products
		$query = "SELECT $columns FROM $pd AS pd LEFT JOIN $pt AS pt ON pd.id=pt.product AND pt.type != 'N/A' AND pt.context != 'addon' LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $catt AS cat ON cat.id=clog.parent AND clog.type='category' WHERE $where GROUP BY pd.id $having";
		$Products = $db->query($query,AS_ARRAY);

		$productcount = $db->query("SELECT FOUND_ROWS() as total");
		foreach ($Products as $product) {
			$obj = new Product($product->id);
			$obj->load_data(array('images'));
			if (empty($obj->images)) $affected[] = $product->sku;
		}
		unset($Products);
		
		if (@$affected) {
			echo 'Items with missing images: '.count($affected).'<br>';
			foreach ($affected as $sku) echo $sku.'<br>';
			// var_dump($affected);
			echo 'Path is '.$this->csv_missing_images_path.'<br>';
			if (file_exists($this->csv_missing_images_path) || mkdir($this->csv_missing_images_path)) {
				$csvs = $this->find_csvs($this->csv_archive_path);
				echo 'CSVs found: '.count($csvs).'<br>';
				krsort($csvs);
				$fp = fopen($this->csv_missing_images_path.'missing_images.csv', 'w');
				fputcsv($fp, array("itKey","itVendorId","itVendStyleCode","itRetailPrice","itCurrentPrice","itImage","itStatus","itDesc","catId","catName","catCategoryType","catGenDesc"));

				foreach ($csvs as $csv_name=>$csv_dir) {
					if (($handle = fopen($csv_dir.$csv_name.'.csv', "r")) !== FALSE) {
						echo 'Looking in '.$csv_name.'.csv<br>';
						while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
							if ($data[0] !== 'itKey') {
								//if (in_array($data[0],$affected))
								foreach ($affected as $key=>$sku) {
									if ($data[0] == $sku) {
										unset($affected[$key]);
										echo $sku.'<br>';
										fputcsv($fp, $data);
										if (!copy($csv_dir.$data[5], $this->csv_missing_images_path.$data[5]))
											echo 'Could not copy '.$csv_dir.$data[5].' to '.$this->csv_missing_images_path.$data[5].'<br>';
										//$data[5]
									}
								}
							}
						}
						fclose($handle);
					}
				
				}
				echo "Done.";
			
			} else echo 'Couldn\'t create directory '.$this->csv_missing_images_path;
		}
		fclose($fp);
	}
	function find_csvs($dir) {
		$csvs = array();
		if ($handle = opendir($dir)) {
			/* loop through directory. */  
			while (false !== ($subdir = readdir($handle))) {
				if (($subdir !== '.') && $subdir !== '..') {
					$subdir = $dir.$subdir.'/';
					if (is_dir($subdir)) {
						$these_csvs = $this->find_csvs_in_dir($subdir);
						foreach ($these_csvs as $csv_name => $csv_dir) {
							$csvs[$csv_name] = $csv_dir;
						}
					}
				}
			}  
		closedir($handle);  
		}
		return $csvs;
	}
	function find_csvs_in_dir($dir) {
			if ($handle = opendir($dir)) {
			$csvs = array();
			$latest_modified = false;
			$accepted_exts = array('csv','jpg','gif','png');
			while (false !== ($file = readdir($handle))) {
				$path_parts = pathinfo($dir.$file);
				if (preg_match('/^20[0-9\-]+$/',$path_parts['filename'])) {
					foreach ($accepted_exts as $acc_ext) {
						if ($path_parts['extension'] == $acc_ext) {
							if ($path_parts['extension'] == 'csv') $csvs[$path_parts['filename']] = $dir;
						}
					}
				}
			}
			closedir($handle);
		  
			return $csvs;
		} else return false;
	}
	/**
	 * Interface processor for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function categories ($workflow=false) {
		global $Shopp;
		$db = DB::get();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'pagenum' => 1,
			'per_page' => 60,
			's' => '',
			'a' => '',
			'p' => ''
			);
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		if ('arrange' == $a)  {
			$this->init_positions();
			$per_page = 300;
		}

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1));

		$filters = array();
		// $filters['limit'] = "$start,$per_page";
		if (!empty($s)) $filters['where'] = "cat.name LIKE '%$s%'";

		$table = DatabaseObject::tablename(EDGECategory::$table);

		$EDGECatalog = new EDGECatalog();
		$EDGECatalog->outofstock = true;
		if ($workflow) {
			$filters['columns'] = "cat.id,cat.parent,cat.priority";
			$results = $EDGECatalog->load_categories($filters,false,true);
			return array_slice($results,$start,$per_page);
		} else {
			if ('arrange' == $a) {
				$filters['columns'] = "cat.id,cat.parent,cat.priority,cat.name,cat.uri,cat.slug";
				$filters['parent'] = '0';
			} else $filters['columns'] = "cat.id,cat.parent,cat.priority,cat.name,cat.description,cat.uri,cat.slug,cat.spectemplate,cat.facetedmenus,count(DISTINCT pd.id) AS total";

			$EDGECatalog->load_categories($filters);
			$EDGECategories = array_slice($EDGECatalog->categories,$start,$per_page);
			
			// var_dump($EDGECategories);
			// var_dump($EDGECatalog->categories);
		}
		
		if ('process' == $p) $this->process_categories(false,$EDGECatalog);
		var_dump($workflow);
		// var_dump($EDGECategories);

		$count = $db->query("SELECT count(*) AS total FROM $table");
		$num_pages = ceil($count->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( array('edit'=>null,'pagenum' => '%#%' )),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		
		
		$table = DatabaseObject::tablename(Category::$table);
		$Catalog = new Catalog();
		$Catalog->outofstock = true;
		$Catalog->load_categories($filters);
		$Categories = $Catalog->categories;
		
		

		$action = esc_url(
			add_query_arg(
				array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('importer-catmap'))),
				admin_url('admin.php')
			)
		);

		// if ('arrange' == $a) {
		// 	include(SHOPP_ADMIN_PATH."/categories/arrange.php");
		// 	return;
		// }

		// $Shopp->Flow->Controller = &$this;
		// var_dump($Shopp->Flow);
		
		// var_dump($EDGECategories);
		include("catmap.php");
	}
	
	function process_categories($remote=false,$EDGECatalog=false,$spi)
	{
		
		if (!$EDGECatalog) {
			$filters['columns'] = "cat.id,cat.parent,cat.priority,cat.name,cat.description,cat.uri,cat.slug,cat.spectemplate,cat.facetedmenus,count(DISTINCT pd.id) AS total";
			$EDGECatalog = new EDGECatalog();
			$EDGECatalog->outofstock = true;
			$EDGECatalog->load_categories($filters);
		}
		$Catalog = new Catalog();
		$Catalog->load_categories();
		foreach ($Catalog->categories as $shopp_category)
			$shopp_categories[$shopp_category->id] = $shopp_category->parent;
		// var_dump($shopp_category);
		// var_dump($shopp_categories);
		
		// var_dump($EDGECatalog->categories);
		$notice='';
		foreach ($EDGECatalog->categories as $edge_category) {
			$temp = "<li>EDGE Category {$edge_category->id}: {$edge_category->name}</li>";
			$updates=array();
			if (!empty($edge_category->products)) {
				$temp .= "<ul>";
				foreach ($edge_category->products as $product_id => $product_name) {
					$temp .= "<li>Product $product_id: $product_name</li>";
					$Product = new Product($product_id);
					if (!empty($edge_category->shopp_categories->categories)) {
						$temp .= "<ul>";
						$updates = array();
						foreach ($edge_category->shopp_categories->categories as $shopp_category_id => $shopp_category_name) {
							if ($shopp_category_id) {
								// var_dump($edge_category->products);
								// if (!array_key_exists($product_id,$edge_category->products)
								// 	&& !$updates[$shopp_category_id])
								if (!isset($updates[$shopp_category_id]))
								{
									$updates[$shopp_category_id] = $shopp_category_id;
									$temp .= "<li>Added to category {$shopp_category_name} ({$shopp_category_id})</li>";
									$shopp_parent_id = $shopp_categories[$shopp_category_id];
									if (isset($shopp_parent_id) && $shopp_parent_id > 0
									&&	!isset($updates[$shopp_parent_id])
									&&	!array_key_exists($product_id,$Catalog->categories["_$shopp_parent_id"]->products))
									{
										$updates[$shopp_categories[$shopp_category_id]] = $shopp_categories[$shopp_category_id];
									}
									
								} else {
									// $temp .= "<li>Already exists in $shopp_category_name ($shopp_category_id)</li>";
								}
								// var_dump($shopp_categories[$shopp_category_id],'');
							}
						}
						// var_dump($updates);
						$Product->save_categories($updates);
						
						$temp .= "</ul>";
					}
				}
				$temp .= "</ul>";
			}
			// $notice .= (!empty($updates)?$temp:'');
			$notice .= $temp;
		}
		
		if ($remote) return "category map finished";
		
		$this->Notice = "<h3>Product assignment results</h3>";
		if ($notice) $this->Notice .= "<ul>$notice</ul>";
		elseif (!empty($EDGECatalog->categories->shopp_categories->categories)) $this->Notice .= "<p>Everything already assigned</p>";
		else $this->Notice .= "<p>Nothing assigned</p>".$temp;
		
		return $this->Notice;
	}

	/**
	 * Registers column headings for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function columns () {
		register_column_headers('shopp_page_shopp-importer-catmap', array(
			'cb'=>'<input type="checkbox" />',
			'id'=>'EDGE ID',
			'name'=>__('EDGE Category','Shopp'),
			'links'=>__('Products','Shopp'),
			'cats'=>'Shopp Categories'
			// 'templates'=>__('Templates','Shopp'),
			// 'menus'=>__('Menus','Shopp'))
		));
	}

	/**
	 * Provides the core interface layout for the category editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function layout () {
		global $Shopp;
		$Admin =& $Shopp->Flow->Admin;
		include("ui.php");
	}

	/**
	 * Registers column headings for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function arrange_cols () {
		register_column_headers('shopp_page_shopp-importer-catmap', array(
			'cat'=>__('Category','Shopp'),
			'move'=>'<div class="move">&nbsp;</div>')
		);
	}

	/**
	 * Interface processor for the category editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function editor () {
		global $Shopp,$EDGECategoryImages;
		$db = DB::get();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		// if (@$_GET['id']) 
		if (empty($Shopp->EDGECategory)) $EDGECategory = new EDGECategory();
		else $EDGECategory = $Shopp->EDGECategory;

		// $EDGECategory->load_images();

		// $Price = new Price();
		// $priceTypes = array(
		// 	array('value'=>'Shipped','label'=>__('Shipped','Shopp')),
		// 	array('value'=>'Virtual','label'=>__('Virtual','Shopp')),
		// 	array('value'=>'Download','label'=>__('Download','Shopp')),
		// 	array('value'=>'Donation','label'=>__('Donation','Shopp')),
		// 	array('value'=>'N/A','label'=>__('N/A','Shopp'))
		// );

		// Build permalink for slug editor
		// $permalink = trailingslashit(shoppurl())."category/";
		// $EDGECategory->slug = apply_filters('editable_slug',$EDGECategory->slug);
		// if (!empty($EDGECategory->slug))
		// 	$permalink .= substr($EDGECategory->uri,0,strpos($EDGECategory->uri,$EDGECategory->slug));

		// $pricerange_menu = array(
		// 	"disabled" => __('Price ranges disabled','Shopp'),
		// 	"auto" => __('Build price ranges automatically','Shopp'),
		// 	"custom" => __('Use custom price ranges','Shopp'),
		// );


		// $categories_menu = $this->menu($EDGECategory->parent,$EDGECategory->id);
		// $categories_menu = '<option value="0">'.__('Parent Category','Shopp').'&hellip;</option>'.$categories_menu;

		// $uploader = $Shopp->Settings->get('uploader_pref');
		// if (!$uploader) $uploader = 'flash';

		$workflows = array(
			"continue" => __('Continue Editing','Shopp'),
			"close" => __('Categories Manager','Shopp'),
			"new" => __('New Category','Shopp'),
			"next" => __('Edit Next','Shopp'),
			"previous" => __('Edit Previous','Shopp')
			);
			
			// echo editcatl;
			// var_dump($EDGECategory->categories);

		include("editcat.php");
		
	}

	/**
	 * Handles saving updated category information from the category editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function save ($Category) {
		global $Shopp;
		$Settings = &ShoppSettings();
		$db = DB::get();
		check_admin_referer('shopp-save-edge-category');

		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$Settings->saveform(); // Save workflow setting

		$Shopp->Catalog = new Catalog();
		$Shopp->Catalog->load_categories(array(
			'columns' => "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug",
			'where' => array(),
			'joins' => array(),
			'orderby' => false,
			'order' => false,
			'outofstock' => true
		));
		
		$Category->update_slug();
		
		// TODO save categories
		// 

		// if (!empty($_POST['deleteImages'])) {
		// 	$deletes = array();
		// 	if (strpos($_POST['deleteImages'],","))	$deletes = explode(',',$_POST['deleteImages']);
		// 	else $deletes = array($_POST['deleteImages']);
		// 	$Category->delete_images($deletes);
		// }

		// Variation price templates
		// if (!empty($_POST['price']) && is_array($_POST['price'])) {
		// 	foreach ($_POST['price'] as &$pricing) {
		// 		$pricing['price'] = floatvalue($pricing['price'],false);
		// 		$pricing['saleprice'] = floatvalue($pricing['saleprice'],false);
		// 		$pricing['shipfee'] = floatvalue($pricing['shipfee'],false);
		// 	}
		// 	$Category->prices = stripslashes_deep($_POST['price']);
		// } else $Category->prices = array();

		// if (empty($_POST['specs'])) $Category->specs = array();
		// else $_POST['specs'] = stripslashes_deep($_POST['specs']);

		// if (empty($_POST['options'])
		// 	|| (count($_POST['options']['v'])) == 1 && !isset($_POST['options']['v'][1]['options'])) {
		// 		$_POST['options'] = $Category->options = array();
		// 		$_POST['prices'] = $Category->prices = array();
		// } else $_POST['options'] = stripslashes_deep($_POST['options']);
		// if (isset($_POST['content'])) $_POST['description'] = $_POST['content'];


		$Category->updates($_POST);
		$Category->save();
		
		$Category->save_categories($_POST['categories']);
		

		// if (!empty($_POST['images']) && is_array($_POST['images'])) {
		// 	$Category->link_images($_POST['images']);
		// 	$Category->save_imageorder($_POST['images']);
		// 	if (!empty($_POST['imagedetails']) && is_array($_POST['imagedetails'])) {
		// 		foreach($_POST['imagedetails'] as $i => $data) {
		// 			$Image = new CategoryImage($data['id']);
		// 			$Image->title = $data['title'];
		// 			$Image->alt = $data['alt'];
		// 			$Image->save();
		// 		}
		// 	}
		// }

		do_action_ref_array('shopp_category_saved',array(&$Category));

		$updated = '<strong>'.$Category->name.'</strong> '.__('category saved.','Shopp');

	}

	/**
	 * Set
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function init_positions () {
		$db =& DB::get();
		// Load the entire catalog structure and update the category positions
		$Catalog = new Catalog();
		$Catalog->outofstock = true;

		$filters['columns'] = "cat.id,cat.parent,cat.priority";
		$Catalog->load_categories($filters);

		foreach ($Catalog->categories as $Category)
			if (!isset($Category->_priority) // Check previous priority and only save changes
					|| (isset($Category->_priority) && $Category->_priority != $Category->priority))
				$db->query("UPDATE $Category->_table SET priority=$Category->priority WHERE id=$Category->id");

	}

	/**
	 * Renders a drop-down menu for selecting parent categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @param int $selection The id of the currently selected parent category
	 * @param int $current The id of the currently edited category
	 * @return void Description...
	 **/
	function menu ($selection=false,$current=false) {
		$db = DB::get();
		$table = DatabaseObject::tablename(EDGECategory::$table);
		$edge_categories = $db->query("SELECT id,name,parent FROM $table ORDER BY parent,name",AS_ARRAY);
		
		$table = DatabaseObject::tablename(Category::$table);
		$categories = $db->query("SELECT id,name,parent FROM $table ORDER BY parent,name",AS_ARRAY);
		
		// echo "edge categories",var_dump($edge_categories);
		// echo "shopp categories",var_dump($categories);
		$edge_categories = $this->edge_sort_tree($edge_categories);
		$categories = sort_tree($categories);
		// echo "edge categories after sort",var_dump($edge_categories);
		// echo "shopp categories after sort",var_dump($categories);

		$options = '';
		foreach ($categories as $category) {
			$padding = str_repeat("&nbsp;",$category->depth*3);
			$selected = ($category->id == $selection)?' selected="selected"':'';
			$disabled = ($current && $category->id == $current)?' disabled="disabled"':'';
			$options .= '<option value="'.$category->id.'"'.$selected.$disabled.'>'.$padding.esc_html($category->name).'</option>';
		}

		return $options;
	}

	/**
	 * Recursively sorts a heirarchical tree of data
	 *
	 * @param array $item The item data to be sorted
	 * @param int $parent (internal) The parent item of the current iteration
	 * @param int $key (internal) The identified index of the parent item in the current iteration
	 * @param int $depth (internal) The number of the nested depth in the current iteration
	 * @return array The sorted tree of data
	 * @author Jonathan Davis / Rob Record
	 **/
	function edge_sort_tree ($items,$parent=0,$key=-1,$depth=-1) {
		$depth++;
		$position = 1;
		$result = array();
		if ($items) {
			foreach ($items as $item) {
				// Preserve initial priority
				if (isset($item->priority))	$item->_priority = $item->priority;
				if ($item->parent == $parent) {
					$item->parentkey = $key;
					$item->depth = $depth;
					$item->priority = $position++;
					$result[] = $item;
					$children = $this->edge_sort_tree($items, $item->id, count($result)-1, $depth);
					$result = array_merge($result,$children); // Add children in as they are found
				}
			}
		}
		$depth--;
		return $result;
	}

	/**
	 * Registers column headings for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function products_cols () {
		register_column_headers('shopp_page_shopp-importer-catmap', array(
			'move'=>'<img src="'.SHOPP_ADMIN_URI.'/icons/updating.gif" alt="updating" width="16" height="16" class="hidden" />',
			'p'=>__('Product','Shopp'))
		);
	}

	/**
	 * Interface processor for the product list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function products ($workflow=false) {
		global $Shopp;
		$db = DB::get();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'pagenum' => 1,
			'per_page' => 500,
			'id' => 0,
			's' => ''
			);
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1));

		$filters = array();
		// $filters['limit'] = "$start,$per_page";
		if (!empty($s))
			$filters['where'] = "cat.name LIKE '%$s%'";
		else $filters['where'] = "true";

		$Category = new Category($id);

		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$product_table = DatabaseObject::tablename(Product::$table);
		$columns = "c.id AS cid,p.id,c.priority,p.name";
		$where = "c.parent=$id AND type='category'";
		$query = "SELECT $columns FROM $catalog_table AS c LEFT JOIN $product_table AS p ON c.product=p.id WHERE $where ORDER BY c.priority ASC,p.name ASC LIMIT $start,$per_page";
		$products = $db->query($query);

		$count = $db->query("SELECT count(*) AS total FROM $table");
		$num_pages = ceil($count->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( array('edit'=>null,'pagenum' => '%#%' )),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));

		$action = esc_url(
			add_query_arg(
				array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('importer-catmap'))),
				admin_url('admin.php')
			)
		);


		include(SHOPP_ADMIN_PATH."/categories/products.php");
	}

 // ...lots of your regular code, then:
    public function __call($name, $args)
    {
        throw new Exception('Undefined method ' . $name . '() called');
    }

} // END class Categorize

?>