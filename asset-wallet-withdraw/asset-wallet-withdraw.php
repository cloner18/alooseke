<?php
/**
 * Plugin Name:       Asset Wallet Withdraw — برداشت موجودی و دارایی
 * Description:       سیستم درخواست برداشت موجودی نقدی و دارایی فیزیکی. وابسته به Asset Wallet و TeraWallet.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       asset-wallet-withdraw
 */

defined( 'ABSPATH' ) || exit;

define( 'AWW_VERSION', '1.0.0' );
define( 'AWW_PLUGIN_FILE', __FILE__ );
define( 'AWW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AWW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AWW_ADMIN_NOTIFY_PHONE', '09134794771' );

final class Asset_Wallet_Withdraw_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( AWW_PLUGIN_FILE, array( $this, 'activate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 35 );
	}

	public function activate() {
		require_once AWW_PLUGIN_DIR . 'includes/class-aww-database.php';
		AWW_Database::install();
		flush_rewrite_rules();
	}

	public function init() {
		require_once AWW_PLUGIN_DIR . 'includes/class-aww-database.php';
		require_once AWW_PLUGIN_DIR . 'includes/class-aww-helpers.php';
		require_once AWW_PLUGIN_DIR . 'includes/class-aww-service.php';
		require_once AWW_PLUGIN_DIR . 'includes/class-aww-ajax.php';
		require_once AWW_PLUGIN_DIR . 'includes/class-aww-shortcodes.php';

		AWW_Database::maybe_upgrade();
		AWW_Ajax::instance();
		AWW_Shortcodes::instance();

		if ( is_admin() ) {
			require_once AWW_PLUGIN_DIR . 'admin/class-aww-admin.php';
			AWW_Admin::instance();
		}
	}
}

Asset_Wallet_Withdraw_Plugin::instance();
