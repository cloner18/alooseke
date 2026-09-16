<?php
defined( 'ABSPATH' ) || exit;

class AWT_History {

	public static function get_items( $user_id, $limit = 50 ) {
		$user_id = absint( $user_id );
		$items   = array();

		// 1) انتقال‌ها (ارسال / دریافت)
		global $wpdb;
		$t = $wpdb->prefix . 'asset_wallet_transfers';
		$transfers = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t}
			 WHERE status = 'completed'
			 AND (sender_user_id = %d OR receiver_user_id = %d)
			 ORDER BY completed_at DESC LIMIT %d",
			$user_id, $user_id, $limit
		) );

	foreach ( $transfers as $tr ) {
	$is_out = ( (int) $tr->sender_user_id === $user_id );
	$other_mobile = $is_out ? $tr->receiver_mobile : $tr->sender_mobile;
	$other_name   = $is_out ? $tr->receiver_full_name : $tr->sender_full_name;

	// شماره کامل (اگر خالی بود نام)
	$mobile_text = ! empty( $other_mobile ) ? $other_mobile : $other_name;

	if ( 'asset' === $tr->transfer_type ) {
		$items[] = array(
			'type'      => $is_out ? 'transfer_out' : 'transfer_in',
			'title'     => $is_out
				? 'انتقال به ' . $mobile_text
				: 'دریافت از ' . $mobile_text,
			'subtitle'  => $other_name,
			'amount'    => $tr->product_name . ' × ' . rtrim( rtrim( $tr->quantity, '0' ), '.' ),
			'direction' => $is_out ? 'out' : 'in',
			'trx'       => $tr->transaction_reference,
			'time'      => $tr->completed_at ?: $tr->created_at,
			'icon'      => $is_out ? 'transfer' : 'receive',
		);
	} else {
		$items[] = array(
			'type'      => $is_out ? 'wallet_out' : 'wallet_in',
			'title'     => $is_out
				? 'انتقال به ' . $mobile_text
				: 'دریافت از ' . $mobile_text,
			'subtitle'  => $other_name,
			'amount'    => number_format_i18n( (float) $tr->amount ) . ' تومان',
			'direction' => $is_out ? 'out' : 'in',
			'trx'       => $tr->transaction_reference,
			'time'      => $tr->completed_at ?: $tr->created_at,
			'icon'      => $is_out ? 'transfer' : 'receive',
		);
	}
}

		// 2) خرید / فروش دارایی از Ledger
		if ( class_exists( 'Asset_Wallet_Accounts' ) && class_exists( 'Asset_Wallet_Database' ) ) {
			$acc = Asset_Wallet_Accounts::get_by_user( $user_id );
			if ( $acc ) {
				$tx_table = Asset_Wallet_Database::table( 'transactions' );
				$assets_t = Asset_Wallet_Database::table( 'assets' );
				$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT t.*, a.name AS asset_name
	 FROM {$tx_table} t
	 LEFT JOIN {$assets_t} a ON a.id = t.asset_id
	 WHERE t.account_id = %d
	 AND t.type IN ('purchase','sell')
	 AND t.status = 'completed'
	 ORDER BY t.id DESC LIMIT %d",
	$acc->id, $limit
) );
// فقط خریدها از ledger
foreach ( (array) $rows as $r ) {
	if ( 'purchase' !== $r->type ) {
		continue; // فروش را از sale_requests می‌گیریم
	}

	$time = '';
	if ( ! empty( $r->order_id ) && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( $r->order_id );
		if ( $order && $order->get_date_created() ) {
			$time = $order->get_date_created()->date( 'Y-m-d H:i:s' );
		}
	}
	if ( empty( $time ) && ! empty( $r->created_at ) && $r->created_at !== '0000-00-00 00:00:00' ) {
		$time = $r->created_at;
	}

	$qty  = rtrim( rtrim( (string) $r->quantity, '0' ), '.' );
	$name = ! empty( $r->asset_name ) ? $r->asset_name : 'دارایی';

	$items[] = array(
		'type'      => 'purchase',
		'title'     => 'خرید دارایی',
		'subtitle'  => $name,
		'amount'    => $name . ' × ' . $qty,
		'direction' => 'in',
		'trx'       => ! empty( $r->reference ) ? $r->reference : ( 'BUY-' . $r->id ),
		'time'      => $time,
		'icon'      => 'purchase',
	);
}
	// فروش‌ها از جدول sale_requests (Source of Truth تاریخ)
$sale_table = $wpdb->prefix . 'asset_wallet_sale_requests';
$sales = $wpdb->get_results( $wpdb->prepare(
	"SELECT s.*, a.name AS asset_name
	 FROM {$sale_table} s
	 LEFT JOIN {$wpdb->prefix}asset_wallet_assets a ON a.id = s.asset_id
	 WHERE s.user_id = %d
	 AND s.status = 'completed'
	 ORDER BY s.completed_at DESC
	 LIMIT %d",
	$user_id,
	$limit
) );

// اگر ستون user_id نداشت، از account_id استفاده کنید:
if ( empty( $sales ) && ! empty( $acc ) ) {
	$sales = $wpdb->get_results( $wpdb->prepare(
		"SELECT s.*, a.name AS asset_name
		 FROM {$sale_table} s
		 LEFT JOIN {$wpdb->prefix}asset_wallet_assets a ON a.id = s.asset_id
		 WHERE s.account_id = %d
		 AND s.status = 'completed'
		 ORDER BY COALESCE(NULLIF(s.completed_at,'0000-00-00 00:00:00'), s.created_at) DESC
		 LIMIT %d",
		$acc->id,
		$limit
	) );
}

foreach ( (array) $sales as $s ) {
	$time = '';
	if ( ! empty( $s->completed_at ) && $s->completed_at !== '0000-00-00 00:00:00' ) {
		$time = $s->completed_at;
	} elseif ( ! empty( $s->created_at ) && $s->created_at !== '0000-00-00 00:00:00' ) {
		$time = $s->created_at;
	}

	$qty  = rtrim( rtrim( (string) $s->quantity, '0' ), '.' );
	$name = ! empty( $s->asset_name ) ? $s->asset_name : 'دارایی';
	$val  = isset( $s->total_amount ) ? (float) $s->total_amount : ( isset( $s->amount ) ? (float) $s->amount : 0 );

	$items[] = array(
		'type'      => 'sell',
		'title'     => 'فروش دارایی',
		'subtitle'  => $name,
		'amount'    => $val > 0
			? number_format_i18n( $val ) . ' تومان'
			: ( $name . ' × ' . $qty ),
		'direction' => 'out',
		'trx'       => ! empty( $s->idempotency_key ) ? $s->idempotency_key : ( 'SELL-' . $s->id ),
		'time'      => $time,
		'icon'      => 'sell',
	);
}
			}
		}

		// 3) افزایش موجودی TeraWallet (اگر جدول موجود باشد)
		$wallet_table = $wpdb->prefix . 'woo_wallet_transactions';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wallet_table ) );
		if ( $exists === $wallet_table ) {
			$wrows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wallet_table}
				 WHERE user_id = %d AND type = 'credit'
				 AND (details LIKE %s OR details LIKE %s OR details = '' OR details IS NULL)
				 ORDER BY transaction_id DESC LIMIT %d",
				$user_id,
				'%افزایش%',
				'%recharge%',
				$limit
			) );
			// ساده‌تر: همه creditهایی که از transfer ما نیستند
			$wrows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wallet_table}
				 WHERE user_id = %d AND type = 'credit'
				 ORDER BY transaction_id DESC LIMIT %d",
				$user_id, $limit
			) );

			foreach ( $wrows as $w ) {
				$details = isset( $w->details ) ? $w->details : '';
				// رد کردن creditهای مربوط به انتقال داخلی خودمان
				if ( strpos( $details, 'دریافت از' ) !== false || strpos( $details, 'transfer_credit' ) !== false ) {
					continue;
				}
				if ( strpos( $details, 'فروش دارایی' ) !== false ) {
					continue; // فروش جدا در ledger هست
				}
				$items[] = array(
					'type'      => 'topup',
					'title'     => 'افزایش موجودی',
					'subtitle'  => $details ?: 'شارژ کیف پول',
					'amount'    => number_format_i18n( (float) $w->amount ) . ' تومان',
					'direction' => 'in',
					'trx'       => 'TW-' . $w->transaction_id,
					'time'      => $w->date ?: $w->created_at,
					'icon'      => 'topup',
				);
			}
		}
         // 4) برداشت‌های تایید شده
$wd_table = $wpdb->prefix . 'asset_wallet_withdrawals';
$wd_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wd_table ) );

if ( $wd_exists === $wd_table ) {
	$withdrawals = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wd_table}
		 WHERE user_id = %d AND status = 'approved'
		 ORDER BY COALESCE(approved_at, created_at) DESC
		 LIMIT %d",
		$user_id,
		$limit
	) );

	foreach ( (array) $withdrawals as $w ) {
		$time = '';
		if ( ! empty( $w->approved_at ) && $w->approved_at !== '0000-00-00 00:00:00' ) {
			$time = $w->approved_at;
		} elseif ( ! empty( $w->created_at ) && $w->created_at !== '0000-00-00 00:00:00' ) {
			$time = $w->created_at;
		}

		if ( 'wallet' === $w->type ) {
			$items[] = array(
				'type'      => 'withdraw_wallet',
				'title'     => 'برداشت موجودی',
				'subtitle'  => ! empty( $w->card_number ) ? $w->card_number : '',
				'amount'    => number_format_i18n( (float) $w->amount ) . ' تومان',
				'direction' => 'out',
				'trx'       => $w->tracking_code,
				'time'      => $time,
				'icon'      => 'withdraw',
			);
		} else {
			$qty  = rtrim( rtrim( (string) $w->quantity, '0' ), '.' );
			$name = ! empty( $w->product_name ) ? $w->product_name : 'دارایی';
			$items[] = array(
				'type'      => 'withdraw_asset',
				'title'     => 'برداشت دارایی',
				'subtitle'  => $name,
				'amount'    => $name . ' × ' . $qty,
				'direction' => 'out',
				'trx'       => $w->tracking_code,
				'time'      => $time,
				'icon'      => 'withdraw',
			);
		}
	}
}
		// مرتب‌سازی بر اساس زمان
		usort( $items, function ( $a, $b ) {
			return strtotime( $b['time'] ) - strtotime( $a['time'] );
		} );

		return array_slice( $items, 0, $limit );
	}

	public static function mask( $mobile ) {
		$mobile = preg_replace( '/[^0-9]/', '', (string) $mobile );
		if ( strlen( $mobile ) < 8 ) {
			return $mobile;
		}
		return substr( $mobile, 0, 4 ) . '***' . substr( $mobile, -2 );
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

	// نام روز هفته (date('w'): 0=یکشنبه)
	$days = array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );
	$day_name = $days[ (int) date( 'w', $ts ) ];

	// تاریخ شمسی با نام ماه از کلاس خودتان
	if ( class_exists( 'Zarnegar_Shamsi_Date_Service' ) ) {
		$gy = (int) date( 'Y', $ts );
		$gm = (int) date( 'm', $ts );
		$gd = (int) date( 'd', $ts );

		$j_text = Zarnegar_Shamsi_Date_Service::convertToJalaliWithMonthName( $gy, $gm, $gd );
		// خروجی مثل: 24 شهریور 1405

		return $day_name . '، ' . $j_text . '، ' . $time;
	}

	// fallback
	if ( class_exists( 'Zarnegar_Shamsi_Date_Service' ) ) {
		$j = Zarnegar_Shamsi_Date_Service::convertToJalali( $ts, '/' );
		return $day_name . '، ' . $j . '، ' . $time;
	}

	return date( 'Y/m/d', $ts ) . '، ' . $time;
}
}