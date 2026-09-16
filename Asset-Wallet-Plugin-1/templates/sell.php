<?php
/**
 * Sell assets template - استایل یکپارچه با سایت
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

$user_id  = get_current_user_id();
$account  = Asset_Wallet_Accounts::get_or_create( $user_id );
$balances = $account ? Asset_Wallet_Balances::get_all_for_account( $account->id ) : array();
?>

<div class="zarnegar-card" dir="rtl" id="asset-wallet-sell"style="width: 50%;margin-left: auto;margin-right: auto;">

	<div class="aw-sell-header">
		
		<div>
			<h2 class="aw-sell-title"><?php esc_html_e( 'فروش دارایی', 'asset-wallet' ); ?></h2>
			<p class="aw-sell-subtitle"><?php esc_html_e( 'دارایی خود را با قیمت لحظه‌ای بفروشید', 'asset-wallet' ); ?></p>
		</div>
	</div>

	<?php if ( empty( $balances ) ) : ?>

		<div class="aw-sell-empty">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="rgba(255,255,255,0.35)"/>
			</svg>
			<p><?php esc_html_e( 'موجودی قابل فروشی ندارید.', 'asset-wallet' ); ?></p>
		</div>

	<?php else : ?>

		<div class="asset-wallet-sell-form">

			<!-- انتخاب دارایی -->
			<div class="aw-form-group">
				<label for="aw-asset-select"><?php esc_html_e( 'انتخاب دارایی', 'asset-wallet' ); ?></label>
				<div class="aw-select-wrap">
					<select id="aw-asset-select" class="aw-input">
	<?php
	$first = true;
	foreach ( $balances as $b ) :
		$available = Asset_Wallet_Helpers::decimal_sub( $b->quantity, $b->reserved_quantity );
		if ( Asset_Wallet_Helpers::decimal_cmp( $available, '0' ) <= 0 ) {
			continue;
		}
		?>
		<option
			value="<?php echo esc_attr( $b->asset_id ); ?>"
			data-available="<?php echo esc_attr( $available ); ?>"
			data-unit="<?php echo esc_attr( $b->unit ); ?>"
			data-name="<?php echo esc_attr( $b->name ); ?>"
			<?php echo $first ? 'selected' : ''; ?>
		>
			<?php echo esc_html( $b->name ); ?>
		</option>
		<?php
		$first = false;
	endforeach;
	?>
</select>
				</div>
			</div>

			<!-- موجودی -->
			<div class="aw-form-group aw-balance-info" style="display:none;">
				<div class="aw-info-badge">
					<span><?php esc_html_e( 'موجودی قابل فروش:', 'asset-wallet' ); ?></span>
					<strong id="aw-available-display">—</strong>
				</div>
			</div>

			<!-- تعداد -->
			<div class="aw-form-group">
				<label for="aw-quantity"style="text-align: center;"><?php esc_html_e( 'تعداد / مقدار فروش', 'asset-wallet' ); ?></label>
				<div class="aw-qty-control">
					<button type="button" class="aw-qty-btn" data-action="minus">−</button>
					<input type="number" id="aw-quantity" class="aw-input aw-qty-input" min="0.00000001" step="any" value="1" />
					<button type="button" class="aw-qty-btn" data-action="plus">+</button>
				</div>
			</div>

			<!-- باکس قیمت -->
			<div class="aw-price-box">
				<div class="aw-price-line">
					<span><?php esc_html_e( 'قیمت لحظه ای فروش', 'asset-wallet' ); ?></span>
					<strong id="aw-unit-price">—</strong>
				</div>
				<div class="aw-price-divider"></div>
				<div class="aw-price-line aw-price-total-line">
					<span><?php esc_html_e( 'مبلغ دریافتی', 'asset-wallet' ); ?></span>
					<strong id="aw-total-price" class="aw-total">—</strong>
				</div>
				<div class="aw-price-updated">
					<small id="aw-price-updated"><?php esc_html_e( 'آخرین بروزرسانی: —', 'asset-wallet' ); ?></small>
				</div>
			</div>

			<!-- دکمه درخواست -->
			<div class="aw-form-group">
				<button type="button" id="aw-request-sale" class="aw-btn aw-btn-primary" disabled>
				  <img src="http://aloseke.com/wp-content/uploads/2026/06/shopping-bag-arrow-down.svg" alt="">
					<?php esc_html_e( 'فروش', 'asset-wallet' ); ?>
				</button>
			</div>

			<!-- بخش OTP -->
			<div id="aw-otp-section" class="aw-otp-section" style="display:none;">
				<div class="aw-otp-header">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="#F8C15B"/></svg>
					<span><?php esc_html_e( 'کد تأیید پیامک شده را وارد کنید', 'asset-wallet' ); ?></span>
				</div>
				<div class="aw-form-group">
					<input type="text" id="aw-otp-input" class="aw-input aw-otp-input" maxlength="8" autocomplete="one-time-code" placeholder="------" />
				</div>
				<div class="aw-otp-actions">
					<button type="button" id="aw-verify-otp" class="aw-btn aw-btn-primary">
						<?php esc_html_e( 'تأیید و فروش', 'asset-wallet' ); ?>
					</button>
					<button type="button" id="aw-resend-otp" class="aw-btn aw-btn-ghost">
						<?php esc_html_e( 'ارسال مجدد کد', 'asset-wallet' ); ?>
					</button>
				</div>
				<input type="hidden" id="aw-sale-request-id" value="" />
			</div>

			<!-- تأیید تغییر قیمت -->
			<div id="aw-price-confirm" class="aw-price-confirm" style="display:none;">
				<p id="aw-price-confirm-msg"></p>
				<div class="aw-otp-actions">
					<button type="button" id="aw-confirm-new-price" class="aw-btn aw-btn-primary">
						<?php esc_html_e( 'تأیید قیمت جدید و ادامه', 'asset-wallet' ); ?>
					</button>
					<button type="button" id="aw-cancel-price" class="aw-btn aw-btn-ghost">
						<?php esc_html_e( 'انصراف', 'asset-wallet' ); ?>
					</button>
				</div>
			</div>

			<div id="aw-messages" class="aw-messages"></div>
		</div>

	<?php endif; ?>
</div>
<style>
/* ========== Sell Form - Gold App Style ========== */
.asset-wallet-sell-container.gold-app-style {
	font-family: inherit;
	direction: rtl;
	padding: 24px;
	color: #fff;
	background: radial-gradient(circle at 15% 10%, rgba(80, 130, 255, 0.12), transparent 40%),
		linear-gradient(135deg, rgba(255, 255, 255, 0.085), rgba(255, 255, 255, 0.025));
	border: 1px solid rgba(255, 255, 255, 0.14);
	backdrop-filter: blur(20px) saturate(140%);
	-webkit-backdrop-filter: blur(20px) saturate(140%);
	box-shadow: 0 12px 40px rgba(0, 0, 0, 0.28), inset 0 1px 1px rgba(255, 255, 255, 0.08);
	border-radius: 22px;
	max-width: 520px;
	margin: 0 auto 2rem;
}

.aw-sell-header {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 28px;
	padding-bottom: 18px;
	border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.aw-sell-header-icon {
	width: 48px;
	height: 48px;
	display: flex;
	align-items: center;
	justify-content: center;

	border-radius: 14px;

}

.aw-sell-title {
	margin: 0;
	font-size: 1.25rem;
	font-weight: 700;
	color: #f8c15b;
}

.aw-sell-subtitle {
	margin: 4px 0 0;
	font-size: 0.85rem;
	color: rgba(255, 255, 255, 0.55);
}

.aw-sell-empty {
	text-align: center;
	padding: 40px 20px;
	color: rgba(255, 255, 255, 0.5);
}

.aw-sell-empty svg {
	margin-bottom: 12px;
	opacity: 0.6;
}

.aw-form-group {
	margin-bottom: 18px;
}

.aw-form-group label {
	display: block;
	margin-bottom: 8px;
	font-size: 0.9rem;
	font-weight: 600;
	color: rgba(255, 255, 255, 0.75);
}

.aw-input {

}

.aw-input:focus {
	border-color: rgba(248, 193, 91, 0.5);
	background: rgba(255, 255, 255, 0.08);
}

.aw-input option {
	background: #1a1a2e;
	color: #fff;
}






.aw-info-badge {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 14px;
	background: #ffffff05;
	border: 1px solid rgba(255, 255, 255, 0.14);
	border-radius: 12px;
	font-size: 0.9rem;
	color: rgba(255, 255, 255, 0.8);
}

.aw-info-badge strong {
	color: #F8C15B;
}



.aw-price-line {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 15px;
	color: rgb(255 255 255);
}

.aw-price-line strong {
	color: #fff;
	font-weight: 600;
}

.aw-price-divider {
	height: 1px;
	background: rgba(255, 255, 255, 0.08);
	margin: 12px 0;
}





.aw-price-updated {
	margin-top: 10px;
	text-align: left;
	color: rgba(255, 255, 255, 0.35);
	font-size: 0.8rem;
}

.aw-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	padding: 14px 24px;
	border: none;
	border-radius: 14px;
	font-size: 1rem;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.2s;
}


.aw-btn-primary:hover:not(:disabled) {
	transform: translateY(-1px);

}

.aw-btn-primary:disabled {
	opacity: 0.4;
	cursor: not-allowed;
	transform: none;
	box-shadow: none;
}

.aw-btn-ghost {
	background: transparent;
	color: rgba(255, 255, 255, 0.7);
	border: 1px solid rgba(255, 255, 255, 0.14);
	margin-top: 10px;
	    background-color: #bba57e00!important;
}

.aw-btn-ghost:hover {
	background: rgba(255, 255, 255, 0.06);
	color: #fff;
}

.aw-otp-section {
	margin-top: 20px;
	padding: 20px;

}

.aw-otp-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 14px;
	font-weight: 600;
	color: rgba(255, 255, 255, 0.85);
}

.aw-otp-input {
	text-align: center;
	letter-spacing: 8px;
	font-size: 1.4rem;
	font-weight: 700;
}

.aw-otp-actions {
	display: flex;
	flex-direction: column;
	gap: 0;
}

.aw-price-confirm {
	margin-top: 16px;
	padding: 18px;
	background: rgba(248, 193, 91, 0.1);
	border: 1px solid rgba(248, 193, 91, 0.3);
	border-radius: 16px;
	color: #fff;
}

.aw-messages {
	margin-top: 16px;
}

.aw-messages .aw-msg {
	padding: 12px 16px;
	border-radius: 12px;
	font-size: 0.9rem;
}



.aw-msg-error {

	color: #f5a8a6;
}

.aw-msg-info {

	color: #a8c5ff;
}

@media (max-width: 480px) {
	.asset-wallet-sell-container.gold-app-style {
		padding: 18px;
		border-radius: 18px;
	}
}
</style>