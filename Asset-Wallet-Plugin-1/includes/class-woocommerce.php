<?php
/**
 * WooCommerce product fields and cart/checkout integration
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_WooCommerce {

	/**
	 * Instance
	 *
	 * @var Asset_Wallet_WooCommerce
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Asset_Wallet_WooCommerce
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Product data tab
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );

		// Variation fields
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

		// Cart / Checkout: storage method choice
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'storage_method_field' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'checkout_storage_method_fields' ) );
add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_checkout_storage_method' ), 10, 4 );
// نمایش نحوه دریافت در جزئیات سفارش
add_action( 'woocommerce_after_order_itemmeta', array( $this, 'display_storage_method_in_order' ), 10, 3 );
add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_storage_method_meta' ), 10, 2 );

		// Hide shipping if all items are wallet storage (optional enhancement)
		add_filter( 'woocommerce_cart_needs_shipping', array( $this, 'maybe_disable_shipping' ) );
	}

	/**
	 * Add product fields in admin
	 */
	public function add_product_fields() {
		echo '<div class="options_group show_if_simple show_if_variable">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_asset_wallet_enabled',
				'label'       => __( 'قابلیت کیف دارایی', 'asset-wallet' ),
				'description' => __( 'این محصول قابلیت نگهداری در کیف دارایی دارد', 'asset-wallet' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_asset_type',
				'label'   => __( 'نوع دارایی', 'asset-wallet' ),
				'options' => array(
					'coin'  => __( 'سکه', 'asset-wallet' ),
					'gold'  => __( 'طلا', 'asset-wallet' ),
					'other' => __( 'سایر', 'asset-wallet' ),
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_asset_unit',
				'label'   => __( 'واحد', 'asset-wallet' ),
				'options' => array(
					'piece' => __( 'عدد', 'asset-wallet' ),
					'gram'  => __( 'گرم', 'asset-wallet' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_asset_weight',
				'label'             => __( 'وزن هر واحد (گرم)', 'asset-wallet' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.00000001',
					'min'  => '0',
				),
				'desc_tip'          => true,
				'description'       => __( 'برای سکه معمولاً ۱، برای طلای وزنی مقدار وزن واحد', 'asset-wallet' ),
			)
		);

		echo '</div>';
	}

	/**
	 * Save product fields
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_product_fields( $post_id ) {
		$enabled = isset( $_POST['_asset_wallet_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_asset_wallet_enabled', $enabled );

		if ( isset( $_POST['_asset_type'] ) ) {
			update_post_meta( $post_id, '_asset_type', sanitize_key( wp_unslash( $_POST['_asset_type'] ) ) );
		}
		if ( isset( $_POST['_asset_unit'] ) ) {
			update_post_meta( $post_id, '_asset_unit', sanitize_key( wp_unslash( $_POST['_asset_unit'] ) ) );
		}
		if ( isset( $_POST['_asset_weight'] ) ) {
			update_post_meta( $post_id, '_asset_weight', number_format( (float) $_POST['_asset_weight'], 8, '.', '' ) );
		}

		// Sync asset definition if enabled
		if ( 'yes' === $enabled ) {
			Asset_Wallet_Assets::ensure_for_product(
				$post_id,
				0,
				array(
					'type'   => get_post_meta( $post_id, '_asset_type', true ) ?: 'coin',
					'unit'   => get_post_meta( $post_id, '_asset_unit', true ) ?: 'piece',
					'weight' => get_post_meta( $post_id, '_asset_weight', true ) ?: '1',
				)
			);
		}
	}

	/**
	 * Variation fields
	 *
	 * @param int     $loop           Loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 */
	public function add_variation_fields( $loop, $variation_data, $variation ) {
		$variation_id = $variation->ID;

		echo '<div class="form-row form-row-full">';

		woocommerce_wp_checkbox(
			array(
				'id'            => "_asset_wallet_enabled_var{$loop}",
				'name'          => "_asset_wallet_enabled[{$loop}]",
				'label'         => __( 'قابلیت کیف دارایی', 'asset-wallet' ),
				'value'         => get_post_meta( $variation_id, '_asset_wallet_enabled', true ),
				'wrapper_class' => 'form-row-full',
			)
		);

		woocommerce_wp_select(
			array(
				'id'            => "_asset_type_var{$loop}",
				'name'          => "_asset_type[{$loop}]",
				'label'         => __( 'نوع دارایی', 'asset-wallet' ),
				'value'         => get_post_meta( $variation_id, '_asset_type', true ) ?: 'coin',
				'options'       => array(
					'coin'  => __( 'سکه', 'asset-wallet' ),
					'gold'  => __( 'طلا', 'asset-wallet' ),
					'other' => __( 'سایر', 'asset-wallet' ),
				),
				'wrapper_class' => 'form-row-first',
			)
		);

		woocommerce_wp_select(
			array(
				'id'            => "_asset_unit_var{$loop}",
				'name'          => "_asset_unit[{$loop}]",
				'label'         => __( 'واحد', 'asset-wallet' ),
				'value'         => get_post_meta( $variation_id, '_asset_unit', true ) ?: 'piece',
				'options'       => array(
					'piece' => __( 'عدد', 'asset-wallet' ),
					'gram'  => __( 'گرم', 'asset-wallet' ),
				),
				'wrapper_class' => 'form-row-last',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => "_asset_weight_var{$loop}",
				'name'              => "_asset_weight[{$loop}]",
				'label'             => __( 'وزن هر واحد', 'asset-wallet' ),
				'value'             => get_post_meta( $variation_id, '_asset_weight', true ) ?: '1',
				'type'              => 'number',
				'custom_attributes' => array( 'step' => '0.00000001', 'min' => '0' ),
				'wrapper_class'     => 'form-row-first',
			)
		);

		echo '</div>';
	}

	/**
	 * Save variation fields
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Loop.
	 */
	public function save_variation_fields( $variation_id, $loop ) {
		$enabled = isset( $_POST['_asset_wallet_enabled'][ $loop ] ) ? 'yes' : 'no';
		update_post_meta( $variation_id, '_asset_wallet_enabled', $enabled );

		if ( isset( $_POST['_asset_type'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_asset_type', sanitize_key( wp_unslash( $_POST['_asset_type'][ $loop ] ) ) );
		}
		if ( isset( $_POST['_asset_unit'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_asset_unit', sanitize_key( wp_unslash( $_POST['_asset_unit'][ $loop ] ) ) );
		}
		if ( isset( $_POST['_asset_weight'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_asset_weight', number_format( (float) $_POST['_asset_weight'][ $loop ], 8, '.', '' ) );
		}

		if ( 'yes' === $enabled ) {
			$parent_id = wp_get_post_parent_id( $variation_id );
			Asset_Wallet_Assets::ensure_for_product(
				$parent_id,
				$variation_id,
				array(
					'type'   => get_post_meta( $variation_id, '_asset_type', true ) ?: 'coin',
					'unit'   => get_post_meta( $variation_id, '_asset_unit', true ) ?: 'piece',
					'weight' => get_post_meta( $variation_id, '_asset_weight', true ) ?: '1',
				)
			);
		}
	}

	/**
	 * Storage method radio on product page
	 */
	public function storage_method_field() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$product_id = $product->get_id();
		$enabled    = get_post_meta( $product_id, '_asset_wallet_enabled', true );

		if ( 'yes' !== $enabled ) {
			return;
		}

		?>
		<div class="asset-wallet-storage-method" style="margin: 15px 0; padding: 12px; border: 1px solid #ddd; border-radius: 6px;">
			<p style="margin: 0 0 8px; font-weight: bold;"><?php esc_html_e( 'نحوه دریافت:', 'asset-wallet' ); ?></p>
			<label style="display: block; margin-bottom: 6px;">
				<input type="radio" name="asset_storage_method" value="physical" checked="checked" />
				<?php esc_html_e( 'دریافت فیزیکی', 'asset-wallet' ); ?>
			</label>
			<label style="display: block;">
				<input type="radio" name="asset_storage_method" value="wallet" />
				<?php esc_html_e( 'نگهداری در کیف دارایی', 'asset-wallet' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Add storage method to cart item data
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $_POST['asset_storage_method'] ) ) {
			$method = sanitize_key( wp_unslash( $_POST['asset_storage_method'] ) );
			if ( in_array( $method, array( 'wallet', 'physical' ), true ) ) {
				$cart_item_data['asset_storage_method'] = $method;
			}
		}
		return $cart_item_data;
	}

	/**
	 * Display in cart
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['asset_storage_method'] ) ) {
			$label = 'wallet' === $cart_item['asset_storage_method']
				? __( 'نگهداری در کیف دارایی', 'asset-wallet' )
				: __( 'دریافت فیزیکی', 'asset-wallet' );

			$item_data[] = array(
				'key'   => __( 'نحوه دریافت', 'asset-wallet' ),
				'value' => $label,
			);
		}
		return $item_data;
	}

	/**
	 * Save to order item meta
	 *
	 * @param WC_Order_Item_Product $item          Item.
	 * @param string                $cart_item_key Key.
	 * @param array                 $values        Values.
	 * @param WC_Order              $order         Order.
	 */
	public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['asset_storage_method'] ) ) {
			$item->add_meta_data( '_asset_storage_method', $values['asset_storage_method'], true );
		}
	}

	/**
	 * Disable shipping if all items are wallet storage
	 *
	 * @param bool $needs Needs shipping.
	 * @return bool
	 */
	public function maybe_disable_shipping( $needs ) {
		if ( ! WC()->cart ) {
			return $needs;
		}

		$all_wallet = true;
		$has_asset  = false;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['asset_storage_method'] ) ) {
				$has_asset = true;
				if ( 'wallet' !== $cart_item['asset_storage_method'] ) {
					$all_wallet = false;
					break;
				}
			} else {
				// Normal product that needs shipping
				$product = $cart_item['data'];
				if ( $product && $product->needs_shipping() ) {
					$all_wallet = false;
					break;
				}
			}
		}

		if ( $has_asset && $all_wallet ) {
			return false;
		}

		return $needs;
	}
	/**
 * نمایش انتخاب نحوه دریافت در صفحه Checkout
 */
public function checkout_storage_method_fields() {
	if ( ! WC()->cart ) {
		return;
	}

	$has_asset_item = false;

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$product = $cart_item['data'];
		if ( ! $product ) {
			continue;
		}

		$product_id   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;

		$enabled = get_post_meta( $product_id, '_asset_wallet_enabled', true );
		if ( $variation_id ) {
			$var_enabled = get_post_meta( $variation_id, '_asset_wallet_enabled', true );
			if ( '' !== $var_enabled ) {
				$enabled = $var_enabled;
			}
		}

		if ( 'yes' !== $enabled ) {
			continue;
		}

		$has_asset_item = true;
		$item_name = $product->get_name();
		?>
		<div class="asset-wallet-checkout-storage" style="margin: 15px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; background: #fafafa;">
			<p style="margin: 0 0 10px; font-weight: bold;">
				<?php echo esc_html( $item_name ); ?> — <?php esc_html_e( 'نحوه دریافت:', 'asset-wallet' ); ?>
			</p>
			<label style="display: block; margin-bottom: 8px;">
				<input type="radio" name="asset_storage_method[<?php echo esc_attr( $cart_item_key ); ?>]" value="physical" checked="checked" />
				<?php esc_html_e( 'دریافت فیزیکی', 'asset-wallet' ); ?>
			</label>
			<label style="display: block;">
				<input type="radio" name="asset_storage_method[<?php echo esc_attr( $cart_item_key ); ?>]" value="wallet" />
				<?php esc_html_e( 'نگهداری در کیف دارایی', 'asset-wallet' ); ?>
			</label>
		</div>
		<?php
	}

	if ( ! $has_asset_item ) {
		return;
	}
}

/**
 * ذخیره انتخاب کاربر از Checkout در Order Item
 */
public function save_checkout_storage_method( $item, $cart_item_key, $values, $order ) {
	if ( isset( $_POST['asset_storage_method'][ $cart_item_key ] ) ) {
		$method = sanitize_key( wp_unslash( $_POST['asset_storage_method'][ $cart_item_key ] ) );
		if ( in_array( $method, array( 'wallet', 'physical' ), true ) ) {
			$item->add_meta_data( '_asset_storage_method', $method, true );
		}
	} else {
		// پیش‌فرض: فیزیکی
		$item->add_meta_data( '_asset_storage_method', 'physical', true );
	}
}
/**
 * نمایش نحوه دریافت زیر هر آیتم در صفحه سفارش ادمین و فرانت
 */
public function display_storage_method_in_order( $item_id, $item, $product ) {
	$storage_method = $item->get_meta( '_asset_storage_method', true );

	if ( empty( $storage_method ) ) {
		return;
	}

	$label = ( 'wallet' === $storage_method )
		? __( 'نگهداری در کیف دارایی', 'asset-wallet' )
		: __( 'دریافت فیزیکی', 'asset-wallet' );

	$color = ( 'wallet' === $storage_method ) ? '#1a7a3a' : '#555';

	echo '<div class="asset-wallet-order-item-meta" style="margin-top:6px; font-size:13px;">';
	echo '<strong>' . esc_html__( 'نحوه دریافت:', 'asset-wallet' ) . '</strong> ';
	echo '<span style="color:' . esc_attr( $color ) . '; font-weight:600;">' . esc_html( $label ) . '</span>';
	echo '</div>';
}

/**
 * نمایش خوانا در متاهای فرمت‌شده سفارش (ایمیل، حساب کاربری و ...)
 */
public function format_storage_method_meta( $formatted_meta, $item ) {
	foreach ( $formatted_meta as $key => $meta ) {
		if ( '_asset_storage_method' === $meta->key ) {
			$formatted_meta[ $key ]->display_key = __( 'نحوه دریافت', 'asset-wallet' );
			$formatted_meta[ $key ]->display_value = ( 'wallet' === $meta->value )
				? __( 'نگهداری در کیف دارایی', 'asset-wallet' )
				: __( 'دریافت فیزیکی', 'asset-wallet' );
		}
	}
	return $formatted_meta;
}
}
