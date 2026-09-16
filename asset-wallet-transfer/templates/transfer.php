<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="zarnegar-card awt-transfer" id="awt-transfer" dir="rtl">

	<div class="awt-header">
		<h2 class="awt-title">انتقال</h2>
		<p class="awt-subtitle">انتقال دارایی یا موجودی نقدی به کاربر دیگر</p>
	</div>

	<!-- Step 1: Type + Phone -->
	<div class="awt-step" data-step="1">
		<div class="awt-form-group">
			<label>نوع انتقال</label>
			<div class="awt-radio-group">
				<label class="awt-radio"><input type="radio" name="awt_type" value="asset" checked> انتقال دارایی</label>
				<label class="awt-radio"><input type="radio" name="awt_type" value="wallet"> انتقال موجودی کیف پول</label>
			</div>
		</div>
		<div class="awt-form-group">
			<label for="awt-phone">شماره موبایل گیرنده</label>
			<input type="tel" id="awt-phone" class="awt-input" placeholder="09xxxxxxxxx" maxlength="13" />
		</div>
		<button type="button" class="awt-btn awt-btn-primary" id="awt-btn-lookup">ادامه</button>
	</div>

	<!-- Step 2: Confirm receiver -->
	<div class="awt-step" data-step="2" style="display:none;">
		<div class="awt-receiver-card">
			<p class="awt-label">گیرنده</p>
			<p class="awt-receiver-name" id="awt-receiver-name">—</p>
			<p class="awt-receiver-mobile" id="awt-receiver-mobile" style="direction: ltr;">—</p>
		</div>
		<p class="awt-confirm-q">آیا اطلاعات گیرنده صحیح است؟</p>
		<button type="button" class="awt-btn awt-btn-primary" id="awt-btn-confirm-receiver">تأیید</button>
		<button type="button" class="awt-btn awt-btn-ghost" id="awt-btn-back-1">بازگشت</button>
	</div>

	<!-- Step 3a: Asset select -->
	<div class="awt-step" data-step="3a" style="display:none;">
		<div class="awt-form-group">
			<label>انتخاب دارایی</label>
			<div id="awt-assets-list" class="awt-assets-list"></div>
		</div>
		<div class="awt-form-group" id="awt-qty-wrap" style="display:none;">
			<label for="awt-quantity">مقدار انتقال</label>
			<input type="number" id="awt-quantity" class="awt-input" min="0.00000001" step="any" value="1" />
			<p class="awt-hint">موجودی: <strong id="awt-asset-avail">—</strong></p>
		</div>
		<button type="button" class="awt-btn awt-btn-primary" id="awt-btn-asset-next" disabled>ادامه</button>
		<button type="button" class="awt-btn awt-btn-ghost" id="awt-btn-back-2a">بازگشت</button>
	</div>

	<!-- Step 3b: Wallet amount -->
	<div class="awt-step" data-step="3b" style="display:none;">
		<div class="awt-balance-box">
			<span>موجودی کیف پول شما</span>
			<strong id="awt-wallet-balance">—</strong>
		</div>
		<div class="awt-form-group">
			<label for="awt-amount">مبلغ انتقال (تومان)</label>
			<input type="number" id="awt-amount" class="awt-input" min="1" step="1" />
		</div>
		<button type="button" class="awt-btn awt-btn-primary" id="awt-btn-wallet-next">ادامه</button>
		<button type="button" class="awt-btn awt-btn-ghost" id="awt-btn-back-2b">بازگشت</button>
	</div>

	<!-- Step 4: Summary + request OTP -->
	<div class="awt-step" data-step="4" style="display:none;">
		<div class="awt-summary" id="awt-summary"></div>
		<button type="button" class="awt-btn awt-btn-primary" id="awt-btn-request">درخواست انتقال و دریافت کد</button>
		<button type="button" class="awt-btn awt-btn-ghost" id="awt-btn-back-3">بازگشت</button>
	</div>

	<!-- Step 5: OTP -->
	<div class="awt-step" data-step="5" style="display:none;">
		<p class="awt-otp-hint">کد تأیید به شماره موبایل شما ارسال شد.</p>
		<div class="awt-form-group">
			<input type="text" id="awt-otp" class="awt-input awt-otp-input" maxlength="8" placeholder="------" autocomplete="one-time-code" />
		</div>
		<button type="button" class="awt-btn awt-btn-primary" id="awt-btn-verify">تأیید انتقال</button>
		<button type="button" class="awt-btn awt-btn-ghost" id="awt-btn-resend">ارسال مجدد کد</button>
	</div>

	<!-- Step 6: Success -->
	<div class="awt-step" data-step="6" style="display:none;">
		<div class="awt-success">
			<p class="awt-success-title">انتقال با موفقیت انجام شد</p>
			<div id="awt-success-details"></div>
			<p class="awt-trx">کد پیگیری: <strong id="awt-trx-ref"></strong></p>
			<button type="button" class="awt-btn awt-btn-primary" id="awt-btn-restart">
	بازگشت به بخش انتقال
</button>
<p class="awt-countdown" style="text-align:center;margin-top:12px;color:rgba(255,255,255,.55);">
	بازگشت خودکار تا <strong id="awt-countdown-num">15</strong> ثانیه
</p>
		</div>
		
	</div>

	<div id="awt-messages" class="awt-messages"></div>
	<input type="hidden" id="awt-receiver-id" value="" />
	<input type="hidden" id="awt-transfer-id" value="" />
	<input type="hidden" id="awt-selected-asset" value="" />
</div>
