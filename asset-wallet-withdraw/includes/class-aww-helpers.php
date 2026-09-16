<?php
defined( 'ABSPATH' ) || exit;

class AWW_Helpers {

	public static function tracking_code() {
		return (string) ( time() . wp_rand( 100, 999 ) );
	}

	public static function user_snapshot( $user_id ) {
		$user  = get_userdata( $user_id );
		$first = get_user_meta( $user_id, 'first_name', true );
		$last  = get_user_meta( $user_id, 'last_name', true );
		$full  = trim( $first . ' ' . $last );
		if ( empty( $full ) && $user ) {
			$full = $user->display_name;
		}
		$mobile = '';
		if ( class_exists( 'Asset_Wallet_Helpers' ) ) {
			$mobile = Asset_Wallet_Helpers::get_user_phone( $user_id );
		} else {
			$mobile = get_user_meta( $user_id, 'billing_phone', true );
		}
		return array(
			'full_name' => $full,
			'mobile'    => $mobile ?: '',
			'first'     => $first,
			'last'      => $last,
		);
	}

	public static function get_user_cards( $user_id ) {
	// روش اصلی سایت شما
	if ( class_exists( 'Zarnegar_User_Service' ) && method_exists( 'Zarnegar_User_Service', 'userCards' ) ) {
		$cards = Zarnegar_User_Service::userCards( $user_id );
		return is_array( $cards ) ? $cards : array();
	}

	// fallback مستقیم از meta
	$raw = get_user_meta( $user_id, '_user_cards', true );
	if ( empty( $raw ) ) {
		return array();
	}
	if ( is_array( $raw ) ) {
		return $raw;
	}
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : array();
}

	public static function get_shipping( $user_id ) {
		return array(
			'full_name' => trim( get_user_meta( $user_id, 'shipping_first_name', true ) . ' ' . get_user_meta( $user_id, 'shipping_last_name', true ) ),
			'phone'     => get_user_meta( $user_id, 'shipping_phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true ),
			'address'   => get_user_meta( $user_id, 'shipping_address_1', true ),
			'city'      => get_user_meta( $user_id, 'shipping_city', true ),
			'state'     => get_user_meta( $user_id, 'shipping_state', true ),
			'postcode'  => get_user_meta( $user_id, 'shipping_postcode', true ),
		);
	}

	public static function has_shipping( $user_id ) {
		$s = self::get_shipping( $user_id );
		return ! empty( $s['address'] ) && ! empty( $s['city'] );
	}

	public static function wallet_balance( $user_id ) {
		if ( function_exists( 'woo_wallet' ) && isset( woo_wallet()->wallet ) && method_exists( woo_wallet()->wallet, 'get_wallet_balance' ) ) {
			return (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
		}
		return (float) get_user_meta( $user_id, '_current_woo_wallet_balance', true );
	}

	public static function wallet_debit( $user_id, $amount, $desc ) {
		if ( function_exists( 'woo_wallet' ) && method_exists( woo_wallet()->wallet, 'debit' ) ) {
			$tx = woo_wallet()->wallet->debit( $user_id, $amount, $desc );
			return $tx ? $tx : new WP_Error( 'debit_failed', 'کسر موجودی ناموفق بود.' );
		}
		return new WP_Error( 'no_api', 'API کیف پول در دسترس نیست.' );
	}

	public static function wallet_credit( $user_id, $amount, $desc ) {
		if ( class_exists( 'Asset_Wallet_TeraWallet' ) ) {
			return Asset_Wallet_TeraWallet::credit( $user_id, $amount, $desc, 'aww_refund_' . md5( $desc . $user_id . $amount ) );
		}
		if ( function_exists( 'woo_wallet' ) && method_exists( woo_wallet()->wallet, 'credit' ) ) {
			$tx = woo_wallet()->wallet->credit( $user_id, $amount, $desc );
			return $tx ? $tx : new WP_Error( 'credit_failed', 'بازگشت موجودی ناموفق بود.' );
		}
		return new WP_Error( 'no_api', 'API کیف پول در دسترس نیست.' );
	}

	public static function send_sms( $mobile, $template_id, $params ) {
		if ( ! function_exists( 'zarnegar_send_sms_ir' ) || empty( $mobile ) ) {
			return false;
		}
		return zarnegar_send_sms_ir( $mobile, (int) $template_id, $params );
	}

	public static function status_label( $status ) {
		$map = array(
			'pending'  => 'در انتظار تایید',
			'approved' => 'تایید شده',
			'rejected' => 'رد شده',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}
	public static function format_date( $mysql_datetime ) {
	if ( empty( $mysql_datetime ) || $mysql_datetime === '0000-00-00 00:00:00' ) {
		return '—';
	}

	$ts = is_numeric( $mysql_datetime ) ? (int) $mysql_datetime : strtotime( (string) $mysql_datetime );
	if ( ! $ts || $ts < 1 ) {
		return '—';
	}

	$time = date( 'H:i', $ts );
	$days = array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );
	$day_name = $days[ (int) date( 'w', $ts ) ];

	if ( class_exists( 'Zarnegar_Shamsi_Date_Service' ) ) {
		$gy = (int) date( 'Y', $ts );
		$gm = (int) date( 'm', $ts );
		$gd = (int) date( 'd', $ts );
		$j_text = Zarnegar_Shamsi_Date_Service::convertToJalaliWithMonthName( $gy, $gm, $gd );
		return $day_name . '، ' . $j_text . '، ' . $time;
	}

	return date( 'Y/m/d', $ts ) . '، ' . $time;
}
}
