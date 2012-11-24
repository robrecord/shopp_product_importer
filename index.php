<?php
/**
	Plugin Name: Shopp Product Importer v2
	Plugin URI: http://www.catskinstudio.com
	Version: 0.9.3	
	Description: Shopp Product Importer Shopp/Wordpress Plugin provides a mechanisim to import Products from a specifically formatted CSV file into the shopp product database.
	Author: Lee Tagg (leet at catskinstudio dot com), fixed by Tim van den Eijnden (@timvdeijnden)
	Author URI: http://www.catskinstudio.com
	Copyright: Copyright © 2010 Catskin Studio
	Licence: GNU GENERAL PUBLIC LICENSE Version 3, 29 June 2007
**/


if (!function_exists("preprint")) { 
    function preprint($s, $return=false) { 
        $x = "<pre>"; 
        $x .= print_r($s, 1); 
        $x .= "</pre>"; 
        if ($return) return $x; 
        else print $x; 
    } 
}
ini_set('memory_limit', 256*1024*1024);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
// set_error_handler('shopp_product_importer::spi_errors');

// echo "Memory limit: ".ini_get('memory_limit');

// $Errors =& ShoppErrors();
// $errors[] = new ShoppError(__('Enter an email address or login name','Shopp'));
// die;



//Get helper classes
include_once("spi_admin.php");
include_once("spi_data.php");
include_once("spi_db.php");
include_once("spi_files.php");
include_once("spi_images.php");
include_once("spi_model.php");

/*Removed as they are no longer part of the import */
//include_once("spi_options.php");
//include_once("spi_products.php");
//include_once("spi_categories.php");
//include_once("spi_tags.php");
//include_once("spi_variations.php");

$spi = new shopp_product_importer();

class shopp_product_importer {
	//Shopp
	public $Shopp;
	//Maps
	public $column_map = null;
	public $variation_map = null;  
	public $spec_map = null;
	public $category_map = null;
	public $tag_map = null;
	public $image_map = null;
	//Data
	public $data = null;	
	public $options = array();	
	public $mapped_product_ids = array();
	//Paths
	public $html_get_path;
	public $csv_get_path; 
	public $csv_archive_path; 
	public $image_put_path;
	public $basepath;
	public $path;
	public $directory;	
	//Html Removal
	public $remove_from_description = array(
		'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"',
		'"http://www.w3.org/TR/html4/loose.dtd">',
		'<html>',
		'<head>',
		'<title>Untitled Document</title>',
		'face=SymbolMT size=5&gt;',
		'&#56256;&#56510;',
		'<spec http-equiv="Content-Type" content="text/html; charset=iso-8859-1">',
		'</head>',
		'<body>',
		'</body>',
		'</html>',	
	);	
	public $debug = true;
	public $auto_import = false;
	
	function spi_errors($errno, $errstr,$errfile, $errline)
	{
		if (dirname($errfile)==dirname(__FILE__)) {
			$this->log("Error: [$errno] $errstr in $errfile on $errline ",0);
		}
	}		
	
	function report_errors() {
		if (isset($_SESSION['spi_errors'])) { 
			echo $this->log($_SESSION['spi_errors']);
			unset($_SESSION['spi_errors']);
		}  	
	}
		
	function send_email_report($result) {
		if (($this->Shopp->Settings->get('catskin_importer_send_email') == 'yes') && function_exists('mail') && (!isset($this->auto_import_test))) {
			$subject = "***** Report for product upload to Seita Diamond Jewelers site";
			$body = 'An upload occurred at '.date('g.ia')." today.\n\nHere are the results:\n\n";
			if (isset($_SESSION['spi_errors'])) 
				foreach ($_SESSION['spi_errors'] as $key => $value)
					$body .= "$key : $value\n";
			$body .= "$result\n";
			foreach (array('uploads@seitadiamondjewelers.com') as $to) {
				if (!mail($to, $subject, $body)) {
					$this->log("Message delivery to $to failed.");
					return false;
				}	
			}
			return true;
					
		} else {
			$this->log("Message delivery not possible.");
			return false;
		}
	}
		
	function __construct() {
		global $Shopp;
		if (class_exists('Shopp')) {
			$this->Shopp = $Shopp;
			$this->set_paths();

			/* The activation hook is executed when the plugin is activated. */
			register_activation_hook(__FILE__,'shopp_importer_activation');
			/* The deactivation hook is executed when the plugin is deactivated */
			register_deactivation_hook(__FILE__,'shopp_importer_deactivation');
			/* This function is executed when the user activates the plugin */

			add_action('admin_menu', array(&$this, 'on_admin_menu'));
			add_action('wp_ajax_upload_spi_csv_file', array(&$this, 'ajax_upload_spi_csv_file'));
			add_action('wp_ajax_import_csv', array(&$this, 'ajax_import_csv'));
			add_action('wp_ajax_import_products', array(&$this, 'ajax_import_products'));
			add_action('wp_ajax_import_images', array(&$this, 'ajax_import_images'));
			add_action('wp_ajax_next_image', array(&$this, 'ajax_next_image'));			
			set_error_handler(array(&$this, 'spi_errors'));
			// $this->ajax_load_file();
			add_action('shopp_init', array(&$this, 'shopp_init'));
			add_action('shopp_loaded', array(&$this, 'shopp_loaded'));
			add_action('shopp_auto_import', array(&$this, 'automatic_start'));

			add_action('shopp_admin_menu', array(&$this, 'shopp_admin_menu'));
			
			//remove before uploading to production!
// 			$rand = rand(10, 99);
			add_action('shopp_auto_import_dev', array(&$this, 'automatic_start_test'));
			//wp_schedule_single_event(time()+1,'shopp_auto_import_dev');//.$rand);
			// end remove
			
			$gofs = get_option( 'gmt_offset' ); // get WordPress offset in hours
			$tz = date_default_timezone_get(); // get current PHP timezone
			date_default_timezone_set('Etc/GMT'.(($gofs < 0)?'+':'').-$gofs); // set the PHP timezone to match WordPress
			
			// date_default_timezone_set($tz); // set the PHP timezone back the way it was
			if (array_key_exists("test_auto", $_GET))  {
				$this->auto_import = true;
				$this->log("Start user-initiated auto import test");
				$this->automatic_start();
			}
		}
		
		
		function shopp_importer_activation()
		{
			// fix timezone
			// http://wordpress.org/support/topic/using-php-timezone
			$gofs = get_option( 'gmt_offset' ); // get WordPress offset in hours
			$tz = date_default_timezone_get(); // get current PHP timezone
			date_default_timezone_set('Etc/GMT'.(($gofs < 0)?'+':'').-$gofs); // set the PHP timezone to match WordPress
			
		  	wp_schedule_event(mktime(4, 0, 0) + 86400, 'daily', 'shopp_auto_import');
		  	//wp_schedule_event(mktime(17, 45, 0) + 86400, 'daily', 'shopp_auto_import_dev');
			date_default_timezone_set($tz); // set the PHP timezone back the way it was
			
		}
		/* This function is executed when the user deactivates the plugin */
		function shopp_importer_deactivation()
		{
		  wp_clear_scheduled_hook('shopp_auto_import');
		  wp_clear_scheduled_hook('shopp_auto_import_dev');
		}
	}
	function automatic_start_test() {
		$this->auto_import_test = true;
		$this->auto_import = true;
		$this->automatic_start();
	}
	
	function automatic_start()
	{
		set_time_limit(86400);
		set_error_handler(array(&$this, 'spi_errors'));
		
		if (($this->Shopp->Settings->get('catskin_importer_auto') == 'yes') || $this->auto_import == true) {
			$this->auto_import = true;
			$this->log("# - Started import");
			$csvs = $this->juggle_files();
			if (!empty($csvs)) {
			// if (list($csvs) = $this->find_csvs($this->csv_get_path)) {
				foreach ($csvs as $filename) {
					$this->log("> - Importing CSV $filename");
					$this->truncate_all_prior_to_import();
					if ($this->ajax_import_csv($filename))
						if ($this->ajax_import_products()) {
							$this->log("mapping categories");
							$this->map_categories();
							$this->log("starting images");
							$this->auto_import_images();
						}
				}
				$this->log("Finished");
			} else {
				$this->send_email_report("No new CSV uploaded.\nStopped.\n");
				$this->log("Stopped");
			}
			$this->auto_import = false;
			
		}
		
	}
	function map_categories()
	{
		global $MapCategories;
		set_error_handler(array(&$this, 'spi_errors'));
		$this->shopp_init();
		// 
		// require_once("{$this->basepath}/{$this->directory}/MapCategories.php");
		$MapCategories = new MapCategories($this);
		$result = $MapCategories->process_categories(true,false,$this);
		$this->log($result);
		if (!$this->auto_import) echo $result;
		
	}
	function find_csvs($dir)
	{
		
		if ($handle = opendir($dir)) {
			$csvs = array();
			$latest_modified = false;
			$accepted_exts = array('csv','jpg','gif','png');
			
			while (false !== ($file = readdir($handle))) {
				$path_parts = pathinfo($dir.$file);
				foreach ($accepted_exts as $acc_ext) {
					if ($path_parts['extension'] == $acc_ext) {
						if ($path_parts['extension'] == 'csv') {
							$csvs[] = $file;
							$this->log("found CSV: $file");
						}
						// $this->log($dir.$file);
						if ($latest_modified < filemtime($dir.$file)) $latest_modified = filemtime($dir.$file);
					}
				}
			}
			closedir($handle);
		  
			return array($csvs, $latest_modified);
		} else return false;
	}
	function juggle_files()
	{
		if ($result=$this->find_csvs($this->csv_upload_path)) {
			list ($csvs,$latest_modified) = $result;
			
			if (!empty($csvs)) {
				
				if ($latest_modified < time()){//-3600) {
					if (!(file_exists($this->csv_archive_path)) && !(mkdir($this->csv_archive_path))) {
						$this->log("Could not create import archive directory");
						if (!(rename($this->csv_get_path,$newpath=substr($this->csv_get_path, 0, -1)."_".date('Y-m-d_H-i-s'))))  {
							$his->log("Could not rename get_path to $newpath");
							// return false;
						}
						return false;
					}
					if (!(rename($this->csv_get_path,$newpath=substr($this->csv_archive_path.'edge/', 0, -1)."_".date('Y-m-d_H-i-s'))))  {
						$this->log("Could not rename get_path to $newpath");
						// return false;
					}
					if (!(rename($this->csv_upload_path,$this->csv_get_path))) {
						$this->log("Could not rename upload_path");
						return false;
					}
					if (!(mkdir($this->csv_upload_path))) {
						$this->log("Could not reinstate upload_path");
						return false;
					}
				} else {
					$this->log("Files were modified too recently  - upload may be occurring");
					return false;
				}
			} else {
				$this->log("Could not see any csv files");
				return false;
			}
			return $csvs;
		} else {
			$this->log("Could not find anything in upload_path dir: ".$this->csv_upload_path);
			return false;
		}
		
	}
	function auto_import_images()
	{
		while ($this->ajax_next_image() !== false) {
			
		} 
		// if ($image_result == 'no-images' || $image_result == 'no-more' || !$image_result) $this->log('END');
		// else $this->next_image();
	}
	function shopp_loaded()
	{
		
	}
	function shopp_init()
	{
		require_once("{$this->basepath}/{$this->directory}/EDGECategory.model.php");
		require_once("{$this->basepath}/{$this->directory}/EDGECatalog.model.php");
		require_once("{$this->basepath}/{$this->directory}/EDGECatMap.model.php");
		// add_action('admin_menu', array(&$this, 'admin_menu'));
		add_action('shopp_admin_menu', array(&$this, 'shopp_admin_menu'));
		
		global $MapCategories;
		foreach (array('MapCategories') as $controller) {
			$target = "{$this->basepath}/{$this->directory}/$controller.php";
			$link = SHOPP_FLOW_PATH."/$controller.php";
			if (!file_exists($link)) symlink( $target,$link );
		}
		require_once("{$this->basepath}/{$this->directory}/MapCategories.php");
		// var_dump($MapCategories);
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
	}	
	
	function on_admin_menu($args) {	
		if (SHOPP_VERSION < '1.1') {
			$page = add_submenu_page($this->Shopp->Flow->Admin->default,__('Importer','Shopp'), __('Importer','Shopp'), SHOPP_USERLEVEL, "shopp-importer", array(&$this,'shopp_importer_settings_page'));
			$spi_admin = new spi_admin($this);
			$help_content = $spi_admin->set_help();
			unset($spi_admin);
			add_contextual_help($page,$help_content);
		}
		if (SHOPP_VERSION >= '1.1') {
			global $wp_importers;
			register_importer("shopp_product_importer2","CSV Importer for Shopp Plugin 2","CSV Import for Shopp Plugin 2",array(&$this,'shopp_importer_settings_page'));
			//exit("The Importers: ".print_r($wp_importers,true));
		}
		// var_dump($this->Shopp->Flow->Admin->Pages);
		
		
	}	
	function shopp_admin_menu($ShoppAdmin)
	{
		global $MapCategoriesMenu;
		$ShoppMenu = $ShoppAdmin->MainMenu; //this is our Shopp menu handle
		// $ShoppAdmin->caps['importer-catmap'] = 'shopp-settings';
		$ShoppAdmin->caps['importer-catmap'] = defined('SHOPP_USERLEVEL')?SHOPP_USERLEVEL:'read';
		$ShoppAdmin->addpage('importer-catmap',__('Category Map','Shopp'),'MapCategories','Map EDGE category IDs to Shopp categories');
		// $MapCategoriesMenu = add_submenu_page($ShoppMenu,'Category Map','Category Map', 'read', "shopp-importer-catmap", array(&$this,'shopp_importer_catmap_page'));
		//
	}
	
	function admin_menu()
	{
		global $Shopp;
		global $MapCategoriesMenu;
		$ShoppMenu = $Shopp->Flow->Admin->MainMenu; //this is our Shopp menu handle
      	$MapCategoriesMenu = add_submenu_page($ShoppMenu,'Category Map','Category Map', 'read', "shopp-importer-catmap", array(&$this,'shopp_importer_catmap_page'));

		$defaults = array(
			'page' => false,
			'id' => false
			);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		if (!defined('WP_ADMIN') || !isset($page)
			|| $page != $ShoppAdmin->pagename('importer-catmap')
			)
				return false;
		

		$MapCategories = new MapCategories($this);
		
		// var_dump($ShoppAdmin->Pages);
		// $Shopp->Flow->Admin->Menus['shopp-catmap'] = 'shopp_page_shopp-importer-catmap';
		// $Shopp->Flow->Admin->caps['catmap'] = 'shopp-catmap';
		// $Shopp->Flow->Admin->Pages['shopp-catmap'] = new ShoppAdminPage('catmap','shopp-catmap','Category Map','../../../shopp_product_importer/MapCategories','Map EDGE category IDs to Shopp categories',false);
		// var_dump($Shopp->Flow->Admin->Pages);
		
	}
	
	function shopp_importer_catmap_page()
	{
		global $MapCategories;
		// var_dump($Shopp->Flow->Admin);die;
		// check_admin_referer('wp_ajax_shopp_category_menu');
		// echo $Shopp->Flow->Admin->pagename('importer-catmap');
		// echo '<option value="">Select a category&hellip;</option>';
		// echo '<option value="catalog-products">All Products</option>';
		// echo $MapCategories->workflow();
		echo $MapCategories->admin();
		exit();

	}
	
	
	function recurse_array($id,$index) {
		if ($parent = $this->shopp_categories[$id]->parent) {
			$parent = $this->recurse_array($parent);
		}
		return ($parent == '0' ? '' : $parent).'/'.$this->shopp_categories[$id]->name;
	}
	
	function get_shopp_categories() {
		global $wpdb;
			$query = "SELECT * FROM {$wpdb->prefix}shopp_category";
			$result = $wpdb->get_results($query);
		return $result;
	}	

	function shopp_importer_settings_page () {
		global $wpdb;
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-importer');
			if (SHOPP_VERSION < '1.1') {
				$this->Shopp->Flow->settings_save();
			} else {
				$this->Shopp->Settings->saveform();
			}
			$updated = __('Importer settings saved.');
		}		
		if (!empty($_POST['perform_import'])) {
			if ($this->start_import()) {
				$importing_now = "perform_import";
				$updated = __('Running Importer - Please Wait.....');
			}
		}
		include("{$this->basepath}/{$this->directory}/settings.php");
		$this->log(' settings',4);
		
	}
	
	function start_import()
	{
		$this->log('*** START',4);
		// $this->log();
		$this->log(' perform_import',4);
		
		check_admin_referer('shopp-importer');
		// set_time_limit(86400);
		// $this->map_columns_from_saved_options();
		// $this->log(' map_columns_from_saved_option',4);
		// $this->ajax_load_file();
		// $this->log(' ajax_load_file',4);
		$this->truncate_all_prior_to_import();				
		$this->log('truncate_all_prior_to_import',4);
		global $importing_now;
		return true;
	}
	
	function display_setting($name,$text) {
		?>
		<input type="hidden" name="settings[<?= $name ?>]" value="no" /><input type="checkbox" name="settings[<?= $name ?>]" value="yes" id="<?= $name ?>"<?php if ($this->Shopp->Settings->get($name) == "yes") echo ' checked="checked"'?> onchange="javascript:update_required();" /><label for="<?= $name ?>"> <?php _e($text,'Shopp'); ?></label><br />
		<?
	}
	
	
	function truncate_all_prior_to_import() {
		$this->log('truncate_all_prior_to_import');
		global $wpdb;
		if ($this->Shopp->Settings->get('catskin_importer_empty_first') == 'yes') {
			$query = "	TRUNCATE TABLE wp_shopp_tag;";
			$result = $wpdb->query($query);			
			$query = "	TRUNCATE TABLE wp_shopp_price;";
			$result = $wpdb->query($query);
			$query = "	TRUNCATE TABLE wp_shopp_product;";
			$result = $wpdb->query($query);
			$query = "	TRUNCATE TABLE wp_shopp_catalog;";
			$result = $wpdb->query($query);
			$query = "	TRUNCATE TABLE wp_shopp_edge_catalog;";
			$result = $wpdb->query($query);
			$query = "	TRUNCATE TABLE wp_shopp_asset;";
			$result = $wpdb->query($query);
			$query = "	DELETE FROM wp_shopp_meta WHERE type='spec' OR type='image';";
			$result = $wpdb->query($query);		
		}	
		
		$query = " CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}shopp_edge_category` (
		  `id` bigint(20) unsigned NOT NULL,
		  `parent` bigint(20) unsigned NOT NULL DEFAULT '0',
		  `name` varchar(255) NOT NULL DEFAULT '',
		  `slug` varchar(64) NOT NULL DEFAULT '',
		  `uri` varchar(255) NOT NULL DEFAULT '',
		  `description` text NOT NULL,
		  `spectemplate` enum('off','on') NOT NULL,
		  `facetedmenus` enum('off','on') NOT NULL,
		  `variations` enum('off','on') NOT NULL,
		  `pricerange` enum('disabled','auto','custom') NOT NULL,
		  `priceranges` text NOT NULL,
		  `specs` text NOT NULL,
		  `options` text NOT NULL,
		  `prices` text NOT NULL,
		  `priority` int(10) NOT NULL DEFAULT '0',
		  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  PRIMARY KEY (`id`),
		  KEY `parent` (`parent`)
		); ";
		$result = $wpdb->query($query);
		
		$query = " CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}shopp_edge_catalog` (
		  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `product` bigint(20) unsigned NOT NULL DEFAULT '0',
		  `parent` bigint(20) unsigned NOT NULL DEFAULT '0',
		  `type` enum('category','tag') NOT NULL,
		  `priority` int(10) NOT NULL DEFAULT '0',
		  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  PRIMARY KEY (`id`),
		  KEY `product` (`product`),
		  KEY `assignment` (`parent`,`type`)
		);";
		$result = $wpdb->query($query);
	
		$query = " CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}shopp_edge_category_map` (
		  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `category` bigint(20) unsigned NOT NULL DEFAULT '0',
		  `edge_category` bigint(20) unsigned NOT NULL DEFAULT '0',
		  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  PRIMARY KEY (`id`),
		  KEY `product` (`category`),
		  KEY `assignment` (`edge_category`)
		);";
		$result = $wpdb->query($query);	
			
		if ($this->Shopp->Settings->get('catskin_importer_clear_categories') == 'yes') {
			// $query = "	TRUNCATE TABLE wp_shopp_category;";
			// $result = $wpdb->query($query);
			$query = "	TRUNCATE TABLE wp_shopp_edge_category;";
			$result = $wpdb->query($query);

		}		
	}
	
	function clean_shopp_settings() {
		global $wpdb;
		$query = "	DELETE FROM `{$wpdb->prefix}shopp_setting` WHERE `name` LIKE 'catskin%';";
		$result = $wpdb->query($query);	
		
		if (!mysql_error()) exit(" Shopp settings cleaned"); else exit(mysql_error());
		return false;
	}	
	
	function truncate_prices_for_product($product_id) {
		global $wpdb;
		if ($this->Shopp->Settings->get('catskin_importer_clear_prices') == 'yes') {
			$query = " DELETE FROM wp_shopp_price WHERE product='{$product_id}'";
			$result = $wpdb->get_var($query);
		}
		return $result;
	}	
	
	function quote_smart($value,$key)
	{
	    // Quote if not a number or a numeric string
			if ((!is_numeric($value) || $key=='sku') && $key != 'id') {
	        $value = "'".mysql_real_escape_string($value)."'";
	    }
	    return $value;
	}	
		
	function ajax_import_csv($filename=null) {

		set_time_limit(1200);
		global $wpdb;
		if ($this->debug) {
			global $wpdb;
			$wpdb->show_errors();
		}
		$this->log(' ajax_import_csv',4);
		if (!$this->column_map) $this->map_columns_from_saved_options();
		// echo '<pre>'.print_r($this->column_map,1).'</pre>';
		$this->ajax_load_file($filename);
// $this->removed_products[]	
	
		// initialize importer table
		$query = "  DROP TABLE IF EXISTS {$wpdb->prefix}shopp_importer; ";
		$result = $wpdb->query($query);

		$query = "  CREATE TABLE {$wpdb->prefix}shopp_importer ( id INT NOT NULL AUTO_INCREMENT, PRIMARY KEY(id), product_id TEXT NULL, price_options TEXT NULL, price_optionkey TEXT NULL, price_label TEXT NULL, processing_status INT NULL DEFAULT 0 ";
			foreach ($this->column_map as $key=>$value)
			{ 
				$query .= ", spi_" . $key . " TEXT NULL";
			}
		$query .= " ) ";
		$result = $wpdb->query($query);
		
		// assemble query
		$query = " INSERT INTO {$wpdb->prefix}shopp_importer (";
		
		// assemble headers
		$column_index = 0;
		foreach ($this->column_map as $key=>$value) { 
			if ($column_index > 0) $query .= ", ";
			$query .= " spi_" . $key . " ";
			$column_index ++; 
		}
		$query .= ") VALUES ";
		
		$sql_beginning = $query;
		// assemble data
		for ($slice=0; $slice < count($this->examine_data); $slice+=30) {
			$query = '';
			foreach (array_slice($this->examine_data,$slice,30) as $row) {
				$values = '';
				foreach ($this->column_map as $key=>$value) {
					if (strpos($key,'image')!==false) {
						if ($this->Shopp->Settings->get('catskin_importer_remove_image_paths') == 'yes') { // removes paths.
							foreach (array('\\','/') as $char)
								if ($v=strrchr($row[$value],$char))
									$row[$value] = substr($v,1);
	 					}
						$row[$value] = str_replace("%", "~", rawurlencode($row[$value]));
						
					}
					$row[$value] = $this->quote_smart($row[$value],$key);
					$values .= ($values ? ', ' : '').$row[$value];				
				}
				$query .= ($query ? ', ' : '')."($values)";
			}
			if (!empty($query)) {
				$query = $sql_beginning.$query.'; ';
				$last_result = $result;
				$result = $wpdb->query($query);
				if (!($result > 0)) {
					$message = "Error importing CSV file";
					$this->log($message);
					if (!$this->auto_import) {
						echo "<h3>$message. Debug info below:</h3>";
						var_dump($query,$row[$value],$this->column_map);
						var_dump($result,array_slice($this->examine_data,$slice,1));
					}
					if (!($last_result>0)) die;
				}
			}
		}
		// $this->map_columns_from_saved_options();
		// $this->log(' map_columns_from_saved_options',4);
		// $ajax_result = json_encode($result);
		$message = "CSV imported to database table {$wpdb->prefix}shopp_importer at ".date("H:i:s");
		$this->log($message);
		if (!$this->auto_import) echo "<h2 style='border-bottom:1px dotted #333'>$message</h2>";
		$this->log(' end ajax_import_csv',4);
		
		if ($this->auto_import) return true;
		exit;
	}
	function ajax_import_products() {
		set_time_limit(24000);
		global $wpdb, $Shopp;
		
		if ($this->debug) $wpdb->show_errors();
		$this->log(' ajax_import_products',4);
		$model = new spi_model($this);
		$model->execute();
		$model->execute_mega_query();
		
		
		function extrapolate_result(&$val, $total=null) {
			$temp = count($val);
			if (@$total) $temp .= " of $total";
			if ($temp > 0) {
				foreach ($val as $sku)
					$temp .= "\n\t$sku";
			}
			return $val = $temp;
		}
		
		extrapolate_result($_SESSION['spi_products_filtered_img']);
		extrapolate_result($_SESSION['spi_products_filtered_cat']);
		extrapolate_result($_SESSION['spi_products_filtered_inv']);
		$products_imported = extrapolate_result($this->result['products'],$this->result['products_imported']);
		$products_removed = extrapolate_result($this->result['products_removed']);
		$products_updated = extrapolate_result($this->result['products_updated']);
		
		if (!isset($this->result['edge_categories'])) $this->result['edge_categories'] = 0;
		
		foreach ($this->result as &$r) $r = (int)$r;

		$result = <<<HTML
Products Imported to database: {$products_imported}
Products Updated: {$products_updated}

Products Filtered due to missing image: {$_SESSION['spi_products_filtered_img']}
Products Filtered due to disallowed category: {$_SESSION['spi_products_filtered_cat']}
Products Removed due to not in stock: {$products_removed}

HTML;

		if ($this->auto_import) $this->send_email_report($result);

		$result .= <<<HTML
Products Filtered due to not in stock: {$_SESSION['spi_products_filtered_inv']}

Prices Imported:  {$this->result['prices']}
Tags Imported: {$this->result['tags']}
Categories Imported: {$this->result['categories']}
EDGE Categories Imported: {$this->result['edge_categories']}
Catalog Items Created: {$this->result['catalogs']}
Spec Items Created: {$this->result['specs']}

HTML;
		
		$this->report_errors();
		
	
		unset($model);
		
		$result = explode("\n",$result);
		if (!$this->auto_import) echo implode("<br>",$result);
		else foreach ($result as $r) $this->log($r);

		
		if ($this->auto_import) return $result;
		
		$this->map_categories();
		
		exit();
	}
	
	function ajax_import_images() {
		if ($this->debug) {
			global $wpdb;
			$wpdb->show_errors();
		}
		$model = new spi_model($this);
		while ($model->any_images_exist() > 0) {
			$works=true;
			$result += $model->execute_images();
			$this->report_errors();
		}
		if (!$works) $this->log('Something wrong with image import');
		unset($model);
		if ($this->auto_import) return @$result;
		else echo @$result;
		exit();
	}	
	
	function ajax_next_image() {
		if ($this->debug) {
			global $wpdb;
			$wpdb->show_errors();
		}
		$model = new spi_model($this);
		$result = $model->execute_images();
		$this->report_errors();
		unset($model);
		if ($this->auto_import) return $result;
		else echo $result;
		exit();
	}			
	
	function get_next_product() {
		global $wpdb;
		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer ORDER BY id limit 1";
		$result = $wpdb->get_row($query,OBJECT);
		return $result;
	}
	
	function get_next_set($id) {
		global $wpdb;
		$id = trim($id);
		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$id}' ORDER BY id ";
		$result = $wpdb->get_results($query,OBJECT);
		return $result;
	}																						
	
	function ajax_upload_spi_csv_file() {	
		$csvs_path = realpath(dirname(dirname(dirname(__FILE__)))).'/edge/';
		if (file_exists($csvs_path.$_POST['file_name'])) {
			unlink($csvs_path.$_POST['file_name']);
		} 
		$file_name = $_POST['file_name'];
		$path_info = pathinfo($_FILES[$upload_name]['name']);
		if (is_uploaded_file($_FILES['csvupload']['tmp_name'])) {
			if (!file_exists($csvs_path)) mkdir($csvs_path);
			chmod($csvs_path,0755);
	   		$uploaded_file = file_get_contents($_FILES['csvupload']['tmp_name']);
	   		$handle = fopen($csvs_path.$file_name, "w");
	   		fwrite($handle, $uploaded_file );
	   		echo "File uploaded successfully: ".$csvs_path.$file_name;
	   		fclose($handle);
	   		
		} else {
			echo "The file didn't upload...";
		}	
					
		exit();
	}
	
	function get_examine_data($filename=false)
	{
		if (isset($this->examine_data) && $this->examine_data) return true;
		else {
			$spi_files = new spi_files($this);
			if (!$filename) $filename = $this->Shopp->Settings->get('catskin_importer_file');
			if (strlen($filename) != 0 && $filename != 'no-file-selected') {
				$has_headers = ($this->Shopp->Settings->get('catskin_importer_has_headers') == 'yes');
				$this->examine_data = $spi_files->load_examine_csv($filename,$has_headers);
				$this->log(' examine_data',4);
				return true;
			}
		}
	}
	
	function ajax_load_file($filename=null) {
		$this->log(' ajax_load_file start',4);
		
		if ($this->get_examine_data($filename)) {
			
			if (!$this->column_map) $this->map_columns_from_saved_options();
			
			$_SESSION['spi_products_filtered_img'] = array();
			$_SESSION['spi_products_filtered_cat'] = array();
			$_SESSION['spi_products_filtered_inv'] = array();
			$_SESSION['spi_products_removed'] = array();
			$_SESSION['spi_products_to_remove'] = array();
			
			foreach ($this->examine_data as $key=>$row) {
				switch ($row[ $this->column_map['edge_inventory_status'] ]) {
					case 'I':	// I    In-stock
					break;
					case 'X':	// X    Scrapped
					case '-':	// -    Deleted
					case 'L':	// L    Layaway
					case 'S':	// S    Sold
					case 'V':	// V    Returned to vendor
					case 'M':	// M    Missing
					case 'U':	// U    Consumed as part (assembled into item or used in repair job)
					default:
						if ($this->Shopp->Settings->get('catskin_importer_empty_first') == 'no') {
							$_SESSION['spi_products_to_remove'][$row[ $this->column_map['sku'] ]] = $row[ $this->column_map['name'] ]; // record lines to remove from DB
						} else {
							$_SESSION['spi_products_filtered_inv'][] = $csv_product_id; // or if starting from empty, log which were filtered out
						}
						unset($this->examine_data[$key]);
					break;
				}
				
			}
			
			// $this->log(' map_columns_from_saved_options',4);
			// $start_at = 1;
			// $rows = $this->Shopp->Settings->get('catskin_importer_row_count');
			// if (strlen($filename) > 0 && strlen($rows) > 0 && strlen($has_headers) > 0) { // ROB >>
				// $_SESSION['spi_product_importer_data'] =  $spi_files->load_csv($filename,$start_at,$rows,$has_headers);
				// unset($spi_files);
				// $this->log(' load_csv',4);
				
				// $spi_data = new spi_data($this);
				// $spi_data->map_product_ids(&$this->examine_data);
				// unset($spi_data);
				
			// } else {
			// 	unset($spi_files);
			// 	$error = "Could not load CSV";
			// }			
		}
		$this->log(' ajax_load_file end',4);
	
		// return $data;	
	}
	
	function map_columns_from_saved_options() {
		//update mappings
		$this->column_map = null;
		$this->variation_map = null;
		$this->tag_map = null;
		$this->category_map = null;
		$this->image_map = null;
		$tag_counter = 0;
		$category_counter = 0;
		$variation_counter = 0;
		$image_counter = 0;  
		$spec_counter = 0; 
		$saved_column_map = $this->Shopp->Settings->get('catskin_importer_column_map');
		for ($col_index = 0; $col_index < $this->Shopp->Settings->get('catskin_importer_column_count'); $col_index++) {
			if ($saved_column_map[$col_index]["type"] == 'tag') {
				$tag_counter++;
				$this->column_map[$col_index] = $saved_column_map[$col_index]["type"].$tag_counter;
				$this->tag_map[] = $saved_column_map[$col_index]["type"].$tag_counter;
			 }else if ($saved_column_map[$col_index]["type"] == 'spec') { 
				//echo $saved_column_map[$col_index]["type"].'<br/>';
				$spec_counter++;
				$this->column_map[$col_index] = $saved_column_map[$col_index]["type"].$spec_counter;
				$this->spec_map[] = $saved_column_map[$col_index]["type"].$spec_counter;   
			} else if ($saved_column_map[$col_index]["type"] == 'variation') {
				$variation_counter++;
				$this->column_map[$col_index] = $saved_column_map[$col_index]["label"];
				$this->variation_map[] = $saved_column_map[$col_index]["label"];
			} else if ($saved_column_map[$col_index]["type"] == 'category') {
				$category_counter++;
				$this->column_map[$col_index] = $saved_column_map[$col_index]["type"].$category_counter;
				$this->category_map[] = $saved_column_map[$col_index]["type"].$category_counter;	
			} else if ($saved_column_map[$col_index]["type"] == 'image') {
				$image_counter++;
				$this->column_map[$col_index] = $saved_column_map[$col_index]["type"].$image_counter;
				$this->image_map[] = $saved_column_map[$col_index]["type"].$image_counter;	
			} else if ($saved_column_map[$col_index]["type"] != '') {						
				$this->column_map[$col_index] = $saved_column_map[$col_index]["type"];
			}
		}

		$this->column_map = array_flip($this->column_map);
		// HACK to use sk as ID
		$this->column_map['id'] = $this->column_map['sku'];
	}	
	
	// ...lots of your regular code, then:
    public function __call($name, $args)
    {
        throw new Exception('Undefined method ' . $name . '() called');
    }

	public function log($message,$level=4)
	{
		$message = "$message  ".$this->report_memory();
		// if ($this->debug) echo "<p style='font-size:10px'>$message</p>";
		if ($this->auto_import) {
			$datetime = date('Y-m-d H:i:s');
			$write = "$datetime - $message\n";
			$fh = fopen( $_SERVER['DOCUMENT_ROOT']."/import.log", 'a') or die("can't open import.log");
			fwrite($fh, $write); fclose($fh);
		} else {
			// error_log($message,$level);
			$datetime = date('Y-m-d H:i:s');
			$write = "$datetime - $message\n";
			$fh = fopen( $_SERVER['DOCUMENT_ROOT']."/import_manual.log", 'a') or die("can't open import.log");
			fwrite($fh, $write); fclose($fh);

		}
		return $message;
		
	}
	
	function report_memory()
	{
		return "( "
		.	$this->convert_bytes(memory_get_usage())
		.	" / "
		.	$this->convert_bytes(memory_get_peak_usage())
		.	" )"
		;
	}
	
	function convert_bytes($size)
	 {
	    $unit=array('b','kb','mb','gb','tb','pb');
	    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	 }
	

}

class shoppImporterException extends Exception
{
	public function errorMessage()
	{
		//error message
		$errorMsg = $this->message;
		return $errorMsg;
	}
}

?>