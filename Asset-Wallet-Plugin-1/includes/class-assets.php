<?php
/**
 * Assets definition management
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Assets {

	/**
	 * Get asset by ID
	 *
	 * @param int $asset_id Asset ID.
	 * @return object|null
	 */
	public static function get( $asset_id ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'assets' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				absint( $asset_id )
			)
		);
	}

	/**
	 * Get asset by product / variation
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return object|null
	 */
	public static function get_by_product( $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'assets' );

		if ( $variation_id ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE variation_id = %d AND status = 'active' LIMIT 1",
					absint( $variation_id )
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND (variation_id IS NULL OR variation_id = 0) AND status = 'active' LIMIT 1",
				absint( $product_id )
			)
		);
	}

	/**
	 * Get all active assets
	 *
	 * @return array
	 */
	public static function get_all_active() {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'assets' );

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'active' ORDER BY name ASC"
		);
	}

	/**
	 * Create or update asset from product data
	 *
	 * @param array $data Data.
	 * @return int|false Asset ID.
	 */
	public static function save( $data ) {
		global $wpdb;
		$table = Asset_Wallet_Database::table( 'assets' );
		$now   = current_time( 'mysql' );

		$defaults = array(
			'name'         => '',
			'slug'         => '',
			'type'         => 'coin',
			'unit'         => 'piece',
			'weight'       => '1.00000000',
			'product_id'   => null,
			'variation_id' => null,
			'status'       => 'active',
			'meta'         => null,
		);

		$data = wp_parse_args( $data, $defaults );

		if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		$data['name']   = sanitize_text_field( $data['name'] );
		$data['slug']   = sanitize_title( $data['slug'] );
		$data['type']   = sanitize_key( $data['type'] );
		$data['unit']   = sanitize_key( $data['unit'] );
		$data['weight'] = number_format( (float) $data['weight'], 8, '.', '' );
		$data['status'] = sanitize_key( $data['status'] );

		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			$data['updated_at'] = $now;

			$wpdb->update(
				$table,
				$data,
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return $id;
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$inserted = $wpdb->insert(
			$table,
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Ensure asset exists for a product (auto-create if needed)
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param array  $extra        Extra data (name, type, unit, weight).
	 * @return object|false
	 */
	public static function ensure_for_product( $product_id, $variation_id = 0, $extra = array() ) {
		$existing = self::get_by_product( $product_id, $variation_id );
		if ( $existing ) {
			return $existing;
		}

		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product ) {
			return false;
		}

		$name = $product->get_name();
		if ( $variation_id && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product_id );
			if ( $parent ) {
				$name = $parent->get_name() . ' - ' . $product->get_attribute_summary();
			}
		}

		$data = array(
			'name'         => ! empty( $extra['name'] ) ? $extra['name'] : $name,
			'slug'         => sanitize_title( $name ) . ( $variation_id ? '-' . $variation_id : '' ),
			'type'         => ! empty( $extra['type'] ) ? $extra['type'] : 'coin',
			'unit'         => ! empty( $extra['unit'] ) ? $extra['unit'] : 'piece',
			'weight'       => ! empty( $extra['weight'] ) ? $extra['weight'] : '1.00000000',
			'product_id'   => absint( $product_id ),
			'variation_id' => $variation_id ? absint( $variation_id ) : null,
			'status'       => 'active',
		);

		$id = self::save( $data );
		return $id ? self::get( $id ) : false;
	}
}
