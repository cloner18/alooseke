<?php
/**
 * Helper functions
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Helpers {

	/**
	 * Format quantity for display
	 *
	 * @param string|float $qty  Quantity.
	 * @param string       $unit Unit.
	 * @return string
	 */
	public static function format_quantity( $qty, $unit = 'piece' ) {
	$qty = (float) $qty;

	if ( 'gram' === $unit ) {
		$formatted = rtrim( rtrim( number_format( $qty, 8, '.', '' ), '0' ), '.' );
		return $formatted . ' ' . __( 'گرم', 'asset-wallet' );
	}

	return number_format_i18n( $qty, 0 ) . ' ' . __( 'عدد', 'asset-wallet' );
}

	/**
	 * Format price with WooCommerce
	 *
	 * @param float  $price    Price.
	 * @param string $currency Currency.
	 * @return string
	 */
	public static function format_price( $price, $currency = '' ) {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $price );
		}
		return number_format_i18n( (float) $price, 0 ) . ' ' . ( $currency ?: 'تومان' );
	}

	/**
	 * Get current user phone (validated)
	 *
	 * @param int $user_id User ID.
	 * @return string|false
	 */
	public static function get_user_phone( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// Priority: billing_phone (WooCommerce)
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user_id, 'phone', true );
		}
		if ( empty( $phone ) ) {
			$user = get_userdata( $user_id );
			if ( $user && ! empty( $user->user_login ) && preg_match( '/^09\d{9}$/', $user->user_login ) ) {
				$phone = $user->user_login;
			}
		}

		$phone = self::normalize_phone( $phone );
		return $phone ? $phone : false;
	}

	/**
	 * Normalize Iranian phone number
	 *
	 * @param string $phone Phone.
	 * @return string|false
	 */
	public static function normalize_phone( $phone ) {
		$phone = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( empty( $phone ) ) {
			return false;
		}
		// Convert +98 or 98 to 0
		if ( strpos( $phone, '98' ) === 0 && strlen( $phone ) === 12 ) {
			$phone = '0' . substr( $phone, 2 );
		}
		if ( strlen( $phone ) === 10 && strpos( $phone, '9' ) === 0 ) {
			$phone = '0' . $phone;
		}
		if ( preg_match( '/^09\d{9}$/', $phone ) ) {
			return $phone;
		}
		return false;
	}

	/**
	 * Generate secure OTP
	 *
	 * @param int $length Length.
	 * @return string
	 */
	public static function generate_otp( $length = 6 ) {
		$min = (int) str_pad( '1', $length, '0' );
		$max = (int) str_pad( '', $length, '9' );
		return (string) wp_rand( $min, $max );
	}

	/**
	 * Hash OTP
	 *
	 * @param string $otp OTP.
	 * @return string
	 */
	public static function hash_otp( $otp ) {
		return password_hash( (string) $otp, PASSWORD_DEFAULT );
	}

	/**
	 * Verify OTP hash
	 *
	 * @param string $otp  OTP.
	 * @param string $hash Hash.
	 * @return bool
	 */
	public static function verify_otp( $otp, $hash ) {
		return password_verify( (string) $otp, $hash );
	}

	/**
	 * Get client IP
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}

	/**
	 * Log error
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public static function log( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Asset Wallet] ' . $message . ( $context ? ' | ' . wp_json_encode( $context ) : '' ) );
		}

		// Optional: store in custom log table or option (limited)
		$logs = get_option( 'asset_wallet_error_logs', array() );
		$logs[] = array(
			'message'   => $message,
			'context'   => $context,
			'timestamp' => current_time( 'mysql' ),
		);
		// Keep last 100
		$logs = array_slice( $logs, -100 );
		update_option( 'asset_wallet_error_logs', $logs, false );
	}

	/**
	 * Get setting
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get_option( $key, $default = null ) {
		return get_option( 'asset_wallet_' . $key, $default );
	}

	/**
	 * Safe decimal compare
	 *
	 * @param string|float $a A.
	 * @param string|float $b B.
	 * @return int -1, 0, 1
	 */
	public static function decimal_cmp( $a, $b ) {
		$a = (string) $a;
		$b = (string) $b;
		if ( function_exists( 'bccomp' ) ) {
			return bccomp( $a, $b, 8 );
		}
		$fa = (float) $a;
		$fb = (float) $b;
		if ( abs( $fa - $fb ) < 0.00000001 ) {
			return 0;
		}
		return $fa < $fb ? -1 : 1;
	}

	/**
	 * Add decimals safely
	 *
	 * @param string|float $a A.
	 * @param string|float $b B.
	 * @return string
	 */
	public static function decimal_add( $a, $b ) {
		if ( function_exists( 'bcadd' ) ) {
			return bcadd( (string) $a, (string) $b, 8 );
		}
		return number_format( (float) $a + (float) $b, 8, '.', '' );
	}

	/**
	 * Subtract decimals safely
	 *
	 * @param string|float $a A.
	 * @param string|float $b B.
	 * @return string
	 */
	public static function decimal_sub( $a, $b ) {
		if ( function_exists( 'bcsub' ) ) {
			return bcsub( (string) $a, (string) $b, 8 );
		}
		return number_format( (float) $a - (float) $b, 8, '.', '' );
	}

	/**
	 * Multiply
	 *
	 * @param string|float $a A.
	 * @param string|float $b B.
	 * @return string
	 */
	public static function decimal_mul( $a, $b ) {
		if ( function_exists( 'bcmul' ) ) {
			return bcmul( (string) $a, (string) $b, 8 );
		}
		return number_format( (float) $a * (float) $b, 8, '.', '' );
	}

	/**
	 * Generate idempotency key
	 *
	 * @return string
	 */
	public static function generate_idempotency_key() {
		return wp_generate_uuid4();
	}

	/**
	 * Get buy price from product/variation (Source of Truth)
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return float|false
	 */
	public static function get_buy_price( $product_id, $variation_id = 0 ) {
		$price = false;

		if ( $variation_id ) {
			$price = get_post_meta( $variation_id, '_buy_price', true );
		}

		if ( ( empty( $price ) || ! is_numeric( $price ) ) && $product_id ) {
			$price = get_post_meta( $product_id, '_buy_price', true );
		}

		if ( empty( $price ) || ! is_numeric( $price ) || (float) $price <= 0 ) {
			return false;
		}

		return (float) $price;
	}
}
