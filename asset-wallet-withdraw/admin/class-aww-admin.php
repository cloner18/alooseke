<?php
defined( 'ABSPATH' ) || exit;

class AWW_Admin {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 65 );
		add_action( 'admin_post_aww_approve', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_aww_reject', array( $this, 'handle_reject' ) );
	}

	public function menu() {
		add_menu_page( 'برداشت‌ها', 'برداشت‌ها', 'manage_woocommerce', 'aww-withdrawals', array( $this, 'page' ), 'dashicons-money-alt', 58 );
	}

	public function page() {
	if ( isset( $_GET['view'] ) ) {
		$this->detail( absint( $_GET['view'] ) );
		return;
	}

	global $wpdb;
	$table  = AWW_Database::table();
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
	$type   = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';

	$where  = array( '1=1' );
	$params = array();
	if ( $search ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '(tracking_code LIKE %s OR user_mobile LIKE %s OR user_full_name LIKE %s)';
		array_push( $params, $like, $like, $like );
	}
	if ( $status ) {
		$where[] = 'status = %s';
		$params[] = $status;
	}
	if ( $type ) {
		$where[] = 'type = %s';
		$params[] = $type;
	}
	$sql  = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 100';
	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

	// شمارش وضعیت‌ها
	$counts = array(
		'all'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
		'pending'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='pending'" ),
		'approved' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='approved'" ),
		'rejected' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='rejected'" ),
	);
	?>
	<style>
		.aww-admin-wrap{max-width:1200px}
		.aww-admin-wrap h1{margin-bottom:18px}
		.aww-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
		.aww-stat{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px 18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.aww-stat .n{font-size:22px;font-weight:700;line-height:1.2}
		.aww-stat .l{color:#646970;font-size:13px;margin-top:4px}
		.aww-stat.pending .n{color:#d97706}
		.aww-stat.approved .n{color:#16a34a}
		.aww-stat.rejected .n{color:#dc2626}
		.aww-filters{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
		.aww-filters input[type=search],.aww-filters select{min-height:36px;border-radius:6px}
		.aww-filters input[type=search]{width:260px}
		.aww-table-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;overflow:hidden}
		.aww-table-card table{margin:0;border:0}
		.aww-table-card th{background:#f6f7f7}
		.aww-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600}
		.aww-badge.pending{background:#fff7ed;color:#c2410c}
		.aww-badge.approved{background:#f0fdf4;color:#15803d}
		.aww-badge.rejected{background:#fef2f2;color:#b91c1c}
		.aww-type{display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;background:#f0f0f1;color:#3c434a}
		.aww-type.asset{background:#eff6ff;color:#1d4ed8}
		.aww-type.wallet{background:#f5f3ff;color:#6d28d9}
		.aww-muted{color:#646970;font-size:12px}
		@media(max-width:782px){.aww-stats{grid-template-columns:1fr 1fr}}
	</style>

	<div class="wrap aww-admin-wrap">
		<h1>درخواست‌های برداشت</h1>

		<div class="aww-stats">
			<div class="aww-stat"><div class="n"><?php echo (int) $counts['all']; ?></div><div class="l">کل درخواست‌ها</div></div>
			<div class="aww-stat pending"><div class="n"><?php echo (int) $counts['pending']; ?></div><div class="l">در انتظار تایید</div></div>
			<div class="aww-stat approved"><div class="n"><?php echo (int) $counts['approved']; ?></div><div class="l">تایید شده</div></div>
			<div class="aww-stat rejected"><div class="n"><?php echo (int) $counts['rejected']; ?></div><div class="l">رد شده</div></div>
		</div>

		<form method="get" class="aww-filters">
			<input type="hidden" name="page" value="aww-withdrawals" />
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="پیگیری / موبایل / نام..." />
			<select name="status">
				<option value="">همه وضعیت‌ها</option>
				<option value="pending" <?php selected( $status, 'pending' ); ?>>در انتظار</option>
				<option value="approved" <?php selected( $status, 'approved' ); ?>>تایید شده</option>
				<option value="rejected" <?php selected( $status, 'rejected' ); ?>>رد شده</option>
			</select>
			<select name="type">
				<option value="">همه انواع</option>
				<option value="wallet" <?php selected( $type, 'wallet' ); ?>>موجودی</option>
				<option value="asset" <?php selected( $type, 'asset' ); ?>>دارایی</option>
			</select>
			<button class="button button-primary">اعمال فیلتر</button>
			<?php if ( $search || $status || $type ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aww-withdrawals' ) ); ?>">پاک کردن</a>
			<?php endif; ?>
		</form>

		<div class="aww-table-card">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th>پیگیری</th>
						<th>کاربر</th>
						<th>موبایل</th>
						<th>نوع</th>
						<th>جزئیات</th>
						<th>وضعیت</th>
						<th>تاریخ</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="8" style="text-align:center;padding:40px;color:#646970;">موردی یافت نشد.</td></tr>
					<?php else : foreach ( $rows as $r ) : ?>
						<tr>
							<td><code><?php echo esc_html( $r->tracking_code ); ?></code></td>
							<td>
								<strong><?php echo esc_html( $r->user_full_name ); ?></strong>
								<div class="aww-muted">ID: <?php echo (int) $r->user_id; ?></div>
							</td>
							<td style="direction:ltr;text-align:right;"><?php echo esc_html( $r->user_mobile ); ?></td>
							<td>
								<span class="aww-type <?php echo esc_attr( $r->type ); ?>">
									<?php echo 'wallet' === $r->type ? 'موجودی' : 'دارایی'; ?>
								</span>
							</td>
							<td>
								<?php
								if ( 'wallet' === $r->type ) {
									echo '<strong>' . esc_html( number_format_i18n( (float) $r->amount ) ) . '</strong> تومان';
								} else {
									echo esc_html( $r->product_name . ' × ' . rtrim( rtrim( (string) $r->quantity, '0' ), '.' ) );
								}
								?>
							</td>
							<td><span class="aww-badge <?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( AWW_Helpers::status_label( $r->status ) ); ?></span></td>
							<td class="aww-muted"><?php echo esc_html( AWW_Helpers::format_date( $r->created_at ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=aww-withdrawals&view=' . $r->id ) ); ?>">جزئیات</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}

private function detail( $id ) {
	$row = AWW_Service::get( $id );
	if ( ! $row ) {
		echo '<div class="wrap"><p>یافت نشد.</p></div>';
		return;
	}
	$wallet_bal = AWW_Helpers::wallet_balance( $row->user_id );
	?>
	<style>
		.aww-detail{max-width:960px}
		.aww-detail-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:20px;margin-top:16px}
		.aww-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:22px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.aww-card h2{margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid #eee;font-size:16px}
		.aww-dl{display:grid;grid-template-columns:140px 1fr;gap:10px 12px;align-items:start}
		.aww-dl .k{color:#646970;font-size:13px}
		.aww-dl .v{font-weight:600;color:#1d2327}
		.aww-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
		.aww-badge.pending{background:#fff7ed;color:#c2410c}
		.aww-badge.approved{background:#f0fdf4;color:#15803d}
		.aww-badge.rejected{background:#fef2f2;color:#b91c1c}
		.aww-actions{display:flex;flex-direction:column;gap:12px}
		.aww-actions textarea{width:100%;border-radius:8px;padding:10px;min-height:80px}
		.aww-actions .button{width:100%;justify-content:center;text-align:center}
		.aww-balance-box{background:#f6f7f7;border-radius:10px;padding:14px;margin-top:8px}
		.aww-balance-box strong{font-size:18px}
		@media(max-width:782px){.aww-detail-grid{grid-template-columns:1fr}}
	</style>

	<div class="wrap aww-detail">
		<h1>
			جزئیات درخواست
			<code style="font-size:14px;"><?php echo esc_html( $row->tracking_code ); ?></code>
		</h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aww-withdrawals' ) ); ?>">&larr; بازگشت به لیست</a></p>

		<div class="aww-detail-grid">
			<div class="aww-card">
				<h2>اطلاعات درخواست</h2>
				<div class="aww-dl">
					<div class="k">وضعیت</div>
					<div class="v"><span class="aww-badge <?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( AWW_Helpers::status_label( $row->status ) ); ?></span></div>

					<div class="k">کاربر</div>
					<div class="v"><?php echo esc_html( $row->user_full_name ); ?> <span style="color:#646970;font-weight:400;">(ID: <?php echo (int) $row->user_id; ?>)</span></div>

					<div class="k">موبایل</div>
					<div class="v" style="direction:ltr;text-align:right;"><?php echo esc_html( $row->user_mobile ); ?></div>

					<div class="k">نوع</div>
					<div class="v"><?php echo 'wallet' === $row->type ? 'برداشت موجودی' : 'برداشت دارایی'; ?></div>

					<?php if ( 'wallet' === $row->type ) : ?>
						<div class="k">مبلغ</div>
						<div class="v" style="color:#b45309;font-size:18px;"><?php echo esc_html( number_format_i18n( (float) $row->amount ) ); ?> تومان</div>
						<div class="k">شماره کارت</div>
						<div class="v" style="direction:ltr;text-align:right;"><?php echo esc_html( $row->card_number ); ?></div>
						<div class="k">شبا</div>
						<div class="v" style="direction:ltr;text-align:right;"><?php echo esc_html( $row->card_sheba ); ?></div>
						<div class="k">بانک</div>
						<div class="v"><?php echo esc_html( $row->card_bank ); ?></div>
						<div class="k">دارنده کارت</div>
						<div class="v"><?php echo esc_html( $row->card_holder ); ?></div>
					<?php else : ?>
						<div class="k">دارایی</div>
						<div class="v"><?php echo esc_html( $row->product_name . ' × ' . rtrim( rtrim( (string) $row->quantity, '0' ), '.' ) ); ?></div>
						<div class="k">گیرنده</div>
						<div class="v"><?php echo esc_html( $row->shipping_full_name ); ?></div>
						<div class="k">تلفن</div>
						<div class="v"><?php echo esc_html( $row->shipping_phone ); ?></div>
						<div class="k">آدرس</div>
						<div class="v"><?php echo esc_html( $row->shipping_state . '، ' . $row->shipping_city . '، ' . $row->shipping_address . ' — ' . $row->shipping_postcode ); ?></div>
					<?php endif; ?>

					<div class="k">تاریخ ثبت</div>
					<div class="v"><?php echo esc_html( AWW_Helpers::format_date( $row->created_at ) ); ?></div>

					<?php if ( $row->reject_reason ) : ?>
						<div class="k">علت رد</div>
						<div class="v" style="color:#b91c1c;"><?php echo esc_html( $row->reject_reason ); ?></div>
					<?php endif; ?>
				</div>

				<div class="aww-balance-box">
					<div style="color:#646970;font-size:13px;margin-bottom:4px;">موجودی فعلی کیف پول کاربر</div>
					<strong><?php echo esc_html( number_format_i18n( $wallet_bal ) ); ?> تومان</strong>
				</div>
			</div>

			<div class="aww-card">
				<h2>عملیات مدیریت</h2>
				<?php if ( 'pending' === $row->status ) : ?>
					<div class="aww-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="aww_approve" />
							<input type="hidden" name="id" value="<?php echo (int) $row->id; ?>" />
							<?php wp_nonce_field( 'aww_admin_' . $row->id ); ?>
							<button class="button button-primary button-hero" onclick="return confirm('درخواست تایید شود؟');">✓ تایید درخواست</button>
						</form>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="aww_reject" />
							<input type="hidden" name="id" value="<?php echo (int) $row->id; ?>" />
							<?php wp_nonce_field( 'aww_admin_' . $row->id ); ?>
							<textarea name="reason" placeholder="علت رد شدن را بنویسید..." required></textarea>
							<button class="button button-hero" style="color:#b91c1c;border-color:#fca5a5;" onclick="return confirm('رد شود و موجودی برگردد؟');">✕ رد درخواست</button>
						</form>
					</div>
				<?php else : ?>
					<p style="color:#646970;margin:0;">این درخواست قبلاً بررسی شده و امکان تغییر وضعیت ندارد.</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

	public function handle_approve() {
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'aww_admin_' . $id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Forbidden' );
		}
		AWW_Service::approve( $id, get_current_user_id() );
		wp_safe_redirect( admin_url( 'admin.php?page=aww-withdrawals&view=' . $id ) );
		exit;
	}

	public function handle_reject() {
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'aww_admin_' . $id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Forbidden' );
		}
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		AWW_Service::reject( $id, $reason, get_current_user_id() );
		wp_safe_redirect( admin_url( 'admin.php?page=aww-withdrawals&view=' . $id ) );
		exit;
	}
}
