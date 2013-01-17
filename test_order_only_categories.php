<?php
require_once 'PHPUnit/Autoload.php';
require_once 'spi_model.php';
define( 'BLOCK_LOAD', true );
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wordpress/wp-config.php' );
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wordpress/wp-includes/wp-db.php' );


class order_only_test extends PHPUnit_Extensions_Database_TestCase{
	
	function main(){
		$this::getConnection();
		$this::getDataSet();
	
	}

	protected function getConnection()
    {	
		global $wpdb;
		$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
		$wpdb->prefix = "wp_";	
    }

	function test_order_only_add(){
		global $wpdb;
		echo 'Test insert Order Only Item<br />';
		
		$count = $wpdb->get_results("SELECT count(id) as count FROM {$wpdb->prefix}shopp_order_only_items");
		echo 'Items in table before test: '.$count[0]->count.'<br />';
		$countotheritems = $wpdb->get_results("SELECT count(id) as count FROM {$wpdb->prefix}shopp_product");
		echo 'Count of all items before test (to ensure no other deletes): '.$countotheritems[0]->count.'<br />';
		
		$spimodel = new spi_model(null);
		$itemToAdd = array('001-100-00434' => "Lady's White 14 Karat Ring Engagement Ring With One 0.50Ct Round H/I Si2 Diamond And 16 dias 0.15Tw Round H Si1 Diamonds");
		
		$spimodel->remove_products($itemToAdd);
		
		$countafter = $wpdb->get_results("SELECT count(id) as count FROM {$wpdb->prefix}shopp_order_only_items");
		echo 'Items in table after test: '.$countafter[0]->count.'<br />';
		$countotheritems = $wpdb->get_results("SELECT count(id) as count FROM {$wpdb->prefix}shopp_product");
		echo 'Count of all items after test (to ensure no other deletes): '.$countotheritems[0]->count.'<br />';
		
		$this->assertEquals($countafter[0]->count, $count[0]->count+1);
		echo 'Success!<p />';
	}
	
	function test_order_only_remove(){
		global $wpdb;
		echo 'Test Remove Order Only Item (back in stock)<br />';
		
		$count = $wpdb->get_results("SELECT count(id) as count FROM {$wpdb->prefix}shopp_order_only_items");
		echo 'Items in table before test: '.$count[0]->count.'<br />';
		
		$spimodel = new spi_model(null);	
		$spimodel->delete_from_order_only('001-100-00434');
		
		$countafter = $wpdb->get_results("SELECT count(id) as count FROM {$wpdb->prefix}shopp_order_only_items");
		echo 'Items in table after test: '.$countafter[0]->count.'<br />';
		
		$this->assertEquals($countafter[0]->count, $count[0]->count-1);
		echo 'Success!<p />';
	}
	
	function getDataSet(){
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}shopp_order_only_items");
		echo 'Set up database table - cleaned<br />';
	}
	

}
$test = new order_only_test();
$test->main();
$test->test_order_only_add();
$test->test_order_only_remove();

?>
