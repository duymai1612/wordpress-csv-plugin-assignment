<?php
/**
 * CSV Parser Class
 *
 * @package ReasonDigital\CSVPageGenerator\CSV
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\CSV;

use ReasonDigital\CSVPageGenerator\Utils\Logger;

/**
 * Handles CSV file parsing with proper encoding and error handling.
 *
 * Provides robust CSV parsing capabilities with support for various
 * encodings, delimiters, and error recovery.
 */
class Parser {

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
	 * Supported encodings for detection.
	 *
	 * @var array
	 */
	private $supported_encodings = array(
		'UTF-8',
		'UTF-16',
		'UTF-16BE',
		'UTF-16LE',
		'ISO-8859-1',
		'Windows-1252',
		'ASCII',
	);

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->settings = get_option( 'csv_page_generator_settings', array() );
	}

	/**
	 * Parse a CSV file and return structured data.
	 *
	 * @param string $file_path Path to the CSV file.
	 * @param array  $options   Parsing options.
	 * @return array Parsed data with headers and rows.
	 * @throws \Exception If file cannot be parsed.
	 */
	public function parse_file( $file_path, array $options = array() ) {
		// Validate file exists and is readable
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			throw new \Exception( __( 'CSV file not found or not readable.', 'csv-page-generator' ) );
		}

		// Set default options
		$options = wp_parse_args( $options, array(
			'delimiter'     => ',',
			'enclosure'     => '"',
			'escape'        => '\\',
			'encoding'      => 'auto',
			'skip_empty'    => true,
			'max_rows'      => $this->settings['max_rows'] ?? 10000,
		) );

		$this->logger->info( 'Starting CSV file parsing', array(
			'file_path' => $file_path,
			'file_size' => filesize( $file_path ),
			'options'   => $options,
		) );

		try {
			// Detect and convert encoding if needed
			$content = $this->read_and_convert_encoding( $file_path, $options['encoding'] );

			// Parse CSV content
			$parsed_data = $this->parse_csv_content( $content, $options );

			$this->logger->info( 'CSV file parsed successfully', array(
				'total_rows' => count( $parsed_data['rows'] ),
				'headers'    => $parsed_data['headers'],
			) );

			return $parsed_data;

		} catch ( \Exception $e ) {
			$this->logger->error( 'CSV parsing failed', array(
				'error'     => $e->getMessage(),
				'file_path' => $file_path,
			) );
			throw $e;
		}
	}

	/**
	 * Read file content and convert encoding if necessary.
	 *
	 * @param string $file_path Path to the file.
	 * @param string $target_encoding Target encoding.
	 * @return string Converted file content.
	 * @throws \Exception If encoding conversion fails.
	 */
	private function read_and_convert_encoding( $file_path, $target_encoding = 'UTF-8' ) {
		$content = file_get_contents( $file_path );
		
		if ( false === $content ) {
			throw new \Exception( __( 'Failed to read CSV file content.', 'csv-page-generator' ) );
		}

		// Remove BOM if present
		$content = $this->remove_bom( $content );

		// Auto-detect encoding if requested
		if ( 'auto' === $target_encoding ) {
			$detected_encoding = $this->detect_encoding( $content );
			$this->logger->debug( 'Detected file encoding', array( 'encoding' => $detected_encoding ) );
		} else {
			$detected_encoding = $target_encoding;
		}

		// Convert to UTF-8 if needed
		if ( 'UTF-8' !== $detected_encoding ) {
			$converted_content = mb_convert_encoding( $content, 'UTF-8', $detected_encoding );
			
			if ( false === $converted_content ) {
				throw new \Exception( 
					sprintf( 
						/* translators: %s: detected encoding */
						__( 'Failed to convert file encoding from %s to UTF-8.', 'csv-page-generator' ), 
						$detected_encoding 
					) 
				);
			}
			
			$content = $converted_content;
			$this->logger->info( 'File encoding converted', array(
				'from' => $detected_encoding,
				'to'   => 'UTF-8',
			) );
		}

		return $content;
	}

	/**
	 * Remove Byte Order Mark (BOM) from content.
	 *
	 * @param string $content File content.
	 * @return string Content without BOM.
	 */
	private function remove_bom( $content ) {
		// UTF-8 BOM
		if ( substr( $content, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$content = substr( $content, 3 );
			$this->logger->debug( 'Removed UTF-8 BOM from file' );
		}
		// UTF-16 BE BOM
		elseif ( substr( $content, 0, 2 ) === "\xFE\xFF" ) {
			$content = substr( $content, 2 );
			$this->logger->debug( 'Removed UTF-16 BE BOM from file' );
		}
		// UTF-16 LE BOM
		elseif ( substr( $content, 0, 2 ) === "\xFF\xFE" ) {
			$content = substr( $content, 2 );
			$this->logger->debug( 'Removed UTF-16 LE BOM from file' );
		}

		return $content;
	}

	/**
	 * Detect file encoding.
	 *
	 * @param string $content File content.
	 * @return string Detected encoding.
	 */
	private function detect_encoding( $content ) {
		// Try mb_detect_encoding first
		if ( function_exists( 'mb_detect_encoding' ) ) {
			$encoding = mb_detect_encoding( $content, $this->supported_encodings, true );
			if ( $encoding ) {
				return $encoding;
			}
		}

		// Fallback to manual detection
		if ( mb_check_encoding( $content, 'UTF-8' ) ) {
			return 'UTF-8';
		}

		if ( mb_check_encoding( $content, 'ISO-8859-1' ) ) {
			return 'ISO-8859-1';
		}

		// Default fallback
		return 'UTF-8';
	}

	/**
	 * Parse CSV content into structured data.
	 *
	 * @param string $content CSV content.
	 * @param array  $options Parsing options.
	 * @return array Parsed data with headers and rows.
	 * @throws \Exception If parsing fails.
	 */
	private function parse_csv_content( $content, array $options ) {
		// Create temporary file for fgetcsv
		$temp_file = tmpfile();
		if ( false === $temp_file ) {
			throw new \Exception( __( 'Failed to create temporary file for CSV parsing.', 'csv-page-generator' ) );
		}

		fwrite( $temp_file, $content );
		rewind( $temp_file );

		$headers = array();
		$rows = array();
		$row_number = 0;
		$errors = array();

		try {
			// Read headers
			$header_row = fgetcsv( $temp_file, 0, $options['delimiter'], $options['enclosure'], $options['escape'] );
			
			if ( false === $header_row || empty( $header_row ) ) {
				throw new \Exception( __( 'CSV file appears to be empty or has no valid headers.', 'csv-page-generator' ) );
			}

			// Clean and validate headers
			$headers = $this->process_headers( $header_row );
			$row_number++;

			// Read data rows
			while ( ( $row = fgetcsv( $temp_file, 0, $options['delimiter'], $options['enclosure'], $options['escape'] ) ) !== false ) {
				$row_number++;

				// Skip empty rows if requested
				if ( $options['skip_empty'] && $this->is_empty_row( $row ) ) {
					continue;
				}

				// Check row limit
				if ( count( $rows ) >= $options['max_rows'] ) {
					$this->logger->warning( 'Maximum row limit reached', array(
						'max_rows'   => $options['max_rows'],
						'row_number' => $row_number,
					) );
					break;
				}

				// Process row data
				try {
					$processed_row = $this->process_row( $row, $headers, $row_number );
					$rows[] = $processed_row;
				} catch ( \Exception $e ) {
					$errors[] = array(
						'row_number' => $row_number,
						'error'      => $e->getMessage(),
						'raw_data'   => $row,
					);
					
					$this->logger->warning( 'Row processing error', array(
						'row_number' => $row_number,
						'error'      => $e->getMessage(),
					) );
				}
			}

		} finally {
			fclose( $temp_file );
		}

		return array(
			'headers'    => $headers,
			'rows'       => $rows,
			'errors'     => $errors,
			'total_rows' => $row_number - 1, // Exclude header row
			'valid_rows' => count( $rows ),
			'error_rows' => count( $errors ),
		);
	}

	/**
	 * Process and validate CSV headers.
	 *
	 * @param array $header_row Raw header row.
	 * @return array Processed headers.
	 * @throws \Exception If required headers are missing.
	 */
	private function process_headers( array $header_row ) {
		$headers = array();
		$required_headers = array( 'title', 'description' );

		// Clean and normalize headers
		foreach ( $header_row as $index => $header ) {
			$clean_header = trim( $header );
			$normalized_header = strtolower( $clean_header );
			
			$headers[ $index ] = array(
				'original' => $clean_header,
				'normalized' => $normalized_header,
			);
		}

		// Check for required headers
		$found_headers = array_column( $headers, 'normalized' );
		$missing_headers = array_diff( $required_headers, $found_headers );

		if ( ! empty( $missing_headers ) ) {
			throw new \Exception( 
				sprintf( 
					/* translators: %s: comma-separated list of missing headers */
					__( 'Required CSV headers missing: %s', 'csv-page-generator' ), 
					implode( ', ', $missing_headers ) 
				) 
			);
		}

		return $headers;
	}

	/**
	 * Process a single CSV row.
	 *
	 * @param array $row Raw row data.
	 * @param array $headers Header information.
	 * @param int   $row_number Row number for error reporting.
	 * @return array Processed row data.
	 * @throws \Exception If row processing fails.
	 */
	private function process_row( array $row, array $headers, $row_number ) {
		$processed_row = array(
			'row_number' => $row_number,
			'data'       => array(),
		);

		// Map row data to headers
		foreach ( $headers as $index => $header_info ) {
			$value = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
			$processed_row['data'][ $header_info['normalized'] ] = $value;
		}

		// Validate required fields
		if ( empty( $processed_row['data']['title'] ) ) {
			throw new \Exception( __( 'Title field is required and cannot be empty.', 'csv-page-generator' ) );
		}

		if ( empty( $processed_row['data']['description'] ) ) {
			throw new \Exception( __( 'Description field is required and cannot be empty.', 'csv-page-generator' ) );
		}

		return $processed_row;
	}

	/**
	 * Check if a row is empty.
	 *
	 * @param array $row Row data.
	 * @return bool True if row is empty.
	 */
	private function is_empty_row( array $row ) {
		foreach ( $row as $cell ) {
			if ( ! empty( trim( $cell ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get supported file encodings.
	 *
	 * @return array List of supported encodings.
	 */
	public function get_supported_encodings() {
		return $this->supported_encodings;
	}

	/**
	 * Validate CSV file before parsing.
	 *
	 * @param string $file_path Path to the CSV file.
	 * @return array Validation results.
	 */
	public function validate_file( $file_path ) {
		$validation = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		// Check file existence
		if ( ! file_exists( $file_path ) ) {
			$validation['valid'] = false;
			$validation['errors'][] = __( 'File does not exist.', 'csv-page-generator' );
			return $validation;
		}

		// Check file readability
		if ( ! is_readable( $file_path ) ) {
			$validation['valid'] = false;
			$validation['errors'][] = __( 'File is not readable.', 'csv-page-generator' );
			return $validation;
		}

		// Check file size
		$file_size = filesize( $file_path );
		$max_size = $this->settings['max_file_size'] ?? 10485760; // 10MB default

		if ( $file_size > $max_size ) {
			$validation['valid'] = false;
			$validation['errors'][] = sprintf(
				/* translators: 1: file size, 2: maximum allowed size */
				__( 'File size (%1$s) exceeds maximum allowed size (%2$s).', 'csv-page-generator' ),
				size_format( $file_size ),
				size_format( $max_size )
			);
		}

		// Check file extension
		$file_extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $file_extension ) {
			$validation['warnings'][] = sprintf(
				/* translators: %s: file extension */
				__( 'File extension is "%s", expected "csv".', 'csv-page-generator' ),
				$file_extension
			);
		}

		// Add file info
		$validation['info']['file_size'] = $file_size;
		$validation['info']['file_extension'] = $file_extension;
		$validation['info']['mime_type'] = mime_content_type( $file_path );

		return $validation;
	}
}
