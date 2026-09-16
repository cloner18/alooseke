<?php
/**
 * OTP management for sale confirmation
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_OTP {

	/**
	 * Create and send OTP for sale
	 *
	 * @param int $user_id         User ID.
	 * @param int $sale_request_id Sale request ID.
	 * @return true|WP_Error
	 */
	public static function create_and_send( $user_id, $sale_request_id ) {
		$user_id = absint( $user_id );
		$sale_request_id = absint( $sale_request_id );

		$phone = Asset_Wallet_Helpers::get_user_phone( $user_id );
		if ( ! $phone ) {
			return new WP_Error( 'no_phone', __( 'شماره موبایل معتبر برای حساب کاربری یافت نشد.', 'asset-wallet' ) );
		}

		// Rate limiting
		$rate_check = self::check_rate_limit( $user_id );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Invalidate previous active OTPs for this action
		self::invalidate_previous( $user_id, 'sell_asset' );

		$otp_code = Asset_Wallet_Helpers::generate_otp( 6 );
		$otp_hash = Asset_Wallet_Helpers::hash_otp( $otp_code );

		$expire_seconds = (int) Asset_Wallet_Helpers::get_option( 'otp_expire_seconds', 120 );
		$max_attempts   = (int) Asset_Wallet_Helpers::get_option( 'otp_max_attempts', 5 );

		global $wpdb;
		$table = Asset_Wallet_Database::table( 'otp' );
		$now   = current_time( 'mysql' );
		$expires = date( 'Y-m-d H:i:s', strtotime( $now ) + $expire_seconds );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'         => $user_id,
				'action'          => 'sell_asset',
				'phone'           => $phone,
				'otp_hash'        => $otp_hash,
				'expires_at'      => $expires,
				'attempts'        => 0,
				'max_attempts'    => $max_attempts,
				'status'          => 'active',
				'sale_request_id' => $sale_request_id,
				'created_at'      => $now,
				'ip_address'      => Asset_Wallet_Helpers::get_client_ip(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'otp_create_failed', __( 'ایجاد کد تأیید با خطا مواجه شد.', 'asset-wallet' ) );
		}

		// Send SMS using existing theme function
		if ( ! function_exists( 'zarnegar_send_sms_ir' ) ) {
			Asset_Wallet_Helpers::log( 'SMS function zarnegar_send_sms_ir not found' );
			return new WP_Error( 'sms_function_missing', __( 'تابع ارسال پیامک در قالب یافت نشد. لطفاً با پشتیبانی تماس بگیرید.', 'asset-wallet' ) );
		}

		$template_id = (int) Asset_Wallet_Helpers::get_option( 'sms_template_id', 604197 );
		$param_name  = Asset_Wallet_Helpers::get_option( 'sms_otp_param_name', 'code' );

		$parameters = array( $param_name => $otp_code );

		$sent = zarnegar_send_sms_ir( $phone, $template_id, $parameters );

		if ( false === $sent || is_wp_error( $sent ) ) {
			Asset_Wallet_Helpers::log( 'SMS send failed', array( 'phone' => substr( $phone, 0, 4 ) . '****', 'result' => $sent ) );
			// Still return success for OTP creation, but log the error
			// We don't fail the whole process if SMS fails temporarily
		}

		return true;
	}

	/**
	 * Verify OTP
	 *
	 * @param int    $user_id         User ID.
	 * @param int    $sale_request_id Sale request ID.
	 * @param string $otp_code        OTP code.
	 * @return true|WP_Error
	 */
	public static function verify( $user_id, $sale_request_id, $otp_code ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'otp' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE user_id = %d AND sale_request_id = %d AND action = 'sell_asset' AND status = 'active'
				 ORDER BY id DESC LIMIT 1",
				absint( $user_id ),
				absint( $sale_request_id )
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'otp_not_found', __( 'کد تأیید فعال یافت نشد. لطفاً مجدداً درخواست دهید.', 'asset-wallet' ) );
		}

		if ( strtotime( $row->expires_at ) < time() ) {
			$wpdb->update( $table, array( 'status' => 'expired' ), array( 'id' => $row->id ), array( '%s' ), array( '%d' ) );
			return new WP_Error( 'otp_expired', __( 'کد تأیید منقضی شده است. لطفاً مجدداً درخواست دهید.', 'asset-wallet' ) );
		}

		if ( (int) $row->attempts >= (int) $row->max_attempts ) {
			$wpdb->update( $table, array( 'status' => 'locked' ), array( 'id' => $row->id ), array( '%s' ), array( '%d' ) );
			return new WP_Error( 'otp_locked', __( 'تعداد تلاش‌های مجاز تمام شده است. لطفاً مجدداً درخواست دهید.', 'asset-wallet' ) );
		}

		// Increment attempts
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET attempts = attempts + 1 WHERE id = %d",
				$row->id
			)
		);

		if ( ! Asset_Wallet_Helpers::verify_otp( $otp_code, $row->otp_hash ) ) {
			$remaining = (int) $row->max_attempts - ( (int) $row->attempts + 1 );
			return new WP_Error(
				'otp_invalid',
				sprintf( __( 'کد تأیید نادرست است. %d تلاش باقی مانده.', 'asset-wallet' ), max( 0, $remaining ) )
			);
		}

		// Success: mark as used
		$wpdb->update(
			$table,
			array(
				'status'      => 'used',
				'verified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $row->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Invalidate previous active OTPs
	 *
	 * @param int    $user_id User ID.
	 * @param string $action  Action.
	 */
	public static function invalidate_previous( $user_id, $action ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'otp' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired' WHERE user_id = %d AND action = %s AND status = 'active'",
				absint( $user_id ),
				sanitize_key( $action )
			)
		);
	}

	/**
	 * Rate limit check
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
public static function check_rate_limit( $user_id ) {
	global $wpdb;
	$table = Asset_Wallet_Database::table( 'otp' );

	$cooldown  = (int) Asset_Wallet_Helpers::get_option( 'otp_resend_cooldown', 12 );
	$window    = (int) Asset_Wallet_Helpers::get_option( 'otp_max_requests_window', 60 );
	$max_count = (int) Asset_Wallet_Helpers::get_option( 'otp_max_requests_count', 5 );

	$now_mysql = current_time( 'mysql' );
	$now_ts    = current_time( 'timestamp' );

	$last = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT created_at FROM {$table}
			 WHERE user_id = %d AND action = 'sell_asset'
			 ORDER BY id DESC LIMIT 1",
			absint( $user_id )
		)
	);

	if ( $last ) {
		$last_ts = strtotime( $last );
		$diff    = $now_ts - $last_ts;

		if ( $diff >= 0 && $diff < $cooldown ) {
			$wait = $cooldown - $diff;
			return new WP_Error(
				'otp_cooldown',
				sprintf( __( 'لطفاً %d ثانیه صبر کنید و سپس مجدداً درخواست دهید.', 'asset-wallet' ), $wait )
			);
		}
	}

	$window_start = date( 'Y-m-d H:i:s', $now_ts - $window );

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			 WHERE user_id = %d AND action = 'sell_asset' AND created_at > %s",
			absint( $user_id ),
			$window_start
		)
	);

	if ( $count >= $max_count ) {
		return new WP_Error(
			'otp_rate_limit',
			__( 'تعداد درخواست‌های کد تأیید بیش از حد مجاز است. لطفاً یک دقیقه صبر کنید.', 'asset-wallet' )
		);
	}

	return true;
}
}
