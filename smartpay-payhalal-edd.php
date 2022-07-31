<?php

/**
* Easy Digital Downloads - PayHalal Payment Gateway
*
* Plugin Name: Easy Digital Downloads - PayHalal Payment Gateway
* Plugin URI:  https://wpsmartpay.com/
* Description: PayHalal Payment Gateway for Easy Digital Downloads.
* Tags: edd, edd-payhalal, payhalal
* Version:     1.3.5
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

    $checkout_label = $edd_options['smartpay_payhalal_edd_checkout_label'] ?? null;

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
            'id'    => 'smartpay_payhalal_edd_public_key',
            'name'  => __('Public Key', 'smartpay-payhalal-edd'),
            'desc'  => __('Enter your PayHalal Public Key', 'smartpay-payhalal-edd'),
            'type'  => 'text'
        ),
        array(
            'id'    => 'smartpay_payhalal_edd_secret_key',
            'name'  => __('Secret', 'smartpay-payhalal-edd'),
            'desc'  => __('Enter your PayHalal Secret', 'smartpay-payhalal-edd'),
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
    );

    return array_merge($settings, ['payhalal' => $gateway_settings]);
}

function process_payment(array $purchase_data)
{
    global $edd_options;

    $public_key         = $edd_options['smartpay_payhalal_edd_public_key']        ?? null;
    $secret_key         = $edd_options['smartpay_payhalal_edd_secret_key']        ?? null;

    if (empty($public_key) || empty($secret_key)) {
        $log_message  = __('You must enter Public key and Secret for PayHalal in gateway settings.', 'smartpay-payhalal-edd');
        smartpay_paddle_log($log_message);
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

    }

    function ph_sha256( $data, $secret ) {
        $hash =
            hash('sha256',$secret.$data["amount"].$data["currency"].$data["product_description"]
                .$data["order_id"].
                $data["customer_name"].$data["customer_email"].$data["customer_phone"]);
        return $hash;
    }

}

// App Key need to be secret, don't publish it online
//$ph_app_secret =
//    'secret-testing-5e2c0c2222d0a9dc032ced3f0482e5d3';
//$values = array();
//// Fill all values with sample data below
//$values["app_id"] =
//    "app-testing-78488f676f34dc5a39886219e0469f7e";
//$values["amount"] = 99.99;
//$values["currency"] = "MYR";
//$values["product_description"] = "My Product";
//$values["order_id"] = "2000";
//$values["customer_name"] = "Tom";
//$values["customer_email"] = "dev@mysite.my";
//$values["customer_phone"] = "65376254";
//$values["language"] = "en";
//$values["hash"] = ph_sha256($values,$ph_app_secret);
//?>
<!--<form name="phPayment" method="post" action="https://api-testing.payhalal.my/pay">-->
<!--    --><?php //foreach ($values as $name => $value) { ?>
<!--        <input type="hidden" name="--><?//=$name;?><!--" value="--><?//=$value;?><!--"/>-->
<!--    --><?php //} ?>
<!--    <input type="Submit" name="Submit" value="Pay with PayHalal"/>-->
<!--</form>-->
