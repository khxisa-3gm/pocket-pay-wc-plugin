<?php
/*
 * Plugin Name: Pocket Pay Payment Plugin
 * Description: Accept online payments on your woocommerce store powered by Pocket.
 * Author: Yamin, Nisa Alias @ ThreeG Media Sdn Bhd 
 * Author URI: https://www.threegmedia.com
 * Version: 1.6
 */
if ( ! in_array( 'woocommerce/woocommerce.php', 
	apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
return;

add_action( 'init', 'initialize_gateway_class' );
add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );	

function initialize_gateway_class() {
	
    class PocketPay extends WC_Payment_Gateway {

		public $store_name = '';
		public $test_mode = false;
		public $api_key = null;
		public $salt = null;

		public function __construct() {
			$this->id = 'pocketpay'; // payment gateway ID
			$this->icon = ''; // payment gateway icon
			$this->has_fields = true; // for custom credit card form
			$this->title = __( 'Pocket Pay', 'text-domain' ); // vertical tab title
			$this->method_title = __( 'Pocket Pay', 'text-domain' ); // payment method name
			$this->method_description = __( 'Pay using your Pocket app or any VISA/Mastercard.', 'text-domain' ); // payment method description
			$this->store_name = __( 'Default Store Name', 'text-domain' ); // store name

			$this->supports = array( 'default_credit_card_form' );

			// load backend options fields
			$this->init_form_fields();

			// load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->store_name = $this->get_option( 'store_name' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->test_mode = 'yes' === $this->get_option( 'test_mode' );
			if ($this->test_mode === true) {
				$this->api_key = $this->get_option( 'test_api_key' );
				$this->salt = $this->get_option( 'test_salt' );
			} else {
				$this->api_key = $this->get_option( 'api_key' );
				$this->salt = $this->get_option( 'salt' );
			}
			
			// Action hook to saves the settings
			if(is_admin()) {
				  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			// Action hook to load custom JavaScript
			add_action( 'woocommerce_api_pocket', array( $this, 'webhook' ) );
		}
		
		public function init_form_fields(){

			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable/Disable', 'text-domain' ),
					'label'       => __( 'Enable Pocket Pay', 'text-domain' ),
					'type'        => 'checkbox',
					'description' => __( 'This enable the Pocket Pay option.', 'text-domain' ),
					'default'     => 'no',
					'desc_tip'    => true
				),
				'title' => array(
					'title'       => __( 'Title', 'text-domain'),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'text-domain' ),
					'default'     => __( 'Credit Card', 'text-domain' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'text-domain' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'text-domain' ),
					'default'     => __( 'Pay with your credit card via our super-cool payment gateway.', 'text-domain' ),
				),
				'store_name' => array(
					'title'       => __( 'Store Name', 'text-domain' ),
					'type'        => 'textarea',
					'description' => __( 'This will be shown in Pocket Pay page.', 'text-domain' ),
					'default'     => __( 'Store Name', 'text-domain' ),
				),
				'test_mode' => array(
					'title'       => __( 'Test mode', 'text-domain' ),
					'label'       => __( 'Enable Test Mode', 'text-domain' ),
					'type'        => 'checkbox',
					'description' => __( 'Place the payment gateway in test mode using test API keys.', 'text-domain' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'test_api_key' => array(
					'title'       => __( 'Test API Key', 'text-domain' ),
					'type'        => 'text'
				),
				'test_salt' => array(
					'title'       => __( 'Test Salt', 'text-domain' ),
					'type'        => 'text',
				),
				'api_key' => array(
					'title'       => __( 'Live API Key', 'text-domain' ),
					'type'        => 'text'
				),
				'salt' => array(
					'title'       => __( 'Live Salt', 'text-domain' ),
					'type'        => 'text'
				)
			);
		}
		
		public function payment_fields() {

			if ( $this->description ) {
				if ( $this->test_mode ) {
					$this->description .= ' Test mode is enabled. You can use the dummy credit card numbers to test it.';
				}
				echo wpautop( wp_kses_post( $this->description ) );
			}
			
		 
		}
		public function validate_fields(){

			return true;
		 
		}
		
		
		public function process_payment( $order_id ) {

			global $woocommerce;
		 
			// get order detailes
			$order = wc_get_order( $order_id );
			
			$api_key = $this->api_key;
			$salt = $this->salt;
			$store_name = $this->store_name;
			$return_url = home_url() . "/wc-api/pocket";
			
			$total_in_cents = intval(floatval($order->get_total())*100);
			
			$hashed_data = $this->spp_hash($api_key, $salt, $order_id, $total_in_cents, $return_url, $store_name);
			if($hashed_data) {
				$createUrl = $this->spp_create_url($api_key, $salt, $order_id, $total_in_cents, $hashed_data->hashed_data, $return_url, $store_name);

				if($createUrl != false){
					return array(
						'result' => 'success',
						'redirect' => $createUrl->payment_url
					);
				} else {
					wc_add_notice(  'Please try again. 1', 'error' );
					return;
				}
			} else {
				wc_add_notice(  'Please try again. 2 : ' . $hashed_data, 'error' );
				return;
			}
		 
		}

		public function webhook() {
			global $woocommerce;
			
			$api_key = $this->api_key;
			$salt = $this->salt;
			if(isset($_GET['OrderId'])){
				$id = $_GET['OrderId'];
				$order = wc_get_order($id);
				if($order){
					$total_in_cents = intval(floatval($order->get_total())*100);
					//Query from SPP first
					$queryResult = $this->query_status($api_key, $salt, $id);
					if($queryResult){
						if(isset($queryResult->status_id) && isset($queryResult->final_amount)){
							$status_id = $queryResult->status_id;
							$final_amount = $queryResult->final_amount;
							if(intval($status_id)==1){
								$final_amount_in_cents = intval(floatval($final_amount) * 100);
								if($final_amount_in_cents == $total_in_cents){
									$order->payment_complete();
									$order->reduce_order_stock();

									update_option('webhook_debug', $_GET);
									return wp_redirect($this->get_return_url( $order ));

								} else {
									wc_add_notice(  'Payment details did not match.', 'error' );
									return wp_redirect( home_url( "cart" ) );
									return;

								}
							} else {
								wc_add_notice(  'Payment not successful.', 'error' );
								return wp_redirect( home_url( "cart" ) );

							}
						} else {
							wc_add_notice(  'Unable to verify transaction status.', 'error' );
							
							return wp_redirect( home_url( "cart" ) );
						}
					} else {
						wc_add_notice(  'Unable to verify transaction status.', 'error' );
						
						return wp_redirect( home_url( "cart" ) );
					}
				} else {
					wc_add_notice(  'Invalid order ID.', 'error' );
			
					return wp_redirect( home_url( "cart" ) );
				}
			} else {
				wc_add_notice(  'Invalid order ID.', 'error' );
				
				return wp_redirect( home_url( "cart" ) );
			}
		}

		
		function query_status($api_key, $salt, $order_id){
			$postData = [
				"api_key" => $api_key,
				"salt" => $salt,
				"order_id" => $order_id
			];

			//var_dump($ac);
			$returnVal = false;
			
			// if($this->test_mode){
			// 	$URL = "http://pay.threeg.asia/payments/status";
			// } else {
			// 	$URL = "https://pay.pocket.com.bn/payments/status";
			// }

			$url = $this->test_mode ? "http://pay.threeg.asia/payments/status" : "https://pay.pocket.com.bn/payments/status";
			
			$response = wp_remote_post ( $URL, array(
				'timeout' => 60,
				'redirection' => 5,
				'headers' => array('Content-Type' => 'application/json'),
				'body' => json_encode( $postData)
			));

			if ( is_wp_error( $response ) ) {
				error_log( "PocketPay Query Status Error: " . $response->get_error_message() );
				return false;
			}

			$statusCode = wp_remote_retrieve_response_code( $response );
        	$results    = wp_remote_retrieve_body( $response );

			if ( intval( $statusCode ) === 200 ) {
				return json_decode( $results );
			}

			error_log( "PocketPay Query Status HTTP Error: $statusCode | Response: $results" );
        	return false;






			// $ch = curl_init();
			// curl_setopt($ch, CURLOPT_URL, $URL);
			
			// //curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			// curl_setopt($ch, CURLOPT_FAILONERROR,1);
			// curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
			// curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			// curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			// curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postData) );
			// curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			
			// $results = curl_exec($ch);
			// $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			// $err = curl_error($ch);
			// //var_dump($results);
			// if(intval($statusCode) == 200){
			// 	//Success
			// 	$returnVal = json_decode($results);
			// } else {
			// 	echo $statusCode;
			// }
			// curl_close ($ch);
			// return $returnVal;
		}
		
		function spp_hash($api_key, $salt, $order_id, $amount, $return_url, $store_name){
			
			$postData = [
				"api_key" => $api_key,
				"salt" => $salt,
				"order_id" =>  $order_id,
				"order_desc" => "Description",
				"order_info" => "Payment requested from $store_name for order #$order_id",
				"subamount_1" => intval($amount),
				"subamount_1_label" => "Final Total",
				"subamount_2" => "0",
				"subamount_3" => "0",
				"subamount_4" => "0",
				"subamount_5" => "0",
				"discount" => "0",
				"return_url" => $return_url
			];
			

			//var_dump($ac);
			$returnVal = false;
			
			if($this->test_mode){
				$URL = "http://pay.threeg.asia/payments/hashOld";
			} else {
				$URL = "https://pocket-pay.threeg.asia/payments/hashOLD";
			}

			$response = wp_remote_post($URL, array(
				'timeout' => 60,
				'redirection' => 5,
				'headers' => array('Content-Type' => 'application/json'),
				'body' => json_encode($postData)
			));

			if (is_wp_error($response)) {
				error_log ( "PocketPay Hash Error: " . $response->get_error_message());
				return false;
			}

			$statusCode = wp_remote_retrieve_response_code($response);
			$results = wp_remote_retrieve_body($response);

			if (intval($statusCode) === 200) {
				return json_decode($results);
			}

			error_log( "PocketPay Hash HTTP Error: $statusCode | Response: $results" );
        	return false;
			
			// $ch = curl_init();
			// curl_setopt($ch, CURLOPT_URL, $URL);
			
			// //curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			// curl_setopt($ch, CURLOPT_FAILONERROR,1);
			// curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
			// curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			// curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			// curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postData) );
			// curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			
			// $results = curl_exec($ch);
			// $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			// $err = curl_error($ch);
			// //var_dump($results);
			// if(intval($statusCode) == 200){
			// 	//Success
			// 	$returnVal = json_decode($results);
			// } else {
				
			// 	wc_add_notice(  'Hashing error : ' . json_encode($URL), 'error' );
			// 	return;
			// }
			// curl_close ($ch);
			// return $returnVal;
		}
		
		function spp_create_url($api_key, $salt, $order_id, $amount, $hashed_data, $return_url, $store_name){
			$postData = [
				"api_key" => $api_key,
				"salt" => $salt,
				"order_id" =>  $order_id,
				"order_desc" => "Description",
				"order_info" => "Payment requested from $store_name for order #$order_id",
				"subamount_1" => $amount,
				"subamount_1_label" => "Final Total",
				"subamount_2" => "0",
				"subamount_3" => "0",
				"subamount_4" => "0",
				"subamount_5" => "0",
				"discount" => "0",
				"return_url" => $return_url,
				"hashed_data" => $hashed_data
			];
			

			//var_dump($ac);
			$returnVal = false;
			
			if($this->test_mode){
				$URL = "http://pay.threeg.asia/payments/createOld";
			} else {
				$URL = "https://pocket-pay.threeg.asia/payments/createOLD";
			}

			$response = wp_remote_post( $URL, array(
				'timeout'     => 60,
				'redirection' => 5,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => json_encode( $postData ),
			) );

			 if ( is_wp_error( $response ) ) {
				error_log( "PocketPay Create URL Error: " . $response->get_error_message() );
				return false;
			}

			$statusCode = wp_remote_retrieve_response_code( $response );
			$results    = wp_remote_retrieve_body( $response );

			if ( intval( $statusCode ) === 200 ) {
				return json_decode( $results );
			}

			error_log( "PocketPay Create URL HTTP Error: $statusCode | Response: $results" );
			return false;


			// $ch = curl_init();
			// curl_setopt($ch, CURLOPT_URL, $URL);
			
			// //curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			// curl_setopt($ch, CURLOPT_FAILONERROR,1);
			// curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
			// curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			// curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			// curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postData) );
			// curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			
			// $results = curl_exec($ch);
			// $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			// $err = curl_error($ch);
			// //var_dump($results);
			// if(intval($statusCode) == 200){
			// 	//Success
			// 	$returnVal = json_decode($results);
			// }
			// curl_close ($ch);
			// return $returnVal;
		}
    }
	
	
}

function add_custom_gateway_class( $gateways ) {
	$gateways[] = 'PocketPay'; // payment gateway class name
	return $gateways;
}
