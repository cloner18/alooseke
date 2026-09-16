<?php
defined( 'ABSPATH' ) || exit;

class AWW_Service {

	public static function create( $args ) {
		$user_id = absint( $args['user_id'] ?? 0 );
		$type    = sanitize_key( $args['type'] ?? '' ); // wallet | asset

		if ( ! $user_id || ! in_array( $type, array( 'wallet', 'asset' ), true ) ) {
			return new WP_Error( 'invalid', 'درخواست نامعتبر است.' );
		}

		$snap = AWW_Helpers::user_snapshot( $user_id );
		$data = array(
			'tracking_code' => AWW_Helpers::tracking_code(),
			'user_id'       => $user_id,
			'type'          => $type,
			'status'        => 'pending',
			'user_full_name'=> $snap['full_name'],
			'user_mobile'   => $snap['mobile'],
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		if ( 'wallet' === $type ) {
			$amount  = (float) ( $args['amount'] ?? 0 );
			$card_id = sanitize_text_field( $args['card_id'] ?? '' );
			if ( $amount <= 0 ) {
				return new WP_Error( 'invalid_amount', 'مبلغ نامعتبر است.' );
			}
			$balance = AWW_Helpers::wallet_balance( $user_id );
			if ( $balance < $amount ) {
				return new WP_Error( 'insufficient', 'موجودی کیف پول کافی نیست.' );
			}
			$cards = AWW_Helpers::get_user_cards( $user_id );
			if ( empty( $cards ) ) {
				return new WP_Error( 'no_card', 'کارت بانکی ثبت نشده است.' );
			}
			$card = self::find_card( $cards, $card_id );
			if ( ! $card ) {
				return new WP_Error( 'invalid_card', 'کارت انتخاب‌شده نامعتبر است.' );
			}

			// Debit immediately
			$debit = AWW_Helpers::wallet_debit( $user_id, $amount, 'برداشت موجودی - ' . $data['tracking_code'] );
			if ( is_wp_error( $debit ) ) {
				return $debit;
			}

			$data['amount']      = number_format( $amount, 4, '.', '' );
			$data['currency']    = 'IRT';
			$data['card_id']     = $card_id;
			$data['card_number'] = self::card_field( $card, array( 'cardNo', 'number', 'card_number', 'card' ) );
$data['card_sheba']  = self::card_field( $card, array( 'shebaNo', 'sheba', 'shaba', 'iban' ) );
$data['card_bank']   = self::card_field( $card, array( 'bankName', 'bank', 'bank_name' ) );
$data['card_holder'] = self::card_field( $card, array( 'holder', 'owner', 'name' ) );
			$data['tera_tx_id']  = is_array( $debit ) ? ( $debit['transaction_id'] ?? '' ) : (string) $debit;

		} else {
			// asset physical withdraw
			if ( ! class_exists( 'Asset_Wallet_Balances' ) || ! class_exists( 'Asset_Wallet_Accounts' ) ) {
				return new WP_Error( 'dependency', 'پلاگین Asset Wallet فعال نیست.' );
			}
			if ( ! AWW_Helpers::has_shipping( $user_id ) ) {
				return new WP_Error( 'no_address', 'آدرس ارسال ثبت نشده است.' );
			}
			$asset_id = absint( $args['asset_id'] ?? 0 );
			$qty      = number_format( (float) ( $args['quantity'] ?? 0 ), 8, '.', '' );
			if ( ! $asset_id || (float) $qty <= 0 ) {
				return new WP_Error( 'invalid_qty', 'مقدار دارایی نامعتبر است.' );
			}
			$acc = Asset_Wallet_Accounts::get_or_create( $user_id );
			$available = Asset_Wallet_Balances::get_available( $acc->id, $asset_id );
			if ( class_exists( 'Asset_Wallet_Helpers' ) && Asset_Wallet_Helpers::decimal_cmp( $available, $qty ) < 0 ) {
				return new WP_Error( 'insufficient_asset', 'موجودی دارایی کافی نیست.' );
			}
			$asset = class_exists( 'Asset_Wallet_Assets' ) ? Asset_Wallet_Assets::get( $asset_id ) : null;
			if ( ! $asset ) {
				return new WP_Error( 'invalid_asset', 'دارایی نامعتبر است.' );
			}

			// Decrease balance immediately
			$before = Asset_Wallet_Balances::get( $acc->id, $asset_id, true );
			$before_q = $before ? $before->quantity : '0';
			$dec = Asset_Wallet_Balances::decrease( $acc->id, $asset_id, $qty );
			if ( is_wp_error( $dec ) ) {
				return $dec;
			}
			$after_q = Asset_Wallet_Helpers::decimal_sub( $before_q, $qty );

			$ledger_id = null;
			if ( class_exists( 'Asset_Wallet_Transactions' ) ) {
				$ledger_id = Asset_Wallet_Transactions::create( array(
					'account_id'     => $acc->id,
					'asset_id'       => $asset_id,
					'type'           => 'withdraw',
					'quantity'       => $qty,
					'unit'           => $asset->unit,
					'balance_before' => $before_q,
					'balance_after'  => $after_q,
					'reference'      => $data['tracking_code'],
					'description'    => 'درخواست برداشت دارایی - در انتظار تایید',
					'status'         => 'completed',
					'created_by'     => $user_id,
				) );
			}

			$ship = AWW_Helpers::get_shipping( $user_id );
			$data['account_id']         = $acc->id;
			$data['asset_id']           = $asset_id;
			$data['product_id']         = $asset->product_id;
			$data['variation_id']       = $asset->variation_id;
			$data['product_name']       = $asset->name;
			$data['quantity']           = $qty;
			$data['unit']               = $asset->unit;
			$data['shipping_full_name'] = $ship['full_name'];
			$data['shipping_phone']     = $ship['phone'];
			$data['shipping_address']   = $ship['address'];
			$data['shipping_city']      = $ship['city'];
			$data['shipping_state']     = $ship['state'];
			$data['shipping_postcode']  = $ship['postcode'];
			$data['ledger_tx_id']       = $ledger_id;
		}

		global $wpdb;
		$table = AWW_Database::table();
		$ok = $wpdb->insert( $table, $data );
		if ( ! $ok ) {
			// compensate
			self::compensate_failed_insert( $type, $user_id, $data );
			return new WP_Error( 'db', 'ثبت درخواست ناموفق بود.' );
		}
		$id = $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		self::sms_on_create( $row );

		return array(
			'id'            => $id,
			'tracking_code' => $data['tracking_code'],
			'message'       => 'درخواست برداشت ثبت شد و در انتظار تایید است.',
		);
	}

	private static function compensate_failed_insert( $type, $user_id, $data ) {
		if ( 'wallet' === $type && ! empty( $data['amount'] ) ) {
			AWW_Helpers::wallet_credit( $user_id, (float) $data['amount'], 'برگشت برداشت ناموفق - ' . $data['tracking_code'] );
		}
		// asset compensation would need increase back - rare path
	}

	private static function find_card( $cards, $card_id ) {
		foreach ( $cards as $i => $c ) {
			$id = isset( $c['id'] ) ? (string) $c['id'] : (string) $i;
			if ( (string) $card_id === $id ) {
				return $c;
			}
			// match by number
			$num = self::card_field( $c, array( 'number', 'card_number', 'card' ) );
			if ( $num && $num === $card_id ) {
				return $c;
			}
		}
		// if only one card and card_id empty
		if ( count( $cards ) === 1 && $card_id === '' ) {
			return reset( $cards );
		}
		return null;
	}

	private static function card_field( $card, $keys ) {
		foreach ( $keys as $k ) {
			if ( isset( $card[ $k ] ) && $card[ $k ] !== '' ) {
				return (string) $card[ $k ];
			}
		}
		return '';
	}

	public static function approve( $id, $admin_id = 0 ) {
		global $wpdb;
		$table = AWW_Database::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
		if ( ! $row || 'pending' !== $row->status ) {
			return new WP_Error( 'invalid', 'درخواست قابل تایید نیست.' );
		}

		$wpdb->update(
			$table,
			array(
				'status'      => 'approved',
				'reviewed_by' => absint( $admin_id ),
				'updated_at'  => current_time( 'mysql' ),
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $row->id )
		);

		$row->status = 'approved';
		self::sms_on_status( $row );
		return true;
	}

	public static function reject( $id, $reason, $admin_id = 0 ) {
		global $wpdb;
		$table = AWW_Database::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
		if ( ! $row || 'pending' !== $row->status ) {
			return new WP_Error( 'invalid', 'درخواست قابل رد نیست.' );
		}

		// Refund
		if ( 'wallet' === $row->type ) {
			$credit = AWW_Helpers::wallet_credit( $row->user_id, (float) $row->amount, 'برگشت برداشت رد شده - ' . $row->tracking_code );
			if ( is_wp_error( $credit ) ) {
				return $credit;
			}
			$refund_id = is_array( $credit ) ? ( $credit['transaction_id'] ?? '' ) : (string) $credit;
		} else {
			if ( class_exists( 'Asset_Wallet_Balances' ) && $row->account_id && $row->asset_id ) {
				$before = Asset_Wallet_Balances::get( $row->account_id, $row->asset_id, true );
				$before_q = $before ? $before->quantity : '0';
				Asset_Wallet_Balances::increase( $row->account_id, $row->asset_id, $row->quantity );
				$after_q = Asset_Wallet_Helpers::decimal_add( $before_q, $row->quantity );
				if ( class_exists( 'Asset_Wallet_Transactions' ) ) {
					Asset_Wallet_Transactions::create( array(
						'account_id'     => $row->account_id,
						'asset_id'       => $row->asset_id,
						'type'           => 'withdraw_refund',
						'quantity'       => $row->quantity,
						'unit'           => $row->unit,
						'balance_before' => $before_q,
						'balance_after'  => $after_q,
						'reference'      => $row->tracking_code,
						'description'    => 'برگشت برداشت رد شده',
						'status'         => 'completed',
						'created_by'     => $admin_id,
					) );
				}
			}
			$refund_id = '';
		}

		$wpdb->update(
			$table,
			array(
				'status'        => 'rejected',
				'reject_reason' => sanitize_textarea_field( $reason ),
				'reviewed_by'   => absint( $admin_id ),
				'refund_tx_id'  => $refund_id,
				'updated_at'    => current_time( 'mysql' ),
				'rejected_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $row->id )
		);

		$row->status = 'rejected';
		$row->reject_reason = $reason;
		self::sms_on_status( $row );
		return true;
	}

	private static function sms_on_create( $row ) {
		$name  = $row->user_full_name ?: 'کاربر';
		$phone = $row->user_mobile;
		$trx   = $row->tracking_code;

		if ( 'wallet' === $row->type ) {
			$price = number_format( (float) $row->amount, 0, '', ',' );
			AWW_Helpers::send_sms( $phone, 595448, array(
				'NAME' => $name, 'PRICE' => $price, 'TRACONESH' => $trx,
			) );
			AWW_Helpers::send_sms( AWW_ADMIN_NOTIFY_PHONE, 901042, array(
				'PHONE' => $phone, 'PRICE' => $price, 'TRACONESH' => $trx,
			) );
		} else {
			AWW_Helpers::send_sms( $phone, 501722, array(
				'NAME' => $name, 'TRACONESH' => $trx,
			) );
			AWW_Helpers::send_sms( AWW_ADMIN_NOTIFY_PHONE, 408189, array(
				'PHONE' => $phone, 'TRACONESH' => $trx,
			) );
		}
	}

	private static function sms_on_status( $row ) {
		$name  = $row->user_full_name ?: 'کاربر';
		$phone = $row->user_mobile;
		$trx   = $row->tracking_code;
		$price = number_format( (float) $row->amount, 0, '', ',' );

		if ( 'wallet' === $row->type ) {
			if ( 'approved' === $row->status ) {
				AWW_Helpers::send_sms( $phone, 111762, array( 'NAME' => $name, 'PRICE' => $price, 'TRACONESH' => $trx ) );
			} elseif ( 'rejected' === $row->status ) {
				AWW_Helpers::send_sms( $phone, 761658, array( 'NAME' => $name, 'PRICE' => $price, 'TRACONESH' => $trx ) );
			}
		} else {
			if ( 'approved' === $row->status ) {
				AWW_Helpers::send_sms( $phone, 445749, array( 'NAME' => $name, 'TRACONESH' => $trx ) );
			} elseif ( 'rejected' === $row->status ) {
				AWW_Helpers::send_sms( $phone, 208156, array( 'NAME' => $name, 'TRACONESH' => $trx ) );
			}
		}
	}

	public static function get_user_list( $user_id, $limit = 50 ) {
		global $wpdb;
		$table = AWW_Database::table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
			absint( $user_id ), absint( $limit )
		) );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AWW_Database::table() . ' WHERE id = %d', absint( $id ) ) );
	}
}
