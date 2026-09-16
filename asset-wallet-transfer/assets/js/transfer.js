(function ($) {
	'use strict';

	var state = {
		type: 'asset',
		receiverId: null,
		receiverName: '',
		assetId: null,
		assetName: '',
		assetUnit: '',
		quantity: 1,
		amount: 0,
		transferId: null
	};

	function msg(text, type) {
		$('#awt-messages').html('<div class="awt-msg awt-msg-' + (type || 'info') + '">' + text + '</div>');
	}

	function showStep(n) {
		$('.awt-step').hide();
		$('.awt-step[data-step="' + n + '"]').show();
		$('#awt-messages').empty();
	}

	function post(action, data, cb) {
		data = data || {};
		data.action = action;
		data.nonce = awtData.nonce;
		$.post(awtData.ajax_url, data).done(function (res) {
			cb(res);
		}).fail(function () {
			msg(awtData.i18n.error, 'error');
		});
	}

	$(function () {
		if (!$('#awt-transfer').length) return;

		$('input[name="awt_type"]').on('change', function () {
			state.type = $(this).val();
		});

		$('#awt-btn-lookup').on('click', function () {
			var phone = $('#awt-phone').val().trim();
			if (!phone) { msg('شماره موبایل را وارد کنید.', 'error'); return; }
			$(this).prop('disabled', true).text(awtData.i18n.loading);
			post('awt_lookup_receiver', { phone: phone }, function (res) {
				$('#awt-btn-lookup').prop('disabled', false).text('ادامه');
				if (!res.success) {
					msg(res.data.message || awtData.i18n.error, 'error');
					return;
				}
				state.receiverId = res.data.receiver_user_id;
				state.receiverName = res.data.full_name;
				$('#awt-receiver-id').val(state.receiverId);
				$('#awt-receiver-name').text(res.data.full_name);
				$('#awt-receiver-mobile').text(res.data.mobile_masked);
				showStep(2);
			});
		});

		$('#awt-btn-confirm-receiver').on('click', function () {
			if (state.type === 'asset') {
				post('awt_get_balances', {}, function (res) {
					if (!res.success) { msg(res.data.message, 'error'); return; }
					var html = '';
					(res.data.balances || []).forEach(function (b) {
						html += '<label class="awt-asset-item"><input type="radio" name="awt_asset" value="' + b.asset_id + '" data-name="' + b.name + '" data-unit="' + b.unit + '" data-avail="' + b.available + '"> ' + b.name + ' <span>(' + parseFloat(b.available).toLocaleString('fa-IR') + ' ' + (b.unit === 'gram' ? 'گرم' : 'عدد') + ')</span></label>';
					});
					if (!html) html = '<p>موجودی قابل انتقال ندارید.</p>';
					$('#awt-assets-list').html(html);
					showStep('3a');
				});
			} else {
				post('awt_get_wallet_balance', {}, function (res) {
					if (!res.success) { msg(res.data.message, 'error'); return; }
					$('#awt-wallet-balance').text(res.data.formatted);
					showStep('3b');
				});
			}
		});

		$(document).on('change', 'input[name="awt_asset"]', function () {
			state.assetId = $(this).val();
			state.assetName = $(this).data('name');
			state.assetUnit = $(this).data('unit');
			var avail = parseFloat($(this).data('avail')) || 0;
			$('#awt-asset-avail').text(avail.toLocaleString('fa-IR') + (state.assetUnit === 'gram' ? ' گرم' : ' عدد'));
			$('#awt-qty-wrap').show();
			$('#awt-btn-asset-next').prop('disabled', false);
		});

		$('#awt-btn-asset-next').on('click', function () {
			state.quantity = parseFloat($('#awt-quantity').val()) || 0;
			if (state.quantity <= 0 || !state.assetId) { msg('مقدار نامعتبر است.', 'error'); return; }
			$('#awt-summary').html(
				'<p><strong>نوع:</strong> انتقال دارایی</p>' +
				'<p><strong>گیرنده:</strong> ' + state.receiverName + '</p>' +
				'<p><strong>دارایی:</strong> ' + state.assetName + '</p>' +
				'<p><strong>مقدار:</strong> ' + state.quantity + '</p>'
			);
			showStep(4);
		});

		$('#awt-btn-wallet-next').on('click', function () {
			state.amount = parseFloat($('#awt-amount').val()) || 0;
			if (state.amount <= 0) { msg('مبلغ نامعتبر است.', 'error'); return; }
			$('#awt-summary').html(
				'<p><strong>نوع:</strong> انتقال موجودی</p>' +
				'<p><strong>گیرنده:</strong> ' + state.receiverName + '</p>' +
				'<p><strong>مبلغ:</strong> ' + state.amount.toLocaleString('fa-IR') + ' تومان</p>'
			);
			showStep(4);
		});

		$('#awt-btn-request').on('click', function () {
			$(this).prop('disabled', true).text(awtData.i18n.loading);
			var data = {
				receiver_user_id: state.receiverId,
				transfer_type: state.type,
				asset_id: state.assetId || 0,
				quantity: state.quantity || 0,
				amount: state.amount || 0
			};
			post('awt_create_transfer', data, function (res) {
				$('#awt-btn-request').prop('disabled', false).text('درخواست انتقال و دریافت کد');
				if (!res.success) { msg(res.data.message, 'error'); return; }
				state.transferId = res.data.transfer_id;
				$('#awt-transfer-id').val(state.transferId);
				msg(res.data.message, 'success');
				showStep(5);
			});
		});

		$('#awt-btn-verify').on('click', function () {
			var otp = $('#awt-otp').val().trim();
			if (!otp) { msg('کد را وارد کنید.', 'error'); return; }
			$(this).prop('disabled', true).text(awtData.i18n.loading);
			post('awt_verify_transfer', { transfer_id: state.transferId, otp: otp }, function (res) {
				$('#awt-btn-verify').prop('disabled', false).text('تأیید انتقال');
				if (!res.success) { msg(res.data.message, 'error'); return; }
				$('#awt-trx-ref').text(res.data.transaction_reference);
				var d = '';
				if (res.data.transfer_type === 'asset') {
					d = '<p>' + (res.data.quantity || '') + ' — ' + (res.data.product_name || '') + '</p>';
				} else {
					d = '<p>' + (parseFloat(res.data.amount) || 0).toLocaleString('fa-IR') + ' تومان</p>';
				}
				d += '<p>گیرنده: ' + (res.data.receiver_name || '') + '</p>';
				$('#awt-success-details').html(d);
				showStep(6);
				// ✅ تایمر باید اینجا باشد
		var sec = 15;
		$('#awt-countdown-num').text(sec);
		if (window.awtCountdownTimer) clearInterval(window.awtCountdownTimer);
		window.awtCountdownTimer = setInterval(function () {
			sec--;
			$('#awt-countdown-num').text(sec);
			if (sec <= 0) {
				clearInterval(window.awtCountdownTimer);
				location.reload();
			}
		}, 1000);

		$('#awt-btn-restart').off('click').on('click', function () {
			if (window.awtCountdownTimer) clearInterval(window.awtCountdownTimer);
			location.reload();
		});
			});
		});
       
		$('#awt-btn-resend').on('click', function () {
			post('awt_resend_otp', { transfer_id: state.transferId }, function (res) {
				msg(res.success ? res.data.message : (res.data.message || awtData.i18n.error), res.success ? 'success' : 'error');
			});
		});

		$('#awt-btn-back-1').on('click', function () { showStep(1); });
		$('#awt-btn-back-2a, #awt-btn-back-2b').on('click', function () { showStep(2); });
		$('#awt-btn-back-3').on('click', function () {
			showStep(state.type === 'asset' ? '3a' : '3b');
		});
	});
})(jQuery);
