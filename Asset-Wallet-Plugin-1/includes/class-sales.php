<?php
/**
 * Sale of assets flow
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Sales {

	/**
	 * Instance
	 *
	 * @var Asset_Wallet_Sales
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Asset_Wallet_Sales
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
		// Nothing heavy here; AJAX handles most
	}

	/**
	 * Create sale request (before OTP)
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $asset_id  Asset ID.
	 * @param string $quantity  Quantity.
	 * @return int|WP_Error Sale request ID.
	 */
	public static function create_request( $user_id, $asset_id, $quantity ) {
		$user_id  = absint( $user_id );
		$asset_id = absint( $asset_id );
		$quantity = number_format( (float) $quantity, 8, '.', '' );

		if ( Asset_Wallet_Helpers::decimal_cmp( $quantity, '0' ) <= 0 ) {
			return new WP_Error( 'invalid_quantity', __( 'مقدار فروش نامعتبر است.', 'asset-wallet' ) );
		}

		$account = Asset_Wallet_Accounts::get_or_create( $user_id );
		if ( ! $account || ! Asset_Wallet_Accounts::is_active( $account ) ) {
			return new WP_Error( 'no_account', __( 'حساب کیف دارایی یافت نشد.', 'asset-wallet' ) );
		}

		$asset = Asset_Wallet_Assets::get( $asset_id );
		if ( ! $asset || 'active' !== $asset->status ) {
			return new WP_Error( 'invalid_asset', __( 'دارایی نامعتبر است.', 'asset-wallet' ) );
		}

		// Check available balance
		$available = Asset_Wallet_Balances::get_available( $account->id, $asset_id );
		if ( Asset_Wallet_Helpers::decimal_cmp( $available, $quantity ) < 0 ) {
			return new WP_Error( 'insufficient_balance', __( 'موجودی کافی نیست.', 'asset-wallet' ) );
		}

		// Get current buy price (for snapshot only)
		$buy_price = Asset_Wallet_Helpers::get_buy_price( $asset->product_id, $asset->variation_id );
		if ( false === $buy_price ) {
			return new WP_Error( 'no_price', __( 'قیمت خرید فعلی برای این دارایی تعریف نشده است. فروش امکان‌پذیر نیست.', 'asset-wallet' ) );
		}

		$total = Asset_Wallet_Helpers::decimal_mul( $buy_price, $quantity );

		$idempotency_key = Asset_Wallet_Helpers::generate_idempotency_key();

		global $wpdb;
		$table = Asset_Wallet_Database::table( 'sale_requests' );
		$now   = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'          => $user_id,
				'account_id'       => $account->id,
				'asset_id'         => $asset_id,
				'product_id'       => $asset->product_id,
				'variation_id'     => $asset->variation_id ?: null,
				'quantity'         => $quantity,
				'unit'             => $asset->unit,
				'unit_price'       => number_format( $buy_price, 4, '.', '' ),
				'total_price'      => number_format( (float) $total, 4, '.', '' ),
				'currency'         => Asset_Wallet_Helpers::get_option( 'currency', 'IRT' ),
				'status'           => 'pending_otp',
				'otp_verified'     => 0,
				'idempotency_key'  => $idempotency_key,
				'price_snapshot'   => wp_json_encode( array(
					'unit_price' => $buy_price,
					'total'      => $total,
					'timestamp'  => time(),
				) ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'create_failed', __( 'ایجاد درخواست فروش با خطا مواجه شد.', 'asset-wallet' ) );
		}

		$sale_id = $wpdb->insert_id;

		// Send OTP
		$otp_result = Asset_Wallet_OTP::create_and_send( $user_id, $sale_id );
		if ( is_wp_error( $otp_result ) ) {
			// Mark request as failed
			$wpdb->update(
				$table,
				array( 'status' => 'failed', 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $sale_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return $otp_result;
		}

		return $sale_id;
	}

	/**
	 * Complete sale after OTP verification (atomic)
	 *
	 * @param int    $user_id         User ID.
	 * @param int    $sale_request_id Sale request ID.
	 * @param string $otp_code        OTP.
	 * @param bool   $confirm_new_price User confirmed new price if changed.
	 * @return array|WP_Error Result with amounts.
	 */
	public static function complete_sale( $user_id, $sale_request_id, $otp_code, $confirm_new_price = false ) {
		$user_id         = absint( $user_id );
		$sale_request_id = absint( $sale_request_id );

		global $wpdb;
		$table = Asset_Wallet_Database::table( 'sale_requests' );

		$request = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND user_id = %d LIMIT 1",
				$sale_request_id,
				$user_id
			)
		);

		if ( ! $request ) {
			return new WP_Error( 'request_not_found', __( 'درخواست فروش یافت نشد.', 'asset-wallet' ) );
		}

		if ( ! in_array( $request->status, array( 'pending_otp', 'otp_verified' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'وضعیت درخواست فروش نامعتبر است.', 'asset-wallet' ) );
		}

		// Verify OTP if not already verified
		if ( ! $request->otp_verified ) {
			$otp_result = Asset_Wallet_OTP::verify( $user_id, $sale_request_id, $otp_code );
			if ( is_wp_error( $otp_result ) ) {
				return $otp_result;
			}

			$wpdb->update(
				$table,
				array(
					'status'       => 'otp_verified',
					'otp_verified' => 1,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $sale_request_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		// Re-read current buy price from Backend (Source of Truth)
		$asset = Asset_Wallet_Assets::get( $request->asset_id );
		if ( ! $asset ) {
			return new WP_Error( 'invalid_asset', __( 'دارایی نامعتبر است.', 'asset-wallet' ) );
		}

		$current_price = Asset_Wallet_Helpers::get_buy_price( $asset->product_id, $asset->variation_id );
		if ( false === $current_price ) {
			return new WP_Error( 'no_price', __( 'قیمت خرید فعلی تعریف نشده است.', 'asset-wallet' ) );
		}

		$snapshot = json_decode( $request->price_snapshot, true );
		$old_price = isset( $snapshot['unit_price'] ) ? (float) $snapshot['unit_price'] : (float) $request->unit_price;

		// Price changed?
		if ( abs( $current_price - $old_price ) > 0.01 && ! $confirm_new_price ) {
			$new_total = Asset_Wallet_Helpers::decimal_mul( $current_price, $request->quantity );
			return new WP_Error(
				'price_changed',
				__( 'قیمت فروش به‌روزرسانی شد.', 'asset-wallet' ),
				array(
					'old_unit_price' => $old_price,
					'new_unit_price' => $current_price,
					'new_total'      => (float) $new_total,
					'quantity'       => $request->quantity,
				)
			);
		}

		$final_unit_price = $current_price;
		$final_total      = Asset_Wallet_Helpers::decimal_mul( $final_unit_price, $request->quantity );

		// Atomic operation
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Lock balance
			$balance_row = Asset_Wallet_Balances::get( $request->account_id, $request->asset_id, true );
			if ( ! $balance_row ) {
				throw new Exception( __( 'موجودی یافت نشد.', 'asset-wallet' ) );
			}

			$available = Asset_Wallet_Helpers::decimal_sub( $balance_row->quantity, $balance_row->reserved_quantity );
			if ( Asset_Wallet_Helpers::decimal_cmp( $available, $request->quantity ) < 0 ) {
				throw new Exception( __( 'موجودی کافی نیست.', 'asset-wallet' ) );
			}

			$balance_before = $balance_row->quantity;

			// Decrease asset
			$decreased = Asset_Wallet_Balances::decrease( $request->account_id, $request->asset_id, $request->quantity );
			if ( is_wp_error( $decreased ) ) {
				throw new Exception( $decreased->get_error_message() );
			}

			$balance_after = Asset_Wallet_Helpers::decimal_sub( $balance_before, $request->quantity );

			// Create asset transaction
			$tx_id = Asset_Wallet_Transactions::create(
				array(
					'account_id'     => $request->account_id,
					'asset_id'       => $request->asset_id,
					'type'           => 'sell',
					'quantity'       => $request->quantity,
					'unit'           => $request->unit,
					'balance_before' => $balance_before,
					'balance_after'  => $balance_after,
					'unit_price'     => $final_unit_price,
					'total_value'    => $final_total,
					'currency'       => $request->currency,
					'reference'      => 'sale_request_' . $sale_request_id,
					'description'    => sprintf( __( 'فروش دارایی #%d', 'asset-wallet' ), $request->asset_id ),
					'status'         => 'completed',
					'created_by'     => $user_id,
				)
			);

			if ( ! $tx_id ) {
				throw new Exception( __( 'ثبت تراکنش دارایی با خطا مواجه شد.', 'asset-wallet' ) );
			}

			// Credit TeraWallet
			$credit_result = Asset_Wallet_TeraWallet::credit(
				$user_id,
				(float) $final_total,
				sprintf( __( 'فروش دارایی از کیف دارایی - درخواست #%d', 'asset-wallet' ), $sale_request_id ),
				'sale_request_' . $sale_request_id
			);

			if ( is_wp_error( $credit_result ) ) {
				throw new Exception( $credit_result->get_error_message() );
			}

			$tera_tx_id = is_array( $credit_result ) && isset( $credit_result['transaction_id'] ) ? $credit_result['transaction_id'] : (string) $credit_result;

			// Update sale request
			$updated = $wpdb->update(
				$table,
				array(
					'status'                    => 'completed',
					'unit_price'                => number_format( $final_unit_price, 4, '.', '' ),
					'total_price'               => number_format( (float) $final_total, 4, '.', '' ),
					'terawallet_transaction_id' => $tera_tx_id,
					'asset_transaction_id'      => $tx_id,
					'updated_at'                => current_time( 'mysql' ),
					'completed_at'              => current_time( 'mysql' ),
				),
				array( 'id' => $sale_request_id ),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				throw new Exception( __( 'به‌روزرسانی درخواست فروش با خطا مواجه شد.', 'asset-wallet' ) );
			}

			$wpdb->query( 'COMMIT' );

			Asset_Wallet_Audit::log(
				'sale_completed',
				'sale_request',
				$sale_request_id,
				null,
				array(
					'unit_price' => $final_unit_price,
					'total'      => $final_total,
					'quantity'   => $request->quantity,
					'tera_tx'    => $tera_tx_id,
				),
				$user_id
			);

			return array(
				'success'         => true,
				'sale_request_id' => $sale_request_id,
				'unit_price'      => $final_unit_price,
				'total_price'     => (float) $final_total,
				'quantity'        => $request->quantity,
				'terawallet_tx'   => $tera_tx_id,
			);

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			Asset_Wallet_Helpers::log( 'Sale completion failed', array(
				'sale_request_id' => $sale_request_id,
				'error'           => $e->getMessage(),
			) );

			// Mark as failed
			$wpdb->update(
				$table,
				array( 'status' => 'failed', 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $sale_request_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return new WP_Error( 'sale_failed', $e->getMessage() );
		}
	}

	/**
	 * Get sale request
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get_request( $id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'sale_requests' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				absint( $id )
			)
		);
	}
}
