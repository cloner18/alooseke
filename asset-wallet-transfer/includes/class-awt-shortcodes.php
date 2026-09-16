<?php
defined( 'ABSPATH' ) || exit;

class AWT_Shortcodes {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'asset_wallet_transfer', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_shortcode( 'asset_wallet_history', array( $this, 'render_history' ) );
	}

	public function assets() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_register_style( 'awt-frontend', AWT_PLUGIN_URL . 'assets/css/transfer.css', array(), AWT_VERSION );
		wp_register_script( 'awt-frontend', AWT_PLUGIN_URL . 'assets/js/transfer.js', array( 'jquery' ), AWT_VERSION, true );
		wp_localize_script( 'awt-frontend', 'awtData', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'awt_nonce' ),
			'i18n'     => array(
				'loading'   => 'در حال بارگذاری...',
				'error'     => 'خطایی رخ داد.',
				'continue'  => 'ادامه',
				'confirm'   => 'تأیید',
				'success'   => 'انتقال با موفقیت انجام شد.',
			),
		) );
	}

	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="awt-login-required"><p>برای انتقال وارد حساب کاربری شوید.</p></div>';
		}
		wp_enqueue_style( 'awt-frontend' );
		wp_enqueue_script( 'awt-frontend' );
		ob_start();
		include AWT_PLUGIN_DIR . 'templates/transfer.php';
		return ob_get_clean();
	}
	public function render_history() {
	if ( ! is_user_logged_in() ) {
		return '<div class="awt-login-required"><p>برای مشاهده تاریخچه وارد شوید.</p></div>';
	}
	wp_enqueue_style( 'awt-frontend' );
	ob_start();
	include AWT_PLUGIN_DIR . 'templates/history.php';
	return ob_get_clean();
}
}
