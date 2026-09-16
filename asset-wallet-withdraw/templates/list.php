<?php
defined( 'ABSPATH' ) || exit;
$rows = AWW_Service::get_user_list( get_current_user_id(), 50 );
?>
<div class="zarnegar-card aww-box" dir="rtl">
	<h2 class="aww-title">درخواست‌های برداشت من</h2>
	<?php if ( empty( $rows ) ) : ?>
		<p class="aww-empty">درخواستی ثبت نشده است.</p>
	<?php else : ?>
		<ul class="aww-list">
			<?php foreach ( $rows as $r ) :
				$status_class = 'aww-st-' . $r->status;
				?>
				<li class="aww-list-item">
					<div class="aww-li-main">
						<strong>
							<?php echo 'wallet' === $r->type ? 'برداشت موجودی' : 'برداشت دارایی'; ?>
						</strong>
						<span class="aww-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( AWW_Helpers::status_label( $r->status ) ); ?>
						</span>
						<div class="aww-li-meta">
							پیگیری: <?php echo esc_html( $r->tracking_code ); ?>
							—
							<?php
							if ( 'wallet' === $r->type ) {
								echo esc_html( number_format_i18n( (float) $r->amount ) . ' تومان' );
							} else {
								echo esc_html( $r->product_name . ' × ' . rtrim( rtrim( (string) $r->quantity, '0' ), '.' ) );
							}
							?>
						</div>
						<?php if ( 'rejected' === $r->status && $r->reject_reason ) : ?>
							<div class="aww-reject">علت رد: <?php echo esc_html( $r->reject_reason ); ?></div>
						<?php endif; ?>
					</div>
					<div class="aww-li-date"><?php echo esc_html( AWW_Helpers::format_date( $r->created_at ) ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
