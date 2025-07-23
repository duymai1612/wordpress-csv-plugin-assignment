<?php
/**
 * Uninstall script for CSV Page Generator plugin.
 *
 * This file is executed when the plugin is deleted from the WordPress admin.
 * It removes all plugin data, options, and custom tables.
 *
 * @package ReasonDigital\CSVPageGenerator
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin constants if not already defined
if ( ! defined( 'CSV_PAGE_GENERATOR_TEXT_DOMAIN' ) ) {
	define( 'CSV_PAGE_GENERATOR_TEXT_DOMAIN', 'csv-page-generator' );
}

/**
 * Remove all plugin options from the database.
 */
function csv_page_generator_remove_options() {
	$options = array(
		'csv_page_generator_settings',
		'csv_page_generator_version',
		'csv_page_generator_db_version',
		'csv_page_generator_import_history',
		'csv_page_generator_file_settings',
		'csv_page_generator_security_settings',
		'csv_page_generator_performance_settings',
		'csv_page_generator_jwt_settings',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
		delete_site_option( $option ); // For multisite
	}
}

/**
 * Remove all plugin transients.
 */
function csv_page_generator_remove_transients() {
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
 * Remove custom database tables if they exist.
 */
function csv_page_generator_remove_custom_tables() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'csv_page_generator_imports';
	
	// Check if table exists before dropping
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
	
	if ( $table_exists ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}

/**
 * Remove uploaded CSV files and directories.
 */
function csv_page_generator_remove_upload_files() {
	$upload_dir = wp_upload_dir();
	$csv_upload_dir = $upload_dir['basedir'] . '/csv-imports';

	if ( is_dir( $csv_upload_dir ) ) {
		csv_page_generator_delete_directory( $csv_upload_dir );
	}
}

/**
 * Recursively delete a directory and its contents.
 *
 * @param string $dir Directory path to delete.
 * @return bool True on success, false on failure.
 */
function csv_page_generator_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );
	
	foreach ( $files as $file ) {
		$file_path = $dir . DIRECTORY_SEPARATOR . $file;
		
		if ( is_dir( $file_path ) ) {
			csv_page_generator_delete_directory( $file_path );
		} else {
			unlink( $file_path );
		}
	}

	return rmdir( $dir );
}

/**
 * Remove user meta data related to the plugin.
 */
function csv_page_generator_remove_user_meta() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			'csv_page_generator_%'
		)
	);
}

/**
 * Main uninstall function.
 */
function csv_page_generator_uninstall() {
	// Only proceed if user has proper capabilities
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// Remove all plugin data
	csv_page_generator_remove_options();
	csv_page_generator_remove_transients();
	csv_page_generator_remove_custom_tables();
	csv_page_generator_remove_upload_files();
	csv_page_generator_remove_user_meta();

	// Clear any cached data
	wp_cache_flush();
}

// Execute uninstall
csv_page_generator_uninstall();
