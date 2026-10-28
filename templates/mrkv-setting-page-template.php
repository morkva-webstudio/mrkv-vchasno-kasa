<?php
# Check empty payment
$all_payment_for_test = array_filter(WC()->payment_gateways->payment_gateways(), function ($gateway) {
	                                return 'yes' === $gateway->enabled;
	                            });
$all_payment_order_statuses = get_option('mrkv_kasa_payment_order_statuses');
$error_message = '';
foreach($all_payment_for_test as $id => $gateway){
	if($all_payment_order_statuses && !array_key_exists($id, $all_payment_order_statuses)){
		$ppo_skip_receipt_creation = get_option('mrkv_kasa_receipt_creation');
		if($ppo_skip_receipt_creation && array_key_exists($id, $ppo_skip_receipt_creation)){
			unset($ppo_skip_receipt_creation[$id]);
			$result = update_option('mrkv_kasa_receipt_creation', $ppo_skip_receipt_creation);
			$error_message = __('Помилка активації способу доставки. Не обрано жодгого статусу замовлення', 'mrkv-vchasno-kasa');
		}
	}
}

# List of all payment types
$payment_types = array(0 => __('Готівка', 'mrkv-vchasno-kasa'), 
					   1 => __('Безготівка', 'mrkv-vchasno-kasa'), 
					   2 => __('Картка', 'mrkv-vchasno-kasa'),
					   3 => __('Передплата', 'mrkv-vchasno-kasa'),
					   4 => __('Післяоплата', 'mrkv-vchasno-kasa'),
					   5 => __('Кредит', 'mrkv-vchasno-kasa'),
					   6 => __('Сертифікат', 'mrkv-vchasno-kasa'),
					   8 => __('Чек', 'mrkv-vchasno-kasa'),
					   11 => __('Бонусні бали', 'mrkv-vchasno-kasa'),
					   12 => __('Погашення кредиту', 'mrkv-vchasno-kasa'),
					   13 => __('Переказ через QR-код', 'mrkv-vchasno-kasa'),
					   14 => __('Переказ з картки', 'mrkv-vchasno-kasa'),
					   15 => __('Переказ з поточного рахунку', 'mrkv-vchasno-kasa'),
					   16 => __('Інтернет еквайринг', 'mrkv-vchasno-kasa'),
					   17 => __('Платіж LiqPay', 'mrkv-vchasno-kasa'),
					   18 => __('Платіж RozetkaPay', 'mrkv-vchasno-kasa'),
					   19 => __('Платіж Portmone', 'mrkv-vchasno-kasa'),
					   20 => __('Платіж NovaPay', 'mrkv-vchasno-kasa'),
					   1111 => __('Власний метод оплати', 'mrkv-vchasno-kasa'));
# List tax groups
$tax_groupes = array(__('ПДВ 20% (А)', 'mrkv-vchasno-kasa'),
					 __('Без ПДВ (Б)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 20% + акциз 5% (ГД)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 7% (В)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 0% (Е)', 'mrkv-vchasno-kasa'), 
					 __('Без ПДВ + акциз 5% (Ж)', 'mrkv-vchasno-kasa'), 
					 __('Не є об\'єктом ПДВ (З)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 20% + ПФ 7.5% (ИК)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 14% (Л)', 'mrkv-vchasno-kasa'), 
					 __('ПДФО 18% Військовий збір 1.5% (М)', 'mrkv-vchasno-kasa'));


# Current  tax group 
$current_tax_group = get_option('mrkv_kasa_tax_group', '1');
# Test enabled
$test_is_active = (get_option('mrkv_kasa_test_enabled') == '') ? false : get_option('mrkv_kasa_test_enabled');

# Send receipt enabled
$mrkv_kasa_receipt_send_user = (get_option('mrkv_kasa_receipt_send_user') == '') ? false : get_option('mrkv_kasa_receipt_send_user');

# Send receipt enabled type
$mrkv_kasa_receipt_send_type = get_option('mrkv_kasa_receipt_send_type');

# Phone enabled
$mrkv_kasa_phone = (get_option('mrkv_kasa_phone') == '') ? false : get_option('mrkv_kasa_phone');

if(isset($mrkv_kasa_receipt_send_user) && $mrkv_kasa_receipt_send_user && isset($mrkv_kasa_receipt_send_type) 
	&& is_array($mrkv_kasa_receipt_send_type) && (in_array('sms', $mrkv_kasa_receipt_send_type) || in_array('cascade', $mrkv_kasa_receipt_send_type) || in_array('viber', $mrkv_kasa_receipt_send_type))){
	$mrkv_kasa_phone = 1;
	update_option('mrkv_kasa_phone', 1);
}

# Shipping price enabled
$mrkv_kasa_shipping_price = (get_option('mrkv_kasa_shipping_price') == '') ? false : get_option('mrkv_kasa_shipping_price');

# Zero price product skip enabled
$mrkv_kasa_skip_zero_product = (get_option('mrkv_kasa_skip_zero_product') == '') ? false : get_option('mrkv_kasa_skip_zero_product');

# Return receipt on a full refund enabled
$mrkv_kasa_return_receipt_auto = (get_option('mrkv_kasa_return_receipt_auto') == '') ? false : get_option('mrkv_kasa_return_receipt_auto');

# Log file open
$debug_log = file_get_contents(__DIR__ . '/../logs/debug.log');
?>
<div class="vchasno-kasa-main">
	<div class="wrap">
		<h1>
		  <span><?php echo esc_html( get_admin_page_title() ); ?></span>
		  <img src="<?php echo esc_url( plugin_dir_url(__FILE__) . '../assets/imgs/logo-kasa-circle-purple.svg' ); ?>" />
		</h1>
		<hr/>
		<p><?php echo esc_html( __('Плагін інтеграції WooCommerce з Kasa.vchasno.com.ua, сервісом програмної реєстрації розрахункових операцій (пРРО).', 'mrkv-vchasno-kasa') ); ?></p>
		<?php settings_errors(); ?>
		<?php 
			if($error_message){
				?>
					<div id="setting-error-settings_updated" class="notice notice-error settings-error is-dismissible"> 
					<p><strong><?php echo esc_html($error_message); ?></strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Приховати це сповіщення.</span></button></div>
				<?php
			}
		?>
		
		<form method="post" action="options.php">
			<?php settings_fields('mrkv_kasa-settings-group'); ?>
			<div class="columns-half">
				<div class="columns-half__column">
					<h2><?php echo esc_html( __('Загальні налаштування', 'mrkv-vchasno-kasa') ); ?></h2>
					<hr>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Токен каси', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="text" name="mrkv_kasa_token" value="<?php echo esc_html(get_option('mrkv_kasa_token')); ?>" />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Іноформація про касира', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="text" name="mrkv_kasa_cashier" value="<?php echo esc_html(get_option('mrkv_kasa_cashier')); ?>" />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Код податкової групи', 'mrkv-vchasno-kasa'); ?></p>
						<select name="mrkv_kasa_tax_group" id="mrkv_kasa_tax_group">
							<?php 
								
								$index = 1;
								foreach($tax_groupes as $group){
									$selected = ($current_tax_group == $index) ? 'selected' : '';
									echo '<option ' . esc_html($selected) . ' value="'. esc_html($index) . '">' . esc_html($group) . '</option>';
									++$index;
								}
							?>
							
						</select>
					</div>
				</div>
				<div class="columns-half__column">
					<h2><?php echo esc_html( __('Тестовий режим', 'mrkv-vchasno-kasa') ); ?></h2>
					<hr>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Увімкнути тестовий режим', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_test_enabled" <?php echo ($test_is_active) ? esc_html('checked') : ''; ?> />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Тестовий токен', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="text" name="mrkv_kasa_test_token" value="<?php echo esc_html(get_option('mrkv_kasa_test_token')); ?>" />
					</div>
				</div>
			</div>
			<div>
				<h2 class="mt-40"><?php echo esc_html( __('Правила автоматичного формування чеків', 'mrkv-vchasno-kasa') ); ?></h2>
					<hr>
					<p><?php echo esc_html( __('Налаштуйте для яких саме способів оплати створювати чеки автоматично. Ви завжди зможете створити чек вручну зі сторінки замовлення.', 'mrkv-vchasno-kasa') ); ?></p>
					<div class="mrkv_table-payment">
						<div class="mrkv_table-payment__header">
						    <p><?php echo esc_html( __('Спосіб оплати', 'mrkv-vchasno-kasa') ); ?></p>
						    <p><?php echo esc_html( __('Форма оплати', 'mrkv-vchasno-kasa') ); ?></p>
						    <p><?php echo esc_html( __('Код оплати', 'mrkv-vchasno-kasa') ); ?></p>
						    <p><?php echo esc_html( __('Статуси замовлення', 'mrkv-vchasno-kasa') ); ?></p>
						</div>
						<hr>
						<div class="mrkv_table-payment__body">
								<?php
								$enabled_gateways = array_filter(WC()->payment_gateways->payment_gateways(), function ($gateway) {
	                                return 'yes' === $gateway->enabled;
	                            });
	                            $ppo_payment_type          = get_option('mrkv_kasa_code_type_payment', []);
	                            $ppo_payment_type_custom = get_option('mrkv_kasa_code_type_payment_custom', []);
                            	$ppo_skip_receipt_creation = get_option('mrkv_kasa_receipt_creation', []);
                            	$mrkv_kasa_payment_order_statuses = get_option('mrkv_kasa_payment_order_statuses', []);
                            	$all_order_statuses = wc_get_order_statuses();

								foreach($enabled_gateways as $id => $gateway){
									?>
									<div class="mrkv_table-payment__body__line">
										<div class="mrkv_table-payment__body__checkbox">
											<input name="mrkv_kasa_receipt_creation[<?php echo esc_html($id); ?>]" id="mrkv_kasa_receipt_creation_<?php echo esc_html($id); ?>" type="checkbox" <?php 
												if(isset($ppo_skip_receipt_creation[$id]) && $ppo_skip_receipt_creation[$id] == 'on'){
													echo esc_html('checked');
												} ?>>
											<label for="mrkv_kasa_receipt_creation_<?php echo esc_html($id); ?>">
												<div class="mrkv_table-payment__body__checkbox__input">
													<span class="mrkv_kasa_slider"></span>
												</div>
												<?php echo esc_html($gateway->get_title()); ?></label>
										</div>
										<div class="mrkv_table-payment__body__type">
											<select name="mrkv_kasa_code_type_payment[<?php echo esc_html($id); ?>]" id="mrkv_kasa_code_type_payment_<?php echo esc_html($id); ?>" style="<?php 
												if(!isset($ppo_skip_receipt_creation[$id])){
													echo esc_html('opacity: .6;');
												} ?>">
												<?php 
													foreach($payment_types as $index => $type){
														$selected = ( isset($ppo_payment_type[$id]) && ($index == $ppo_payment_type[$id]) ) ? 'selected' : '';
														echo '<option ' . esc_html($selected) . ' value="'. esc_html($index) . '">' . esc_html($type) . '</option>';
													}
												?>
												
											</select>
										</div>
										<div class="mrkv_table-payment__body__number_pay">
											<?php 
												$readonly_pay = '';
												if(isset($ppo_payment_type[$id]) && $ppo_payment_type[$id] != 1111)
												{
													$ppo_payment_type_custom[$id] = $ppo_payment_type[$id];
													$readonly_pay = 'readonly';
												}
												elseif(!isset($ppo_payment_type[$id]))
												{
													$ppo_payment_type_custom[$id] = 0;
													$readonly_pay = 'readonly';
												}
											?>
											<input type="number" name="mrkv_kasa_code_type_payment_custom[<?php echo esc_html($id); ?>]" id="mrkv_kasa_code_type_payment_custom[<?php echo esc_html($id); ?>]" value="<?php echo isset($ppo_payment_type_custom[$id]) ? $ppo_payment_type_custom[$id] : ''; ?>" <?php echo $readonly_pay; ?>>
										</div>
										<div class="mrkv_table-payment__body__statuses" style="<?php 
												if(!isset($ppo_skip_receipt_creation[$id])){
													echo esc_html('opacity: .6;');
												} ?>">
											<select class="chosen chosen-select order-statuses" name="mrkv_kasa_payment_order_statuses[<?php echo esc_attr( $id ); ?>][]" data-placeholder="<?php esc_attr_e( 'Оберіть статуси замовлення', 'mrkv-vchasno-kasa' ); ?>" multiple>
	                                            <?php
	                                            if (! empty($all_order_statuses)) :
	                                                foreach ($all_order_statuses as $k => $v) :
	                                                    $k = str_replace('wc-', '', $k);
	                                                    $is_selected = ( is_array($mrkv_kasa_payment_order_statuses) && isset($mrkv_kasa_payment_order_statuses[$id]) && is_array($mrkv_kasa_payment_order_statuses[$id]) && in_array($k, $mrkv_kasa_payment_order_statuses[$id]) ) ? esc_html('selected') : '';
	                                                    ?>
	                                                <option value="<?php echo esc_html($k); ?>" <?php echo esc_html($is_selected); ?>><?php echo esc_html($v); ?></option>
	                                                    <?php
	                                                endforeach;
	                                            else :
	                                                printf('<option value="">%s</option>', esc_html(__('None', 'mrkv-vchasno-kasa')));
	                                            endif;
	                                            ?>
	                                        </select>
										</div>
									</div>
									<hr>
									<?php
								}

								$m_ua_active_plugins = get_option('m_ua_active_plugins');
								$active_shippings = false;

								if($m_ua_active_plugins && is_array($m_ua_active_plugins) && !empty($m_ua_active_plugins) && defined( 'MRKV_UA_SHIPPING_LIST' ))
								{
									foreach(MRKV_UA_SHIPPING_LIST as $slug => $shipping)
					                {
					                    if($m_ua_active_plugins && isset($m_ua_active_plugins[$slug]['enabled']) && $m_ua_active_plugins[$slug]['enabled'] == 'on')
					                    {
					                    	$id = $slug . '_code';
					                    	?>
												<div class="mrkv_table-payment__body__line">
													<div class="mrkv_table-payment__body__checkbox">
														<input name="mrkv_kasa_receipt_creation[<?php echo esc_html($id); ?>]" id="mrkv_kasa_receipt_creation_<?php echo esc_html($id); ?>" type="checkbox" <?php 
															if(isset($ppo_skip_receipt_creation[$id]) && $ppo_skip_receipt_creation[$id] == 'on'){
																echo esc_html('checked');
															} ?>>
														<label for="mrkv_kasa_receipt_creation_<?php echo esc_html($id); ?>">
															<div class="mrkv_table-payment__body__checkbox__input">
																<span class="mrkv_kasa_slider"></span>
															</div>
															<?php echo esc_html($shipping['name']) . ' ' . esc_html( __('(післяплата)', 'mrkv-vchasno-kasa') ); ?></label>
													</div>
													<div class="mrkv_table-payment__body__type">
														<select name="mrkv_kasa_code_type_payment[<?php echo esc_html($id); ?>]" id="mrkv_kasa_code_type_payment_<?php echo esc_html($id); ?>" style="<?php 
															if(!isset($ppo_skip_receipt_creation[$id])){
																echo esc_html('opacity: .6;');
															} ?>">
															<?php 
																foreach($payment_types as $index => $type){
																	$selected = ( isset($ppo_payment_type[$id]) && ($index == $ppo_payment_type[$id]) ) ? 'selected' : '';
																	echo '<option ' . esc_html($selected) . ' value="'. esc_html($index) . '">' . esc_html($type) . '</option>';
																}
															?>
															
														</select>
													</div>
													<div class="mrkv_table-payment__body__number_pay">
														<?php 
															$readonly_pay = '';
															if(isset($ppo_payment_type[$id]) && $ppo_payment_type[$id] != 1111)
															{
																$ppo_payment_type_custom[$id] = $ppo_payment_type[$id];
																$readonly_pay = 'readonly';
															}
															elseif(!isset($ppo_payment_type[$id]))
															{
																$ppo_payment_type_custom[$id] = 0;
																$readonly_pay = 'readonly';
															}
														?>
														<input type="number" name="mrkv_kasa_code_type_payment_custom[<?php echo esc_html($id); ?>]" id="mrkv_kasa_code_type_payment_custom[<?php echo esc_html($id); ?>]" value="<?php echo isset($ppo_payment_type_custom[$id]) ? $ppo_payment_type_custom[$id] : ''; ?>" <?php echo $readonly_pay; ?>>
													</div>
													<div class="mrkv_table-payment__body__statuses" style="<?php 
															if(!isset($ppo_skip_receipt_creation[$id])){
																echo esc_html('opacity: .6;');
															} ?>">
														<select class="chosen chosen-select order-statuses" name="mrkv_kasa_payment_order_statuses[<?php echo esc_attr( $id ); ?>][]" data-placeholder="<?php esc_attr_e( 'Оберіть статуси замовлення', 'mrkv-vchasno-kasa' ); ?>" multiple>
				                                            <?php
				                                            if (! empty($all_order_statuses)) :
				                                                foreach ($all_order_statuses as $k => $v) :
				                                                    $k = str_replace('wc-', '', $k);
				                                                    $is_selected = ( is_array($mrkv_kasa_payment_order_statuses) && isset($mrkv_kasa_payment_order_statuses[$id]) && is_array($mrkv_kasa_payment_order_statuses[$id]) && in_array($k, $mrkv_kasa_payment_order_statuses[$id]) ) ? esc_html('selected') : '';
				                                                    ?>
				                                                <option value="<?php echo esc_html($k); ?>" <?php echo esc_html($is_selected); ?>><?php echo esc_html($v); ?></option>
				                                                    <?php
				                                                endforeach;
				                                            else :
				                                                printf('<option value="">%s</option>', esc_html(__('None', 'mrkv-vchasno-kasa')));
				                                            endif;
				                                            ?>
				                                        </select>
													</div>
												</div>
												<hr>
												<?php
					                    }
					                }
								}
							?>
						</div>
					</div>
			</div>
			<div class="columns-half">
				<div class="columns-half__column">
					<h2 class="mt-40"><?php echo esc_html( __('Додаткові налаштування', 'mrkv-vchasno-kasa') ); ?></h2>
					<hr>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Надсилати номер телефона покупця при створенні чеку', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_phone" <?php echo ($mrkv_kasa_phone) ? esc_html('checked') : ''; ?> />
					</div>
					<div class="line-form mrkv_kasa_receipt_send_user-select">
						<p class="line-form__title"><?php esc_html_e('Відправка чеків покупцям', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_receipt_send_user" <?php echo ($mrkv_kasa_receipt_send_user) ? esc_html('checked') : ''; ?> />
						<?php 
							$send_receipt_type_list = array('email', 'sms', 'viber', 'cascade');
						?>
						<select class="chosen chosen-select" name="mrkv_kasa_receipt_send_type[]" id="mrkv_kasa_receipt_send_type" data-placeholder="<?php esc_attr_e('Оберіть тип відправлення', 'mrkv-vchasno-kasa'); ?>" multiple>
							<?php
								foreach ($send_receipt_type_list as $type) {
								    $selected = ( isset($mrkv_kasa_receipt_send_type) && is_array($mrkv_kasa_receipt_send_type) && in_array($type, $mrkv_kasa_receipt_send_type) ) ? 'selected' : '';
								    ?>
								    <option value="<?php echo esc_attr($type); ?>" <?php echo esc_html($selected); ?>>
								        <?php echo esc_html($type); ?>
								    </option>
								    <?php
								}
							?>
						</select>
						<p><i><?php echo esc_html( __('cascade – спробувати відправити через Viber. Якщо вайбер не встановлений на телефоні, то відправити через SMS.', 'mrkv-vchasno-kasa') ); ?></i></p>
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Включати вартість доставки у чеку', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_shipping_price" <?php echo ($mrkv_kasa_shipping_price) ? esc_html('checked') : ''; ?> />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Пропускати товари з нульовою ціною', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_skip_zero_product" <?php echo ($mrkv_kasa_skip_zero_product) ? esc_html('checked') : ''; ?> />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Увімкнути автоматичне створення чеку повернення, коли замовлення стане refund (повернене)', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_return_receipt_auto" <?php echo ($mrkv_kasa_return_receipt_auto) ? esc_html('checked') : ''; ?> />
						<p><i><?php esc_html_e('Чек створиться, лише якщо замовлення вже має чек продажу. Часткові повернення не підтримуються.', 'mrkv-vchasno-kasa'); ?></i></p>
					</div>
					<?php echo submit_button(esc_html(__('Зберегти', 'mrkv-vchasno-kasa'))); ?>
				</div>
				<div class="columns-half__column">
					<div class="line-form">
						<h2 class="line-form__title mt-40"><?php esc_html_e('Лог', 'mrkv-vchasno-kasa'); ?></h2>
						<hr>
						<pre class="mrkv_full-log"><?php echo esc_html( $debug_log ); ?></pre>
					</div>
					<div class="line-form clear-line">
						<div class="mrkv_clean-all-log"><?php echo esc_html( __('Очистити Лог', 'mrkv-vchasno-kasa') ); ?></div>
						<span><?php echo esc_html( __('*Файл логу автоматично очищується кожні 30 днів', 'mrkv-vchasno-kasa') ); ?></span>
					</div>
				</div>
			</div>
		</form>
		<div class="plugin-development mt-40">
			<span><?php echo esc_html( __('Веб студія', 'mrkv-vchasno-kasa') ); ?></span>
			<a href="https://morkva.co.ua/" target="_blank">
			    <img src="<?php echo esc_url( plugin_dir_url(__FILE__) . '../assets/imgs/morkva-logo.svg' ); ?>" alt="Morkva" title="Morkva" />
			</a>
		</div>
	</div>
</div>

<script>
	jQuery(document).ready( function($){
		jQuery('.mrkv_table-payment__body__type select').change(function(){
			let mrkv_kasa_code_type_payment = jQuery(this).val();

			if(mrkv_kasa_code_type_payment != 1111)
			{
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__number_pay input').val(mrkv_kasa_code_type_payment);
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__number_pay input').prop('readonly', true);
			}
			else{
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__number_pay input').val('');
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__number_pay input').prop('readonly', false);
			}
		});
		jQuery('.mrkv_clean-all-log').click(function(){
			jQuery.ajax({
		    url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		    type: 'POST',
		    data:{ 
		      action: 'clearlog',
		      nonce: '<?php echo wp_create_nonce( 'mrkv_clear_log_nonce' ); ?>'
		    },
		    success: function( data ){
		      jQuery('.mrkv_full-log').text('');
		    }
		  });
		});
		jQuery(".chosen-select").chosen({no_results_text: "Не задано статуси!"}); 
		jQuery('.mrkv_table-payment__body__checkbox input').change(function(){
			if(this.checked) {
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__type select').css('opacity', '1');
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__statuses').css('opacity', '1');
			}
			else{
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__type select').css('opacity', '.6');
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__statuses').css('opacity', '.6');
			}
		});
		jQuery('#mrkv_kasa_receipt_send_type').change(function(){
			jQuery("#mrkv_kasa_receipt_send_type option:selected").map(function(){ 
				if(this.value == 'sms' || this.value == 'cascade' || this.value == 'viber'){
					jQuery('input[name="mrkv_kasa_phone"]').prop('checked', true);
				}  
			});
		});
	});
</script>

