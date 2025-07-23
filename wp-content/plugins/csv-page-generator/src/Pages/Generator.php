<?php
/**
 * Page Generator Class
 *
 * @package ReasonDigital\CSVPageGenerator\Pages
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Pages;

use ReasonDigital\CSVPageGenerator\Utils\Logger;

/**
 * Handles WordPress page creation from CSV data.
 *
 * Provides robust page generation with duplicate handling,
 * slug generation, and comprehensive error handling.
 */
class Generator {

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
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger   = $logger;
		$this->settings = get_option( 'csv_page_generator_settings', array() );
	}

	/**
	 * Create a WordPress page from CSV data.
	 *
	 * @param array $page_data Page data array.
	 * @return int Page ID.
	 * @throws \Exception If page creation fails.
	 */
	public function create_page( array $page_data ) {
		// Validate required fields
		if ( empty( $page_data['post_title'] ) ) {
			throw new \Exception( __( 'Page title is required.', 'csv-page-generator' ) );
		}

		if ( empty( $page_data['post_content'] ) ) {
			throw new \Exception( __( 'Page content is required.', 'csv-page-generator' ) );
		}

		// Prepare page data
		$prepared_data = $this->prepare_page_data( $page_data );

		$this->logger->debug(
			'Creating page',
			array(
				'title' => $prepared_data['post_title'],
				'slug'  => $prepared_data['post_name'],
			)
		);

		// Create the page
		$page_id = wp_insert_post( $prepared_data, true );

		if ( is_wp_error( $page_id ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to create page: %s', 'csv-page-generator' ),
					$page_id->get_error_message()
				)
			);
		}

		// Handle post-creation tasks
		$this->handle_post_creation_tasks( $page_id, $page_data );

		$this->logger->info(
			'Page created successfully',
			array(
				'page_id' => $page_id,
				'title'   => $prepared_data['post_title'],
				'slug'    => get_post_field( 'post_name', $page_id ),
			)
		);

		return $page_id;
	}

	/**
	 * Prepare page data for WordPress insertion.
	 *
	 * @param array $page_data Raw page data.
	 * @return array Prepared page data.
	 */
	private function prepare_page_data( array $page_data ) {
		// Set defaults
		$defaults = array(
			'post_type'      => 'page',
			'post_status'    => 'draft',
			'post_author'    => get_current_user_id(),
			'post_excerpt'   => '',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);

		$prepared_data = wp_parse_args( $page_data, $defaults );

		// Sanitize title and content
		$prepared_data['post_title']   = sanitize_text_field( $prepared_data['post_title'] );
		$prepared_data['post_content'] = wp_kses_post( $prepared_data['post_content'] );

		// Generate unique slug if not provided or if duplicate exists
		if ( empty( $prepared_data['post_name'] ) ) {
			$prepared_data['post_name'] = $this->generate_unique_slug( $prepared_data['post_title'] );
		} else {
			$prepared_data['post_name'] = $this->ensure_unique_slug( $prepared_data['post_name'] );
		}

		// Validate post status
		$valid_statuses = array( 'draft', 'publish', 'private', 'pending' );
		if ( ! in_array( $prepared_data['post_status'], $valid_statuses, true ) ) {
			$prepared_data['post_status'] = 'draft';
		}

		// Validate post author
		if ( ! get_userdata( $prepared_data['post_author'] ) ) {
			$prepared_data['post_author'] = get_current_user_id();
		}

		return $prepared_data;
	}

	/**
	 * Generate a unique slug from title.
	 *
	 * @param string $title Page title.
	 * @return string Unique slug.
	 */
	private function generate_unique_slug( $title ) {
		$base_slug = sanitize_title( $title );
		return $this->ensure_unique_slug( $base_slug );
	}

	/**
	 * Ensure slug is unique by appending number if necessary.
	 *
	 * @param string $slug Desired slug.
	 * @return string Unique slug.
	 */
	private function ensure_unique_slug( $slug ) {
		$original_slug = $slug;
		$counter       = 1;

		while ( $this->slug_exists( $slug ) ) {
			$slug = $original_slug . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Check if a slug already exists.
	 *
	 * @param string $slug Slug to check.
	 * @return bool True if slug exists.
	 */
	private function slug_exists( $slug ) {
		$existing_post = get_page_by_path( $slug, OBJECT, 'page' );
		return ! empty( $existing_post );
	}

	/**
	 * Handle post-creation tasks like meta data and featured images.
	 *
	 * @param int   $page_id Page ID.
	 * @param array $page_data Original page data.
	 */
	private function handle_post_creation_tasks( $page_id, array $page_data ) {
		// Handle meta data
		if ( ! empty( $page_data['meta_input'] ) && is_array( $page_data['meta_input'] ) ) {
			foreach ( $page_data['meta_input'] as $meta_key => $meta_value ) {
				update_post_meta( $page_id, $meta_key, $meta_value );
			}
		}

		// Handle featured image from URL
		if ( ! empty( $page_data['meta_input']['_csv_featured_image_url'] ) ) {
			$this->handle_featured_image( $page_id, $page_data['meta_input']['_csv_featured_image_url'] );
		}

		// Handle categories (convert to page tags)
		if ( ! empty( $page_data['tags_input'] ) ) {
			wp_set_post_tags( $page_id, $page_data['tags_input'] );
		}

		// Set custom template if specified
		if ( ! empty( $page_data['page_template'] ) ) {
			update_post_meta( $page_id, '_wp_page_template', $page_data['page_template'] );
		}
	}

	/**
	 * Handle featured image from URL.
	 *
	 * @param int    $page_id Page ID.
	 * @param string $image_url Image URL.
	 */
	private function handle_featured_image( $page_id, $image_url ) {
		try {
			// Validate URL
			if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
				throw new \Exception( 'Invalid image URL format.' );
			}

			// Check if image is already in media library
			$existing_attachment = $this->find_attachment_by_url( $image_url );

			if ( $existing_attachment ) {
				set_post_thumbnail( $page_id, $existing_attachment );
				$this->logger->debug(
					'Used existing attachment for featured image',
					array(
						'page_id'       => $page_id,
						'attachment_id' => $existing_attachment,
						'image_url'     => $image_url,
					)
				);
				return;
			}

			// Download and create attachment
			$attachment_id = $this->download_and_create_attachment( $image_url, $page_id );

			if ( $attachment_id ) {
				set_post_thumbnail( $page_id, $attachment_id );
				$this->logger->info(
					'Featured image set successfully',
					array(
						'page_id'       => $page_id,
						'attachment_id' => $attachment_id,
						'image_url'     => $image_url,
					)
				);
			}
		} catch ( \Exception $e ) {
			$this->logger->warning(
				'Failed to set featured image',
				array(
					'page_id'   => $page_id,
					'image_url' => $image_url,
					'error'     => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Find existing attachment by URL.
	 *
	 * @param string $url Image URL.
	 * @return int|false Attachment ID or false if not found.
	 */
	private function find_attachment_by_url( $url ) {
		global $wpdb;

		$filename = basename( $url );

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_wp_attached_file' 
			AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $filename )
			)
		);

		return $attachment_id ? (int) $attachment_id : false;
	}

	/**
	 * Download image and create attachment.
	 *
	 * @param string $image_url Image URL.
	 * @param int    $parent_id Parent post ID.
	 * @return int|false Attachment ID or false on failure.
	 */
	private function download_and_create_attachment( $image_url, $parent_id ) {
		// Include required WordPress functions
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Download the image
		$temp_file = download_url( $image_url );

		if ( is_wp_error( $temp_file ) ) {
			throw new \Exception( 'Failed to download image: ' . $temp_file->get_error_message() );
		}

		// Prepare file array
		$file_array = array(
			'name'     => basename( $image_url ),
			'tmp_name' => $temp_file,
		);

		// Create attachment
		$attachment_id = media_handle_sideload( $file_array, $parent_id );

		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			unlink( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			throw new \Exception( 'Failed to create attachment: ' . $attachment_id->get_error_message() );
		}

		return $attachment_id;
	}

	/**
	 * Bulk create pages from CSV data.
	 *
	 * @param array $pages_data Array of page data.
	 * @param array $options Processing options.
	 * @return array Results with created and failed pages.
	 */
	public function bulk_create_pages( array $pages_data, array $options = array() ) {
		$results = array(
			'created' => array(),
			'failed'  => array(),
			'total'   => count( $pages_data ),
		);

		$batch_size  = $options['batch_size'] ?? 50;
		$skip_errors = $options['skip_errors'] ?? true;

		// Process in batches
		$batches = array_chunk( $pages_data, $batch_size, true );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $index => $page_data ) {
				try {
					$page_id              = $this->create_page( $page_data );
					$results['created'][] = array(
						'index'   => $index,
						'page_id' => $page_id,
						'title'   => $page_data['post_title'],
					);
				} catch ( \Exception $e ) {
					$results['failed'][] = array(
						'index' => $index,
						'error' => $e->getMessage(),
						'data'  => $page_data,
					);

					if ( ! $skip_errors ) {
						throw $e;
					}
				}
			}

			// Memory cleanup between batches
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}

		$this->logger->info(
			'Bulk page creation completed',
			array(
				'total_pages'   => $results['total'],
				'created_pages' => count( $results['created'] ),
				'failed_pages'  => count( $results['failed'] ),
			)
		);

		return $results;
	}

	/**
	 * Delete pages created from a specific import.
	 *
	 * @param int $import_id Import ID.
	 * @return array Deletion results.
	 */
	public function delete_import_pages( $import_id ) {
		$pages = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => '_csv_page_generator_source',
						'value' => $import_id,
					),
				),
			)
		);

		$results = array(
			'deleted' => 0,
			'failed'  => 0,
			'total'   => count( $pages ),
		);

		foreach ( $pages as $page ) {
			$deleted = wp_delete_post( $page->ID, true );

			if ( $deleted ) {
				++$results['deleted'];
			} else {
				++$results['failed'];
			}
		}

		$this->logger->info(
			'Import pages deletion completed',
			array(
				'import_id'     => $import_id,
				'total_pages'   => $results['total'],
				'deleted_pages' => $results['deleted'],
				'failed_pages'  => $results['failed'],
			)
		);

		return $results;
	}
}
