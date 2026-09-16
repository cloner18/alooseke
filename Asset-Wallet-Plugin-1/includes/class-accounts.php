<?php
/**
 * Asset Accounts management
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Accounts {

	/**
	 * Get or create account for user
	 *
	 * @param int $user_id User ID.
	 * @return object|false Account object or false.
	 */
	public static function get_or_create( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$account = self::get_by_user( $user_id );
		if ( $account ) {
			return $account;
		}

		return self::create( $user_id );
	}

	/**
	 * Get account by user ID
	 *
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public static function get_by_user( $user_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'accounts' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d LIMIT 1",
				absint( $user_id )
			)
		);
	}

	/**
	 * Get account by ID
	 *
	 * @param int $account_id Account ID.
	 * @return object|null
	 */
	public static function get( $account_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'accounts' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				absint( $account_id )
			)
		);
	}

	/**
	 * Create account
	 *
	 * @param int $user_id User ID.
	 * @return object|false
	 */
	public static function create( $user_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'accounts' );
		$now   = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'    => absint( $user_id ),
				'status'     => 'active',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			// Race condition: already created
			return self::get_by_user( $user_id );
		}

		return self::get( $wpdb->insert_id );
	}

	/**
	 * Check if account is active
	 *
	 * @param int|object $account Account ID or object.
	 * @return bool
	 */
	public static function is_active( $account ) {
		if ( is_numeric( $account ) ) {
			$account = self::get( $account );
		}
		return $account && 'active' === $account->status;
	}
}
