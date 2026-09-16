<?php
/**
 * Audit logging
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Audit {

	/**
	 * Log an action
	 *
	 * @param string $action      Action name.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id   Entity ID.
	 * @param mixed  $old_value   Old value.
	 * @param mixed  $new_value   New value.
	 * @param int    $user_id     User ID (optional).
	 */
	public static function log( $action, $entity_type, $entity_id = null, $old_value = null, $new_value = null, $user_id = null ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'audit_log' );

		$wpdb->insert(
			$table,
			array(
				'user_id'     => $user_id ? absint( $user_id ) : get_current_user_id(),
				'admin_id'    => current_user_can( 'manage_woocommerce' ) ? get_current_user_id() : null,
				'action'      => sanitize_key( $action ),
				'entity_type' => sanitize_key( $entity_type ),
				'entity_id'   => $entity_id ? absint( $entity_id ) : null,
				'old_value'   => is_scalar( $old_value ) ? $old_value : wp_json_encode( $old_value ),
				'new_value'   => is_scalar( $new_value ) ? $new_value : wp_json_encode( $new_value ),
				'ip_address'  => Asset_Wallet_Helpers::get_client_ip(),
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
