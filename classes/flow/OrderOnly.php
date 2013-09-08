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


class OrderOnly extends AdminController {

	/**
	 * Categorize constructor
	 *
	 * @return void
	 * @author Rob Record
	 **/
	
	public $spi;
	
	function __construct ($spi) {
		parent::__construct();
		//wp_enqueue_script('postbox');
			//shopp_enqueue_script('colorbox');
			//do_action('shopp_customer_editor_scripts');
			//add_action('admin_head',array(&$this,'layout'));
		//do_action('shopp_customer_admin_scripts');
		//$this->spi = $spi;
		//global $Shopp;
		
		

	}
	
	function admin(){
		
		global $Shopp,$Items,$ProductImages,$wpdb,$message;

		$db = DB::get();
		$images = DatabaseObject::tablename(ProductImage::$table);
		$orderonlyitems = DatabaseObject::tablename("order_only_items");
		$pd = DatabaseObject::tablename(Product::$table);


		if (isset($_POST['selected']) && count($_POST['selected']) > 0){
			$selected = $_POST['selected'];
			$numOfDeleted = 0;
			foreach($selected as $delete){
				$Product = new Product($delete);
				$Product->delete();
				$numOfDeleted += 1;
				$query = "DELETE FROM {$orderonlyitems} WHERE id='{$deleted}'";
				$wpdb->query($query);
			}
			$message = $numOfDeleted .' Order Only Products Deleted';
		}

		$query = "SELECT orderonly.sku, orderonly.name, orderonly.id as itemid, images.value, images.id from {$orderonlyitems} as orderonly INNER JOIN (SELECT * FROM {$images} WHERE parent='45' AND context='product') as images ON orderonly.id=images.parent";
		$Items = $wpdb->get_results($query);
		
		$ProductImages = array();
		foreach ($Items as $i => $image) {
			$image->value = unserialize($image->value);
			$ProductImages[$i] = new ProductImage();
			$ProductImages[$i]->copydata($image,false,array());
			$ProductImages[$i]->expopulate();
		}

		include(SHOPP_ADMIN_PATH."/orderonly/orderonly.php");
	
	}
	
	function columns () {
		shopp_enqueue_script('calendar');
		register_column_headers('shopp_page_order-only-items', array(
			'cb'=>'<input type="checkbox" />',
			'sku'=>__('SKU','Shopp'),
			'desc'=>__('Description','Shopp'),
			'image'=>__('Image','Shopp')
		));

	}
}

?>