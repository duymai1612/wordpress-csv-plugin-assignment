<?php
/**
 * Plugin Name: CSV Page Generator
 * Plugin URI: https://github.com/reason-digital/wordpress-csv-plugin
 * Description: A professional WordPress plugin that allows CSV file uploads to automatically generate WordPress pages with enhanced security and performance features.
 * Version: 1.0.0
 * Author: Reason Digital Developer
 * Author URI: https://reasondigital.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: csv-page-generator
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: false
 *
 * @package ReasonDigital\CSVPageGenerator
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'CSV_PAGE_GENERATOR_VERSION', '1.0.0' );
define( 'CSV_PAGE_GENERATOR_PLUGIN_FILE', __FILE__ );
define( 'CSV_PAGE_GENERATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSV_PAGE_GENERATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CSV_PAGE_GENERATOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CSV_PAGE_GENERATOR_TEXT_DOMAIN', 'csv-page-generator' );

// Minimum PHP version check
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action( 'admin_notices', 'csv_page_generator_php_version_notice' );
	return;
}

// Minimum WordPress version check
if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
	add_action( 'admin_notices', 'csv_page_generator_wp_version_notice' );
	return;
}

/**
 * Display admin notice for insufficient PHP version.
 */
function csv_page_generator_php_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
				esc_html__( '%1$s requires PHP version %2$s or higher. You are running version %3$s.', 'csv-page-generator' ),
				'<strong>CSV Page Generator</strong>',
				'8.1',
				PHP_VERSION
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display admin notice for insufficient WordPress version.
 */
function csv_page_generator_wp_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Plugin name, 2: Required WordPress version, 3: Current WordPress version */
				esc_html__( '%1$s requires WordPress version %2$s or higher. You are running version %3$s.', 'csv-page-generator' ),
				'<strong>CSV Page Generator</strong>',
				'6.0',
				get_bloginfo( 'version' )
			);
			?>
		</p>
	</div>
	<?php
}

// Load Composer autoloader
if ( file_exists( CSV_PAGE_GENERATOR_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once CSV_PAGE_GENERATOR_PLUGIN_DIR . 'vendor/autoload.php';
}

// Load required classes manually (since we don't have Composer autoloader yet)
$required_classes = array(
	'src/Core/Plugin.php',
	'src/Core/Loader.php',
	'src/Core/Activator.php',
	'src/Core/Deactivator.php',
	'src/Utils/Logger.php',
	'src/Security/NonceManager.php',
	'src/Admin/AdminPage.php',
	'src/Admin/UploadHandler.php',
	'src/CSV/Parser.php',
	'src/CSV/Validator.php',
	'src/CSV/Processor.php',
	'src/Pages/Generator.php',
	'src/Utils/Database.php',
	'src/Security/FileValidator.php',
);

foreach ( $required_classes as $class_file ) {
	$file_path = CSV_PAGE_GENERATOR_PLUGIN_DIR . $class_file;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

/**
 * Initialize the plugin.
 */
function csv_page_generator_init() {
	if ( class_exists( 'ReasonDigital\CSVPageGenerator\Core\Plugin' ) ) {
		$plugin = ReasonDigital\CSVPageGenerator\Core\Plugin::get_instance();
		$plugin->init();
	}
}
add_action( 'plugins_loaded', 'csv_page_generator_init' );

/**
 * Plugin activation hook.
 */
function csv_page_generator_activate() {
	if ( class_exists( 'ReasonDigital\CSVPageGenerator\Core\Activator' ) ) {
		ReasonDigital\CSVPageGenerator\Core\Activator::activate();
	}
}
register_activation_hook( __FILE__, 'csv_page_generator_activate' );

/**
 * Plugin deactivation hook.
 */
function csv_page_generator_deactivate() {
	if ( class_exists( 'ReasonDigital\CSVPageGenerator\Core\Deactivator' ) ) {
		ReasonDigital\CSVPageGenerator\Core\Deactivator::deactivate();
	}
}
register_deactivation_hook( __FILE__, 'csv_page_generator_deactivate' );
