<?php
defined( 'ABSPATH' ) || exit;
$user_id = get_current_user_id();
$items   = AWT_History::get_items( $user_id, 200 ); // همه را می‌گیریم، نمایش محدود می‌شود

$icons = array(
	'receive'  => 'http://aloseke.com/wp-content/uploads/2026/09/card-recive-svgrepo-com.svg',
	'transfer' => 'http://aloseke.com/wp-content/uploads/2026/09/card-send-svgrepo-com.svg',
	'purchase' => 'http://aloseke.com/wp-content/uploads/2026/09/card-svgrepo-com.svg',
	'topup'    => 'http://aloseke.com/wp-content/uploads/2026/09/card-recive-svgrepo-com.svg',
	'sell'     => 'http://aloseke.com/wp-content/uploads/2026/09/card-transfer-svgrepo-com.svg',
	'withdraw' => 'http://aloseke.com/wp-content/uploads/2026/09/card-send-svgrepo-com.svg',
);
?>
<div class="awt-history zarnegar-card" dir="rtl" id="awt-history">
	<h2 class="awt-title">تاریخچه تراکنش‌ها</h2>

	<!-- فیلتر نوع -->
	<div class="awt-filter-bar">
		<button type="button" class="awt-filter active" data-filter="all">همه</button>
		<button type="button" class="awt-filter" data-filter="purchase">خرید</button>
		<button type="button" class="awt-filter" data-filter="sell">فروش</button>
		<button type="button" class="awt-filter" data-filter="transfer">انتقال</button>
		<button type="button" class="awt-filter" data-filter="receive">دریافت</button>
		<button type="button" class="awt-filter" data-filter="topup">افزایش موجودی</button>
		<button type="button" class="awt-filter" data-filter="withdraw">برداشت</button>
	</div>

	<?php if ( empty( $items ) ) : ?>
		<p class="awt-empty">هنوز تراکنشی ثبت نشده است.</p>
	<?php else : ?>
		<ul class="awt-history-list" id="awt-history-list">
			<?php foreach ( $items as $item ) :
				$icon = isset( $icons[ $item['icon'] ] ) ? $icons[ $item['icon'] ] : $icons['receive'];
				$box  = ( 'in' === $item['direction'] ) ? 'awt-amt-in' : 'awt-amt-out';

				// گروه فیلتر
				$filter_key = $item['icon'];
				if ( in_array( $item['type'], array( 'transfer_out', 'wallet_out' ), true ) ) {
					$filter_key = 'transfer';
				} elseif ( in_array( $item['type'], array( 'transfer_in', 'wallet_in' ), true ) ) {
					$filter_key = 'receive';
				} elseif ( in_array( $item['type'], array( 'withdraw_wallet', 'withdraw_asset' ), true ) ) {
					$filter_key = 'withdraw';
				}
				?>
				<li class="awt-history-item" data-filter="<?php echo esc_attr( $filter_key ); ?>" style="display:none;">
					<div class="awt-h-icon">
						<img src="<?php echo esc_url( $icon ); ?>" alt="" width="28" height="28" />
					</div>
					<div class="awt-h-main">
						<div class="awt-h-title"><?php echo esc_html( $item['title'] ); ?></div>
						<div class="awt-h-date"><?php echo esc_html( AWT_History::format_date( $item['time'] ) ); ?></div>
					</div>
					<div class="awt-h-trx"><?php echo esc_html( $item['trx'] ); ?></div>
					<div class="awt-h-amount <?php echo esc_attr( $box ); ?>">
						<?php echo esc_html( $item['amount'] ); ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="awt-more-wrap">
			<button type="button" class="awt-btn-more" id="awt-load-more">مشاهده بیشتر</button>
		</div>
	<?php endif; ?>
</div>

<style>
.awt-history { padding: 16px; color: #fff; width: 100%; background: #ffffff00;}
.awt-filter-bar {
	display: flex;
	gap: 8px;
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: none;
	padding-bottom: 12px;
	margin-bottom: 8px;
	    justify-content: center;
}
.awt-filter-bar::-webkit-scrollbar { display: none; }
.awt-filter {
	flex: 0 0 auto;
	padding: 8px 14px!important;
	border-radius: 15px;
	border: 1px solid rgba(255, 255, 255, 0.14);
	background: #ffffff05!important;
	color: #F8C15B!important;
	font-size: .82rem;
	font-weight: 600;
	cursor: pointer;
	white-space: nowrap;
}
.awt-filter.active {
	background: #ffffff05;
	border: 1px solid rgba(255, 255, 255, 0.14);
	color: #ffffff!important;
}
.awt-history-list { list-style: none; margin: 0; padding: 0; }
.awt-history-item {
	display: grid;
	grid-template-columns: 44px 1fr auto auto;
	gap: 12px;
	align-items: center;
	padding: 14px 8px;
	border-bottom: 1px solid rgba(255,255,255,.08);
}
.awt-h-icon {
	width: 44px; height: 44px;
	display: flex; align-items: center; justify-content: center;
	background: rgba(255,255,255,.05);
	border-radius: 12px;
}
.awt-h-title { font-weight: 700; color: #fff; margin-bottom: 4px; }
.awt-h-date { font-size: .82rem; color: rgba(255,255,255,.5); }
.awt-h-trx { font-size: .8rem; color: rgba(255,255,255,.45); direction: ltr; text-align: left; }
.awt-h-amount {
	min-width: 110px;
	text-align: center;
	padding: 8px 8px;
	border-radius:5px;
	font-weight: 700;
	font-size: .9rem;
}
.awt-amt-in  { background: #224f0b;  color: #ffffff; }
.awt-amt-out { background: #a61e2d; color: #ffffff; }
.awt-more-wrap { text-align: center; padding: 18px 0 6px; }
.awt-btn-more {
	padding: 10px 28px!important;
	border-radius: 12px;
	border: 1px solid rgba(255, 255, 255, 0.14)!important;
	background: #ffffff05!important;
	color: white!important;
	font-weight: 600;
	cursor: pointer!important;
}
.awt-btn-more:hover { background: rgba(248,193,91,.2); }
.awt-btn-more.is-hidden { display: none; }

@media (max-width: 640px) {
	.awt-history-item {
		grid-template-columns: 40px 1fr;
		grid-template-areas:
			"icon main"
			"icon amount"
			"trx trx";
	}
	.awt-h-icon { grid-area: icon; }
	.awt-h-main { grid-area: main; }
	.awt-h-amount { grid-area: amount; justify-self: start; }
	.awt-h-trx { grid-area: trx; margin-top: 4px; }
}
</style>

<script>
jQuery(function($) {
	var pageSize = 10;
	var shown = 0;
	var currentFilter = 'all';

	function visibleItems() {
		if (currentFilter === 'all') {
			return $('#awt-history-list .awt-history-item');
		}
		return $('#awt-history-list .awt-history-item[data-filter="' + currentFilter + '"]');
	}

	function render() {
		$('#awt-history-list .awt-history-item').hide();
		var $items = visibleItems();
		$items.slice(0, shown).show();
		if (shown >= $items.length) {
			$('#awt-load-more').addClass('is-hidden');
		} else {
			$('#awt-load-more').removeClass('is-hidden');
		}
	}

	// شروع: ۱۰ مورد
	shown = pageSize;
	render();

	$('#awt-load-more').on('click', function() {
		shown += pageSize;
		render();
	});

	$('.awt-filter').on('click', function() {
		$('.awt-filter').removeClass('active');
		$(this).addClass('active');
		currentFilter = $(this).data('filter');
		shown = pageSize;
		render();
	});
});
</script>