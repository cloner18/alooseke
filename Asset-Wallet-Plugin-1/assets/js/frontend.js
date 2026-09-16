(function ($) {
	'use strict';

	var AW = {
		saleRequestId: null,
		priceTimer: null,
		currentAssetId: null,
		currentUnitPrice: 0,
		confirmNewPrice: false,

		init: function () {
	if (!$('#asset-wallet-sell').length) {
		return;
	}

	this.bindEvents();

	var self = this;
	setTimeout(function () {
		var $select = $('#aw-asset-select');
		if (!$select.length) {
			return;
		}
		if (!$select.val()) {
			var $first = $select.find('option[value!=""]').first();
			if ($first.length) {
				$select.val($first.val());
			}
		}
		if ($select.val()) {
			self.onAssetChange();
		}
	}, 50);
},

		bindEvents: function () {
			var self = this;

			$('#aw-asset-select').on('change', function () {
				self.onAssetChange();
			});

			$('#aw-quantity').on('input change', function () {
				self.fetchPrice();
			});

			$('.aw-qty-btn').on('click', function () {
				var action = $(this).data('action');
				var $input = $('#aw-quantity');
				var val = parseFloat($input.val()) || 0;
				var step = 1;
				if (action === 'plus') {
					val += step;
				} else {
					val = Math.max(0, val - step);
				}
				$input.val(val);
				self.fetchPrice();
			});

			$('#aw-request-sale').on('click', function () {
				self.createSaleRequest();
			});

			$('#aw-verify-otp').on('click', function () {
				self.verifyOtp();
			});

			$('#aw-resend-otp').on('click', function () {
				self.resendOtp();
			});

			$('#aw-confirm-new-price').on('click', function () {
				self.confirmNewPrice = true;
				$('#aw-price-confirm').hide();
				self.verifyOtp();
			});

			$('#aw-cancel-price').on('click', function () {
				self.confirmNewPrice = false;
				$('#aw-price-confirm').hide();
				self.showMessage(assetWallet.i18n.error || 'انصراف داده شد.', 'info');
			});
		},

		onAssetChange: function () {
			var $opt = $('#aw-asset-select option:selected');
			var assetId = $opt.val();
			var available = $opt.data('available');
			var unit = $opt.data('unit');

			this.currentAssetId = assetId || null;
			this.stopPricePolling();

			if (!assetId) {
				$('.aw-balance-info').hide();
				$('#aw-request-sale').prop('disabled', true);
				$('#aw-unit-price, #aw-total-price').text('—');
				return;
			}

			$('.aw-balance-info').show();
			var availNum = parseFloat(available) || 0;
var formatted;

if (unit === 'gram') {
	// برای گرم تا ۸ رقم اعشار، صفرهای اضافی حذف شود
	formatted = availNum.toLocaleString('fa-IR', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 8
	}) + ' گرم';
} else {
	// برای عدد بدون اعشار
	formatted = availNum.toLocaleString('fa-IR', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 0
	}) + ' عدد';
}

$('#aw-available-display').text(formatted);
			$('#aw-quantity').attr('max', available);
			$('#aw-request-sale').prop('disabled', false);

			this.fetchPrice();
			this.startPricePolling();
		},

		fetchPrice: function () {
			var self = this;
			var assetId = this.currentAssetId;
			var quantity = parseFloat($('#aw-quantity').val()) || 0;

			if (!assetId || quantity <= 0) {
				$('#aw-unit-price, #aw-total-price').text('—');
				return;
			}

			$.ajax({
				url: assetWallet.ajax_url,
				type: 'GET',
				data: {
					action: 'asset_wallet_get_sell_price',
					nonce: assetWallet.nonce,
					asset_id: assetId,
					quantity: quantity
				},
				success: function (res) {
					if (res.success && res.data) {
						self.currentUnitPrice = res.data.unit_price;
						$('#aw-unit-price').text(self.formatPrice(res.data.unit_price));
						$('#aw-total-price').text(self.formatPrice(res.data.total_price));
						$('#aw-price-updated').text('آخرین بروزرسانی: چند ثانیه پیش');
					} else {
						$('#aw-unit-price, #aw-total-price').text('—');
						if (res.data && res.data.message) {
							self.showMessage(res.data.message, 'error');
						}
					}
				},
				error: function () {
					$('#aw-unit-price, #aw-total-price').text('—');
				}
			});
		},

		startPricePolling: function () {
			var self = this;
			this.stopPricePolling();
			var interval = assetWallet.live_price_interval || 8000;
			this.priceTimer = setInterval(function () {
				self.fetchPrice();
			}, interval);
		},

		stopPricePolling: function () {
			if (this.priceTimer) {
				clearInterval(this.priceTimer);
				this.priceTimer = null;
			}
		},

		createSaleRequest: function () {
			var self = this;
			var assetId = this.currentAssetId;
			var quantity = parseFloat($('#aw-quantity').val()) || 0;

			if (!assetId || quantity <= 0) {
				this.showMessage('مقدار یا دارایی نامعتبر است.', 'error');
				return;
			}

			$('#aw-request-sale').prop('disabled', true).text(assetWallet.i18n.loading);

			$.ajax({
				url: assetWallet.ajax_url,
				type: 'POST',
				data: {
					action: 'asset_wallet_create_sale_request',
					nonce: assetWallet.nonce,
					asset_id: assetId,
					quantity: quantity
				},
				success: function (res) {
					$('#aw-request-sale').prop('disabled', false).text('درخواست فروش');
					if (res.success) {
    self.saleRequestId = res.data.sale_request_id;
    $('#aw-sale-request-id').val(self.saleRequestId);
    $('#aw-request-sale').hide();           // مخفی کردن دکمه فروش
    $('#aw-otp-section').slideDown();
    self.showMessage(res.data.message || assetWallet.i18n.otp_sent, 'success');
    self.stopPricePolling();
} else {
						self.showMessage(res.data.message || assetWallet.i18n.error, 'error');
					}
				},
				error: function () {
					$('#aw-request-sale').show().prop('disabled', false).text('فروش');
					self.showMessage(assetWallet.i18n.error, 'error');
				}
			});
		},

		verifyOtp: function () {
			var self = this;
			var otp = $('#aw-otp-input').val().trim();
			var saleId = this.saleRequestId || $('#aw-sale-request-id').val();

			if (!otp || !saleId) {
				this.showMessage('کد تأیید را وارد کنید.', 'error');
				return;
			}

			$('#aw-verify-otp').prop('disabled', true).text(assetWallet.i18n.loading);

			$.ajax({
				url: assetWallet.ajax_url,
				type: 'POST',
				data: {
					action: 'asset_wallet_verify_sale_otp',
					nonce: assetWallet.nonce,
					sale_request_id: saleId,
					otp: otp,
					confirm_new_price: self.confirmNewPrice ? 1 : 0
				},
				success: function (res) {
					$('#aw-verify-otp').prop('disabled', false).text(assetWallet.i18n.submit);

					if (res.success) {
						self.showMessage(res.data.message || assetWallet.i18n.sale_success, 'success');
						$('#aw-otp-section').hide();
						setTimeout(function () {
							location.reload();
						}, 2000);
					} else if (res.data && res.data.code === 'price_changed') {
						var pd = res.data.price_data || {};
						var msg = 'قیمت فروش به‌روزرسانی شد.\n' +
							'قیمت جدید هر واحد: ' + self.formatPrice(pd.new_unit_price) + '\n' +
							'مبلغ جدید: ' + self.formatPrice(pd.new_total) + '\n' +
							'آیا با قیمت جدید ادامه می‌دهید؟';
						$('#aw-price-confirm-msg').html(msg.replace(/\n/g, '<br>'));
						$('#aw-price-confirm').show();
					} else {
						self.showMessage((res.data && res.data.message) || assetWallet.i18n.error, 'error');
					}
				},
				error: function () {
					$('#aw-verify-otp').prop('disabled', false).text(assetWallet.i18n.submit);
					self.showMessage(assetWallet.i18n.error, 'error');
				}
			});
		},

		resendOtp: function () {
			var self = this;
			var saleId = this.saleRequestId || $('#aw-sale-request-id').val();

			$('#aw-resend-otp').prop('disabled', true);

			$.ajax({
				url: assetWallet.ajax_url,
				type: 'POST',
				data: {
					action: 'asset_wallet_resend_otp',
					nonce: assetWallet.nonce,
					sale_request_id: saleId
				},
				success: function (res) {
					$('#aw-resend-otp').prop('disabled', false);
					if (res.success) {
						self.showMessage(res.data.message || 'کد مجدداً ارسال شد.', 'success');
					} else {
						self.showMessage((res.data && res.data.message) || assetWallet.i18n.error, 'error');
					}
				},
				error: function () {
					$('#aw-resend-otp').prop('disabled', false);
					self.showMessage(assetWallet.i18n.error, 'error');
				}
			});
		},

		formatPrice: function (num) {
			if (typeof num !== 'number') {
				num = parseFloat(num) || 0;
			}
			return num.toLocaleString('fa-IR') + ' تومان';
		},

		showMessage: function (text, type) {
			var cls = 'aw-msg aw-msg-' + (type || 'info');
			$('#aw-messages').html('<div class="' + cls + '">' + text + '</div>');
		}
	};

	$(document).ready(function () {
		AW.init();
	});

})(jQuery);
