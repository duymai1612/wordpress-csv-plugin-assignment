<?php
/**
 * CSV Validator Class
 *
 * @package ReasonDigital\CSVPageGenerator\CSV
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\CSV;

use ReasonDigital\CSVPageGenerator\Utils\Logger;

/**
 * Validates CSV data and individual rows for WordPress page creation.
 *
 * Provides comprehensive validation for CSV data including field validation,
 * content sanitization, and WordPress-specific checks.
 */
class Validator {

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
	 * Validation rules.
	 *
	 * @var array
	 */
	private $validation_rules;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->settings = get_option( 'csv_page_generator_settings', array() );
		$this->setup_validation_rules();
	}

	/**
	 * Set up validation rules for CSV fields.
	 */
	private function setup_validation_rules() {
		$this->validation_rules = array(
			'title' => array(
				'required'   => true,
				'max_length' => 255,
				'min_length' => 1,
				'sanitize'   => 'sanitize_text_field',
			),
			'description' => array(
				'required'   => true,
				'max_length' => 65535,
				'min_length' => 1,
				'sanitize'   => 'wp_kses_post',
			),
			'slug' => array(
				'required'   => false,
				'max_length' => 200,
				'pattern'    => '/^[a-z0-9-]+$/',
				'sanitize'   => 'sanitize_title',
			),
			'status' => array(
				'required'   => false,
				'allowed'    => array( 'draft', 'publish', 'private', 'pending' ),
				'default'    => 'draft',
				'sanitize'   => 'sanitize_text_field',
			),
			'categories' => array(
				'required'   => false,
				'max_length' => 500,
				'sanitize'   => 'sanitize_text_field',
			),
			'meta_description' => array(
				'required'   => false,
				'max_length' => 160,
				'sanitize'   => 'sanitize_text_field',
			),
			'featured_image_url' => array(
				'required'   => false,
				'max_length' => 2048,
				'pattern'    => '/^https?:\/\/.+\.(jpg|jpeg|png|gif|webp)$/i',
				'sanitize'   => 'esc_url_raw',
			),
		);
	}

	/**
	 * Validate a single CSV row.
	 *
	 * @param array $row_data Row data to validate.
	 * @param int   $row_number Row number for error reporting.
	 * @return array Validation result with sanitized data.
	 */
	public function validate_row( array $row_data, $row_number = 0 ) {
		$result = array(
			'valid'      => true,
			'errors'     => array(),
			'warnings'   => array(),
			'data'       => array(),
			'row_number' => $row_number,
		);

		foreach ( $this->validation_rules as $field => $rules ) {
			$value = $row_data[ $field ] ?? '';
			$field_result = $this->validate_field( $field, $value, $rules );

			if ( ! $field_result['valid'] ) {
				$result['valid'] = false;
				$result['errors'] = array_merge( $result['errors'], $field_result['errors'] );
			}

			if ( ! empty( $field_result['warnings'] ) ) {
				$result['warnings'] = array_merge( $result['warnings'], $field_result['warnings'] );
			}

			$result['data'][ $field ] = $field_result['value'];
		}

		// Additional cross-field validation
		$cross_validation = $this->validate_cross_fields( $result['data'] );
		if ( ! $cross_validation['valid'] ) {
			$result['valid'] = false;
			$result['errors'] = array_merge( $result['errors'], $cross_validation['errors'] );
		}

		// Log validation results for debugging
		if ( ! $result['valid'] ) {
			$this->logger->warning( 'Row validation failed', array(
				'row_number' => $row_number,
				'errors'     => $result['errors'],
			) );
		}

		return $result;
	}

	/**
	 * Validate a single field.
	 *
	 * @param string $field_name Field name.
	 * @param mixed  $value Field value.
	 * @param array  $rules Validation rules.
	 * @return array Validation result.
	 */
	private function validate_field( $field_name, $value, array $rules ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'value'    => $value,
		);

		// Handle empty values
		if ( empty( $value ) ) {
			if ( ! empty( $rules['required'] ) ) {
				$result['valid'] = false;
				$result['errors'][] = sprintf(
					/* translators: %s: field name */
					__( 'Field "%s" is required and cannot be empty.', 'csv-page-generator' ),
					$field_name
				);
				return $result;
			}

			// Use default value if available
			if ( isset( $rules['default'] ) ) {
				$result['value'] = $rules['default'];
			}

			return $result;
		}

		// Sanitize value
		if ( ! empty( $rules['sanitize'] ) && function_exists( $rules['sanitize'] ) ) {
			$result['value'] = call_user_func( $rules['sanitize'], $value );
		}

		// Length validation
		if ( isset( $rules['max_length'] ) ) {
			$length = mb_strlen( $result['value'] );
			if ( $length > $rules['max_length'] ) {
				$result['valid'] = false;
				$result['errors'][] = sprintf(
					/* translators: 1: field name, 2: current length, 3: maximum length */
					__( 'Field "%1$s" is too long (%2$d characters). Maximum allowed: %3$d characters.', 'csv-page-generator' ),
					$field_name,
					$length,
					$rules['max_length']
				);
			}
		}

		if ( isset( $rules['min_length'] ) ) {
			$length = mb_strlen( $result['value'] );
			if ( $length < $rules['min_length'] ) {
				$result['valid'] = false;
				$result['errors'][] = sprintf(
					/* translators: 1: field name, 2: current length, 3: minimum length */
					__( 'Field "%1$s" is too short (%2$d characters). Minimum required: %3$d characters.', 'csv-page-generator' ),
					$field_name,
					$length,
					$rules['min_length']
				);
			}
		}

		// Pattern validation
		if ( ! empty( $rules['pattern'] ) && ! preg_match( $rules['pattern'], $result['value'] ) ) {
			$result['valid'] = false;
			$result['errors'][] = sprintf(
				/* translators: %s: field name */
				__( 'Field "%s" contains invalid characters or format.', 'csv-page-generator' ),
				$field_name
			);
		}

		// Allowed values validation
		if ( ! empty( $rules['allowed'] ) && ! in_array( $result['value'], $rules['allowed'], true ) ) {
			$result['valid'] = false;
			$result['errors'][] = sprintf(
				/* translators: 1: field name, 2: provided value, 3: allowed values */
				__( 'Field "%1$s" has invalid value "%2$s". Allowed values: %3$s.', 'csv-page-generator' ),
				$field_name,
				$result['value'],
				implode( ', ', $rules['allowed'] )
			);
		}

		// Field-specific validation
		$specific_validation = $this->validate_field_specific( $field_name, $result['value'] );
		if ( ! $specific_validation['valid'] ) {
			$result['valid'] = false;
			$result['errors'] = array_merge( $result['errors'], $specific_validation['errors'] );
		}

		if ( ! empty( $specific_validation['warnings'] ) ) {
			$result['warnings'] = array_merge( $result['warnings'], $specific_validation['warnings'] );
		}

		return $result;
	}

	/**
	 * Field-specific validation logic.
	 *
	 * @param string $field_name Field name.
	 * @param mixed  $value Field value.
	 * @return array Validation result.
	 */
	private function validate_field_specific( $field_name, $value ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);

		switch ( $field_name ) {
			case 'title':
				// Check for duplicate titles
				if ( $this->title_exists( $value ) ) {
					$result['warnings'][] = sprintf(
						/* translators: %s: title */
						__( 'A page with title "%s" already exists. A unique slug will be generated.', 'csv-page-generator' ),
						$value
					);
				}
				break;

			case 'slug':
				if ( ! empty( $value ) ) {
					// Check for duplicate slugs
					if ( $this->slug_exists( $value ) ) {
						$result['warnings'][] = sprintf(
							/* translators: %s: slug */
							__( 'A page with slug "%s" already exists. A unique slug will be generated.', 'csv-page-generator' ),
							$value
						);
					}
				}
				break;

			case 'featured_image_url':
				if ( ! empty( $value ) ) {
					// Validate URL format
					if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
						$result['valid'] = false;
						$result['errors'][] = __( 'Featured image URL is not a valid URL.', 'csv-page-generator' );
					}
				}
				break;

			case 'categories':
				if ( ! empty( $value ) ) {
					// Validate category names
					$categories = array_map( 'trim', explode( ',', $value ) );
					foreach ( $categories as $category ) {
						if ( mb_strlen( $category ) > 50 ) {
							$result['warnings'][] = sprintf(
								/* translators: %s: category name */
								__( 'Category name "%s" is very long and may be truncated.', 'csv-page-generator' ),
								$category
							);
						}
					}
				}
				break;

			case 'meta_description':
				if ( ! empty( $value ) && mb_strlen( $value ) > 160 ) {
					$result['warnings'][] = __( 'Meta description is longer than recommended 160 characters for SEO.', 'csv-page-generator' );
				}
				break;
		}

		return $result;
	}

	/**
	 * Validate cross-field relationships.
	 *
	 * @param array $data Row data.
	 * @return array Validation result.
	 */
	private function validate_cross_fields( array $data ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Generate slug if not provided
		if ( empty( $data['slug'] ) && ! empty( $data['title'] ) ) {
			$data['slug'] = sanitize_title( $data['title'] );
		}

		// Validate slug uniqueness
		if ( ! empty( $data['slug'] ) && $this->slug_exists( $data['slug'] ) ) {
			// This will be handled during page creation with unique slug generation
		}

		return $result;
	}

	/**
	 * Check if a title already exists.
	 *
	 * @param string $title Page title.
	 * @return bool True if title exists.
	 */
	private function title_exists( $title ) {
		$existing_post = get_page_by_title( $title, OBJECT, 'page' );
		return ! empty( $existing_post );
	}

	/**
	 * Check if a slug already exists.
	 *
	 * @param string $slug Page slug.
	 * @return bool True if slug exists.
	 */
	private function slug_exists( $slug ) {
		$existing_post = get_page_by_path( $slug, OBJECT, 'page' );
		return ! empty( $existing_post );
	}

	/**
	 * Validate entire CSV data set.
	 *
	 * @param array $csv_data Parsed CSV data.
	 * @return array Validation summary.
	 */
	public function validate_csv_data( array $csv_data ) {
		$summary = array(
			'total_rows'    => count( $csv_data['rows'] ),
			'valid_rows'    => 0,
			'invalid_rows'  => 0,
			'warnings'      => 0,
			'errors'        => array(),
			'row_results'   => array(),
		);

		foreach ( $csv_data['rows'] as $index => $row ) {
			$row_data = $row['data'];
			$row_number = $row['row_number'];

			$validation_result = $this->validate_row( $row_data, $row_number );
			$summary['row_results'][ $index ] = $validation_result;

			if ( $validation_result['valid'] ) {
				$summary['valid_rows']++;
			} else {
				$summary['invalid_rows']++;
				$summary['errors'] = array_merge( $summary['errors'], $validation_result['errors'] );
			}

			if ( ! empty( $validation_result['warnings'] ) ) {
				$summary['warnings'] += count( $validation_result['warnings'] );
			}
		}

		$this->logger->info( 'CSV data validation completed', array(
			'total_rows'   => $summary['total_rows'],
			'valid_rows'   => $summary['valid_rows'],
			'invalid_rows' => $summary['invalid_rows'],
			'warnings'     => $summary['warnings'],
		) );

		return $summary;
	}

	/**
	 * Get validation rules for a specific field.
	 *
	 * @param string $field_name Field name.
	 * @return array|null Validation rules or null if field not found.
	 */
	public function get_field_rules( $field_name ) {
		return $this->validation_rules[ $field_name ] ?? null;
	}

	/**
	 * Get all validation rules.
	 *
	 * @return array All validation rules.
	 */
	public function get_all_rules() {
		return $this->validation_rules;
	}

	/**
	 * Add custom validation rule.
	 *
	 * @param string $field_name Field name.
	 * @param array  $rules Validation rules.
	 */
	public function add_validation_rule( $field_name, array $rules ) {
		$this->validation_rules[ $field_name ] = $rules;
	}
}
