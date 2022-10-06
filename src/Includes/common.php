<?php

/**
 * Function for checking payhalal supported currency.
 *
 * @since 1.0.0
 * @uses edd_get_currency()
 * @return boolean
 * @access public
 */
function is_payhalal_currency_supported(): bool
{
	$payhalal_supported_currency = ['MYR'];

	if (in_array(strtoupper(edd_get_currency()), $payhalal_supported_currency)) {
		return true;
	} else {
		add_action('admin_notices', 'smartpay_payhalal_unsupported_currency_notice');
		return false;
	}
}

/**
 * show unsupported currency notice
 * @return void
 */
function smartpay_payhalal_unsupported_currency_notice(): void {
	echo __( '<div class="error"><p>Unsupported currency! Your currency <code>' . strtoupper( edd_get_currency() ) . '</code> does not supported by PayHalal.</p></div>', 'wp-smartpay-edd' );
}

/**
 * @param $message
 * @param bool $print
 *
 * @return void
 */
function smartpay_payhalal_log($message, bool $print = false): void {
	global $edd_options;

	$message = is_array($message) || is_object($message) ? print_r($message) : $message;

	if (isset($edd_options['debug_mode'])) {
		edd_debug_log($message);
	}

	echo $print ? $message : '';
	return;
}
