<?php
/**
 * Shortcodes
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Shortcodes {

	/**
	 * Instance
	 *
	 * @var Asset_Wallet_Shortcodes
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Asset_Wallet_Shortcodes
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
		add_shortcode( 'asset_wallet', array( $this, 'render_wallet' ) );
		add_shortcode( 'asset_wallet_sell', array( $this, 'render_sell' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_assets() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_register_style(
			'asset-wallet-frontend',
			ASSET_WALLET_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ASSET_WALLET_VERSION
		);

		wp_register_script(
			'asset-wallet-frontend',
			ASSET_WALLET_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			ASSET_WALLET_VERSION,
			true
		);

		wp_localize_script(
			'asset-wallet-frontend',
			'assetWallet',
			array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'asset_wallet_nonce' ),
				'live_price_interval' => (int) Asset_Wallet_Helpers::get_option( 'live_price_interval', 8 ) * 1000,
				'i18n'                => array(
					'loading'           => __( 'در حال بارگذاری...', 'asset-wallet' ),
					'error'             => __( 'خطایی رخ داد.', 'asset-wallet' ),
					'confirm_sale'      => __( 'آیا از درخواست فروش اطمینان دارید؟', 'asset-wallet' ),
					'price_changed'     => __( 'قیمت تغییر کرده است. آیا با قیمت جدید ادامه می‌دهید؟', 'asset-wallet' ),
					'otp_sent'          => __( 'کد تأیید ارسال شد.', 'asset-wallet' ),
					'sale_success'      => __( 'فروش با موفقیت انجام شد.', 'asset-wallet' ),
					'enter_otp'         => __( 'کد تأیید را وارد کنید', 'asset-wallet' ),
					'resend'            => __( 'ارسال مجدد کد', 'asset-wallet' ),
					'submit'            => __( 'تأیید و فروش', 'asset-wallet' ),
					'no_balance'        => __( 'موجودی ندارید.', 'asset-wallet' ),
					'select_asset'      => __( 'دارایی را انتخاب کنید', 'asset-wallet' ),
				),
			)
		);
	}

	/**
	 * [asset_wallet] shortcode
	 *
	 * @return string
	 */
	public function render_wallet() {
		if ( ! is_user_logged_in() ) {
			return '<div class="asset-wallet-login-required"><p>' . esc_html__( 'برای مشاهده دارایی‌های خود وارد حساب کاربری شوید.', 'asset-wallet' ) . '</p></div>';
		}

		wp_enqueue_style( 'asset-wallet-frontend' );
		wp_enqueue_script( 'asset-wallet-frontend' );

		ob_start();
		include ASSET_WALLET_PLUGIN_DIR . 'templates/wallet.php';
		return ob_get_clean();
	}

	/**
	 * [asset_wallet_sell] shortcode
	 *
	 * @return string
	 */
	public function render_sell() {
		if ( ! is_user_logged_in() ) {
			return '<div class="asset-wallet-login-required"><p>' . esc_html__( 'برای فروش دارایی وارد حساب کاربری شوید.', 'asset-wallet' ) . '</p></div>';
		}

		wp_enqueue_style( 'asset-wallet-frontend' );
		wp_enqueue_script( 'asset-wallet-frontend' );

		ob_start();
		include ASSET_WALLET_PLUGIN_DIR . 'templates/sell.php';
		return ob_get_clean();
	}
}
