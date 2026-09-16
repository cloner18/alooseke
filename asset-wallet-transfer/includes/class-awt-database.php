<?php
defined( 'ABSPATH' ) || exit;

class AWT_Database {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'asset_wallet_transfers';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			transfer_uuid VARCHAR(64) NOT NULL,
			transaction_reference VARCHAR(50) NOT NULL,
			sender_user_id BIGINT UNSIGNED NOT NULL,
			sender_account_id BIGINT UNSIGNED NULL,
			sender_first_name VARCHAR(100) NULL,
			sender_last_name VARCHAR(100) NULL,
			sender_full_name VARCHAR(200) NULL,
			sender_mobile VARCHAR(20) NULL,
			receiver_user_id BIGINT UNSIGNED NOT NULL,
			receiver_account_id BIGINT UNSIGNED NULL,
			receiver_first_name VARCHAR(100) NULL,
			receiver_last_name VARCHAR(100) NULL,
			receiver_full_name VARCHAR(200) NULL,
			receiver_mobile VARCHAR(20) NULL,
			transfer_type VARCHAR(20) NOT NULL,
			asset_id BIGINT UNSIGNED NULL,
			product_id BIGINT UNSIGNED NULL,
			variation_id BIGINT UNSIGNED NULL,
			product_name VARCHAR(255) NULL,
			quantity DECIMAL(30,8) NULL,
			unit VARCHAR(20) NULL,
			amount DECIMAL(20,4) NULL,
			currency VARCHAR(10) NULL DEFAULT 'IRT',
			status VARCHAR(40) NOT NULL DEFAULT 'pending_otp',
			otp_verified TINYINT(1) NOT NULL DEFAULT 0,
			sender_transaction_id BIGINT UNSIGNED NULL,
			receiver_transaction_id BIGINT UNSIGNED NULL,
			sender_tera_tx_id VARCHAR(100) NULL,
			receiver_tera_tx_id VARCHAR(100) NULL,
			sender_otp_sms_status VARCHAR(20) NULL DEFAULT 'pending',
			receiver_sms_status VARCHAR(20) NULL DEFAULT 'pending',
			sender_success_sms_status VARCHAR(20) NULL DEFAULT 'pending',
			reversal_reference VARCHAR(50) NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			cancelled_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_uuid (transfer_uuid),
			UNIQUE KEY uk_trx (transaction_reference),
			KEY idx_sender (sender_user_id),
			KEY idx_receiver (receiver_user_id),
			KEY idx_status (status),
			KEY idx_type (transfer_type),
			KEY idx_created (created_at)
		) $charset;";

		dbDelta( $sql );
		update_option( 'awt_db_version', AWT_VERSION );
	}

	public static function maybe_upgrade() {
		$ver = get_option( 'awt_db_version', '' );
		if ( $ver !== AWT_VERSION ) {
			self::install();
		}
	}
}
