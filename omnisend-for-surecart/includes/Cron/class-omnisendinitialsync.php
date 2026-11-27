<?php
/**
 * Omnisend initial sync
 *
 * @package OmnisendSureCartPlugin
 */

declare(strict_types=1);

namespace Omnisend\SureCartAddon\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Omnisend\SureCartAddon\Service\OmnisendInitialSyncService;

/**
 * Class OmnisendInitialSync
 */
class OmnisendInitialSync {
	public const OPTION_CATEGORY_CODE  = 'omnisend_sc_category_sync';
	public const OPTION_PRODUCT_CODE   = 'omnisend_sc_product_sync';
	public const OPTION_ORDER_CODE     = 'omnisend_sc_order_sync';
	public const OPTION_CUSTOMERS_CODE = 'omnisend_sc_customers_sync';

	private const SCHEDULE_CODE        = 'omni_send_core_every_minute';
	private const SCHEDULE_HOOK_CODE   = 'omnisend_sc_initial_sync';
	private const SYNC_STATUS_PROGRESS = 2;
	private const SYNC_STATUS_COMPLETE = 1;

	/**
	 * Schedule or unschedule sync event
	 *
	 * @param bool $plugin_active
	 */
	public function __construct( bool $plugin_active ) {
		if ( ! $plugin_active ) {
			wp_clear_scheduled_hook( self::SCHEDULE_HOOK_CODE );

			return;
		}

		add_action( self::SCHEDULE_HOOK_CODE, array( $this, 'execute' ) );

		if ( ! wp_next_scheduled( self::SCHEDULE_HOOK_CODE ) ) {
			wp_schedule_event( time(), self::SCHEDULE_CODE, self::SCHEDULE_HOOK_CODE );
		}
	}

	/**
	 * Execute all syncs
	 *
	 * @return void
	 */
	public static function execute(): void {
		$sync_service = new OmnisendInitialSyncService();

		if ( self::is_sync_allowed( self::OPTION_CATEGORY_CODE ) ) {
			update_option( self::OPTION_CATEGORY_CODE, self::SYNC_STATUS_PROGRESS );
			$sync_service->sync_all_categories();
			update_option( self::OPTION_CATEGORY_CODE, self::SYNC_STATUS_COMPLETE );
		}

		if ( self::is_sync_allowed( self::OPTION_PRODUCT_CODE ) ) {
			update_option( self::OPTION_PRODUCT_CODE, self::SYNC_STATUS_PROGRESS );
			$sync_service->sync_all_products();
			update_option( self::OPTION_PRODUCT_CODE, self::SYNC_STATUS_COMPLETE );
		}

		if ( self::is_sync_allowed( self::OPTION_ORDER_CODE ) ) {
			update_option( self::OPTION_ORDER_CODE, self::SYNC_STATUS_PROGRESS );
			$sync_service->sync_all_orders();
			update_option( self::OPTION_ORDER_CODE, self::SYNC_STATUS_COMPLETE );
		}

		if ( self::is_sync_allowed( self::OPTION_CUSTOMERS_CODE ) ) {
			update_option( self::OPTION_CUSTOMERS_CODE, self::SYNC_STATUS_PROGRESS );
			$sync_service->sync_all_customers();
			update_option( self::OPTION_CUSTOMERS_CODE, self::SYNC_STATUS_COMPLETE );
		}
	}

	/**
	 * Gets status message for all syncs
	 *
	 * @return array
	 */
	public static function get_sync_status(): array {
		return array(
			__( 'Categories', 'omnisend-for-surecart' ) => self::get_sync_status_message( (int) get_option( self::OPTION_CATEGORY_CODE ) ),
			__( 'Products', 'omnisend-for-surecart' )   => self::get_sync_status_message( (int) get_option( self::OPTION_PRODUCT_CODE ) ),
			__( 'Orders', 'omnisend-for-surecart' )     => self::get_sync_status_message( (int) get_option( self::OPTION_ORDER_CODE ) ),
			__( 'Customers', 'omnisend-for-surecart' )  => self::get_sync_status_message( (int) get_option( self::OPTION_CUSTOMERS_CODE ) ),
		);
	}

	/**
	 * Get sync status message
	 *
	 * @param int $sync_status
	 *
	 * @return string
	 */
	private static function get_sync_status_message( int $sync_status ): string {
		if ( $sync_status === self::SYNC_STATUS_PROGRESS ) {
			return __( 'In progress', 'omnisend-for-surecart' );
		} elseif ( $sync_status === self::SYNC_STATUS_COMPLETE ) {
			return __( 'Finished', 'omnisend-for-surecart' );
		}

		return __( 'Not started', 'omnisend-for-surecart' );
	}

	/**
	 * Checks if sync can be initialized
	 *
	 * @param string $sync_code
	 *
	 * @return bool
	 */
	private static function is_sync_allowed( string $sync_code ): bool {
		$sync_status = (int) get_option( $sync_code );

		if ( $sync_status === self::SYNC_STATUS_PROGRESS || $sync_status === self::SYNC_STATUS_COMPLETE ) {
			return false;
		}

		return true;
	}
}
