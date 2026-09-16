<?php
/**
 * TeraWallet (WooCommerce Wallet) integration
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_TeraWallet {

	/**
	 * Credit user wallet using official API
	 *
	 * @param int    $user_id     User ID.
	 * @param float  $amount      Amount.
	 * @param string $description Description.
	 * @param string $reference   Idempotency reference.
	 * @return array|WP_Error|string Transaction ID or error.
	 */
	public static function credit( $user_id, $amount, $description = '', $reference = '' ) {
		$user_id = absint( $user_id );
		$amount  = (float) $amount;

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'مبلغ نامعتبر است.', 'asset-wallet' ) );
		}

		// Check if TeraWallet / woo-wallet is active
		if ( ! function_exists( 'woo_wallet' ) && ! class_exists( 'Woo_Wallet' ) ) {
			return new WP_Error( 'terawallet_missing', __( 'افزونه TeraWallet (WooCommerce Wallet) فعال نیست.', 'asset-wallet' ) );
		}

		// Idempotency: check if we already credited for this reference
		if ( $reference ) {
			$existing = get_user_meta( $user_id, '_asset_wallet_tera_ref_' . md5( $reference ), true );
			if ( $existing ) {
				return array(
					'transaction_id' => $existing,
					'already_credited' => true,
				);
			}
		}

		try {
			// Official way: woo_wallet()->wallet->credit()
			if ( function_exists( 'woo_wallet' ) && isset( woo_wallet()->wallet ) && method_exists( woo_wallet()->wallet, 'credit' ) ) {
				$tx_id = woo_wallet()->wallet->credit( $user_id, $amount, $description );
			} elseif ( function_exists( 'woo_wallet_credit' ) ) {
				$tx_id = woo_wallet_credit( $user_id, $amount, $description );
			} else {
				// Fallback for some versions
				$wallet = new Woo_Wallet_Wallet();
				if ( method_exists( $wallet, 'credit' ) ) {
					$tx_id = $wallet->credit( $user_id, $amount, $description );
				} else {
					return new WP_Error( 'terawallet_api', __( 'API اعتباردهی TeraWallet در دسترس نیست.', 'asset-wallet' ) );
				}
			}

			if ( ! $tx_id ) {
				return new WP_Error( 'credit_failed', __( 'اعتباردهی به کیف پول ریالی با خطا مواجه شد.', 'asset-wallet' ) );
			}

			// Store reference for idempotency
			if ( $reference ) {
				update_user_meta( $user_id, '_asset_wallet_tera_ref_' . md5( $reference ), $tx_id );
			}

			return array(
				'transaction_id' => $tx_id,
				'already_credited' => false,
			);

		} catch ( Exception $e ) {
			Asset_Wallet_Helpers::log( 'TeraWallet credit exception', array( 'error' => $e->getMessage() ) );
			return new WP_Error( 'credit_exception', $e->getMessage() );
		}
	}

	/**
	 * Check if TeraWallet is available
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'woo_wallet' ) || class_exists( 'Woo_Wallet' ) || function_exists( 'woo_wallet_credit' );
	}
}
