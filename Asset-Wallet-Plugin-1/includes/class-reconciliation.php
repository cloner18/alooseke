<?php
/**
 * Balance reconciliation from ledger
 *
 * @package Asset_Wallet
 */

defined( 'ABSPATH' ) || exit;

class Asset_Wallet_Reconciliation {

	/**
	 * Recalculate all balances for an account or all
	 *
	 * @param int $account_id Optional account ID.
	 * @return array Results.
	 */
	public static function recalculate( $account_id = 0 ) {
		global $wpdb;

		$balances_table = Asset_Wallet_Database::table( 'balances' );
		$results        = array();

		if ( $account_id ) {
			$balances = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$balances_table} WHERE account_id = %d",
					absint( $account_id )
				)
			);
		} else {
			$balances = $wpdb->get_results( "SELECT * FROM {$balances_table}" );
		}

		foreach ( $balances as $row ) {
			$ledger_qty = Asset_Wallet_Transactions::calculate_balance_from_ledger( $row->account_id, $row->asset_id );
			$current    = $row->quantity;
			$match      = Asset_Wallet_Helpers::decimal_cmp( $current, $ledger_qty ) === 0;

			$results[] = array(
				'account_id'    => $row->account_id,
				'asset_id'      => $row->asset_id,
				'current'       => $current,
				'ledger'        => $ledger_qty,
				'status'        => $match ? 'OK' : 'MISMATCH',
				'difference'    => Asset_Wallet_Helpers::decimal_sub( $ledger_qty, $current ),
			);

			if ( ! $match ) {
				// Optionally auto-fix
				$wpdb->update(
					$balances_table,
					array(
						'quantity'   => $ledger_qty,
						'updated_at' => current_time( 'mysql' ),
					),
					array(
						'account_id' => $row->account_id,
						'asset_id'   => $row->asset_id,
					),
					array( '%s', '%s' ),
					array( '%d', '%d' )
				);

				Asset_Wallet_Audit::log(
					'reconciliation_fix',
					'balance',
					$row->id,
					array( 'old' => $current ),
					array( 'new' => $ledger_qty )
				);
			}
		}

		return $results;
	}
}
