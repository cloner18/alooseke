<?php
/**
 * Uninstall Asset Wallet
 *
 * By default does NOT delete financial data.
 * Only removes data if the option asset_wallet_delete_data_on_uninstall is set to yes
 * by an administrator explicitly.
 *
 * @package Asset_Wallet
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$delete = get_option( 'asset_wallet_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $delete ) {
	// Keep all financial data safe.
	return;
}

global $wpdb;

$tables = array(
	'asset_wallet_accounts',
	'asset_wallet_assets',
	'asset_wallet_balances',
	'asset_wallet_transactions',
	'asset_wallet_orders',
	'asset_wallet_sale_requests',
	'asset_wallet_delivery_requests',
	'asset_wallet_otp',
	'asset_wallet_audit_log',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore
}

// Clean options
$options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'asset_wallet_%'" );
foreach ( $options as $option ) {
	delete_option( $option );
}
