<?php
defined( 'ABSPATH' ) || exit;

class AWT_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$actions = array(
			'awt_lookup_receiver',
			'awt_get_balances',
			'awt_get_wallet_balance',
			'awt_create_transfer',
			'awt_verify_transfer',
			'awt_resend_otp',
		);
		foreach ( $actions as $a ) {
			add_action( 'wp_ajax_' . $a, array( $this, str_replace( 'awt_', '', $a ) ) );
		}
	}

	private function verify() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'auth', __( 'وارد حساب شوید.', 'asset-wallet-transfer' ) );
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'awt_nonce' ) ) {
			return new WP_Error( 'nonce', __( 'درخواست نامعتبر است.', 'asset-wallet-transfer' ) );
		}
		return get_current_user_id();
	}

	public function lookup_receiver() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$r = AWT_Transfers::lookup_receiver( $uid, $phone );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message(), 'code' => $r->get_error_code() ) );
		}
		wp_send_json_success( $r );
	}

	public function get_balances() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		if ( ! class_exists( 'Asset_Wallet_Accounts' ) || ! class_exists( 'Asset_Wallet_Balances' ) ) {
			wp_send_json_error( array( 'message' => 'Asset Wallet فعال نیست.' ) );
		}
		$acc = Asset_Wallet_Accounts::get_or_create( $uid );
		$rows = Asset_Wallet_Balances::get_all_for_account( $acc->id );
		$data = array();
		foreach ( $rows as $b ) {
			$avail = class_exists( 'Asset_Wallet_Helpers' )
				? Asset_Wallet_Helpers::decimal_sub( $b->quantity, $b->reserved_quantity )
				: ( (float) $b->quantity - (float) $b->reserved_quantity );
			if ( (float) $avail <= 0 ) {
				continue;
			}
			$data[] = array(
				'asset_id'  => (int) $b->asset_id,
				'name'      => $b->name,
				'unit'      => $b->unit,
				'available' => $avail,
			);
		}
		wp_send_json_success( array( 'balances' => $data ) );
	}

	public function get_wallet_balance() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		$bal = AWT_Transfers::get_wallet_balance( $uid );
		wp_send_json_success( array( 'balance' => $bal, 'formatted' => number_format_i18n( $bal ) . ' تومان' ) );
	}

	public function create_transfer() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		$args = array(
			'sender_user_id'   => $uid,
			'receiver_user_id' => isset( $_POST['receiver_user_id'] ) ? absint( $_POST['receiver_user_id'] ) : 0,
			'transfer_type'    => isset( $_POST['transfer_type'] ) ? sanitize_key( $_POST['transfer_type'] ) : '',
			'asset_id'         => isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0,
			'quantity'         => isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '0',
			'amount'           => isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0,
		);
		$r = AWT_Transfers::create_request( $args );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message(), 'code' => $r->get_error_code() ) );
		}
		wp_send_json_success( $r );
	}

	public function verify_transfer() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		$tid = isset( $_POST['transfer_id'] ) ? absint( $_POST['transfer_id'] ) : 0;
		$otp = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : '';
		$r = AWT_Transfers::complete( $uid, $tid, $otp );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message(), 'code' => $r->get_error_code() ) );
		}
		wp_send_json_success( $r );
	}

	public function resend_otp() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		$tid = isset( $_POST['transfer_id'] ) ? absint( $_POST['transfer_id'] ) : 0;
		$r = AWT_Transfers::send_otp( $uid, $tid );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'کد مجدداً ارسال شد.', 'asset-wallet-transfer' ) ) );
	}
}
