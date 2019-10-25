<?php
/**
 * Klarna Payment Gateway class file.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Payment_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Klarna_Payments extends WC_Payment_Gateway {

	/**
	 * Client token retrieved from Klarna when session is created.
	 *
	 * @var string
	 */
	public $client_token;

	/**
	 * Allowed currencies
	 *
	 * @var array
	 */
	public $allowed_currencies = array( 'USD', 'GBP', 'SEK', 'NOK', 'EUR', 'DKK', 'CHF' );

	/**
	 * Klarna Payments create session error.
	 *
	 * @var bool|WP_Error
	 */
	public $session_error = false;

	/**
	 * Klarna Payments iframe background.
	 *
	 * @var string
	 */
	public $background;

	/**
	 * Klarna Payments iframe button color.
	 *
	 * @var string
	 */
	public $color_button;

	/**
	 * Klarna Payments iframe button text color.
	 *
	 * @var string
	 */
	public $color_button_text;

	/**
	 * Klarna Payments iframe checkbox color.
	 *
	 * @var string
	 */
	public $color_checkbox;

	/**
	 * Klarna Payments iframe checkbox checkmark color.
	 *
	 * @var string
	 */
	public $color_checkbox_checkmark;

	/**
	 * Klarna Payments iframe header color.
	 *
	 * @var string
	 */
	public $color_header;

	/**
	 * Klarna Payments iframe link color.
	 *
	 * @var string
	 */
	public $color_link;

	/**
	 * Klarna Payments iframe border color.
	 *
	 * @var string
	 */
	public $color_border;

	/**
	 * Klarna Payments iframe selected border color.
	 *
	 * @var string
	 */
	public $color_border_selected;

	/**
	 * Klarna Payments iframe text color.
	 *
	 * @var string
	 */
	public $color_text;

	/**
	 * Klarna Payments iframe details color.
	 *
	 * @var string
	 */
	public $color_details;

	/**
	 * Klarna Payments iframe secondary text color.
	 *
	 * @var string
	 */
	public $color_text_secondary;

	/**
	 * Klarna Payments radius border.
	 *
	 * @var string
	 */
	public $radius_border;

	/**
	 * Float What is Klarna? link in checkout page.
	 *
	 * @var string
	 */
	public $float_what_is_klarna;

	/**
	 * Hide what is Klarna? link in checkout page.
	 *
	 * @var string
	 */
	public $hide_what_is_klarna;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'klarna_payments';
		$this->method_title       = __( 'Klarna Payments', 'klarna-payments-for-woocommerce' );
		$this->method_description = __( 'Get the flexibility to pay over time with Klarna!', 'klarna-payments-for-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = apply_filters( 'wc_klarna_payments_supports', array( 'products' ) ); // Make this filterable.

		$base_location      = wc_get_base_location();
		$this->shop_country = $base_location['country'];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title   = $this->get_option( 'title' );
		$this->enabled = $this->get_option( 'enabled' );

		// Iframe options.
		$this->background               = $this->get_option( 'background' );
		$this->color_button             = $this->get_option( 'color_button' );
		$this->color_button_text        = $this->get_option( 'color_button_text' );
		$this->color_checkbox           = $this->get_option( 'color_checkbox' );
		$this->color_checkbox_checkmark = $this->get_option( 'color_checkbox_checkmark' );
		$this->color_header             = $this->get_option( 'color_header' );
		$this->color_link               = $this->get_option( 'color_link' );
		$this->color_border             = $this->get_option( 'color_border' );
		$this->color_border_selected    = $this->get_option( 'color_border_selected' );
		$this->color_text               = $this->get_option( 'color_text' );
		$this->color_details            = $this->get_option( 'color_details' );
		$this->color_text_secondary     = $this->get_option( 'color_text_secondary' );
		$this->radius_border            = $this->get_option( 'radius_border' );

		// What is Klarna link.
		$this->hide_what_is_klarna  = 'yes' === $this->get_option( 'hide_what_is_klarna' );
		$this->float_what_is_klarna = 'yes' === $this->get_option( 'float_what_is_klarna' );

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'woocommerce_api_wc_gateway_klarna_payments', array( $this, 'notification_listener' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );
		add_filter( 'wc_klarna_payments_create_session_args', array( $this, 'iframe_options' ) );
		add_filter( 'wc_get_template', array( $this, 'override_kp_payment_option' ), 10, 3 );

		if ( '' !== $this->background ) {
			add_action( 'wp_head', array( $this, 'iframe_background' ) );
		}
		add_action( 'klarna_payments_template', array( $this, 'klarna_payments_session' ) );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = include WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/includes/kp-form-fields.php';
	}

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon_width = '39';
		$icon_html  = '<img src="' . $this->icon . '" alt="Klarna" style="max-width:' . $icon_width . 'px"/>';
		if ( ! $this->hide_what_is_klarna ) {
			// If default WooCommerce CSS is used, float "What is Klarna link like PayPal does it".
			if ( $this->float_what_is_klarna ) {
				$link_style = 'style="float: right; line-height: 52px; font-size: .83em;"';
			} else {
				$link_style = '';
			}

			$what_is_klarna_text = 'What is Klarna?';

			if ( 'us' === strtolower( $this->klarna_country ) ) {
				$link_url = 'https://www.klarna.com/us/business/what-is-klarna';
			} elseif ( 'at' === strtolower( $this->klarna_country ) || 'de' === strtolower( $this->klarna_country ) ) {
				$link_url = 'https://www.klarna.com';
			} else {
				$link_url = 'https://www.klarna.com/uk/what-we-do';
			}

			// Change text for Germany.
			$locale = get_locale();
			if ( stripos( $locale, 'de' ) !== false ) {
				$what_is_klarna_text = 'Was ist Klarna?';
			}
			$icon_html .= '<a ' . $link_style . ' href="' . $link_url . '" onclick="window.open(\'' . $link_url . '\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . $what_is_klarna_text . '</a>';
		}
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Add sidebar to the settings page.
	 */
	public function admin_options() {
		ob_start();
		parent::admin_options();
		$parent_options = ob_get_contents();
		ob_end_clean();
		KP_Banners::settings_sidebar( $parent_options );
	}

	/**
	 * Check country and currency
	 *
	 * Fired before create session and update session, and inside is_available.
	 */
	public function country_currency_check() {
		// Check if allowed currency.
		if ( ! in_array( get_woocommerce_currency(), $this->allowed_currencies, true ) ) {
			$this->unset_session_values();

			return new WP_Error( 'currency', 'Currency not allowed for Klarna Payments' );
		}

		// If US, check if USD used.
		if ( 'USD' === get_woocommerce_currency() ) {
			if ( 'US' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'USD must be used for US purchases' );
			}
		}

		// If GB, check if GBP used.
		if ( 'GBP' === get_woocommerce_currency() ) {
			if ( 'GB' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'GBP must be used for GB purchases' );
			}
		}

		// If SE, check if SEK used.
		if ( 'SEK' === get_woocommerce_currency() ) {
			if ( 'SE' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'SEK must be used for SE purchases' );
			}
		}

		// If NO, check if NOK used.
		if ( 'NOK' === get_woocommerce_currency() ) {
			if ( 'NO' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'NOK must be used for NO purchases' );
			}
		}

		// If DK, check if DKK used.
		if ( 'DKK' === get_woocommerce_currency() ) {
			if ( 'DK' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'DKK must be used for DK purchases' );
			}
		}

		// If CH, check if CHF used.
		if ( 'CHF' === get_woocommerce_currency() ) {
			if ( 'CH' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'CHF must be used for CH purchases' );
			}
		}

		// If EUR country, check if EUR used.
		if ( 'EUR' === get_woocommerce_currency() ) {
			if ( ! in_array( $this->klarna_country, array( 'AT', 'DE', 'NL', 'FI' ), true ) ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'EUR must be used for AT, DE, NL, FI purchases' );
			}
		}

		return true;
	}

	/**
	 * Check if Klarna Payments should be available
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return false;
		}

		$this->set_credentials();

		// Check credentials.
		if ( '' === $this->merchant_id || '' === $this->shared_secret ) {
			return false;
		}

		// Check country and currency.
		if ( is_wp_error( $this->country_currency_check() ) ) {
			return false;
		}

		// Check if there was a session error.
		if ( is_wp_error( $this->session_error ) ) {
			return false;
		}

		if ( '' === $this->merchant_id || '' === $this->shared_secret ) {
			return false;
		}

		return true;
	}

	/**
	 * Override checkout form template if Klarna Checkout is the selected payment method.
	 *
	 * @param string $located Target template file location.
	 * @param string $template_name The name of the template.
	 * @param array  $args Arguments for the template.
	 * @return string
	 */
	public function override_kp_payment_option( $located, $template_name, $args ) {
		if ( is_checkout() ) {
			if ( 'checkout/payment-method.php' === $template_name ) {
				if ( 'klarna_payments' === $args['gateway']->id ) {
					$located = untrailingslashit( plugin_dir_path( __DIR__ ) ) . '/templates/klarna-payments-categories.php';
				}
			}
		}

		return $located;
	}

	/**
	 * Create Klarna Payments session request.
	 *
	 * @param string $request_url  Klarna request URL.
	 * @param array  $request_args Klarna request arguments.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function create_session_request( $request_url, $request_args ) {
		// Make it filterable.
		$request_args = apply_filters( 'wc_klarna_payments_create_session_args', $request_args );

		$response      = wp_safe_remote_post( $request_url, $request_args );
		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		$session_id    = isset( $response_body['session_id'] ) ? $response_body['session_id'] : null;

		// Log the request.
		$log = WC_Klarna_Payments::format_log( $session_id, 'POST', 'Klarna Payments create session request.', $request_args, $response_body, $code );
		WC_Klarna_Payments::log( $log );

		if ( is_array( $response ) ) {
			if ( 200 === $code ) {
				$decoded = json_decode( $response['body'] );

				return $decoded;
			} else {
				return new WP_Error( $code, $response['body'] );
			}
		} else {
			return new WP_Error( 'kp_create_session', 'Could not create Klarna Payments session.' );
		}
	}

	/**
	 * Update Klarna Payments session.
	 *
	 * @param string $request_url  Klarna request URL.
	 * @param array  $request_args Klarna request arguments.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function update_session_request( $request_url, $request_args ) {
		// Make it filterable.
		$request_args = apply_filters( 'wc_klarna_payments_update_session_args', $request_args );

		$response      = wp_safe_remote_post( $request_url, $request_args );
		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		WC_Klarna_Payments::log( 'Klarna Payments update session request. Status Code: ' . $code . ' Response: ' . stripslashes_deep( wp_json_encode( $response_body ) ) );

		if ( is_array( $response ) ) {
			if ( 204 === $code ) {
				return true;
			} else {
				return new WP_Error( $code, $response['body'] );
			}
		} else {
			return new WP_Error( 'kp_update_session', 'Could not update Klarna Payments session.' );
		}
	}

	/**
	 * Adds Klarna Payments container to checkout page.
	 */
	public function payment_fields() {
		echo esc_html( '<div id="' . $this->id . '_container" class="klarna_payments_container" data-payment_method_category="' . $this->id . '"></div>' );
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_register_script(
			'klarna_payments',
			plugins_url( 'assets/js/klarna-payments.js', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array( 'jquery' ),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);

		$default_kp_checkout_fields = array(
			'billing_given_name'       => '#billing_first_name',
			'billing_family_name'      => '#billing_last_name',
			'billing_email'            => '#billing_email',
			'billing_phone'            => '#billing_phone',
			'billing_country'          => '#billing_country',
			'billing_region'           => '#billing_state',
			'billing_postal_code'      => '#billing_postcode',
			'billing_city'             => '#billing_city',
			'billing_street_address'   => '#billing_address_1',
			'billing_street_address2'  => '#billing_address_2',
			'billing_company'          => '#billing_company',
			'shipping_given_name'      => '#shipping_first_name',
			'shipping_family_name'     => '#shipping_last_name',
			'shipping_country'         => '#shipping_country',
			'shipping_region'          => '#shipping_state',
			'shipping_postal_code'     => '#shipping_postcode',
			'shipping_city'            => '#shipping_city',
			'shipping_street_address'  => '#shipping_address_1',
			'shipping_street_address2' => '#shipping_address_2',
		);

		// Localize the script.
		$klarna_payments_params                                    = array();
		$klarna_payments_params['testmode']                        = $this->testmode;
		$klarna_payments_params['default_checkout_fields']         = apply_filters( 'wc_klarna_payments_default_checkout_fields', $default_kp_checkout_fields );
		$klarna_payments_params['customer_type']                   = $this->get_option( 'customer_type' );
		$klarna_payments_params['remove_postcode_spaces']          = ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? 'yes' : 'no';
		$klarna_payments_params['failed_field_validation_text']    = __( ' is a required field.', 'woocommerce' );
		$klarna_payments_params['failed_checkbox_validation_text'] = __( 'Make sure all required checkboxes are checked.', 'klarna-payments-for-woocommerce' );
		$klarna_payments_params['ajaxurl']                         = admin_url( 'admin-ajax.php' );
		wp_localize_script( 'klarna_payments', 'klarna_payments_params', $klarna_payments_params );
		wp_enqueue_script( 'klarna_payments' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Admin page hook.
	 *
	 * @hook admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		if ( ! isset( $_GET['section'] ) || 'klarna_payments' !== $_GET['section'] ) { // phpcs:ignore
			return;
		}

		wp_enqueue_script(
			'klarna_payments_admin',
			plugins_url( 'assets/js/klarna-payments-admin.js', WC_KLARNA_PAYMENTS_MAIN_FILE )
		);
	}

	/**
	 * Place Klarna Payments order, after authorization.
	 *
	 * Uses authorization token to place the order.
	 *
	 * @TODO: Set customer payment method as KP.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_payment( $order_id ) {

		$response = array(
			'order_id'  => $order_id,
			'addresses' => $this->get_address_from_order( $order_id ),
			'time'      => time(),
		);

		update_post_meta( $order_id, '_wc_klarna_environment', $this->environment );
		update_post_meta( $order_id, '_wc_klarna_country', $this->klarna_country );

		// Add #kp hash to checkout url so we can do a finalize call to Klarna.
		return array(
			'result'   => 'success',
			'redirect' => wc_get_checkout_url() . '#kp=' . base64_encode( wp_json_encode( $response ) ),
		);
	}

	/**
	 * Process Klarna Payments response.
	 *
	 * @param array    $response Klarna API response.
	 * @param WC_Order $order WooCommerce order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_klarna_response( $response, $order ) {
		// Default the return array to failure.
		$return_val = array(
			'result'   => 'failure',
			'redirect' => '',
		);

		// Process the response.
		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );

			$fraud_status = $decoded->fraud_status;

			switch ( $fraud_status ) {
				case 'ACCEPTED':
					$return_val = $this->process_accepted( $order, $decoded );
					break;
				case 'PENDING':
					$return_val = $this->process_pending( $order, $decoded );
					break;
				case 'REJECTED':
					$return_val = $this->process_rejected( $order, $decoded );
					break;
			}

			update_post_meta( $order->get_id(), '_wc_klarna_environment', $this->environment );
			update_post_meta( $order->get_id(), '_wc_klarna_country', $this->klarna_country );

			$this->unset_session_values();
		} else {
			// Log error message.
			WC_Klarna_Payments::log( 'process_payment error response: ' . stripslashes_deep( wp_json_encode( $response ) ) );

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			} else {
				$error_message = 'Klarna error failed. ' . $response['response']['code'] . ' - ' . $response['response']['message'] . '.';
			}

			wc_add_notice( $error_message, 'error' );
			WC()->session->reload_checkout = true;
		} // End if().

		return $return_val;
	}

	/**
	 * Process accepted Klarna Payments order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param stdClass $decoded Klarna order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_accepted( $order, $decoded ) {
		$order->payment_complete( $decoded['order_id'] );
		$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded['order_id'] );
		update_post_meta( $order->get_id(), '_wc_klarna_order_id', $decoded['order_id'], true );

		do_action( 'wc_klarna_payments_accepted', $order->get_id(), $decoded );
		do_action( 'wc_klarna_accepted', $order->get_id(), $decoded );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Process pending Klarna Payments order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param stdClass $decoded Klarna order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_pending( $order, $decoded ) {
		$order->update_status( 'on-hold', 'Klarna order is under review, order ID: ' . $decoded['order_id'] );
		update_post_meta( $order->get_id(), '_wc_klarna_order_id', $decoded['order_id'], true );
		update_post_meta( $order->get_id(), '_transaction_id', $decoded['order_id'], true );

		do_action( 'wc_klarna_payments_pending', $order->get_id(), $decoded );
		do_action( 'wc_klarna_pending', $order->get_id(), $decoded );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Process rejected Klarna Payments order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param stdClass $decoded Klarna order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_rejected( $order, $decoded ) {
		$order->update_status( 'on-hold', 'Klarna order was rejected.' );

		do_action( 'wc_klarna_payments_rejected', $order->get_id(), $decoded );
		do_action( 'wc_klarna_rejected', $order->get_id(), $decoded );

		return array(
			'result'   => 'failure',
			'redirect' => '',
			'messages' => '<div class="woocommerce-error">Klarna payment rejected</div>',
		);
	}

	/**
	 * Notification listener for Pending orders. This plugin doesn't handle pending orders, but it does allow Klarna
	 * Order Management plugin to hook in and process pending orders.
	 *
	 * @link https://developers.klarna.com/en/us/kco-v3/pending-orders
	 *
	 * @hook woocommerce_api_wc_gateway_klarna_payments
	 */
	public function notification_listener() {
		do_action( 'wc_klarna_notification_listener' );
	}

	/**
	 * This plugin doesn't handle order management, but it allows Klarna Order Management plugin to process refunds
	 * and then return true or false.
	 *
	 * @param int      $order_id WooCommerce order ID.
	 * @param null|int $amount Refund amount.
	 * @param string   $reason Reason for refund.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return apply_filters( 'wc_klarna_payments_process_refund', false, $order_id, $amount, $reason );
	}

	/**
	 * Add display options to create session request.
	 *
	 * @param array $request_args Klarna create session request arguments.
	 *
	 * @return mixed
	 *
	 * @hook wc_klarna_payments_create_session_args
	 */
	public function iframe_options( $request_args ) {
		$options = array();

		if ( '' !== $this->color_button ) {
			$options['color_button'] = $this->color_button;
		}

		if ( '' !== $this->color_button_text ) {
			$options['color_button_text'] = $this->color_button_text;
		}

		if ( '' !== $this->color_checkbox ) {
			$options['color_checkbox'] = $this->color_checkbox;
		}

		if ( '' !== $this->color_checkbox_checkmark ) {
			$options['color_checkbox_checkmark'] = $this->color_checkbox_checkmark;
		}

		if ( '' !== $this->color_header ) {
			$options['color_header'] = $this->color_header;
		}

		if ( '' !== $this->color_link ) {
			$options['color_link'] = $this->color_link;
		}

		if ( '' !== $this->color_border ) {
			$options['color_border'] = $this->color_border;
		}

		if ( '' !== $this->color_border_selected ) {
			$options['color_border_selected'] = $this->color_border_selected;
		}

		if ( '' !== $this->color_text ) {
			$options['color_text'] = $this->color_text;
		}

		if ( '' !== $this->color_details ) {
			$options['color_details'] = $this->color_details;
		}

		if ( '' !== $this->color_text_secondary ) {
			$options['color_text_secondary'] = $this->color_text_secondary;
		}

		if ( '' !== $this->radius_border ) {
			$options['radius_border'] = $this->radius_border . 'px';
		}

		if ( ! empty( $options ) ) {
			$decoded_body          = json_decode( $request_args['body'] );
			$decoded_body->options = $options;

			$request_args['body'] = wp_json_encode( $decoded_body );
		}

		return $request_args;
	}

	/**
	 * Add <head> CSS for Klarna Payments iframe background.
	 *
	 * @hook wp_head
	 */
	public function iframe_background() {
		if ( '' !== $this->background ) {
			echo esc_html( "<style type='text/css'>div#klarna_container { background: $this->background !important; padding: 10px; } div#klarna_container:empty { padding: 0; } </style>" );
		}
	}

	/**
	 * Unsets Klarna Payments sessions values.
	 */
	public function unset_session_values() {
		WC()->session->__unset( 'klarna_payments_session_id' );
		WC()->session->__unset( 'klarna_payments_client_token' );
		WC()->session->__unset( 'klarna_payments_session_country' );
		WC()->session->__unset( 'klarna_payments_categories' );
	}

	/**
	 * Adds can't edit address notice to KP EU orders.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 */
	public function address_notice( $order ) {
		if ( $this->id === $order->get_payment_method() ) {
			echo '<div style="margin: 10px 0; padding: 10px; border: 1px solid #B33A3A; font-size: 12px">Order address should not be changed and any changes you make will not be reflected in Klarna system.</div>';
		}
	}

	/**
	 * Set payment method title for order.
	 *
	 * @param array $order WooCommerce order.
	 * @param array $klarna_place_order_response The Klarna place order response.
	 * @return void
	 * @todo Change it so that it dynamically gets information from Klarna.
	 */
	public function set_payment_method_title( $order, $klarna_place_order_response ) {
		$title         = $order->get_payment_method_title();
		$klarna_method = $klarna_place_order_response['authorized_payment_method']['type'];
		switch ( $klarna_method ) {
			case 'invoice':
				$klarna_method = 'Pay Later';
				break;
			case 'base_account':
				$klarna_method = 'Slice It';
				break;
			case 'direct_debit':
				$klarna_method = 'Direct Debit';
				break;
			default:
				$klarna_method = null;
		}
		if ( null !== $klarna_method ) {
			$new_title = $title . ' - ' . $klarna_method;
			$order->set_payment_method_title( $new_title );
		}
	}
}

/**
 * Adds the Klarna Payments Gateway to WooCommerce
 *
 * @param  array $methods All registered payment methods.
 * @return array $methods All registered payment methods.
 */
function add_kp_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Klarna_Payments';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_kp_gateway' );
