<?php
# Include all classes
include plugin_dir_path(__DIR__) . "classes/mrkv-vchasno-kasa-api.php"; 
include plugin_dir_path(__DIR__) . "classes/mrkv-logger-kasa.php"; 
include plugin_dir_path(__DIR__) . "classes/mrkv-connect-data.php"; 

# Check if class exist
if (!class_exists('MRKV_SHIFT')){
	/**
	 * Class to work with shift
	 */
	class MRKV_SHIFT
	{
		/**
		 * @var string Token number
		 * */
		private $api_vchasno_kasa;

		/**
		 * @var string Shift Status
		 * */
		private $current_status;

		/**
		 * @var string Url action
		 * */
		const ACTION_TYPE = 'fiscal/execute';

		/**
		 * Constructor for create shift object
		 * @var string Token number
		 * @var string Source data
		 * @var string Device name
		 * */
		function __construct(){
			# Create data connect
			$connected = new MRKV_CONNECT_DATA();
			# Save data
			$this->api_vchasno_kasa = new MRKV_VCHASNO_KASA_API($connected->get_connect_data());
			# Current status save
			$this->current_status = (get_option('mrkv_kasa_shift_status', '0') == '') ? '0' : get_option('mrkv_kasa_shift_status', '0');
			 
		}

		/**
		 * Get shift status and update
		 * */
		public function update_shift_status(){
			# Create params array
			$params = array(
					'source' => 'VCD-1880',
					'fiscal' => array(
						'task' => 18
					)
			);

			# Send query to Vchasno Kasa
			$response = $this->api_vchasno_kasa->connect($params, self::ACTION_TYPE);

			# Create log data object
			$log = new MRKV_LOGGER_KASA();

			# Decode json response to StdClass
			$result = json_decode($response);

			# If response not exist error message
			if(is_object($result) && empty($result->errortxt)){
				# Check exist option value
				if($this->validation_shift($result->info->shift_status) != $this->current_status){
					# Change exist option value
					$this->current_status = $this->validation_shift($result->info->shift_status);
					update_option('mrkv_kasa_shift_status', $this->current_status);

					# Show result in log file
					$status_name = ($this->current_status == 0) ? __('Закрита', 'mrkv-vchasno-kasa') : __('Відкрита', 'mrkv-vchasno-kasa');
					$log->save_log(
					    sprintf(
					        /* translators: %s is the current shift status name */
					        __('Статус зміни оновлено. Поточний статус зміни: %s', 'mrkv-vchasno-kasa'),
					        $status_name
					    )
					);
				}
			}
			else{
				# Show Error
				$log->save_log(__('Помилка при перевірці статуса зміни: ', 'mrkv-vchasno-kasa'));
				$log->save_log(is_object($result) && isset($result->errortxt) ? $result->errortxt : $response);
			}
			
		}

		/**
		 * Open shift Vchasno Kasa
		 * */
		public function open_shift(){
			# Create params array
			$params = array(
					'source' => 'VCD-1880',
					'fiscal' => array(
						'task' => 0
					)
			);

			# Send query to Vchasno Kasa
			$response = $this->api_vchasno_kasa->connect($params, self::ACTION_TYPE);

			# Create log data object
			$log = new MRKV_LOGGER_KASA();

			# Decode json response to StdClass
			$result = json_decode($response);

			# If response not exist error message
			if(is_object($result) && empty($result->errortxt)){
				# Change exist option value
				$this->current_status = '1';
				update_option('mrkv_kasa_shift_status', $this->current_status);

				# Show result in log file
				$status_translated = __('Відкрита', 'mrkv-vchasno-kasa');

				$log->save_log(
				    sprintf(
				        /* translators: %s — поточний статус зміни (текст) */
				        __('Статус зміни оновлено. Поточний статус зміни: %s', 'mrkv-vchasno-kasa'),
				        $status_translated
				    )
				);

				# Get current datetime
				$date_now = date_i18n("Y-m-d h:i:sa");

				# Show added data
				$log->save_log(
				    sprintf(
				        /* translators: %s — YYYY-MM-DD */
				        __('== Початок дня %s ==', 'mrkv-vchasno-kasa'),
				        $date_now
				    )
				);
			}
			else{
				# Show Error
				$log->save_log(__('Зміну не вдалося відкрити. Помилка: ', 'mrkv-vchasno-kasa'));
				$log->save_log(is_object($result) && isset($result->errortxt) ? $result->errortxt : $response);
			}
		}

		/**
		 * Close shift Vchasno Kasa
		 * */
		public function close_shift(){
			# Create params array
			$params = array(
					'source' => 'VCD-1880',
					'fiscal' => array(
						'task' => 11
					)
			);

			# Send query to Vchasno Kasa
			$response = $this->api_vchasno_kasa->connect($params, self::ACTION_TYPE);

			# Create log data object
			$log = new MRKV_LOGGER_KASA();

			# Decode json response to StdClass
			$result = json_decode($response);

			# If response not exist error message
			if(is_object($result) && empty($result->errortxt)){
				# Change exist option value
				$this->current_status = '0';
				update_option('mrkv_kasa_shift_status', $this->current_status);

				# Show result in log file
				$status_name = __('Закрита', 'mrkv-vchasno-kasa');

				$log->save_log(
				    sprintf(
				        /* translators: %s — shift name */
				        __('Статус зміни оновлено. Поточний статус зміни: %s', 'mrkv-vchasno-kasa'),
				        $status_name
				    )
				);


				# Get current datetime
				$date_now = date_i18n("Y-m-d h:i:sa");

				# Show added data
				$log->save_log(
				    sprintf(
				        /* translators: %s — YYYY-MM-DD */
				        __('== Кінець дня %s ==', 'mrkv-vchasno-kasa'),
				        $date_now
				    )
				);
			}
			else{
				# Show Error
				$log->save_log(__('Зміну не вдалося закрити. Помилка: ', 'mrkv-vchasno-kasa'));
				$log->save_log(is_object($result) && isset($result->errortxt) ? $result->errortxt : $response);
			}
		}

		/**
		 * Validation result of shift status query
		 * @var string Status in Vchasno Kasa
		 * @return string New system status
		 * */
		private function validation_shift($shift_status){
			# Return system status
			return (intval($shift_status) != 1) ? 0 : 1;
		}

		/**
		 * Return system current shift status name
		 * @return string Current shift status name
		 * */
		public function get_current_shift_status_name(){
			# Array of names
			$status_names = [__('Закрита', 'mrkv-vchasno-kasa'), __('Відкрита', 'mrkv-vchasno-kasa')];

			# Return name
			return $status_names[$this->current_status];
		}
	}
}