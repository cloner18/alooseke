<?php
/**
 * Ledger / Transactions
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Transactions {

	/**
	 * Create a ledger transaction
	 *
	 * @param array $data Transaction data.
	 * @return int|false Transaction ID.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'transactions' );

		$defaults = array(
			'account_id'     => 0,
			'asset_id'       => 0,
			'order_id'       => null,
			'order_item_id'  => null,
			'type'           => '',
			'quantity'       => '0.00000000',
			'unit'           => 'piece',
			'balance_before' => '0.00000000',
			'balance_after'  => '0.00000000',
			'unit_price'     => null,
			'total_value'    => null,
			'currency'       => 'IRT',
			'reference'      => null,
			'description'    => null,
			'status'         => 'completed',
			'meta'           => null,
			'created_by'     => get_current_user_id() ?: 0,
		);

		$data = wp_parse_args( $data, $defaults );

		$data['quantity']       = number_format( (float) $data['quantity'], 8, '.', '' );
		$data['balance_before'] = number_format( (float) $data['balance_before'], 8, '.', '' );
		$data['balance_after']  = number_format( (float) $data['balance_after'], 8, '.', '' );

		if ( null !== $data['unit_price'] ) {
			$data['unit_price'] = number_format( (float) $data['unit_price'], 4, '.', '' );
		}
		if ( null !== $data['total_value'] ) {
			$data['total_value'] = number_format( (float) $data['total_value'], 4, '.', '' );
		}

		if ( is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}

		$data['created_at'] = current_time( 'mysql' );

		$formats = array(
			'%d', // account_id
			'%d', // asset_id
			'%d', // order_id
			'%d', // order_item_id
			'%s', // type
			'%s', // quantity
			'%s', // unit
			'%s', // balance_before
			'%s', // balance_after
			'%s', // unit_price
			'%s', // total_value
			'%s', // currency
			'%s', // reference
			'%s', // description
			'%s', // status
			'%s', // meta
			'%s', // created_at
			'%d', // created_by
		);

		$inserted = $wpdb->insert( $table, $data, $formats );

		if ( ! $inserted ) {
			Asset_Wallet_Helpers::log( 'Failed to create transaction', array( 'error' => $wpdb->last_error, 'data' => $data ) );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get transaction by ID
	 *
	 * @param int $id Transaction ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'transactions' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				absint( $id )
			)
		);
	}

	/**
	 * Check if purchase already credited (idempotency)
	 *
	 * @param int $order_id      Order ID.
	 * @param int $order_item_id Order item ID.
	 * @return bool
	 */
	public static function purchase_exists( $order_id, $order_item_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'transactions' );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE order_id = %d AND order_item_id = %d AND type = 'purchase' LIMIT 1",
				absint( $order_id ),
				absint( $order_item_id )
			)
		);

		return ! empty( $exists );
	}

	/**
	 * Get transactions for account
	 *
	 * @param int   $account_id Account ID.
	 * @param array $args       Args (limit, offset, type, asset_id).
	 * @return array
	 */
	public static function get_for_account( $account_id, $args = array() ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'transactions' );

		$defaults = array(
			'limit'    => 50,
			'offset'   => 0,
			'type'     => '',
			'asset_id' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'account_id = %d' );
		$params = array( absint( $account_id ) );

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = sanitize_key( $args['type'] );
		}
		if ( ! empty( $args['asset_id'] ) ) {
			$where[]  = 'asset_id = %d';
			$params[] = absint( $args['asset_id'] );
		}

		$where_sql = implode( ' AND ', $where );
		$params[]  = absint( $args['limit'] );
		$params[]  = absint( $args['offset'] );

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Calculate balance from ledger for reconciliation
	 *
	 * @param int $account_id Account ID.
	 * @param int $asset_id   Asset ID.
	 * @return string
	 */
	public static function calculate_balance_from_ledger( $account_id, $asset_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'transactions' );

		// Sum of completed transactions that affect balance
		// purchase, admin_credit, transfer_in, delivery_reversal, refund (positive)
		// sell, delivery, admin_debit, transfer_out, reservation (negative for available)

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(
					CASE
						WHEN type IN ('purchase','admin_credit','delivery_reversal','refund','reservation_release') THEN quantity
						WHEN type IN ('sell','delivery','admin_debit','reservation') THEN -quantity
						ELSE 0
					END
				), 0)
				FROM {$table}
				WHERE account_id = %d AND asset_id = %d AND status = 'completed'",
				absint( $account_id ),
				absint( $asset_id )
			)
		);

		return number_format( (float) $result, 8, '.', '' );
	}
}
