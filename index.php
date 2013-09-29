<?php
/**
	Plugin Name: Shopp Product Importer for EDGE
	Plugin URI: http://www.catskinstudio.com
	Version: 1.0
	Description: Shopp Product Importer Shopp/Wordpress Plugin provides a mechanisim to import Products from a specifically formatted CSV file into the shopp product database.
	Author: Lee Tagg (leet at catskinstudio dot com), fixed by Tim van den Eijnden (@timvdeijnden), EDGE support & more by Rob Record (@robrecord)
	Author URI: http://www.catskinstudio.com
	Copyright: Copyright © 2010 Catskin Studio
	Licence: GNU GENERAL PUBLIC LICENSE Version 3, 29 June 2007
**/

ini_set('memory_limit', 80*1024*1024);
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
	public $class_path;
	public $model_path;
	public $flow_path;
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


	public $result = array();

	function __construct() {
		/* The activation hook is executed when the plugin is activated. */
		register_activation_hook( __FILE__, array( $this, 'shopp_importer_activation' ) );
		/* The deactivation hook is executed when the plugin is deactivated */
		register_deactivation_hook( __FILE__, array( $this, 'shopp_importer_deactivation') );

		global $Shopp;
		if (class_exists('Shopp')) {
			$this->Shopp = &$Shopp;
			$this->set_paths();

			add_action('admin_menu', array(&$this, 'on_admin_menu'));
			add_action('wp_ajax_upload_spi_csv_file', array(&$this, 'ajax_upload_spi_csv_file'));
			add_action('wp_ajax_import_csv', array(&$this, 'ajax_import_csv'));
			add_action('wp_ajax_import_products', array(&$this, 'ajax_import_products'));
			add_action('wp_ajax_import_images', array(&$this, 'ajax_import_images'));
			add_action('wp_ajax_next_image', array(&$this, 'ajax_next_image'));

			add_action( 'init', array(&$this, 'register_edge_categories'));
			add_action('shopp_init', array(&$this, 'shopp_init'));
			add_action('shopp_auto_import', array(&$this, 'automatic_start'));

			add_filter('shopp_admin_menus', array(&$this, 'shopp_admin_menu'));

			if (array_key_exists("test_auto", $_GET))  {
				$this->auto_import = true;
				$this->log("Start user-initiated auto import test");
				$this->automatic_start();
			}
		}
	}

	function test_cron_import()
	{
		add_action('shopp_auto_import_dev', array(&$this, 'automatic_start_test_dev'));
		wp_schedule_single_event(time()+1,'shopp_auto_import_dev');
	}

	function shopp_importer_activation()
	{
		$timestamp = ( new DateTime( '04:30-0400' ) )->setTimezone( new DateTimeZone( 'UTC' ) )
				->getTimestamp();
		wp_schedule_event( $timestamp, 'daily', 'shopp_auto_import' );

		global $wpdb;

		$create_order_only_items_table = <<<SQL
			CREATE TABLE IF NOT EXISTS `wp_shopp_order_only_items` (
				`id` bigint(20) NOT NULL,
				`name` varchar(255) NOT NULL,
				`sku` varchar(100) NOT NULL,
				KEY `sku` (`sku`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;
SQL;
		$create_order_only_cats_table = <<<SQL
			CREATE TABLE IF NOT EXISTS `wp_shopp_order_only_cats` (
				`cat_id` int(11) NOT NULL,
				UNIQUE KEY `cat_id` (`cat_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;
SQL;
		$wpdb->query($create_order_only_items_table);
		$wpdb->query($create_order_only_cats_table);
	}

	function shopp_importer_deactivation()
	{
		/* This function is executed when the user deactivates the plugin */
		wp_clear_scheduled_hook('shopp_auto_import');
	}

	function automatic_start_test_dev()
	{
		wp_clear_scheduled_hook('shopp_auto_import_dev');
		$this->automatic_start_test();
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
			$this->log("# - Started import");
			if( $_GET['test_auto'] == 'import')
			{ // test import with import directory
				list ( $csvs, $latest_modified ) = $this->find_csvs( $this->csv_get_path );
			}
			else
			{ // test import with upload directory
				$csvs = $this->juggle_files();
			}
			if (!empty($csvs)) {
				natcasesort( $csvs );
			// if (list($csvs) = $this->find_csvs($this->csv_get_path)) {
				foreach ($csvs as $filename) {
					$this->log("> - Importing CSV $filename");
					$this->truncate_all_prior_to_import();
					if ($this->ajax_import_csv($filename))
					{
						if ($this->ajax_import_products())
						{
							$this->log("starting images");
							$this->auto_import_images();
						}
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
		echo "Map EDGE Categories - TODO";
		// global $MapCategories;
		// set_error_handler(array(&$this, 'spi_errors'));
		// $this->shopp_init();
		// $MapCategories = new MapCategories($this);
		// $result = $MapCategories->process_categories(true,false,$this);
		// $this->log($result);
		// if (!$this->auto_import) echo $result;
	}
	function find_csvs($dir)
	{
		$dir = $dir.'/';
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
							$this->log("Could not rename get_path to $newpath");
							// return false;
						}
						return false;
					}
					if (!(rename($this->csv_get_path,$newpath=substr($this->csv_archive_path.'/edge/', 0, -1)."_".date('Y-m-d_H-i-s'))))  {
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
				$this->log("Could not see any csv files in $this->csv_upload_path");
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

	function shopp_init()
	{
		// add_action('shopp_admin_menu', array(&$this, 'shopp_admin_menu'));

		foreach (array('MapCategories', 'OrderOnly') as $controller) {
			$target = "{$this->flow_path}/$controller.php";
			$link = SHOPP_FLOW_PATH."/$controller.php";
			if (!file_exists($link)) symlink( $target,$link );
			require_once($target);
		}

		$target = "{$this->path}/classes/ui/orderonly";
		$link = SHOPP_ADMIN_PATH."/orderonly";
		if (!file_exists($link)) symlink( $target,$link );
	}

	public function register_edge_categories()
	{
	}

	function set_paths() {
		if ( !defined('ABSPATH') )
			define('ABSPATH', dirname(__FILE__) . '/');

		$this->basepath = WP_PLUGIN_DIR;
		$this->path = WP_PLUGIN_DIR . '/' . array_pop(explode('/',dirname(__FILE__)));
		$this->thispluginpath = WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)).'/';

		$this->directory = basename($this->path);
		$this->class_path = "{$this->basepath}/{$this->directory}/classes";
		$this->model_path = "{$this->class_path}/model";
		$this->flow_path = "{$this->class_path}/flow";
		$this->csv_root = ABSPATH . $this->Shopp->Settings->get('catskin_importer_csv_directory');
		// $this->csv_upload_path = ABSPATH.'../wordpress2/edge3/';
		// $this->csv_get_path = WP_CONTENT_DIR.'/edge/';
		$this->csv_get_path = $this->csv_root.'/import';
		$this->csv_upload_path = $this->csv_root.'/upload';
		$this->csv_archive_path = $this->csv_root.'/archive';
		$this->html_get_path = $this->csv_get_path.'/product_htmls';
		$this->image_put_path = $this->csv_get_path;
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
			register_importer("shopp_product_importer","EDGE CSV to Shopp","Import <strong>Shopp products</strong> from an EDGE CSV file",array(&$this,'shopp_importer_settings_page'));
			//exit("The Importers: ".print_r($wp_importers,true));
		}

	}
	function shopp_admin_menu($ShoppAdmin)
	{
		$ShoppMenu = $ShoppAdmin->MainMenu; //this is our Shopp menu handle
		// $ShoppAdmin->caps['importer-main'] = defined('SHOPP_USERLEVEL')?SHOPP_USERLEVEL:'read';
		// $ShoppAdmin->addpage('importer-main',__('Manage Order Only','Shopp'),'OrderOnly','Manage Order Only');
		// array(&$this,'shopp_importer_settings_page')

		// $ShoppAdmin->caps['importer-catmap'] = defined('SHOPP_USERLEVEL')?SHOPP_USERLEVEL:'read';
		// !bookmark TODO addpage
		// $ShoppAdmin->addpage('importer-catmap',__('Category Map','Shopp'),'MapCategories','Map EDGE category IDs to Shopp categories');
		$ShoppAdmin->caps['importer-orderonly'] = defined('SHOPP_USERLEVEL')?SHOPP_USERLEVEL:'read';
		$ShoppAdmin->addpage('importer-orderonly',__('Manage Order Only','Shopp'),'OrderOnly','Manage Order Only');
	}

	function admin_menu()
	{
		$defaults = array(
			'page' => false,
			'id' => false
			);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		if ( !defined('WP_ADMIN') || !isset($page) )
				return false;
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
		// $this->log(' settings',4);

	}

	function start_import()
	{

		set_error_handler(array(&$this, 'spi_errors'));

		$this->log('*** START',4);
		// $this->log();
		// $this->log(' perform_import',4);

		check_admin_referer('shopp-importer');
		// set_time_limit(86400);
		// $this->map_columns_from_saved_options();
		// $this->log(' map_columns_from_saved_option',4);
		// $this->ajax_load_file();
		// $this->log(' ajax_load_file',4);
		$this->truncate_all_prior_to_import();
		global $importing_now;
		return true;
	}

	function display_setting($name, $text) {
		?>
		<tr class="setting">
			<td>
				<label for="<?= $name ?>"> <?php _e($text,'Shopp'); ?></label>
			</td>
			<td>
				<input type="hidden" name="settings[<?= $name ?>]" value="no" />
				<input type="checkbox" name="settings[<?= $name ?>]" value="yes" id="<?= $name ?>"<?php if ($this->Shopp->Settings->get($name) == "yes") echo ' checked="checked"'?> onchange="update_required()" />
			</td>
		</tr>
		<?
	}

	function truncate_all_prior_to_import() {

		global $wpdb, $Shopp;
		$GLOBALS['wp_rewrite'] = new WP_Rewrite();
		$Shopp->taxonomies();

		// check shopp_category taxonomy exists
		if( !taxonomy_exists( 'shopp_category' ) )
		{
			$this->log( "shopp_category taxanomy not registered!" );
			die( "shopp_category taxanomy not registered!" );
		}

		$this->result = array(
			'products_imported'=>array(),
			'products_removed'=>array(),
			'products_updated'=>array(),
			'edge_categories_added'=>array(),
			'added_to_order_only'=>array(),
			'remove_from_order_only'=>array()
		);

		$_SESSION['spi_products_filtered_cat'] =
		$_SESSION['spi_products_filtered_img'] =
		$_SESSION['spi_products_filtered_inv'] =
		$_SESSION['spi_products_to_remove'] =
		$_SESSION['spi_products_to_add_order_only'] = array();

		if ( $this->Shopp->Settings->get('catskin_importer_empty_first') == 'yes' || !$this->auto_import ) {
			global $wpdb;
			while( count( $shopp_product_posts = get_posts( array(
				'post_type'        => 'shopp_product',
				'post_status'      => 'any',
				'suppress_filters' => true,
				'posts_per_page'   => 100,
			) ) ) > 0 )
			{
				foreach( $shopp_product_posts as $product )
					wp_delete_post( $product->ID, true );
			}
			foreach( array(
					'wp_shopp_index',
					'wp_shopp_price',
					'wp_shopp_asset',
					'wp_shopp_summary',
				) as $table )
			{
				$wpdb->query( "TRUNCATE TABLE $table;" );
			}
			$wpdb->query( "DELETE FROM wp_shopp_meta WHERE type='spec' OR type='image' OR type='meta';" );

			if( $this->Shopp->Settings->get('catskin_importer_clear_categories') == 'yes')
			{
				$this->log("Clearing categories not implemented");
			}

		}
	}

	function clean_shopp_settings() {
		global $wpdb;
		$query = "	DELETE FROM `{$wpdb->prefix}shopp_setting` WHERE `name` LIKE 'catskin%';";
		$result = $wpdb->query($query);

		if (!mysql_error()) exit(" Shopp settings cleaned"); else exit(mysql_error());
		return false;
	}

	function quote_smart($value,$key)
	{
		// Quote if not a number or a numeric string
			if ( (!is_numeric($value) || $key=='sku') ) {
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

		// $this->log(' ajax_import_csv',4);
		if ( !$this->column_map ) $this->map_columns_from_saved_options();

		$this->ajax_load_file($filename);

		// initialize importer table
		$result = $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}shopp_importer;" );

		foreach ($this->column_map as $key=>$value)
		{
			$spi_query[] = "spi_{$key} TEXT NULL";
			$query_headers[] = "spi_$key";
		}
		$spi_query = implode( ", \n", $spi_query );
		$query = <<<SQL
CREATE TABLE {$wpdb->prefix}shopp_importer
(
id					INT NOT NULL AUTO_INCREMENT, PRIMARY KEY(id),
product_id			TEXT NULL,
price_options		TEXT NULL,
price_optionkey		TEXT NULL,
price_label			TEXT NULL,
processing_status	INT NULL DEFAULT 0,
{$spi_query}
);
SQL;
		$result = $wpdb->query($query);

		// assemble headers
		$query_header_sql = implode( ', ', $query_headers );

		// assemble query

		// assemble data
		$chunk_size = 30;
		for ( $slice = 0; $slice < count( $this->examine_data ); $slice += $chunk_size ) {
			$rows = array();
			foreach( array_slice( $this->examine_data, $slice, $chunk_size ) as $row ) {
				$values = array();
				foreach( $this->column_map as $field_name => $column_id ) {
					$value = $row[ $column_id ];
					if( strpos( $field_name, 'image' ) )
					{
						if( $this->Shopp->Settings->get( 'catskin_importer_remove_image_paths' ) == 'yes' )
							$value = $this->remove_image_paths( $value );
						$value = str_replace( "%", "~", rawurlencode( $value ) );
					}
					$value = $this->quote_smart( $value, $field_name );

					$values[] = $value;
				}
				$rows[] = '( ' . implode( ', ', $values ) . ' )';
			}
			if (!empty($rows)) {
				$query_values_sql = implode( ",\n\t", $rows );

				$query = <<<SQL
INSERT INTO {$wpdb->prefix}shopp_importer (
	{$query_header_sql}
) VALUES
	{$query_values_sql};
SQL;
				$last_result = $result;
				$result = $wpdb->query( $query );
				if ( !( $result > 0 ) ) {
					$message = "Error importing CSV file";
					$this->log( $message );
					if ( !$this->auto_import ) {
						echo "<h3>$message. Debug info below:</h3>";
						var_dump( $query, $row[$value], $this->column_map );
						var_dump( $result, array_slice( $this->examine_data, $slice, 1 ));
					}
					if ( !( $last_result > 0 ) ) die;
				}
			}
		}

		// $this->map_columns_from_saved_options();
		// $this->log(' map_columns_from_saved_options',4);
		// $ajax_result = json_encode($result);
		$message = "CSV imported to database table {$wpdb->prefix}shopp_importer at ".date("H:i:s");
		$this->log($message);
		if ($this->auto_import) return true;
		echo "<h2 style='border-bottom:1px dotted #333'>$message</h2>";
		exit;
	}

	function ajax_import_products() {
		set_time_limit(24000);
		global $wpdb, $Shopp;

		if ($this->debug) $wpdb->show_errors();
		// $this->log(' ajax_import_products',4);
		$model = new spi_model($this);
		$count_products = $model->execute();
		if( $count_products !== 0 )
			$model->execute_mega_query();

		$this->extrapolate_result($_SESSION['spi_products_filtered_img']);
		$this->extrapolate_result($_SESSION['spi_products_filtered_cat']);
		$this->extrapolate_result($_SESSION['spi_products_filtered_inv']);
		$this->extrapolate_result($this->result['products_imported']);
		$this->extrapolate_result($this->result['products_removed']);
		$this->extrapolate_result($this->result['products_updated']);
		$this->extrapolate_result($this->result['added_to_order_only']);
		$this->extrapolate_result($this->result['remove_from_order_only']);

		// $edge_categories_added = extrapolate_result( $this->result['edge_categories'] );

		// foreach ($this->result as &$r) $r = (int) $r;

		$result = <<<HTML
Products Imported to database: {$this->result['products_imported']}
Products Updated: {$this->result['products_updated']}

Products added to Order Only: {$this->result['added_to_order_only']}
Products removed from Order Only: {$this->result['remove_from_order_only']}

Products Filtered due to missing image: {$_SESSION['spi_products_filtered_img']}
Products Filtered due to disallowed category: {$_SESSION['spi_products_filtered_cat']}
Products Removed due to not in stock: {$this->result['products_removed']}

HTML;

		if ($this->auto_import) $this->send_email_report($result);

		$result .= <<<HTML
Products Filtered due to not in stock: {$_SESSION['spi_products_filtered_inv']}

HTML;
// EDGE Categories Imported: {$edge_categories_added}
// HTML;

		$this->report_errors();

		unset(
		    $_SESSION['spi_products_filtered_cat'],
			$_SESSION['spi_products_filtered_img'],
			$_SESSION['spi_products_filtered_inv'],
			$_SESSION['spi_products_to_remove'],
			$_SESSION['spi_products_to_add_order_only'],
			$model
		);

		$result = explode("\n",$result);
		if (!$this->auto_import) echo implode("<br>",$result);
		else foreach ($result as $r) $this->log($r);


		if ($this->auto_import) return $result;

		exit();
	}

	function extrapolate_result(&$values) {
		$temp = count($values);
		if ($temp > 0)
		{
			ksort($values);
			foreach ($values as $sku)
				$temp .= "\n\t$sku";
		}
		return $values = $temp;
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
		if (file_exists($csvs_path.'/'.$_POST['file_name'])) {
			unlink($csvs_path.'/'.$_POST['file_name']);
		}
		$file_name = $_POST['file_name'];
		$path_info = pathinfo($_FILES[$upload_name]['name']);
		if (is_uploaded_file($_FILES['csvupload']['tmp_name'])) {
			if (!file_exists($csvs_path)) mkdir($csvs_path);
			chmod($csvs_path,0755);
			$uploaded_file = file_get_contents($_FILES['csvupload']['tmp_name']);
			$handle = @fopen($csvs_path.'/'.$file_name, "w");
			fwrite($handle, $uploaded_file );
			echo "File uploaded successfully: ".$csvs_path.'/'.$file_name;
			fclose($handle);

		} else {
			echo "The file didn't upload...";
		}

		exit();
	}

	function get_examine_data($filename=false)
	{
		if (!$this->auto_import && isset($this->examine_data) && $this->examine_data) return true;
		else {
			$spi_files = new spi_files($this);
			if (!$filename) $filename = $this->Shopp->Settings->get('catskin_importer_file');
			if (strlen($filename) != 0 && $filename != 'no-file-selected') {
				$has_headers = ($this->Shopp->Settings->get('catskin_importer_has_headers') == 'yes');
				$this->examine_data = $spi_files->load_examine_csv($filename,$has_headers);
				// $this->log(' examine_data',4);
				return true;
			}
		}
	}

	function ajax_load_file($filename=null) {
		// $this->log(' ajax_load_file start',4);

		if ($this->get_examine_data($filename)) {

			if (!$this->column_map) $this->map_columns_from_saved_options();



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
		// $this->log(' ajax_load_file end',4);

		// return $data;
	}

	function map_columns_from_saved_options() {
		//update mappings
		$this->column_map = null;
		$this->variation_map = null;
		$this->tag_map = null;
		$this->category_map = null;
		$this->image_map = null;
		$saved_column_map = $this->Shopp->Settings->get('catskin_importer_column_map');
		for ( $col_index = 0; $col_index < count( $saved_column_map ); $col_index ++ )
		{
			$type = &$saved_column_map[$col_index]["type"];
			switch( $type )
			{
				case '':
					break;
				case 'tag':
					$this->tag_map[] = $this->column_map[$col_index] = $type.(count($this->tag_map)+1);
					break;
				case 'spec':
					//echo $type.'<br/>';
					$this->spec_map[] = $this->column_map[$col_index] = $type.(count($this->spec_map)+1);
					break;
				case 'variation':
					$this->variation_map[] = $this->column_map[$col_index] = $type.(count($this->variation_map)+1);
					break;
				case 'category':
					$this->category_map[] = $this->column_map[$col_index] = $type.(count($this->category_map)+1);
					break;
				case 'image':
					$this->image_map[] = $this->column_map[$col_index] = $type.(count($this->image_map)+1);
					break;
				default:
					$this->column_map[$col_index] = $type;
			}
		}
		$this->column_map = array_flip($this->column_map);
		// HACK to use sk as ID
		$this->column_map['id'] = $this->column_map['sku'];
	}

	function remove_image_paths( $value )
	{
		foreach( array( '\\', '/' ) as $char )
			if( $v = strrchr( $value, $char ) )
				$value = substr( $v, 1 );
		return $value;
	}

	// ...lots of your regular code, then:
	public function __call($name, $args)
	{
		throw new Exception('Undefined method ' . $name . '() called');
	}

	public function log($message,$level=4)
	{
		if( $this->Shopp->Settings->get( 'catskin_importer_debug_output' ) == 'yes' )
			echo "$message<br>\n";

			$log_name = ($this->auto_import) ? 'import.log' : 'import_manual.log';
			$log_path = "$this->csv_root/$log_name";
			if ( is_writable($log_path) ) {

			}

			$message = date('Y-m-d H:i:s')." - $message  ".$this->report_memory()."\n";
			error_log($message,3,$log_path);

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
		$unit = array( 'b', 'kb', 'mb', 'gb', 'tb', 'pb' );
		return @round(
				$size / pow(
					1024,
					( $i = floor(
							log( $size, 1024 )
						)
					)
				), 2
			) . ' ' . $unit[ $i ];
	}



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

		if ( isset($this->auto_import) && ! isset($this->auto_import_test) && ($this->Shopp->Settings->get('catskin_importer_send_email') == 'yes') ) {
			if (!function_exists('mail')) {
				$this->log("Message delivery not possible.");
				return false;
			}
			$subject = "***** Report for product upload to Seita Diamond Jewelers site";
			$body = 'An upload occurred at '.date('g.ia')." today.\n\nHere are the results:\n\n";
			if (isset($_SESSION['spi_errors']))
				foreach ($_SESSION['spi_errors'] as $key => $value)
					$body .= "$key : $value\n";
			$body .= "$result\n";
			$to = $headers = array();
			// $to[] = "Seita Uploads <uploads@seitajewelers.com>";
			$to[] = "Rob Record <robotix@gmail.com>";
			$headers[] = "From: Seita Jewelers Website <seita@seitajewelers.com>";
			$headers[] = "Reply-To: Seita Uploads <uploads@seitajewelers.com>";
			$headers[] = "Cc: Rob Record <rob@robrecord.com>";
			$headers[] = "X-Mailer: PHP/" . phpversion();
			if ( ! mail( implode( ', ', $to ), $subject, $body, implode( "\r\n", $headers ) ) ) {
				$this->log( "Message delivery to $to failed." );
				return false;
			}
			return true;
		}
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

class SPI_WP_Timezone
{
	public static $php_timezone;
	public static $gmt_offset;

	public static function set( $timezone )
	{
		// fix timezone
		// http://wordpress.org/support/topic/using-php-timezone

		// get current PHP timezone
		self::$php_timezone = date_default_timezone_get();

		if( ! isset( $timezone ) )
		{
			// get WordPress offset in hours
			self::$gmt_offset = get_option( 'gmt_offset' );
			// set the PHP timezone to match WordPress
			return date_default_timezone_set( 'Etc/GMT' . ( ( $gmt_offset < 0 ) ? '+' : '' ) . - $gmt_offset);
		}
		else
			return date_default_timezone_set( $timezone );
	}

	public static function reset()
	{
		// set the PHP timezone back the way it was
		return date_default_timezone_set( self::$php_timezone );
	}

	public static function to_timezone( $time, $timezone = 'UTC' )
	{
		$date = new DateTime( $time, $timezone );
		return $date;
	}
}
