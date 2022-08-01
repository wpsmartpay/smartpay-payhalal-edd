<?php

/**
* Easy Digital Downloads - PayHalal Payment Gateway
*
* Plugin Name: Easy Digital Downloads - PayHalal Payment Gateway
* Plugin URI:  https://wpsmartpay.com/
* Description: PayHalal Payment Gateway for Easy Digital Downloads.
* Tags: edd, edd-payhalal, payhalal
* Version:     1.0.0
* Author:      WPSmartPay
* Author URI:  https://wpsmartpay.com/
* Text Domain: smartpay-payhalal-edd
*
* Requires PHP: 7.2.0
* Requires at least: 4.9
* Tested up to: 6.0.1
*/

define( 'SMARTPAY_PAYHALAL_EDD', '1.0.0' );

// Check EDD is installed and activated
// check the plugin is installed and activated after loading the plugin
add_action('plugins_loaded', function() {
    if (defined('EDD_VERSION') && !defined('SMARTPAY_PAYHALAL_EDD_VERSION')) {
        // Set Plugin version.
        if (!defined('SMARTPAY_PAYHALAL_EDD_VERSION')) {
            define('SMARTPAY_PAYHALAL_EDD_VERSION', '1.0.0');
        }

        // Define plugin store URL to maintain licence.
        if (!defined('SMARTPAY_PAYHALAL_EDD_PLUGIN_NAME')) {
            define('SMARTPAY_PAYHALAL_EDD_PLUGIN_NAME', 'PayHalal for Easy Digital Downloads');
        }

        // Main plugin file.
        if (!defined('SMARTPAY_PAYHALAL_EDD_PLUGIN_FILE')) {
            define('SMARTPAY_PAYHALAL_EDD_PLUGIN_FILE', __FILE__);
        }

        // Define plugin directory.
        if (!defined('SMARTPAY_PAYHALAL_EDD_PLUGIN_DIR')) {
            define('SMARTPAY_PAYHALAL_EDD_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }

        // Define plugin store URL to maintain licence.
        if (!defined('SMARTPAY_PAYHALAL_EDD_STORE_URL')) {
            define('SMARTPAY_PAYHALAL_EDD_STORE_URL', 'https://wpsmartpay.com');
        }

        add_action('init', function (){
            edd_debug_log($_POST);
        });
        add_action('init', 'process_callback');
        add_action('edd_gateway_payhalal', 'process_payment');
        add_action('edd_payhalal_cc_form', '__return_false');
        add_filter('edd_payment_gateways', 'register_gateway');
        add_filter('edd_settings_sections_gateways', 'gateway_section', 11, 1);
        add_filter('edd_settings_gateways', 'gateway_settings');

        // DO STAFFS
    } else{
        add_action('admin_notices', 'wp_smartpay_payhalal_edd_not_installed_notice');
    }
});

function wp_smartpay_payhalal_edd_not_installed_notice()
{
    echo __('<div class="error notice-warning"><p> EDD version = '. EDD_VERSION . 'You must install and active <code>Easy Digital Download</code> to use <code>PayHalal for Easy Digital Downloads</code> plugin.</p></div>',
        'smartpay-payhalal-edd');
}

function register_gateway(array $gateways = array()): array
{
    global $edd_options;

    $checkout_label = $edd_options['payhalal_edd_checkout_label'] ?? null;

    $gateways['payhalal'] = array(
        'admin_label'    => __('PayHalal', 'smartpay-payhalal-edd'),
        'checkout_label' => __($checkout_label ?? 'PayHalal', 'smartpay-payhalal-edd'),
        'supports'       => ['buy_now'],
    );

    return $gateways;
}

function gateway_section(array $sections = array()): array
{
    $sections['payhalal'] = __('PayHalal', 'smartpay-payhalal-edd');

    return $sections;
}

function gateway_settings(array $settings): array
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

    return array_merge($settings, ['payhalal' => $gateway_settings]);
}

function process_payment(array $purchase_data)
{
    $payment = edd_get_payment(76);
//    var_dump($payment->email);
//    die();
    global $edd_options;

    $is_test_mode = $edd_options['smartpay_payhalal_edd_enabled_test_mode'] ?? false;

    $public_key         =  $is_test_mode ? $edd_options['smartpay_payhalal_test_edd_public_key'] : $edd_options['smartpay_payhalal_live_edd_public_key'];

    $secret_key         = $is_test_mode ? $edd_options['smartpay_payhalal_test_edd_secret_key'] : $edd_options['smartpay_payhalal_live_edd_secret_key'];

    if (empty($public_key) || empty($secret_key)) {
        $log_message  = __('You must enter Public key and Secret for PayHalal in gateway settings.', 'smartpay-payhalal-edd');
        edd_set_error('credential_error', $log_message);
    }

    $payment_price = number_format($purchase_data['price'], 2);

    $payment_data = array(
        'price'         => $payment_price,
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => edd_get_currency(),
        'downloads'     => $purchase_data['downloads'],
        'cart_details'  => $purchase_data['cart_details'],
        'user_info'     => $purchase_data['user_info'],
        'status'        => 'pending',
    );

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
        $values["app_id"] = $public_key;
        $values["amount"] = smartpay_payhalal_remove_thousand_seperator($payment_price);
        $values["currency"] = "MYR";
        $values["product_description"] = $title;
        $values["order_id"] = $payment_id;
        $values["customer_email"] = $purchase_data['user_email'];
        $values["language"] = "en";
        $values["hash"] = ph_sha256($values,$secret_key);

        $form_url = 'https://api-testing.payhalal.my/pay';

        echo '<form id="payhalal" method="post" action="' . $form_url . '" >';
        foreach ($values as $key => $value) {
            echo '<input type="hidden" name="' . $key . '" value="' . $value . '" >';
        }
        echo '<center>
							<button type="submit">Please click here if you are not redirected within a few seconds</button>
							</center>';
        echo '</form>';

        echo '<script type="text/javascript">';
        echo '  document.getElementById("payhalal").submit();';
        echo '</script>';
    }
}

function ph_sha256( $data, $secret ) {
    $hash =
        hash('sha256',$secret.$data["amount"].$data["currency"].$data["product_description"]
            .$data["order_id"].
            $data["customer_name"].$data["customer_email"].$data["customer_phone"]);
    return $hash;
}

function smartpay_payhalal_remove_thousand_seperator(String $amount)
{
    $amount = str_replace(edd_get_option('thousands_separator', ','), '', $amount);

    $decimal_separator = edd_get_option('decimal_separator', '.');

    if ('.' != $decimal_separator) {
        $amount = str_replace($decimal_separator, '.', $amount);
    }

    return $amount;
}

function process_callback()
{
    if (isset($_GET['edd-listener']) && $_GET['edd-listener'] == 'payhalal') {
        $payment = edd_get_payment($_POST['order_id']);
        edd_debug_log($_POST);

        if ($_POST["status"] == "SUCCESS") {
            // Remove car
            $payment->update_status('publish');
        } elseif ($_POST["status"] == "FAIL") {
            $payment->update_status('failed');
        }
    } else {
        edd_add_note('Connection Error. Please Try Again');
    }
}