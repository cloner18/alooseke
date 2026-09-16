<?php
/**
 * Wallet display template - همسان با استایل زرنگار
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

$balance = woo_wallet()->wallet->get_wallet_balance($user_id) ?? null;
$user_id  = get_current_user_id();
$account  = Asset_Wallet_Accounts::get_or_create( $user_id );
$balances = $account ? Asset_Wallet_Balances::get_all_for_account( $account->id ) : array();
?>

<div class="zarnegar-dashboard-mini-wallet zarnegar-card asset-wallet-card" dir="rtl">
 <div class="zarnegar-card__top">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M4.8916 9.61431C4.8916 9.21193 5.21525 8.88574 5.61449 8.88574H9.46991C9.86916 8.88574 10.1928 9.21193 10.1928 9.61431C10.1928 10.0167 9.86916 10.3429 9.46991 10.3429H5.61449C5.21525 10.3429 4.8916 10.0167 4.8916 9.61431Z" fill="#F8C15B"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M21.1884 10.0038C21.1262 9.99995 21.0584 9.99998 20.9881 10L20.9706 10H18.2149C15.9435 10 14 11.7361 14 14C14 16.2639 15.9435 18 18.2149 18H20.9706L20.9881 18C21.0584 18 21.1262 18 21.1884 17.9962C22.111 17.9397 22.927 17.2386 22.9956 16.2594C23.0001 16.1952 23 16.126 23 16.0619L23 16.0444V11.9556L23 11.9381C23 11.874 23.0001 11.8048 22.9956 11.7406C22.927 10.7614 22.111 10.0603 21.1884 10.0038ZM17.9706 15.0667C18.5554 15.0667 19.0294 14.5891 19.0294 14C19.0294 13.4109 18.5554 12.9333 17.9706 12.9333C17.3858 12.9333 16.9118 13.4109 16.9118 14C16.9118 14.5891 17.3858 15.0667 17.9706 15.0667Z" fill="#F8C15B"></path> <path opacity="0.5" d="M21.1394 10.0015C21.1394 8.82091 21.0965 7.55447 20.3418 6.64658C20.2689 6.55894 20.1914 6.47384 20.1088 6.39124C19.3604 5.64288 18.4114 5.31076 17.239 5.15314C16.0998 4.99997 14.6442 4.99999 12.8064 5H10.6936C8.85583 4.99999 7.40019 4.99997 6.26098 5.15314C5.08856 5.31076 4.13961 5.64288 3.39124 6.39124C2.64288 7.13961 2.31076 8.08856 2.15314 9.26098C1.99997 10.4002 1.99999 11.8558 2 13.6936V13.8064C1.99999 15.6442 1.99997 17.0998 2.15314 18.239C2.31076 19.4114 2.64288 20.3604 3.39124 21.1088C4.13961 21.8571 5.08856 22.1892 6.26098 22.3469C7.40018 22.5 8.8558 22.5 10.6935 22.5H12.8064C14.6442 22.5 16.0998 22.5 17.239 22.3469C18.4114 22.1892 19.3604 21.8571 20.1088 21.1088C20.3133 20.9042 20.487 20.6844 20.6346 20.4486C21.0851 19.7291 21.1394 18.8473 21.1394 17.9985C21.0912 18 21.0404 18 20.9882 18L18.2149 18C15.9435 18 14 16.2639 14 14C14 11.7361 15.9435 10 18.2149 10L20.9881 10C21.0403 9.99999 21.0912 9.99997 21.1394 10.0015Z" fill="#F8C15B"></path> <path d="M10.1013 2.57211L7.99988 3.99253L6.2666 5.15237C7.40496 4.99997 8.8588 4.99999 10.6935 5H12.8063C14.6441 4.99998 16.0997 4.99997 17.2389 5.15314C17.4681 5.18394 17.6887 5.22142 17.9009 5.26737L15.9999 4L13.8874 2.57211C12.7588 1.8093 11.2299 1.8093 10.1013 2.57211Z" fill="#F8C15B"></path> </g></svg> کیف پول

            <a href="<?php echo esc_url(wc_get_endpoint_url('wallet')); ?>" class="zarnegar-card-top_icon">
                <i class="fa fa-arrow-circle-left"></i>
            </a>
        </div>
        <div class="d-flex justify-content-between align-items-start zarnegar-card card-gray flex-mobile-wide">
            <span class="d-flex align-items-center gap-2 fw-bold pt-3 text-white">
               <svg  width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M11.25 7.84748C10.3141 8.10339 9.75 8.82154 9.75 9.5C9.75 10.1785 10.3141 10.8966 11.25 11.1525V7.84748Z" fill="#F8C15B"></path> <path d="M12.75 12.8475V16.1525C13.6859 15.8966 14.25 15.1785 14.25 14.5C14.25 13.8215 13.6859 13.1034 12.75 12.8475Z" fill="#F8C15B"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12ZM12 5.25C12.4142 5.25 12.75 5.58579 12.75 6V6.31673C14.3804 6.60867 15.75 7.83361 15.75 9.5C15.75 9.91421 15.4142 10.25 15 10.25C14.5858 10.25 14.25 9.91421 14.25 9.5C14.25 8.82154 13.6859 8.10339 12.75 7.84748V11.3167C14.3804 11.6087 15.75 12.8336 15.75 14.5C15.75 16.1664 14.3804 17.3913 12.75 17.6833V18C12.75 18.4142 12.4142 18.75 12 18.75C11.5858 18.75 11.25 18.4142 11.25 18V17.6833C9.61957 17.3913 8.25 16.1664 8.25 14.5C8.25 14.0858 8.58579 13.75 9 13.75C9.41421 13.75 9.75 14.0858 9.75 14.5C9.75 15.1785 10.3141 15.8966 11.25 16.1525V12.6833C9.61957 12.3913 8.25 11.1664 8.25 9.5C8.25 7.83361 9.61957 6.60867 11.25 6.31673V6C11.25 5.58579 11.5858 5.25 12 5.25Z" fill="#F8C15B"></path> </g></svg> موجودی نقدی
            </span>

            <div class="text-left">
                <span class="zarnegar-price">
                    <?= $balance; ?>
                </span>
                <p class="m-0">
                    <a href="<?php echo esc_url(wc_get_endpoint_url('wallet')); ?>" class="text-secondary">+ افزایش موجودی</a>
                </p>
          
    </div>
	  </div>
	<!-- هدر کارت -->
	

	<?php if ( empty( $balances ) ) : ?>

		<div class="d-flex justify-content-between align-items-center zarnegar-card card-gray flex-mobile-wide p-3"style="padding-top:8px; padding-bottom:8px;">
			<span class="text-white fw-bold">هنوز دارایی در کیف شما ثبت نشده است.</span>
		</div>

	<?php else : ?>

		<?php foreach ( $balances as $b ) :
			$available = Asset_Wallet_Helpers::decimal_sub( $b->quantity, $b->reserved_quantity );
			$buy_price = Asset_Wallet_Helpers::get_buy_price( $b->product_id, $b->variation_id );
			$value     = $buy_price !== false ? (float) Asset_Wallet_Helpers::decimal_mul( $buy_price, $available ) : null;
			?>
			<div class="d-flex justify-content-between align-items-start zarnegar-card card-gray flex-mobile-wide mb-2"style="margin-top:8px; margin-bottom:8px;">
				
				<span class="d-flex align-items-center gap-2 fw-bold pt-3 text-white">
					<!-- آیکون طلا / سکه -->
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="#F8C15B"/>
						<path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z" fill="#F8C15B"/>
					</svg>
					<?php echo esc_html( $b->name ); ?>
				</span>

				<div class="text-left">
					<span class="m-0 text-secondary">
						<?php echo esc_html( Asset_Wallet_Helpers::format_quantity( $available, $b->unit ) ); ?>
					</span>

					<?php if ( null !== $value ) : ?>
						<p class="zarnegar-price text-white" style="font-size: 0.85rem;">
							ارزش: <?php echo wp_kses_post( Asset_Wallet_Helpers::format_price( $value ) ); ?>
						</p>
					<?php endif; ?>

					<?php if ( Asset_Wallet_Helpers::decimal_cmp( $b->reserved_quantity, '0' ) > 0 ) : ?>
						<p class="m-0 text-warning" style="font-size: 0.8rem;">
							رزرو: <?php echo esc_html( Asset_Wallet_Helpers::format_quantity( $b->reserved_quantity, $b->unit ) ); ?>
						</p>
					<?php endif; ?>
				</div>

			</div>
		<?php endforeach; ?>

	<?php endif; ?>

</div>