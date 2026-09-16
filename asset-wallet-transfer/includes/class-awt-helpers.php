<?php
defined( 'ABSPATH' ) || exit;

class AWT_Helpers {

	public static function normalize_phone( $phone ) {
		if ( class_exists( 'Asset_Wallet_Helpers' ) ) {
			return Asset_Wallet_Helpers::normalize_phone( $phone );
		}
		$phone = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( empty( $phone ) ) {
			return false;
		}
		if ( strpos( $phone, '98' ) === 0 && strlen( $phone ) === 12 ) {
			$phone = '0' . substr( $phone, 2 );
		}
		if ( strlen( $phone ) === 10 && strpos( $phone, '9' ) === 0 ) {
			$phone = '0' . $phone;
		}
		return preg_match( '/^09\d{9}$/', $phone ) ? $phone : false;
	}

	public static function get_user_phone( $user_id ) {
		if ( class_exists( 'Asset_Wallet_Helpers' ) ) {
			return Asset_Wallet_Helpers::get_user_phone( $user_id );
		}
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user_id, 'phone', true );
		}
		return self::normalize_phone( $phone );
	}

public static function is_authenticated( $user_id ) {
	$val = get_user_meta( absint( $user_id ), '_user_is_authenticated', true );

	// حالت‌های معتبر
	if ( true === $val || $val === 1 || $val === '1' || $val === 'true' || $val === 'yes' ) {
		return true;
	}

	// بعضی سایت‌ها مقدار را به صورت رشته خالی/صفر نگه می‌دارند
	return false;
}

	public static function mask_mobile( $mobile ) {
		$mobile = preg_replace( '/[^0-9]/', '', (string) $mobile );
		if ( strlen( $mobile ) < 8 ) {
			return '****';
		}
		return substr( $mobile, 0, 4 ) . '******' . substr( $mobile, -2 );
	}

	public static function user_snapshot( $user_id ) {
		$user  = get_userdata( $user_id );
		$first = get_user_meta( $user_id, 'first_name', true );
		$last  = get_user_meta( $user_id, 'last_name', true );
		if ( empty( $first ) && $user ) {
			$first = $user->display_name;
		}
		$full = trim( $first . ' ' . $last );
		if ( empty( $full ) && $user ) {
			$full = $user->display_name;
		}
		return array(
			'user_id'    => (int) $user_id,
			'first_name' => $first,
			'last_name'  => $last,
			'full_name'  => $full,
			'mobile'     => self::get_user_phone( $user_id ) ?: '',
		);
	}

	public static function generate_otp( $len = 6 ) {
		if ( class_exists( 'Asset_Wallet_Helpers' ) ) {
			return Asset_Wallet_Helpers::generate_otp( $len );
		}
		return (string) wp_rand( (int) str_pad( '1', $len, '0' ), (int) str_pad( '', $len, '9' ) );
	}

	public static function hash_otp( $otp ) {
		return password_hash( (string) $otp, PASSWORD_DEFAULT );
	}

	public static function verify_otp( $otp, $hash ) {
		return password_verify( (string) $otp, $hash );
	}

	public static function trx_ref() {
	return (string) ( time() . wp_rand( 100, 999 ) );
}

	public static function log( $msg, $ctx = array() ) {
		if ( class_exists( 'Asset_Wallet_Helpers' ) ) {
			Asset_Wallet_Helpers::log( '[Transfer] ' . $msg, $ctx );
			return;
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AWT] ' . $msg . ( $ctx ? ' ' . wp_json_encode( $ctx ) : '' ) );
		}
	}

	public static function client_ip() {
		if ( class_exists( 'Asset_Wallet_Helpers' ) ) {
			return Asset_Wallet_Helpers::get_client_ip();
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
