<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

# Check if class exist
if (!class_exists('MRKV_SETUP')){
	/**
	 * Class for setup plugin
	 */
	class MRKV_SETUP
	{
		/**
		 * @var string Path to plugin
		 * */
		private $file_path;
		
		/**
		 * Constructore create object
		 * @var string Path to plugin directory
		 * */
		function __construct($file_path)
		{
			# Get main file directory
			$this->file_path = $file_path;

			# Register settings data
			add_action('admin_init', array($this, 'mrkv_vchasno_kasa_register_mysettings'));
			# Create settings page
			add_action('admin_menu', array($this, 'mrkv_vchasno_kasa_register_plugin_page'));

			# Add action to order details actions
			add_action('woocommerce_order_actions', array($this, 'mrkv_vchasno_kasa_wc_add_order_meta_box_action'));
			# Create bill process
			add_action('woocommerce_order_action_create_bill_vchasno_kasa_action', array($this, 'mrkv_vchasno_kasa_wc_process_order_meta_box_action'));

			# Check HPOS
			if(class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()){
				# Add order admin column
				add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'mrkv_vchasno_kasa_wc_new_order_column'), 20);
				# Add data to custom order list column
				add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content_hpos'), 20, 2);
			}
			else{
				# Add order admin column
				add_filter('manage_edit-shop_order_columns', array($this, 'mrkv_vchasno_kasa_wc_new_order_column'));
				# Add data to custom order list column
				add_action('manage_shop_order_posts_custom_column', array($this, 'mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content'));
			}

			# Add widget to dashboard
			add_action('wp_dashboard_setup', array($this, 'mrkv_vchasno_kasa_ppo_status_dashboard_widget'));

			# Ajax Functions
			add_action( 'wp_ajax_clearlog', array($this, 'clear_all_log') );
			add_action( 'wp_ajax_nopriv_clearlog', array($this, 'clear_all_log') );

			# Add monthly time in cron
			add_filter( 'cron_schedules', array($this, 'monthly_cron_job_recurrence') );

			# Add function in cron hook 	
			add_action('clear_all_log_plugin_event_hook', array($this,'clear_all_log'));

			# Add function autocreate order receipt
			add_action('woocommerce_order_status_changed', array($this,'mrkv_vchasno_kasa_auto_create_receipt'), 99, 3);

			# Add metabox to order edit
			add_action( 'add_meta_boxes', array($this, 'mrkv_vchasno_kasa_wc_add_metabox'));

			# Individual tax code 
			add_action('woocommerce_product_options_general_product_data', [$this, 'mrkv_vchasno_add_individual_taxcode_field']);
			add_action('woocommerce_admin_process_product_object', [$this, 'mrkv_vchasno_save_individual_taxcode_field']);

			# Add save metabox to order edit
			add_action( 'wp_ajax_submit_morkva_vchasno_kasa', array($this, 'mrkv_vchasno_kasa_wc_do_metabox_action') );
			add_action( 'wp_ajax_nopriv_submit_morkva_vchasno_kasa', array($this, 'mrkv_vchasno_kasa_wc_do_metabox_action') );

			# Cron for clean log every month
			if( ! wp_next_scheduled( 'clear_all_log_plugin_event_hook' ) ) {
				# Add event
				wp_schedule_event( time(), 'monthly', 'clear_all_log_plugin_event_hook');
			}
		}

		public function mrkv_vchasno_add_individual_taxcode_field() {

		    global $product_object;

		    if ( $product_object && ! $product_object->is_type('variation') ) {

		        echo '<div class="options_group">';

		        woocommerce_wp_text_input( array(
		            'id'          => 'mrkv_vchasno_ind_taxcode',
		            'label'       => __('Індивідуальний код податку', 'mrkv-vchasno-kasa'),
		            'type'        => 'number',
		            'desc_tip'    => true,
		            'description' => __('Внесіть індивідуальний код податку', 'mrkv-vchasno-kasa'),
		        ) );

		        echo '</div>';
		    }
		}

		public function mrkv_vchasno_save_individual_taxcode_field( $product ) {

		    if ( isset($_POST['mrkv_vchasno_ind_taxcode']) ) {
		        $product->update_meta_data(
		            'mrkv_vchasno_ind_taxcode',
		            sanitize_text_field($_POST['mrkv_vchasno_ind_taxcode'])
		        );
		    }
		}

		/**
		 * Register all settings data
		 * */
		public function mrkv_vchasno_kasa_register_mysettings() {
		    $options = array(
		        'mrkv_kasa_token'               => 'sanitize_text_field',
		        'mrkv_kasa_code_type_payment'   => array($this, 'sanitize_array_of_integers'),
		        'mrkv_kasa_code_type_payment_custom'   => array($this, 'sanitize_array_of_integers'),
		        'mrkv_kasa_tax_group'            => 'absint',
		        'mrkv_kasa_test_token'           => 'sanitize_text_field',
		        'mrkv_kasa_test_enabled'         => array($this, 'sanitize_checkbox'),
		        'mrkv_kasa_shift_status'         => 'absint',
		        'mrkv_kasa_cashier'              => 'sanitize_text_field',
		        'mrkv_kasa_receipt_creation'     => array($this, 'sanitize_array_of_checkboxes'),
		        'mrkv_kasa_payment_order_statuses' => array($this, 'sanitize_multidimensional_array_of_strings'),
		        'mrkv_kasa_phone'                => array($this, 'sanitize_checkbox'),
		        'mrkv_kasa_shipping_price'       => array($this, 'sanitize_checkbox'),
		        'mrkv_kasa_skip_zero_product'    => array($this, 'sanitize_checkbox'),
		        'mrkv_kasa_receipt_send_user'    => array($this, 'sanitize_checkbox'),
		        'mrkv_kasa_receipt_send_type'    => array($this, 'sanitize_array_of_strings'),
		    );

		    foreach ($options as $option_name => $sanitize_callback) {
		        register_setting(
		            'mrkv_kasa-settings-group',
		            $option_name,
		            array(
		                'sanitize_callback' => $sanitize_callback,
		            )
		        );
		    }
		}

		/**
		 * Sanitizes checkbox fields (returns 'on' or '').
		 */
		public function sanitize_checkbox($input) {
		    return ($input === 'on' || $input === 1 || $input === true) ? 'on' : '';
		}

		/**
		 * Sanitizes array of integers (e.g. for select multiple numeric).
		 */
		public function sanitize_array_of_integers($input) {
		    if (!is_array($input)) {
		        return array();
		    }
		    return array_map('absint', $input);
		}

		/**
		 * Sanitizes array of strings (for multiple selects or checkboxes).
		 */
		public function sanitize_array_of_strings($input) {
		    if (!is_array($input)) {
		        return array();
		    }
		    return array_map('sanitize_text_field', $input);
		}

		public function sanitize_multidimensional_array_of_strings($input) {
	    if (!is_array($input)) {
	        return array();
	    }
	    $output = [];

	    foreach ($input as $key => $value) {
	        if (is_array($value)) {
	            $output[$key] = array_map('sanitize_text_field', $value);
	        } else {
	            $output[$key] = sanitize_text_field($value);
	        }
	    }
	    return $output;
	}


		/**
		 * Sanitizes array of checkboxes which come as associative array with 'on' or missing keys.
		 */
		public function sanitize_array_of_checkboxes($input) {
		    if (!is_array($input)) {
		        return array();
		    }
		    $output = [];
		    foreach ($input as $key => $value) {
		        $output[$key] = ($value === 'on') ? 'on' : '';
		    }
		    return $output;
		}

		/**
		 * Register setting page
		 * */
		public function mrkv_vchasno_kasa_register_plugin_page(){
			# Add settings page
			$menu = add_menu_page(__('Налаштування пРРО Вчасно Каса', 'mrkv-vchasno-kasa'), __('Вчасно Каса', 'mrkv-vchasno-kasa'), 'manage_woocommerce', 'vchasno_kasa_settings', array($this, 'mrkv_vchasno_kasa_show_plugin_admin_page'), plugin_dir_url($this->file_path) . 'assets/imgs/logo-kasa-circle-purple.svg');

			# Add styles to setting page
			add_action( 'load-' . $menu, array($this, 'setting_admin_enqueue_scripts'), 100);
		}

		/**
		 * Add Styles to setting page
		 * */
		public function setting_admin_enqueue_scripts() {
		    $plugin_dir = plugin_dir_path( __FILE__ );

		    wp_enqueue_style(
		        'chosen-css',
		        plugin_dir_url( __FILE__ ) . '../assets/css/chosen.min.css',
		        array(),
		        filemtime( $plugin_dir . '../assets/css/chosen.min.css' )
		    );

		    wp_enqueue_style(
		        'setting-css',
		        plugin_dir_url( __FILE__ ) . '../assets/css/settings.css',
		        array(),
		        filemtime( $plugin_dir . '../assets/css/settings.css' )
		    );
		}

		/**
		 * Get template of settings page
		 * */
		public function mrkv_vchasno_kasa_show_plugin_admin_page(){
			# Include js file
			wp_enqueue_script(
			    'chosen-js',
			    plugin_dir_url(__FILE__) . '../assets/js/chosen.jquery.min.js',
			    array('jquery'),
			    '3.0',
			    true 
			);
			
			# Show Settings Page template
			include plugin_dir_path($this->file_path) . "templates/mrkv-setting-page-template.php"; 
		}

		/**
		 * Add action to woo order
		 * @param array All actions for order
		 * @return array All actions for order with new
		 * */
		public function mrkv_vchasno_kasa_wc_add_order_meta_box_action($actions){
			# Add action
			$actions['create_bill_vchasno_kasa_action'] = __('Створити чек Вчасно Каса', 'mrkv-vchasno-kasa');
			# Return all list
        	return $actions;
		}

		/**
		 * Create bill process function
		 * @param object Order data
		 * */
		public function mrkv_vchasno_kasa_wc_process_order_meta_box_action($order){
			# Include Create receipt class
			include plugin_dir_path($this->file_path) . "classes/mrkv-vchasno-kasa-receipt.php";

			# Set type creation
			$type_creation = 'handle';

			# Creator recaipt
			$creator = new MRKV_VCHASNO_KASA_RECEIPT($order, $type_creation);

			# Create Receipt
			$creator->create_receipt();
		}

		/**
		 * Add order admin column
		 * @param array All columns in order list table
		 * @param array All columns in order list table with new
		 * */
		public function mrkv_vchasno_kasa_wc_new_order_column($columns){
			# Add column
			$columns['receipt_vchasno_kasa_column'] = __('ID Чека Вчасно Каса', 'mrkv-vchasno-kasa');
			# Return list
        	return $columns;
		}

		/**
		 * Fill ID Receipt column
		 * @param array All column with content
		 * */
		public function mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content($column){
			# Get global current order data
	        global $the_order;

	        # If this our column
	        if ('receipt_vchasno_kasa_column' === $column) {
	        	# Get Recaipt ID
	            $receipt_url = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_url', true);
	            $receipt_id = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_id', true);

				if(!$receipt_url || !$receipt_id){
					$receipt_url = $the_order->get_meta('vchasno_kasa_receipt_url');
					$receipt_id  = $the_order->get_meta('vchasno_kasa_receipt_id');
				}

	            # Check if exist
	            if($receipt_url && $receipt_id){
	            	# Print link to Vchasno Kasa Receipt 
 	            	printf(
					    '<style>.vchasno-link:hover{opacity:.7;}</style><a class="vchasno-link" style="background: #EAB5F7 !important;background-color: #EAB5F7 !important;border-color: #cec2d1 !important;color: #010101 !important;font-weight: 600;font-size: 15px;padding: 7px 13px;border: 1px solid;border-radius: 5px;" href="%s" target="_blank">%s</a>',
					    esc_url( $receipt_url ),
					    esc_html( $receipt_id )
					);
	            }
	        }
	    }

	    /**
		 * Fill ID Receipt column
		 * @param array All column with content
		 * */
		public function mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content_hpos($column, $the_order){
	        # If this our column
	        if ('receipt_vchasno_kasa_column' === $column) {
	        	# Get Recaipt ID
	            $receipt_url = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_url', true);
	            $receipt_id = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_id', true);

				if(!$receipt_url || !$receipt_id){
					$receipt_url = $the_order->get_meta('vchasno_kasa_receipt_url');
					$receipt_id  = $the_order->get_meta('vchasno_kasa_receipt_id');
				}

	            # Check if exist
	            if($receipt_url && $receipt_id){
	            	# Print link to Vchasno Kasa Receipt 
 	            	printf(
					    '<style>.vchasno-link:hover{opacity:.7;}</style><a class="vchasno-link" style="background: #EAB5F7 !important;background-color: #EAB5F7 !important;border-color: #cec2d1 !important;color: #010101 !important;font-weight: 600;font-size: 15px;padding: 7px 13px;border: 1px solid;border-radius: 5px;" href="%s" target="_blank">%s</a>',
					    esc_url($receipt_url),
					    esc_html($receipt_id)
					);
	            }
	        }
	    }

	    /**
	     * Register plugin dashboard widget only for admin role
	     * */
	    public function mrkv_vchasno_kasa_ppo_status_dashboard_widget(){
	    	# Check user role
	    	if (current_user_can('activate_plugins')) {
	    		# Add widget
	            wp_add_dashboard_widget('status_widget_vchasno', __('Вчасно Каса', 'mrkv-vchasno-kasa') . '<img src="' .  plugin_dir_url(__FILE__) . '../assets/imgs/logo-kasa-circle-purple.svg' . '" />', array($this, 'mrkv_vchasno_kasa_status_widget_form'));
	        }
	    }

	    /**
	     * Plugin dashboard widget functionality
	     * */
	    public function mrkv_vchasno_kasa_status_widget_form(){
	    	# Show Widget Page template
			include plugin_dir_path($this->file_path) . "templates/mrkv-widget-template.php"; 
	    }

	    /**
	     * Claer all text in log file 
	     * */
	    public function clear_all_log()
	    {
	    	if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash($_POST['nonce'])), 'mrkv_clear_log_nonce')) {
		        wp_send_json_error(__('Invalid nonce.', 'mrkv-vchasno-kasa'), 403);
		        wp_die();
		    }

	    	if( current_user_can('editor') || current_user_can('administrator') ) 
	    	{
	    		# Include Create receipt class
				include plugin_dir_path($this->file_path) . "classes/mrkv-logger-kasa.php";

				# Get logger class
				$logger = new MRKV_LOGGER_KASA();

				# Clean log file
				$logger->clear_file_log();
	    	}

			# Close Ajax query
			wp_die();
	    }

	    /**
	     * Add monthly time
	     * @param array All schedules in wp site
	     * @return array All schedules with montly
	     * */
	    public function monthly_cron_job_recurrence($schedules){
	    	# Create monthly scedules
	    	$schedules['monthly'] = array(
				'display' => __( 'Once monthly', 'mrkv-vchasno-kasa' ),
				'interval' => 2635200,
			);

			# Return array with monthly schedules
			return $schedules;
	    }

	    /**
	     * Automatic receipt creation
	     *
	     * @param string $order_id Order ID
	     * @param string $old_status old order status
	     * @param string $new_status new order status
	     */
	    public function mrkv_vchasno_kasa_auto_create_receipt($order_id, $old_status, $new_status){
	    	# Include Create receipt class
			include plugin_dir_path($this->file_path) . "classes/mrkv-vchasno-kasa-receipt.php";

			# Get order data
			$order = wc_get_order( $order_id );

			# Set type creation
			$type_creation = 'auto';

			# Creator recaipt
			$creator = new MRKV_VCHASNO_KASA_RECEIPT($order, $type_creation);

			# Create Receipt
			$creator->create_receipt();
	    }

	    /**
	     * Add metabox
	     * 
	     * */
	    public function mrkv_vchasno_kasa_wc_add_metabox()
	    {	
	    	# Check hpos
	    	if(class_exists( CustomOrdersTableController::class )){
	            $screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
	            ? wc_get_page_screen_id( 'shop-order' )
	            : 'shop_order';
	        }
	        else{
	            $screen = 'shop_order';
	        }

	    	# Add metabox to admin page
	        add_meta_box( 'morkva_vchasno_kasa_metabox', __('Вчасно Каса','mrkv-vchasno-kasa'), array($this, 'mrkv_vchasno_kasa_wc_add_metabox_content'), $screen, 'side', 'core' );
	    }

	    /**
	     * Add content to metabox
	     * 
	     * */
	    public function mrkv_vchasno_kasa_wc_add_metabox_content($post)
	    {
	    	# Get order data
	        if ($post)
	        {
	        	# Create order id variable
	        	$order_id = $post->ID;
		        $receipt_url = get_post_meta($order_id, 'vchasno_kasa_receipt_url', true);
	            $receipt_id = get_post_meta($order_id, 'vchasno_kasa_receipt_id', true);
	            $order = wc_get_order( $order_id );

				if($order)
				{
					$receipt_url = $order->get_meta('vchasno_kasa_receipt_url');
					$receipt_id  = $order->get_meta('vchasno_kasa_receipt_id');	
				}

	            # Check receipt id
	            if($receipt_id)
	            {
	            	# Show receipt link
	            	printf(
	            	    'Чек: <a href="%s" target="_blank">%s</a>',
	            	    esc_url($receipt_url),
	            	    esc_html($receipt_id)
	            	);
	            }
	            else
	            {
	        	    $button_text = __( 'Створити чек', 'mrkv-vchasno-kasa' );

	            	echo '<div class="mrkv_vchasno_create_receipt">
	            		<div class="mrkv_vchasno_create_receipt_btn button button-primary">' . esc_html($button_text) . '</div>
	            		<svg style="display: none;" version="1.1" id="L9" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="30px" height="30px" x="0px" y="0px"
						  viewBox="0 0 100 100" enable-background="new 0 0 0 0" xml:space="preserve">
						    <path fill="#000" d="M73,50c0-12.7-10.3-23-23-23S27,37.3,27,50 M30.9,50c0-10.5,8.5-19.1,19.1-19.1S69.1,39.5,69.1,50">
						      <animateTransform 
						         attributeName="transform" 
						         attributeType="XML" 
						         type="rotate"
						         dur="1s" 
						         from="0 50 50"
						         to="360 50 50" 
						         repeatCount="indefinite" />
						  </path>
						</svg></div>';

					$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
					$order_id_int = intval( $order_id );
					$nonce = wp_create_nonce( 'mrkv_vchasno_kasa_nonce' );

					echo '<script>
					    jQuery(document).ready(function($) {
					        jQuery(".mrkv_vchasno_create_receipt_btn").click(function(){
					            jQuery.ajax({
					                url: "' . $ajax_url. '",
					                type: "POST",
					                data: {
					                    action: "submit_morkva_vchasno_kasa",
					                    order_id: "' . $order_id_int . '",
					                    nonce: "' . esc_js($nonce) . '"
					                },
					                beforeSend: function(xhr) {
					                    jQuery(".mrkv_vchasno_create_receipt svg").show();
					                },
					                success: function(data) {
					                    location.reload();
					                }
					            });
					        });
					    });
					</script>';
	            }
	        }
	    }

	    /**
	     * Create receipt by button vchasno kasa
	     * 
	     * @var Order ID
	     * */
	    public function mrkv_vchasno_kasa_wc_do_metabox_action()
	    {
	    	if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash($_POST['nonce'])), 'mrkv_vchasno_kasa_nonce')) {
		        wp_send_json_error(__('Invalid nonce.', 'mrkv-vchasno-kasa'), 403);
		        wp_die();
		    }

		    if( current_user_can('editor') || current_user_can('administrator') ) 
	    	{
	    		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

		    	# Check order id
		    	if($order_id){
		    		# Get order data
		    		$order = wc_get_order( $order_id );
		    		# Create order vchasno receipt
			        $this->mrkv_vchasno_kasa_wc_process_order_meta_box_action($order);
		    	}
	    	}
	    }
	}
}