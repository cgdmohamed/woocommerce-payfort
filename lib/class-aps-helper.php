<?php

/**
 * APS Helper
 *
 * @link       https://paymentservices.amazon.com/
 * @since      2.2.0
 *
 * @package    APS
 * @subpackage APS/lib
 */

/**
 * APS Helper
 *
 * @since      2.2.0
 * @package    APS
 * @subpackage APS/lib
 * @author     Amazon Payment Services
 */
class APS_Helper extends APS_Super {

	private static $instance;
	private $aps_config;
	private $aps_order;
	private $log;

	public function __construct() {
		$this->aps_config = APS_Config::get_instance();
		$this->aps_order  = new APS_Order();
	}

	/**
	 * @return APS_Helper
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new APS_Helper();
		}
		return self::$instance;
	}

	/**
	 * @return APS_Config
	 */
	public function get_config_object() {
		return $this->aps_config;
	}

	/**
	 * Return base currency
	 *
	 * @return string
	 */
	public function get_base_currency() {
		return get_option( 'woocommerce_currency' );
	}

	/**
	 * Return front currency
	 *
	 * @return string
	 */
	public function get_front_currency() {
		$currency = get_woocommerce_currency();
		if ( isset( $_COOKIE['wmc_current_currency'] ) && ! empty( $_COOKIE['wmc_current_currency'] ) ) {
			$currency = $_COOKIE['wmc_current_currency'];
		}
		return $currency;
	}

	/**
	 * Return fort currency
	 *
	 * @param base_currency_code string
	 * @param current_currency_code string
	 * @return string
	 */
	public function get_fort_currency() {
		$base_currency_code    = $this->get_base_currency();
		$current_currency_code = $this->get_front_currency();
		$gateway_currency      = $this->aps_config->get_gateway_currency();
		$currency_code         = $base_currency_code;
		if ( 'front' === $gateway_currency ) {
			$currency_code = $current_currency_code;
		}
		return $currency_code;
	}

	/**
	 * Log the error on the disk
	 */
	public function log( $messages, $force_debug = false ) {
		$debug_mode = $this->aps_config->get_debug_mode() === 'yes' ? true : false;
		if ( ! $debug_mode && ! $force_debug ) {
			return;
		}
		if ( ! class_exists( 'WC_Logger' ) ) {
			include_once 'class-wc-logger.php';
		}
		if ( empty( $this->log ) ) {
			$this->log = new WC_Logger();
		}
		$this->log->add( APS_NAME, $messages );
	}

	/**
	 * Convert Amount with decimal points
	 * @param decimal $amount
	 * @param decimal $currency_value
	 * @param string  $currency_code
	 * @return decimal
	 */
	public function convert_fort_amount( $amount, $currency_value, $currency_code ) {
		$gateway_currency = $this->aps_config->get_gateway_currency();
		$new_amount       = 0;
		$decimal_points   = $this->get_currency_decimal_points( $currency_code );
		if ( 'front' === $gateway_currency ) {
			$new_amount = round( $amount * $currency_value, $decimal_points );
		} else {
			$new_amount = round( $amount, $decimal_points );
		}
		if ( 0 !== $decimal_points ) {
			$new_amount = $new_amount * ( pow( 10, $decimal_points ) );
		}
		return round( $new_amount, 2 );
	}

	/**
	 * Convert decimal point Amount with original amount
	 * @param decimal $amount
	 * @param string  $currency_code
	 * @return decimal
	 */
	public function convert_dec_amount( $amount, $currency_code ) {
		$new_amount     = 0;
		$decimal_points = $this->get_currency_decimal_points( $currency_code );
		$divide_by      = intval( str_pad( 1, $decimal_points + 1, 0, STR_PAD_RIGHT ) );
		if ( 0 === $decimal_points ) {
			$new_amount = $amount;
		} else {
			$new_amount = $amount / $divide_by;
		}
		return round( $new_amount, 2 );
	}

	/**
	 *
	 * @param string $currency
	 * @param integer
	 */
	public function get_currency_decimal_points( $currency ) {
		$decimal_point  = 2;
		$arr_currencies = array(
			'JOD' => 3,
			'KWD' => 3,
			'OMR' => 3,
			'TND' => 3,
			'BHD' => 3,
			'LYD' => 3,
			'IQD' => 3,
			'CLF' => 4,
			'BIF' => 0,
			'DJF' => 0,
			'GNF' => 0,
			'ISK' => 0,
			'JPY' => 0,
			'KMF' => 0,
			'KRW' => 0,
			'CLP' => 0,
			'PYG' => 0,
			'RWF' => 0,
			'UGX' => 0,
			'VND' => 0,
			'VUV' => 0,
			'XAF' => 0,
			'BYR' => 0,
		);
		if ( isset( $arr_currencies[ $currency ] ) ) {
			$decimal_point = $arr_currencies[ $currency ];
		}
		return $decimal_point;
	}

	/**
	 * Get Valu Products array
	 *
	 * @return string
	 */
	private function get_valu_products_data() {
		$products      = array();
		$product_name  = '';
		$category_name = '';
		$order_id      = $this->aps_order->get_session_order_id();
		$order         = $this->aps_order->get_order_by_id( $order_id );
		$items         = $order->get_items();
		$currency      = $this->get_fort_currency();
		foreach ( $items as $item ) {
			$product_name       = $this->clean_string( $item->get_name() );
			$product_id         = $item->get_product_id();
			$product_categories = get_the_terms( $product_id, 'product_cat' );
			foreach ( $product_categories as $product_category ) {
				$category_name = $this->clean_string( $product_category->name );
				break;
			}
			break;
		}
		if ( count( $items ) > 1 ) {
			$product_name = 'MutipleProducts';
		}
		$product_price  = $this->convert_fort_amount( $order->get_total(), $this->aps_order->get_currency_value(), $currency );
		$producs_string = '[{product_name=' . $product_name . ', product_price=' . $product_price . ', product_category=' . $category_name . '}]';
		return $producs_string;
	}

	/**
	 * generate fort signature
	 * @param array $arrData
	 * @param sting $signType request or response
	 * @return string fort signature
	 */
	public function generate_signature( $arr_data, $sign_type = 'request', $type = 'regular' ) {
		$sha_string = $hash_algorithm = '';
		ksort( $arr_data );
		foreach ( $arr_data as $k => $v ) {
			if ( 'products' === $k ) {
				$sha_string .= "$k=" . $this->get_valu_products_data();
			} elseif ( 'apple_header' === $k || 'apple_paymentMethod' === $k ) {
				$sha_string .= $k . '={';
				foreach ( $v as $i => $j ) {
					$sha_string .= $i . '=' . $j . ', ';
				}
				$sha_string  = rtrim( $sha_string, ', ' );
				$sha_string .= '}';
			} else {
				$sha_string .= "$k=$v";
			}
		}
		if ( 'apple_pay' === $type ) {
			$hash_algorithm = $this->aps_config->get_apple_pay_hash_algorithm();
		} else {
			$hash_algorithm = $this->aps_config->get_hash_algorithm();
		}
		$hmac_key = '';
		if ( 'apple_pay' === $type ) {
			if ( 'request' === $sign_type ) {
				$sha_string = $this->aps_config->get_apple_pay_request_sha_phrase() . $sha_string . $this->aps_config->get_apple_pay_request_sha_phrase();
				$hmac_key   = $this->aps_config->get_apple_pay_request_sha_phrase();
			} else {
				$sha_string = $this->aps_config->get_apple_pay_response_sha_phrase() . $sha_string . $this->aps_config->get_apple_pay_response_sha_phrase();
				$hmac_key   = $this->aps_config->get_apple_pay_response_sha_phrase();
			}
		} else {
			if ( 'request' === $sign_type ) {
				$sha_string = $this->aps_config->get_request_sha_phrase() . $sha_string . $this->aps_config->get_request_sha_phrase();
				$hmac_key   = $this->aps_config->get_request_sha_phrase();
			} else {
				$sha_string = $this->aps_config->get_response_sha_phrase() . $sha_string . $this->aps_config->get_response_sha_phrase();
				$hmac_key   = $this->aps_config->get_response_sha_phrase();
			}
		}
		if ( in_array( $hash_algorithm, array( 'sha256', 'sha512' ), true ) ) {
			$signature = hash( $hash_algorithm, $sha_string );
		} elseif ( 'hmac256' === $hash_algorithm ) {
			$signature = hash_hmac( 'sha256', $sha_string, $hmac_key );
		} elseif ( 'hmac512' === $hash_algorithm ) {
			$signature = hash_hmac( 'sha512', $sha_string, $hmac_key );
		}
		return $signature;
	}

	/**
	 * Generate Random Key
	 *
	 * @return string
	 */
	public function generate_random_key() {
		return time() . wp_rand( 10 * 45, 100 * 98 );
	}

	/**
	 * Set flash message
	 * @param message string
	 *
	 * @return void
	 */
	public function set_flash_msg( $message, $status ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $status );
		}
	}

	/**
	 * Get customer ip
	 *
	 * @return string
	 */
	public function get_customer_ip() {
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Call Rest API
	 * @param post_data array
	 * @param gateway_url array
	 *
	 * @return result array
	 */
	public function call_rest_api( $post_data, $gateway_url ) {
		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		$useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:20.0) Gecko/20100101 Firefox/20.0';
		curl_setopt( $ch, CURLOPT_USERAGENT, $useragent );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json;charset=UTF-8',
			)
		);
		curl_setopt( $ch, CURLOPT_URL, $gateway_url );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_FAILONERROR, 1 );
		curl_setopt( $ch, CURLOPT_ENCODING, 'compress, gzip' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 ); // allow redirects
		//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 ); // The number of seconds to wait while trying to connect
		//curl_setopt($ch, CURLOPT_TIMEOUT, Yii::app()->params['apiCallTimeout']); // timeout in seconds
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $post_data ) );

		$response = curl_exec( $ch );

		curl_close( $ch );

		$array_result = json_decode( $response, true );

		if ( ! $response || empty( $array_result ) ) {
			return false;
		}
		return $array_result;
	}

	/**
	 * Call apple pay api
	 *
	 * @return json
	 */
	public function init_apple_pay_api( $apple_url ) {
		$ch                            = curl_init();
		$domain_name                   = $this->aps_config->get_apple_pay_domain_name();
		$apple_pay_display_name        = $this->aps_config->get_apple_pay_display_name();
		$production_key                = $this->aps_config->get_apple_pay_production_key();
		$apple_certificates            = get_option( 'aps_apple_pay_certificates' );
		$certificate_path              = plugin_dir_path( dirname( __FILE__ ) ) . 'certificates/' . $apple_certificates['apple_certificate_path_file'];
		$apple_pay_merchant_identifier = openssl_x509_parse( file_get_contents( $certificate_path ) )['subject']['UID'];
		$certificate_key               = plugin_dir_path( dirname( __FILE__ ) ) . 'certificates/' . $apple_certificates['apple_certificate_key_file'];
		$data                          = '{"merchantIdentifier":"' . $apple_pay_merchant_identifier . '", "domainName":"' . $domain_name . '", "displayName":"' . $apple_pay_display_name . '"}';

		curl_setopt( $ch, CURLOPT_URL, $apple_url );
		curl_setopt( $ch, CURLOPT_SSLCERT, $certificate_path );
		curl_setopt( $ch, CURLOPT_SSLKEY, $certificate_key );
		curl_setopt( $ch, CURLOPT_SSLKEYPASSWD, $production_key );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

		$response = curl_exec( $ch );
		curl_close( $ch );
		return $response;
	}

	/**
	 * Clear string
	 *
	 * @return string
	 */
	public function clean_string( $string ) {
		$string = str_replace( array( ' ', '-' ), array( '', '' ), $string );
		return preg_replace( '/[^A-Za-z0-9\-]/', '', $string );
	}

	/**
	 * Find valu order id by reference
	 *
	 * @return $order_id
	 */
	public function find_valu_order_by_reference( $reference_key ) {
		global $wpdb;
		$meta_key = 'valu_reference_id';
		$meta     = $wpdb->get_row( 'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key="' . $wpdb->escape( $meta_key ) . '" AND meta_value="' . $wpdb->escape( $reference_key ) . '"', ARRAY_A );
		if ( is_array( $meta ) && ! empty( $meta ) ) {
			return $meta['post_id'];
		} else {
			return false;
		}
	}

	/**
	 * Find token row by token id
	 *
	 * @return $token_row
	 */
	public function find_token_row( $token_name, $user_id = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'woocommerce_payment_tokens';
		$token_sql  = 'select * from ' . $table_name . ' where token = "' . $token_name . '" and user_id = ' . $user_id . ' and gateway_id = "' . APS_Constants::APS_PAYMENT_TYPE_CC . '"';
		return $wpdb->get_row( $token_sql, ARRAY_A );
	}

	public function getOrderRefundedAmoutTotal($order_id){
		global $wpdb;

		$refund_datetime = date("Y-m-d H:i:s", strtotime("-3 seconds", strtotime(current_time( 'mysql' ))));

		$sql = "SELECT SUM( postmeta.meta_value )
				FROM $wpdb->postmeta AS postmeta
				INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'shop_order_refund' AND posts.post_parent = ".$order_id." )
				WHERE postmeta.meta_key = '_refund_amount'
				AND postmeta.post_id = posts.ID and posts.post_date < '".$refund_datetime."'";
		$total = $wpdb->get_var($sql);
		return floatval( $total );
	}

	/**
	 * Find card type by card bin
	 *
	 * @return string
	 */
	public function find_card_type( $card_bin ) {
		$visa_regex       = '/^4[0-9]{0,15}$/m';
		$mastercard_regex = '/^5$|^5[0-5][0-9]{0,16}$/m';
		$amex_regex       = '/^3$|^3[47][0-9]{0,13}$/m';
		$mada_regex       = '/^' . $this->aps_config->get_mada_bins() . '/';
		$meeza_regex      = '/^' . $this->aps_config->get_meeza_bins() . '/';
		$card_type        = null;
		if ( preg_match( $mada_regex, $card_bin ) ) {
			$card_type = 'mada';
		} elseif ( preg_match( $meeza_regex, $card_bin ) ) {
			$card_type = 'meeza';
		} elseif ( preg_match( $visa_regex, $card_bin ) ) {
			$card_type = 'visa';
		} elseif ( preg_match( $mastercard_regex, $card_bin ) ) {
			$card_type = 'mastercard';
		} elseif ( preg_match( $amex_regex, $card_bin ) ) {
			$card_type = 'amex';
		}
		return $card_type;
	}

	/**
	 * Get data by fort id
	 */
	public function have_post_by_fort_id( $post_type, $fort_id, $post_status ) {
		$args     = array(
			'post_type'   => $post_type,
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => 'aps_fort_id',
					'value' => $fort_id,
				),
			),
			'post_status' => $post_status,
		);
		$get_data = new WP_Query( $args );
		if ( $get_data->have_posts() ) {
			return true;
		} else {
			return false;
		}
		wp_reset_postdata();
	}

	/**
	 * Captured amount history
	 */
	public function captured_amount_total( $order_id ) {
		global $post;
		$old_post                 = $post;
		$history                  = array();
		$get_capture_transactions = array(
			'post_type'   => 'aps_capture_trans',
			'post_parent' => $order_id,
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => 'aps_authorization_captured_amount',
					'compare' => 'EXISTS',
				),
			),
			'post_status' => 'capture',
			'orderby'     => 'ID',
			'order'       => 'DESC',
		);

		$get_transactions = new WP_Query( $get_capture_transactions );
		if ( $get_transactions->have_posts() ) {
			while ( $get_transactions->have_posts() ) {
				$get_transactions->the_post();
				$history[] = array(
					'date'   => get_the_date(),
					'amount' => get_post_meta( get_the_id(), 'aps_authorization_captured_amount', true ),
				);
			}
		}
		$post = $old_post;
		wp_reset_postdata();
		return array_sum( array_column( $history, 'amount' ) );
	}

	/**
	 * APS notify
	 *
	 * @param response_params array
	 * @param order_id int
	 *
	 * @return bool
	 */
	public function aps_status_checker( $order_id ) {
		//send host to host
		$this->aps_order->load_order( $order_id );
		$currency       = $this->aps_order->get_currency();
		$payment_method = $this->aps_order->get_payment_method();

		$signature_type = 'regular';
        $access_code = $this->aps_config->get_access_code();
        if($payment_method == APS_Constants::APS_PAYMENT_TYPE_APPLE_PAY){
            $access_code = $this->aps_config->get_apple_pay_access_code();
            $signature_type = 'apple_pay';
        }

        if($payment_method == APS_Constants::APS_PAYMENT_TYPE_VALU){
        	$valu_reference_id = get_post_meta( $order_id, 'valu_reference_id', true );
        	if(!empty($valu_reference_id)){
        		$this->log( 'APS aps_status_checker valu order_id#' . $order_id. 'valu_reference_id#'.$valu_reference_id );
        		$order_id = $valu_reference_id;
        	}
        }

		$command        = APS_Constants::APS_COMMAND_CHECK_STATUS;
		$gateway_params = array(
			'merchant_identifier' => $this->aps_config->get_merchant_identifier(),
			'access_code'         => $access_code,
			'merchant_reference'  => $order_id,
			'language'            => $this->aps_config->get_language(),
			'query_command'       => $command,
		);
		//generate request signature
		$signature                   = $this->generate_signature( $gateway_params, 'request', $signature_type );
		$gateway_params['signature'] = $signature;
		$gateway_url                 = $this->aps_config->get_gateway_url( 'api' );
		$this->log( 'APS aps_status_checker request \n\n' . wp_json_encode( $gateway_params, true ) );
		$response = $this->call_rest_api( $gateway_params, $gateway_url );
		$this->log( 'APS aps_status_checker response \n\n' . wp_json_encode( $response, true ) );
		return $response;
	}
}

