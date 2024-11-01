<?php
/*
Plugin Name: WPSC Inventory Manager
Author URI: http://joachim-uhl.de
Plugin URI: http://www.joachim-uhl.de/projekte/wordpress-e-commerce-add-on-inventory-manager/
Description: Plugin for <a href="http://www.instinct.co.nz">Wordpress Shopping Cart</a> to manage the product stock. We call it Inventory-Manager.
Version: 0.6
Author: Joachim Uhl based on the Code for the Stock Counter Plugin from Kolja Schleich

Copyright 2010  Joachim Uhl (email : joachim@joachim-uhl.de)
Copyright on Stock Counter Plugin: Kolja Schleich (email : kolja.schleich@googlemail.com; http://wordpress.org/extend/plugins/wpsc-stock-counter/)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WPSC_InventoryManager
{
	/**
	 * all products with options
	 *
	 * @var array
	 */
	private $products = array();
		
		
	/**
	 * class constructor
	 *
	 * @param none
	 * @return void
	 */ 
	public function __construct()
	{
		$this->initialize();
	}
	
	
	/**
	 * initialize plugin: define constants, register hooks and actions
	 * 
	 * @param none
	 * @return void
	 */
	private function initialize()
	{
		if ( !defined( 'WP_CONTENT_URL' ) )
			define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
		if ( !defined( 'WP_PLUGIN_URL' ) )
			define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
                

		
		register_activation_hook(__FILE__, array(&$this, 'activate') );
		load_plugin_textdomain( 'wpsc-inventory-manager', false, basename(__FILE__, '.php').'/languages' );
		add_action( 'admin_menu', array(&$this, 'addAdminMenu') );

		// Uninstallation for WP 2.7
		if ( function_exists('register_uninstall_hook') )
		register_uninstall_hook(__FILE__, array(&$this, 'uninstall'));
			
		$this->plugin_url = WP_PLUGIN_URL.'/'.basename(__FILE__, '.php');
		$this->getProducts();
	}
	

	/**
	 * gets products list from database 
	 *
	 * @param none
	 * @return boolean
	 */
	private function getProducts()
	{
		global $wpdb;

		$products = $wpdb->get_results( "SELECT a.id AS id, a.name AS name, a.quantity AS quantity, a.price AS price, c.name as category FROM {$wpdb->prefix}wpsc_product_list AS a, {$wpdb->prefix}wpsc_item_category_assoc AS b, {$wpdb->prefix}wpsc_product_categories AS c where a.active = 1 and a.quantity_limited = 1 and a.id = b.product_id and b.category_id = c.id ORDER BY id DESC" );
		if ( $products ) {
			foreach ( $products AS $product ) {
				$this->products[$product->id]['name'] = $product->name;
				$this->products[$product->id]['stock_quantity'] = $product->quantity;
                                $this->products[$product->id]['price'] = $product->price;
                                $this->products[$product->id]['category'] = $product->category;
				$this->getProductMeta( $product->id );
			}
			return true;
		}

		return false;
	}


	/**
	 * gets number of sold objects for given product
	 *
	 * @param int $pid ID of product
	 * @return int
	 */
	private function getSoldProducts( $pid )
	{
		global $wpdb;
		$sold = 0;

		$tickets = $wpdb->get_results( "SELECT `quantity` FROM {$wpdb->prefix}wpsc_cart_contents WHERE `prodid` = '".$pid."'" );
                if ( $tickets ) {
			foreach ( $tickets AS $ticket )
				$sold += $ticket->quantity;
		}
		return $sold;
	}

		
	/**
	 * gets product data for given product
	 *
	 * @param int $pid ID of product
	 * @return void
	 */
	private function getProductMeta( $pid )
	{
		$options = get_option( 'wpsc-inventory-manager' );
		
		$this->products[$pid]['limit'] = $this->products[$pid]['stock_quantity']; /*$options['products'][$pid]['limit'];*/
		$this->products[$pid]['count'] = 1; /*All products are active and have to be displayed. Old code: $options['products'][$pid]['count']*/
		$this->products[$pid]['linked_products'] = $options['products'][$pid]['linked_products'];

		if ( 1 == $this->products[$pid]['count'] ) {
			$sold = $this->getSoldProducts( $pid );
			$this->products[$pid]['sold'] = $sold;
			$this->products[$pid]['remaining'] = $this->products[$pid]['limit'] - $sold;
		}
	}


	/**
	 * prints admin page
	 *
	 * @param none
	 * @return void
	 */
	public function printAdminPage()
	{		
            global $wpdb;
            $url_array = $wpdb->get_results( "SELECT `option_value` FROM {$wpdb->prefix}options WHERE `option_value` = \"siteurl\" ");
            $url = $url_array[0];

          print '<div class="wrap">';
          print '<h2>Inventory Summary - Out of Stock</h2>';
          print '<table id="outofstockgrid" class="widefat" style="margin-top: 1em;">';
          print  '<thead>
				<tr>
                                        <th scope="col">ID</th>
					<th scope="col">Product</th>
					<th scope="col">Original Stock</th>
					<th scope="col">Sold</th>
					<th scope="col">Available</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Category</th>
				</tr>
		</thead>';
          print '<tbody id="the-list">';
          foreach ( $this->products AS $pid => $data ) {
              
              if ($this->products[$pid]['remaining']<1) {
                  $class = ( 'alternate' == $class ) ? '' : 'alternate';
                  print '<tr class=';
                  print $class;
                  print '>';

                  print '<td>';
                  print $pid;
                  print '</td>';

                  print '<td>';
                  print "<a href=\"".$url."/wp-admin/admin.php?page=wpsc-edit-products&product_id=".$pid."\">";
                  print "<b style=\"color: red\">".$data['name']."</b>";
                  print "</a>";
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['stock_quantity'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['sold'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['remaining'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['price'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['category'];
                  print '</td>';

                  print '</tr>';
              }
          }
          print '</tbody>';
	  print '</table>';
          
          print '<script language="javascript" type="text/javascript"> ';
          print 'var table01_Props = { };'; 
          print 'var tf01 = setFilterGrid(\'outofstockgrid\',table01_Props);';  
          print '</script>';   

          print '<h2>Inventory Summary - In Stock</h2>';
          print '<table id="instockgrid" class="widefat" style="margin-top: 1em;">';
          print  '<thead>
				<tr>
                                        <th scope="col">ID</th>
					<th scope="col">Product</th>
					<th scope="col">Original Stock</th>
					<th scope="col">Sold</th>
					<th scope="col">Available</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Category</th>
				</tr>
		</thead>';
      
          print '<tbody id="the-list">';
          foreach ( $this->products AS $pid => $data ) {
              
              if ($this->products[$pid]['remaining']>0) {
                  $class = ( 'alternate' == $class ) ? '' : 'alternate';


                  print '<tr class=';
                  print $class;
                  print '>';

                  print '<td>';
                  print $pid;
                  print '</td>';

                  print '<td>';
                  if ($this->products[$pid]['remaining']>= 1) {
                      print "<a href=\"".$url."/wp-admin/admin.php?page=wpsc-edit-products&product_id=".$pid."\">";
                      print $data['name'];} else {print "<b style=\"color: red\">".$data['name']."</b>";}
                      print "</a>";
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['stock_quantity'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['sold'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['remaining'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['price'];
                  print '</td>';

                  print '<td>';
                  print $this->products[$pid]['category'];
                  print '</td>';

                  print '</tr>';
              }
          }
          print '</tbody>';
	  print '</table>';
          
          
          print '<script language="javascript" type="text/javascript"> ';
          print 'var table02_Props = { };'; 
          print 'var tf02 = setFilterGrid(\'instockgrid\',table02_Props);';  
          print '</script>';   
          print '</div>';
	}

		
	/**
	 * Activate Plugin
	 *
	 * @param none
	 * @return void
	 */
	public function activate()
	{
		$options = array();
		add_option( 'wpsc-inventory-manager', $options, 'DTL Ticketing Options', 'yes' );

		/*
		* Add Capability to export DTA Files and change DTA Settings
		*/
		$role = get_role('administrator');
		$role->add_cap('view_inventory_manager');
		$role->add_cap('edit_inventory_manager_settings');
		
		$role = get_role('editor');
		$role->add_cap('view_inventory_manager');
	}
	
	
	/**
	 * adds code to Wordpress head
	 *
	 * @param none
	 * @return void
	 */
	public function addHeaderCode()
	{
		wp_print_scripts( 'jquery' );
                wp_enqueue_script('tablefilter', plugins_url('/tablefilter/tablefilter.js', __FILE__, false));
	}
	
	
	/**
	 * adds admin menu
	 *
	 * @param none
	 * @return void
	 */
	public function addAdminMenu()
	{
		$plugin = basename(__FILE__,'.php').'/'.basename(__FILE__);
//		$menu_title = "<img src='".$this->plugin_url."/icon.png' alt='' /> ".;
		$menu_title = __( 'Inventory Manager', 'wpsc-inventory-manager' );

	 	$mypage = add_submenu_page( 'wpsc-sales-logs', __( 'Inventory Manager', 'wpsc-inventory-manager' ), $menu_title, 'view_inventory_manager', basename(__FILE__), array(&$this, 'printAdminPage') );
		add_action( "admin_print_scripts-$mypage", array(&$this, 'addHeaderCode') );
		add_filter( 'plugin_action_links_' . $plugin, array( &$this, 'pluginActions' ) );
	}
	 
	 
	/**
	 * display link to settings page in plugin table
	 *
	 * @param array $links array of action links
	 * @return new array of plugin actions
	 */
	public function pluginActions( $links )
	{
		$settings_link = '<a href="admin.php?page='.basename(__FILE__).'">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link );
	
		return $links;
	}
	
	
	/**
	 * Uninstall Plugin
	 *
	 * @param none
	 * @return void
	 */
	public function uninstall()
	{
	 	delete_option( 'wpsc-inventory-manager' );
	}
}

// Run the plugin
$wpsc_inventory_manager = new WPSC_InventoryManager();
?>
