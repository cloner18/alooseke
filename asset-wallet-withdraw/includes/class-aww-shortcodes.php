<?php
defined( 'ABSPATH' ) || exit;

class AWW_Shortcodes {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'asset_wallet_withdraw', array( $this, 'form' ) );
		add_shortcode( 'asset_wallet_withdraw_list', array( $this, 'list' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets() {
		if ( ! is_user_logged_in() ) return;
		wp_register_style( 'aww-css', AWW_PLUGIN_URL . 'assets/css/withdraw.css', array(), AWW_VERSION );
		wp_register_script( 'aww-js', AWW_PLUGIN_URL . 'assets/js/withdraw.js', array( 'jquery' ), AWW_VERSION, true );
		wp_localize_script( 'aww-js', 'awwData', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'aww_nonce' ),
		) );
	}

	public function form() {
		if ( ! is_user_logged_in() ) {
			return '<p>برای ثبت درخواست وارد شوید.</p>';
		}
		wp_enqueue_style( 'aww-css' );
		wp_enqueue_script( 'aww-js' );
		ob_start();
		include AWW_PLUGIN_DIR . 'templates/form.php';
		return ob_get_clean();
	}

	public function list() {
		if ( ! is_user_logged_in() ) {
			return '<p>برای مشاهده لیست وارد شوید.</p>';
		}
		wp_enqueue_style( 'aww-css' );
		ob_start();
		include AWW_PLUGIN_DIR . 'templates/list.php';
		return ob_get_clean();
	}
}
