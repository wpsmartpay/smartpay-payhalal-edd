<?php

require_once __DIR__ . '/vendor/autoload.php';

use SmartPayPayhalalEdd\License\EDDPayhalalAdmin;
use SmartPayPayhalalEdd\PayHalalPaymentGateway;

EDDPayhalalAdmin::instance();

// check the license first
$license = EDDPayhalalAdmin::check_licence();


if ($license && 'true' == $license->success && ('valid' == $license->license || 'expired' == $license->license)) {
    PayHalalPaymentGateway::instance();
} else {
    add_action('admin_notices', 'smartpay_payhalal_edd_licence_invalid_notice');
}

function smartpay_payhalal_edd_licence_invalid_notice(): void
{
    echo sprintf(__('<div class="error"><p><strong>PayHalal for Easy Digital Downloads License invalid! You must put and active license to activate the plugin, get feature updates and premium support. <a href="%s">Input your licence</a> or <a href="%s" target="_blank">Purchase licence</a></p></div></strong>',
        'wp-smartpay-edd'), admin_url('edit.php?post_type=download&page=edd-settings&tab=licenses'),
        SMARTPAY_PAYHALAL_EDD_STORE_URL . '/checkout/?edd_action=add_to_cart&download_id=88&edd_options[price_id]=1');
    // TODO: change the download id and price id
}
