<?php
/**
 * Database management class
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Database {

	/**
	 * Install / upgrade tables
	 */
	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Accounts
		$sql_accounts = "CREATE TABLE {$prefix}asset_wallet_accounts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_user (user_id),
			KEY idx_status (status)
		) $charset_collate;";

		// Assets definition
		$sql_assets = "CREATE TABLE {$prefix}asset_wallet_assets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			type VARCHAR(50) NOT NULL DEFAULT 'coin',
			unit VARCHAR(20) NOT NULL DEFAULT 'piece',
			weight DECIMAL(30,8) NOT NULL DEFAULT 1.00000000,
			product_id BIGINT UNSIGNED NULL,
			variation_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_slug (slug),
			KEY idx_product (product_id, variation_id),
			KEY idx_type_status (type, status)
		) $charset_collate;";

		// Balances (cache)
		$sql_balances = "CREATE TABLE {$prefix}asset_wallet_balances (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT UNSIGNED NOT NULL,
			asset_id BIGINT UNSIGNED NOT NULL,
			quantity DECIMAL(30,8) NOT NULL DEFAULT 0.00000000,
			weight DECIMAL(30,8) NOT NULL DEFAULT 0.00000000,
			reserved_quantity DECIMAL(30,8) NOT NULL DEFAULT 0.00000000,
			reserved_weight DECIMAL(30,8) NOT NULL DEFAULT 0.00000000,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_account_asset (account_id, asset_id),
			KEY idx_asset (asset_id)
		) $charset_collate;";

		// Ledger / Transactions
		$sql_transactions = "CREATE TABLE {$prefix}asset_wallet_transactions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT UNSIGNED NOT NULL,
			asset_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NULL,
			order_item_id BIGINT UNSIGNED NULL,
			type VARCHAR(30) NOT NULL,
			quantity DECIMAL(30,8) NOT NULL,
			unit VARCHAR(20) NOT NULL,
			balance_before DECIMAL(30,8) NOT NULL,
			balance_after DECIMAL(30,8) NOT NULL,
			unit_price DECIMAL(20,4) NULL,
			total_value DECIMAL(20,4) NULL,
			currency VARCHAR(10) NULL DEFAULT 'IRT',
			reference VARCHAR(100) NULL,
			description TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'completed',
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			created_by BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_order_item_type (order_id, order_item_id, type),
			KEY idx_account_asset (account_id, asset_id),
			KEY idx_type_created (type, created_at),
			KEY idx_reference (reference),
			KEY idx_order (order_id)
		) $charset_collate;";

		// Order links
		$sql_orders = "CREATE TABLE {$prefix}asset_wallet_orders (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			account_id BIGINT UNSIGNED NOT NULL,
			asset_id BIGINT UNSIGNED NOT NULL,
			quantity DECIMAL(30,8) NOT NULL,
			storage_method VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			transaction_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_order_item (order_id, order_item_id),
			KEY idx_account (account_id),
			KEY idx_status (status)
		) $charset_collate;";

		// Sale requests
		$sql_sale_requests = "CREATE TABLE {$prefix}asset_wallet_sale_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			account_id BIGINT UNSIGNED NOT NULL,
			asset_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			variation_id BIGINT UNSIGNED NULL,
			quantity DECIMAL(30,8) NOT NULL,
			unit VARCHAR(20) NOT NULL,
			unit_price DECIMAL(20,4) NOT NULL,
			total_price DECIMAL(20,4) NOT NULL,
			currency VARCHAR(10) NOT NULL DEFAULT 'IRT',
			status VARCHAR(30) NOT NULL DEFAULT 'pending_otp',
			otp_verified TINYINT(1) NOT NULL DEFAULT 0,
			terawallet_transaction_id VARCHAR(100) NULL,
			asset_transaction_id BIGINT UNSIGNED NULL,
			idempotency_key VARCHAR(64) NOT NULL,
			price_snapshot LONGTEXT NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uk_idempotency (idempotency_key),
			KEY idx_user_status (user_id, status),
			KEY idx_asset (asset_id),
			KEY idx_created (created_at)
		) $charset_collate;";

		// Delivery requests
		$sql_delivery = "CREATE TABLE {$prefix}asset_wallet_delivery_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			account_id BIGINT UNSIGNED NOT NULL,
			asset_id BIGINT UNSIGNED NOT NULL,
			quantity DECIMAL(30,8) NOT NULL,
			unit VARCHAR(20) NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			reserved_at DATETIME NULL,
			shipping_address LONGTEXT NULL,
			tracking_code VARCHAR(100) NULL,
			asset_transaction_id BIGINT UNSIGNED NULL,
			admin_note TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY idx_user_status (user_id, status),
			KEY idx_asset (asset_id)
		) $charset_collate;";

		// OTP
		$sql_otp = "CREATE TABLE {$prefix}asset_wallet_otp (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			action VARCHAR(50) NOT NULL,
			phone VARCHAR(20) NOT NULL,
			otp_hash VARCHAR(255) NOT NULL,
			expires_at DATETIME NOT NULL,
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			sale_request_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			verified_at DATETIME NULL,
			ip_address VARCHAR(45) NULL,
			PRIMARY KEY (id),
			KEY idx_user_action_status (user_id, action, status),
			KEY idx_expires (expires_at),
			KEY idx_sale_request (sale_request_id)
		) $charset_collate;";

		// Audit log
		$sql_audit = "CREATE TABLE {$prefix}asset_wallet_audit_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			admin_id BIGINT UNSIGNED NULL,
			action VARCHAR(100) NOT NULL,
			entity_type VARCHAR(50) NOT NULL,
			entity_id BIGINT UNSIGNED NULL,
			old_value LONGTEXT NULL,
			new_value LONGTEXT NULL,
			ip_address VARCHAR(45) NULL,
			user_agent TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_entity (entity_type, entity_id),
			KEY idx_user_created (user_id, created_at),
			KEY idx_action (action)
		) $charset_collate;";

		dbDelta( $sql_accounts );
		dbDelta( $sql_assets );
		dbDelta( $sql_balances );
		dbDelta( $sql_transactions );
		dbDelta( $sql_orders );
		dbDelta( $sql_sale_requests );
		dbDelta( $sql_delivery );
		dbDelta( $sql_otp );
		dbDelta( $sql_audit );

		update_option( 'asset_wallet_db_version', ASSET_WALLET_VERSION );

		// Default settings
		$defaults = array(
			'otp_expire_seconds'       => 120,
			'otp_max_attempts'         => 5,
			'otp_resend_cooldown'      => 60,
			'otp_max_requests_window'  => 900,
			'otp_max_requests_count'   => 5,
			'sms_template_id'          => 604197,
			'sms_otp_param_name'       => 'code',
			'live_price_interval'      => 8,
			'enable_physical_delivery' => 1,
			'currency'                 => 'IRT',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( 'asset_wallet_' . $key ) ) {
				update_option( 'asset_wallet_' . $key, $value );
			}
		}

		flush_rewrite_rules();
	}

	/**
	 * Get table name with prefix
	 *
	 * @param string $name Table short name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'asset_wallet_' . $name;
	}
}
