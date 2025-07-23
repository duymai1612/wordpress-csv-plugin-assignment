<?php
/**
 * Plugin Deactivator Class
 *
 * @package ReasonDigital\CSVPageGenerator\Core
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Core;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Deactivator {

	/**
	 * Plugin deactivation handler.
	 *
	 * Performs cleanup when the plugin is deactivated:
	 * - Clears scheduled events
	 * - Cleans up temporary files
	 * - Removes transients
	 * - Flushes rewrite rules
	 * 
	 * Note: This does NOT remove user data, settings, or database tables.
	 * That is handled by the uninstall.php file when the plugin is deleted.
	 */
	public static function deactivate() {
		// Clear scheduled events
		self::clear_scheduled_events();

		// Clean up temporary files
		self::cleanup_temporary_files();

		// Remove transients
		self::remove_transients();

		// Remove user capabilities (optional - commented out to preserve permissions)
		// self::remove_capabilities();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Remove activation flag
		delete_option( 'csv_page_generator_activated' );

		// Log deactivation
		if ( class_exists( 'ReasonDigital\CSVPageGenerator\Utils\Logger' ) ) {
			$logger = new \ReasonDigital\CSVPageGenerator\Utils\Logger();
			$logger->info( 'CSV Page Generator plugin deactivated.' );
		}
	}

	/**
	 * Clear all scheduled events related to the plugin.
	 */
	private static function clear_scheduled_events() {
		// Clear cleanup cron job
		$timestamp = wp_next_scheduled( 'csv_page_generator_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'csv_page_generator_cleanup' );
		}

		// Clear any import processing cron jobs
		$import_events = wp_get_scheduled_events();
		foreach ( $import_events as $timestamp => $events ) {
			foreach ( $events as $hook => $event_data ) {
				if ( strpos( $hook, 'csv_page_generator_' ) === 0 ) {
					wp_unschedule_event( $timestamp, $hook );
				}
			}
		}
	}

	/**
	 * Clean up temporary files created during imports.
	 */
	private static function cleanup_temporary_files() {
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/csv-imports/temp';

		if ( is_dir( $temp_dir ) ) {
			$files = glob( $temp_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// Only remove files older than 1 hour to avoid interfering with active imports
					if ( filemtime( $file ) < ( time() - 3600 ) ) {
						unlink( $file );
					}
				}
			}
		}
	}

	/**
	 * Remove all plugin-related transients.
	 */
	private static function remove_transients() {
		global $wpdb;

		// Remove all transients with our prefix
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_csv_page_generator_%',
				'_transient_timeout_csv_page_generator_%'
			)
		);

		// For multisite
		if ( is_multisite() ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
					'_site_transient_csv_page_generator_%',
					'_site_transient_timeout_csv_page_generator_%'
				)
			);
		}
	}

	/**
	 * Remove user capabilities (optional - currently commented out).
	 * 
	 * This method is available but not called by default to preserve
	 * user permissions in case the plugin is reactivated.
	 */
	private static function remove_capabilities() {
		// Get all roles
		$roles = wp_roles();
		
		if ( ! $roles ) {
			return;
		}

		$capabilities = array(
			'csv_page_generator_upload',
			'csv_page_generator_manage',
			'csv_page_generator_settings',
			'csv_page_generator_view_logs',
		);

		// Remove capabilities from all roles
		foreach ( $roles->roles as $role_name => $role_info ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $capabilities as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Cancel any running import processes.
	 */
	private static function cancel_running_imports() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'csv_page_generator_imports';
		
		// Check if table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		
		if ( $table_exists ) {
			// Update any running imports to cancelled status
			$wpdb->update(
				$table_name,
				array(
					'status'       => 'cancelled',
					'completed_at' => current_time( 'mysql' ),
				),
				array( 'status' => 'processing' ),
				array( '%s', '%s' ),
				array( '%s' )
			);
		}
	}

	/**
	 * Clear any cached data.
	 */
	private static function clear_cache() {
		// Clear object cache
		wp_cache_flush();

		// Clear any plugin-specific cache
		if ( function_exists( 'wp_cache_delete_group' ) ) {
			wp_cache_delete_group( 'csv_page_generator' );
		}
	}

	/**
	 * Send notification about deactivation (if enabled).
	 */
	private static function send_deactivation_notification() {
		$settings = get_option( 'csv_page_generator_settings', array() );
		
		if ( ! empty( $settings['enable_notifications'] ) && ! empty( $settings['notification_email'] ) ) {
			$subject = sprintf(
				/* translators: %s: Site name */
				__( 'CSV Page Generator Plugin Deactivated - %s', 'csv-page-generator' ),
				get_bloginfo( 'name' )
			);

			$message = sprintf(
				/* translators: 1: Site name, 2: Site URL, 3: Current time */
				__( 'The CSV Page Generator plugin has been deactivated on %1$s (%2$s) at %3$s.', 'csv-page-generator' ),
				get_bloginfo( 'name' ),
				home_url(),
				current_time( 'mysql' )
			);

			wp_mail( $settings['notification_email'], $subject, $message );
		}
	}

	/**
	 * Log deactivation details for debugging.
	 */
	private static function log_deactivation_details() {
		$details = array(
			'timestamp'    => current_time( 'mysql' ),
			'user_id'      => get_current_user_id(),
			'user_login'   => wp_get_current_user()->user_login,
			'site_url'     => home_url(),
			'plugin_version' => defined( 'CSV_PAGE_GENERATOR_VERSION' ) ? CSV_PAGE_GENERATOR_VERSION : 'unknown',
		);

		// Store deactivation details for potential debugging
		update_option( 'csv_page_generator_last_deactivation', $details );
	}
}
