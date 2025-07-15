<?php
/**
 * Plugin Name: Omnisend for SureCart Add-On
 * Requires Plugins: surecart
 * Description: A SureCart add-on to sync Products/Categories/Orders/Contacts with Omnisend. In collaboration with SureCart plugin, it also enables better customer tracking
 * Version: 1.0.9
 * Author: Omnisend
 * Author URI: https://www.omnisend.com
 * Developer: Omnisend
 * Developer URI: https://www.omnisend.com
 * Text Domain: omnisend-for-surecart
 * ------------------------------------------------------------------------
 * Copyright 2025 Omnisend
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package OmnisendSureCartPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OMNISEND_SURECART_ADDON_NAME', 'Omnisend for SureCart Add-On' );
define( 'OMNISEND_SURECART_ADDON_VERSION', '1.0.9' );

spl_autoload_register( array( 'Omnisend_SureCartAddOn', 'autoloader' ) );
register_deactivation_hook( __FILE__, array( 'Omnisend_SureCartAddOn', 'deactivation_actions' ) );
register_uninstall_hook( __FILE__, array( 'Omnisend_SureCartAddOn', 'uninstall_actions' ) );
add_action( 'activated_plugin', array( 'Omnisend_SureCartAddOn', 'activation_actions' ) );
add_action( 'plugins_loaded', array( 'Omnisend_SureCartAddOn', 'check_plugin_requirements' ) );

use Omnisend\SureCartAddon\Actions\OmnisendAddOnAction;
use Omnisend\SureCartAddon\Cron\OmnisendInitialSync;
use Omnisend\SureCartAddon\Provider\OmnisendSettingsProvider;

/**
 * Class Omnisend_SureCartAddOn
 */
class Omnisend_SureCartAddOn {
	/**
	 * Register Actions
	 *
	 * @return void
	 */
	public static function register_actions(): void {
		new OmnisendAddOnAction();
	}

	/**
	 * Redirect to settings upon activation
	 *
	 * @param string $plugin
	 *
	 * @return void
	 */
	public static function activation_actions( string $plugin ): void {
		if ( $plugin !== 'omnisend-for-surecart-add-on/class-omnisend-surecartaddon.php' ) {
			return;
		}

		OmnisendSettingsProvider::set_default_options();

		if ( ! self::check_plugin_requirements() ) {
			return;
		}

		exit( esc_url( wp_safe_redirect( admin_url( 'options-general.php?page=omnisend-surecart' ) ) ) );
	}

	/**
	 * Deletes sync event
	 *
	 * @return void
	 */
	public static function deactivation_actions(): void {
		new OmnisendInitialSync( false );
	}

	/**
	 * Removes settings
	 *
	 * @return void
	 */
	public static function uninstall_actions(): void {
		$options = array(
			OmnisendInitialSync::OPTION_CATEGORY_CODE,
			OmnisendInitialSync::OPTION_PRODUCT_CODE,
			OmnisendInitialSync::OPTION_ORDER_CODE,
			OmnisendInitialSync::OPTION_CUSTOMERS_CODE,
			OmnisendSettingsProvider::STORE_CONNECTED_OPTION,
		);

		$options = array_merge( $options, OmnisendSettingsProvider::OPTION_LIST );

		foreach ( $options as $option_code ) {
			delete_option( $option_code );
		}
	}

	/**
	 * Autoloader function to load classes dynamically.
	 *
	 * @param string $class_name The name of the class to load.
	 *
	 * @return void
	 */
	public static function autoloader( string $class_name ): void {
		$namespace = 'Omnisend\SureCartAddon';

		if ( strpos( $class_name, $namespace ) !== 0 ) {
			return;
		}

		$class       = str_replace( $namespace . '\\', '', $class_name );
		$class_parts = explode( '\\', $class );
		$class_file  = 'class-' . strtolower( array_pop( $class_parts ) ) . '.php';

		$directory = plugin_dir_path( __FILE__ );
		$path      = $directory . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $class_parts ) . DIRECTORY_SEPARATOR . $class_file;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Checks plugin requirements.
	 *
	 * @return bool
	 */
	public static function check_plugin_requirements(): bool {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';

		if ( ! class_exists( '\Omnisend\SDK\V1\Omnisend' ) || ! Omnisend\SDK\V1\Omnisend::is_connected() ) {
			add_action( 'admin_notices', array( 'Omnisend_SureCartAddOn', 'omnisend_is_not_connected_notice' ) );

			return false;
		}

		$sc_token = get_option( '_transient_surecart_account' );

		if ( ! class_exists( '\SureCart\Rest\OrderRestServiceProvider' ) || ! is_object( $sc_token ) || ! isset( $sc_token['public_token'] ) ) {
			add_action( 'admin_notices', array( 'Omnisend_SureCartAddOn', 'surecart_is_not_connected_notice' ) );

			return false;
		}

		add_action( 'init', array( 'Omnisend_SureCartAddOn', 'register_actions' ), 10 );

		return true;
	}

	/**
	 * Display a notice if Omnisend is not connected.
	 *
	 * @return void
	 */
	public static function omnisend_is_not_connected_notice(): void {
		echo '<div class="error"><p>' . esc_html__( 'Your Omnisend is not configured properly. Please configure it by connecting to your Omnisend account.', 'omnisend-for-surecart' ) . '<a href="https://wordpress.org/plugins/omnisend/">' . esc_html__( 'Omnisend plugin.', 'omnisend-for-surecart' ) . '</a></p></div>';
	}

	/**
	 * Display a notice if SureCart is not connected.
	 *
	 * @return void
	 */
	public static function surecart_is_not_connected_notice(): void {
		echo '<div class="error"><p>' . esc_html__( 'Your SureCart is not configured properly. Please configure it by connecting to your SureCart account.', 'omnisend-for-surecart' ) . '<a href="https://surecart.com/docs/add-surecart-api/">' . esc_html__( 'SureCart plugin.', 'omnisend-for-surecart' ) . '</a></p></div>';
	}
}
