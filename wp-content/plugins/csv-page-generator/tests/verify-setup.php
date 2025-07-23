<?php
/**
 * Setup Verification Script
 *
 * This script verifies that the CSV Page Generator plugin is properly
 * installed, configured, and functional.
 *
 * @package ReasonDigital\CSVPageGenerator\Tests
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verify plugin setup and functionality.
 */
function verify_csv_plugin_setup() {
	echo "=== CSV Page Generator Plugin - Setup Verification ===\n\n";
	
	$all_tests_passed = true;
	
	// Test 1: Plugin Activation
	echo "1. Testing Plugin Activation...\n";
	if ( is_plugin_active( 'csv-page-generator/csv-page-generator.php' ) ) {
		echo "   ✅ Plugin is active\n";
	} else {
		echo "   ❌ Plugin is not active\n";
		$all_tests_passed = false;
	}
	
	// Test 2: Database Table
	echo "\n2. Testing Database Table...\n";
	global $wpdb;
	$table_name = $wpdb->prefix . 'csv_page_generator_imports';
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
	
	if ( $table_exists ) {
		echo "   ✅ Database table exists: {$table_name}\n";
		
		// Check table structure
		$columns = $wpdb->get_results( "DESCRIBE {$table_name}" );
		$expected_columns = array( 'id', 'user_id', 'filename', 'status', 'total_rows' );
		$found_columns = array_column( $columns, 'Field' );
		
		$missing_columns = array_diff( $expected_columns, $found_columns );
		if ( empty( $missing_columns ) ) {
			echo "   ✅ Table structure is correct\n";
		} else {
			echo "   ❌ Missing columns: " . implode( ', ', $missing_columns ) . "\n";
			$all_tests_passed = false;
		}
	} else {
		echo "   ❌ Database table does not exist\n";
		$all_tests_passed = false;
	}
	
	// Test 3: Plugin Options
	echo "\n3. Testing Plugin Options...\n";
	$settings = get_option( 'csv_page_generator_settings' );
	if ( $settings ) {
		echo "   ✅ Plugin settings exist\n";
		echo "   - Max file size: " . size_format( $settings['max_file_size'] ?? 0 ) . "\n";
		echo "   - Max rows: " . ( $settings['max_rows'] ?? 0 ) . "\n";
	} else {
		echo "   ❌ Plugin settings not found\n";
		$all_tests_passed = false;
	}
	
	// Test 4: Upload Directory
	echo "\n4. Testing Upload Directory...\n";
	$upload_dir = wp_upload_dir();
	$csv_upload_dir = $upload_dir['basedir'] . '/csv-imports';
	
	if ( file_exists( $csv_upload_dir ) && is_writable( $csv_upload_dir ) ) {
		echo "   ✅ Upload directory exists and is writable: {$csv_upload_dir}\n";
	} else {
		echo "   ❌ Upload directory issue: {$csv_upload_dir}\n";
		if ( ! file_exists( $csv_upload_dir ) ) {
			echo "   - Directory does not exist\n";
		}
		if ( ! is_writable( $csv_upload_dir ) ) {
			echo "   - Directory is not writable\n";
		}
		$all_tests_passed = false;
	}
	
	// Test 5: Sample File
	echo "\n5. Testing Sample File...\n";
	$sample_file = plugin_dir_path( dirname( __FILE__ ) ) . 'samples/sample-data.csv';
	if ( file_exists( $sample_file ) && is_readable( $sample_file ) ) {
		echo "   ✅ Sample file exists and is readable\n";
		echo "   - File size: " . size_format( filesize( $sample_file ) ) . "\n";
		
		// Check file content
		$handle = fopen( $sample_file, 'r' );
		$header = fgetcsv( $handle );
		fclose( $handle );
		
		if ( $header && count( $header ) >= 2 ) {
			echo "   ✅ Sample file has valid CSV structure\n";
			echo "   - Headers: " . implode( ', ', $header ) . "\n";
		} else {
			echo "   ❌ Sample file has invalid CSV structure\n";
			$all_tests_passed = false;
		}
	} else {
		echo "   ❌ Sample file not found or not readable: {$sample_file}\n";
		$all_tests_passed = false;
	}
	
	// Test 6: Class Loading
	echo "\n6. Testing Class Loading...\n";
	$required_classes = array(
		'ReasonDigital\CSVPageGenerator\CSV\Parser',
		'ReasonDigital\CSVPageGenerator\CSV\Validator',
		'ReasonDigital\CSVPageGenerator\CSV\Processor',
		'ReasonDigital\CSVPageGenerator\Pages\Generator',
		'ReasonDigital\CSVPageGenerator\Utils\Logger',
		'ReasonDigital\CSVPageGenerator\Utils\Database',
	);
	
	$missing_classes = array();
	foreach ( $required_classes as $class ) {
		if ( ! class_exists( $class ) ) {
			$missing_classes[] = $class;
		}
	}
	
	if ( empty( $missing_classes ) ) {
		echo "   ✅ All required classes are loaded\n";
	} else {
		echo "   ❌ Missing classes:\n";
		foreach ( $missing_classes as $class ) {
			echo "   - {$class}\n";
		}
		$all_tests_passed = false;
	}
	
	// Test 7: Basic CSV Processing
	echo "\n7. Testing Basic CSV Processing...\n";
	try {
		if ( class_exists( 'ReasonDigital\CSVPageGenerator\CSV\Parser' ) ) {
			$logger = new \ReasonDigital\CSVPageGenerator\Utils\Logger();
			$parser = new \ReasonDigital\CSVPageGenerator\CSV\Parser( $logger );
			
			// Test with sample file
			$parsed_data = $parser->parse_file( $sample_file );
			
			echo "   ✅ CSV parsing successful\n";
			echo "   - Total rows: " . $parsed_data['total_rows'] . "\n";
			echo "   - Valid rows: " . $parsed_data['valid_rows'] . "\n";
			echo "   - Error rows: " . $parsed_data['error_rows'] . "\n";
		} else {
			echo "   ❌ Parser class not available\n";
			$all_tests_passed = false;
		}
	} catch ( Exception $e ) {
		echo "   ❌ CSV parsing failed: " . $e->getMessage() . "\n";
		$all_tests_passed = false;
	}
	
	// Test 8: Check for Created Pages (if any exist)
	echo "\n8. Checking for CSV-Generated Pages...\n";
	$csv_pages = get_posts( array(
		'post_type'   => 'page',
		'post_status' => 'any',
		'numberposts' => -1,
		'meta_query'  => array(
			array(
				'key'     => '_csv_page_generator_source',
				'compare' => 'EXISTS',
			),
		),
	) );
	
	if ( ! empty( $csv_pages ) ) {
		echo "   ✅ Found " . count( $csv_pages ) . " CSV-generated pages\n";
		echo "   - Sample titles:\n";
		foreach ( array_slice( $csv_pages, 0, 3 ) as $page ) {
			echo "     * " . $page->post_title . " (ID: {$page->ID})\n";
		}
	} else {
		echo "   ℹ️  No CSV-generated pages found (this is normal for fresh installation)\n";
	}
	
	// Test 9: Import History
	echo "\n9. Checking Import History...\n";
	$import_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	if ( $import_count > 0 ) {
		echo "   ✅ Found {$import_count} import record(s)\n";
		
		$recent_import = $wpdb->get_row( "SELECT * FROM {$table_name} ORDER BY started_at DESC LIMIT 1" );
		if ( $recent_import ) {
			echo "   - Most recent: {$recent_import->filename} ({$recent_import->status})\n";
			echo "   - Success rate: {$recent_import->successful_rows}/{$recent_import->total_rows} rows\n";
		}
	} else {
		echo "   ℹ️  No import history found (this is normal for fresh installation)\n";
	}
	
	// Final Summary
	echo "\n" . str_repeat( "=", 60 ) . "\n";
	if ( $all_tests_passed ) {
		echo "🎉 ALL TESTS PASSED! Plugin is ready for use.\n\n";
		echo "Next steps:\n";
		echo "1. Access WordPress admin: http://127.0.0.1:[PORT]/wp-admin\n";
		echo "2. Go to CSV Pages → Upload CSV\n";
		echo "3. Upload the sample file: samples/sample-data.csv\n";
		echo "4. Verify 14 pages are created successfully\n";
	} else {
		echo "❌ SOME TESTS FAILED! Please review the issues above.\n\n";
		echo "Common solutions:\n";
		echo "1. Ensure plugin is activated: wp plugin activate csv-page-generator\n";
		echo "2. Check file permissions: chmod -R 755 wp-content/plugins/csv-page-generator/\n";
		echo "3. Create upload directory: mkdir -p wp-content/uploads/csv-imports\n";
		echo "4. Restart DDEV: ddev restart\n";
	}
	echo "\n";
}

// Run verification if accessed directly via WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	verify_csv_plugin_setup();
} elseif ( isset( $_GET['verify_setup'] ) && current_user_can( 'manage_options' ) ) {
	// Run via web interface
	echo "<pre>";
	verify_csv_plugin_setup();
	echo "</pre>";
} else {
	echo "<h2>CSV Plugin Setup Verification</h2>";
	echo "<p>This script verifies that the CSV Page Generator plugin is properly installed and configured.</p>";
	echo "<p><a href='" . add_query_arg( 'verify_setup', '1' ) . "' class='button button-primary'>Run Verification</a></p>";
	echo "<p><strong>Note:</strong> You must be logged in as an administrator to run this verification.</p>";
}
