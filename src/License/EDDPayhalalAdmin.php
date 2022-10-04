<?php
namespace SmartPayPayhalalEdd\License;

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('EDDPayhalalAdmin')) :
    final class EDDPayhalalAdmin {
        private static $instance = null;

        /**
         * Main EDDPayhalalAdmin Instance.
         * @return \SmartPayPayhalalEdd\License\EDDPayhalalAdmin|null
         */
        public static function instance()
        {
            if (!isset(self::$instance) && !(self::$instance instanceof EDDPayhalalAdmin)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Construct Main class SmartPay.
         * @access public
         */
        public function __construct()
        {
            add_action('admin_menu', array($this, 'smartpay_payhalal_admin_license_page'), 9);
            add_action('admin_init', array($this, 'init_smartpay_payhalal_updater'), 10);
        }

        /**
         * call the updater class
         * @return void
         */
        public function init_smartpay_payhalal_updater(): void
        {
            global $edd_options;
            $license_key = $edd_options['edd_payhalal_for_easy_digital_downloads_license_key'] ?? null;
            if ($license_key) {
                // Setup updater
                PayhalalUpdater::instance(88, $license_key);
            }
        }

        /**
         * check this license is active or not
         * this option is set when license is activated
         * @return false|mixed|null
         */
        public static function check_licence()
        {
            return get_option('edd_payhalal_for_easy_digital_downloads_license_active');
        }

        /**
         * Add the license page to the admin menu
         *
         * @return \EDD_License|null
         */
        function smartpay_payhalal_admin_license_page()
        {
            if (class_exists('EDD_License') && is_admin()) {
                return new \EDD_License(SMARTPAY_PAYHALAL_EDD_PLUGIN_FILE, SMARTPAY_PAYHALAL_EDD_PLUGIN_NAME,
                    SMARTPAY_PAYHALAL_EDD_VERSION, 'WPSmartPay', 'smartpay_payhalal_licence_key',
                    SMARTPAY_PAYHALAL_EDD_STORE_URL, 88); // change the id with your plugin id before release
            }
            return null;
        }
    }

endif;
