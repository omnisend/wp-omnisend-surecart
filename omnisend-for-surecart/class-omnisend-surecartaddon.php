<?php
/**
 * Plugin Name: Omnisend for SureCart Add-On
 * Requires Plugins: surecart
 * Description: A SureCart add-on to sync Products/Categories/Orders/Contacts with Omnisend. In collaboration with SureCart plugin, it also enables better customer tracking
 * Version: 1.1.0
 * Requires PHP: 7.4
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
define( 'OMNISEND_SURECART_ADDON_VERSION', '1.1.0' );

spl_autoload_register( array( 'Omnisend_SureCartAddOn', 'autoloader' ) );
register_deactivation_hook( __FILE__, array( 'Omnisend_SureCartAddOn', 'deactivation_actions' ) );
register_uninstall_hook( __FILE__, array( 'Omnisend_SureCartAddOn', 'uninstall_actions' ) );
add_action( 'activated_plugin', array( 'Omnisend_SureCartAddOn', 'activation_actions' ) );
add_action( 'plugins_loaded', array( 'Omnisend_SureCartAddOn', 'check_plugin_requirements' ) );
add_action( 'admin_init', array( 'Omnisend_SureCartAddOn', 'add_privacy_policy_content' ) );

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
	 * Suggests privacy policy text for site administrators, as recommended by the
	 * WordPress privacy policy content API for plugins that collect user data.
	 *
	 * @return void
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content =
			'<p>' . esc_html__( 'The Omnisend for SureCart Add-On syncs your SureCart store data to Omnisend for email and SMS marketing purposes. Depending on your setup, this may include customer email addresses, first and last names, phone numbers, order and purchase history, the products and categories involved, your consent choices, and other contact information.', 'omnisend-for-surecart' ) . '</p>' .
			'<p>' . esc_html__( 'This data is transmitted to and stored by Omnisend, a third-party service, and is retained there according to Omnisend’s data retention practices for as long as your contact record exists. Existing customers, orders, products and categories may also be synced to Omnisend in bulk when the add-on is activated.', 'omnisend-for-surecart' ) . '</p>' .
			'<p>' . esc_html__( 'When the accompanying Omnisend plugin is active, a tracking snippet may also set cookies in visitors’ browsers to identify contacts and track their activity on the site.', 'omnisend-for-surecart' ) . '</p>' .
			'<p>' . sprintf(
				/* translators: 1: Omnisend Privacy Policy URL, 2: Omnisend Terms of Use URL */
				esc_html__( 'You have the right to request access to, export of, or deletion of your personal data. For details on how Omnisend processes personal data and how to exercise these rights, see Omnisend’s Privacy Policy at %1$s and Terms of Use at %2$s.', 'omnisend-for-surecart' ),
				'<a href="https://www.omnisend.com/privacy/" target="_blank">https://www.omnisend.com/privacy/</a>',
				'<a href="https://www.omnisend.com/terms" target="_blank">https://www.omnisend.com/terms</a>'
			) . '</p>';

		wp_add_privacy_policy_content( OMNISEND_SURECART_ADDON_NAME, wp_kses_post( $content ) );
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

		$sc_token = get_option( 'sc_api_token' );

		if ( ! class_exists( '\SureCart\Rest\OrderRestServiceProvider' ) || $sc_token === false ) {
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
