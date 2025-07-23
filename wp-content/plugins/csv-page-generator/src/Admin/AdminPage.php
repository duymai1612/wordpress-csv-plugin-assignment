<?php
/**
 * Admin Page Class
 *
 * @package ReasonDigital\CSVPageGenerator\Admin
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Admin;

use ReasonDigital\CSVPageGenerator\Security\NonceManager;
use ReasonDigital\CSVPageGenerator\Utils\Logger;
use ReasonDigital\CSVPageGenerator\Admin\UploadHandler;

/**
 * Handles the admin interface for the CSV Page Generator plugin.
 *
 * Manages admin menus, pages, and basic admin functionality.
 */
class AdminPage {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Nonce manager instance.
	 *
	 * @var NonceManager
	 */
	private $nonce_manager;

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
	 * Upload handler instance.
	 *
	 * @var UploadHandler
	 */
	private $upload_handler;

	/**
	 * Constructor.
	 *
	 * @param string       $version Plugin version.
	 * @param NonceManager $nonce_manager Nonce manager instance.
	 * @param Logger       $logger Logger instance.
	 */
	public function __construct( $version, NonceManager $nonce_manager, Logger $logger ) {
		$this->version        = $version;
		$this->nonce_manager  = $nonce_manager;
		$this->logger         = $logger;
		$this->settings       = get_option( 'csv_page_generator_settings', array() );
		$this->upload_handler = new UploadHandler( $logger );
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Main menu page
		add_menu_page(
			__( 'CSV Page Generator', 'csv-page-generator' ),
			__( 'CSV Pages', 'csv-page-generator' ),
			'manage_options',
			'csv-page-generator',
			array( $this, 'display_main_page' ),
			'dashicons-media-spreadsheet',
			30
		);

		// Upload submenu
		add_submenu_page(
			'csv-page-generator',
			__( 'Upload CSV', 'csv-page-generator' ),
			__( 'Upload CSV', 'csv-page-generator' ),
			'manage_options',
			'csv-page-generator',
			array( $this, 'display_main_page' )
		);

		// Import history submenu
		add_submenu_page(
			'csv-page-generator',
			__( 'Import History', 'csv-page-generator' ),
			__( 'Import History', 'csv-page-generator' ),
			'manage_options',
			'csv-page-generator-history',
			array( $this, 'display_history_page' )
		);

		// Settings submenu
		add_submenu_page(
			'csv-page-generator',
			__( 'Settings', 'csv-page-generator' ),
			__( 'Settings', 'csv-page-generator' ),
			'manage_options',
			'csv-page-generator-settings',
			array( $this, 'display_settings_page' )
		);
	}

	/**
	 * Initialize admin settings.
	 */
	public function init_settings() {
		// Register settings
		register_setting(
			'csv_page_generator_settings',
			'csv_page_generator_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Add settings sections and fields
		$this->add_settings_sections();
	}

	/**
	 * Add settings sections and fields.
	 */
	private function add_settings_sections() {
		// File Upload Settings
		add_settings_section(
			'csv_page_generator_file_settings',
			__( 'File Upload Settings', 'csv-page-generator' ),
			array( $this, 'file_settings_section_callback' ),
			'csv_page_generator_settings'
		);

		add_settings_field(
			'max_file_size',
			__( 'Maximum File Size (bytes)', 'csv-page-generator' ),
			array( $this, 'max_file_size_callback' ),
			'csv_page_generator_settings',
			'csv_page_generator_file_settings'
		);

		add_settings_field(
			'max_rows',
			__( 'Maximum Rows per Import', 'csv-page-generator' ),
			array( $this, 'max_rows_callback' ),
			'csv_page_generator_settings',
			'csv_page_generator_file_settings'
		);

		// Page Generation Settings
		add_settings_section(
			'csv_page_generator_page_settings',
			__( 'Page Generation Settings', 'csv-page-generator' ),
			array( $this, 'page_settings_section_callback' ),
			'csv_page_generator_settings'
		);

		add_settings_field(
			'default_post_status',
			__( 'Default Page Status', 'csv-page-generator' ),
			array( $this, 'default_post_status_callback' ),
			'csv_page_generator_settings',
			'csv_page_generator_page_settings'
		);
	}

	/**
	 * Display the main admin page.
	 */
	public function display_main_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'csv-page-generator' ) );
		}

		// Include the main page template
		$template_path = CSV_PAGE_GENERATOR_PLUGIN_DIR . 'templates/admin/upload-form.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'CSV Page Generator', 'csv-page-generator' ) . '</h1>';
			echo '<p>' . esc_html__( 'Upload form template not found.', 'csv-page-generator' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Display the import history page.
	 */
	public function display_history_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'csv-page-generator' ) );
		}

		// Get import history data
		global $wpdb;
		$table_name = $wpdb->prefix . 'csv_page_generator_imports';

		// Handle pagination
		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		// Get total count for pagination
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$total_pages = ceil( $total_items / $per_page );

		// Get import records with pagination
		$imports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY started_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		// Include the history page template
		$template_path = CSV_PAGE_GENERATOR_PLUGIN_DIR . 'templates/admin/import-history.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			// Fallback: display basic history inline
			$this->display_history_fallback( $imports, $current_page, $total_pages, $total_items );
		}
	}

	/**
	 * Fallback method to display import history when template is missing.
	 */
	private function display_history_fallback( $imports, $current_page, $total_pages, $total_items ) {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import History', 'csv-page-generator' ) . '</h1>';

		if ( empty( $imports ) ) {
			echo '<p>' . esc_html__( 'No import records found.', 'csv-page-generator' ) . '</p>';
		} else {
			echo '<p>' . sprintf( esc_html__( 'Total imports: %d', 'csv-page-generator' ), $total_items ) . '</p>';
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'ID', 'csv-page-generator' ) . '</th>';
			echo '<th>' . esc_html__( 'Filename', 'csv-page-generator' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'csv-page-generator' ) . '</th>';
			echo '<th>' . esc_html__( 'Rows', 'csv-page-generator' ) . '</th>';
			echo '<th>' . esc_html__( 'Success', 'csv-page-generator' ) . '</th>';
			echo '<th>' . esc_html__( 'Failed', 'csv-page-generator' ) . '</th>';
			echo '<th>' . esc_html__( 'Date', 'csv-page-generator' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $imports as $import ) {
				echo '<tr>';
				echo '<td>' . esc_html( $import->id ) . '</td>';
				echo '<td>' . esc_html( $import->original_filename ) . '</td>';
				echo '<td><span class="status-' . esc_attr( $import->status ) . '">' . esc_html( ucfirst( $import->status ) ) . '</span></td>';
				echo '<td>' . esc_html( $import->total_rows ) . '</td>';
				echo '<td>' . esc_html( $import->successful_rows ) . '</td>';
				echo '<td>' . esc_html( $import->failed_rows ) . '</td>';
				echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $import->started_at ) ) ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * Display the settings page.
	 */
	public function display_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'csv-page-generator' ) );
		}

		// Include the settings page template
		$template_path = plugin_dir_path( dirname( __DIR__ ) ) . 'templates/admin/settings.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'CSV Page Generator Settings', 'csv-page-generator' ) . '</h1>';
			echo '<form method="post" action="options.php">';
			settings_fields( 'csv_page_generator_settings' );
			do_settings_sections( 'csv_page_generator_settings' );
			submit_button();
			echo '</form>';
			echo '</div>';
		}
	}

	/**
	 * Handle CSV file upload via AJAX.
	 */
	public function handle_csv_upload() {
		$this->upload_handler->handle_ajax_upload();
	}

	/**
	 * Get import progress via AJAX.
	 */
	public function get_import_progress() {
		$this->upload_handler->get_upload_progress();
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['max_file_size'] ) ) {
			$sanitized['max_file_size'] = absint( $input['max_file_size'] );
		}

		if ( isset( $input['max_rows'] ) ) {
			$sanitized['max_rows'] = absint( $input['max_rows'] );
		}

		if ( isset( $input['default_post_status'] ) ) {
			$allowed_statuses                 = array( 'draft', 'publish', 'private' );
			$sanitized['default_post_status'] = in_array( $input['default_post_status'], $allowed_statuses, true )
				? $input['default_post_status']
				: 'draft';
		}

		return $sanitized;
	}

	/**
	 * File settings section callback.
	 */
	public function file_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure file upload limits and restrictions.', 'csv-page-generator' ) . '</p>';
	}

	/**
	 * Page settings section callback.
	 */
	public function page_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure how pages are generated from CSV data.', 'csv-page-generator' ) . '</p>';
	}

	/**
	 * Max file size field callback.
	 */
	public function max_file_size_callback() {
		$value = $this->settings['max_file_size'] ?? 10485760;
		echo '<input type="number" name="csv_page_generator_settings[max_file_size]" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Maximum file size in bytes (default: 10MB = 10485760 bytes).', 'csv-page-generator' ) . '</p>';
	}

	/**
	 * Max rows field callback.
	 */
	public function max_rows_callback() {
		$value = $this->settings['max_rows'] ?? 10000;
		echo '<input type="number" name="csv_page_generator_settings[max_rows]" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Maximum number of rows to process in a single import.', 'csv-page-generator' ) . '</p>';
	}

	/**
	 * Default post status field callback.
	 */
	public function default_post_status_callback() {
		$value    = $this->settings['default_post_status'] ?? 'draft';
		$statuses = array(
			'draft'   => __( 'Draft', 'csv-page-generator' ),
			'publish' => __( 'Published', 'csv-page-generator' ),
			'private' => __( 'Private', 'csv-page-generator' ),
		);

		echo '<select name="csv_page_generator_settings[default_post_status]">';
		foreach ( $statuses as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $value, $status, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Default status for pages created from CSV imports.', 'csv-page-generator' ) . '</p>';
	}
}
