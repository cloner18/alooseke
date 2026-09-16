<?php
/**
 * Admin menu and pages
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Admin {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'کیف دارایی', 'asset-wallet' ),
			__( 'کیف دارایی', 'asset-wallet' ),
			'manage_woocommerce',
			'asset-wallet',
			array( $this, 'page_dashboard' ),
			'dashicons-portfolio',
			56
		);

		add_submenu_page( 'asset-wallet', __( 'داشبورد', 'asset-wallet' ), __( 'داشبورد', 'asset-wallet' ), 'manage_woocommerce', 'asset-wallet', array( $this, 'page_dashboard' ) );
		add_submenu_page( 'asset-wallet', __( 'دارایی‌ها', 'asset-wallet' ), __( 'دارایی‌ها', 'asset-wallet' ), 'manage_woocommerce', 'asset-wallet-assets', array( $this, 'page_assets' ) );
		add_submenu_page( 'asset-wallet', __( 'حساب کاربران', 'asset-wallet' ), __( 'حساب کاربران', 'asset-wallet' ), 'manage_woocommerce', 'asset-wallet-accounts', array( $this, 'page_accounts' ) );
		add_submenu_page( 'asset-wallet', __( 'تراکنش‌ها', 'asset-wallet' ), __( 'تراکنش‌ها', 'asset-wallet' ), 'manage_woocommerce', 'asset-wallet-transactions', array( $this, 'page_transactions' ) );
		add_submenu_page( 'asset-wallet', __( 'درخواست‌های فروش', 'asset-wallet' ), __( 'درخواست‌های فروش', 'asset-wallet' ), 'manage_woocommerce', 'asset-wallet-sales', array( $this, 'page_sales' ) );
		add_submenu_page( 'asset-wallet', __( 'تنظیمات', 'asset-wallet' ), __( 'تنظیمات', 'asset-wallet' ), 'manage_woocommerce', 'asset-wallet-settings', array( $this, 'page_settings' ) );
		add_submenu_page( 'asset-wallet', __( 'Reconciliation', 'asset-wallet' ), __( 'Reconciliation', 'asset-wallet' ), 'manage_woocommerce', 'asset-wallet-reconciliation', array( $this, 'page_reconciliation' ) );
	}

	public function enqueue( $hook ) {
		if ( strpos( $hook, 'asset-wallet' ) === false ) {
			return;
		}
		wp_enqueue_style( 'asset-wallet-admin', ASSET_WALLET_PLUGIN_URL . 'assets/css/admin.css', array(), ASSET_WALLET_VERSION );
	}

	public function page_dashboard() {
		global $wpdb;
		$accounts_count   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Asset_Wallet_Database::table( 'accounts' ) );
		$tx_count         = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Asset_Wallet_Database::table( 'transactions' ) );
		$sales_pending    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Asset_Wallet_Database::table( 'sale_requests' ) . " WHERE status IN ('pending_otp','otp_verified','processing')" );
		$delivery_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Asset_Wallet_Database::table( 'delivery_requests' ) . " WHERE status IN ('pending','reserved','processing')" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'کیف دارایی — داشبورد', 'asset-wallet' ); ?></h1>
			<div style="display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;">
				<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);min-width:180px;">
					<h3><?php echo esc_html( number_format_i18n( $accounts_count ) ); ?></h3>
					<p><?php esc_html_e( 'حساب کاربران', 'asset-wallet' ); ?></p>
				</div>
				<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);min-width:180px;">
					<h3><?php echo esc_html( number_format_i18n( $tx_count ) ); ?></h3>
					<p><?php esc_html_e( 'تراکنش‌ها', 'asset-wallet' ); ?></p>
				</div>
				<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);min-width:180px;">
					<h3><?php echo esc_html( number_format_i18n( $sales_pending ) ); ?></h3>
					<p><?php esc_html_e( 'فروش در انتظار', 'asset-wallet' ); ?></p>
				</div>
				<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);min-width:180px;">
					<h3><?php echo esc_html( number_format_i18n( $delivery_pending ) ); ?></h3>
					<p><?php esc_html_e( 'تحویل در انتظار', 'asset-wallet' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	public function page_assets() {
		$assets = Asset_Wallet_Assets::get_all_active();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'تعریف دارایی‌ها', 'asset-wallet' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th><?php esc_html_e( 'نام', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'نوع', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'واحد', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'وزن', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'محصول', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'asset-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $assets ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'هنوز دارایی تعریف نشده. با فعال کردن گزینه کیف دارایی روی محصولات، به صورت خودکار ایجاد می‌شوند.', 'asset-wallet' ); ?></td></tr>
					<?php else : foreach ( $assets as $a ) : ?>
						<tr>
							<td><?php echo esc_html( $a->id ); ?></td>
							<td><?php echo esc_html( $a->name ); ?></td>
							<td><?php echo esc_html( $a->type ); ?></td>
							<td><?php echo esc_html( $a->unit ); ?></td>
							<td><?php echo esc_html( $a->weight ); ?></td>
							<td>
								<?php
								if ( $a->product_id ) {
									echo '<a href="' . esc_url( get_edit_post_link( $a->product_id ) ) . '">#' . esc_html( $a->product_id ) . '</a>';
									if ( $a->variation_id ) {
										echo ' / Var #' . esc_html( $a->variation_id );
									}
								}
								?>
							</td>
							<td><?php echo esc_html( $a->status ); ?></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function page_accounts() {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'accounts' );
		$rows  = $wpdb->get_results( "SELECT a.*, u.display_name, u.user_email FROM {$table} a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id ORDER BY a.id DESC LIMIT 100" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'حساب کاربران', 'asset-wallet' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th><?php esc_html_e( 'کاربر', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'ایمیل', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'تاریخ ایجاد', 'asset-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r->id ); ?></td>
							<td><?php echo esc_html( $r->display_name ); ?> (#<?php echo esc_html( $r->user_id ); ?>)</td>
							<td><?php echo esc_html( $r->user_email ); ?></td>
							<td><?php echo esc_html( $r->status ); ?></td>
							<td><?php echo esc_html( $r->created_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function page_transactions() {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'transactions' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'تراکنش‌های دارایی (Ledger)', 'asset-wallet' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th><?php esc_html_e( 'حساب', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'دارایی', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'نوع', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'مقدار', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'قبل', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'بعد', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'قیمت واحد', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'asset-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r->id ); ?></td>
							<td><?php echo esc_html( $r->account_id ); ?></td>
							<td><?php echo esc_html( $r->asset_id ); ?></td>
							<td><?php echo esc_html( $r->type ); ?></td>
							<td><?php echo esc_html( $r->quantity ); ?></td>
							<td><?php echo esc_html( $r->balance_before ); ?></td>
							<td><?php echo esc_html( $r->balance_after ); ?></td>
							<td><?php echo $r->unit_price ? esc_html( number_format_i18n( $r->unit_price ) ) : '—'; ?></td>
							<td><?php echo esc_html( $r->created_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function page_sales() {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'sale_requests' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'درخواست‌های فروش', 'asset-wallet' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th><?php esc_html_e( 'کاربر', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'دارایی', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'مقدار', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'قیمت واحد', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'مبلغ کل', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'asset-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r->id ); ?></td>
							<td><?php echo esc_html( $r->user_id ); ?></td>
							<td><?php echo esc_html( $r->asset_id ); ?></td>
							<td><?php echo esc_html( $r->quantity ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $r->unit_price ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $r->total_price ) ); ?></td>
							<td><?php echo esc_html( $r->status ); ?></td>
							<td><?php echo esc_html( $r->created_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function page_delivery() {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'delivery_requests' );

		if ( isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'asset_wallet_delivery_action' ) ) {
			$id = absint( $_GET['id'] );
			if ( 'complete' === $_GET['action'] ) {
				$result = Asset_Wallet_Delivery::complete( $id );
				echo is_wp_error( $result )
					? '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'
					: '<div class="notice notice-success"><p>تکمیل شد.</p></div>';
			} elseif ( 'cancel' === $_GET['action'] ) {
				$result = Asset_Wallet_Delivery::cancel( $id );
				echo is_wp_error( $result )
					? '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'
					: '<div class="notice notice-success"><p>لغو شد.</p></div>';
			}
		}

		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'درخواست‌های تحویل فیزیکی', 'asset-wallet' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th><?php esc_html_e( 'کاربر', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'دارایی', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'مقدار', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'asset-wallet' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'asset-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r->id ); ?></td>
							<td><?php echo esc_html( $r->user_id ); ?></td>
							<td><?php echo esc_html( $r->asset_id ); ?></td>
							<td><?php echo esc_html( $r->quantity ); ?></td>
							<td><?php echo esc_html( $r->status ); ?></td>
							<td><?php echo esc_html( $r->created_at ); ?></td>
							<td>
								<?php if ( in_array( $r->status, array( 'reserved', 'processing' ), true ) ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=asset-wallet-delivery&action=complete&id=' . $r->id ), 'asset_wallet_delivery_action' ) ); ?>"><?php esc_html_e( 'تکمیل', 'asset-wallet' ); ?></a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=asset-wallet-delivery&action=cancel&id=' . $r->id ), 'asset_wallet_delivery_action' ) ); ?>"><?php esc_html_e( 'لغو', 'asset-wallet' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function page_settings() {
		if ( isset( $_POST['asset_wallet_save_settings'] ) && check_admin_referer( 'asset_wallet_settings' ) ) {
			$fields = array(
				'otp_expire_seconds', 'otp_max_attempts', 'otp_resend_cooldown',
				'otp_max_requests_window', 'otp_max_requests_count',
				'sms_template_id', 'sms_otp_param_name', 'live_price_interval',
			);
			foreach ( $fields as $f ) {
				if ( isset( $_POST[ $f ] ) ) {
					update_option( 'asset_wallet_' . $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
				}
			}
			echo '<div class="notice notice-success"><p>' . esc_html__( 'تنظیمات ذخیره شد.', 'asset-wallet' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'تنظیمات کیف دارایی', 'asset-wallet' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'asset_wallet_settings' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'زمان انقضای OTP (ثانیه)', 'asset-wallet' ); ?></th>
						<td><input type="number" name="otp_expire_seconds" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'otp_expire_seconds', 120 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'حداکثر تلاش OTP', 'asset-wallet' ); ?></th>
						<td><input type="number" name="otp_max_attempts" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'otp_max_attempts', 5 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'فاصله ارسال مجدد OTP (ثانیه)', 'asset-wallet' ); ?></th>
						<td><input type="number" name="otp_resend_cooldown" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'otp_resend_cooldown', 60 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'پنجره محدودیت درخواست OTP (ثانیه)', 'asset-wallet' ); ?></th>
						<td><input type="number" name="otp_max_requests_window" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'otp_max_requests_window', 900 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'حداکثر تعداد درخواست در پنجره', 'asset-wallet' ); ?></th>
						<td><input type="number" name="otp_max_requests_count" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'otp_max_requests_count', 5 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'شناسه Template پیامک', 'asset-wallet' ); ?></th>
						<td><input type="number" name="sms_template_id" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'sms_template_id', 604197 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'نام پارامتر OTP در Template', 'asset-wallet' ); ?></th>
						<td><input type="text" name="sms_otp_param_name" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'sms_otp_param_name', 'code' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'فاصله بروزرسانی قیمت Live (ثانیه)', 'asset-wallet' ); ?></th>
						<td><input type="number" name="live_price_interval" value="<?php echo esc_attr( Asset_Wallet_Helpers::get_option( 'live_price_interval', 8 ) ); ?>" /></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" name="asset_wallet_save_settings" class="button button-primary"><?php esc_html_e( 'ذخیره تنظیمات', 'asset-wallet' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	public function page_reconciliation() {
		$results = array();
		if ( isset( $_POST['run_reconciliation'] ) && check_admin_referer( 'asset_wallet_reconciliation' ) ) {
			$results = Asset_Wallet_Reconciliation::recalculate();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reconciliation — بازمحاسبه موجودی از Ledger', 'asset-wallet' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'asset_wallet_reconciliation' ); ?>
				<p><?php esc_html_e( 'این ابزار موجودی فعلی جدول balances را با مجموع تراکنش‌های Ledger مقایسه می‌کند و در صورت اختلاف، موجودی را اصلاح می‌کند.', 'asset-wallet' ); ?></p>
				<button type="submit" name="run_reconciliation" class="button button-primary"><?php esc_html_e( 'اجرای Reconciliation', 'asset-wallet' ); ?></button>
			</form>
			<?php if ( ! empty( $results ) ) : ?>
				<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
					<thead>
						<tr>
							<th>Account</th>
							<th>Asset</th>
							<th>Current</th>
							<th>Ledger</th>
							<th>Status</th>
							<th>Difference</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $results as $r ) : ?>
							<tr style="<?php echo 'MISMATCH' === $r['status'] ? 'background:#ffebee;' : ''; ?>">
								<td><?php echo esc_html( $r['account_id'] ); ?></td>
								<td><?php echo esc_html( $r['asset_id'] ); ?></td>
								<td><?php echo esc_html( $r['current'] ); ?></td>
								<td><?php echo esc_html( $r['ledger'] ); ?></td>
								<td><strong><?php echo esc_html( $r['status'] ); ?></strong></td>
								<td><?php echo esc_html( $r['difference'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
