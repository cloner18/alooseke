<?php
/**
 * Physical delivery requests
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Delivery {

	/**
	 * Instance
	 *
	 * @var Asset_Wallet_Delivery
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Asset_Wallet_Delivery
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// AJAX will call static methods
	}

	/**
	 * Create delivery request and reserve
	 *
	 * @param int    $user_id  User ID.
	 * @param int    $asset_id Asset ID.
	 * @param string $quantity Quantity.
	 * @param array  $address  Shipping address.
	 * @return int|WP_Error Request ID.
	 */
	public static function create_request( $user_id, $asset_id, $quantity, $address = array() ) {
		$user_id  = absint( $user_id );
		$asset_id = absint( $asset_id );
		$quantity = number_format( (float) $quantity, 8, '.', '' );

		if ( Asset_Wallet_Helpers::decimal_cmp( $quantity, '0' ) <= 0 ) {
			return new WP_Error( 'invalid_quantity', __( 'مقدار نامعتبر است.', 'asset-wallet' ) );
		}

		$account = Asset_Wallet_Accounts::get_or_create( $user_id );
		if ( ! $account ) {
			return new WP_Error( 'no_account', __( 'حساب یافت نشد.', 'asset-wallet' ) );
		}

		$asset = Asset_Wallet_Assets::get( $asset_id );
		if ( ! $asset ) {
			return new WP_Error( 'invalid_asset', __( 'دارایی نامعتبر است.', 'asset-wallet' ) );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			$reserved = Asset_Wallet_Balances::reserve( $account->id, $asset_id, $quantity );
			if ( is_wp_error( $reserved ) ) {
				throw new Exception( $reserved->get_error_message() );
			}

			$balance_row = Asset_Wallet_Balances::get( $account->id, $asset_id );
			$balance_before = $balance_row ? $balance_row->quantity : '0';

			// Ledger reservation
			$tx_id = Asset_Wallet_Transactions::create(
				array(
					'account_id'     => $account->id,
					'asset_id'       => $asset_id,
					'type'           => 'reservation',
					'quantity'       => $quantity,
					'unit'           => $asset->unit,
					'balance_before' => $balance_before,
					'balance_after'  => $balance_before, // available decreased, total same
					'reference'      => 'delivery_reserve',
					'description'    => __( 'رزرو برای تحویل فیزیکی', 'asset-wallet' ),
					'status'         => 'completed',
					'created_by'     => $user_id,
				)
			);

			$table = Asset_Wallet_Database::table( 'delivery_requests' );
			$now   = current_time( 'mysql' );

			$wpdb->insert(
				$table,
				array(
					'user_id'             => $user_id,
					'account_id'          => $account->id,
					'asset_id'            => $asset_id,
					'quantity'            => $quantity,
					'unit'                => $asset->unit,
					'status'              => 'reserved',
					'reserved_at'         => $now,
					'shipping_address'    => wp_json_encode( $address ),
					'asset_transaction_id'=> $tx_id,
					'created_at'          => $now,
					'updated_at'          => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);

			$request_id = $wpdb->insert_id;
			$wpdb->query( 'COMMIT' );

			return $request_id;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'delivery_failed', $e->getMessage() );
		}
	}

	/**
	 * Complete delivery (admin)
	 *
	 * @param int $request_id Request ID.
	 * @return true|WP_Error
	 */
	public static function complete( $request_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'delivery_requests' );

		$request = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $request_id ) )
		);

		if ( ! $request || 'reserved' !== $request->status && 'processing' !== $request->status ) {
			return new WP_Error( 'invalid_request', __( 'درخواست نامعتبر است.', 'asset-wallet' ) );
		}

		$wpdb->query( 'START TRANSACTION' );

		try {
			$finalized = Asset_Wallet_Balances::finalize_delivery( $request->account_id, $request->asset_id, $request->quantity );
			if ( is_wp_error( $finalized ) ) {
				throw new Exception( $finalized->get_error_message() );
			}

			$balance_row = Asset_Wallet_Balances::get( $request->account_id, $request->asset_id );
			$balance_after = $balance_row ? $balance_row->quantity : '0';

			Asset_Wallet_Transactions::create(
				array(
					'account_id'     => $request->account_id,
					'asset_id'       => $request->asset_id,
					'type'           => 'delivery',
					'quantity'       => $request->quantity,
					'unit'           => $request->unit,
					'balance_before' => Asset_Wallet_Helpers::decimal_add( $balance_after, $request->quantity ),
					'balance_after'  => $balance_after,
					'reference'      => 'delivery_' . $request_id,
					'description'    => __( 'تحویل فیزیکی نهایی', 'asset-wallet' ),
					'status'         => 'completed',
					'created_by'     => get_current_user_id(),
				)
			);

			$wpdb->update(
				$table,
				array(
					'status'       => 'completed',
					'updated_at'   => current_time( 'mysql' ),
					'completed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $request_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$wpdb->query( 'COMMIT' );
			return true;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'complete_failed', $e->getMessage() );
		}
	}

	/**
	 * Cancel delivery and release reserve
	 *
	 * @param int $request_id Request ID.
	 * @return true|WP_Error
	 */
	public static function cancel( $request_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'delivery_requests' );

		$request = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $request_id ) )
		);

		if ( ! $request || ! in_array( $request->status, array( 'pending', 'reserved', 'processing' ), true ) ) {
			return new WP_Error( 'invalid_request', __( 'درخواست نامعتبر است.', 'asset-wallet' ) );
		}

		$wpdb->query( 'START TRANSACTION' );

		try {
			Asset_Wallet_Balances::release_reserve( $request->account_id, $request->asset_id, $request->quantity );

			$balance_row = Asset_Wallet_Balances::get( $request->account_id, $request->asset_id );
			$balance = $balance_row ? $balance_row->quantity : '0';

			Asset_Wallet_Transactions::create(
				array(
					'account_id'     => $request->account_id,
					'asset_id'       => $request->asset_id,
					'type'           => 'reservation_release',
					'quantity'       => $request->quantity,
					'unit'           => $request->unit,
					'balance_before' => $balance,
					'balance_after'  => $balance,
					'reference'      => 'delivery_cancel_' . $request_id,
					'description'    => __( 'لغو رزرو تحویل فیزیکی', 'asset-wallet' ),
					'status'         => 'completed',
					'created_by'     => get_current_user_id(),
				)
			);

			$wpdb->update(
				$table,
				array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $request_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$wpdb->query( 'COMMIT' );
			return true;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'cancel_failed', $e->getMessage() );
		}
	}
}
