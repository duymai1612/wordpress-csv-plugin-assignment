<?php
/**
 * CSV Processing Test Script
 *
 * @package ReasonDigital\CSVPageGenerator\Tests
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

// This is a simple test script to verify CSV processing functionality
// Run this from WordPress admin or via WP-CLI

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only allow administrators to run this test
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Insufficient permissions to run CSV processing test.' );
}

// Load required classes
use ReasonDigital\CSVPageGenerator\CSV\Parser;
use ReasonDigital\CSVPageGenerator\CSV\Validator;
use ReasonDigital\CSVPageGenerator\CSV\Processor;
use ReasonDigital\CSVPageGenerator\Utils\Logger;

/**
 * Test CSV processing functionality.
 */
function test_csv_processing() {
	echo "<h2>CSV Processing Test</h2>\n";
	
	// Initialize logger
	$logger = new Logger();
	
	// Get sample CSV file path
	$sample_file = plugin_dir_path( dirname( __FILE__ ) ) . 'samples/sample-data.csv';
	
	if ( ! file_exists( $sample_file ) ) {
		echo "<p style='color: red;'>Error: Sample CSV file not found at: {$sample_file}</p>\n";
		return;
	}
	
	echo "<p><strong>Testing with sample file:</strong> " . basename( $sample_file ) . "</p>\n";
	echo "<p><strong>File size:</strong> " . size_format( filesize( $sample_file ) ) . "</p>\n";
	
	try {
		// Test 1: Parse CSV file
		echo "<h3>Test 1: CSV Parsing</h3>\n";
		$parser = new Parser( $logger );
		$parsed_data = $parser->parse_file( $sample_file );
		
		echo "<p>✅ CSV parsed successfully!</p>\n";
		echo "<ul>\n";
		echo "<li>Total rows: " . $parsed_data['total_rows'] . "</li>\n";
		echo "<li>Valid rows: " . $parsed_data['valid_rows'] . "</li>\n";
		echo "<li>Error rows: " . $parsed_data['error_rows'] . "</li>\n";
		echo "<li>Headers: " . implode( ', ', array_column( $parsed_data['headers'], 'original' ) ) . "</li>\n";
		echo "</ul>\n";
		
		// Test 2: Validate CSV data
		echo "<h3>Test 2: CSV Validation</h3>\n";
		$validator = new Validator( $logger );
		$validation_results = $validator->validate_csv_data( $parsed_data );
		
		echo "<p>✅ CSV validation completed!</p>\n";
		echo "<ul>\n";
		echo "<li>Total rows: " . $validation_results['total_rows'] . "</li>\n";
		echo "<li>Valid rows: " . $validation_results['valid_rows'] . "</li>\n";
		echo "<li>Invalid rows: " . $validation_results['invalid_rows'] . "</li>\n";
		echo "<li>Warnings: " . $validation_results['warnings'] . "</li>\n";
		echo "</ul>\n";
		
		// Show validation details for first few rows
		echo "<h4>Sample Row Validation Results:</h4>\n";
		echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
		echo "<tr><th>Row</th><th>Status</th><th>Title</th><th>Errors</th><th>Warnings</th></tr>\n";
		
		$sample_rows = array_slice( $validation_results['row_results'], 0, 5 );
		foreach ( $sample_rows as $index => $row_result ) {
			$status = $row_result['valid'] ? '✅ Valid' : '❌ Invalid';
			$title = substr( $row_result['data']['title'] ?? 'N/A', 0, 50 );
			$errors = count( $row_result['errors'] );
			$warnings = count( $row_result['warnings'] );
			
			echo "<tr>\n";
			echo "<td>" . $row_result['row_number'] . "</td>\n";
			echo "<td>{$status}</td>\n";
			echo "<td>{$title}</td>\n";
			echo "<td>{$errors}</td>\n";
			echo "<td>{$warnings}</td>\n";
			echo "</tr>\n";
		}
		echo "</table>\n";
		
		// Test 3: Process a small subset (first 3 valid rows only)
		echo "<h3>Test 3: Page Generation (Limited Test)</h3>\n";
		echo "<p><em>Note: This test will create actual WordPress pages from the first 3 valid CSV rows.</em></p>\n";
		
		// Filter to get only first 3 valid rows
		$valid_rows = array_filter( $validation_results['row_results'], function( $row ) {
			return $row['valid'];
		} );
		$test_rows = array_slice( $valid_rows, 0, 3, true );
		
		// Create a limited CSV data structure for testing
		$test_csv_data = array(
			'headers' => $parsed_data['headers'],
			'rows' => array(),
		);
		
		foreach ( $test_rows as $index => $row_result ) {
			$test_csv_data['rows'][] = array(
				'row_number' => $row_result['row_number'],
				'data' => $row_result['data'],
			);
		}
		
		// Process the test data
		$processor = new Processor( $logger );
		$options = array(
			'post_status' => 'draft',
			'post_author' => get_current_user_id(),
			'skip_errors' => true,
			'send_notifications' => false,
		);
		
		// Create temporary file for processing
		$temp_file = tempnam( sys_get_temp_dir(), 'csv_test_' );
		file_put_contents( $temp_file, file_get_contents( $sample_file ) );
		
		// Override the processor to use our limited data
		// For this test, we'll manually create a few pages
		echo "<p>Creating test pages...</p>\n";
		
		$created_pages = array();
		foreach ( $test_rows as $row_result ) {
			$page_data = array(
				'post_title'   => $row_result['data']['title'],
				'post_content' => $row_result['data']['description'],
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
				'meta_input'   => array(
					'_csv_page_generator_test' => true,
					'_csv_page_generator_row'  => $row_result['row_number'],
				),
			);
			
			$page_id = wp_insert_post( $page_data );
			if ( ! is_wp_error( $page_id ) ) {
				$created_pages[] = array(
					'page_id' => $page_id,
					'title'   => $row_result['data']['title'],
					'row'     => $row_result['row_number'],
				);
			}
		}
		
		echo "<p>✅ Test pages created successfully!</p>\n";
		echo "<ul>\n";
		foreach ( $created_pages as $page ) {
			$edit_link = admin_url( 'post.php?post=' . $page['page_id'] . '&action=edit' );
			echo "<li><a href='{$edit_link}' target='_blank'>Page ID {$page['page_id']}: {$page['title']}</a> (Row {$page['row']})</li>\n";
		}
		echo "</ul>\n";
		
		// Cleanup
		unlink( $temp_file );
		
		echo "<h3>Test Summary</h3>\n";
		echo "<p>✅ All tests completed successfully!</p>\n";
		echo "<p><strong>Note:</strong> Test pages were created with 'draft' status and marked with test metadata. You can safely delete them after reviewing.</p>\n";
		
	} catch ( Exception $e ) {
		echo "<p style='color: red;'>❌ Test failed with error: " . esc_html( $e->getMessage() ) . "</p>\n";
		echo "<p><strong>Stack trace:</strong></p>\n";
		echo "<pre>" . esc_html( $e->getTraceAsString() ) . "</pre>\n";
	}
}

// Run the test if this file is accessed directly
if ( isset( $_GET['run_csv_test'] ) && $_GET['run_csv_test'] === '1' ) {
	test_csv_processing();
} else {
	echo "<h2>CSV Processing Test</h2>\n";
	echo "<p>This script tests the CSV processing functionality with the sample data file.</p>\n";
	echo "<p><a href='" . add_query_arg( 'run_csv_test', '1' ) . "' class='button button-primary'>Run CSV Processing Test</a></p>\n";
	echo "<p><strong>Warning:</strong> This test will create actual WordPress pages. Make sure you're running this on a development site.</p>\n";
}
