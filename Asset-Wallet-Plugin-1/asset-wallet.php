<?php
/**
 * Plugin Name:       Asset Wallet - کیف دارایی طلا و سکه
 * Plugin URI:        https://t.me/FrnzGh1996
 * Description:       سیستم اختصاصی نگهداری دارایی‌های فیزیکی (طلا و سکه) به صورت دیجیتال در حساب کاربر. یکپارچه با WooCommerce و TeraWallet.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Fathemeh Ghorbani
 * Text Domain:       asset-wallet
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'ASSET_WALLET_VERSION', '1.0.0' );
define( 'ASSET_WALLET_PLUGIN_FILE', __FILE__ );
define( 'ASSET_WALLET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASSET_WALLET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASSET_WALLET_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class Asset_Wallet {

	/**
	 * Single instance
	 *
	 * @var Asset_Wallet
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Asset_Wallet
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files
	 */
		/**
	 * Include required files
	 */
	private function includes() {
		// Core
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-database.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-helpers.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-accounts.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-assets.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-balances.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-transactions.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-orders.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-sales.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-delivery.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-otp.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-terawallet.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-woocommerce.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-shortcodes.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-ajax.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-audit.php';
		require_once ASSET_WALLET_PLUGIN_DIR . 'includes/class-reconciliation.php';

		// Admin - فقط یک فایل
		if ( is_admin() ) {
			require_once ASSET_WALLET_PLUGIN_DIR . 'admin/class-admin.php';
		}
	}
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		register_activation_hook( ASSET_WALLET_PLUGIN_FILE, array( 'Asset_Wallet_Database', 'install' ) );
		register_deactivation_hook( ASSET_WALLET_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_my_account_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_my_account_menu_item' ) );
		add_action( 'woocommerce_account_asset-wallet_endpoint', array( $this, 'my_account_content' ) );
	}

	/**
	 * Plugins loaded
	 */
	public function on_plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Initialize components
		Asset_Wallet_WooCommerce::instance();
		Asset_Wallet_Shortcodes::instance();
		Asset_Wallet_Ajax::instance();
		Asset_Wallet_Orders::instance();
		Asset_Wallet_Sales::instance();
		Asset_Wallet_Delivery::instance();

		if ( is_admin() ) {
			Asset_Wallet_Admin::instance();
		}
	}

	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'asset-wallet', false, dirname( ASSET_WALLET_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register My Account endpoint
	 */
	public function register_my_account_endpoint() {
		add_rewrite_endpoint( 'asset-wallet', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add menu item to My Account
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function add_my_account_menu_item( $items ) {
		$new_items = array();
		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			if ( 'dashboard' === $key ) {
				$new_items['asset-wallet'] = __( 'کیف دارایی', 'asset-wallet' );
			}
		}
		if ( ! isset( $new_items['asset-wallet'] ) ) {
			$new_items['asset-wallet'] = __( 'کیف دارایی', 'asset-wallet' );
		}
		return $new_items;
	}

	/**
	 * My Account content
	 */
	public function my_account_content() {
		echo do_shortcode( '[asset_wallet]' );
	}

	/**
	 * WooCommerce missing notice
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'پلاگین کیف دارایی نیاز به نصب و فعال بودن WooCommerce دارد.', 'asset-wallet' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Deactivation
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}
}

/**
 * Returns the main instance
 *
 * @return Asset_Wallet
 */
function asset_wallet() {
	return Asset_Wallet::instance();
}

// Start the plugin
asset_wallet();
