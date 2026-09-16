<?php
/**
 * AJAX / REST handlers
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Ajax {

	/**
	 * Instance
	 *
	 * @var Asset_Wallet_Ajax
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Asset_Wallet_Ajax
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Public / logged-in AJAX
		$actions = array(
			'asset_wallet_get_balances',
			'asset_wallet_get_sell_price',
			'asset_wallet_create_sale_request',
			'asset_wallet_verify_sale_otp',
			'asset_wallet_resend_otp',
			'asset_wallet_create_delivery',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'asset_wallet_', '', $action ) ) );
		}
	}

	/**
	 * Verify nonce and user
	 *
	 * @param string $action Action name for nonce.
	 * @return int|WP_Error User ID or error.
	 */
	private function verify_request( $action = 'asset_wallet_nonce' ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', __( 'لطفاً وارد حساب کاربری شوید.', 'asset-wallet' ) );
		}

		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return new WP_Error( 'invalid_nonce', __( 'درخواست نامعتبر است. صفحه را تازه کنید.', 'asset-wallet' ) );
		}

		return get_current_user_id();
	}

	/**
	 * Get user balances
	 */
	public function get_balances() {
		$user_id = $this->verify_request();
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		$account = Asset_Wallet_Accounts::get_or_create( $user_id );
		if ( ! $account ) {
			wp_send_json_error( array( 'message' => __( 'حساب یافت نشد.', 'asset-wallet' ) ) );
		}

		$balances = Asset_Wallet_Balances::get_all_for_account( $account->id );
		$data     = array();

		foreach ( $balances as $b ) {
			$available = Asset_Wallet_Helpers::decimal_sub( $b->quantity, $b->reserved_quantity );
			$buy_price = Asset_Wallet_Helpers::get_buy_price( $b->product_id, $b->variation_id );

			$data[] = array(
				'asset_id'          => (int) $b->asset_id,
				'name'              => $b->name,
				'type'              => $b->type,
				'unit'              => $b->unit,
				'quantity'          => $b->quantity,
				'reserved'          => $b->reserved_quantity,
				'available'         => $available,
				'product_id'        => (int) $b->product_id,
				'variation_id'      => (int) $b->variation_id,
				'current_buy_price' => $buy_price !== false ? $buy_price : null,
				'current_value'     => $buy_price !== false ? (float) Asset_Wallet_Helpers::decimal_mul( $buy_price, $available ) : null,
			);
		}

		wp_send_json_success( array( 'balances' => $data ) );
	}

	/**
	 * Get live sell price
	 */
	public function get_sell_price() {
		$user_id = $this->verify_request();
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		$asset_id = isset( $_REQUEST['asset_id'] ) ? absint( $_REQUEST['asset_id'] ) : 0;
		$quantity = isset( $_REQUEST['quantity'] ) ? number_format( (float) $_REQUEST['quantity'], 8, '.', '' ) : '0';

		if ( ! $asset_id || Asset_Wallet_Helpers::decimal_cmp( $quantity, '0' ) <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'پارامترهای نامعتبر.', 'asset-wallet' ) ) );
		}

		$account = Asset_Wallet_Accounts::get_by_user( $user_id );
		if ( ! $account ) {
			wp_send_json_error( array( 'message' => __( 'حساب یافت نشد.', 'asset-wallet' ) ) );
		}

		$asset = Asset_Wallet_Assets::get( $asset_id );
		if ( ! $asset ) {
			wp_send_json_error( array( 'message' => __( 'دارایی نامعتبر است.', 'asset-wallet' ) ) );
		}

		// Ownership / balance check (soft)
		$available = Asset_Wallet_Balances::get_available( $account->id, $asset_id );

		$buy_price = Asset_Wallet_Helpers::get_buy_price( $asset->product_id, $asset->variation_id );
		if ( false === $buy_price ) {
			wp_send_json_error( array( 'message' => __( 'قیمت خرید فعلی تعریف نشده است.', 'asset-wallet' ) ) );
		}

		$total = (float) Asset_Wallet_Helpers::decimal_mul( $buy_price, $quantity );

		wp_send_json_success(
			array(
				'asset_id'    => $asset_id,
				'unit_price'  => $buy_price,
				'quantity'    => $quantity,
				'total_price' => $total,
				'available'   => $available,
				'updated_at'  => current_time( 'mysql' ),
				'timestamp'   => time(),
			)
		);
	}

	/**
	 * Create sale request + send OTP
	 */
	public function create_sale_request() {
		$user_id = $this->verify_request();
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		$asset_id = isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? number_format( (float) $_POST['quantity'], 8, '.', '' ) : '0';

		$result = Asset_Wallet_Sales::create_request( $user_id, $asset_id, $quantity );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
		}

		wp_send_json_success(
			array(
				'sale_request_id' => $result,
				'message'         => __( 'کد تأیید به شماره موبایل شما ارسال شد.', 'asset-wallet' ),
			)
		);
	}

	/**
	 * Verify OTP and complete sale
	 */
	public function verify_sale_otp() {
		$user_id = $this->verify_request();
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		$sale_request_id   = isset( $_POST['sale_request_id'] ) ? absint( $_POST['sale_request_id'] ) : 0;
		$otp_code          = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : '';
		$confirm_new_price = ! empty( $_POST['confirm_new_price'] );

		$result = Asset_Wallet_Sales::complete_sale( $user_id, $sale_request_id, $otp_code, $confirm_new_price );

		if ( is_wp_error( $result ) ) {
			$data = array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			);
			if ( 'price_changed' === $result->get_error_code() ) {
				$data['price_data'] = $result->get_error_data();
			}
			wp_send_json_error( $data );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'فروش با موفقیت انجام شد و مبلغ به کیف پول ریالی شما واریز گردید.', 'asset-wallet' ),
				'unit_price'  => $result['unit_price'],
				'total_price' => $result['total_price'],
				'quantity'    => $result['quantity'],
			)
		);
	}

	/**
	 * Resend OTP
	 */
	public function resend_otp() {
		$user_id = $this->verify_request();
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		$sale_request_id = isset( $_POST['sale_request_id'] ) ? absint( $_POST['sale_request_id'] ) : 0;

		$request = Asset_Wallet_Sales::get_request( $sale_request_id );
		if ( ! $request || (int) $request->user_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'درخواست نامعتبر است.', 'asset-wallet' ) ) );
		}

		if ( 'pending_otp' !== $request->status ) {
			wp_send_json_error( array( 'message' => __( 'وضعیت درخواست اجازه ارسال مجدد نمی‌دهد.', 'asset-wallet' ) ) );
		}

		$result = Asset_Wallet_OTP::create_and_send( $user_id, $sale_request_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'کد تأیید مجدداً ارسال شد.', 'asset-wallet' ) ) );
	}

	/**
	 * Create delivery request
	 */
	public function create_delivery() {
		$user_id = $this->verify_request();
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		$asset_id = isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? number_format( (float) $_POST['quantity'], 8, '.', '' ) : '0';

		$address = array(
			'address_1' => isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '',
			'city'      => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'state'     => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'postcode'  => isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '',
			'phone'     => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
		);

		$result = Asset_Wallet_Delivery::create_request( $user_id, $asset_id, $quantity, $address );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'delivery_request_id' => $result,
				'message'             => __( 'درخواست تحویل با موفقیت ثبت و دارایی رزرو شد.', 'asset-wallet' ),
			)
		);
	}
}
