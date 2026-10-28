<?php

include plugin_dir_path(__DIR__) . "classes/mrkv-logger-kasa.php"; 

# Check if class exist
if (!class_exists('MRKV_VCHASNO_KASA_API')){
	/**
	 * Class for connect with Vchasno Kasa
	 */
	class MRKV_VCHASNO_KASA_API
	{
		/**
		 * @var string Token number
		 * */
		private $token;

		/**
		 * @var string Payment type
		 * */
		private $type;

		/**
		 * @var string Url to vchasno kasa post
		 * */
		const POST_URL = 'https://kasa.vchasno.ua/api/v3/';

		/**
		 * Constructor for connect with Vchasno Kasa
		 * @var string Token number
		 * */
		function __construct($connect_data){
			# Save all variables
			$this->token = $connect_data['token'];
			$this->type = $connect_data['type'];
		}

		/**
		 * Connect with Vchasno Kasa
		 * @param array Params for query
		 * @var string Action name
		 * @return string Query response
		 * */
		public function connect($params, $action, $json = ''){
			# Encode query
			$params_put = ($json) ? $json : json_encode($params);
			# Get query url
			$url = self::POST_URL . $action;
			# Set header query values
			$header = array( 'Content-type' => 'application/json',
							'Authorization' => $this->token, 
			 );

			# Generate setting Post query
			$response = wp_remote_post(
                $url,
                array(
                    'timeout'     => 60,
                    'redirection' => 5,
                    'blocking' => true,	
                    'headers'     => $header,
                    'body'        => $params_put,
                )
            );

			# Return response
			return  $response['body'];
		}

		/**
		 * Connect with Vchasno Kasa
		 * @param array Params for query
		 * @var string Action name
		 * @return string Query response
		 * */
		public function send_receipt($params, $action, $json = ''){
			# Encode query
			$params_put = ($json) ? $json : json_encode($params);
			# Get query url
			$url = self::POST_URL . $action;

			# Set header query values
			$header = array( 'Content-type' => 'application/json',
							'Authorization' => $this->token, 
			);

			# Generate setting Post query
			$response = wp_remote_post(
                $url,
                array(
                    'timeout'     => 60,
                    'redirection' => 5,
                    'blocking' => true,	
                    'headers'     => $header,
                    'body'        => $params_put,
                )
            );

			# Return response, a failed request must not break the order that already has its receipt
			return wp_remote_retrieve_body($response);
		}
	}
}