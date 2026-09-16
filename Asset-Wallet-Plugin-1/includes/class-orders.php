<?php
/**
 * WooCommerce Order integration for Asset Wallet
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Orders {

	/**
	 * Instance
	 *
	 * @var Asset_Wallet_Orders
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Asset_Wallet_Orders
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
		// Payment complete hooks (HPOS compatible)
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_payment_complete' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_payment_complete' ), 20, 1 );

		// Refund
		add_action( 'woocommerce_order_refunded', array( $this, 'handle_refund' ), 20, 2 );
	}

	/**
	 * Handle successful payment → credit assets
	 *
	 * @param int $order_id Order ID.
	 */
	public function handle_payment_complete( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$user_id = $order->get_user_id();
	if ( ! $user_id ) {
		return;
	}

	$account = Asset_Wallet_Accounts::get_or_create( $user_id );
	if ( ! $account || ! Asset_Wallet_Accounts::is_active( $account ) ) {
		Asset_Wallet_Helpers::log( 'Account not active for order', array( 'order_id' => $order_id, 'user_id' => $user_id ) );
		return;
	}

	$has_wallet_item = false;

	foreach ( $order->get_items() as $item_id => $item ) {
		$this->process_order_item( $order, $item_id, $item, $account );

		// چک کردن اینکه آیا این آیتم کیف دارایی بوده
		$storage_method = $item->get_meta( '_asset_storage_method', true );
		if ( 'wallet' === $storage_method ) {
			$has_wallet_item = true;
		}
	}

	// اگر حداقل یک آیتم به کیف دارایی اضافه شده، سفارش را تکمیل کن
	if ( $has_wallet_item ) {
		// فقط اگر هنوز تکمیل نشده باشد
		if ( ! $order->has_status( 'completed' ) ) {
			$order->update_status( 'completed', __( 'سفارش به دلیل نگهداری در کیف دارایی به‌صورت خودکار تکمیل شد.', 'asset-wallet' ) );
		}
	}
}

	/**
	 * Process single order item for asset credit
	 *
	 * @param WC_Order      $order   Order.
	 * @param int           $item_id Item ID.
	 * @param WC_Order_Item $item    Item.
	 * @param object        $account Account.
	 */
	private function process_order_item( $order, $item_id, $item, $account ) {
		$product = $item->get_product();
		if ( ! $product ) {
			return;
		}

		$product_id   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;

		// Check if product has asset wallet enabled
		$enabled = get_post_meta( $product_id, '_asset_wallet_enabled', true );
		if ( $variation_id ) {
			$var_enabled = get_post_meta( $variation_id, '_asset_wallet_enabled', true );
			if ( '' !== $var_enabled ) {
				$enabled = $var_enabled;
			}
		}

		if ( 'yes' !== $enabled ) {
			return;
		}

		// Storage method from order item meta
		$storage_method = $item->get_meta( '_asset_storage_method', true );
		if ( empty( $storage_method ) ) {
			$storage_method = $order->get_meta( '_asset_storage_method_' . $item_id, true );
		}
		if ( empty( $storage_method ) ) {
			// Fallback: check order meta
			$storage_method = 'physical';
		}

		if ( 'wallet' !== $storage_method ) {
			return;
		}

		// Idempotency check
		if ( Asset_Wallet_Transactions::purchase_exists( $order->get_id(), $item_id ) ) {
			return;
		}

		// Get or create asset definition
		$type   = get_post_meta( $variation_id ? $variation_id : $product_id, '_asset_type', true ) ?: 'coin';
		$unit   = get_post_meta( $variation_id ? $variation_id : $product_id, '_asset_unit', true ) ?: 'piece';
		$weight = get_post_meta( $variation_id ? $variation_id : $product_id, '_asset_weight', true ) ?: '1';

		$asset = Asset_Wallet_Assets::ensure_for_product(
			$product_id,
			$variation_id,
			array(
				'type'   => $type,
				'unit'   => $unit,
				'weight' => $weight,
			)
		);

		if ( ! $asset ) {
			Asset_Wallet_Helpers::log( 'Could not create asset for product', array( 'product_id' => $product_id, 'variation_id' => $variation_id ) );
			return;
		}

		$quantity = $item->get_quantity();
		$unit_price = $item->get_total() / max( 1, $quantity ); // historical purchase price from order

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Lock balance
			$balance_row = Asset_Wallet_Balances::get( $account->id, $asset->id, true );
			$balance_before = $balance_row ? $balance_row->quantity : '0.00000000';

			// Increase balance
			$increased = Asset_Wallet_Balances::increase( $account->id, $asset->id, $quantity, $quantity );
			if ( ! $increased ) {
				throw new Exception( 'Balance increase failed' );
			}

			$balance_after = Asset_Wallet_Helpers::decimal_add( $balance_before, $quantity );

			// Create ledger transaction
			$tx_id = Asset_Wallet_Transactions::create(
				array(
					'account_id'     => $account->id,
					'asset_id'       => $asset->id,
					'order_id'       => $order->get_id(),
					'order_item_id'  => $item_id,
					'type'           => 'purchase',
					'quantity'       => $quantity,
					'unit'           => $asset->unit,
					'balance_before' => $balance_before,
					'balance_after'  => $balance_after,
					'unit_price'     => $unit_price,
					'total_value'    => $item->get_total(),
					'currency'       => $order->get_currency(),
					'reference'      => 'order_' . $order->get_id() . '_item_' . $item_id,
					'description'    => sprintf( __( 'خرید از سفارش #%s', 'asset-wallet' ), $order->get_order_number() ),
					'status'         => 'completed',
					'created_by'     => 0,
				)
			);

			if ( ! $tx_id ) {
				// Unique constraint violation = already processed
				$wpdb->query( 'ROLLBACK' );
				return;
			}

			// Record in asset_wallet_orders
			$orders_table = Asset_Wallet_Database::table( 'orders' );
			$now          = current_time( 'mysql' );

			$wpdb->insert(
				$orders_table,
				array(
					'order_id'       => $order->get_id(),
					'order_item_id'  => $item_id,
					'account_id'     => $account->id,
					'asset_id'       => $asset->id,
					'quantity'       => number_format( (float) $quantity, 8, '.', '' ),
					'storage_method' => 'wallet',
					'status'         => 'credited',
					'transaction_id' => $tx_id,
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);

			$wpdb->query( 'COMMIT' );

			Asset_Wallet_Audit::log(
				'purchase_credited',
				'transaction',
				$tx_id,
				null,
				array(
					'order_id' => $order->get_id(),
					'asset_id' => $asset->id,
					'quantity' => $quantity,
				),
				$user_id = $order->get_user_id()
			);

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			Asset_Wallet_Helpers::log( 'Purchase credit failed', array(
				'order_id' => $order->get_id(),
				'item_id'  => $item_id,
				'error'    => $e->getMessage(),
			) );
		}
	}

	/**
	 * Handle refund
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public function handle_refund( $order_id, $refund_id ) {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );

		if ( ! $order || ! $refund ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		$account = Asset_Wallet_Accounts::get_by_user( $user_id );
		if ( ! $account ) {
			return;
		}

		global $wpdb;
		$orders_table = Asset_Wallet_Database::table( 'orders' );

		// Find credited items for this order
		$credited = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table} WHERE order_id = %d AND status = 'credited'",
				$order_id
			)
		);

		if ( empty( $credited ) ) {
			return;
		}

		foreach ( $credited as $row ) {
			// Check if asset still available
			$available = Asset_Wallet_Balances::get_available( $account->id, $row->asset_id );

			if ( Asset_Wallet_Helpers::decimal_cmp( $available, $row->quantity ) >= 0 ) {
				// Can reverse
				$wpdb->query( 'START TRANSACTION' );

				try {
					$balance_row = Asset_Wallet_Balances::get( $account->id, $row->asset_id, true );
					$balance_before = $balance_row ? $balance_row->quantity : '0';

					$decreased = Asset_Wallet_Balances::decrease( $account->id, $row->asset_id, $row->quantity );
					if ( is_wp_error( $decreased ) ) {
						throw new Exception( $decreased->get_error_message() );
					}

					$balance_after = Asset_Wallet_Helpers::decimal_sub( $balance_before, $row->quantity );

					Asset_Wallet_Transactions::create(
						array(
							'account_id'     => $account->id,
							'asset_id'       => $row->asset_id,
							'order_id'       => $order_id,
							'order_item_id'  => $row->order_item_id,
							'type'           => 'refund',
							'quantity'       => $row->quantity,
							'unit'           => 'piece',
							'balance_before' => $balance_before,
							'balance_after'  => $balance_after,
							'reference'      => 'refund_' . $refund_id,
							'description'    => sprintf( __( 'بازگشت وجه سفارش #%s', 'asset-wallet' ), $order->get_order_number() ),
							'status'         => 'completed',
						)
					);

					$wpdb->update(
						$orders_table,
						array( 'status' => 'refunded', 'updated_at' => current_time( 'mysql' ) ),
						array( 'id' => $row->id ),
						array( '%s', '%s' ),
						array( '%d' )
					);

					$wpdb->query( 'COMMIT' );

				} catch ( Exception $e ) {
					$wpdb->query( 'ROLLBACK' );
					Asset_Wallet_Helpers::log( 'Refund reverse failed', array( 'error' => $e->getMessage() ) );
				}
			} else {
				// Asset already sold or reserved → mark for admin review
				$wpdb->update(
					$orders_table,
					array( 'status' => 'pending_review', 'updated_at' => current_time( 'mysql' ) ),
					array( 'id' => $row->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				Asset_Wallet_Helpers::log( 'Refund requires admin review - asset not fully available', array(
					'order_id' => $order_id,
					'asset_id' => $row->asset_id,
					'available' => $available,
					'needed'    => $row->quantity,
				) );
			}
		}
	}
}
