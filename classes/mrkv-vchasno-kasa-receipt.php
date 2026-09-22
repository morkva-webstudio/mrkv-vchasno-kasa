<?php
# Include all classes
include plugin_dir_path(__DIR__) . "classes/mrkv-vchasno-kasa-api.php"; 
include plugin_dir_path(__DIR__) . "classes/mrkv-logger-kasa.php"; 
include plugin_dir_path(__DIR__) . "classes/mrkv-shift.php"; 

# Check if class exist
if (!class_exists('MRKV_VCHASNO_KASA_RECEIPT')){
	/**
	 * Class for create receipt
	 */
	class MRKV_VCHASNO_KASA_RECEIPT
	{
		/**
		 * @var string Token number
		 * */
		private $api_vchasno_kasa;

		/**
		 * @var string Shift status 
		 * */
		private $shift_status;

		/**
		 * @var string All data connect 
		 * */
		private $data_connect;

		/**
		 * @param string Current order data
		 * */
		private $order;

		/**
		 * @param string Type creation
		 * */
		private $type_creation;

		/**
		 * @var string Ua Shipping Support
		 * */
		private $mrkv_ua_shipping_key;

		/**
		 * @var string Url action
		 * */
		const ACTION_TYPE = 'fiscal/execute';

		/**
		 * @var string Url action notification
		 * */
		const ACTION_TYPE_CHECK = 'notifications/checks';

		/**
		 * @var integer Seconds after which an order lock is treated as abandoned
		 * */
		const LOCK_TTL = 120;

		/**
		 * Constructor for creator receipt
		 * @param Order data
		 * */
		function __construct($order, $type_creation){
			# Get current Shift data
			$shift = new MRKV_SHIFT();
			# Update status shift
			$shift->update_shift_status();
			# Get shift status
			$this->shift_status = get_option('mrkv_kasa_shift_status', '0');

			# Create data connect
			$connected = new MRKV_CONNECT_DATA();
			# Save data connect
			$this->data_connect = $connected->get_connect_data();

			# Save order data
			$this->order = $order;
			$this->mrkv_ua_shipping_key = $this->get_mrkv_ua_shipping_key();

			# Save type creation
			$this->type_creation = $type_creation;

			# Save data
			$this->api_vchasno_kasa = new MRKV_VCHASNO_KASA_API($connected->get_connect_data());
		}

		/**
		 * Check shift status
		 * @return boolean Shift status
		 * */
		private function check_shift_status(){
			# Return status 
			return ($this->shift_status == '0') ? 0 : 1;
		}

		/**
		 * Get Ua shipping key
		 * @return boolean Shift status
		 * */
		private function get_mrkv_ua_shipping_key()
		{
			$m_ua_active_plugins = get_option('m_ua_active_plugins');
			$key = '';

			if($this->order->get_payment_method() =='cod' && $m_ua_active_plugins && is_array($m_ua_active_plugins) && !empty($m_ua_active_plugins) && defined( 'MRKV_UA_SHIPPING_LIST' ))
			{
				$keys_shipping = array_keys(MRKV_UA_SHIPPING_LIST);
	    		$key = '';

	    		foreach($this->order->get_shipping_methods() as $shipping)
	            {
	            	foreach($keys_shipping as $key_ship)
					{
						if(str_contains($shipping->get_method_id(), $key_ship))
						{
							$key = $key_ship;
						}
						if(in_array($shipping->get_method_id(), MRKV_UA_SHIPPING_LIST[$key_ship]['old_slugs']))
						{
							$key = $key_ship;
						}
					}
	            }
	        }

	        if($key)
	        {
	        	return $key . '_code';
	        }

			# Return status 
			return '';
		}

		/**
		 * Check token exist
		 * @return boolean Result of exist token
		 * */
		private function check_token_exist(){
			# Return answer
			return ($this->data_connect['token'] == '') ? 0 : 1;
		}

		/**
		 * Check if receipt is already created 
		 * */
		private function check_receipt_exist(){
			$receipt_id  = $this->order->get_meta('vchasno_kasa_receipt_id');
			$receipt_url = $this->order->get_meta('vchasno_kasa_receipt_url');

			return ! empty($receipt_id) || ! empty($receipt_url);
		}

		/**
		 * Log that the receipt already exists (order note only for manual creation,
		 * otherwise every later status change would add a note)
		 * @param object Logger
		 * */
		private function report_receipt_exists($log){
			$message = __('Помилка при створені чека: Чек вже було створено для данного замовлення', 'mrkv-vchasno-kasa');

			$log->save_log($message);

			if($this->type_creation == 'handle'){
				$this->order->add_order_note($message, $is_customer_note = 0, $added_by_user = false);
			}
		}

		/**
		 * Take the per-order lock, so two requests can not create a receipt for one order at once
		 * @return boolean True if the lock is ours
		 * */
		private function acquire_lock(){
			$name = 'mrkv_kasa_lock_' . $this->order->get_id();

			# add_option() fails when the row already exists
			if(add_option($name, time(), '', 'no')){
				return true;
			}

			# Lock left by a request that died before releasing it
			$locked_at = (int) get_option($name);

			if($locked_at && (time() - $locked_at) > self::LOCK_TTL){
				delete_option($name);

				return add_option($name, time(), '', 'no');
			}

			return false;
		}

		/**
		 * Release the per-order lock
		 * */
		private function release_lock(){
			delete_option('mrkv_kasa_lock_' . $this->order->get_id());
		}

		/**
		 * Load the order again, the object we were given can be older than a receipt saved meanwhile
		 * */
		private function refresh_order(){
			$fresh = wc_get_order($this->order->get_id());

			if($fresh){
				$this->order = $fresh;
			}
		}

		/**
		 * Manual creation skips the auto rules, but not for orders that must never get a receipt
		 * @return boolean True if the order status forbids creating a receipt
		 * */
		private function is_status_blocked(){
			$blocked = apply_filters('mrkv_kasa_manual_blocked_statuses', array('cancelled', 'refunded', 'failed', 'trash', 'checkout-draft'));

			return in_array($this->order->get_status(), (array) $blocked, true);
		}

		/**
		 * Check order payment type on exist 
		 * @return boolean If has in list
		 * */
		private function check_payment_types(){
			# Get rules for create receipt
			$ppo_skip_receipt_creation = get_option('mrkv_kasa_receipt_creation');
			$mrkv_kasa_payment_order_statuses = get_option('mrkv_kasa_payment_order_statuses');

			# Get current order status
			$current_status = $this->order->get_status();
			
			if($this->mrkv_ua_shipping_key && isset($ppo_skip_receipt_creation[$this->mrkv_ua_shipping_key]) && is_array($mrkv_kasa_payment_order_statuses) && isset($mrkv_kasa_payment_order_statuses[$this->mrkv_ua_shipping_key]) && in_array($current_status, $mrkv_kasa_payment_order_statuses[$this->mrkv_ua_shipping_key]))
			{
				return false;
			}
			
			if(isset($ppo_skip_receipt_creation[ $this->order->get_payment_method() ]) && is_array($mrkv_kasa_payment_order_statuses) 
				&& isset($mrkv_kasa_payment_order_statuses[$this->order->get_payment_method()]) 
				&& in_array($current_status, $mrkv_kasa_payment_order_statuses[ $this->order->get_payment_method()])){
				# Continue create
				 return false;
			}

			# Stop create
			return true;
		}

		/**
		 * Return payment type for current method
		 * @return integer|null Current payment type, null if it is not configured for this method
		 * */
		private function get_payment_type(){
			/**
			 * Lets a site map a payment method missing from the settings (e.g. an old gateway id)
			 * @param integer|null Payment type from the settings
			 * @param object Order
			 * */
			return apply_filters('mrkv_kasa_payment_type', $this->resolve_payment_type(), $this->order);
		}

		/**
		 * Payment type from the plugin settings
		 * @return integer|null Payment type, null if the settings have none for this method
		 * */
		private function resolve_payment_type(){
			# Get settings payment 
			$ppo_payment_type = get_option('mrkv_kasa_code_type_payment');
			$ppo_payment_type_custom = get_option('mrkv_kasa_code_type_payment_custom');

			$id = $this->order->get_payment_method(); 

			if($this->mrkv_ua_shipping_key)
			{
				$id = $this->mrkv_ua_shipping_key;
			}

			if(isset($ppo_payment_type[$id]) && $ppo_payment_type[$id] == 1111 && isset($ppo_payment_type_custom[$id]) && $ppo_payment_type_custom[$id])
			{
				return $ppo_payment_type_custom[$id];
			}

			# Check settings exist
			if($this->mrkv_ua_shipping_key && isset($ppo_payment_type[ $this->mrkv_ua_shipping_key ])){
				# Return type of settings
				return $ppo_payment_type[ $this->mrkv_ua_shipping_key ];
			}

			# Check settings exist
			if(isset($ppo_payment_type[ $this->order->get_payment_method() ])){
				# Return type of settings
				return $ppo_payment_type[ $this->order->get_payment_method() ];
			}

			# Not configured, a silent cash type would fiscalize a card payment as cash
			return null;
		}

		/**
		 * Create receipt main function
		 * */
		public function create_receipt(){
			# Create log data object
			$log = new MRKV_LOGGER_KASA();

			# Check type creation
			if($this->type_creation != 'handle'){
				# Check order by rules
				if($this->check_payment_types()){
					# Stop create
					return;
				}
			}

			# Check if receipt is already created
			if($this->check_receipt_exist()){
				$this->report_receipt_exists($log);

				return;
			}

			# Another request is creating the receipt for this order right now
			if(!$this->acquire_lock()){
				$message = __('Чек для цього замовлення вже створюється. Зачекайте кілька секунд і оновіть сторінку', 'mrkv-vchasno-kasa');

				$log->save_log($message);

				if($this->type_creation == 'handle'){
					$this->order->add_order_note($message, $is_customer_note = 0, $added_by_user = false);
				}

				return;
			}

			try{
				# The receipt could be saved by the request that held the lock
				$this->refresh_order();

				if($this->check_receipt_exist()){
					$this->report_receipt_exists($log);

					return;
				}

				$this->create_receipt_locked($log);
			}
			finally{
				$this->release_lock();
			}
		}

		/**
		 * Create receipt, the caller holds the order lock
		 * @param object Logger
		 * */
		private function create_receipt_locked($log){
			# Manual creation skips the rules above, so check the order itself
			if($this->type_creation == 'handle' && $this->is_status_blocked()){
				$message = sprintf(
					/* translators: %s is the order status */
					__('Помилка при створені чека: Не можна створити чек для замовлення зі статусом "%s"', 'mrkv-vchasno-kasa'),
					$this->order->get_status()
				);

				$log->save_log($message);
				$this->order->add_order_note($message, $is_customer_note = 0, $added_by_user = false);

				return;
			}

			# Check payment type is configured
			$payment_type = $this->get_payment_type();

			if($payment_type === null){
				$message = sprintf(
					/* translators: %s is the payment method id */
					__('Помилка при створені чека: Для способу оплати "%s" не налаштовано тип оплати для ПРРО', 'mrkv-vchasno-kasa'),
					$this->order->get_payment_method()
				);

				$log->save_log($message);
				$this->order->add_order_note($message, $is_customer_note = 0, $added_by_user = false);

				return;
			}

			# Check shift status
			if(!$this->check_shift_status()){
				# Get current Shift data
				$shift = new MRKV_SHIFT();

				# Open shift
				$shift->open_shift();

				# Update status shift
				$shift->update_shift_status();
			}

			# Check token exist
			if(!$this->check_token_exist()){
				# Show Error
				$log->save_log(__('Помилка при створені чека: Порожній токен', 'mrkv-vchasno-kasa'));

				# Show error in order
				$this->order->add_order_note(__('Помилка при створені чека: Порожній токен', 'mrkv-vchasno-kasa'), $is_customer_note = 0, $added_by_user = false);

				# Stop create
				return;
			}

			# Create array with all params
			$params = array();
			# Get main order data
			$order_data = $this->order->get_data();
			# Get admin user
			$user = wp_get_current_user();
			# All items in order
			$goods_items = $this->order->get_items();

			# Get order email
			$email = isset($order_data['billing']['email']) ? $order_data['billing']['email'] : $user->user_email;
			# Get order phone
			$phone = isset($order_data['billing']['phone']) ? $order_data['billing']['phone'] : '';

			# Check if phone enabled
			if(get_option('mrkv_kasa_phone')){

				$pattern = "/^\+380\d{3}\d{2}\d{2}\d{2}$/";

				if(!preg_match($pattern, $phone)){
					$simple_phone_format = substr($phone, -9);
					$phone = '+380' . $simple_phone_format;
				}

				# Set user info in params
				$params['userinfo'] = array(
					'email' => $email,
					'phone' => $phone
				);
			}
			else{
				# Set user info in params
				$params['userinfo'] = array(
					'email' => $email
				);
			}

			# Array of all products
			$goods = array();
			# Total price order
        	# Total price order
        	$products_subtotal = 0;
			$products_total = 0;
			$total_price = 0;

        	# Loop all order items
        	foreach ($goods_items as $item) 
        	{
        		# Sum subtotals and totals for all items
			    $products_subtotal += $item->get_subtotal();
			    $products_total += $item->get_total();

        		# Get product price
        		$price = ($item->get_subtotal() / $item->get_quantity());
        		$discount_total = $item->get_subtotal() - $item->get_total();

        		if(($price == 0) && get_option('mrkv_kasa_skip_zero_product', 1)){
        			continue;
        		}

        		$global_tax = get_option('mrkv_kasa_tax_group', 1);
				$tax_group  = ( ! empty( $global_tax ) || $global_tax === '0' ) ? intval( $global_tax ) : 1;

				$product_obj = $item->get_product();
				if ( $product_obj && is_a( $product_obj, 'WC_Product' ) ) {
					$meta_taxcode = trim( (string) $product_obj->get_meta( 'mrkv_vchasno_ind_taxcode' ) );

					if ( $meta_taxcode !== '' && is_numeric( $meta_taxcode ) && strpos( $meta_taxcode, 'field_' ) === false ) {
						$tax_group = intval( $meta_taxcode );
					}
				}

        		# Save item
        		$goods[] = array(
        			'code' => "" . $item->get_id(),
        			'name' => $item->get_name(),
        			'cnt' => $item->get_quantity(),
        			'price' => 'bbb' . number_format($price, 2, '.', '') . 'bbb',
        			'disc' => 'bbb' . number_format($discount_total, 2, '.', '') . 'bbb',
        			'disc_type' => 0,
        			'taxgrp' => $tax_group
        		);

        		$total_price += $item->get_total();
	        }

	        $has_shipping_total_exclude = true;

	        # Check if order has delivery price
	        if(get_option('mrkv_kasa_shipping_price', 1) && $this->order->get_shipping_total()){
	        	# Save item
        		$goods[] = array(
        			'code' => "delivery",
        			'name' => __('Доставка', 'mrkv-vchasno-kasa'),
        			'cnt' => 1,
        			'price' => 'bbb' . number_format($this->order->get_shipping_total(), 2, '.', '') . 'bbb',
        			'disc' => 'bbb' . number_format(0.00, 2, '.', '') . 'bbb',
        			'taxgrp' => intval(get_option('mrkv_kasa_tax_group', 1))
        		);

        		$total_price += $this->order->get_shipping_total();

        		$has_shipping_total_exclude = false;
	        }

	        # Comment check
	        $comment = "";

	        $order_total = $this->order->get_total();
	        $discount_order_total = 0.00;

	        if($has_shipping_total_exclude && $this->order->get_shipping_total())
	        {
	        	$order_total = $this->order->get_total() - $this->order->get_shipping_total();
	        }

	        $payment_total = $order_total;

	        if($total_price != $order_total && $total_price > $order_total)
	        {
	        	$discount_order_total = $total_price - $order_total;
	        }

	        # Add source
	        $params['source'] = 'VCD-1880';

			# Set fiscal params
			$params['fiscal'] = array(
				'task' => 1,
				'receipt' => array(
					'sum' => 'bbb' . number_format($total_price, 2, '.', '') . 'bbb',
					'round' => 'bbb' . number_format((0.00), 2, '.', '') . 'bbb',
					'disc' => 'bbb' . number_format($discount_order_total, 2, '.', '') . 'bbb',
					'disc_type' => 0,
					'comment_up' => '',
					'comment_down' => '',
					'rows' => $goods,
					'pays' => array(
						array(
							'type' => intval($payment_type),
							'sum' => 'bbb' . number_format($payment_total, 2, '.', '') . 'bbb',
							'change' => 'bbb' . number_format((0.00), 2, '.', '') . 'bbb',
							'comment' => $comment,
							'currency' => 'ГРН'
						)
					)
				)
			);

			# Check Cashier data
	        $cashier = get_option('mrkv_kasa_cashier');
	        if(isset($cashier) && $cashier != ''){
	        	$params['fiscal']['cashier'] = '' . $cashier;
	        }

			# Create json
			$json = json_encode($params);

			# Remove quotes with bbb
			$json = str_replace('"bbb', '', $json);
			$json = str_replace('bbb"', '', $json);

			# Show Error
			$log->save_log('<pre>' . $json . '</pre>');

			# Create receipt (Send post query)
			$response = $this->api_vchasno_kasa->connect($params, self::ACTION_TYPE, $json);

			# Show Error
			$log->save_log('<pre>' . $response . '</pre>');

			# Decode json response to StdClass
			$result = json_decode($response);

			# Check if result has error
			if($result->errortxt == ''){
				# Save qr link
				$receipt_url = $result->info->doccode;
				$receipt_id = !empty($receipt_url_id) ? $receipt_url_id : $receipt_url;
				$receipt_full_url = !empty($full_url) ? $full_url : 'https://kasa.vchasno.ua/check-viewer/' . $receipt_url;

				$this->order->update_meta_data('vchasno_kasa_receipt_id', $receipt_id);
				$this->order->update_meta_data('vchasno_kasa_receipt_url', $receipt_full_url);
				$this->order->save();

				# Add message in log 
				$log->save_log(
				    sprintf(
				        /* translators: %d is the order ID */
				        __('Чек створено для замовлення %d', 'mrkv-vchasno-kasa'),
				        $this->order->get_id()
				    )
				);

				# Show in history
				$this->order->add_order_note(
				    sprintf(
				        /* translators: %s is the URL to view the receipt */
				        __('Чек створено <a href="%s" target="_blank">Відкрити</a>', 'mrkv-vchasno-kasa'),
				        esc_url('https://kasa.vchasno.ua/check-viewer/' . $receipt_url)
				    ),
				    $is_customer_note = 0,
				    $added_by_user = false
				);

				# Check if send receipt enabled
				if(get_option('mrkv_kasa_receipt_send_user')){
					# Get sender type list
					$sender_type_list = get_option('mrkv_kasa_receipt_send_user');

					# Check selected sender type
					if(isset($sender_type_list) && $sender_type_list && is_array($sender_type_list)){
						# Loop all channel
						foreach($sender_type_list as $sender_type){
							# Set receipnt
							$recipient = $phone;
							# Check sender type 
							if($sender_type == 'email'){
								$recipient = $email;
							}

							# Create sender params
							$params_sender = array(
								'recipient' => $recipient,
								'channel' => $sender_type,
								'check' => $receipt_url
							);

							# Create json
							$json_sender = json_encode($params_sender);

							# Show Error
							$log->save_log('<pre>' . $json_sender . '</pre>');

							# Create receipt (Send post query)
							$response_sender = $this->api_vchasno_kasa->send_receipt($params_sender, self::ACTION_TYPE_CHECK, $json_sender);

							# Show Error
							$log->save_log('<pre>' . $response_sender . '</pre>');
						}
					}
				}
			}
			else{
				# Show Error
				$log->save_log(__('Помилка при створенні чеку: ', 'mrkv-vchasno-kasa'));
				$log->save_log($result->errortxt);

				# Show in history
				$this->order->add_order_note(__('Помилка при створенні чеку. Докладніше у логах плагіну', 'mrkv-vchasno-kasa'), $is_customer_note = 0, $added_by_user = false);
			}
		}
	}
}