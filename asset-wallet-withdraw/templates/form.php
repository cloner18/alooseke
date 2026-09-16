<?php
defined( 'ABSPATH' ) || exit;
$user_id = get_current_user_id();
$cards   = AWW_Helpers::get_user_cards( $user_id );
$ship    = AWW_Helpers::get_shipping( $user_id );
$has_ship = AWW_Helpers::has_shipping( $user_id );
$balance = AWW_Helpers::wallet_balance( $user_id );
?>
<div class="zarnegar-card aww-box" id="aww-form" dir="rtl">
	
<div class="aww-header">
		<h2 class="aww-title">درخواست برداشت</h2>
		<p class="aww-subtitle">برداشت موجودی نقدی یا درخواست تحویل فیزیکی طلا</p>
	</div>
	<div class="aww-type-group">
		<label class="aww-type-card"><input type="radio" name="aww_type" value="wallet" checked> برداشت موجودی نقدی</label>
		<label class="aww-type-card"><input type="radio" name="aww_type" value="asset"> برداشت دارایی</label>
	</div>

	<!-- Wallet -->
	<div class="aww-panel" data-type="wallet">
		<div class="aww-info">موجودی قابل برداشت: <strong id="aww-wallet-bal"><?php echo esc_html( number_format_i18n( $balance ) ); ?> تومان</strong></div>

		<?php if ( empty( $cards ) ) : ?>
			<div class="aww-empty-action">
				<p>کارتی ثبت نکرده‌اید.</p>
				<a class="aww-btn" href="https://aloseke.com/my-account/bank/">ثبت کارت بانکی</a>
			</div>
		<?php else : ?>
			<div class="aww-field">
				<label>مبلغ برداشت (تومان)</label>
				<input type="number" id="aww-amount" class="aww-input" min="1" step="1" />
			</div>
			<div class="aww-field">
				<label>انتخاب کارت</label>
				<div class="aww-cards">
					<?php foreach ( $cards as $i => $c ) :
	$cid  = (string) $i; // ایندکس آرایه = شناسه کارت
	$num  = $c['cardNo']   ?? '';
	$bank = $c['bankName'] ?? '';
	$sheba = $c['shebaNo'] ?? '';
	?>
	<label class="aww-card-item">
		<input type="radio" name="aww_card" value="<?php echo esc_attr( $cid ); ?>">
		<span>
			<?php echo esc_html( $bank ? $bank . '  ' : '' ); ?>
			<br>
			<?php echo esc_html( $num ); ?>
			<?php if ( $sheba ) : ?>
				<br><small><?php echo esc_html( $sheba ); ?></small>
			<?php endif; ?>
		</span>
	</label>
<?php endforeach; ?>
				</div>
			</div>
			<button type="button" class="aww-btn aww-btn-primary" id="aww-submit-wallet">ثبت درخواست برداشت</button>
		<?php endif; ?>
	</div>

	<!-- Asset -->
	<div class="aww-panel" data-type="asset" style="display:none;">
		<?php if ( ! $has_ship ) : ?>
			<div class="aww-empty-action">
				<p>آدرس ارسال ثبت نشده است.</p>
				<a class="aww-btn" href="https://aloseke.com/my-account/edit-address/">ثبت آدرس</a>
			</div>
		<?php else : ?>
			<div class="aww-ship-box">
				<strong>آدرس ارسال:</strong>
				<p><?php echo esc_html( $ship['full_name'] ); ?> — <?php echo esc_html( $ship['phone'] ); ?></p>
				<p><?php echo esc_html( $ship['state'] . '، ' . $ship['city'] . '، ' . $ship['address'] ); ?></p>
			</div>
			<div class="aww-field">
				<label>انتخاب دارایی</label>
				<div id="aww-assets" class="aww-assets"></div>
			</div>
			<div class="aww-field" id="aww-qty-wrap" style="display:none;">
				<label>مقدار</label>
				<input type="number" id="aww-qty" class="aww-input" min="0.00000001" step="any" value="1" />
				<small>موجودی: <span id="aww-asset-avail">—</span></small>
			</div>
			<button type="button" class="aww-btn aww-btn-primary" id="aww-submit-asset" disabled>ثبت درخواست برداشت دارایی</button>
		<?php endif; ?>
	</div>

	<div id="aww-msg" class="aww-msg"></div>
</div>
