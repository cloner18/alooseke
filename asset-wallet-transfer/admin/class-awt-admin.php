<?php
defined( 'ABSPATH' ) || exit;

class AWT_Admin {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
	}

	public function menu() {
		// Attach under Asset Wallet if exists, else top-level
		$parent = 'asset-wallet';
		global $submenu;
		if ( ! isset( $submenu['asset-wallet'] ) ) {
			add_menu_page( 'انتقال‌ها', 'انتقال دارایی', 'manage_woocommerce', 'awt-transfers', array( $this, 'page' ), 'dashicons-randomize', 57 );
			return;
		}
		add_submenu_page( $parent, 'انتقال‌ها', 'انتقال‌ها', 'manage_woocommerce', 'awt-transfers', array( $this, 'page' ) );
	}

	public function page() {
		global $wpdb;
		$table = AWT_Database::table();

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$type   = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$where  = array( '1=1' );
		$params = array();

		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(transaction_reference LIKE %s OR sender_full_name LIKE %s OR receiver_full_name LIKE %s OR sender_mobile LIKE %s OR receiver_mobile LIKE %s OR product_name LIKE %s)';
			array_push( $params, $like, $like, $like, $like, $like, $like );
		}
		if ( $type ) {
			$where[] = 'transfer_type = %s';
			$params[] = $type;
		}
		if ( $status ) {
			$where[] = 'status = %s';
			$params[] = $status;
		}

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 100';
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
		?>
		<div class="wrap">
			<h1>انتقال‌های کیف دارایی / کیف پول</h1>
			<form method="get" style="margin:15px 0;">
				<input type="hidden" name="page" value="awt-transfers" />
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجو: نام، موبایل، شماره تراکنش..." style="width:280px;" />
				<select name="type">
					<option value="">همه انواع</option>
					<option value="asset" <?php selected( $type, 'asset' ); ?>>دارایی</option>
					<option value="wallet" <?php selected( $type, 'wallet' ); ?>>کیف پول</option>
				</select>
				<select name="status">
					<option value="">همه وضعیت‌ها</option>
					<option value="completed" <?php selected( $status, 'completed' ); ?>>تکمیل‌شده</option>
					<option value="failed" <?php selected( $status, 'failed' ); ?>>ناموفق</option>
					<option value="pending_otp" <?php selected( $status, 'pending_otp' ); ?>>در انتظار OTP</option>
				</select>
				<button class="button">فیلتر</button>
			</form>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>شماره تراکنش</th>
						<th>فرستنده</th>
						<th>گیرنده</th>
						<th>نوع</th>
						<th>جزئیات</th>
						<th>وضعیت</th>
						<th>تاریخ</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7">موردی یافت نشد.</td></tr>
					<?php else : foreach ( $rows as $r ) : ?>
						<tr>
							<td><code><?php echo esc_html( $r->transaction_reference ); ?></code></td>
							<td>
								<?php echo esc_html( $r->sender_full_name ); ?><br>
								<small><?php echo esc_html( $r->sender_mobile ); ?></small>
							</td>
							<td>
								<?php echo esc_html( $r->receiver_full_name ); ?><br>
								<small><?php echo esc_html( $r->receiver_mobile ); ?></small>
							</td>
							<td><?php echo 'asset' === $r->transfer_type ? 'دارایی' : 'کیف پول'; ?></td>
							<td>
								<?php
								if ( 'asset' === $r->transfer_type ) {
									echo esc_html( $r->product_name . ' × ' . $r->quantity );
								} else {
									echo esc_html( number_format_i18n( $r->amount ) . ' تومان' );
								}
								?>
							</td>
							<td><?php echo esc_html( $r->status ); ?></td>
							<td><?php echo esc_html( $r->completed_at ?: $r->created_at ); ?></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
