<?php
/**
 * Main Plugin Class
 *
 * @package ReasonDigital\CSVPageGenerator\Core
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Core;

use ReasonDigital\CSVPageGenerator\Admin\AdminPage;
use ReasonDigital\CSVPageGenerator\Security\NonceManager;
use ReasonDigital\CSVPageGenerator\Utils\Logger;

/**
 * Main plugin class that orchestrates all plugin functionality.
 *
 * This class follows the Singleton pattern to ensure only one instance
 * of the plugin is running at any time.
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin loader instance.
	 *
	 * @var Loader
	 */
	private $loader;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Nonce manager instance.
	 *
	 * @var NonceManager
	 */
	private $nonce_manager;

	/**
	 * Admin page instance.
	 *
	 * @var AdminPage
	 */
	private $admin_page;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->version = defined( 'CSV_PAGE_GENERATOR_VERSION' ) ? CSV_PAGE_GENERATOR_VERSION : '1.0.0';
		$this->loader = new Loader();
		$this->logger = new Logger();
		$this->nonce_manager = new NonceManager();
	}

	/**
	 * Get the singleton instance of the plugin.
	 *
	 * @return Plugin The plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * This method sets up all the hooks and initializes the plugin components.
	 */
	public function init() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->run();

		// Log plugin initialization
		$this->logger->info( 'CSV Page Generator plugin initialized successfully.' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		// Load admin functionality
		if ( is_admin() ) {
			$this->admin_page = new AdminPage( $this->get_version(), $this->nonce_manager, $this->logger );
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'csv-page-generator',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		// Admin menu and pages
		$this->loader->add_action( 'admin_menu', $this->admin_page, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $this->admin_page, 'init_settings' );

		// Admin scripts and styles
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_scripts' );

		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_csv_page_generator_upload', $this->admin_page, 'handle_csv_upload' );
		$this->loader->add_action( 'wp_ajax_csv_page_generator_progress', $this->admin_page, 'get_import_progress' );

		// Admin notices
		$this->loader->add_action( 'admin_notices', $this, 'display_admin_notices' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		// Public scripts and styles
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_scripts' );

		// Template filters
		$this->loader->add_filter( 'template_include', $this, 'load_csv_page_template' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	private function run() {
		$this->loader->run();
	}

	/**
	 * Enqueue admin area stylesheets.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_styles( $hook_suffix ) {
		// Only load on our admin pages
		if ( ! $this->is_csv_generator_admin_page( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'csv-page-generator-admin',
			CSV_PAGE_GENERATOR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue admin area scripts.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// Only load on our admin pages
		if ( ! $this->is_csv_generator_admin_page( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_script(
			'csv-page-generator-admin',
			CSV_PAGE_GENERATOR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localize script for AJAX
		wp_localize_script(
			'csv-page-generator-admin',
			'csvPageGenerator',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'adminUrl'   => admin_url( '' ), // Fix: Add missing adminUrl
				'uploadNonce' => $this->nonce_manager->create_nonce( 'csv_page_generator_upload' ),
				'progressNonce' => $this->nonce_manager->create_nonce( 'csv_page_generator_progress' ),
				'strings'    => array(
					'uploading'     => __( 'Uploading...', 'csv-page-generator' ),
					'processing'    => __( 'Processing...', 'csv-page-generator' ),
					'complete'      => __( 'Complete!', 'csv-page-generator' ),
					'error'         => __( 'Error occurred', 'csv-page-generator' ),
					'confirmDelete' => __( 'Are you sure you want to delete this import?', 'csv-page-generator' ),
				),
			)
		);
	}

	/**
	 * Enqueue public-facing stylesheets.
	 */
	public function enqueue_public_styles() {
		wp_enqueue_style(
			'csv-page-generator-public',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/dist/css/frontend-style.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue public-facing scripts.
	 */
	public function enqueue_public_scripts() {
		wp_enqueue_script(
			'csv-page-generator-public',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/dist/js/frontend.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Check if current admin page belongs to our plugin.
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return bool True if it's our admin page.
	 */
	private function is_csv_generator_admin_page( $hook_suffix ) {
		$our_pages = array(
			'toplevel_page_csv-page-generator',
			'csv-page-generator_page_csv-page-generator-settings',
			'csv-page-generator_page_csv-page-generator-history',
			// WordPress uses menu title for hook suffix, so we need both variations
			'csv-pages_page_csv-page-generator-settings',
			'csv-pages_page_csv-page-generator-history',
		);

		return in_array( $hook_suffix, $our_pages, true );
	}

	/**
	 * Load custom template for CSV-generated pages.
	 *
	 * @param string $template The path of the template to include.
	 * @return string The template path.
	 */
	public function load_csv_page_template( $template ) {
		if ( is_page() && $this->is_csv_generated_page() ) {
			$custom_template = plugin_dir_path( dirname( __DIR__ ) ) . 'templates/public/csv-page-template.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}

	/**
	 * Check if the current page was generated from CSV.
	 *
	 * @return bool True if the page was generated from CSV.
	 */
	private function is_csv_generated_page() {
		global $post;
		if ( ! $post ) {
			return false;
		}

		$csv_generated = get_post_meta( $post->ID, '_csv_page_generator_source', true );
		return ! empty( $csv_generated );
	}

	/**
	 * Display admin notices.
	 */
	public function display_admin_notices() {
		// Display any stored admin notices
		$notices = get_transient( 'csv_page_generator_admin_notices' );
		if ( $notices ) {
			foreach ( $notices as $notice ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $notice['type'] ),
					esc_html( $notice['message'] )
				);
			}
			delete_transient( 'csv_page_generator_admin_notices' );
		}
	}

	/**
	 * Get the version number of the plugin.
	 *
	 * @return string The version number.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get the logger instance.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Get the nonce manager instance.
	 *
	 * @return NonceManager The nonce manager instance.
	 */
	public function get_nonce_manager() {
		return $this->nonce_manager;
	}
}
