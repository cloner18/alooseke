<?php
defined( 'ABSPATH' ) || exit;

class AWT_Transfers {

	public static function find_user_by_phone( $phone ) {
		$phone = AWT_Helpers::normalize_phone( $phone );
		if ( ! $phone ) {
			return false;
		}
		global $wpdb;
		$keys = array( 'billing_phone', 'phone', 'mobile', 'user_phone' );
		$alt  = ( strpos( $phone, '0' ) === 0 ) ? substr( $phone, 1 ) : '0' . $phone;

		foreach ( $keys as $key ) {
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND (meta_value = %s OR meta_value = %s) LIMIT 1",
					$key,
					$phone,
					$alt
				)
			);
			if ( $id ) {
				return (int) $id;
			}
		}
		$user = get_user_by( 'login', $phone );
		return $user ? (int) $user->ID : false;
	}

	public static function lookup_receiver( $sender_id, $phone ) {
		$phone = AWT_Helpers::normalize_phone( $phone );
		if ( ! $phone ) {
			return new WP_Error( 'invalid_phone', __( 'شماره موبایل نامعتبر است.', 'asset-wallet-transfer' ) );
		}
		$receiver_id = self::find_user_by_phone( $phone );
		if ( ! $receiver_id ) {
			return new WP_Error( 'not_found', __( 'کاربری با این شماره پیدا نشد.', 'asset-wallet-transfer' ) );
		}
		if ( (int) $receiver_id === (int) $sender_id ) {
			return new WP_Error( 'self_transfer', __( 'امکان انتقال به حساب خودتان وجود ندارد.', 'asset-wallet-transfer' ) );
		}
		if ( ! AWT_Helpers::is_authenticated( $receiver_id ) ) {
			return new WP_Error( 'not_authenticated', __( 'این کاربر امکان دریافت انتقال را ندارد.', 'asset-wallet-transfer' ) );
		}
		$snap = AWT_Helpers::user_snapshot( $receiver_id );
		return array(
			'receiver_user_id' => $receiver_id,
			'full_name'        => $snap['full_name'],
			'mobile_masked'    => AWT_Helpers::mask_mobile( $snap['mobile'] ?: $phone ),
		);
	}

	public static function create_request( $args ) {
		$sender_id   = absint( $args['sender_user_id'] ?? 0 );
		$receiver_id = absint( $args['receiver_user_id'] ?? 0 );
		$type        = sanitize_key( $args['transfer_type'] ?? '' );

		if ( ! $sender_id || ! $receiver_id || $sender_id === $receiver_id ) {
			return new WP_Error( 'invalid_users', __( 'فرستنده یا گیرنده نامعتبر است.', 'asset-wallet-transfer' ) );
		}
		if ( ! in_array( $type, array( 'asset', 'wallet' ), true ) ) {
			return new WP_Error( 'invalid_type', __( 'نوع انتقال نامعتبر است.', 'asset-wallet-transfer' ) );
		}
		if ( ! AWT_Helpers::is_authenticated( $receiver_id ) ) {
			return new WP_Error( 'not_authenticated', __( 'این کاربر امکان دریافت انتقال را ندارد.', 'asset-wallet-transfer' ) );
		}

		$s_snap = AWT_Helpers::user_snapshot( $sender_id );
		$r_snap = AWT_Helpers::user_snapshot( $receiver_id );

		$s_acc = class_exists( 'Asset_Wallet_Accounts' ) ? Asset_Wallet_Accounts::get_or_create( $sender_id ) : null;
		$r_acc = class_exists( 'Asset_Wallet_Accounts' ) ? Asset_Wallet_Accounts::get_or_create( $receiver_id ) : null;

		$data = array(
			'transfer_uuid'         => wp_generate_uuid4(),
			'transaction_reference' => AWT_Helpers::trx_ref(),
			'sender_user_id'        => $sender_id,
			'sender_account_id'     => $s_acc ? $s_acc->id : null,
			'sender_first_name'     => $s_snap['first_name'],
			'sender_last_name'      => $s_snap['last_name'],
			'sender_full_name'      => $s_snap['full_name'],
			'sender_mobile'         => $s_snap['mobile'],
			'receiver_user_id'      => $receiver_id,
			'receiver_account_id'   => $r_acc ? $r_acc->id : null,
			'receiver_first_name'   => $r_snap['first_name'],
			'receiver_last_name'    => $r_snap['last_name'],
			'receiver_full_name'    => $r_snap['full_name'],
			'receiver_mobile'      => $r_snap['mobile'],
			'transfer_type'         => $type,
			'status'                => 'pending_otp',
			'otp_verified'          => 0,
			'created_at'            => current_time( 'mysql' ),
			'updated_at'            => current_time( 'mysql' ),
		);

		if ( 'asset' === $type ) {
			if ( ! class_exists( 'Asset_Wallet_Balances' ) || ! class_exists( 'Asset_Wallet_Assets' ) ) {
				return new WP_Error( 'dependency', __( 'پلاگین Asset Wallet در دسترس نیست.', 'asset-wallet-transfer' ) );
			}
			$asset_id = absint( $args['asset_id'] ?? 0 );
			$qty      = number_format( (float) ( $args['quantity'] ?? 0 ), 8, '.', '' );
			if ( ! $asset_id || (float) $qty <= 0 ) {
				return new WP_Error( 'invalid_quantity', __( 'مقدار دارایی نامعتبر است.', 'asset-wallet-transfer' ) );
			}
			$asset = Asset_Wallet_Assets::get( $asset_id );
			if ( ! $asset || 'active' !== $asset->status ) {
				return new WP_Error( 'invalid_asset', __( 'دارایی نامعتبر است.', 'asset-wallet-transfer' ) );
			}
			$available = Asset_Wallet_Balances::get_available( $s_acc->id, $asset_id );
			if ( class_exists( 'Asset_Wallet_Helpers' ) && Asset_Wallet_Helpers::decimal_cmp( $available, $qty ) < 0 ) {
				return new WP_Error( 'insufficient_balance', __( 'موجودی کافی نیست.', 'asset-wallet-transfer' ) );
			} elseif ( (float) $available < (float) $qty ) {
				return new WP_Error( 'insufficient_balance', __( 'موجودی کافی نیست.', 'asset-wallet-transfer' ) );
			}
			$data['asset_id']     = $asset_id;
			$data['product_id']   = $asset->product_id;
			$data['variation_id'] = $asset->variation_id;
			$data['product_name'] = $asset->name;
			$data['quantity']     = $qty;
			$data['unit']         = $asset->unit;
		} else {
			$amount = (float) ( $args['amount'] ?? 0 );
			if ( $amount <= 0 ) {
				return new WP_Error( 'invalid_amount', __( 'مبلغ نامعتبر است.', 'asset-wallet-transfer' ) );
			}
			$bal = self::get_wallet_balance( $sender_id );
			if ( $bal < $amount ) {
				return new WP_Error( 'insufficient_wallet', __( 'موجودی کیف پول شما برای این انتقال کافی نیست.', 'asset-wallet-transfer' ) );
			}
			$data['amount']   = number_format( $amount, 4, '.', '' );
			$data['currency'] = 'IRT';
		}

		global $wpdb;
		$table = AWT_Database::table();
		if ( ! $wpdb->insert( $table, $data ) ) {
			return new WP_Error( 'create_failed', __( 'ایجاد درخواست انتقال ناموفق بود.', 'asset-wallet-transfer' ) );
		}
		$transfer_id = $wpdb->insert_id;

		$otp = self::send_otp( $sender_id, $transfer_id, $s_snap );
		if ( is_wp_error( $otp ) ) {
			$wpdb->update( $table, array( 'status' => 'failed', 'sender_otp_sms_status' => 'failed', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $transfer_id ) );
			return $otp;
		}
		$wpdb->update( $table, array( 'sender_otp_sms_status' => 'sent', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $transfer_id ) );

		return array(
			'transfer_id'           => $transfer_id,
			'transaction_reference' => $data['transaction_reference'],
			'message'               => __( 'کد تأیید به شماره موبایل شما ارسال شد.', 'asset-wallet-transfer' ),
		);
	}

	public static function send_otp( $user_id, $transfer_id, $snap = null ) {
		if ( ! $snap ) {
			$snap = AWT_Helpers::user_snapshot( $user_id );
		}
		$phone = $snap['mobile'];
		if ( ! $phone ) {
			return new WP_Error( 'no_phone', __( 'شماره موبایل فرستنده یافت نشد.', 'asset-wallet-transfer' ) );
		}
		if ( ! function_exists( 'zarnegar_send_sms_ir' ) ) {
			return new WP_Error( 'sms_missing', __( 'تابع ارسال پیامک در دسترس نیست.', 'asset-wallet-transfer' ) );
		}

		$otp  = AWT_Helpers::generate_otp( 6 );
		$hash = AWT_Helpers::hash_otp( $otp );
		$exp  = current_time( 'timestamp' ) + 120;

		// Store in Asset Wallet OTP table if available, else usermeta fallback
		if ( class_exists( 'Asset_Wallet_Database' ) ) {
			global $wpdb;
			$otp_table = Asset_Wallet_Database::table( 'otp' );
			// Invalidate previous
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$otp_table} SET status = 'expired' WHERE user_id = %d AND action = 'transfer' AND status = 'active'",
				$user_id
			) );
			$wpdb->insert(
				$otp_table,
				array(
					'user_id'         => $user_id,
					'action'          => 'transfer',
					'phone'           => $phone,
					'otp_hash'        => $hash,
					'expires_at'      => date( 'Y-m-d H:i:s', $exp ),
					'attempts'        => 0,
					'max_attempts'    => 5,
					'status'          => 'active',
					'sale_request_id' => $transfer_id,
					'created_at'      => current_time( 'mysql' ),
					'ip_address'      => AWT_Helpers::client_ip(),
				)
			);
		} else {
			set_transient( 'awt_otp_' . $user_id . '_' . $transfer_id, $hash, 120 );
		}

		$sent = zarnegar_send_sms_ir(
			$phone,
			812226,
			array(
				'NAME' => $snap['full_name'] ?: 'کاربر',
				'OTP'  => $otp,
			)
		);
		if ( false === $sent || is_wp_error( $sent ) ) {
			AWT_Helpers::log( 'OTP SMS failed', array( 'user' => $user_id ) );
		}
		return true;
	}

	public static function complete( $user_id, $transfer_id, $otp_code ) {
		global $wpdb;
		$table    = AWT_Database::table();
		$transfer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d AND sender_user_id = %d LIMIT 1",
			$transfer_id,
			$user_id
		) );

		if ( ! $transfer ) {
			return new WP_Error( 'not_found', __( 'درخواست انتقال یافت نشد.', 'asset-wallet-transfer' ) );
		}
		if ( 'completed' === $transfer->status ) {
			return array(
				'already_completed'     => true,
				'transaction_reference' => $transfer->transaction_reference,
				'message'               => __( 'انتقال قبلاً انجام شده است.', 'asset-wallet-transfer' ),
			);
		}
		if ( ! in_array( $transfer->status, array( 'pending_otp', 'otp_verified' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'وضعیت انتقال نامعتبر است.', 'asset-wallet-transfer' ) );
		}

		if ( ! $transfer->otp_verified ) {
			$v = self::verify_otp( $user_id, $transfer_id, $otp_code );
			if ( is_wp_error( $v ) ) {
				return $v;
			}
			$wpdb->update( $table, array( 'otp_verified' => 1, 'status' => 'otp_verified', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $transfer_id ) );
		}

		if ( ! AWT_Helpers::is_authenticated( $transfer->receiver_user_id ) ) {
			$wpdb->update( $table, array( 'status' => 'failed', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $transfer_id ) );
			return new WP_Error( 'not_authenticated', __( 'گیرنده امکان دریافت ندارد.', 'asset-wallet-transfer' ) );
		}

		$wpdb->query( 'START TRANSACTION' );
		try {
			$wpdb->update( $table, array( 'status' => 'processing', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $transfer_id ) );

			if ( 'asset' === $transfer->transfer_type ) {
				$res = self::do_asset_transfer( $transfer );
			} else {
				$res = self::do_wallet_transfer( $transfer );
			}
			if ( is_wp_error( $res ) ) {
				throw new Exception( $res->get_error_message() );
			}

			$wpdb->update(
				$table,
				array(
					'status'                  => 'completed',
					'sender_transaction_id'   => $res['sender_tx'] ?? null,
					'receiver_transaction_id' => $res['receiver_tx'] ?? null,
					'sender_tera_tx_id'       => $res['sender_tera'] ?? null,
					'receiver_tera_tx_id'     => $res['receiver_tera'] ?? null,
					'updated_at'              => current_time( 'mysql' ),
					'completed_at'            => current_time( 'mysql' ),
				),
				array( 'id' => $transfer_id )
			);
			$wpdb->query( 'COMMIT' );

			self::send_success_sms( $transfer );

			return array(
				'success'               => true,
				'transaction_reference' => $transfer->transaction_reference,
				'transfer_type'         => $transfer->transfer_type,
				'receiver_name'         => $transfer->receiver_full_name,
				'quantity'              => $transfer->quantity,
				'product_name'          => $transfer->product_name,
				'amount'                => $transfer->amount,
				'message'               => __( 'انتقال با موفقیت انجام شد.', 'asset-wallet-transfer' ),
			);
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			$wpdb->update( $table, array( 'status' => 'failed', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $transfer_id ) );
			AWT_Helpers::log( 'Transfer failed', array( 'id' => $transfer_id, 'err' => $e->getMessage() ) );
			return new WP_Error( 'transfer_failed', $e->getMessage() );
		}
	}

	private static function verify_otp( $user_id, $transfer_id, $code ) {
		if ( class_exists( 'Asset_Wallet_Database' ) ) {
			global $wpdb;
			$t = Asset_Wallet_Database::table( 'otp' );
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$t} WHERE user_id = %d AND sale_request_id = %d AND action = 'transfer' AND status = 'active' ORDER BY id DESC LIMIT 1",
				$user_id,
				$transfer_id
			) );
			if ( ! $row ) {
				return new WP_Error( 'otp_not_found', __( 'کد تأیید فعال یافت نشد.', 'asset-wallet-transfer' ) );
			}
			if ( strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
				$wpdb->update( $t, array( 'status' => 'expired' ), array( 'id' => $row->id ) );
				return new WP_Error( 'otp_expired', __( 'کد تأیید منقضی شده است.', 'asset-wallet-transfer' ) );
			}
			if ( (int) $row->attempts >= (int) $row->max_attempts ) {
				return new WP_Error( 'otp_locked', __( 'تعداد تلاش‌ها بیش از حد مجاز است.', 'asset-wallet-transfer' ) );
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET attempts = attempts + 1 WHERE id = %d", $row->id ) );
			if ( ! AWT_Helpers::verify_otp( $code, $row->otp_hash ) ) {
				return new WP_Error( 'otp_invalid', __( 'کد تأیید نادرست است.', 'asset-wallet-transfer' ) );
			}
			$wpdb->update( $t, array( 'status' => 'used', 'verified_at' => current_time( 'mysql' ) ), array( 'id' => $row->id ) );
			return true;
		}
		$hash = get_transient( 'awt_otp_' . $user_id . '_' . $transfer_id );
		if ( ! $hash || ! AWT_Helpers::verify_otp( $code, $hash ) ) {
			return new WP_Error( 'otp_invalid', __( 'کد تأیید نادرست است.', 'asset-wallet-transfer' ) );
		}
		delete_transient( 'awt_otp_' . $user_id . '_' . $transfer_id );
		return true;
	}

	private static function do_asset_transfer( $t ) {
		if ( ! class_exists( 'Asset_Wallet_Balances' ) || ! class_exists( 'Asset_Wallet_Transactions' ) ) {
			return new WP_Error( 'dependency', __( 'Asset Wallet در دسترس نیست.', 'asset-wallet-transfer' ) );
		}
		$sa = $t->sender_account_id;
		$ra = $t->receiver_account_id;
		$aid = $t->asset_id;
		$qty = $t->quantity;

		$bal = Asset_Wallet_Balances::get( $sa, $aid, true );
		if ( ! $bal ) {
			return new WP_Error( 'no_balance', __( 'موجودی یافت نشد.', 'asset-wallet-transfer' ) );
		}
		$avail = Asset_Wallet_Helpers::decimal_sub( $bal->quantity, $bal->reserved_quantity );
		if ( Asset_Wallet_Helpers::decimal_cmp( $avail, $qty ) < 0 ) {
			return new WP_Error( 'insufficient_balance', __( 'موجودی کافی نیست.', 'asset-wallet-transfer' ) );
		}
		$before_s = $bal->quantity;
		$dec = Asset_Wallet_Balances::decrease( $sa, $aid, $qty );
		if ( is_wp_error( $dec ) ) {
			return $dec;
		}
		$after_s = Asset_Wallet_Helpers::decimal_sub( $before_s, $qty );

		$stx = Asset_Wallet_Transactions::create( array(
			'account_id' => $sa, 'asset_id' => $aid, 'type' => 'transfer_out',
			'quantity' => $qty, 'unit' => $t->unit,
			'balance_before' => $before_s, 'balance_after' => $after_s,
			'reference' => $t->transaction_reference,
			'description' => sprintf( __( 'انتقال به %s', 'asset-wallet-transfer' ), $t->receiver_full_name ),
			'status' => 'completed', 'created_by' => $t->sender_user_id,
		) );

		Asset_Wallet_Balances::ensure( $ra, $aid );
		$bal_r = Asset_Wallet_Balances::get( $ra, $aid, true );
		$before_r = $bal_r ? $bal_r->quantity : '0';
		Asset_Wallet_Balances::increase( $ra, $aid, $qty );
		$after_r = Asset_Wallet_Helpers::decimal_add( $before_r, $qty );

		$rtx = Asset_Wallet_Transactions::create( array(
			'account_id' => $ra, 'asset_id' => $aid, 'type' => 'transfer_in',
			'quantity' => $qty, 'unit' => $t->unit,
			'balance_before' => $before_r, 'balance_after' => $after_r,
			'reference' => $t->transaction_reference,
			'description' => sprintf( __( 'دریافت از %s', 'asset-wallet-transfer' ), $t->sender_full_name ),
			'status' => 'completed', 'created_by' => $t->sender_user_id,
		) );

		return array( 'sender_tx' => $stx, 'receiver_tx' => $rtx );
	}

	private static function do_wallet_transfer( $t ) {
		$amount = (float) $t->amount;
		$bal = self::get_wallet_balance( $t->sender_user_id );
		if ( $bal < $amount ) {
			return new WP_Error( 'insufficient_wallet', __( 'موجودی کیف پول کافی نیست.', 'asset-wallet-transfer' ) );
		}

		$debit = self::wallet_debit( $t->sender_user_id, $amount, sprintf( 'انتقال به %s - %s', $t->receiver_full_name, $t->transaction_reference ), 'awt_debit_' . $t->transfer_uuid );
		if ( is_wp_error( $debit ) ) {
			return $debit;
		}

		$credit = self::wallet_credit( $t->receiver_user_id, $amount, sprintf( 'دریافت از %s - %s', $t->sender_full_name, $t->transaction_reference ), 'awt_credit_' . $t->transfer_uuid );
		if ( is_wp_error( $credit ) ) {
			self::wallet_credit( $t->sender_user_id, $amount, 'برگشت انتقال ناموفق - ' . $t->transaction_reference, 'awt_comp_' . $t->transfer_uuid );
			return $credit;
		}

		return array(
			'sender_tera'   => is_array( $debit ) ? ( $debit['transaction_id'] ?? '' ) : (string) $debit,
			'receiver_tera' => is_array( $credit ) ? ( $credit['transaction_id'] ?? '' ) : (string) $credit,
		);
	}

	public static function get_wallet_balance( $user_id ) {
		if ( class_exists( 'Asset_Wallet_TeraWallet' ) && method_exists( 'Asset_Wallet_Transfers', 'get_terawallet_balance' ) ) {
			// use local
		}
		if ( function_exists( 'woo_wallet' ) && isset( woo_wallet()->wallet ) && method_exists( woo_wallet()->wallet, 'get_wallet_balance' ) ) {
			return (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
		}
		return (float) get_user_meta( $user_id, '_current_woo_wallet_balance', true );
	}

	public static function wallet_credit( $user_id, $amount, $desc = '', $ref = '' ) {
		if ( class_exists( 'Asset_Wallet_TeraWallet' ) ) {
			return Asset_Wallet_TeraWallet::credit( $user_id, $amount, $desc, $ref );
		}
		if ( function_exists( 'woo_wallet' ) && method_exists( woo_wallet()->wallet, 'credit' ) ) {
			$tx = woo_wallet()->wallet->credit( $user_id, $amount, $desc );
			return $tx ? array( 'transaction_id' => $tx ) : new WP_Error( 'credit_failed', 'Credit failed' );
		}
		return new WP_Error( 'no_api', __( 'API کیف پول در دسترس نیست.', 'asset-wallet-transfer' ) );
	}

	public static function wallet_debit( $user_id, $amount, $desc = '', $ref = '' ) {
		if ( $ref ) {
			$ex = get_user_meta( $user_id, '_awt_ref_' . md5( $ref ), true );
			if ( $ex ) {
				return array( 'transaction_id' => $ex, 'already' => true );
			}
		}
		if ( function_exists( 'woo_wallet' ) && method_exists( woo_wallet()->wallet, 'debit' ) ) {
			$tx = woo_wallet()->wallet->debit( $user_id, $amount, $desc );
			if ( ! $tx ) {
				return new WP_Error( 'debit_failed', __( 'کسر موجودی ناموفق بود.', 'asset-wallet-transfer' ) );
			}
			if ( $ref ) {
				update_user_meta( $user_id, '_awt_ref_' . md5( $ref ), $tx );
			}
			return array( 'transaction_id' => $tx );
		}
		return new WP_Error( 'no_api', __( 'API کسر موجودی در دسترس نیست.', 'asset-wallet-transfer' ) );
	}

	private static function send_success_sms( $t ) {
		if ( ! function_exists( 'zarnegar_send_sms_ir' ) ) {
			return;
		}
		global $wpdb;
		$table = AWT_Database::table();

		if ( 'wallet' === $t->transfer_type && $t->receiver_mobile ) {
			$ok = zarnegar_send_sms_ir( $t->receiver_mobile, 125771, array(
				'NAME' => $t->receiver_full_name,
				'PRICE' => number_format( (float) $t->amount, 0, '', ',' ),
				'TRACONESH' => $t->transaction_reference,
			) );
			$wpdb->update( $table, array( 'receiver_sms_status' => ( $ok && ! is_wp_error( $ok ) ) ? 'sent' : 'failed' ), array( 'id' => $t->id ) );
		} elseif ( 'asset' === $t->transfer_type && $t->receiver_mobile ) {
			$ok = zarnegar_send_sms_ir( $t->receiver_mobile, 293884, array(
				'NAME' => $t->receiver_full_name,
				'PRODUCT' => $t->product_name,
				'TRACONESH' => $t->transaction_reference,
			) );
			$wpdb->update( $table, array( 'receiver_sms_status' => ( $ok && ! is_wp_error( $ok ) ) ? 'sent' : 'failed' ), array( 'id' => $t->id ) );
		}

		if ( $t->sender_mobile ) {
			$ok = zarnegar_send_sms_ir( $t->sender_mobile, 606230, array(
				'NAME' => $t->sender_full_name,
				'TRACONESH' => $t->transaction_reference,
			) );
			$wpdb->update( $table, array( 'sender_success_sms_status' => ( $ok && ! is_wp_error( $ok ) ) ? 'sent' : 'failed' ), array( 'id' => $t->id ) );
		}
	}

	public static function get_history( $user_id, $limit = 50 ) {
		global $wpdb;
		$table = AWT_Database::table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE (sender_user_id = %d OR receiver_user_id = %d) AND status = 'completed' ORDER BY completed_at DESC LIMIT %d",
			$user_id, $user_id, $limit
		) );
	}
}
