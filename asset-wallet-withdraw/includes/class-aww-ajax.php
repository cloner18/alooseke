<?php
defined( 'ABSPATH' ) || exit;

class AWW_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$actions = array( 'aww_create', 'aww_get_balances', 'aww_get_wallet_balance' );
		foreach ( $actions as $a ) {
			add_action( 'wp_ajax_' . $a, array( $this, str_replace( 'aww_', '', $a ) ) );
		}
	}

	private function verify() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'auth', 'وارد شوید.' );
		}
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'aww_nonce' ) ) {
			return new WP_Error( 'nonce', 'درخواست نامعتبر.' );
		}
		return get_current_user_id();
	}

	public function create() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		$r = AWW_Service::create( array(
			'user_id'  => $uid,
			'type'     => isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '',
			'amount'   => isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0,
			'card_id'  => isset( $_POST['card_id'] ) ? sanitize_text_field( wp_unslash( $_POST['card_id'] ) ) : '',
			'asset_id' => isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0,
			'quantity' => isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '0',
		) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( $r );
	}

	public function get_balances() {
		$uid = $this->verify();
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => $uid->get_error_message() ) );
		}
		if ( ! class_exists( 'Asset_Wallet_Accounts' ) ) {
			wp_send_json_error( array( 'message' => 'Asset Wallet فعال نیست.' ) );
		}
		$acc = Asset_Wallet_Accounts::get_or_create( $uid );
		$rows = Asset_Wallet_Balances::get_all_for_account( $acc->id );
		$data = array();
		foreach ( $rows as $b ) {
			$avail = Asset_Wallet_Helpers::decimal_sub( $b->quantity, $b->reserved_quantity );
			if ( (float) $avail <= 0 ) continue;
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
		$bal = AWW_Helpers::wallet_balance( $uid );
		wp_send_json_success( array( 'balance' => $bal, 'formatted' => number_format_i18n( $bal ) . ' تومان' ) );
	}
}
