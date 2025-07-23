<?php
/**
 * Upload Handler Class
 *
 * @package ReasonDigital\CSVPageGenerator\Admin
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Admin;

use ReasonDigital\CSVPageGenerator\CSV\Processor;
use ReasonDigital\CSVPageGenerator\Security\FileValidator;
use ReasonDigital\CSVPageGenerator\Utils\Logger;

/**
 * Handles CSV file uploads and processing initiation.
 *
 * Provides secure file upload handling with validation,
 * sanitization, and processing coordination.
 */
class UploadHandler {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * File validator instance.
	 *
	 * @var FileValidator
	 */
	private $file_validator;

	/**
	 * CSV processor instance.
	 *
	 * @var Processor
	 */
	private $processor;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Upload directory path.
	 *
	 * @var string
	 */
	private $upload_dir;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->file_validator = new FileValidator( $logger );
		$this->processor = new Processor( $logger );
		$this->settings = get_option( 'csv_page_generator_settings', array() );
		$this->setup_upload_directory();
	}

	/**
	 * Set up upload directory.
	 */
	private function setup_upload_directory() {
		$wp_upload_dir = wp_upload_dir();
		$this->upload_dir = $wp_upload_dir['basedir'] . '/csv-imports';

		// Ensure directory exists
		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );
		}
	}

	/**
	 * Handle CSV file upload via AJAX.
	 */
	public function handle_ajax_upload() {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'csv_page_generator_upload' ) ) {
				throw new \Exception( __( 'Security verification failed.', 'csv-page-generator' ) );
			}

			// Check user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( __( 'Insufficient permissions.', 'csv-page-generator' ) );
			}

			// Validate file upload
			if ( empty( $_FILES['csv_file'] ) ) {
				throw new \Exception( __( 'No file uploaded.', 'csv-page-generator' ) );
			}

			$uploaded_file = $_FILES['csv_file'];

			// Check for upload errors
			if ( $uploaded_file['error'] !== UPLOAD_ERR_OK ) {
				throw new \Exception( $this->get_upload_error_message( $uploaded_file['error'] ) );
			}

			// Validate file
			$validation_result = $this->file_validator->validate_uploaded_file( $uploaded_file );
			if ( ! $validation_result['valid'] ) {
				throw new \Exception( implode( ' ', $validation_result['errors'] ) );
			}

			// Move file to secure location
			$file_path = $this->move_uploaded_file( $uploaded_file );

			// Get processing options
			$options = $this->get_processing_options();

			// Start processing (this could be done in background)
			$results = $this->processor->process_csv_file( $file_path, $options );

			// Clean up uploaded file
			$this->cleanup_file( $file_path );

			wp_send_json_success( array(
				'message'   => __( 'CSV file processed successfully.', 'csv-page-generator' ),
				'results'   => $results,
				'import_id' => $results['import_id'],
			) );

		} catch ( \Exception $e ) {
			$this->logger->error( 'CSV upload failed', array(
				'error' => $e->getMessage(),
				'user_id' => get_current_user_id(),
			) );

			wp_send_json_error( array(
				'message' => $e->getMessage(),
			) );
		}
	}

	/**
	 * Move uploaded file to secure location.
	 *
	 * @param array $uploaded_file Uploaded file data.
	 * @return string Path to moved file.
	 * @throws \Exception If file move fails.
	 */
	private function move_uploaded_file( array $uploaded_file ) {
		$filename = $this->generate_secure_filename( $uploaded_file['name'] );
		$file_path = $this->upload_dir . '/' . $filename;

		if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $file_path ) ) {
			throw new \Exception( __( 'Failed to move uploaded file to secure location.', 'csv-page-generator' ) );
		}

		// Set secure file permissions
		chmod( $file_path, 0644 );

		$this->logger->info( 'File uploaded successfully', array(
			'original_name' => $uploaded_file['name'],
			'secure_name'   => $filename,
			'file_size'     => $uploaded_file['size'],
		) );

		return $file_path;
	}

	/**
	 * Generate secure filename.
	 *
	 * @param string $original_name Original filename.
	 * @return string Secure filename.
	 */
	private function generate_secure_filename( $original_name ) {
		$info = pathinfo( $original_name );
		$extension = strtolower( $info['extension'] ?? '' );
		
		// Ensure CSV extension
		if ( 'csv' !== $extension ) {
			$extension = 'csv';
		}

		// Generate unique filename
		$timestamp = time();
		$user_id = get_current_user_id();
		$random = wp_generate_password( 8, false );
		
		return sprintf( 'csv_%d_%d_%s.%s', $user_id, $timestamp, $random, $extension );
	}

	/**
	 * Get processing options from request.
	 *
	 * @return array Processing options.
	 */
	private function get_processing_options() {
		$options = array(
			'original_filename' => sanitize_file_name( $_FILES['csv_file']['name'] ?? '' ),
			'post_status'       => sanitize_text_field( $_POST['post_status'] ?? 'draft' ),
			'post_author'       => absint( $_POST['post_author'] ?? get_current_user_id() ),
			'skip_errors'       => true,
			'send_notifications' => $this->settings['enable_notifications'] ?? false,
		);

		// Validate post status
		$valid_statuses = array( 'draft', 'publish', 'private', 'pending' );
		if ( ! in_array( $options['post_status'], $valid_statuses, true ) ) {
			$options['post_status'] = 'draft';
		}

		// Validate post author
		if ( ! get_userdata( $options['post_author'] ) ) {
			$options['post_author'] = get_current_user_id();
		}

		return $options;
	}

	/**
	 * Get upload error message.
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( $error_code ) {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
				return __( 'File size exceeds server upload limit.', 'csv-page-generator' );
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'File size exceeds form upload limit.', 'csv-page-generator' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'File was only partially uploaded.', 'csv-page-generator' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'csv-page-generator' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Missing temporary upload directory.', 'csv-page-generator' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Failed to write file to disk.', 'csv-page-generator' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'File upload stopped by extension.', 'csv-page-generator' );
			default:
				return __( 'Unknown upload error occurred.', 'csv-page-generator' );
		}
	}

	/**
	 * Clean up uploaded file.
	 *
	 * @param string $file_path File path to clean up.
	 */
	private function cleanup_file( $file_path ) {
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
			$this->logger->debug( 'Uploaded file cleaned up', array( 'file_path' => $file_path ) );
		}
	}

	/**
	 * Handle file upload via standard form submission.
	 *
	 * @return array Upload results.
	 * @throws \Exception If upload fails.
	 */
	public function handle_form_upload() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['csv_upload_nonce'] ?? '', 'csv_page_generator_upload' ) ) {
			throw new \Exception( __( 'Security verification failed.', 'csv-page-generator' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( __( 'Insufficient permissions.', 'csv-page-generator' ) );
		}

		// Validate file upload
		if ( empty( $_FILES['csv_file'] ) ) {
			throw new \Exception( __( 'No file uploaded.', 'csv-page-generator' ) );
		}

		$uploaded_file = $_FILES['csv_file'];

		// Check for upload errors
		if ( $uploaded_file['error'] !== UPLOAD_ERR_OK ) {
			throw new \Exception( $this->get_upload_error_message( $uploaded_file['error'] ) );
		}

		// Validate file
		$validation_result = $this->file_validator->validate_uploaded_file( $uploaded_file );
		if ( ! $validation_result['valid'] ) {
			throw new \Exception( implode( ' ', $validation_result['errors'] ) );
		}

		// Move file to secure location
		$file_path = $this->move_uploaded_file( $uploaded_file );

		// Get processing options
		$options = $this->get_processing_options();

		try {
			// Process the file
			$results = $this->processor->process_csv_file( $file_path, $options );

			// Clean up uploaded file
			$this->cleanup_file( $file_path );

			return $results;

		} catch ( \Exception $e ) {
			// Clean up file on error
			$this->cleanup_file( $file_path );
			throw $e;
		}
	}

	/**
	 * Get upload progress for AJAX requests.
	 */
	public function get_upload_progress() {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'csv_page_generator_progress' ) ) {
				throw new \Exception( __( 'Security verification failed.', 'csv-page-generator' ) );
			}

			// Check user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( __( 'Insufficient permissions.', 'csv-page-generator' ) );
			}

			$import_id = absint( $_POST['import_id'] ?? 0 );
			if ( ! $import_id ) {
				throw new \Exception( __( 'Invalid import ID.', 'csv-page-generator' ) );
			}

			$progress = $this->processor->get_import_progress( $import_id );

			if ( ! $progress ) {
				throw new \Exception( __( 'Import not found.', 'csv-page-generator' ) );
			}

			wp_send_json_success( array(
				'progress' => $progress,
			) );

		} catch ( \Exception $e ) {
			wp_send_json_error( array(
				'message' => $e->getMessage(),
			) );
		}
	}

	/**
	 * Cancel import process.
	 */
	public function cancel_import() {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'csv_page_generator_cancel' ) ) {
				throw new \Exception( __( 'Security verification failed.', 'csv-page-generator' ) );
			}

			// Check user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( __( 'Insufficient permissions.', 'csv-page-generator' ) );
			}

			$import_id = absint( $_POST['import_id'] ?? 0 );
			if ( ! $import_id ) {
				throw new \Exception( __( 'Invalid import ID.', 'csv-page-generator' ) );
			}

			$success = $this->processor->cancel_import( $import_id );

			if ( $success ) {
				wp_send_json_success( array(
					'message' => __( 'Import cancelled successfully.', 'csv-page-generator' ),
				) );
			} else {
				throw new \Exception( __( 'Failed to cancel import.', 'csv-page-generator' ) );
			}

		} catch ( \Exception $e ) {
			wp_send_json_error( array(
				'message' => $e->getMessage(),
			) );
		}
	}

	/**
	 * Get maximum upload size.
	 *
	 * @return int Maximum upload size in bytes.
	 */
	public function get_max_upload_size() {
		$max_upload = wp_max_upload_size();
		$plugin_max = $this->settings['max_file_size'] ?? 10485760; // 10MB default
		
		return min( $max_upload, $plugin_max );
	}

	/**
	 * Get upload directory info.
	 *
	 * @return array Upload directory information.
	 */
	public function get_upload_info() {
		return array(
			'upload_dir'     => $this->upload_dir,
			'max_file_size'  => $this->get_max_upload_size(),
			'allowed_types'  => array( 'csv' ),
			'is_writable'    => is_writable( $this->upload_dir ),
		);
	}
}
