<?php
/**
 * Balances management (cache layer)
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Balances {

	/**
	 * Get balance row (with optional lock)
	 *
	 * @param int  $account_id Account ID.
	 * @param int  $asset_id   Asset ID.
	 * @param bool $for_update Lock row.
	 * @return object|null
	 */
	public static function get( $account_id, $asset_id, $for_update = false ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'balances' );

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE account_id = %d AND asset_id = %d LIMIT 1",
			absint( $account_id ),
			absint( $asset_id )
		);

		if ( $for_update ) {
			$sql .= ' FOR UPDATE';
		}

		return $wpdb->get_row( $sql );
	}

	/**
	 * Get available quantity (quantity - reserved)
	 *
	 * @param int $account_id Account ID.
	 * @param int $asset_id   Asset ID.
	 * @return string Decimal string.
	 */
	public static function get_available( $account_id, $asset_id ) {
		$row = self::get( $account_id, $asset_id );
		if ( ! $row ) {
			return '0.00000000';
		}
		return Asset_Wallet_Helpers::decimal_sub( $row->quantity, $row->reserved_quantity );
	}

	/**
	 * Get all balances for an account
	 *
	 * @param int $account_id Account ID.
	 * @return array
	 */
	public static function get_all_for_account( $account_id ) {
		global $wpdb;
		$balances_table = Asset_Wallet_Database::table( 'balances' );
		$assets_table   = Asset_Wallet_Database::table( 'assets' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, a.name, a.type, a.unit, a.weight AS asset_weight, a.product_id, a.variation_id
				 FROM {$balances_table} b
				 INNER JOIN {$assets_table} a ON a.id = b.asset_id
				 WHERE b.account_id = %d AND a.status = 'active'
				 AND (b.quantity > 0 OR b.reserved_quantity > 0)
				 ORDER BY a.name ASC",
				absint( $account_id )
			)
		);
	}

	/**
	 * Ensure balance row exists
	 *
	 * @param int $account_id Account ID.
	 * @param int $asset_id   Asset ID.
	 * @return object
	 */
	public static function ensure( $account_id, $asset_id ) {
		$row = self::get( $account_id, $asset_id );
		if ( $row ) {
			return $row;
		}

		global $wpdb;
		$table = Asset_Wallet_Database::table( 'balances' );
		$now   = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'account_id'        => absint( $account_id ),
				'asset_id'          => absint( $asset_id ),
				'quantity'          => '0.00000000',
				'weight'            => '0.00000000',
				'reserved_quantity' => '0.00000000',
				'reserved_weight'   => '0.00000000',
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return self::get( $account_id, $asset_id );
	}

	/**
	 * Increase balance (must be called inside transaction + lock)
	 *
	 * @param int    $account_id Account ID.
	 * @param int    $asset_id   Asset ID.
	 * @param string $quantity   Quantity to add.
	 * @param string $weight     Weight to add (optional).
	 * @return bool
	 */
	public static function increase( $account_id, $asset_id, $quantity, $weight = null ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'balances' );

		self::ensure( $account_id, $asset_id );

		$qty = number_format( (float) $quantity, 8, '.', '' );
		$w   = null !== $weight ? number_format( (float) $weight, 8, '.', '' ) : $qty;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET quantity = quantity + %s,
				     weight = weight + %s,
				     updated_at = %s
				 WHERE account_id = %d AND asset_id = %d",
				$qty,
				$w,
				current_time( 'mysql' ),
				absint( $account_id ),
				absint( $asset_id )
			)
		);

		return false !== $result;
	}

	/**
	 * Decrease balance (must be called inside transaction + lock)
	 * Checks available balance first.
	 *
	 * @param int    $account_id Account ID.
	 * @param int    $asset_id   Asset ID.
	 * @param string $quantity   Quantity to subtract.
	 * @param string $weight     Weight to subtract (optional).
	 * @return bool|WP_Error
	 */
	public static function decrease( $account_id, $asset_id, $quantity, $weight = null ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'balances' );

		$row = self::get( $account_id, $asset_id, true );
		if ( ! $row ) {
			return new WP_Error( 'no_balance', __( 'موجودی یافت نشد.', 'asset-wallet' ) );
		}

		$available = Asset_Wallet_Helpers::decimal_sub( $row->quantity, $row->reserved_quantity );
		if ( Asset_Wallet_Helpers::decimal_cmp( $available, $quantity ) < 0 ) {
			return new WP_Error( 'insufficient_balance', __( 'موجودی کافی نیست.', 'asset-wallet' ) );
		}

		$qty = number_format( (float) $quantity, 8, '.', '' );
		$w   = null !== $weight ? number_format( (float) $weight, 8, '.', '' ) : $qty;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET quantity = quantity - %s,
				     weight = weight - %s,
				     updated_at = %s
				 WHERE account_id = %d AND asset_id = %d
				 AND (quantity - reserved_quantity) >= %s",
				$qty,
				$w,
				current_time( 'mysql' ),
				absint( $account_id ),
				absint( $asset_id ),
				$qty
			)
		);

		if ( ! $result ) {
			return new WP_Error( 'decrease_failed', __( 'کاهش موجودی با خطا مواجه شد.', 'asset-wallet' ) );
		}

		return true;
	}

	/**
	 * Reserve quantity
	 *
	 * @param int    $account_id Account ID.
	 * @param int    $asset_id   Asset ID.
	 * @param string $quantity   Quantity.
	 * @return bool|WP_Error
	 */
	public static function reserve( $account_id, $asset_id, $quantity ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'balances' );

		$row = self::get( $account_id, $asset_id, true );
		if ( ! $row ) {
			return new WP_Error( 'no_balance', __( 'موجودی یافت نشد.', 'asset-wallet' ) );
		}

		$available = Asset_Wallet_Helpers::decimal_sub( $row->quantity, $row->reserved_quantity );
		if ( Asset_Wallet_Helpers::decimal_cmp( $available, $quantity ) < 0 ) {
			return new WP_Error( 'insufficient_balance', __( 'موجودی کافی برای رزرو نیست.', 'asset-wallet' ) );
		}

		$qty = number_format( (float) $quantity, 8, '.', '' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET reserved_quantity = reserved_quantity + %s,
				     reserved_weight = reserved_weight + %s,
				     updated_at = %s
				 WHERE account_id = %d AND asset_id = %d
				 AND (quantity - reserved_quantity) >= %s",
				$qty,
				$qty,
				current_time( 'mysql' ),
				absint( $account_id ),
				absint( $asset_id ),
				$qty
			)
		);

		return $result ? true : new WP_Error( 'reserve_failed', __( 'رزرو با خطا مواجه شد.', 'asset-wallet' ) );
	}

	/**
	 * Release reserved quantity
	 *
	 * @param int    $account_id Account ID.
	 * @param int    $asset_id   Asset ID.
	 * @param string $quantity   Quantity.
	 * @return bool
	 */
	public static function release_reserve( $account_id, $asset_id, $quantity ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'balances' );

		$qty = number_format( (float) $quantity, 8, '.', '' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET reserved_quantity = GREATEST(0, reserved_quantity - %s),
				     reserved_weight = GREATEST(0, reserved_weight - %s),
				     updated_at = %s
				 WHERE account_id = %d AND asset_id = %d",
				$qty,
				$qty,
				current_time( 'mysql' ),
				absint( $account_id ),
				absint( $asset_id )
			)
		);

		return false !== $result;
	}

	/**
	 * Finalize delivery: decrease quantity + release reserve
	 *
	 * @param int    $account_id Account ID.
	 * @param int    $asset_id   Asset ID.
	 * @param string $quantity   Quantity.
	 * @return bool|WP_Error
	 */
	public static function finalize_delivery( $account_id, $asset_id, $quantity ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'balances' );

		$row = self::get( $account_id, $asset_id, true );
		if ( ! $row ) {
			return new WP_Error( 'no_balance', __( 'موجودی یافت نشد.', 'asset-wallet' ) );
		}

		$qty = number_format( (float) $quantity, 8, '.', '' );

		if ( Asset_Wallet_Helpers::decimal_cmp( $row->reserved_quantity, $qty ) < 0 ) {
			return new WP_Error( 'invalid_reserve', __( 'مقدار رزرو شده کافی نیست.', 'asset-wallet' ) );
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET quantity = quantity - %s,
				     weight = weight - %s,
				     reserved_quantity = reserved_quantity - %s,
				     reserved_weight = reserved_weight - %s,
				     updated_at = %s
				 WHERE account_id = %d AND asset_id = %d
				 AND reserved_quantity >= %s AND quantity >= %s",
				$qty,
				$qty,
				$qty,
				$qty,
				current_time( 'mysql' ),
				absint( $account_id ),
				absint( $asset_id ),
				$qty,
				$qty
			)
		);

		return $result ? true : new WP_Error( 'finalize_failed', __( 'نهایی‌سازی تحویل با خطا مواجه شد.', 'asset-wallet' ) );
	}
}
