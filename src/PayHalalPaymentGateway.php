<?php
namespace SmartPayPayhalalEdd;

class PayHalalPaymentGateway {
	/**
	 * The single instance of the class.
	 * @var null
	 */
	private static $instance = null;

	private bool $isTestMode = false;

	private $publicKey = '';

	private $secret = '';

	public function __construct()
	{
		if (!function_exists('edd_is_gateway_active')) {
			return;
		}

		if (!$this->_checkApiKeys()) {
			// add credentials error notice
			add_action('admin_notices', function () {
				echo __(sprintf(
					'<div class="error">
                        <p><strong>PayHalal API key was not set or found yet!</strong> To get the PayHalal services on EDD, you must put your api key  <a href="%s"> Input your API key</a> or <a href="%s" target="_blank">Get your API key here</a></p>
                    </div>',
					admin_url('edit.php?post_type=download&page=edd-settings&tab=gateways&section=smartpay_payhalal'),
					'https://payhalal.my/merchant/developer'
				), 'smartpay-pro');
			});
		}

		if (!is_payhalal_currency_supported()) {
			smartpay_payhalal_edd_log(__('SmartPay-PayHalal: Construct gateway; Your currency does not supported by Paddle.', 'wp-smartpay-edd'));

			return;
		}

		$this->init_actions();
	}

	/**
	 * make single instance of the class
	 * @return PayHalalPaymentGateway|null
	 */
	public static function instance()
	{
		if (!isset(self::$instance) && !(self::$instance instanceof PayHalalPaymentGateway)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * initialize actions
	 * @return void
	 */
	public function init_actions()
	{
		add_action('init', [$this, 'process_callback']);
		add_action('edd_gateway_smartpay_payhalal', [$this, 'process_payment']);
		add_action('edd_smartpay_payhalal_cc_form', '__return_false');
		add_filter('edd_payment_gateways', [$this, 'register_gateway']);
		add_filter('edd_accepted_payment_icons', [$this, 'payment_icon']);
		add_filter('edd_settings_sections_gateways', [$this, 'gateway_section'], 9, 1);
		add_filter('edd_settings_gateways', [$this, 'gateway_settings']);
	}

	/**
	 * generate hash for payment
	 * @param
	 * $data
	 * @param $secret
	 * @return string
	 */
	private function ph_sha256( $data, $secret ) {
		$hash =
			hash('sha256',$secret.$data["amount"].$data["currency"].$data["product_description"]
			              .$data["order_id"].
			              $data["customer_name"].$data["customer_email"].$data["customer_phone"]);
		return $hash;
	}

	/**
	 * 'thousand' operator separator for amount
	 * @param String $amount
	 * @return array|string|string[]
	 */
	private function smartpay_payhalal_remove_thousand_seperator(String $amount)
	{
		$amount = str_replace(edd_get_option('thousands_separator', ','), '', $amount);

		$decimal_separator = edd_get_option('decimal_separator', '.');

		if ('.' != $decimal_separator) {
			$amount = str_replace($decimal_separator, '.', $amount);
		}

		return $amount;
	}


	/**
	 * call back url for payment
	 * update the payment status
	 * with callback payment status
	 * @return void
	 */
	public function process_callback()
	{
		if (isset($_GET['edd-listener']) && $_GET['edd-listener'] == 'payhalal') {
			$payment = edd_get_payment($_POST['order_id']);
			edd_debug_log($_POST);

			if ($_POST["status"] == "SUCCESS") {
				// Remove car
				$payment->update_status('publish');
				// add debug log
				$log_message = __(sprintf(
					'SmartPay-PayHalal: Payment #%s. Success.',
					$payment->ID
				), 'smartpay-payhalal-edd');
				smartpay_payhalal_edd_log($log_message);
			} elseif ($_POST["status"] == "FAIL") {
				$payment->update_status('failed');
				$log_message = __(sprintf(
					'SmartPay-PayHalal: Payment #%s. Failed.',
					$payment->ID
				), 'smartpay-payhalal-edd');
				smartpay_payhalal_edd_log($log_message);
			}
		} else {
			$log_message = __( 'SmartPay-PayHalal: Connection Error. Callback was not success.', 'smartpay-payhalal-edd');
			smartpay_payhalal_edd_log($log_message);
			edd_add_note('Connection Error. Please Try Again');
		}
	}

	/**
	 * process the payment
	 * @param array $purchase_data
	 * @return void
	 */
	public function process_payment(array $purchase_data): void {
		// If recurring payment does not proceed by PayHalal
		if (edd_recurring()->is_purchase_recurring($purchase_data)) {
			edd_set_error('smartpay_payhalal_recurring_error', __('PayHalal does not support recurring payment.', 'smartpay-payhalal-edd'));
			echo 'PayHalal does not support recurring payment at this moment by SmartPay PayHalal for EDD.';
			return;
		}

		$payment_price = number_format($purchase_data['price'], 2);

		$payment_data = array(
			'price'         => $payment_price,
			'customer_name'   => $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'],
			'date'          => $purchase_data['date'],
			'user_email'    => $purchase_data['user_email'],
			'purchase_key'  => $purchase_data['purchase_key'],
			'currency'      => edd_get_currency(),
			'downloads'     => $purchase_data['downloads'],
			'cart_details'  => $purchase_data['cart_details'],
			'user_info'     => $purchase_data['user_info'],
			'status'        => 'pending',
		);

		$customer_name = $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'];

		$payment_id = edd_insert_payment($payment_data);
		// If payment inserted.
		if ($payment_id) {
			// Make order title.
			if (edd_get_cart_quantity() == 1) {
				$item = reset($purchase_data['cart_details']);
				$item_option = edd_get_price_option_name($item['item_number']['id'] ?? null,
					$item['item_number']['options']['price_id'] ?? null);
				$title = mb_strimwidth($item['name'] . ($item_option ? ' - ' . $item_option : ''), 0, 30,
						'...') . sprintf(' (#%s)', $payment_id);
			} else {
				$title = sprintf('%s items (#%s)', edd_get_cart_quantity(), $payment_id);
			}

			edd_empty_cart();

			$values = array();
			// Fill all values with sample data below
			$values["app_id"] = $this->publicKey;
			$values["amount"] = $this->smartpay_payhalal_remove_thousand_seperator($payment_price);
			$values["currency"] = edd_get_currency();
			$values["product_description"] = $title;
			$values["order_id"] = $payment_id;
			$values["customer_name"] = $customer_name;
			$values["customer_email"] = $purchase_data['user_email'];
			$values["language"] = "en";
			$values["hash"] = $this->ph_sha256($values,$this->secret);

			// srt up the url for form submit
			$payment_url = $this->isTestMode ? 'https://api-testing.payhalal.my/pay' : 'https://api.payhalal.my/pay';

			echo '<form id="payhalal" method="post" action="' . $payment_url . '" >';
			foreach ($values as $key => $value) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '" >';
			}
			echo '<center>
							<button type="submit">Please click here if you are not redirected within a few seconds</button>
							</center>';
			echo '</form>';

			echo '<script type="text/javascript">';
			echo 'document.getElementById("payhalal").submit();';
			echo '</script>';
		}
	}

	/**
	 * register payhalal into EDD's gateway list
	 * @param array $gateways
	 * @return array
	 */
	public function register_gateway(array $gateways = array()): array
	{
		global $edd_options;

		$checkout_label = $edd_options['payhalal_edd_checkout_label'] ?? null;

		$gateways['smartpay_payhalal'] = array(
			'admin_label'    => __('PayHalal', 'smartpay-payhalal-edd'),
			'checkout_label' => __($checkout_label ?? 'PayHalal', 'smartpay-payhalal-edd'),
			'supports'       => ['buy_now'],
		);

		return $gateways;
	}

	/**
	 * Register the gateway icon
	 *
	 * @since 1.1.1
	 * @param array $icons
	 * @return array
	 * @access public
	 */
	public function payment_icon(array $icons = array()): array
	{
		global $edd_options;

		$checkout_icon = $edd_options['smartpay_payhalal_checkout_icon'] ?? plugins_url('', SMARTPAY_PAYHALAL_EDD_PLUGIN_FILE) . '/assets/images/payhalal-logo.png';

		$icons[$checkout_icon] = __('PayHalal', 'smartpay-payhalal-edd');
		return $icons;
	}

	/**
	 * add gateway to the Payment gateway sections
	 * @param array $sections
	 * @return array
	 */
	public function gateway_section(array $sections = array()): array
	{
		$sections['smartpay_payhalal'] = __('PayHalal', 'smartpay-payhalal-edd');

		return $sections;
	}

	/**
	 * add necessary settings for PayHalal gateway
	 * @param array $settings
	 * @return array
	 */
	public function gateway_settings(array $settings): array
	{
		global $edd_options;
		$gateway_settings = array(
			array(
				'id'    => 'smartpay_payhalal_edd_settings',
				'name'  => '<strong>' . __('PayHalal Gateway Settings', 'smartpay-payhalal-edd') . '</strong>',
				'desc'  => __('Configure your PayHalal Gateway Settings', 'smartpay-payhalal-edd'),
				'type'  => 'header'
			),
			array(
				'id'    => 'smartpay_payhalal_edd_enabled_test_mode',
				'name'   => __('Test Mode', 'smartpay-payhalal-edd'),
				'desc' => __('If you enable test mode, then you need to provide Public key and Secret Key from PayHalal test credentials.', 'smartpay-payhalal-edd'),
				'type'    => 'checkbox',
			),
			array(
				'id'    => 'smartpay_payhalal_test_edd_public_key',
				'name'  => __('Test Public Key', 'smartpay-payhalal-edd'),
				'desc'  => __('Enter your test PayHalal Public Key', 'smartpay-payhalal-edd'),
				'type'  => 'text'
			),
			array(
				'id'    => 'smartpay_payhalal_test_edd_secret_key',
				'name'  => __('Test Secret', 'smartpay-payhalal-edd'),
				'desc'  => __('Enter your test PayHalal Secret', 'smartpay-payhalal-edd'),
				'type'  => 'text'
			),

			array(
				'id'    => 'smartpay_payhalal_live_edd_public_key',
				'name'  => __('Live Public Key', 'smartpay-payhalal-edd'),
				'desc'  => __('Enter your live PayHalal Public Key', 'smartpay-payhalal-edd'),
				'type'  => 'text'
			),
			array(
				'id'    => 'smartpay_payhalal_live_edd_secret_key',
				'name'  => __('Live Secret', 'smartpay-payhalal-edd'),
				'desc'  => __('Enter your live PayHalal Secret', 'smartpay-payhalal-edd'),
				'type'  => 'text'
			),

			array(
				'id'    => 'smartpay_payhalal_edd_checkout_icon',
				'name'  => __('Gateway Icon', 'smartpay-payhalal-edd'),
				'desc'  => __('Gateway Icon URL must be including http:// or https://. If you don\'t set, it will use the default value.', 'smartpay-payhalal-edd'),
				'type'  => 'upload',
				'size'  => 'regular',
			),

			$paddle_webhook_description_text = __(
				sprintf(
					'<p>For PayHalal to function completely, you must configure your URL for Notification (Server to Server). Visit your <a href="%s" target="_blank">account dashboard</a> to configure them. Please add the URL below to all notification types. It doesn\'t work for localhost or local IP.</p><p><b>INS URL:</b> <code>%s</code></p>.',
					'https://payhalal.my/account/settings',
					home_url("index.php?edd-listener=payhalal")
				),
				'smartpay-payhalal-edd'
			),

			$_SERVER['REMOTE_ADDR'] == '127.0.0.1' ? $paddle_webhook_description_text .= __('<p><b>Warning!</b> It seems you are on the localhost.</p>', 'smartpay-payhalal-edd') : '',

			array(
				'id'    => 'smartpay_payhalal_edd_webhook_description',
				'type'  => 'descriptive_text',
				'name'  => __('URL for Notification (Server to Server)', 'smartpay-payhalal-edd'),
				'desc'  => $paddle_webhook_description_text,
			),

			array(
				'id'    => 'smartpay_payhalal_edd_success_link',
				'type'  => 'descriptive_text',
				'name'  => __('EDD success page URL For (URL after Purchase, Return URL, Cancel URL)', 'smartpay-payhalal-edd'),
				'desc'  => get_permalink($edd_options['success_page']),
			),
		);

		return array_merge($settings, ['smartpay_payhalal' => $gateway_settings]);
	}

	private function _checkApiKeys()
	{
		global $edd_options;

		$is_test_mode = $edd_options['smartpay_payhalal_edd_enabled_test_mode'] ?? false;

		if ($is_test_mode) {
			$this->isTestMode = true;
		}

		$public_key         =  $is_test_mode ? $edd_options['smartpay_payhalal_test_edd_public_key'] : $edd_options['smartpay_payhalal_live_edd_public_key'] ?? null;

		$secret_key         = $is_test_mode ? $edd_options['smartpay_payhalal_test_edd_secret_key'] : $edd_options['smartpay_payhalal_live_edd_secret_key'] ?? null;

		if (empty($public_key) || empty($secret_key)) {
			$log_message  = __('You must enter Public key and Secret for PayHalal in gateway settings.', 'smartpay-payhalal-edd');
			edd_set_error('credential_error', $log_message);
			return false;
		} else {
			$this->publicKey = $public_key;
			$this->secret = $secret_key;
			return true;
		}
	}

}
