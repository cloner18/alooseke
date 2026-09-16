<?php
/**
 * Plugin Name:       Asset Wallet Transfer — انتقال بین کاربران
 * Plugin URI:        https://aloseke.com
 * Description:       سیستم انتقال دارایی (Asset Wallet) و موجودی نقدی (TeraWallet) بین کاربران احراز هویت‌شده. وابسته به پلاگین Asset Wallet.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            FathemehGH
 * Text Domain:       asset-wallet-transfer
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AWT_VERSION', '1.0.0' );
define( 'AWT_PLUGIN_FILE', __FILE__ );
define( 'AWT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AWT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

final class Asset_Wallet_Transfer_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( AWT_PLUGIN_FILE, array( $this, 'activate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 30 );
	}

	public function activate() {
		if ( ! $this->is_asset_wallet_active() ) {
			deactivate_plugins( plugin_basename( AWT_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'پلاگین Asset Wallet باید نصب و فعال باشد.', 'asset-wallet-transfer' ),
				esc_html__( 'خطا', 'asset-wallet-transfer' ),
				array( 'back_link' => true )
			);
		}
		require_once AWT_PLUGIN_DIR . 'includes/class-awt-database.php';
		AWT_Database::install();
		flush_rewrite_rules();
	}

	public function init() {
		if ( ! $this->is_asset_wallet_active() ) {
			add_action( 'admin_notices', array( $this, 'missing_dependency_notice' ) );
			return;
		}

		require_once AWT_PLUGIN_DIR . 'includes/class-awt-database.php';
		require_once AWT_PLUGIN_DIR . 'includes/class-awt-helpers.php';
		require_once AWT_PLUGIN_DIR . 'includes/class-awt-transfers.php';
		require_once AWT_PLUGIN_DIR . 'includes/class-awt-ajax.php';
		require_once AWT_PLUGIN_DIR . 'includes/class-awt-shortcodes.php';
		require_once AWT_PLUGIN_DIR . 'includes/class-awt-history.php';

		AWT_Database::maybe_upgrade();
		AWT_Ajax::instance();
		AWT_Shortcodes::instance();

		if ( is_admin() ) {
			require_once AWT_PLUGIN_DIR . 'admin/class-awt-admin.php';
			AWT_Admin::instance();
		}
	}

	private function is_asset_wallet_active() {
		return class_exists( 'Asset_Wallet' ) || class_exists( 'Asset_Wallet_Balances' ) || defined( 'ASSET_WALLET_VERSION' );
	}

	public function missing_dependency_notice() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'پلاگین «انتقال کیف دارایی» نیاز به فعال بودن پلاگین Asset Wallet دارد.', 'asset-wallet-transfer' );
		echo '</p></div>';
	}
}

Asset_Wallet_Transfer_Plugin::instance();
