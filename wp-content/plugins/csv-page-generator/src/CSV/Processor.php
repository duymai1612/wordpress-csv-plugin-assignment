<?php
/**
 * CSV Processor Class
 *
 * @package ReasonDigital\CSVPageGenerator\CSV
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\CSV;

use ReasonDigital\CSVPageGenerator\CSV\Parser;
use ReasonDigital\CSVPageGenerator\CSV\Validator;
use ReasonDigital\CSVPageGenerator\Pages\Generator;
use ReasonDigital\CSVPageGenerator\Utils\Logger;
use ReasonDigital\CSVPageGenerator\Utils\Database;

/**
 * Main CSV processing orchestrator.
 *
 * Coordinates the entire CSV processing workflow from upload
 * to page generation with comprehensive error handling and logging.
 */
class Processor {

	/**
	 * Parser instance.
	 *
	 * @var Parser
	 */
	private $parser;

	/**
	 * Validator instance.
	 *
	 * @var Validator
	 */
	private $validator;

	/**
	 * Page generator instance.
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Database utility instance.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Current import ID.
	 *
	 * @var int
	 */
	private $import_id;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->parser = new Parser( $logger );
		$this->validator = new Validator( $logger );
		$this->generator = new Generator( $logger );
		$this->database = new Database( $logger );
		$this->settings = get_option( 'csv_page_generator_settings', array() );
	}

	/**
	 * Process uploaded CSV file.
	 *
	 * @param string $file_path Path to uploaded CSV file.
	 * @param array  $options Processing options.
	 * @return array Processing results.
	 * @throws \Exception If processing fails.
	 */
	public function process_csv_file( $file_path, array $options = array() ) {
		$start_time = microtime( true );

		// Set default options
		$options = wp_parse_args( $options, array(
			'post_status'  => $this->settings['default_post_status'] ?? 'draft',
			'post_author'  => $this->settings['default_post_author'] ?? get_current_user_id(),
			'batch_size'   => $this->settings['batch_size'] ?? 100,
			'skip_errors'  => true,
			'send_notifications' => $this->settings['enable_notifications'] ?? false,
		) );

		$this->logger->info( 'Starting CSV file processing', array(
			'file_path' => $file_path,
			'options'   => $options,
		) );

		try {
			// Create import record
			$this->import_id = $this->create_import_record( $file_path, $options );

			// Step 1: Parse CSV file
			$this->update_import_status( 'parsing' );
			$parsed_data = $this->parser->parse_file( $file_path );

			// Step 2: Validate data
			$this->update_import_status( 'validating' );
			$validation_results = $this->validator->validate_csv_data( $parsed_data );

			// Step 3: Process valid rows
			$this->update_import_status( 'processing' );
			$processing_results = $this->process_validated_data( $validation_results, $options );

			// Step 4: Finalize import
			$this->update_import_status( 'completed' );
			$final_results = $this->finalize_import( $parsed_data, $validation_results, $processing_results );

			$processing_time = microtime( true ) - $start_time;
			$this->logger->info( 'CSV processing completed successfully', array(
				'import_id'       => $this->import_id,
				'processing_time' => $processing_time,
				'results'         => $final_results,
			) );

			// Send notification if enabled
			if ( $options['send_notifications'] ) {
				$this->send_completion_notification( $final_results );
			}

			return $final_results;

		} catch ( \Exception $e ) {
			$this->handle_processing_error( $e );
			throw $e;
		}
	}

	/**
	 * Create import record in database.
	 *
	 * @param string $file_path File path.
	 * @param array  $options Processing options.
	 * @return int Import ID.
	 */
	private function create_import_record( $file_path, array $options ) {
		$import_data = array(
			'user_id'           => get_current_user_id(),
			'filename'          => basename( $file_path ),
			'original_filename' => $options['original_filename'] ?? basename( $file_path ),
			'file_size'         => filesize( $file_path ),
			'total_rows'        => 0,
			'processed_rows'    => 0,
			'successful_rows'   => 0,
			'failed_rows'       => 0,
			'status'            => 'pending',
			'error_log'         => '',
			'created_pages'     => '',
			'started_at'        => current_time( 'mysql' ),
		);

		return $this->database->create_import_record( $import_data );
	}

	/**
	 * Update import status.
	 *
	 * @param string $status New status.
	 * @param array  $data Additional data to update.
	 */
	private function update_import_status( $status, array $data = array() ) {
		$update_data = array_merge( array( 'status' => $status ), $data );
		$this->database->update_import_record( $this->import_id, $update_data );
	}

	/**
	 * Process validated CSV data.
	 *
	 * @param array $validation_results Validation results.
	 * @param array $options Processing options.
	 * @return array Processing results.
	 */
	private function process_validated_data( array $validation_results, array $options ) {
		$results = array(
			'created_pages'   => array(),
			'failed_pages'    => array(),
			'skipped_pages'   => array(),
			'total_processed' => 0,
		);

		$batch_size = $options['batch_size'];
		$valid_rows = array_filter( $validation_results['row_results'], function( $row ) {
			return $row['valid'];
		} );

		$total_valid = count( $valid_rows );
		$processed = 0;

		// Update total rows in import record
		$this->update_import_status( 'processing', array(
			'total_rows' => $validation_results['total_rows'],
		) );

		// Process in batches
		$batches = array_chunk( $valid_rows, $batch_size, true );
		
		foreach ( $batches as $batch_index => $batch ) {
			$this->logger->debug( 'Processing batch', array(
				'batch_index' => $batch_index + 1,
				'batch_size'  => count( $batch ),
				'total_batches' => count( $batches ),
			) );

			foreach ( $batch as $row_index => $row_result ) {
				try {
					$page_data = $this->prepare_page_data( $row_result['data'], $options );
					$page_id = $this->generator->create_page( $page_data );

					$results['created_pages'][] = array(
						'page_id'    => $page_id,
						'title'      => $page_data['post_title'],
						'slug'       => $page_data['post_name'],
						'row_number' => $row_result['row_number'],
					);

					$processed++;

				} catch ( \Exception $e ) {
					$error_info = array(
						'row_number' => $row_result['row_number'],
						'error'      => $e->getMessage(),
						'data'       => $row_result['data'],
					);

					$results['failed_pages'][] = $error_info;

					$this->logger->error( 'Failed to create page from CSV row', $error_info );

					// Skip or stop based on options
					if ( ! $options['skip_errors'] ) {
						throw $e;
					}
				}

				// Update progress
				$this->update_import_status( 'processing', array(
					'processed_rows'  => $processed,
					'successful_rows' => count( $results['created_pages'] ),
					'failed_rows'     => count( $results['failed_pages'] ),
				) );
			}

			// Allow for memory cleanup between batches
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}

		$results['total_processed'] = $processed;

		return $results;
	}

	/**
	 * Prepare page data from CSV row.
	 *
	 * @param array $row_data CSV row data.
	 * @param array $options Processing options.
	 * @return array WordPress page data.
	 */
	private function prepare_page_data( array $row_data, array $options ) {
		$page_data = array(
			'post_title'   => $row_data['title'],
			'post_content' => $row_data['description'],
			'post_status'  => $row_data['status'] ?: $options['post_status'],
			'post_author'  => $options['post_author'],
			'post_type'    => 'page',
			'meta_input'   => array(
				'_csv_page_generator_source' => $this->import_id,
				'_csv_page_generator_row'    => $row_data['row_number'] ?? 0,
			),
		);

		// Handle slug
		if ( ! empty( $row_data['slug'] ) ) {
			$page_data['post_name'] = $row_data['slug'];
		}

		// Handle meta description
		if ( ! empty( $row_data['meta_description'] ) ) {
			$page_data['meta_input']['_yoast_wpseo_metadesc'] = $row_data['meta_description'];
		}

		// Handle categories (convert to tags for pages)
		if ( ! empty( $row_data['categories'] ) ) {
			$categories = array_map( 'trim', explode( ',', $row_data['categories'] ) );
			$page_data['tags_input'] = $categories;
		}

		// Handle featured image
		if ( ! empty( $row_data['featured_image_url'] ) ) {
			$page_data['meta_input']['_csv_featured_image_url'] = $row_data['featured_image_url'];
		}

		return $page_data;
	}

	/**
	 * Finalize import process.
	 *
	 * @param array $parsed_data Parsed CSV data.
	 * @param array $validation_results Validation results.
	 * @param array $processing_results Processing results.
	 * @return array Final results.
	 */
	private function finalize_import( array $parsed_data, array $validation_results, array $processing_results ) {
		$final_results = array(
			'import_id'       => $this->import_id,
			'total_rows'      => $parsed_data['total_rows'],
			'valid_rows'      => $validation_results['valid_rows'],
			'invalid_rows'    => $validation_results['invalid_rows'],
			'processed_rows'  => $processing_results['total_processed'],
			'created_pages'   => count( $processing_results['created_pages'] ),
			'failed_pages'    => count( $processing_results['failed_pages'] ),
			'warnings'        => $validation_results['warnings'],
			'pages'           => $processing_results['created_pages'],
			'errors'          => $processing_results['failed_pages'],
			'completed_at'    => current_time( 'mysql' ),
		);

		// Update final import record
		$this->update_import_status( 'completed', array(
			'total_rows'      => $final_results['total_rows'],
			'processed_rows'  => $final_results['processed_rows'],
			'successful_rows' => $final_results['created_pages'],
			'failed_rows'     => $final_results['failed_pages'],
			'completed_at'    => $final_results['completed_at'],
			'created_pages'   => wp_json_encode( $processing_results['created_pages'] ),
			'error_log'       => wp_json_encode( $processing_results['failed_pages'] ),
		) );

		return $final_results;
	}

	/**
	 * Handle processing errors.
	 *
	 * @param \Exception $exception The exception that occurred.
	 */
	private function handle_processing_error( \Exception $exception ) {
		$this->logger->error( 'CSV processing failed', array(
			'import_id' => $this->import_id,
			'error'     => $exception->getMessage(),
			'trace'     => $exception->getTraceAsString(),
		) );

		if ( $this->import_id ) {
			$this->update_import_status( 'failed', array(
				'error_log'    => $exception->getMessage(),
				'completed_at' => current_time( 'mysql' ),
			) );
		}
	}

	/**
	 * Send completion notification.
	 *
	 * @param array $results Processing results.
	 */
	private function send_completion_notification( array $results ) {
		$notification_email = $this->settings['notification_email'] ?? get_option( 'admin_email' );
		
		if ( empty( $notification_email ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'CSV Import Completed - %s', 'csv-page-generator' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: total rows, 2: created pages, 3: failed pages */
			__( "CSV import has been completed.\n\nSummary:\n- Total rows processed: %1\$d\n- Pages created successfully: %2\$d\n- Failed pages: %3\$d\n\nImport ID: %4\$d", 'csv-page-generator' ),
			$results['processed_rows'],
			$results['created_pages'],
			$results['failed_pages'],
			$results['import_id']
		);

		wp_mail( $notification_email, $subject, $message );
	}

	/**
	 * Get import progress.
	 *
	 * @param int $import_id Import ID.
	 * @return array Progress information.
	 */
	public function get_import_progress( $import_id ) {
		return $this->database->get_import_record( $import_id );
	}

	/**
	 * Cancel import process.
	 *
	 * @param int $import_id Import ID.
	 * @return bool Success status.
	 */
	public function cancel_import( $import_id ) {
		$this->logger->info( 'Import cancelled by user', array( 'import_id' => $import_id ) );
		
		return $this->database->update_import_record( $import_id, array(
			'status'       => 'cancelled',
			'completed_at' => current_time( 'mysql' ),
		) );
	}
}
