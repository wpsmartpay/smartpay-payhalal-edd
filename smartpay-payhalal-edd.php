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

        // boot The Application
        require __DIR__ . '/bootstrap.php';

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
