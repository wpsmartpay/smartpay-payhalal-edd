<?php
namespace SmartPayPayhalalEdd\License;

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('PayhalalUpdater')):

class PayhalalUpdater {

    private static $instance = null;
    private static $api_data = array();
    private static $plugin_name = '';
    private static $plugin_slug = '';
    private static $wp_override = false;
    private static $cache_key = '';
    private static $beta_update = false;

    /**
     * Get active instance
     *
     * @return self::$instance The one true PayhalalUpdater
     */
    public static function instance($product_id, $license_key)
    {
        if (!isset(self::$instance) && !(self::$instance instanceof PayhalalUpdater)) {
            self::$instance = new self($product_id, $license_key);
        }

        return self::$instance;
    }

    /**
     * Constructor
     *
     * @access private
     */
    public function __construct($product_id, $license_key)
    {
        global $edd_plugin_data;

        self::$api_data = array(
            'version' => SMARTPAY_PAYHALAL_EDD_VERSION,    // Current version number
            'license' => $license_key,            // License key (used get_option above to retrieve from DB)
            'item_id' => $product_id,            // ID of the product
            'author'  => 'WPSmartPay',            // Author of this plugin
            'beta'    => self::$beta_update,
        );

        self::$plugin_name    = plugin_basename(SMARTPAY_PAYHALAL_EDD_PLUGIN_FILE);

        self::$plugin_slug    = basename(SMARTPAY_PAYHALAL_EDD_PLUGIN_FILE, '.php');

        self::$cache_key    = 'edd_sl_' . md5(serialize(self::$plugin_slug . $license_key . self::$beta_update));

        $edd_plugin_data[self::$plugin_slug] = self::$api_data;

        /**
         * Fires after the $edd_plugin_data is setup.
         *
         * @since 0.1
         * @param array $edd_plugin_data Array of EDD SL plugin data.
         */
        do_action('post_edd_sl_plugin_updater_setup', $edd_plugin_data);

        // Set up hooks.
        $this->init();
    }

    /**
     * Set up WordPress filters to hook into WP's update process.
     *
     * @uses add_filter()
     *
     * @access private
     * @return void
     */
    public function init()
    {
        // Add filters
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugins_api_filter'), 10, 3);

        // Remove actions
        remove_action('after_plugin_row_' . self::$plugin_name, 'wp_plugin_update_row');

        // Add actions
        add_action('admin_init', array($this, 'show_changelog'));
        add_action('after_plugin_row_' . self::$plugin_name, array($this, 'show_update_notification'), 10, 2);
    }

    /**
     * Check for Updates at the defined API endpoint and modify the update array.
     *
     * This function dives into the update API just when WordPress creates its update array,
     * then adds a custom API call and injects the custom plugin data retrieved from the API.
     * It is reassembled from parts of the native WordPress plugin update code.
     * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
     *
     * @param $_transient_data
     *
     * @return array Modified update array with custom plugin data.
     * @uses api_request()
     *
     * @access public
     */
    public function check_update($_transient_data)
    {
        global $pagenow;

        if (!is_object($_transient_data)) {
            $_transient_data = new \stdClass;
        }

        if ('plugins.php' == $pagenow && is_multisite()) {
            return $_transient_data;
        }

        if (!empty($_transient_data->response) && !empty($_transient_data->response[self::$plugin_name]) && false === self::$wp_override) {
            return $_transient_data;
        }

        $version_info = $this->get_cached_version_info();

        if (false === $version_info) {
            $version_info = $this->api_request('plugin_latest_version', array('slug' => self::$plugin_slug, 'beta' => self::$beta_update));

            $this->set_version_info_cache($version_info);
        }

        if (false !== $version_info && is_object($version_info) && isset($version_info->new_version)) {

            if (version_compare(SMARTPAY_PAYHALAL_EDD_VERSION, $version_info->new_version, '<')) {

                $_transient_data->response[self::$plugin_name] = $version_info;

                // Make sure the plugin property is set to the plugin's name/location.
                $_transient_data->response[self::$plugin_name]->plugin = self::$plugin_name;
            }

            $_transient_data->last_checked = time();
            $_transient_data->checked[self::$plugin_name] = SMARTPAY_PAYHALAL_EDD_VERSION;
        }

        return $_transient_data;
    }

    /**
     * @param  string  $file
     * @param  array  $plugin
     *
     * @return void
     */
    public function show_update_notification(string $file, array $plugin): void
    {
        if (is_network_admin()) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            return;
        }

        if (!is_multisite()) {
            return;
        }

        if (self::$plugin_name != $file) {
            return;
        }

        // Remove our filter on the site transient
        remove_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'), 10);

        $update_cache = get_site_transient('update_plugins');

        $update_cache = is_object($update_cache) ? $update_cache : new \stdClass();

        if (empty($update_cache->response) || empty($update_cache->response[self::$plugin_name])) {

            $version_info = $this->get_cached_version_info();

            if (false === $version_info) {
                $version_info = $this->api_request('plugin_latest_version', array('slug' => self::$plugin_slug, 'beta' => self::$beta_update));

                // Since we disabled our filter for the transient, we aren't running our object conversion on banners, sections, or icons. Do this now:
                if (isset($version_info->banners) && !is_array($version_info->banners)) {
                    $version_info->banners = $this->convert_object_to_array($version_info->banners);
                }

                if (isset($version_info->sections) && !is_array($version_info->sections)) {
                    $version_info->sections = $this->convert_object_to_array($version_info->sections);
                }

                if (isset($version_info->icons) && !is_array($version_info->icons)) {
                    $version_info->icons = $this->convert_object_to_array($version_info->icons);
                }

                $this->set_version_info_cache($version_info);
            }

            if (!is_object($version_info)) {
                return;
            }

            if (version_compare(SMARTPAY_EDD_VERSION, $version_info->new_version, '<')) {

                $update_cache->response[self::$plugin_name] = $version_info;
            }

            $update_cache->last_checked = time();
            $update_cache->checked[self::$plugin_name] = SMARTPAY_PAYHALAL_EDD_VERSION;

            set_site_transient('update_plugins', $update_cache);
        } else {
            $version_info = $update_cache->response[self::$plugin_name];
        }

        // Restore our filter
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));

        if (!empty($update_cache->response[self::$plugin_name]) && version_compare(SMARTPAY_PAYHALAL_EDD_VERSION, $version_info->new_version, '<')) {

            // build a plugin list row, with update notification
            $wp_list_table = _get_list_table('WP_Plugins_List_Table');
            # <tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange">
            echo '<tr class="plugin-update-tr" id="' . self::$plugin_slug . '-update" data-slug="' . self::$plugin_slug . '" data-plugin="' . self::$plugin_slug . '/' . $file . '">';
            echo '<td colspan="3" class="plugin-update colspanchange">';
            echo '<div class="update-message notice inline notice-warning notice-alt">';

            $changelog_link = self_admin_url('index.php?edd_sl_action=view_plugin_changelog&plugin=' . self::$plugin_name . '&slug=' . self::$plugin_slug . '&TB_iframe=true&width=772&height=911');

            if (empty($version_info->download_link)) {
                printf(
                    __('There is a new version of %1$s available. %2$sView version %3$s details%4$s.', 'easy-digital-downloads'),
                    esc_html($version_info->name),
                    '<a target="_blank" class="thickbox" href="' . esc_url($changelog_link) . '">',
                    esc_html($version_info->new_version),
                    '</a>'
                );
            } else {
                printf(
                    __('There is a new version of %1$s available. %2$sView version %3$s details%4$s or %5$supdate now%6$s.', 'easy-digital-downloads'),
                    esc_html($version_info->name),
                    '<a target="_blank" class="thickbox" href="' . esc_url($changelog_link) . '">',
                    esc_html($version_info->new_version),
                    '</a>',
                    '<a href="' . esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . self::$plugin_name, 'upgrade-plugin_' . self::$plugin_name)) . '">',
                    '</a>'
                );
            }

            do_action("in_plugin_update_message-{$file}", $plugin, $version_info);

            echo '</div></td></tr>';
        }
    }

    /**
     * @param $_data
     * @param $_action
     * @param $_args
     *
     * @return array|mixed|\WP_Error|null
     */
    public function plugins_api_filter($_data, $_action = '', $_args = null)
    {
        if ($_action != 'plugin_information') {
            return $_data;
        }

        if (!isset($_args->slug) || ($_args->slug != self::$plugin_slug)) {
            return $_data;
        }

        $to_send = array(
            'slug'   => self::$plugin_slug,
            'is_ssl' => is_ssl(),
            'fields' => array(
                'banners' => array(),
                'reviews' => false,
                'icons'   => array(),
            )
        );

        $cache_key = 'edd_api_request_' . md5(serialize(self::$plugin_slug . self::$api_data['license'] . self::$beta_update));

        // Get the transient where we store the api request for this plugin for 24 hours
        $edd_api_request_transient = $this->get_cached_version_info($cache_key);

        //If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
        if (empty($edd_api_request_transient)) {
            $api_response = $this->api_request('plugin_information', $to_send);

            // Expires in 3 hours
            $this->set_version_info_cache($api_response, $cache_key);

            if (false !== $api_response) {
                $_data = $api_response;
            }
        } else {
            $_data = $edd_api_request_transient;
        }

        // Convert sections into an associative array, since we're getting an object, but Core expects an array.
        if (isset($_data->sections) && !is_array($_data->sections)) {
            $_data->sections = $this->convert_object_to_array($_data->sections);
        }

        // Convert banners into an associative array, since we're getting an object, but Core expects an array.
        if (isset($_data->banners) && !is_array($_data->banners)) {
            $_data->banners = $this->convert_object_to_array($_data->banners);
        }

        // Convert icons into an associative array, since we're getting an object, but Core expects an array.
        if (isset($_data->icons) && !is_array($_data->icons)) {
            $_data->icons = $this->convert_object_to_array($_data->icons);
        }

        if (!isset($_data->plugin)) {
            $_data->plugin = self::$plugin_name;
        }

        return $_data;
    }

    /**
     * @param $data
     *
     * @return array
     */
    private function convert_object_to_array($data): array
    {
        $new_data = array();
        foreach ($data as $key => $value) {
            $new_data[$key] = $value;
        }

        return $new_data;
    }

    public function http_request_args($args, $url)
    {
        $verify_ssl = $this->verify_ssl();
        if (strpos($url, 'https://') !== false && strpos($url, 'edd_action=package_download')) {
            $args['sslverify'] = $verify_ssl;
        }
        return $args;
    }

    /**
     * @param $_action
     * @param $_data
     *
     * @return array|mixed|\WP_Error|null
     */
    private function api_request($_action, $_data)
    {
        global $edd_plugin_url_available;

        $verify_ssl = $this->verify_ssl();

        // Do a quick status check on this domain if we haven't already checked it.
        $store_hash = md5(SMARTPAY_PAYHALAL_EDD_STORE_URL);
        if (!is_array($edd_plugin_url_available) || !isset($edd_plugin_url_available[$store_hash])) {
            $test_url_parts = parse_url(SMARTPAY_PAYHALAL_EDD_STORE_URL);

            $scheme = !empty($test_url_parts['scheme']) ? $test_url_parts['scheme']     : 'http';
            $host   = !empty($test_url_parts['host'])   ? $test_url_parts['host']       : '';
            $port   = !empty($test_url_parts['port'])   ? ':' . $test_url_parts['port'] : '';

            if (empty($host)) {
                $edd_plugin_url_available[$store_hash] = false;
            } else {
                $test_url = $scheme . '://' . $host . $port;
                $response = wp_remote_get($test_url, array('timeout' => 5, 'sslverify' => $verify_ssl));
                $edd_plugin_url_available[$store_hash] = ! is_wp_error($response);
            }
        }

        if (!$edd_plugin_url_available[$store_hash]) {
            return;
        }
        $data = array_merge((array) self::$api_data, $_data);

        if ($data['slug'] != self::$plugin_slug) {
            return;
        }

        if (SMARTPAY_PAYHALAL_EDD_STORE_URL == trailingslashit(home_url())) {
            return false; // Don't allow a plugin to ping itself
        }

        $api_params = array(
            'edd_action' => 'get_version',
            'license'    => !empty($data['license']) ? $data['license'] : '',
            'item_name'  => $data['item_name'] ?? false,
            'item_id'    => $data['item_id'] ?? false,
            'version'    => $data['version'] ?? false,
            'slug'       => $data['slug'],
            'author'     => $data['author'],
            'url'        => home_url(),
            'beta'       => !empty($data['beta']),
        );

        $request = wp_remote_post(SMARTPAY_PAYHALAL_EDD_STORE_URL,
            array('timeout' => 15, 'sslverify' => $verify_ssl, 'body' => $api_params));

        if (!is_wp_error($request)) {
            $request = json_decode(wp_remote_retrieve_body($request));
        }

        if ($request && isset($request->sections)) {
            $request->sections = maybe_unserialize($request->sections);
        } else {
            $request = false;
        }

        if ($request && isset($request->banners)) {
            $request->banners = maybe_unserialize($request->banners);
        }

        if ($request && isset($request->icons)) {
            $request->icons = maybe_unserialize($request->icons);
        }

        if (!empty($request->sections)) {
            foreach ($request->sections as $key => $section) {
                $request->$key = (array) $section;
            }
        }

        return $request;
    }

    public function show_changelog(): void
    {
        global $edd_plugin_data;

        if (empty($_REQUEST['edd_sl_action']) || 'view_plugin_changelog' != $_REQUEST['edd_sl_action']) {
            return;
        }

        if (empty($_REQUEST['plugin'])) {
            return;
        }

        if (empty($_REQUEST['slug'])) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            wp_die(__('You do not have permission to install plugin updates', 'easy-digital-downloads'), __('Error', 'easy-digital-downloads'), array('response' => 403));
        }

        $data         = $edd_plugin_data[$_REQUEST['slug']];
        $beta         = !empty($data['beta']);
        $cache_key    = md5('edd_plugin_' . sanitize_key($_REQUEST['plugin']) . '_' . $beta . '_version_info');
        $version_info = $this->get_cached_version_info($cache_key);

        if (false === $version_info) {
            $api_params = array(
                'edd_action' => 'get_version',
                'item_name'  => isset($data['item_name']) ? $data['item_name'] : false,
                'item_id'    => isset($data['item_id']) ? $data['item_id'] : false,
                'slug'       => $_REQUEST['slug'],
                'author'     => $data['author'],
                'url'        => home_url(),
                'beta'       => !empty($data['beta'])
            );

            $verify_ssl = $this->verify_ssl();
            $request    = wp_remote_post(SMARTPAY_PAYHALAL_EDD_STORE_URL, array('timeout' => 15, 'sslverify' => $verify_ssl, 'body' => $api_params));

            if (!is_wp_error($request)) {
                $version_info = json_decode(wp_remote_retrieve_body($request));
            }

            if (!empty($version_info) && isset($version_info->sections)) {
                $version_info->sections = maybe_unserialize($version_info->sections);
            } else {
                $version_info = false;
            }

            if (!empty($version_info)) {
                foreach ($version_info->sections as $key => $section) {
                    $version_info->$key = (array) $section;
                }
            }

            $this->set_version_info_cache($version_info, $cache_key);
        }

        if (!empty($version_info) && isset($version_info->sections['changelog'])) {
            echo '<div style="background:#fff;padding:10px;">' . $version_info->sections['changelog'] . '</div>';
        }

        exit;
    }

    /**
     * get cache for the plugin
     *
     * @param  string  $cache_key
     *
     * @return object
     */
    public function get_cached_version_info($cache_key = '')
    {
        if (empty($cache_key)) {
            $cache_key = self::$cache_key;
        }

        $cache = get_option($cache_key);

        if (empty($cache['timeout']) || time() > $cache['timeout']) {
            return false; // Cache is expired
        }

        // We need to turn the icons into an array, thanks to WP Core forcing these into an object at some point.
        $cache['value'] = json_decode($cache['value']);
        if (!empty($cache['value']->icons)) {
            $cache['value']->icons = (array) $cache['value']->icons;
        }

        return $cache['value'];
    }

    /**
     * set cache for the plugin
     *
     * @param  object  $value
     * @param  string  $cache_key
     */
    public function set_version_info_cache($value = '', $cache_key = ''): void
    {
        if (empty($cache_key)) {
            $cache_key = self::$cache_key;
        }

        $data = array(
            'timeout' => strtotime('+3 hours', time()),
            'value'   => json_encode($value)
        );

        update_option($cache_key, $data, 'no');
    }

    /**
     * Check if SSL is used
     *
     * @return bool
     */
    private function verify_ssl(): bool
    {
        return (bool) apply_filters('edd_sl_api_request_verify_ssl', true, $this);
    }
}

endif;
