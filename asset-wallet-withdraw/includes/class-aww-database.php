<?php
defined( 'ABSPATH' ) || exit;

class AWW_Database {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'asset_wallet_withdrawals';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tracking_code VARCHAR(32) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			account_id BIGINT UNSIGNED NULL,
			type VARCHAR(20) NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			amount DECIMAL(20,4) NULL,
			currency VARCHAR(10) NULL DEFAULT 'IRT',
			asset_id BIGINT UNSIGNED NULL,
			product_id BIGINT UNSIGNED NULL,
			variation_id BIGINT UNSIGNED NULL,
			product_name VARCHAR(255) NULL,
			quantity DECIMAL(30,8) NULL,
			unit VARCHAR(20) NULL,
			card_id VARCHAR(64) NULL,
			card_number VARCHAR(32) NULL,
			card_sheba VARCHAR(34) NULL,
			card_bank VARCHAR(100) NULL,
			card_holder VARCHAR(150) NULL,
			shipping_full_name VARCHAR(200) NULL,
			shipping_phone VARCHAR(20) NULL,
			shipping_address TEXT NULL,
			shipping_city VARCHAR(100) NULL,
			shipping_state VARCHAR(100) NULL,
			shipping_postcode VARCHAR(20) NULL,
			user_full_name VARCHAR(200) NULL,
			user_mobile VARCHAR(20) NULL,
			reject_reason TEXT NULL,
			admin_note TEXT NULL,
			ledger_tx_id BIGINT UNSIGNED NULL,
			tera_tx_id VARCHAR(100) NULL,
			refund_tx_id VARCHAR(100) NULL,
			reviewed_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			approved_at DATETIME NULL,
			rejected_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_tracking (tracking_code),
			KEY idx_user (user_id),
			KEY idx_status (status),
			KEY idx_type (type),
			KEY idx_created (created_at),
			KEY idx_mobile (user_mobile)
		) $charset;";

		dbDelta( $sql );
		update_option( 'aww_db_version', AWW_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'aww_db_version' ) !== AWW_VERSION ) {
			self::install();
		}
	}
}
