<?php
class WC_Search_Orders_By_Product_Admin {

	public function __construct() {
		//admin script and style
		add_action('admin_enqueue_scripts', array(&$this, 'sobp_enqueue_admin_script'));
		add_action( 'restrict_manage_posts', array(&$this,'sobp_display_products_search_dropdown_restrict'));
		add_filter( 'request', array(&$this,'sobp_filter_orders_request_by_product'));
		// Search orders Settings
		add_action('admin_init', array( $this,'sobp_search_settings_init'));
		add_action( 'admin_menu', array( $this, 'sobp_search_settings_menu' ), 20 );
		// Reorders woocommerce sub menus
		add_filter( 'menu_order', array( $this, 'sobp_menu_order' ) );
		add_filter( 'custom_menu_order', array( $this, 'sobp_custom_menu_order' ) );
	}

	/**
	 * Admin Scripts
	 */

	public function sobp_enqueue_admin_script() {
		global $WC_Search_Orders_By_Product;
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$WC_Search_Orders_By_Product->library->sobp_load_select2_lib();
		wp_register_script('search_orders_by_product_admin_js', $WC_Search_Orders_By_Product->plugin_url.'assets/admin/js/admin.js', array('jquery', 'select2_js'), $WC_Search_Orders_By_Product->version, true);
		wp_localize_script( 'search_orders_by_product_admin_js', 'wc_products_select_params', array(
			'i18n_no_matches'           => _x( 'No matches found', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_ajax_error'           => _x( 'Loading failed', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_short_1'    => _x( 'Please enter 1 or more characters', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_long_1'     => _x( 'Please delete 1 character', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_long_n'     => _x( 'Please delete %qty% characters', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_selection_too_long_1' => _x( 'You can only select 1 item', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_selection_too_long_n' => _x( 'You can only select %qty% items', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_load_more'            => _x( 'Loading more results&hellip;', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_searching'            => _x( 'Searching&hellip;', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'ajax_url'                  => admin_url( 'admin-ajax.php' ),
			'search_woo_products_nonce'     => wp_create_nonce( 'search-woo-products' ),
		) );

		if ( in_array( $screen_id, wc_get_screen_ids() ) ) {
			wp_enqueue_script( 'search_orders_by_product_admin_js' );
		}

	}

	public function sobp_display_products_search_dropdown_restrict() {
		global $typenow;

		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {
			$this->display_products_search_dropdown();
		}
	}

	public function display_products_search_dropdown() {
		global $WC_Search_Orders_By_Product;
		$product_name = '';
		$product_id = '';
		if ( ! empty( $_GET['product_id'] ) ) {
			$product_id = absint( $_GET['product_id'] );
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product_name = $product->get_title();
			}
		}
		?>
		<select class="woo-orders-search-by-product" style="width:203px;" id="product_id" name="product_id" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', $WC_Search_Orders_By_Product->text_domain ); ?>" data-action="search_woo_products">
		<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo htmlspecialchars( $product_name ); ?><option>
		</select>
		<?php
		// Product type filtering
		$terms   = get_terms( 'product_type' );
		$output  = '<select name="search_product_type" id="dropdown_product_type">';
		$output .= '<option value="">' . __( 'Search by product types', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
		if(!empty($terms)) {
					foreach ( $terms as $term ) {
				$output .= '<option value="' . sanitize_title( $term->name ) . '" ';

				if ( isset( $_GET['search_product_type'] ) ) {
					$output .= selected( $term->slug, $_GET['search_product_type'], false );
				}

				$output .= '>';

				switch ( $term->name ) {
					case 'grouped' :
						$output .= __( 'Grouped product', $WC_Search_Orders_By_Product->text_domain );
						break;
					case 'external' :
						$output .= __( 'External/Affiliate product', $WC_Search_Orders_By_Product->text_domain );
						break;
					case 'variable' :
						$output .= __( 'Variable product', $WC_Search_Orders_By_Product->text_domain );
						break;
					case 'simple' :
						$output .= __( 'Simple product', $WC_Search_Orders_By_Product->text_domain );
						break;
					default :
						// Assuming that we have other types in future
						$output .= ucfirst( $term->name );
						break;
				}

				$output .= '</option>';

				if ( 'simple' == $term->name ) {

					$output .= '<option value="downloadable" ';

					if ( isset( $_GET['search_product_type'] ) ) {
						$output .= selected( 'downloadable', $_GET['search_product_type'], false );
					}

					$output .= '> ' . ( is_rtl() ? '&larr;' : '&rarr;' ) . ' ' . __( 'Downloadable', $WC_Search_Orders_By_Product->text_domain ) . '</option>';

					$output .= '<option value="virtual" ';

					if ( isset( $_GET['search_product_type'] ) ) {
						$output .= selected( 'virtual', $_GET['search_product_type'], false );
					}

					$output .= '> ' . ( is_rtl() ? '&larr;' : '&rarr;' ) . ' ' . __( 'Virtual', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
				}
			}
		}

		echo $output .= '</select>';
	}

	public function sobp_filter_orders_request_by_product($vars) {
		global $typenow, $wp_query, $wpdb, $wp_post_statuses;
		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {
		// Search orders by product.
		if ( ! empty( $_GET['product_id'] ) ) {
		$order_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT order_id
			FROM {$wpdb->prefix}woocommerce_order_items
			WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
			AND order_item_type = 'line_item'
			", $_GET['product_id'] ) );

			$order_ids = ! empty( $order_ids ) ? $order_ids : array( 0 );			

			if (!empty($_GET['search_product_type'])) {
				if (WC_Product_Factory::get_product_type($_GET['product_id'])==$_GET['search_product_type']) {
					$vars['post__in'] = $order_ids;
				}else{
					$vars['post__in'] = array( 0 );
				}
			}else{
				$vars['post__in'] = $order_ids;
			}

			}
		if (!empty($_GET['search_product_type']) && empty($_GET['product_id'])) {
			// get all product ids in orders
			$product_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT meta_value
			FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = %s
			", '_product_id' ) );
			
			if(!empty($product_ids)) {
				$product_ids = array_unique($product_ids);
				$product_ids_filter_type = array();				
				foreach ($product_ids as $product_id) {
					if (WC_Product_Factory::get_product_type($product_id)==$_GET['search_product_type']) {
						$product_ids_filter_type[] = $product_id;
					}					
				}

				if(!empty($product_ids_filter_type)) {
					$orders_ids_arr = array();
					foreach ($product_ids_filter_type as $prod_id) {
						$order_ids_data = $wpdb->get_col( $wpdb->prepare( "
						SELECT order_id
						FROM {$wpdb->prefix}woocommerce_order_items
						WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
						AND order_item_type = 'line_item'
						", $prod_id ) );
						if(!empty($order_ids_data)) {
							$orders_ids_arr[] = array_unique($order_ids_data);
						}
					}
					if(!empty($orders_ids_arr)) {
						$final_order_ids = array();
						foreach ($orders_ids_arr as $ord_arr) {
							foreach ($ord_arr as $ord_id) {
								$final_order_ids[] = $ord_id;
							}
						}
						if(!empty($final_order_ids)) {
							$final_order_ids = array_unique($final_order_ids);
							$vars['post__in'] = $final_order_ids;
						}
					}
				}else{
					$vars['post__in'] = array( 0 );
				}
			}
		}
		}
		return $vars;
	}
	
	function sobp_search_settings_init() {
	    register_setting( 'sobp_search_options', 'sobp_settings', array($this, 'sobp_search_options_validate') );
	}
	
	function sobp_search_settings_menu() {
	    global $WC_Search_Orders_By_Product;
	    if ( current_user_can( 'manage_woocommerce' ) ) {
			add_submenu_page( 'woocommerce', __( 'WC Search Orders By Product Settings', $WC_Search_Orders_By_Product->text_domain ),  __( 'WC Search Orders By Product Settings', $WC_Search_Orders_By_Product->text_domain ) , 'manage_woocommerce', 'wc-search-orders-by-product-settings', array( $this, 'sobp_search_settings_page' ) );
		}
	}
	
	function sobp_search_settings_page() {?>
        <div class="wrap">
            <h1>WC Search Orders By Product Settings</h1>
            <?php settings_errors(); ?>
            <form action="options.php" method="post">
                <?php settings_fields('sobp_search_options'); ?>
                <?php $options = get_option('sobp_settings'); ?>
                <table class="form-table">
                    <tr>
                        <td class="td-full">
                            <label for="search_orders_by_product_type">
                                <input name="sobp_settings[search_orders_by_product_type]" type="checkbox" id="search_orders_by_product_type" value="1"<?php checked('1', $options['search_orders_by_product_type']); ?> />
                                <?php _e('Search Orders By Product Types'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td class="td-full">
                            <label for="search_orders_by_product_category">
                                <input name="sobp_settings[search_orders_by_product_category]" type="checkbox" id="search_orders_by_product_category" value="1"<?php checked('1', $options['search_orders_by_product_category']); ?> />
                                <?php _e('Search Orders By Product Categories'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
	<?php }
	
    // Sanitize and validate input. Accepts an array, return a sanitized array.
    function sobp_search_options_validate($input) {
        $input['search_orders_by_product_type'] = ( $input['search_orders_by_product_type'] == 1 ? 1 : 0 );
        $input['search_orders_by_product_category'] = ( $input['search_orders_by_product_category'] == 1 ? 1 : 0 );
        return $input;
    }
	
	/**
	 * Reorder the woocommerce menu items in admin.
	 *
	 * @param mixed $menu_order
	 * @return array
	 */
	public function sobp_menu_order($menu_order) {
	    global $submenu;
        $settings = $submenu['woocommerce'];
            foreach ( $settings as $key => $details ) {
                if ( $details[2] == 'wc-search-orders-by-product-settings' ) {
                    $index = $key;
                    $store_index_data = $details;
                }
        }
        if(!empty($index) && !empty($store_index_data)) {
            $submenu['woocommerce'][] = $store_index_data;
            unset( $submenu['woocommerce'][$index] );
            # Reorder the menu based on the keys in ascending order
            ksort( $submenu['woocommerce'] );
        }
	    return $menu_order;
	}
	
	/**
	 * Custom menu order.
	 *
	 * @return bool
	 */
	public function sobp_custom_menu_order() {
		return current_user_can( 'manage_woocommerce' );
	}
}