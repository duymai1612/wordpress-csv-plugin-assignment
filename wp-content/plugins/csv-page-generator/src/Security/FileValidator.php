<?php
/**
 * File Validator Class
 *
 * @package ReasonDigital\CSVPageGenerator\Security
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Security;

use ReasonDigital\CSVPageGenerator\Utils\Logger;

/**
 * Validates uploaded files for security and compliance.
 *
 * Provides comprehensive file validation including type checking,
 * size limits, content scanning, and security measures.
 */
class FileValidator {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Allowed MIME types.
	 *
	 * @var array
	 */
	private $allowed_mime_types = array(
		'text/csv',
		'text/plain',
		'application/csv',
		'application/excel',
		'application/vnd.ms-excel',
		'application/vnd.msexcel',
	);

	/**
	 * Allowed file extensions.
	 *
	 * @var array
	 */
	private $allowed_extensions = array( 'csv' );

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger   = $logger;
		$this->settings = get_option( 'csv_page_generator_settings', array() );
	}

	/**
	 * Validate uploaded file.
	 *
	 * @param array $uploaded_file Uploaded file data from $_FILES.
	 * @return array Validation result.
	 */
	public function validate_uploaded_file( array $uploaded_file ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		// Basic file validation
		$basic_validation = $this->validate_basic_file_properties( $uploaded_file );
		$result           = $this->merge_validation_results( $result, $basic_validation );

		if ( ! $result['valid'] ) {
			return $result;
		}

		// File type validation
		$type_validation = $this->validate_file_type( $uploaded_file );
		$result          = $this->merge_validation_results( $result, $type_validation );

		if ( ! $result['valid'] ) {
			return $result;
		}

		// File size validation
		$size_validation = $this->validate_file_size( $uploaded_file );
		$result          = $this->merge_validation_results( $result, $size_validation );

		if ( ! $result['valid'] ) {
			return $result;
		}

		// Content validation
		$content_validation = $this->validate_file_content( $uploaded_file );
		$result             = $this->merge_validation_results( $result, $content_validation );

		// Security validation
		$security_validation = $this->validate_file_security( $uploaded_file );
		$result              = $this->merge_validation_results( $result, $security_validation );

		$this->logger->info(
			'File validation completed',
			array(
				'filename' => $uploaded_file['name'],
				'valid'    => $result['valid'],
				'errors'   => count( $result['errors'] ),
				'warnings' => count( $result['warnings'] ),
			)
		);

		return $result;
	}

	/**
	 * Validate basic file properties.
	 *
	 * @param array $uploaded_file Uploaded file data.
	 * @return array Validation result.
	 */
	private function validate_basic_file_properties( array $uploaded_file ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		// Check if file was uploaded
		if ( empty( $uploaded_file['tmp_name'] ) || ! is_uploaded_file( $uploaded_file['tmp_name'] ) ) {
			$result['valid']    = false;
			$result['errors'][] = __( 'File was not properly uploaded.', 'csv-page-generator' );
			return $result;
		}

		// Check filename
		if ( empty( $uploaded_file['name'] ) ) {
			$result['valid']    = false;
			$result['errors'][] = __( 'Filename is missing.', 'csv-page-generator' );
			return $result;
		}

		// Sanitize filename
		$sanitized_name = sanitize_file_name( $uploaded_file['name'] );
		if ( $sanitized_name !== $uploaded_file['name'] ) {
			$result['warnings'][] = __( 'Filename contains invalid characters and will be sanitized.', 'csv-page-generator' );
		}

		// Check file exists and is readable
		if ( ! file_exists( $uploaded_file['tmp_name'] ) || ! is_readable( $uploaded_file['tmp_name'] ) ) {
			$result['valid']    = false;
			$result['errors'][] = __( 'Uploaded file is not accessible.', 'csv-page-generator' );
			return $result;
		}

		$result['info']['original_name']  = $uploaded_file['name'];
		$result['info']['sanitized_name'] = $sanitized_name;
		$result['info']['tmp_name']       = $uploaded_file['tmp_name'];

		return $result;
	}

	/**
	 * Validate file type and extension.
	 *
	 * @param array $uploaded_file Uploaded file data.
	 * @return array Validation result.
	 */
	private function validate_file_type( array $uploaded_file ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		// Check file extension
		$file_extension = strtolower( pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_extension, $this->allowed_extensions, true ) ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				/* translators: 1: file extension, 2: allowed extensions */
				__( 'File extension "%1$s" is not allowed. Allowed extensions: %2$s', 'csv-page-generator' ),
				$file_extension,
				implode( ', ', $this->allowed_extensions )
			);
		}

		// Check MIME type
		$mime_type = $this->get_file_mime_type( $uploaded_file['tmp_name'] );

		if ( ! in_array( $mime_type, $this->allowed_mime_types, true ) ) {
			$result['warnings'][] = sprintf(
				/* translators: 1: detected MIME type, 2: allowed MIME types */
				__( 'File MIME type "%1$s" is unusual for CSV files. Expected: %2$s', 'csv-page-generator' ),
				$mime_type,
				implode( ', ', $this->allowed_mime_types )
			);
		}

		$result['info']['extension'] = $file_extension;
		$result['info']['mime_type'] = $mime_type;

		return $result;
	}

	/**
	 * Get file MIME type.
	 *
	 * @param string $file_path File path.
	 * @return string MIME type.
	 */
	private function get_file_mime_type( $file_path ) {
		// Try multiple methods for MIME type detection
		if ( function_exists( 'finfo_file' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );

			if ( $mime_type ) {
				return $mime_type;
			}
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );
			if ( $mime_type ) {
				return $mime_type;
			}
		}

		// Fallback to WordPress function
		$wp_filetype = wp_check_filetype( basename( $file_path ) );
		return $wp_filetype['type'] ?: 'application/octet-stream';
	}

	/**
	 * Validate file size.
	 *
	 * @param array $uploaded_file Uploaded file data.
	 * @return array Validation result.
	 */
	private function validate_file_size( array $uploaded_file ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		$file_size = $uploaded_file['size'];
		$max_size  = $this->settings['max_file_size'] ?? 10485760; // 10MB default

		// Check if file is empty
		if ( $file_size === 0 ) {
			$result['valid']    = false;
			$result['errors'][] = __( 'File is empty.', 'csv-page-generator' );
			return $result;
		}

		// Check maximum size
		if ( $file_size > $max_size ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				/* translators: 1: file size, 2: maximum allowed size */
				__( 'File size (%1$s) exceeds maximum allowed size (%2$s).', 'csv-page-generator' ),
				size_format( $file_size ),
				size_format( $max_size )
			);
		}

		// Warning for large files
		$warning_threshold = $max_size * 0.8; // 80% of max size
		if ( $file_size > $warning_threshold ) {
			$result['warnings'][] = sprintf(
				/* translators: %s: file size */
				__( 'File is quite large (%s). Processing may take longer.', 'csv-page-generator' ),
				size_format( $file_size )
			);
		}

		$result['info']['file_size'] = $file_size;
		$result['info']['max_size']  = $max_size;

		return $result;
	}

	/**
	 * Validate file content.
	 *
	 * @param array $uploaded_file Uploaded file data.
	 * @return array Validation result.
	 */
	private function validate_file_content( array $uploaded_file ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		$file_path = $uploaded_file['tmp_name'];

		// Read first few lines to validate CSV structure
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			$result['valid']    = false;
			$result['errors'][] = __( 'Cannot read file content.', 'csv-page-generator' );
			return $result;
		}

		try {
			// Check for BOM and encoding issues
			$first_bytes = fread( $handle, 3 );
			rewind( $handle );

			if ( $first_bytes === "\xEF\xBB\xBF" ) {
				$result['info']['has_bom'] = true;
			}

			// Read header line
			$header_line = fgets( $handle );
			if ( ! $header_line ) {
				$result['valid']    = false;
				$result['errors'][] = __( 'File appears to be empty or unreadable.', 'csv-page-generator' );
				fclose( $handle );
				return $result;
			}

			// Basic CSV structure validation
			$header_fields = str_getcsv( $header_line );

			if ( count( $header_fields ) < 2 ) {
				$result['valid']    = false;
				$result['errors'][] = __( 'CSV file must have at least 2 columns (Title and Description).', 'csv-page-generator' );
			}

			// Check for required headers
			$normalized_headers = array_map( 'strtolower', array_map( 'trim', $header_fields ) );
			$required_headers   = array( 'title', 'description' );
			$missing_headers    = array_diff( $required_headers, $normalized_headers );

			if ( ! empty( $missing_headers ) ) {
				$result['valid']    = false;
				$result['errors'][] = sprintf(
					/* translators: %s: comma-separated list of missing headers */
					__( 'Required CSV headers missing: %s', 'csv-page-generator' ),
					implode( ', ', $missing_headers )
				);
			}

			// Count approximate number of rows
			$row_count = 0;
			while ( fgets( $handle ) && $row_count < 1000 ) { // Sample first 1000 rows
				++$row_count;
			}

			$result['info']['header_fields']  = $header_fields;
			$result['info']['estimated_rows'] = $row_count;

		} finally {
			fclose( $handle );
		}

		return $result;
	}

	/**
	 * Validate file security.
	 *
	 * @param array $uploaded_file Uploaded file data.
	 * @return array Validation result.
	 */
	private function validate_file_security( array $uploaded_file ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		$file_path = $uploaded_file['tmp_name'];

		// Check for suspicious content patterns
		$suspicious_patterns = array(
			'/<script[^>]*>/i',
			'/<iframe[^>]*>/i',
			'/javascript:/i',
			'/vbscript:/i',
			'/onload\s*=/i',
			'/onerror\s*=/i',
		);

		// Read file content for security scanning
		$content = file_get_contents( $file_path );
		if ( $content === false ) {
			$result['warnings'][] = __( 'Could not scan file content for security issues.', 'csv-page-generator' );
			return $result;
		}

		// Check for suspicious patterns
		foreach ( $suspicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				$result['warnings'][] = __( 'File contains potentially suspicious content. Please review before processing.', 'csv-page-generator' );
				break;
			}
		}

		// Check for excessively long lines (potential DoS)
		$lines           = explode( "\n", $content );
		$max_line_length = 10000; // 10KB per line

		foreach ( $lines as $line_number => $line ) {
			if ( strlen( $line ) > $max_line_length ) {
				$result['warnings'][] = sprintf(
					/* translators: %d: line number */
					__( 'Line %d is extremely long and may cause processing issues.', 'csv-page-generator' ),
					$line_number + 1
				);
				break;
			}
		}

		// Check for binary content
		if ( ! mb_check_encoding( $content, 'UTF-8' ) && ! mb_check_encoding( $content, 'ISO-8859-1' ) ) {
			$result['warnings'][] = __( 'File may contain binary data or unsupported encoding.', 'csv-page-generator' );
		}

		$result['info']['content_length'] = strlen( $content );
		$result['info']['line_count']     = count( $lines );

		return $result;
	}

	/**
	 * Merge validation results.
	 *
	 * @param array $result1 First result.
	 * @param array $result2 Second result.
	 * @return array Merged result.
	 */
	private function merge_validation_results( array $result1, array $result2 ) {
		return array(
			'valid'    => $result1['valid'] && $result2['valid'],
			'errors'   => array_merge( $result1['errors'], $result2['errors'] ),
			'warnings' => array_merge( $result1['warnings'], $result2['warnings'] ),
			'info'     => array_merge( $result1['info'], $result2['info'] ),
		);
	}

	/**
	 * Get allowed file types.
	 *
	 * @return array Allowed file types.
	 */
	public function get_allowed_types() {
		return array(
			'extensions' => $this->allowed_extensions,
			'mime_types' => $this->allowed_mime_types,
		);
	}

	/**
	 * Get maximum file size.
	 *
	 * @return int Maximum file size in bytes.
	 */
	public function get_max_file_size() {
		return $this->settings['max_file_size'] ?? 10485760;
	}
}
