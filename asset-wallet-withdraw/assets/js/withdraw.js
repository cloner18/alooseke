(function ($) {
	'use strict';

	function msg(t, type) {
		$('#aww-msg').html('<div class="aww-alert aww-alert-' + (type || 'info') + '">' + t + '</div>');
	}

	function post(action, data, cb) {
		data = data || {};
		data.action = action;
		data.nonce = awwData.nonce;
		$.post(awwData.ajax_url, data).done(cb).fail(function () { msg('خطا در ارتباط', 'error'); });
	}

	$(function () {
		if (!$('#aww-form').length) return;

		$('input[name="aww_type"]').on('change', function () {
			var t = $(this).val();
			$('.aww-panel').hide();
			$('.aww-panel[data-type="' + t + '"]').show();
			if (t === 'asset') {
				post('aww_get_balances', {}, function (res) {
					if (!res.success) { msg(res.data.message, 'error'); return; }
					var html = '';
					(res.data.balances || []).forEach(function (b) {
						html += '<label class="aww-asset-item"><input type="radio" name="aww_asset" value="' + b.asset_id + '" data-avail="' + b.available + '" data-unit="' + b.unit + '"> ' + b.name + ' <span>(' + parseFloat(b.available).toLocaleString('fa-IR') + ')</span></label>';
					});
					$('#aww-assets').html(html || '<p>موجودی ندارید.</p>');
				});
			}
		});

		$(document).on('change', 'input[name="aww_asset"]', function () {
			var avail = parseFloat($(this).data('avail')) || 0;
			var unit = $(this).data('unit');
			$('#aww-asset-avail').text(avail.toLocaleString('fa-IR') + (unit === 'gram' ? ' گرم' : ' عدد'));
			$('#aww-qty-wrap').show();
			$('#aww-submit-asset').prop('disabled', false);
		});

		$('#aww-submit-wallet').on('click', function () {
			var amount = parseFloat($('#aww-amount').val()) || 0;
			var card = $('input[name="aww_card"]:checked').val();
			if (amount <= 0) { msg('مبلغ را وارد کنید.', 'error'); return; }
			if (!card) { msg('کارت را انتخاب کنید.', 'error'); return; }
			$(this).prop('disabled', true);
			post('aww_create', { type: 'wallet', amount: amount, card_id: card }, function (res) {
				$('#aww-submit-wallet').prop('disabled', false);
				if (!res.success) { msg(res.data.message, 'error'); return; }
				msg(res.data.message + ' — پیگیری: ' + res.data.tracking_code, 'success');
			});
		});

		$('#aww-submit-asset').on('click', function () {
			var asset = $('input[name="aww_asset"]:checked').val();
			var qty = parseFloat($('#aww-qty').val()) || 0;
			if (!asset || qty <= 0) { msg('دارایی و مقدار را مشخص کنید.', 'error'); return; }
			$(this).prop('disabled', true);
			post('aww_create', { type: 'asset', asset_id: asset, quantity: qty }, function (res) {
				$('#aww-submit-asset').prop('disabled', false);
				if (!res.success) { msg(res.data.message, 'error'); return; }
				msg(res.data.message + ' — پیگیری: ' + res.data.tracking_code, 'success');
			});
		});
	});
})(jQuery);
