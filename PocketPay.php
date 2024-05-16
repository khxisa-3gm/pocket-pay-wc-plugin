<?php
/*
 * Plugin Name: Pocket Pay Payment Plugin
 * Description: Accept online payments on your woocommerce store powered by Pocket.
 * Author: Yamin @ ThreeG Media Sdn Bhd
 * Author URI: https://www.threegmedia.com
 * Version: 1.0
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;
add_action( 'plugins_loaded', 'initialize_gateway_class' );
add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );	

function initialize_gateway_class() {
	
    class PocketPay extends WC_Payment_Gateway {

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
			$this->api_key = $this->test_mode ? $this->get_option( 'test_api_key' ) : $this->get_option( 'api_key' );
			$this->salt = $this->test_mode ? $this->get_option( 'test_salt' ) : $this->get_option( 'salt' );

			// Action hook to saves the settings
			if(is_admin()) {
				  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			// Action hook to load custom JavaScript
			//add_action( 'wp_enqueue_scripts', array( $this, 'payment_gateway_scripts' ) );
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
		
		public function payment_scripts() {

			/*
			// process a token only on cart/checkout pages
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}

			// stop enqueue JS if payment gateway is disabled
			if ( 'no' === $this->enabled ) {
				return;
			}

			
			// stop enqueue JS if API keys are not set
			if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
				return;
			}

			// stop enqueue JS if test mode is enabled
			if ( ! $this->test_mode ) {
				return;
			}

			// stop enqueue JS if site without SSL
			if ( ! is_ssl() ) {
				return;
			}
			*/


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
			if($hashed_data){
				$createUrl = $this->spp_create_url($api_key, $salt, $order_id, $total_in_cents, $hashed_data->hashed_data, $return_url, $store_name);

				if($createUrl != false){
					return array(
						'result' => 'success',
						'redirect' => $createUrl->payment_url
					);
				} else {
					wc_add_notice(  'Please try again.', 'error' );
					return;
				}
			} else {
				wc_add_notice(  'Please try again.', 'error' );
				return;
			}

			/*if( !is_wp_error( $response ) ) {
		 
				$body = json_decode( $response['body'], true );
		 
				// it could be different depending on your payment processor
				if ( $body['response']['responseCode'] == 'APPROVED' ) {
		 
					// we received the payment
					$order->payment_complete();
					$order->reduce_order_stock();
		 
					// notes to customer
					$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
					$order->add_order_note( 'This private note shows only on order edit page', false );
		 
					// empty cart
					$woocommerce->cart->empty_cart();
		 
					// redirect to the thank you page
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
		 
				} else {
					wc_add_notice(  'Please try again.', 'error' );
					return;
				}
		 
			} else {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			}*/
		 
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
									//return wp_redirect($this->get_checkout_url( $order ));
									//return wp_redirect($this->get_page_by_path( 'cart' ) );

								}
							} else {
								wc_add_notice(  'Payment not successful.', 'error' );
								return wp_redirect( home_url( "cart" ) );
								//return;
								//return wp_redirect($this->get_checkout_url( $order ));

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

		function get_last_order_id($api_key, $salt){
			$postData = [
				"api_key" => $api_key,
				"salt" => $salt
			];

			//var_dump($ac);
			$returnVal = false;
			
			if($this->test_mode){
				$URL = "http://pay.threeg.asia/payments/getLastOrderId";
			} else {
				$URL = "https://pay.pocket.com.bn/payments/getLastOrderId";
			}
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $URL);
			
			//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR,1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postData) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			
			$results = curl_exec($ch);
			$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			//var_dump($results);
			if(intval($statusCode) == 200){
				//Success
				$returnVal = json_decode($results);
			} else {
				echo $statusCode;
			}
			curl_close ($ch);
			return $returnVal;
		}
		
		function query_status($api_key, $salt, $order_id){
			$postData = [
				"api_key" => $api_key,
				"salt" => $salt,
				"order_id" => $order_id
			];

			//var_dump($ac);
			$returnVal = false;
			
			if($this->test_mode){
				$URL = "http://pay.threeg.asia/payments/status";
			} else {
				$URL = "https://pay.pocket.com.bn/payments/status";
			}
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $URL);
			
			//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR,1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postData) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			
			$results = curl_exec($ch);
			$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			//var_dump($results);
			if(intval($statusCode) == 200){
				//Success
				$returnVal = json_decode($results);
			} else {
				echo $statusCode;
			}
			curl_close ($ch);
			return $returnVal;
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
				$URL = "http://pay.threeg.asia/payments/hash";
			} else {
				$URL = "https://pay.pocket.com.bn/payments/hash";
			}
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $URL);
			
			//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR,1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postData) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			
			$results = curl_exec($ch);
			$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			//var_dump($results);
			if(intval($statusCode) == 200){
				//Success
				$returnVal = json_decode($results);
			}
			curl_close ($ch);
			return $returnVal;
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
				$URL = "http://pay.threeg.asia/payments/create";
			} else {
				$URL = "https://pay.pocket.com.bn/payments/create";
			}
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $URL);
			
			//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR,1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postData) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			
			$results = curl_exec($ch);
			$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			//var_dump($results);
			if(intval($statusCode) == 200){
				//Success
				$returnVal = json_decode($results);
			}
			curl_close ($ch);
			return $returnVal;
		}
    }
	
	
}

function add_custom_gateway_class( $gateways ) {
	$gateways[] = 'PocketPay'; // payment gateway class name
	return $gateways;
}