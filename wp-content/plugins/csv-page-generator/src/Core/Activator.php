<?php
/**
 * Plugin Activator Class
 *
 * @package ReasonDigital\CSVPageGenerator\Core
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Core;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Performs all necessary setup when the plugin is activated:
	 * - Creates database tables
	 * - Sets default options
	 * - Creates upload directories
	 * - Sets up capabilities
	 * - Flushes rewrite rules
	 */
	public static function activate() {
		// Check WordPress and PHP version requirements
		self::check_requirements();

		// Create database tables
		self::create_database_tables();

		// Set default plugin options
		self::set_default_options();

		// Create upload directories
		self::create_upload_directories();

		// Set up user capabilities
		self::setup_capabilities();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation flag
		update_option( 'csv_page_generator_activated', true );

		// Log activation
		if ( class_exists( 'ReasonDigital\CSVPageGenerator\Utils\Logger' ) ) {
			$logger = new \ReasonDigital\CSVPageGenerator\Utils\Logger();
			$logger->info( 'CSV Page Generator plugin activated successfully.' );
		}
	}

	/**
	 * Check if WordPress and PHP meet minimum requirements.
	 *
	 * @throws \Exception If requirements are not met.
	 */
	private static function check_requirements() {
		global $wp_version;

		// Check WordPress version
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'CSV Page Generator requires WordPress 6.0 or higher.', 'csv-page-generator' ),
				esc_html__( 'Plugin Activation Error', 'csv-page-generator' ),
				array( 'back_link' => true )
			);
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'CSV Page Generator requires PHP 8.1 or higher.', 'csv-page-generator' ),
				esc_html__( 'Plugin Activation Error', 'csv-page-generator' ),
				array( 'back_link' => true )
			);
		}

		// Check if required PHP extensions are available
		$required_extensions = array( 'mbstring', 'json' );
		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die(
					sprintf(
						/* translators: %s: PHP extension name */
						esc_html__( 'CSV Page Generator requires the %s PHP extension.', 'csv-page-generator' ),
						$extension
					),
					esc_html__( 'Plugin Activation Error', 'csv-page-generator' ),
					array( 'back_link' => true )
				);
			}
		}
	}

	/**
	 * Create custom database tables for the plugin.
	 */
	private static function create_database_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'csv_page_generator_imports';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			filename varchar(255) NOT NULL,
			original_filename varchar(255) NOT NULL,
			file_size bigint(20) NOT NULL,
			total_rows int(11) NOT NULL,
			processed_rows int(11) DEFAULT 0,
			successful_rows int(11) DEFAULT 0,
			failed_rows int(11) DEFAULT 0,
			status varchar(20) DEFAULT 'pending',
			error_log longtext,
			created_pages longtext,
			started_at datetime DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY started_at (started_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Update database version
		update_option( 'csv_page_generator_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$default_settings = array(
			'max_file_size'       => 10485760, // 10MB
			'max_rows'            => 10000,
			'batch_size'          => 100,
			'allowed_file_types'  => array( 'csv' ),
			'default_post_status' => 'draft',
			'default_post_author' => get_current_user_id(),
			'enable_notifications' => true,
			'notification_email'  => get_option( 'admin_email' ),
			'enable_logging'      => true,
			'log_level'           => 'info',
			'enable_cleanup'      => true,
			'cleanup_days'        => 30,
		);

		// Only set if not already exists
		if ( ! get_option( 'csv_page_generator_settings' ) ) {
			update_option( 'csv_page_generator_settings', $default_settings );
		}

		// Set plugin version
		update_option( 'csv_page_generator_version', CSV_PAGE_GENERATOR_VERSION );
	}

	/**
	 * Create necessary upload directories.
	 */
	private static function create_upload_directories() {
		$upload_dir = wp_upload_dir();
		$csv_upload_dir = $upload_dir['basedir'] . '/csv-imports';

		// Create main upload directory
		if ( ! file_exists( $csv_upload_dir ) ) {
			wp_mkdir_p( $csv_upload_dir );
		}

		// Create subdirectories
		$subdirs = array( 'temp', 'processed', 'logs' );
		foreach ( $subdirs as $subdir ) {
			$dir_path = $csv_upload_dir . '/' . $subdir;
			if ( ! file_exists( $dir_path ) ) {
				wp_mkdir_p( $dir_path );
			}
		}

		// Create .htaccess file for security
		$htaccess_file = $csv_upload_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# Deny direct access to uploaded files\n";
			$htaccess_content .= "Order deny,allow\n";
			$htaccess_content .= "Deny from all\n";
			$htaccess_content .= "<Files ~ \"\\.(csv)$\">\n";
			$htaccess_content .= "    Order allow,deny\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</Files>\n";

			file_put_contents( $htaccess_file, $htaccess_content );
		}

		// Create index.php files for security
		$index_content = "<?php\n// Silence is golden.\n";
		$directories = array( $csv_upload_dir, $csv_upload_dir . '/temp', $csv_upload_dir . '/processed', $csv_upload_dir . '/logs' );
		
		foreach ( $directories as $dir ) {
			$index_file = $dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, $index_content );
			}
		}
	}

	/**
	 * Set up user capabilities for the plugin.
	 */
	private static function setup_capabilities() {
		// Get administrator role
		$admin_role = get_role( 'administrator' );
		
		if ( $admin_role ) {
			// Add custom capabilities
			$admin_role->add_cap( 'csv_page_generator_upload' );
			$admin_role->add_cap( 'csv_page_generator_manage' );
			$admin_role->add_cap( 'csv_page_generator_settings' );
			$admin_role->add_cap( 'csv_page_generator_view_logs' );
		}

		// Get editor role
		$editor_role = get_role( 'editor' );
		
		if ( $editor_role ) {
			// Add limited capabilities for editors
			$editor_role->add_cap( 'csv_page_generator_upload' );
			$editor_role->add_cap( 'csv_page_generator_manage' );
		}
	}

	/**
	 * Schedule cleanup cron job.
	 */
	private static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'csv_page_generator_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'csv_page_generator_cleanup' );
		}
	}

	/**
	 * Create sample CSV file for testing.
	 */
	private static function create_sample_csv() {
		$upload_dir = wp_upload_dir();
		$sample_file = $upload_dir['basedir'] . '/csv-imports/sample.csv';

		if ( ! file_exists( $sample_file ) ) {
			$sample_content = "Title,Description\n";
			$sample_content .= "\"Sample Page 1\",\"This is a sample page description for testing the CSV import functionality.\"\n";
			$sample_content .= "\"Sample Page 2\",\"Another sample page with different content to demonstrate the import process.\"\n";
			$sample_content .= "\"Sample Page 3\",\"A third sample page to show how multiple pages can be created from CSV data.\"\n";

			file_put_contents( $sample_file, $sample_content );
		}
	}
}
